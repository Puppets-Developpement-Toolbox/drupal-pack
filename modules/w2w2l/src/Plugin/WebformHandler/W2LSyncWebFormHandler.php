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
      "debug" => false,
      "prefill_param_name" => "sf_id",
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
      "#required" => true,
      "#default_value" => $this->configuration["object_url"],
    ];

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
    ];

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
      if(str_ends_with($element['#type'], '_file')) {
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


      foreach($files as $key => $file) {
        $fileEntity = $fileStorage->load($file);
        $result = \Drupal::service("w2w2l.gateway")->attach(
          $id,
          new \SplFileObject($fileEntity->getFileUri())
        );
      }
    } catch (\Exception $e) {
      \Drupal::logger("w2w2l")->error(
        "Error sending data to Salesforce: @message",
        ["@message" => $e->getMessage()]
      );
      $result['success'] = false;
      $result['errorMessage'] = $e->getMessage();
    }

    \Drupal::moduleHandler()->invokeAll("w2w2l_sent", [
      $webform_submission,
      $data,
      $result,
    ]);
  }

}
