<?php

namespace Drupal\w2w2l;

use Drupal\w2w2l\ClientInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class RestClient implements ClientInterface {
  
  protected Client $client;
  
  protected string $accessToken;
  protected string $instanceUrl;
  
  public function __construct(
    $endpoint, $client_id, $client_secret, $username, 
    $password, $security_token
  ) {
    $this->client = new Client([
      'base_uri' => trim($endpoint, '/') .'/'
    ]);

    $response = $this->client->request('post', 'services/oauth2/token', [
      RequestOptions::FORM_PARAMS => [
        'grant_type' => 'password',
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'username' => $username,
        'password' => "{$password}{$security_token}",
      ]
    ]);
    $data = json_decode($response->getBody());

    $hash = hash_hmac(
      'sha256', 
      "{$data->id}{$data->issued_at}", 
      $client_secret, 
      true
    );
    
    if (base64_encode($hash) !== $data->signature) {
      throw new Exception('Salesforce access token is invalid');
    }
    $this->accessToken = $data->access_token;
    $this->instanceUrl = $data->instance_url;
  }

  public function create($sobject, $type) 
  {
    $response = $this->client->request(
      'post', 
      "{$this->instanceUrl}/services/data/v58.0/sobjects/{$type}/", [
        RequestOptions::HEADERS => [
            'Authorization' => "Bearer {$this->accessToken}",
            'X-PrettyPrint' => 1,
        ],
        RequestOptions::JSON => $sobject,
    ]);

    return json_decode($response->getBody());
  }

}