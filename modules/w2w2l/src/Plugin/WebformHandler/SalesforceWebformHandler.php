<?php

namespace Drupal\w2w2l\Plugin\WebformHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\webform\Annotation\WebformHandler;
use Drupal\webform\Plugin\WebformHandler\EmailWebformHandler;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\Plugin\WebformHandlerInterface;
use Drupal\webform\Plugin\WebformHandlerMessageInterface;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;



use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "salesforce_webform_handler",
 *   label = @Translation("Salesforce webform handler"),
 *   category = @Translation("Salesforce"),
 *   description = @Translation("Sends submission data to Salesforce"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
final class SalesforceWebformHandler extends WebformHandlerBase
{

  /**
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var WebformSubmissionConditionsValidatorInterface
   */
  protected $conditionsValidator;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   *
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param LoggerChannelFactoryInterface $logger_factory
   * @param ConfigFactoryInterface $config_factory
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param WebformSubmissionConditionsValidatorInterface $conditions_validator
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
    EntityTypeManagerInterface $entity_type_manager,
    WebformSubmissionConditionsValidatorInterface $conditions_validator
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->loggerFactory = $logger_factory->get('sa_handler');
    $this->configFactory = $config_factory;
    $this->conditionsValidator = $conditions_validator;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * @param ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return ContainerFactoryPluginInterface|EmailWebformHandler|WebformHandlerBase|WebformHandlerInterface|WebformHandlerMessageInterface|static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('webform_submission.conditions_validator')
    );
    $instance->setConfiguration($configuration);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration()
  {
    return [];
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
  {
    
    $webform = $webform_submission->getWebform()->id();
    $input = $webform_submission->getData();
    $hardCode = [
      'salutation' => 'Madame, Monsieur', //'Salutation'
      'leadSource' => 'Sodexo Live Hospitality France Lead',
      'rating' => 'Warm', //'Rating'
      'country' => 'FR', //CountryCode
      'categoryClient' => 'Other', //'Client_Category__c'
      'categoryClient2' => 'Other', //'Client_Category2__c'
      'sector' => 'Other', //'Activity_Sector__c'
    ];

    $final = $hardCode;
    $input = $webform_submission->getData();

    switch ($webform) {
      case 'contact':
        $final = $hardCode;

        $final['firstname'] = $input["name"];
        $final['lastname'] = $input["last_name"];
        $final['company'] = $input["company"];
        $final['email'] = $input["email"];
        $final["phone"] = $input["phone"];
        $final["phone"] = str_replace(' ', '', $final["phone"]);
        $final["phone"] = str_replace('-', '', $final["phone"]);
        if ($input['person_type']== 'common'){
          $final["categoryClient"] = 'Individual';
          $final["categoryClient2"]= 'Individual' ;
          $final["sector"] = 'Individual';
        }


        // get the earliest date from the events
        $eventDates = [];
        $eventResumes = [];
        foreach($input['concerned_events'] as $nid){
          $node = Node::load($nid);

          if(!$node->get('field_setup_begin_date')->isEmpty()) {
            $nodeDate = $node->get('field_setup_begin_date')->date->format('Y-m-d');
            $eventDates[]= $nodeDate;
          }

          $url_object = \Drupal\Core\Url::fromRoute(
            'entity.node.canonical', 
            ['node' => $nid], 
            ['absolute' => TRUE]
          );
          $eventResumes[] = $node->getTitle() . ' : '. $url_object->toString(); 
        }

        sort($eventDates);
        $final["eventDate"] = count($eventDates) ? $eventDates[0] : null;

        $final['spoken'] =  \Drupal::LanguageManager()->getCurrentLanguage()->getId() === "fr" ? 'French' : 'English';

        //load a taxonomy term
        if(!empty($input["event_key"])) {
          $eventCta = \Drupal::entityQuery('node')
            ->condition('type', 'generic_event')
            ->condition('field_setup_contact_event_key', $input["event_key"])
            ->accessCheck(TRUE)
            ->execute();
            
          if(!empty($eventCta)){
            $eventCta = reset($eventCta);
            
            if(
              ($node = Node::load($eventCta)) && 
              !$node->get('field_setup_event_category')->isEmpty()
            ) {
              $final['hosp_c'] = $node->get('field_setup_event_category')->entity->label();
            }
          }

        }

        $commentaryCompose[] = "=INFO=";  
        $commentaryCompose[] = "Message :";
        $commentaryCompose[] = $input["message"];
        
        $events [] = 'Choix du(des) évènements : ';
        foreach($input['concerned_events'] as $event => $nid){
          $node = Node::load($nid);
          $url_object = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $nid], ['absolute' => TRUE]);
          $events [] = $node->getTitle() . ' : '. $url_object->toString();
        }
        $events = \implode("\n", $events);
        $commentaryCompose[]= $events;
        $final["commentary"]  = \implode("\n\n", $commentaryCompose);

        break;
    }

    if(empty($final['company']) && !empty($final['lastname'])){
      $final["company"] = 'FOYER: '.$final['lastname'];
    }
    
    $result = \Drupal::service('w2w2l.gateway')->contactHospitality([
      ...$final
    ]);

    $salesforcelink =  $result['success']?"https://sodexocdm.my.salesforce.com/{$result['id']}":'Something\'s wrong with Salesforce';
    $input['salesforce_link'] = $salesforcelink;
    $webform_submission->setData($input);

  }
}
