<?php

namespace Drupal\br_forms\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\br_forms\EloquaSubmissionInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines the aggregator item entity class.
 *
 * @ContentEntityType(
 *   id = "eloqua_submission",
 *   label = @Translation("Eloqua submission"),
 *   handlers = {
 *     "storage" = "Drupal\br_forms\EloquaSubmissionStorage",
 *     "storage_schema" = "Drupal\br_forms\EloquaSubmissionSchema",
 *   },
 *   base_table = "eloqua_submission",
 *   render_cache = FALSE,
 *   entity_keys = {
 *     "id" = "eid",
 *     "label" = "html_form_id",
 *     "langcode" = "langcode",
 *   }
 * )
 */
class EloquaSubmission extends ContentEntityBase implements EloquaSubmissionInterface {

  /**
   * The data.
   *
   * @var array
   */
  protected $data = [];

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->get('html_form_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['html_form_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Html form ID'))
      ->setDescription(t('The ID of the html form.'));

    $fields['domain'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Domain'))
      ->setDescription(t('The domain from which the form was sent.'));

    $fields['remote_form_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Remote form ID'))
      ->setDescription(t('The remote ID of the form.'));

    $fields['remote_data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Remote data'))
      ->setDescription(t('The remote data of the form.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the eloqua form was submitted.'));

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Submitted by'))
      ->setDescription(t('The submitter.'))
      ->setSetting('target_type', 'user');

    $fields['published'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status of the form'))
      ->setDescription(t('The status of the form i.e sent or not.'))
      ->setDefaultValue(FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    if (isset($this->get('created')->value)) {
      return $this->get('created')->value;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getData($key = NULL) {
    if (isset($key)) {
      return (isset($this->data[$key])) ? $this->data[$key] : NULL;
    }
    else {
      return $this->data;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    $user = $this->get('uid')->entity;
    if (!$user || $user->isAnonymous()) {
      $user = User::getAnonymousUser();
    }
    return $user;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setData(array $data) {
    $this->data = $data;
    return $this;
  }

}
