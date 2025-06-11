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
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "webform2web2lead",
 *   label = @Translation("Webform to Web2Lead"),
 *   category = @Translation("Salesforce"),
 *   description = @Translation("Sends submission data to Salesforce"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */

final class W2W2LWebFormHandler extends WebformHandlerBase
{

  /**
   * Avoid using proper DIC here and rely on the parent class constructor.
   *
   * The parent class constructor relies on 9 injected services, thus is very
   * cumbersome to override and maintain. By using this quick and dirty helper,
   * we can more simply wrap the event dispatcher service.
   * 
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher service.
   */
  protected function eventDispatcher()
  {
    if (!$this->eventDispatcher) {
      $this->eventDispatcher = \Drupal::service('event_dispatcher');
    }

    return $this->eventDispatcher;
  }

  //ov2
  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Typical salesforce campaign fields.
   *
   * Used for available list of campaign fields.
   *
   * @var array
   *
   * @see https://help.salesforce.com/articleView?id=setting_up_web-to-lead.htm&type=0
   */
  protected $salesforceCampaignFields = [''];

  /**
   * {@inheritdoc}
   */
  //cv2


  /**
   * @var ConfigFactoryInterface
   */
  protected $configFactory;



  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @param LoggerChannelFactoryInterface $logger_factory

   */


  /**
   * @param ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   *
   * @return ContainerFactoryPluginInterface|EmailWebformHandler|WebformHandlerBase|WebformHandlerInterface|WebformHandlerMessageInterface|static
   */

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration()
  {
    return [
      'type' => 'x-www-form-urlencoded',
      'object_url' => '',
      'salesforce_mapping' => [],
      'excluded_data' => [],
      'custom_data' => '',
      'debug' => FALSE,
    ];
  }




  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state)
  {
    $webform = $this->getWebform();
    $form['object_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint URL'),
      '#description' => $this->t('The endpoint URL to POST to.'),
      '#required' => false,
      '#default_value' => $this->configuration['object_url'],
    ];

    $map_sources = [];
    $elements = $this->webform->getElementsInitializedAndFlattened();
    foreach ($elements as $key => $element) {
      if (strpos($key, '#') === 0 || empty($element['#title']) || !empty($element['#webform_composite_elements'])) {
        if (!empty($element['#webform_composite_elements'])) {
          foreach ($element['#webform_composite_elements'] as $subkey => $subelement) {
            $map_sources[$key . '_' . $subkey] = $element['#title'] . ' - ' . $subelement['#title'];
          }
        }
        continue;
      }
      $map_sources[$key] = $element['#title'];
    }
    $field_definitions = $this->submissionStorage->getFieldDefinitions();
    $field_definitions = $this->submissionStorage->checkFieldDefinitionAccess($webform, $field_definitions);
    foreach ($field_definitions as $key => $field_definition) {
      $map_sources[$key] = $field_definition['title'] . ' (type : ' . $field_definition['type'] . ')';
    }

    $form['salesforce_mapping'] = [
      '#type' => 'webform_mapping',
      '#title' => $this->t('Webform to Salesforce mapping'),
      '#description' => $this->t('Only Maps with specified "Salesforce Web-to-Lead Campaign Field" will be submitted to salesforce.'),
      '#source__title' => t('Webform Submitted Data'),
      '#destination__title' => t('Salesforce Web-to-Lead Campaign Field'),
      '#source' => $map_sources,
      '#destination__type' => 'webform_select_other',
      '#destination' => array_combine($this->salesforceCampaignFields, $this->salesforceCampaignFields),
      '#default_value' => $this->configuration['salesforce_mapping'],
    ];

    $form['custom_data'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom data'),
      '#description' => $this->t('Custom data will take precedence over submission data. You may use tokens.'),
    ];

    $form['custom_data']['custom_data'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Custom data'),
      '#description' => $this->t('Enter custom data that will be included in all remote post requests.'),
      '#parents' => ['settings', 'custom_data'],
      '#default_value' => $this->configuration['custom_data'],
    ];
    $form['custom_data']['custom_data'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Insert data'),
      '#description' => $this->t("Enter custom data that will be included when a webform submission is saved."),
      '#parents' => ['settings', 'custom_data'],
      '#default_value' => $this->configuration['custom_data'],
    ];

    $form['custom_data']['token_tree_link'] = $this->tokenManager->buildTreeLink();

    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging'),
      '#description' => $this->t('If checked, posted submissions will be displayed onscreen to all users.'),
      '#return_value' => TRUE,
      '#default_value' => $this->configuration['debug'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission)
  {
    $sf_data = $this->prepareSfObject($webform_submission);
    if($sf_data === []) {
      \Drupal::logger('w2w2l')->warning('No data to send to Salesforce.');
      return;
    }else{
    $result = \Drupal::service('w2w2l.gateway')
      ->send($sf_data, $this->configuration['object_url']);
    }

    \Drupal::moduleHandler()->invokeAll(
      'w2w2l_sent', 
      [&$webform_submission, &$sf_data, &$result]
    );

  }

  protected function prepareSfObject(WebformSubmissionInterface $webform_submission)
  {
    $data = $webform_submission->getData();
    // Get Salesforce field mappings.
    $salesforce_mapping = $this->configuration['salesforce_mapping'];
    $salesforce_data = [];

    foreach ($data as $key => $value) {
      if (is_array($value)) {
        foreach ($value as $sub_key => $sub_value) {
          $new_key = $key . '_' . $sub_key;
          if (array_key_exists($new_key, $salesforce_mapping)) {
            $salesforce_data[$salesforce_mapping[$new_key]] = $sub_value;
          }
        }
        continue;
      }

      // if it's a simple key , place it , else do a deeeeep array dive and place the value 
      if (array_key_exists($key, $salesforce_mapping)) {
        $value = trim($value);
        if(!empty($value)) {

          // Begin Only cast types
          $value = html_entity_decode($value);

          $value = mb_convert_encoding($value, 'UTF-8', mb_detect_encoding($value));
          if ($value === "false") {
            $value = false;
          }
          if ($value === "true") {
            $value = true;
          }
          // End Only cast types

          $dimArray = $salesforce_mapping[$key];
          // If the key has a dot 
          $key_parts = explode('.', $dimArray);
          
          $dimArray =  $this->digArrayAndPlaceAtLast($key_parts, [], $value);
          $value = $dimArray;
          $salesforce_data = array_merge_recursive($salesforce_data, $value);

        }
      }
    }
    \Drupal::moduleHandler()->invokeAll(
      'w2w2l_prepare', 
      [&$salesforce_data, &$data  ]
    );
    return $salesforce_data;
  }

  private function digArrayAndPlaceAtLast(array $keys, array $target, $value = null)
  {
    if ($keys) {
      $key = array_shift($keys);
      $target[$key] = $this->digArrayAndPlaceAtLast($keys, [], $value);
    } else {
      $target =  $value;
    }
  
    return $target;
  }
  
}
