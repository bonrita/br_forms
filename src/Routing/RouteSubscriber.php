<?php

namespace Drupal\br_forms\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class RouteSubscriber.
 *
 * @package Drupal\br_forms\Routing
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {

    foreach ($collection as $name => $route) {

      if (strpos($name, 'rest.br_eloq') === 0) {
        // Remove the csrf token requirement, as this is not really needed:
        // all we do is store information in the session.
        $requirements = $route->getRequirements();
        unset($requirements['_csrf_request_header_token']);
        $route->setRequirements($requirements);
      }

    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -400];
    return $events;
  }

}
