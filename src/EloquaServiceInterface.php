<?php

namespace Drupal\br_forms;

/**
 * Interface EloquaServiceInterface.
 *
 * @package Drupal\br_forms
 */
interface EloquaServiceInterface {

  /**
   * Get a list of form user submitted entities not yet submitted to Eloqua.
   *
   * @return array
   *   A list of Eloqua form submission entities.
   */
  public function getUnsubmittedEloquaforms();

  /**
   * Validate eloqua credentials.
   *
   * @param string $siteName
   *   The site name.
   * @param string $username
   *   The user name.
   * @param string $password
   *   The password.
   * @param string $host
   *   The host name.
   *
   * @return bool
   *   TRUE/FALSE
   */
  public function validateEloquaCredentials($siteName, $username, $password, $host);

  /**
   * Get client.
   *
   * @param bool $force_new_instance
   *   Force creation of a new client instance.
   *
   * @return \EloquaForms\Client
   *   Eloqua form client.
   */
  public function getClient($force_new_instance = FALSE);

  /**
   * Analyse the html forms from the file in the current theme.
   *
   * @return array
   *   A list of html forms defined in the system.
   */
  public function getSystemHtmlForms();

  /**
   * Get the html form fields.
   *
   * @param string $form_id
   *   The html form id.
   *
   * @return array
   *   A list of fields in this html form.
   */
  public function getSystemHtmlFormFields($form_id);

  /**
   * Get remote eloqua form fields.
   *
   * @param int $form_id
   *   The html form id.
   *
   * @return array
   *   A list of fields in this html form.
   */
  public function getRemoteEloquaFormFields($form_id);

  /**
   * Get remote eloqua form field attributes.
   *
   * @param int $form_id
   *   The html form id.
   *
   * @return array
   *   A list of fields and there attributes.
   */
  public function getRemoteEloquaFormFieldAttributes($form_id);

  /**
   * Get remote eloqua required fields.
   *
   * @param int $form_id
   *   The html form id.
   *
   * @return array
   *   A list of required fields.
   */
  public function getRemoteEloquaRequiredFields($form_id);

  /**
   * Saving the submitted data.
   *
   * @param array $data
   *   A list of submitted data to save.
   *
   * @return int
   *   SAVED_NEW or SAVED_UPDATED is returned depending on the operation
   *   performed.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   In case of failures, an exception is thrown.
   */
  public function createAndSaveEloquaSubmittedData(array $data);

  /**
   * Delete all data from the database that was successfully sent.
   *
   * @return bool
   *   TRUE / FALSE.
   */
  public function deleteEloquaSubmittedData();

  /**
   * Post all data from the database that needs to be posted to Eloqua.
   *
   * @return bool
   *   TRUE / FALSE.
   */
  public function postEloquaSavedData();

  /**
   * Get the render array.
   *
   * @param string $form_id
   *   The name of the form ID.
   * @param string $domain
   *   The domain.
   * @param string $language_code
   *   The language code.
   * @param array $properties
   *   A list of any extra properties.
   *
   * @return array
   *   The render array.
   */
  public function getRenderFormArray($form_id, $domain = NULL, $language_code = NULL, array $properties = []);

}
