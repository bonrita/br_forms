<?php

namespace Drupal\br_forms;

use Drupal\br_forms\Entity\EloquaSubmission;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Language\LanguageManagerInterface;
use EloquaForms\ClientException;
use EloquaForms\Data\Validation\IsRequiredCondition;
use EloquaForms\Data\Validation\TextLengthCondition;
use GuzzleHttp\Exception\ConnectException;
use EloquaForms\Client;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Class EloquaServiceBase.
 *
 * @package Drupal\br_forms
 */
abstract class EloquaServiceBase implements EloquaServiceInterface {

  /**
   * The exception message template.
   */
  const MESSAGE = '%type: @message in %function (line %line of %file). LONG MESSAGE: @long_message';

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Eloqua forms client.
   *
   * @var \EloquaForms\Client
   */
  protected $client;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * EloquaServiceBase constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(LanguageManagerInterface $language_manager, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnsubmittedEloquaforms() {
    $entities = $this->entityTypeManager
      ->getStorage('eloqua_submission')
      ->loadByProperties(array('published' => 0));

    return $entities;
  }

  /**
   * Validate eloqua credentials.
   *
   * @param $siteName
   * @param $username
   * @param $password
   * @param $host
   * @return bool|string
   */
  public function validateEloquaCredentials($siteName, $username, $password, $host) {
    $valid = TRUE;
    $client = new Client();
    $client
      ->setCredentials($siteName, $username, $password)
      ->setHost($host);

    try {
      $client->getForms()->get();
      $this->client = $client;
    } catch (\Exception $e) {
      return $e->getMessage();
    }

    return $valid;
  }

  /**
   * {@inheritdoc}
   */
  public function getSystemHtmlForms() {
    $forms = [];
    $file_path = $this->getHtmlFormsFilePath();

    if (file_exists($file_path)) {
      $file_content = file_get_contents($file_path);
      $forms = Yaml::parse($file_content);
    }

    return $forms;
  }

  /**
   * {@inheritdoc}
   */
  public function getSystemHtmlFormFields($form_id) {
    $fields = [];

    $forms = $this->getSystemHtmlForms();

    if (isset($forms[$form_id]['fields']) && is_array($forms[$form_id]['fields'])) {
      $fields = $forms[$form_id]['fields'];
    }

    return $fields;
  }

  /**
   * Get the html form field path from the default theme.
   *
   * @return array
   *   The file path.
   */
  protected function getHtmlFormsFilePath() {

    $config = $this->configFactory->get('system.theme');
    $default_theme = $config->get('default');
    $file_name = "$default_theme.eloqua_forms.yml";

    $theme_path = drupal_get_path('theme', $default_theme);
    $file_path = "$theme_path/$file_name";

    return $file_path;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteEloquaFormFields($form_id) {
    $fields = [];
    /** @var \EloquaForms\Data\Form $eloqua_form */
    $eloqua_form = $this->getClient()->getForm();
    $eloqua_form->setFormId($form_id);

    $remote_fields = $eloqua_form->pullFields();

    /** @var \EloquaForms\Data\FieldElement $remote_field */
    foreach ($remote_fields as $remote_field) {
      $fields[$remote_field->getHtmlName()] = $remote_field;
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteEloquaFormFieldAttributes($form_id) {
    $attributes = [];
    $fields = $this->getRemoteEloquaFormFields($form_id);

    /** @var \EloquaForms\Data\FieldElement $field */
    foreach ($fields as $field) {
      $validations = $field->getValidations();
      if (!empty($field->getHtmlName()) && $field->getHtmlName() <> 'submit' && !empty($validations)) {
        $attributes[$field->getHtmlName()] = $validations;
      }
    }

    return $attributes;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteEloquaRequiredFields($form_id) {
    $required = [];
    $attributes = $this->getRemoteEloquaFormFieldAttributes($form_id);

    /** @var \EloquaForms\Data\FieldElement $field */
    foreach ($attributes as $field_name => $field_attribute) {
      foreach ($field_attribute as $attribute) {
        if ($attribute instanceof IsRequiredCondition) {
          $required[] = $field_name;
        }
      }
    }

    return $required;
  }

  /**
   * {@inheritdoc}
   */
  public function createAndSaveEloquaSubmittedData(array $data) {
    // Check creating a submission with default data.
    /** @var \Drupal\br_forms\Entity\EloquaSubmission $eloqua_submission */
    $eloqua_submission = EloquaSubmission::create($data);
    return $eloqua_submission->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteEloquaSubmittedData() {
    $variables = [];
    $success = TRUE;
    try {
      $storage = $this->entityTypeManager
        ->getStorage('eloqua_submission');

      $submissions = $storage
        ->loadByProperties(array('published' => 1));

      $storage->delete($submissions);

    } catch (\Exception $e) {
      $success = FALSE;
      $variables['@long_message'] = $e->getLongMessage();
      watchdog_exception('br_forms', $e, self::MESSAGE, $variables);
    }

    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function postEloquaSavedData() {
    $success = TRUE;
    $submit_form = FALSE;

    $variables = [];

    try {
      $config = $this->configFactory->get('br_forms.eloqua');

      // Get a list of all forms that were not yet submitted.
      $eloqua_submissions = $this->getUnsubmittedEloquaforms();

      /** @var \Drupal\br_forms\Entity\EloquaSubmission $item */
      foreach ($eloqua_submissions as $item) {
        $client = $this->getClient(TRUE);

        /** @var \EloquaForms\Data\Form $form */
        $form = $client->getForm();

        $remote_data = $item->remote_data->getValue()[0];

        if (!empty($remote_data)) {

          $html_form_id = $item->html_form_id->getString();
          $form_id = (int) $item->remote_form_id->getString();
          $domain = $item->domain->getString();

          $form->setFormId($form_id);

          foreach ($remote_data as $field => $value) {
            $config_key = "fields.$domain.$html_form_id.$field";

            // Send field values only if they were configured in drupal.
            $field_id = $config->get($config_key, FALSE);
            if ($field_id) {
              $submit_form = TRUE;
              $form->addFieldByName($field_id, $value);
            }
          }

          // Post only if we have fields to post.
          if ($submit_form) {
            $form->post();

            // Update database record on success.
            $item->published = TRUE;
            $item->save();
          }

        }
      }
    } catch (ClientException $e) {
      $success = FALSE;
      $variables['@long_message'] = method_exists($e, 'getLongMessage') ? $e->getLongMessage() : $e->getMessage();
      watchdog_exception('br_forms', $e, self::MESSAGE, $variables);
    } catch (ConnectException $e) {
      $success = FALSE;
      $variables['@long_message'] = method_exists($e, 'getLongMessage') ? $e->getLongMessage() : $e->getMessage();
      watchdog_exception('br_forms', $e, self::MESSAGE, $variables);
    } catch (\Exception $e) {
      $success = FALSE;
      $variables['@long_message'] = method_exists($e, 'getLongMessage') ? $e->getLongMessage() : $e->getMessage();
      watchdog_exception('br_forms', $e, self::MESSAGE, $variables);
    }

    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderFormArray($form_id, $domain = NULL, $language_code = NULL, array $properties = []) {
    $fields = [];
    $cache_list = [];
    $config = $this->configFactory->get('br_forms.eloqua');
    $cache = new CacheableMetadata();
    $cache->addCacheableDependency($config);

    $language_code = empty($language_code) ? $this->languageManager->getCurrentLanguage()
      ->getId() : $language_code;

    if (empty($domain)) {
      /** @var \Drupal\br_country\CurrentCountry $current_country_service */
      $current_country_service = \Drupal::service('br_country.current');
      $domain = $current_country_service->getSlug();
    }

    $config_key = "fields.$domain.$form_id";

    $system_form_fields = $this->getSystemHtmlFormFields($form_id);

    $form_fields = $config->get($config_key);
    $eloqua_form_id = $config->get("forms.$domain.$form_id");

    $required_fields = $this->getRemoteEloquaRequiredFields($eloqua_form_id);

    $remote_fields = $this->getRemoteEloquaFormFields($eloqua_form_id);

    foreach ($form_fields as $key => $value) {
      /** @var \EloquaForms\Data\FieldElement $field_element */
      $field_element = $remote_fields[$value];

      if (in_array($value, $required_fields)) {
        $fields[$key] = [
          '#label' => t($field_element->getName()),
          '#required' => TRUE,
        ];

      }
      else {

        $fields[$key] = [
          '#label' => t($field_element->getName()),
          '#required' => FALSE,
        ];
      }

      // Field type.
      $fields[$key]['#eloqua_field_type'] = $system_form_fields[$key]['type'];
      $fields[$key]['#machine_name'] = $key;

      // If radio add options.
      if ($field_element->getDisplayType() == 'radio') {
        $options = $field_element->getOptionList();
        /** @var \EloquaForms\Data\FieldOption $option */
        foreach ($options as $option) {
          $fields[$key]['#options'][$option->getValue()] = [
            'label' => t($option->getDisplayName()),
            'id' => Html::getId($option->getValue()),
          ];
        }
      }

      // Add validations.
      $fields[$key]['#validations'] = [];

      $field_validations = $field_element->getValidations();
      if (!empty($field_validations)) {
        /** @var \EloquaForms\Data\Validation\Validation $field_validation */
        foreach ($field_validations as $field_validation) {
          $fields[$key]['#validations'][] = $field_validation->getName();

          if ($field_validation instanceof TextLengthCondition) {
            $fields[$key]['#validation_attr'][$field_validation->getName()] = [
              'min' => $field_validation->getMinimum(),
              'max' => $field_validation->getMaximum(),
            ];
          }

        }
      }

      $fields[$key]['#theme'] = 'eloqua_field';
    }

    $extra_fields = $this->addExtraFormElementsHelper($domain, $form_id, $language_code);

    $cache->applyTo($cache_list);
    $build = [
      '#theme' => "eloqua_form",
      '#form_id' => $form_id,
      '#path_prefix' => "$domain/$language_code",
      '#fields' => $fields,
      '#extra_fields' => $extra_fields,
      '#extra_properties' => $properties,
      '#cache' => $cache_list['#cache'],
    ];

    return $build;
  }

  /**
   * A list of extra elements to render.
   *
   * @param string $domain
   *   The domain.
   * @param string $form_id
   *   The html form ID.
   * @param string $lang_code
   *   The language code.
   *
   * @return array
   *   A list of extra fields.
   */
  protected function addExtraFormElementsHelper($domain, $form_id, $lang_code) {

    $extra_fields = [];

    $config = $this->configFactory->get('br_forms.eloqua');
    $key = "form_extras.{$domain}.{$form_id}";
    $fields = $config->get($key);

    foreach ($fields as $key => $value) {
      $needle = "br_{$lang_code}_";
      if (strpos($key, $needle) === 0) {
        $field_key = str_replace($needle, '', $key);
        $extra_fields[$field_key] = $value;
      }
    }

    return $extra_fields;
  }

}
