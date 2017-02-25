<?php

namespace Drupal\br_forms\Plugin\rest\resource;

use Drupal\br_forms\EloquaServiceInterface;
use Drupal\mm_rest\Model\ResponseModelFactory;
use Drupal\mm_rest\Plugin\ResourceBase;
use Drupal\mm_rest\Plugin\RestEntityProcessorManager;
use Drupal\rest\ResourceResponse;
use EloquaForms\ClientException;
use EloquaForms\Data\Validation\IsRequiredCondition;
use GuzzleHttp\Exception\ConnectException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\mm_rest\CacheableMetaDataCollectorInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "br_eloqua:v1",
 *   label = @Translation("Br eloqua form API (v1)"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/{domain}/{language}/eloquaforms",
 *     "https://www.drupal.org/link-relations/create" = "/api/v1/{domain}/{language}/eloquaforms",
 *   }
 * )
 */
class EloquaRestResource extends ResourceBase {

  /**
   * The eloqua service.
   *
   * @var \Drupal\br_forms\EloquaServiceInterface
   */
  protected $eloquaService;

  /**
   * A list of errors.
   *
   * @var array
   */
  protected $errors;

  /**
   * A list of remote form fields.
   *
   * @var array
   */
  protected $remoteFormFields;

  /**
   * The response model factory.
   *
   * @var \Drupal\mm_rest\Model\ResponseModelFactory
   */
  protected $responseModelFactory;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\mm_rest\Plugin\RestEntityProcessorManager $entity_processor
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   * @param \Drupal\mm_rest\CacheableMetaDataCollectorInterface $cacheable_metadata_collector
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, Request $request, RestEntityProcessorManager $entity_processor, ConfigFactoryInterface $configFactory, CacheableMetaDataCollectorInterface $cacheable_metadata_collector, EloquaServiceInterface $eloqua_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, $request, $entity_processor, $configFactory, $cacheable_metadata_collector);

    $this->eloquaService = $eloqua_service;
    $this->errors = [];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('mm_rest'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('plugin.manager.mm_rest_entity_processor'),
      $container->get('config.factory'),
      $container->get('mm_rest.cacheable_metadata_collector'),
      $container->get('br_forms.eloqua')
    );
  }

  /**
   * Responds to POST requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post($domain, $language, $form_submission, Request $request) {
    $variables = [];
    $message = '%type: @message in %function (line %line of %file). LONG MESSAGE: @long_message';

    try {
      $submit_form = $error_noticed = FALSE;
      $config = $this->config->get('br_forms.eloqua');

      /** @var \Drupal\br_forms\EloquaService $eloqua_service */
      $eloqua_service = \Drupal::service('br_forms.eloqua');
      $client = $this->eloquaService->getClient();

      /** @var \EloquaForms\Data\Form $form */
      $form = $client->getForm();

      $form_config_key = "forms.$domain.{$form_submission['form_id']}";
      $form_id = $config->get($form_config_key, FALSE);
      if ($form_id && !empty($form_submission['fields'])) {

        $form->setFormId($form_id);
        $this->remoteFormFields = $this->eloquaService->getRemoteEloquaFormFields($form_id);

        foreach ($form_submission['fields'] as $field => $value) {
          $config_key = "fields.$domain.{$form_submission['form_id']}.$field";

          // Send field values only if they were configured in drupal.
          $field_id = $config->get($config_key, FALSE);
          if ($field_id) {

            $failed = $this->validateFieldUserSubmittedData($field_id, $field, $value);
            if ($failed) {
              $error_noticed = TRUE;
            }

            $submit_form = TRUE;
            $form->addFieldByName($field_id, $value);
          }
        }

        // Post only if we have fields to post.
        if ($submit_form && $error_noticed === FALSE) {
          // @todo Remove the posting of the form once the cron functionality has been approved.
          // The posting will be done via cron.
          // $form->post();

          // @todo enable or disable once approved.
          // Save to database.
          $data_to_save = [
            'html_form_id' => $form_submission['form_id'],
            'langcode' => $language,
            'remote_data' => $form_submission['fields'],
            'domain' => $domain,
            'remote_form_id' => $form_id,
            'uid' => \Drupal::currentUser()->id(),
          ];

          $eloqua_service->createAndSaveEloquaSubmittedData($data_to_save);

          $data['success'] = $this->t('The data has been successfully submitted.');
        }
        else {
          $data['error'] = [
            'code' => 'error.form.validation',
            'message' => 'Not all fields are filled correctly.',
            'fields' => $this->errors,
          ];

          $responseModel = $this->getResponseModelFactory()->createFromContent($data);
          $responseModel->setStatusCode(400);
          $resourceResponse = new ResourceResponse($responseModel);
          return $resourceResponse;
        }
      }
      else {
        if (!$form_id) {
          $data['error'] = [
            'code' => 'error.form',
            'message' => $this->t('Please contact the site administrator to configure the forms. The form submitted is not configured.'),
          ];
        }
        elseif (empty($form_submission['fields'])) {
          $data['error'] = [
            'code' => 'error.form',
            'message' => $this->t('No fields have been submitted.'),
          ];
        }

        $responseModel = $this->getResponseModelFactory()->createFromContent($data);
        $responseModel->setStatusCode(403);
        $resourceResponse = new ResourceResponse($responseModel);
        return $resourceResponse;
      }

      $this->requestData = $data;

    }
    catch (ClientException $e) {
      $variables['@long_message'] = $e->getLongMessage();
      watchdog_exception('br_forms', $e, $message, $variables);
      throw new HttpException($e->getCode(), $e->getMessage(), $e);
    }
    catch (ConnectException $e) {
      $variables['@long_message'] = $e->getLongMessage();
      watchdog_exception('br_forms', $e, $message, $variables);
      throw new HttpException($e->getCode(), $e->getMessage(), $e);
    }
    catch (BadRequestHttpException $e) {
      throw new BadRequestHttpException('bad request');
    }
    catch (\Exception $e) {
      if (method_exists($e, 'getLongMessage')) {
        $variables['@long_message'] = $e->getLongMessage();
      }

      watchdog_exception('br_forms', $e, $message, $variables);
      throw new HttpException($e->getCode(), $e->getMessage(), $e);
    }

    return $this->responseData();
  }

  /**
   * {@inheritdoc}
   */
  protected function responseData() {
    return $this->requestData;
  }

  /**
   * Validate submitted data.
   *
   * @param string $field_id
   *   The remote field ID.
   * @param string $field
   *   The html field ID.
   * @param string $value
   *   The user submitted value.
   *
   * @return bool
   *   TRUE/FALSE if validation error noticed.
   */
  protected function validateFieldUserSubmittedData($field_id, $field, $value) {
    $error_noticed = FALSE;

    /** @var \EloquaForms\Data\FieldElement $field_element */
    $field_element = $this->remoteFormFields[$field_id];
    $field_validations = $field_element->getValidations();

    if (!empty($field_validations)) {
      /** @var \EloquaForms\Data\Validation\Validation $validation */
      foreach ($field_validations as $validation) {
        if (!($validation instanceof IsRequiredCondition) && !$validation->validate($value)) {
          $error_noticed = TRUE;
          $this->errors[] = [
            'field' => $field,
            'message' => $validation->getMessage(),
          ];
        }
      }
    }

    return $error_noticed;
  }

  /**
   * Get the resource response factory.
   *
   * @return \Drupal\mm_rest\Model\ResponseModelFactory
   *   The response model factory.
   */
  public function getResponseModelFactory() {
    if (!isset($this->responseModelFactory)) {
      $this->responseModelFactory = ResponseModelFactory::createFactory();
    }

    return $this->responseModelFactory;
  }

}
