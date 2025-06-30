<?php

namespace Drupal\w2w2l\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
//import Cookie class from Drupal

/**
 * Webform2Web2Lead adds gclid and arrivalCookie
 */
class GclidArrivalCookifier implements EventSubscriberInterface
{

  public function onKernelResponse(ResponseEvent $event)
  {
    $request = $event->getRequest();
    $trackingCookies = [
      'gclid' => time() + (86400 * 30 * 13),
      'utm_campaign' => 0,
      'utm_term' => 0,
      'utm_medium' => 0,
      'utm_content' => 0,
      'utm_source' => 0,
    ];

    foreach($trackingCookies as $cookieName => $duration) {
      $cookieValue = $request->query->get($cookieName);
      if ($cookieValue) {
        $response = $event->getResponse();
        $response->headers->setCookie(new Cookie(
          "w2w2l-{$cookieName}",
          $cookieValue,
          $duration,
          '/',
          null,
          null,
          false
        ));
      }
    }

    if (!$request->cookies->has('w2w2l-arrivalCookie')) {
      $fullUrl = $request->getUri();
      //ttl is 0, it expires when browser closes
      $response = $event->getResponse();
      $response->headers->setCookie(new Cookie(
        'w2w2l-arrivalCookie',
        $fullUrl,
        0,
        '/',
        null,
        null,
        false
      ));
    }

  }


  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents()
  {
    return [
      KernelEvents::RESPONSE => ['onKernelResponse'],
    ];
  }
}
