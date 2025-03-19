<?php

namespace Drupal\w2w2l\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Annotation\WebformHandler;
use Drupal\webform\Element\WebformAjaxElementTrait;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\Twig\WebformTwigExtension;
use Drupal\webform\Utility\WebformElementHelper;
use Drupal\webform\WebformSubmissionInterface;

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
      "type" => "x-www-form-urlencoded",
      "object_url" => "",
      "salesforce_mapping" => [],
      "excluded_data" => [],
      "custom_data" => "",
      "debug" => false,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state
  ) {
    $webform = $this->getWebform();
    $form["object_url"] = [
      "#type" => "textfield",
      "#title" => $this->t("Endpoint URL"),
      "#description" => $this->t("The endpoint URL to POST to."),
      "#required" => false,
      "#default_value" => $this->configuration["object_url"],
    ];

    $map_sources = [];
    $elements = $this->webform->getElementsInitializedAndFlattened();
    foreach ($elements as $key => $element) {
      if (
        strpos($key, "#") === 0 ||
        empty($element["#title"]) ||
        !empty($element["#webform_composite_elements"])
      ) {
        if (!empty($element["#webform_composite_elements"])) {
          foreach (
            $element["#webform_composite_elements"]
            as $subkey => $subelement
          ) {
            $map_sources[$key . "_" . $subkey] =
              $element["#title"] . " - " . $subelement["#title"];
          }
        }
        continue;
      }
      $map_sources[$key] = $element["#title"];
    }
    $field_definitions = $this->submissionStorage->getFieldDefinitions();
    $field_definitions = $this->submissionStorage->checkFieldDefinitionAccess(
      $webform,
      $field_definitions
    );
    foreach ($field_definitions as $key => $field_definition) {
      $map_sources[$key] =
        $field_definition["title"] .
        " (type : " .
        $field_definition["type"] .
        ")";
    }

    $form["custom_data"] = [
      "#type" => "details",
      "#title" => $this->t("Custom data"),
      "#description" => $this->t(
        "Custom data will take precedence over submission data. You may use tokens."
      ),
    ];

    $form["custom_data"]["custom_data"] = [
      "#type" => "webform_codemirror",
      "#mode" => "yaml",
      "#title" => $this->t("Custom data"),
      "#description" => $this->t(
        "Enter custom data that will be included in all remote post requests."
      ),
      "#parents" => ["settings", "custom_data"],
      "#default_value" => $this->configuration["custom_data"],
    ];

    $form["custom_data"][
      "token_tree_link"
    ] = $this->tokenManager->buildTreeLink();

    $form["debug"] = [
      "#type" => "checkbox",
      "#title" => $this->t("Enable debugging"),
      "#description" => $this->t(
        "If checked, posted submissions will be displayed onscreen to all users."
      ),
      "#return_value" => true,
      "#default_value" => $this->configuration["debug"],
    ];

    $form["salesforce_mapping"] = [
      "#type" => "fieldset",
      "#tree" => true,
      "#prefix" => '<div id="w2w2l-mapping-table">',
      "#suffix" => "</div>",
      "#title" => $this->t("Mapping settings"),
      "#help" => $this->t(
        'Only Maps with specified "Salesforce Web-to-Lead Campaign Field" will be submitted to salesforce.'
      ),
    ];
    $form["salesforce_mapping"]["prefix"] = [
      "#type" => "markup",
      "#markup" => '<table><thead>
        <tr>
          <th>Salesforce mapping</th>
          <th>Compute value</th>
          <th></th>
        </tr>
      </thead>',
    ];

    $mappings =
      $form_state->getValue("salesforce_mapping") ?:
      $this->configuration["salesforce_mapping"];

    $form["salesforce_mapping"]["help"] = WebformTwigExtension::buildTwigHelp();

    foreach ($mappings as $i => $mapping) {
      $form["salesforce_mapping"][] = [
        "#prefix" => "<tr>",
        "#suffix" => "</tr>",
        "salesforce" => [
          "#prefix" => "<td>",
          "#suffix" => "</td>",
          "#type" => "textfield",
          "#default_value" => $mapping["salesforce"],
        ],
        "value" => [
          "#prefix" => "<td>",
          "#suffix" => "</td>",
          "#type" => "textarea",
          "#default_value" => $mapping["value"],
        ],
      ];
    }

    $form["salesforce_mapping"]["suffix"] = [
      "#type" => "markup",
      "#markup" => "</table>",
    ];

    $form["salesforce_mapping"]["footer"]["add_row"] = [
      "#type" => "submit",
      "#value" => $this->t("Add new mapping"),
      "#name" => "w2w2l_ajax_add_row_action",
      "#attributes" => [
        "class" => ["button--primary"],
      ],
      "#submit" => [[get_called_class(), "addMapping"]],
      "#ajax" => [
        "callback" => [get_called_class(), "refreshMappingAjaxCallback"],
        "wrapper" => "w2w2l-mapping-table",
        "progress" => ["type" => "fullscreen"],
      ],
    ];
    WebformElementHelper::convertRenderMarkupToStrings($form);

    return $form;
  }

  /**
   * Ajax callback for adding new rows.
   */
  public static function addMapping(array $form, FormStateInterface $form_state)
  {
    $salesforce_mapping = $form_state->get("salesforce_mapping");
    $salesforce_mapping[] = [
      "salesforce" => "",
      "value" => "",
    ];
    $form_state->set("salesforce_mapping", $salesforce_mapping);
    $form_state->setRebuild();
  }

  /**
   * Ajax callback for adding new rows.
   */
  // public static function removeMapping(
  //   array $form,
  //   FormStateInterface $form_state
  // ) {
  //   $button = $form_state->getTriggeringElement();
  //   $name = $button["#name"];
  //   $removeIndex = str_replace("remove_row_", "", $name);
  //   $mappingKeys = $form_state->get("salesforce_key");
  //   $mappingValues = $form_state->get("salesforce_value");
  //   unset($mappingKeys[$removeIndex]);
  //   unset($mappingValues[$removeIndex]);
  //   $form_state->set("salesforce_key", array_values($mappingKeys));
  //   $form_state->set("salesforce_value", array_values($mappingValues));
  //   $form_state->setRebuild();
  // }

  /**
   * Ajax callback for adding new rows.
   */
  public static function refreshMappingAjaxCallback(
    array $form,
    FormStateInterface $form_state
  ) {
    return $form["settings"]["salesforce_mapping"];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    if ($form_state->hasAnyErrors()) {
      return;
    }

    // Validate data element keys.
    $mapping = $form_state->getValue("salesforce_mapping");
    foreach ($mapping as $key => $mapping) {
      if (is_numeric($key) && count(array_filter($mapping)) === 1) {
        $form_state->setErrorByName(
          "salesforce_mapping",
          $this->t("entry %key is invalid.", ["%key" => $key])
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
    // filter empty mapping
    $this->configuration["salesforce_mapping"] = array_filter(
      $this->configuration["salesforce_mapping"],
      fn($mapping) => count(array_filter($mapping))
    );
  }

  /**************************
   **** Form submission ****
   *************************/

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission)
  {
    $sf_data = $this->prepareSfObject($webform_submission);

    $result = \Drupal::service("w2w2l.gateway")->send(
      $sf_data,
      $this->configuration["object_url"]
    );

    \Drupal::moduleHandler()->invokeAll("w2w2l_sent", [
      $webform_submission,
      $sf_data,
      $result,
    ]);
  }

  protected function prepareSfObject(
    WebformSubmissionInterface $webform_submission
  ) {
    $data = $webform_submission->getData();
    // Get Salesforce field mappings.
    $salesforce_mapping = $this->configuration["salesforce_mapping"];
    $salesforce_data = [];

    foreach ($salesforce_mapping as $mapping) {
      $value = WebformTwigExtension::renderTwigTemplate(
        $webform_submission,
        "{% endautoescape %}{$mapping["value"]}{{% endautoescape %}"
      );

      $value = trim($value);
      if (!empty($value)) {
        // Begin Only cast types
        if ($value === "false") {
          $value = false;
        }
        if ($value === "true") {
          $value = true;
        }
        // End Only cast types

        $dimArray = $mapping["salesforce"];
        // If the key has a dot
        $key_parts = explode(".", $dimArray);

        $dimArray = $this->digArrayAndPlaceAtLast($key_parts, [], $value);
        $value = $dimArray;
        $salesforce_data = array_merge_recursive($salesforce_data, $value);
      }
    }

    \Drupal::moduleHandler()->invokeAll("w2w2l_prepare", [
      &$salesforce_data,
      &$data,
    ]);
    return $salesforce_data;
  }

  private function digArrayAndPlaceAtLast(
    array $keys,
    array $target,
    $value = null
  ): array {
    if ($keys) {
      $key = array_shift($keys);
      $target[$key] = $this->digArrayAndPlaceAtLast($keys, [], $value);
    } else {
      $target = $value;
    }

    return $target;
  }
}
