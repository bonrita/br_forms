<?php

namespace Drupal\br_forms;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the eloqua submission storage.
 */
class EloquaSubmissionStorage extends SqlContentEntityStorage implements EloquaSubmissionStorageInterface {

  /**
   * Delete eloqua submission data from the 'eloqua_submission' table.
   *
   * @param array $eloqua_submissions
   *   An array of eloqua submissions.
   */
  protected function deleteData(array $eloqua_submissions) {
    Database::getConnection()->delete('eloqua_submission')
      ->condition('eid', array_keys($eloqua_submissions), 'IN')
      ->execute();
  }

}
