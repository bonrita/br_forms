<?php

namespace Drupal\br_forms\Form;


use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class EloquaGeneralSettingsForm extends ConfigFormBase {

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'eloqua_general_settings';
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $eloqua_settings = $this->config('br_forms.eloqua');

    $form['site_name'] = [
      '#type' => 'textfield',
      '#title' => t('Site name'),
      '#default_value' => $eloqua_settings->get('credentials.site_name'),
      '#required' => TRUE,
    ];

    $form['user_name'] = [
      '#type' => 'textfield',
      '#title' => t('User name'),
      '#default_value' => $eloqua_settings->get('credentials.user_name'),
      '#required' => TRUE,
    ];

    $form['password'] = [
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#default_value' => $eloqua_settings->get('credentials.password'),
      '#required' => TRUE,
    ];

    $form['host'] = [
      '#type' => 'textfield',
      '#title' => t('Host'),
      '#default_value' => $eloqua_settings->get('credentials.host'),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * @inheritDoc
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_state->getValues();

    $site_name = $form_state->getValue('site_name');
    $user_name = $form_state->getValue('user_name');
    $password= $form_state->getValue('password');
    $host = $form_state->getValue('host');

    $eloqua_service = \Drupal::service('br_forms.eloqua')->validateEloquaCredentials($site_name, $user_name, $password, $host);

    if($eloqua_service !== TRUE) {
      $form_state->setErrorByName('site_name', $eloqua_service);
    }

  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $eloqua_settings = $this->config('br_forms.eloqua');

    $eloqua_settings
      ->set('credentials.site_name', $form_state->getValue('site_name'))
      ->set('credentials.user_name', $form_state->getValue('user_name'))
      ->set('credentials.password', $form_state->getValue('password'))
      ->set('credentials.host', $form_state->getValue('host'))
      ->save();
  }


}
