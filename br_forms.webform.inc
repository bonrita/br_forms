<?php

/**
 * @file
 * Integrates third party settings for the eloqua br forms.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 *
 * Add eloqua general settings.
 */
function br_forms_form_webform_admin_third_party_settings_form_alter(&$form, FormStateInterface $form_state, $form_id) {

  /** @var \Drupal\webform\WebformThirdPartySettingsManagerInterface $third_party_settings_manager */
  $third_party_settings_manager = \Drupal::service('webform.third_party_settings_manager');

  $eloqua_username = $third_party_settings_manager->getThirdPartySetting('br_forms', 'eloqua_username');
  $eloqua_password = $third_party_settings_manager->getThirdPartySetting('br_forms', 'eloqua_password');
  $eloqua_sitename = $third_party_settings_manager->getThirdPartySetting('br_forms', 'eloqua_sitename');
  $eloqua_host = $third_party_settings_manager->getThirdPartySetting('br_forms', 'eloqua_host');


  $form['third_party_settings']['br_forms'] = [
    '#type' => 'fieldset',
    '#title' => t('Eloqua settings'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  ];

  $form['third_party_settings']['br_forms']['eloqua_username'] = array(
    '#type' => 'textfield',
    '#title' => t('Username'),
    '#default_value' => $eloqua_username,
    '#size' => 60,
    '#maxlength' => 128,
  );

  $form['third_party_settings']['br_forms']['eloqua_password'] = array(
    '#type' => 'textfield',
    '#title' => t('Password'),
    '#default_value' => $eloqua_password,
    '#size' => 60,
    '#maxlength' => 128,
  );

  $form['third_party_settings']['br_forms']['eloqua_sitename'] = array(
    '#type' => 'textfield',
    '#title' => t('Site name'),
    '#default_value' => $eloqua_sitename,
    '#size' => 60,
    '#maxlength' => 128,
  );

  $form['third_party_settings']['br_forms']['eloqua_host'] = array(
    '#type' => 'textfield',
    '#title' => t('Host'),
    '#default_value' => $eloqua_host,
    '#size' => 60,
    '#maxlength' => 128,
  );
}

/**
 * Implements hook_form_FORM_ID_alter().
 *
 * Add eloqua form specific settings.
 */
function br_forms_form_webform_third_party_settings_form_alter(&$form, FormStateInterface $form_state, $form_id) {

  /** @var \Drupal\webform\WebformSubmissionForm $form_object */
  $form_object = $form_state->getFormObject();

  /** @var \Drupal\webform\WebformSubmissionInterface $webform */
  $webform = $form_object->getEntity();

  $form['third_party_settings']['br_forms'] = [
    '#type' => 'fieldset',
    '#title' => t('Eloqua settings'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  ];

  $eloqua_form_id = "eloqua_{$webform->id()}_form_id";

  $eloqua_form_id_value = $webform->getThirdPartySetting('br_forms', $eloqua_form_id);

  $form['third_party_settings']['br_forms'][$eloqua_form_id] = array(
    '#type' => 'textfield',
    '#title' => t('Form id'),
    '#default_value' => $eloqua_form_id_value,
    '#size' => 60,
    '#maxlength' => 128,
  );

}
