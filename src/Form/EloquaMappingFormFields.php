<?php

namespace Drupal\br_forms\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\br_forms\EloquaService;

/**
 * Class EloquaMappingFormFields.
 *
 * @package Drupal\br_forms\Form
 */
class EloquaMappingFormFields extends ConfigFormBase {

  /**
   * Eloqua service.
   *
   * @var \Drupal\br_forms\EloquaService
   */
  protected $eloquaService;

  /**
   * The domain code.
   *
   * @var string
   */
  protected $domain;

  /**
   * The HTML form ID.
   *
   * @var string
   */
  protected $htmlFormID;

  /**
   * Eloqua configuration object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $eloquaConfig;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EloquaService $eloqua_service) {
    parent::__construct($config_factory);
    $this->eloquaService = $eloqua_service;
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
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['br_forms.eloqua'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'eloqua_form_field_mapping';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);
    $this->eloquaConfig = $this->config('br_forms.eloqua');

    /** @var \Drupal\domain\DomainLoader $domain_loader */
    $domain_loader = \Drupal::service('domain.loader');
    $domain_list = $domain_loader->loadOptionsList();

    $request = $this->getRequest();
    $this->domain = $request->get('domain');
    $this->htmlFormID = $request->get('html_form_id');
    $eloqua_form_id = $request->get('eloqua_form_id');

    /** @var \Drupal\br_forms\EloquaService $eloqua_service */
    $eloqua_service = \Drupal::service('br_forms.eloqua');

    $form['#title'] = $this->t('Eloqua form field mapping for the "@form" form of "@country" domain.', [
      '@form' => $this->htmlFormID,
      '@country' => $domain_list["country_{$this->domain}"],
    ]);

    $form['domain'] = array(
      '#type' => 'value',
      '#value' => $this->domain,
    );

    $form['html_form_id'] = array(
      '#type' => 'value',
      '#value' => $this->htmlFormID,
    );

    $form['eloqua_form_id'] = array(
      '#type' => 'value',
      '#value' => $eloqua_form_id,
    );

    /** @var \EloquaForms\Data\Form $eloqua_form */
    $eloqua_form = $this->eloquaService->getClient()->getForm();
    $eloqua_form->setFormId($eloqua_form_id);

    $html_form_fields = $this->eloquaService->getSystemHtmlFormFields($this->htmlFormID);

    if (!empty($html_form_fields)) {
      $must_fields = [];
      $required_fields = $eloqua_service->getRemoteEloquaRequiredFields($eloqua_form_id);
      $options = $this->getEloquaFormFieldOptions($eloqua_form_id);

      foreach ($required_fields as $required_field) {
        $must_fields[] = $options[$required_field];
      }

      // Add Extra form elements data.
      $this->buidExtraFormElements($form, $form_state);

      $form['fields'] = array(
        '#type' => 'fieldset',
        '#title' => t('Fields to map'),
        '#collapsible' => TRUE,
        '#collapsed' => FALSE,
      );

      if (!empty($must_fields)) {
        $form['fields']['help'] = array(
          '#type' => 'item',
          '#title' => $this->t('Required fields'),
          '#markup' => implode(', ', $must_fields),
        );
      }

      $form['fields']['notice'] = array(
        '#type' => 'item',
        '#title' => $this->t('Note'),
        '#markup' => $this->t('lf a field is not mapped, it will not be rendered or added to the form.'),
      );

      foreach ($html_form_fields as $html_form_field => $attributes) {
        $config_key = "fields.$this->domain.$this->htmlFormID.$html_form_field";
        $form['fields'][$html_form_field] = array(
          '#type' => 'select',
          '#title' => isset($attributes['label']) ? $attributes['label'] : ucfirst($html_form_field),
          '#options' => $options,
          '#default_value' => $this->eloquaConfig->get($config_key),
          '#description' => $this->t('This field is of type "@type"', ['@type' => $attributes['type']]),
        );
      }
    }
    else {
      $config = $this->config('system.theme');
      $default_theme = $config->get('default');
      $file_name = "$default_theme.eloqua_forms.yml";

      $form['empty'] = array(
        '#type' => 'item',
        '#title' => $this->t('File missing'),
        '#markup' => $this->t('Please add the forms in the file: @file_name . Put the file in the theme: @theme', [
          '@file_name' => $file_name,
          '@theme' => $default_theme,
        ]),
      );
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $user_input = $form_state->getUserInput();

    $clean_value_keys = array_flip($form_state->getCleanValueKeys());
    $user_values = array_diff_key($user_input, $clean_value_keys);
    unset($user_values['help']);

    /** @var \Drupal\br_forms\EloquaService $eloqua_service */
    $eloqua_service = \Drupal::service('br_forms.eloqua');
    $required_fields = $eloqua_service->getRemoteEloquaRequiredFields($values['eloqua_form_id']);
    $fields = $this->getEloquaFormFieldOptions($values['eloqua_form_id']);

    $form_elements = array_keys($user_values);
    foreach ($required_fields as $field) {
      if (strpos($field, 'br_') === FALSE && !in_array($field, $user_values)) {
        $form_state->setErrorByName($form_elements[0], $this->t('You must map the field: "@field" to any of the form elements. It is required', ['@field' => $fields[$field]]));
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('br_forms.eloqua');
    $values = $form_state->getValues();

    $user_input = $form_state->getUserInput();

    $clean_value_keys = array_flip($form_state->getCleanValueKeys());
    $user_values = array_diff_key($user_input, $clean_value_keys);
    unset($user_values['help']);

    // Reset the current's form values.
    $form_fields = "fields.{$values['domain']}.{$values['html_form_id']}";
    $config->clear($form_fields);

    $form_extras = "form_extras.{$values['domain']}.{$values['html_form_id']}";
    $config->clear($form_extras);

    // Add form id to config.
    $form_config_key = "forms.{$values['domain']}.{$values['html_form_id']}";
    $config->set($form_config_key, $values['eloqua_form_id']);

    // Add or clear fields in the config.
    foreach ($user_values as $key => $user_value) {
      // Prevent storing extra form attributes in the real field list.
      if (strpos($key, 'br_') === FALSE) {
        $config_key = "fields.{$values['domain']}.{$values['html_form_id']}.$key";
        if (empty($user_value)) {
          $config->clear($config_key);
        }
        else {
          $config->set($config_key, $user_value);
        }
      }
    }

    // Store the extra form attributes.
    foreach ($user_values as $key => $extra_value) {
      if (strpos($key, 'br_') === 0) {
        $config_extra = "form_extras.{$values['domain']}.{$values['html_form_id']}.$key";
        if (empty($extra_value)) {
          $config->clear($config_extra);
        }
        else {
          $config->set($config_extra, $extra_value);
        }
      }
    }

    $config->save();
  }

  /**
   * Render extra form elements.
   *
   * @param array $form
   *   The form list.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function addExtraAdminFormElements(array &$form, FormStateInterface $form_state, $lang_code = 'en') {
    $title_list = ['contact'];
    $lang_object = \Drupal::languageManager()->getLanguage($lang_code);

    $form["extra_$lang_code"] = array(
      '#type' => 'fieldset',
      '#title' => t('@lang version', ['@lang' => $lang_object->getName()]),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );

    // Header text.
    $form["extra_$lang_code"]['other'] = array(
      '#type' => 'fieldset',
      '#title' => t('Header text'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );

    if (in_array($this->htmlFormID, $title_list)) {
      $form["extra_$lang_code"]['other']["br_{$lang_code}_page_title"] = array(
        '#title' => t('Page title'),
        '#type' => 'textfield',
        '#description' => t('This is the page title and the menu link title.'),
        '#default_value' => $this->getExtrasDefaultFieldValue($lang_code, 'page_title'),
        '#required' => TRUE,
      );
    }

    $form["extra_$lang_code"]['other']["br_{$lang_code}_intro_text"] = array(
      '#title' => t('Intro text'),
      '#type' => 'textarea',
      '#description' => t('Intro text'),
      '#default_value' => $this->getExtrasDefaultFieldValue($lang_code, 'intro_text'),
    );

    $form["extra_$lang_code"]['other']["br_{$lang_code}_success"] = array(
      '#title' => t('Success text'),
      '#type' => 'textarea',
      '#description' => t('Success text'),
      '#default_value' => $this->getExtrasDefaultFieldValue($lang_code, 'success'),
    );

    $form["extra_$lang_code"]['other']["br_{$lang_code}_warning"] = array(
      '#title' => t('Warning text'),
      '#type' => 'textarea',
      '#description' => t('Warning text'),
      '#default_value' => $this->getExtrasDefaultFieldValue($lang_code, 'warning'),
    );

    $form["extra_$lang_code"]['other']["br_{$lang_code}_response"] = array(
      '#title' => t('Response text'),
      '#type' => 'textarea',
      '#description' => t('Response text'),
      '#default_value' => $this->getExtrasDefaultFieldValue($lang_code, 'response'),
    );

  }

  /**
   * Get a list of eloqua forms.
   *
   * @param int $eloqua_form_id
   *   The eloqua remote form ID.
   *
   * @return array
   *   a list of eloqua forms.
   */
  protected function getEloquaFormFieldOptions($eloqua_form_id) {
    $fields = $this->eloquaService->getRemoteEloquaFormFields($eloqua_form_id);

    $options = [
      '' => $this->t('Select'),
    ];

    /** @var \EloquaForms\Data\FieldElement $field */
    foreach ($fields as $field) {
      if ($field->getDisplayType() == 'hidden') {
        continue;
      }
      if (!empty($field->getHtmlName()) && $field->getHtmlName() <> 'submit') {
        $name = $field->getName();

        if ($field->getDisplayType()) {
          $name .= " (" . $field->getDisplayType() . ")";
        }

        $options[$field->getHtmlName()] = $name;
      }
    }

    return $options;
  }

  /**
   * Get the config value of the extras field.
   *
   * @param string $lang_code
   *   The language code.
   * @param string $field_name
   *   The field name.
   *
   * @return null|string
   *   The value of the field.
   */
  protected function getExtrasDefaultFieldValue($lang_code, $field_name) {
    $key = "form_extras.{$this->domain}.{$this->htmlFormID}.br_{$lang_code}_{$field_name}";
    return $this->eloquaConfig->get($key);
  }

  /**
   * Build extra form elements.
   *
   * @param array $form
   *   The form list.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  protected function buidExtraFormElements(array &$form, FormStateInterface $form_state) {
    $connection = \Drupal::database();
    $query = $connection->select('country__field__slug', 'cs');
    $query->fields('cl', array('field_country_languages_value'));
    $query->leftJoin('country__field_country_languages', 'cl', 'cs.entity_id = cl.entity_id');
    $query->where("cs.field__slug_value = '$this->domain'");

    $result = $query->execute();

    foreach ($result as $item) {
      $this->addExtraAdminFormElements($form, $form_state, $item->field_country_languages_value);
    }
  }

}
