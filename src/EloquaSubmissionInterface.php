<?php

namespace Drupal\br_forms;

use Drupal\user\EntityOwnerInterface;

/**
 * Interface EloquaSubmissionInterface.
 *
 * @package Drupal\br_forms
 */
interface EloquaSubmissionInterface extends EntityOwnerInterface {

  /**
   * Returns the time that the submission was created.
   *
   * @return int
   *   The timestamp of when the submission was created.
   */
  public function getCreatedTime();

  /**
   * Gets the eloqua submission's data.
   *
   * @param string $key
   *   A string that maps to a key in the submission's data.
   *   If no key is specified, then the entire data array is returned.
   *
   * @return array
   *   The eloqua submission data.
   */
  public function getData($key = NULL);

  /**
   * Set the eloqua submission's data.
   *
   * @param array $data
   *   The eloqua submission data.
   */
  public function setData(array $data);

}
