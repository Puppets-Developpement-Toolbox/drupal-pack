<?php declare(strict_types = 1);

namespace Drupal\ppts_design\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Puppets design demonstrator routes.
 */
final class DesignController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke($design = null): array {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
      '#cache' => [
        'tags' => ["ppts-design:{$design}"]
      ]
    ];

    return $build;
  }

}
