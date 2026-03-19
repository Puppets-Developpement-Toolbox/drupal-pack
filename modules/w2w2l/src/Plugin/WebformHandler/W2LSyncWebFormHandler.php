<?php

namespace Drupal\w2w2l\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileStorageInterface;
use Drupal\webform\Annotation\WebformHandler;
use Drupal\webform\Element\WebformAjaxElementTrait;
use Drupal\webform\Plugin\WebformHandlerBase;
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

  private $sfData = [];

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
    if(empty($id)) return;

    $salesforce = \Drupal::service("w2w2l.gateway");
    $retrieved = $salesforce->retrieve($id, $this->configuration["object_url"]);

    if($retrieved['success']) {
      $this->sfData[$id] = $retrieved['data'];
      $sfObject = array_change_key_case($retrieved['data'], CASE_LOWER);

      // Récupérer les pièces jointes depuis Salesforce
      $attachments = $salesforce->getAttachments($id);

      $filesByElement = [];
      if ($attachments['success'] && !empty($attachments['data'])) {
        foreach ($attachments['data'] as $attachment) {
          foreach ($elements as $key => $value) {
            if (str_starts_with(strtolower($attachment['title']), strtolower($key))) {
              $filesByElement[$key][] = $attachment;
            }
          }
        }
      }

      foreach($elements as $key => $element) {
        // Précharger les fichiers
        if (isset($filesByElement[$key])) {
          if(!empty($filesByElement[$key])) {
            $elements[$key]['#w2w2l_uploaded_files'] = $filesByElement[$key];
          }
          continue;
        }

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


  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission)
  {
    $idKey = $this->configuration['prefill_param_name'];
    $id = \Drupal::request()->query->get($idKey, null);

    if(empty($id)) return;

    $data = $webform_submission->getData();
    $data = array_filter($data);
    if(!empty($data[$idKey])) unset($data[$idKey]);

    $webform = $webform_submission->getWebform();
    /** @var FileStorageInterface $fileStorage */
    $fileStorage = \Drupal::entityTypeManager()->getStorage('file');

    $inputFiles = [];
    $sfObject = $this->sfData[$id];
    if(!$sfObject) return;
    $skPpts = array_map('strtolower', array_keys($sfObject));

    foreach($data as $key => $value) {
      $element = $webform->getElement($key);
      if(str_ends_with($element['#type'], '_file') || $element['#type'] === 'webform_dropzonejs') {
        $inputFiles[$key] = $element['#multiple'] ? (array)$value : $value;
        unset($data[$key]);
      }
      if(!in_array(strtolower($key), $skPpts)) {
        unset($data[$key]);
      }
    }

    \Drupal::moduleHandler()->invokeAll("w2w2l_sync_prepare", [
      $webform_submission,
      current($this->sfData),
      &$data,
      &$inputFiles
    ]);

    try{
      $result = \Drupal::service("w2w2l.gateway")->update(
        $id,
        $this->configuration["object_url"],
        $data
      );

      foreach($inputFiles as $inputName => $inputFile) {
        $isMultiple = is_array($inputFile);
        foreach((array)$inputFile as $index => $file) {
          $fileEntity = $fileStorage->load($file);
          $fileName = [$inputName, $fileEntity->id(), $id];
          if($isMultiple) $fileName[] = $index + 1;
          $result = \Drupal::service("w2w2l.gateway")->attach(
            $id,
            implode('_', $fileName),
            new \SplFileObject($fileEntity->getFileUri())
          );
        }
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
