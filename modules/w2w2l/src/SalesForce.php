<?php

namespace Drupal\w2w2l;

use Drupal\w2w2l\ClientInterface;
use Drupal;


/**
 * SalesForce service.
 */
class SalesForce
{

  protected ClientInterface $client;

  public function __construct()
  {
    $this->client = new RestClient(
      $_ENV['SF_REST_ENDPOINT'],
      $_ENV['SF_REST_CLIENT_ID'],
      $_ENV['SF_REST_CLIENT_SECRET'],
      $_ENV['SF_LOGIN'] ?? null,
      $_ENV['SF_SECURE_TOKEN'] ?? null
    );
  }

  public function send($lead, $endpoint)
  {
    try {
      $r = $this->client->create($lead, $endpoint);
      $success = $r->success;
    } catch (\GuzzleHttp\Exception\ClientException $e) {
      $response = $e->getResponse();
      \Drupal::logger('w2w2l')->error($response->getBody()->getContents() . '<br>' .
        'Values: ' . json_encode($lead, JSON_PRETTY_PRINT));
      return $e;
    }

    if (!$success) {
      \Drupal::logger('w2w2l')
        ->error("error while registering a lead: " . json_encode($r, JSON_PRETTY_PRINT));
    }

    \Drupal::logger('w2w2l')
      ->info("lead registered: " . json_encode($r, JSON_PRETTY_PRINT)
        . '<br>' .
        'Values: ' . json_encode($lead, JSON_PRETTY_PRINT));

    return ['success' => $success, 'id' => $r->id ?? '',];
  }
}
