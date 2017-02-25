<?php

namespace Drupal\br_forms\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Class EloquaFormController.
 *
 * @package Drupal\br_forms\Controller
 */
class EloquaFormController extends ControllerBase {

  /**
   * A list of form ids that have configured page titles.
   *
   * @var array
   */
  protected $formTitles;

  /**
   * EloquaFormController constructor.
   */
  public function __construct() {
    $this->formTitles = ['contact'];
  }

  /**
   * Form controller.
   *
   * @param string $html_form
   *   Html form.
   *
   * @return array
   *   Render array.
   */
  public function pageForm($html_form) {

    /** @var \Drupal\br_forms\EloquaService $eloqua_service */
    $eloqua_service = \Drupal::service('br_forms.eloqua');

    $build = $eloqua_service->getRenderFormArray($html_form);

    // Attach JavaScript library.
    $build['#attached']['library'][] = 'br_forms/br_forms.forms';

    return [
      'forms' => $build,
    ];
  }

  /**
   * Returns the contact page title.
   */
  public function getPageTitle() {

    // The html form Id cannot be set before so i get it from the route match.
    /** @var \Drupal\Core\Routing\RouteMatchInterface $current_route_match */
    $current_route_match = \Drupal::service('current_route_match');
    $html_form = $current_route_match->getParameter('html_form');

    $title = $this->t('Eloqua form');

    if (!in_array($html_form, $this->formTitles)) {
      return $title;
    }

    /** @var \Drupal\br_country\CurrentCountry $current_country_service */
    $current_country_service = \Drupal::service('br_country.current');

    $domain = $current_country_service->getSlug();
    $language_code = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $config = \Drupal::configFactory()->get('br_forms.eloqua');
    $key = "form_extras.{$domain}.contact";
    $page_title_key = "br_{$language_code}_page_title";
    $fields = $config->get($key);

    if (!empty($fields[$page_title_key])) {
      $title = $fields[$page_title_key];
    }

    return $title;
  }

}
