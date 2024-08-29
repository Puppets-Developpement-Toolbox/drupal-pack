<?php

namespace Drupal\w2w2l\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
//import Cookie class from Drupal

/**
 * Sodexo salesforce event subscriber.
 */
class GclidCookifier implements EventSubscriberInterface
{

  /**
   * Kernel request event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Response event.
   */
  public function onKernelRequest(RequestEvent $event)
  {
    // @todo Place code here.

  }

  /**
   * Kernel response event handler.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   Response event.
   */
  public function onKernelResponse(ResponseEvent $event)
  {
    $request = $event->getRequest();
    $gclid = $request->query->get('gclid');
    if ($gclid) {
      $response = $event->getResponse();
      // add gclid cookie in response with ttl of 13months
      $response->headers->setCookie(new Cookie('gclid', $gclid, time() + (86400 * 30 * 13), '/'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents()
  {
    return [
      KernelEvents::REQUEST => ['onKernelRequest'],
      KernelEvents::RESPONSE => ['onKernelResponse'],
    ];
  }
}
