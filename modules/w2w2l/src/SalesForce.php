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
      $_ENV['SF_LOGIN'],
      $_ENV['SF_PASSWORD'],
      $_ENV['SF_SECURE_TOKEN']
    );
  }

  public function contactHospitality($formData)
  {
    $lead = $this->baseSObjectHos($formData);

    $lead->Salutation = $formData["salutation"] ?? 'Madame, Monsieur';
    $lead->FirstName = $formData["firstname"];
    $lead->LastName  = $formData["lastname"];
    $lead->Company = $formData["company"] ?? "Individual";
    $lead->Email = $formData["email"];
    $lead->MobilePhone = $formData["phone"]; 
    $lead->Primary_Event_Date__c = $formData["eventDate"];
    $lead->Client_Category__c = $formData["categoryClient"] ?? "Other";
    $lead->Client_Category2__c = $formData["categoryClient2"] ?? "Other";
    $lead->Activity_Sector__c = $formData["sector"] ?? "Other";
    $lead->Lead_type__c = $formData['leadType'] ?? "Sodexo_Live";
    $lead->CountryCode = $formData['country'] ?? 'FR';
    $lead->Type_de_demande__c = $formData["typeDemande"] ?? "Contact";
    $lead->RecordType =  ["Name" => "Lead FR"];
    $lead->Type_formulaire__c = "Formulaire contact site SLH - Professionnel";
    $lead->Interested_By__c = "Hospitalité";
    $lead->Hospitalite__c = $formData['hosp_c']??'Autre (préciser)';
    if($lead->Hospitalite__c == 'Autre (préciser)'){
      $lead->Preciser__c = 'A qualifier';
    }
    $lead->HasOptedOutOfEmail = $formData["optout"] ?? true;

    return $this->send($lead);
  }

  // Base Object different for ydp to avoid regressions on over projects ( if this module is ever shared between them)
  private function baseSObjectHos($data)
  {
    $lead = new \stdClass();
    //@todo "origine de la demande" Internet site web générique SLH 
    $lead->LeadSource = $data['source'] ?? "Sodexo Live Hospitality France Lead";
    $lead->Rating = $formData["rating"] ?? "Warm";
    $lead->Description = $data["commentary"] ?? '';
    
    if (null !== \Drupal::request()->cookies->get('gclid')) {
      $lead->GCLID__c = \Drupal::request()->cookies->get('gclid');
    }

    return $lead;
  }

  private function send($lead)
  {  
    try {
      $r = $this->client->create($lead, 'Lead');
      $success = $r->success;
    }
    catch(\GuzzleHttp\Exception\ClientException $e)
    {
    $response = $e->getResponse();
      \Drupal::logger('w2w2l')->error($response->getBody()->getContents());
      return false;
    }
    catch (\Throwable $e) {
      \Drupal::logger('w2w2l')->error("error while registering a lead: " . $e->getMessage());
      return false;
    }
    
    
    if (!$success) {
      \Drupal::logger('w2w2l')
        ->error("error while registering a lead: " . json_encode($r, JSON_PRETTY_PRINT));
    }
    \Drupal::logger('w2w2l')
      ->info("lead registered: " . json_encode($r, JSON_PRETTY_PRINT));
    return ['success' => $success, 'id' => $r->id??'',];
  }
}
