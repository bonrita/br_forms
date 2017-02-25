<?php

namespace Drupal\br_forms\Form;


use Drupal\br_forms\EloquaService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EloquaMappingForm extends ConfigFormBase {

  /**
   * Eloqua service.
   *
   * @var \Drupal\br_forms\EloquaService
   */
  protected $eloqua_service;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EloquaService $eloqua_service) {
    parent::__construct($config_factory);
    $this->eloqua_service = $eloqua_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('br_forms.eloqua')
    );
  }

  /**
   * @inheritDoc
   */
  protected function getEditableConfigNames() {
    return ['br_forms.eloqua'];
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'eloqua_form_mapping';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('br_forms.eloqua');

    /** @var \Drupal\domain\DomainLoader $domain_loader */
    $domain_loader = \Drupal::service('domain.loader');
    $domain_list = $domain_loader->loadOptionsList();

    $html_forms = $this->eloqua_service->getSystemHtmlForms();

    if (!empty($html_forms)) {

      foreach ($domain_list as $domain_code => $domain_name) {
        $domain_prefix = str_replace('country_', '', $domain_code);
        $form[$domain_code] = array(
          '#type' => 'fieldset',
          '#title' => $domain_name,
          '#collapsible' => TRUE,
          '#collapsed' => FALSE,
          '#description' => $this->t('Please select an eloqua form that corresponds to the above forms.'),
        );

        // Add the html forms.
        foreach ($html_forms as $html_form_id => $html_form_value) {
          $config_key = "forms.$domain_prefix.$html_form_id";
          $form[$domain_code]["{$domain_prefix}_{$html_form_id}"] = array(
            '#type' => 'select',
            '#title' => $html_form_value['label'],
            '#options' => $this->getEloquaFormOptions(),
            '#default_value' => $config->get($config_key),
            '#description' => $html_form_value['description'],
          );
          $form[$domain_code]["{$domain_prefix}_{$html_form_id}_submit"] = array(
            '#type' => 'submit',
            '#value' => $this->t('Add or edit fields'),
            '#name' => "{$domain_prefix}_{$html_form_id}_submit",
          );

        }

      }
    }
    else {
      $config = $this->config('system.theme');
      $default_theme = $config->get('default');
      $file_name = "$default_theme.eloqua_forms.yml";

      $form['empty'] = array(
        '#type' => 'item',
        '#title' => $this->t('File missing'),
        '#markup' => $this->t('Please add the forms in the file: @file_name .' . ' Put the file in the theme: @theme', [
          '@file_name' => $file_name,
          '@theme' => $default_theme
        ]),

      );
    }

    return $form;
  }

  /**
   * @inheritDoc
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate "Add or edit" submitted values.
    $submit_btn = $form_state->getTriggeringElement();

    if ($submit_btn['#name'] <> 'op') {
      $user_input = $form_state->getUserInput();
      $element_name = str_replace('_submit', '', $submit_btn['#name']);

      if (empty($user_input[$element_name])) {
        $form_state->setErrorByName($element_name, $this->t('You must select a form to edit.'));
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('br_forms.eloqua');
    $submit_btn = $form_state->getTriggeringElement();
    $user_input = $form_state->getUserInput();

    if ($submit_btn['#name'] == 'op') {
      // Saving the whole form.
      $skip = [
        'form_build_id',
        'form_token',
        'form_id',
        'op',
      ];

      foreach ($user_input as $key => $value) {
        if (!in_array($key, $skip)) {
          $domain = substr($key, 0, 2);
          $html_form = substr($key, 3);
          $config_key = "forms.$domain.$html_form";
          $config->set($config_key, $value);
        }
      }
      $config->save();
    }
    else {
      $element_name = str_replace('_submit', '', $submit_btn['#name']);
      $domain = substr($element_name, 0, 2);
      $html_form = substr($element_name, 3);

      $eloqua_form_id = $user_input[$element_name];

      $route_parameters = [
        'domain' => $domain,
        'html_form_id' => $html_form,
        'eloqua_form_id' => $eloqua_form_id,
      ];

      $form_state->setRedirect('br_forms.eloqua.configure.form_fields', $route_parameters);
    }

  }

  /**
   * Get a list of eloqua forms.
   *
   * @return array
   *   a list of eloqua forms.
   */
  protected function getEloquaFormOptions() {

    // @todo Put in a try catch block as the credentials may not have been configured yet.
    $eloqua_forms = $this->eloqua_service->getClient()->getForms()->get();

    $options = [
      '' => $this->t('Select')
    ];

    /** @var \EloquaForms\Data\Form $eloqua_form */
    foreach ($eloqua_forms as $eloqua_form) {
      $options[$eloqua_form->getFormId()] = $eloqua_form->getName();
    }

    return $options;
  }

}
