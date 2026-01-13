<?php

declare(strict_types=1);

namespace Drupal\favicon_ico_redirect\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

final class FaviconIcoRedirectController extends ControllerBase {

  protected $themeManager;

  public function __construct(ThemeManagerInterface $theme_manager) {
    $this->themeManager = $theme_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('theme.manager')
    );
  }

  public function __invoke(): RedirectResponse {
    $active_theme = $this->themeManager->getActiveTheme();
    $theme_name = $active_theme->getName();
    $theme_settings = $this->config($theme_name . '.settings');
    $favicon_path = $theme_settings->get('favicon.path');

    if (empty($favicon_path)) {
      $favicon_path = '/' . $active_theme->getPath() . '/favicon.ico';
    } else {
      $favicon_path = '/' . ltrim($favicon_path, '/');
    }

    return new RedirectResponse($favicon_path, 301);
  }
}
