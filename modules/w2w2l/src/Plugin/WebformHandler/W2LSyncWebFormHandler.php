<?php

namespace Drupal\w2w2l\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileStorageInterface;
use Drupal\webform\Annotation\WebformHandler;
use Drupal\webform\Element\WebformAjaxElementTrait;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\Twig\WebformTwigExtension;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 *
 * @WebformHandler(
 *   id = "webform2web2lead_sync",
 *   label = @Translation("Web2Lead synced form"),
 *   category = @Translation("Salesforce"),
 *   description = @Translation("Sync submission data with Salesforce"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
final class W2LSyncWebFormHandler extends WebformHandlerBase
{
  use WebformAjaxElementTrait;

  /**************************
   ** Plugin Configuration **
   *************************/

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration()
  {
    return [
      "object_url" => "",
      // "salesforce_mapping" => [],
      "debug" => false,
      "prefill_param_name" => "sf_id",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_statedum
  ) {
    $webform = $this->getWebform();
    $form["object_url"] = [
      "#type" => "textfield",
      "#title" => $this->t("Endpoint URL"),
      "#description" => $this->t("The endpoint URL to POST to."),
      "#required" => true,
      "#default_value" => $this->configuration["object_url"],
    ];

    // $map_sources = [];
    // $elements = $this->webform->getElementsInitializedAndFlattened();
    // foreach ($elements as $key => $element) {
    //   if (
    //     strpos($key, "#") === 0 ||
    //     empty($element["#title"]) ||
    //     !empty($element["#webform_composite_elements"])
    //   ) {
    //     if (!empty($element["#webform_composite_elements"])) {
    //       foreach (
    //         $element["#webform_composite_elements"]
    //         as $subkey => $subelement
    //       ) {
    //         $map_sources[$key . "_" . $subkey] =
    //           $element["#title"] . " - " . $subelement["#title"];
    //       }
    //     }
    //     continue;
    //   }
    //   $map_sources[$key] = $element["#title"];
    // }
    //
    // $field_definitions = $this->submissionStorage->getFieldDefinitions();
    // $field_definitions = $this->submissionStorage->checkFieldDefinitionAccess(
    //   $webform,
    //   $field_definitions
    // );
    // foreach ($field_definitions as $key => $field_definition) {
    //   $map_sources[$key] =
    //     $field_definition["title"] .
    //     " (type : " .
    //     $field_definition["type"] .
    //     ")";
    // }

    $form["debug"] = [
      "#type" => "checkbox",
      "#title" => $this->t("Enable debugging"),
      "#description" => $this->t(
        "If checked, posted submissions will be displayed onscreen to all users."
      ),
      "#return_value" => true,
      "#default_value" => $this->configuration["debug"],
    ];

    $form["prefill_param_name"] = [
      "#type" => "textfield",
      "#title" => $this->t("URL parameter name"),
      "#description" => $this->t(
        "The name of the URL parameter containing the Salesforce ID (e.g., 'sf_id' for ?sf_id=00Q...)."
      ),
      '#required' => true,
      "#default_value" => $this->configuration["prefill_param_name"],
      // "#states" => [
      //   "visible" => [
      //     ":input[name=\"settings[enable_prefill]\"]" => ["checked" => true],
      //   ],
      // ],
    ];

    // $form["prefill"]["prefill_mapping"] = [
    //   "#type" => "details",
    //   "#title" => $this->t("Prefill field mapping"),
    //   "#description" => $this->t(
    //     "Map Salesforce fields to webform fields for prefilling."
    //   ),
    //   "#tree" => true,
    //   "#prefix" => '<div id="w2w2l-prefill-mapping-table">',
    //   "#suffix" => "</div>",
    //   "#states" => [
    //     "visible" => [
    //       ":input[name=\"settings[enable_prefill]\"]" => ["checked" => true],
    //     ],
    //   ],
    // ];

    // $prefill_mappings =
    //   $form_state->getValue("prefill_mapping") ?:
    //   $this->configuration["prefill_mapping"];

    // $form["prefill"]["prefill_mapping"]["prefix"] = [
    //   "#type" => "markup",
    //   "#markup" => '<table><thead>
    //     <tr>
    //       <th>Salesforce field</th>
    //       <th>Webform field</th>
    //     </tr>
    //   </thead>',
    // ];

    // foreach ($prefill_mappings as $i => $mapping) {
    //   $form["prefill"]["prefill_mapping"][$i] = [
    //     "#prefix" => "<tr>",
    //     "#suffix" => "</tr>",
    //     "salesforce_field" => [
    //       "#prefix" => "<td>",
    //       "#suffix" => "</td>",
    //       "#type" => "textfield",
    //       "#default_value" => $mapping["salesforce_field"] ?? "",
    //       "#placeholder" => "FirstName",
    //     ],
    //     "webform_field" => [
    //       "#prefix" => "<td>",
    //       "#suffix" => "</td>",
    //       "#type" => "select",
    //       "#options" => $map_sources,
    //       "#default_value" => $mapping["webform_field"] ?? "",
    //       "#empty_option" => $this->t("- Select field -"),
    //     ],
    //   ];
    // }

    // $form["prefill"]["prefill_mapping"]["suffix"] = [
    //   "#type" => "markup",
    //   "#markup" => "</table>",
    // ];

    // $form["prefill"]["prefill_mapping"]["footer"]["add_prefill_row"] = [
    //   "#type" => "submit",
    //   "#value" => $this->t("Add prefill mapping"),
    //   "#name" => "w2w2l_ajax_add_prefill_row_action",
    //   "#attributes" => [
    //     "class" => ["button--primary"],
    //   ],
    //   "#submit" => [[get_called_class(), "addPrefillMapping"]],
    //   "#ajax" => [
    //     "callback" => [get_called_class(), "refreshPrefillMappingAjaxCallback"],
    //     "wrapper" => "w2w2l-prefill-mapping-table",
    //     "progress" => ["type" => "fullscreen"],
    //   ],
    // ];

    // $form["salesforce_mapping"] = [
    //   "#type" => "fieldset",
    //   "#tree" => true,
    //   "#prefix" => '<div id="w2w2l-mapping-table">',
    //   "#suffix" => "</div>",
    //   "#title" => $this->t("Mapping settings"),
    //   "#help" => $this->t(
    //     'Only Maps with specified "Salesforce Web-to-Lead Campaign Field" will be submitted to salesforce.'
    //   ),
    // ];
    // $form["salesforce_mapping"]["prefix"] = [
    //   "#type" => "markup",
    //   "#markup" => '<table><thead>
    //     <tr>
    //       <th>Salesforce mapping</th>
    //       <th>Compute value</th>
    //       <th></th>
    //     </tr>
    //   </thead>',
    // ];

    // $mappings =
    //   $form_state->getValue("salesforce_mapping") ?:
    //   $this->configuration["salesforce_mapping"];

    // $form["salesforce_mapping"]["help"] = WebformTwigExtension::buildTwigHelp();

    // foreach ($mappings as $i => $mapping) {
    //   $form["salesforce_mapping"][] = [
    //     "#prefix" => "<tr>",
    //     "#suffix" => "</tr>",
    //     "salesforce" => [
    //       "#prefix" => "<td>",
    //       "#suffix" => "</td>",
    //       "#type" => "textfield",
    //       "#default_value" => $mapping["salesforce"],
    //     ],
    //     "value" => [
    //       "#prefix" => "<td>",
    //       "#suffix" => "</td>",
    //       "#type" => "textarea",
    //       "#default_value" => $mapping["value"],
    //     ],
    //   ];
    // }

    // $form["salesforce_mapping"]["suffix"] = [
    //   "#type" => "markup",
    //   "#markup" => "</table>",
    // ];

    // $form["salesforce_mapping"]["footer"]["add_row"] = [
    //   "#type" => "submit",
    //   "#value" => $this->t("Add new mapping"),
    //   "#name" => "w2w2l_ajax_add_row_action",
    //   "#attributes" => [
    //     "class" => ["button--primary"],
    //   ],
    //   "#submit" => [[get_called_class(), "addMapping"]],
    //   "#ajax" => [
    //     "callback" => [get_called_class(), "refreshMappingAjaxCallback"],
    //     "wrapper" => "w2w2l-mapping-table",
    //     "progress" => ["type" => "fullscreen"],
    //   ],
    // ];
    WebformElementHelper::convertRenderMarkupToStrings($form);

    return $form;
  }



  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
  }

  public function alterElements(array &$elements, WebformInterface $webform) {
    $id = \Drupal::request()->query->get($this->configuration['prefill_param_name'], null);
    if($id) {
      $salesforce = \Drupal::service("w2w2l.gateway");
      $retrieved = $salesforce->retrieve($id, $this->configuration["object_url"]);
      if($retrieved['success']) {
        $sfObject = array_change_key_case($retrieved['data'], CASE_LOWER);
        foreach($elements as $key => $element) {
          if(empty($sfObject[$key])) continue;

          switch ($element['#type']) {
            case 'textfield':
            case 'email':
            case 'select':
            case 'checkbox':
            case 'radios':
            case 'textarea':
              $elements[$key]['#default_value'] = $sfObject[$key];
              break;
            default:
              break;
          }
        }
      }
    }
  }


  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission)
  {
    $id = \Drupal::request()->query->get($this->configuration['prefill_param_name'], null);

    if(empty($id)) return;

    $data = $webform_submission->getData();
    $data = array_filter($data);
    $webform = $webform_submission->getWebform();
    /** @var FileStorageInterface $fileStorage */
    $fileStorage = \Drupal::entityTypeManager()->getStorage('file');

    $files = [];
    foreach($data as $key => $value) {
      $element = $webform->getElement($key);
      if($element['#type'] == 'webform_document_file') {
        $files[$key] = $value;
        unset($data[$key]);
      }
    }

    try{
      $result = \Drupal::service("w2w2l.gateway")->update(
        $id,
        $this->configuration["object_url"],
        $data
      );
      dump($result);

      foreach($files as $key => $file) {
        $fileEntity = $fileStorage->load($file);
        $result = \Drupal::service("w2w2l.gateway")->attach(
          $id,
          new \SplFileObject($fileEntity->getFileUri())
        );
        dump($result);
      }
    } catch (\Exception $e) {
      \Drupal::logger("w2w2l")->error(
        "Error sending data to Salesforce: @message",
        ["@message" => $e->getMessage()]
      );
      $result['success'] = false;
      $result['errorMessage'] = $e->getMessage();
    }
die();
    \Drupal::moduleHandler()->invokeAll("w2w2l_sent", [
      $webform_submission,
      $data,
      $result,
    ]);
  }

}
