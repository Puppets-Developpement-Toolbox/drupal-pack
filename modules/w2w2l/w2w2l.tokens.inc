<?php

use Drupal\Core\Render\BubbleableMetadata;

/**
* Implements hook_token_info().
*/
function w2w2l_token_info() {
  $types['w2w2l'] = [
    'name' => t('Webform 2 Web2lead'),
    'description' => t('Define custom tokens to handle cookie value.'),
    // 'needs-data' => 'w2w2l',
  ];
  $cookies = [
    'gclid',
    'utm_campaign',
    'utm_term',
    'utm_medium',
    'utm_content',
    'utm_source',
    'arrivalCookie'
  ];
  foreach($cookies as $cookie) {
    $tokens["cookie:{$cookie}"] = [
      'name' => t('Cookie value'),
      'description' => t('Token to get current cookie value.'),
    ];
  }

  return [
    'types' => $types,
    'tokens' => ['w2w2l' => $tokens],
  ];
}

/**
* Implements hook_tokens().
*/
function w2w2l_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];
  if ($type == 'w2w2l') {
    $bubbleable_metadata->setCacheMaxAge(0);
    foreach($tokens as $token => $original) {
      $suffix = str_replace('cookie:', '', $token);
      $request = Drupal::request();
      $replacement = $request->cookies->get("w2w2l-{$suffix}", '');
      if(empty($replacement)) {
        $replacement = $request->query->get($suffix);
      }
    }
  }
  return $replacements;
}
