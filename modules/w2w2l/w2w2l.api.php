<?php

use Drupal\webform\WebformSubmissionInterface;

/**
 * called just after the salesforce lead creation
 */
function hook_w2w2l_sent(WebformSubmissionInterface &$webformSubmission, array &$sfData, array &$sfResult) {
  
}

/**
 * called just before the salesforce lead creation
 */
function hook_w2w2l_prepare(array &$sfData,  array &$data = [] ) {
  
}
