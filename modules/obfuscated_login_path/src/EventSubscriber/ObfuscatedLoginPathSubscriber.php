<?php

namespace Drupal\obfuscated_login_path\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Obfuscated login path event subscriber.
 * Listens to the dynamic route events.
 */
class ObfuscatedLoginPathSubscriber extends RouteSubscriberBase

{

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection)
  {
    if ($route = $collection->get('user.login')) {
      $route->setPath('/us3ur/l0gg1ne');
    }

  }
}
