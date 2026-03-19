<?php

namespace Drupal\w2w2l;

use Drupal\w2w2l\ClientInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientExceptionInterface;
use SplFileInfo;

class RestClient implements ClientInterface
{

  protected Client $client;

  protected string $accessToken;
  protected string $instanceUrl;

  public function __construct(
    $endpoint,
    $client_id,
    $client_secret,
    $username = "",
    $password = ""
  ) {

    $this->client = new Client([
      'base_uri' => trim($endpoint, '/') . '/'
    ]);

    // "classic" auth , with username and password
    if (!empty($username) && !empty($password)) {

      $response = $this->client->request('post', 'services/oauth2/token', [
        RequestOptions::FORM_PARAMS => [
          'grant_type' => 'password',
          'client_id' => $client_id,
          'client_secret' => $client_secret,
          'username' => $username,
          'password' => $password
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
    // "jwt" auth, with client_id and client_secret
    else {
      $response = $this->client->request('post', 'services/oauth2/token', [
        RequestOptions::FORM_PARAMS => [
          'grant_type' => 'client_credentials',
          'client_id' => $client_id,
          'client_secret' => $client_secret,
        ]
      ]);
      $data = json_decode($response->getBody());

      if ($data->access_token) {
        $this->accessToken = $data->access_token;
        $this->instanceUrl = $data->instance_url;
      } else {
        throw new Exception('Token access invalid ');
      }
    }
  }

  public function create($sobject, $endpoint)
  {
    try {
      $response = $this->client->request(
        'post',
        "{$this->instanceUrl}{$endpoint}",
        [
          RequestOptions::HEADERS => [
            'Authorization' => "Bearer {$this->accessToken}",
            'X-PrettyPrint' => 1,
            'Content-Type' => 'application/json'
          ],
          "body" => json_encode($sobject, JSON_UNESCAPED_UNICODE),
        ]
      );
    } catch(ClientExceptionInterface $e) {
      $message = $e->getMessage();
      throw new Exception("Invalid lead :{$message} \n".var_export($sobject, true));
    }

    return json_decode($response->getBody());
  }

  public function retrieve($id, $endpoint, $fields = [])
  {
    try {
      $url = "{$this->instanceUrl}{$endpoint}/{$id}";

      // Ajouter les champs spécifiques si fournis
      if (!empty($fields)) {
        $url .= '?fields=' . implode(',', $fields);
      }

      $response = $this->client->request(
        'get',
        $url,
        [
          RequestOptions::HEADERS => [
            'Authorization' => "Bearer {$this->accessToken}",
            'X-PrettyPrint' => 1,
            'Content-Type' => 'application/json'
          ],
        ]
      );
    } catch(ClientExceptionInterface $e) {
      $message = $e->getMessage();
      throw new Exception("Unable to retrieve {$sobject_type} with ID {$id}: {$message}");
    }

    return json_decode($response->getBody(), true);
  }


  public function update($id, $endpoint, $sobject)
  {
    try {
      $url = "{$this->instanceUrl}{$endpoint}/{$id}";

      $response = $this->client->request(
        'PATCH',
        $url,
        [
          RequestOptions::HEADERS => [
            'Authorization' => "Bearer {$this->accessToken}",
            'X-PrettyPrint' => 1,
            'Content-Type' => 'application/json'
          ],
          "body" => json_encode($sobject, JSON_UNESCAPED_UNICODE),
        ]
      );
    } catch(ClientExceptionInterface $e) {
      $message = $e->getMessage();
      throw new Exception("Unable to update {$endpoint} with ID {$id}: {$message}");
    }

    return json_decode($response->getBody());
  }

  public function attach(string $id, string $fileName, \SplFileInfo $file) {
    try {
      $url = "{$this->instanceUrl}/services/data/v58.0/sobjects/ContentVersion";

      $response = $this->client->request(
        'POST',
        $url,
        [
          RequestOptions::HEADERS => [
            'Authorization' => "Bearer {$this->accessToken}",
            'X-PrettyPrint' => 1,
            'Content-Type' => 'application/json'
          ],
          "body" => json_encode([
            // 'ParentId' => $id,
            'Title' => $fileName,
            'PathOnClient' => $file->getFilename(),
            'VersionData' => base64_encode(file_get_contents($file->getPathname())),
          ], JSON_UNESCAPED_UNICODE),
        ]
      );
      $contentVersionReponse = json_decode($response->getBody());
      $contentVersion = $this->retrieve($contentVersionReponse->id, "/services/data/v58.0/sobjects/ContentVersion", ['ContentDocumentId']);
      $documentLink = $this->create([
        'ContentDocumentId'=> $contentVersion['ContentDocumentId'],
        'LinkedEntityId'=> $id,
        'ShareType'=> "V",
        'Visibility'=> "AllUsers"
      ], "/services/data/v58.0/sobjects/ContentDocumentLink");

    } catch(ClientExceptionInterface $e) {
      $message = $e->getMessage();
      throw new Exception("Unable to attach file to {$id}: {$message}");
    }

    return $contentVersion;
  }

  public function getAttachments(string $linkedEntityId): array
  {
    try {
      // Récupérer les ContentDocumentLinks liés à l'entité
      $query = urlencode("SELECT ContentDocumentId FROM ContentDocumentLink WHERE LinkedEntityId = '{$linkedEntityId}'");
      $url = "{$this->instanceUrl}/services/data/v58.0/query/?q={$query}";

      $response = $this->client->request(
        'GET',
        $url,
        [
          RequestOptions::HEADERS => [
            'Authorization' => "Bearer {$this->accessToken}",
            'Content-Type' => 'application/json'
          ],
        ]
      );

      $links = json_decode($response->getBody(), true);
      if (empty($links['records'])) {
        return [];
      }

      $attachments = [];
      foreach ($links['records'] as $link) {
        $contentDocumentId = $link['ContentDocumentId'];

        // Récupérer la dernière version du document (Title contient le format: inputName_drupalFileId[_index])
        $versionQuery = urlencode("SELECT Id, Title, PathOnClient, ContentSize FROM ContentVersion WHERE ContentDocumentId = '{$contentDocumentId}' AND IsLatest = true");
        $versionUrl = "{$this->instanceUrl}/services/data/v58.0/query/?q={$versionQuery}";

        $versionResponse = $this->client->request(
          'GET',
          $versionUrl,
          [
            RequestOptions::HEADERS => [
              'Authorization' => "Bearer {$this->accessToken}",
              'Content-Type' => 'application/json'
            ],
          ]
        );

        $versions = json_decode($versionResponse->getBody(), true);
        if (!empty($versions['records'])) {
          $version = $versions['records'][0];
          $attachments[] = [
            'id' => $version['Id'],
            'title' => $version['Title'],
            'filename' => $version['PathOnClient'],
            'size' => $version['ContentSize'],
          ];
        }
      }

      return $attachments;
    } catch (ClientExceptionInterface $e) {
      throw new Exception("Unable to get attachments for {$linkedEntityId}: " . $e->getMessage());
    }
  }
}
