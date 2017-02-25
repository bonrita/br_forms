<?php

namespace Drupal\br_forms;

use Drupal\webform\Entity\Webform;
use EloquaForms\Client;

/**
 * Class EloquaService.
 *
 * @package Drupal\br_forms
 */
class EloquaService extends EloquaServiceBase {

  /**
   * {@inheritdoc}
   */
  public function getClient($force_new_instance = FALSE) {

    if (!$this->client || $force_new_instance) {
      $credentials = $this->configFactory->get('br_forms.eloqua');

      $site_name = $credentials->get('credentials.site_name');
      $user_name = $credentials->get('credentials.user_name');
      $password = $credentials->get('credentials.password');
      $host = $credentials->get('credentials.host');

      $client = new Client();
      $client
        ->setCredentials($site_name, $user_name, $password)
        ->setHost($host);
      $this->client = $client;
    }

    return $this->client;
  }

}
