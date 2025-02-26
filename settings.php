<?php

// in some case this can be called twice
if(!function_exists('ppts_env')) {
  /**
   * @var string $key
   * @var mixed $default
   * return env value,
<<<<<<< Updated upstream
   * throw exception 
=======
   * throw exception
>>>>>>> Stashed changes
   */
  function ppts_env(string $key) {
    $args = func_get_args();
    if(count($args) === 1) {
      return $_ENV[$key] ?? throw new InvalidArgumentException("Env {$key} is missing");
    }
    return $_ENV[$key] ?? $args[1];
  }
}

/**
 * force CSS and JS aggregation.
 */
$config['system.performance']['css']['preprocess'] = true;
$config['system.performance']['js']['preprocess'] = true;
<<<<<<< Updated upstream

$settings['container_yamls'][] = __DIR__ . '/services.yml';

/**
 * Location of the site configuration files.
 *
 * The $settings['config_sync_directory'] specifies the location of file system
 * directory used for syncing configuration data. On install, the directory is
 * created. This is used for configuration imports.
 *
 * The default location for this directory is inside a randomly-named
 * directory in the public files path. The setting below allows you to set
 * its location.
 */
$settings["config_sync_directory"] = $app_root . "/../config/sync";
=======

$settings['container_yamls'][] = __DIR__ . '/services.yml';

>>>>>>> Stashed changes

/**
 * Deployment identifier.
 *
 * Drupal's dependency injection container will be automatically invalidated and
 * rebuilt when the Drupal core version changes. When updating contributed or
 * custom code that changes the container, changing this identifier will also
 * allow the container to be invalidated as soon as code is deployed.
 */
$settings['deployment_identifier'] = ppts_env('APP_VERSION', \Drupal::VERSION);


/**
 * Access control for update.php script.
 *
 * If you are updating your Drupal installation using the update.php script but
 * are not logged in using either an account with the "Administer software
 * updates" permission or the site maintenance account (the account that was
 * created during installation), you will need to modify the access check
 * statement below. Change the FALSE to a TRUE to disable the access check.
 * After finishing the upgrade, be sure to open this file again and change the
 * TRUE back to a FALSE!
 */
$settings['update_free_access'] = FALSE;
<<<<<<< Updated upstream



$settings["update_free_access"] = false;
/**
 * Authorized file system operations:
 *
 * The Update Manager module included with Drupal provides a mechanism for
 * site administrators to securely install missing updates for the site
 * directly through the web user interface. On securely-configured servers,
 * the Update manager will require the administrator to provide SSH or FTP
 * credentials before allowing the installation to proceed; this allows the
 * site to update the new files as the user who owns all the Drupal files,
 * instead of as the user the webserver is running as. On servers where the
 * webserver user is itself the owner of the Drupal files, the administrator
 * will not be prompted for SSH or FTP credentials (note that these server
 * setups are common on shared hosting, but are inherently insecure).
 *
 * Some sites might wish to disable the above functionality, and only update
 * the code directly via SSH or FTP themselves. This setting completely
 * disables all functionality related to these authorized file operations.
 *
 * @see https://www.drupal.org/node/244924
 *
 * Remove the leading hash signs to disable.
 */
$settings["allow_authorize_operations"] = false;
=======
>>>>>>> Stashed changes


/**
 * Private file path:
 *
 * A local file system path where private files will be stored. This directory
 * must be absolute, outside of the Drupal installation directory and not
 * accessible over the web.
 *
 * Note: Caches need to be cleared when this value is changed to make the
 * private:// stream wrapper available to the system.
 *
 * See https://www.drupal.org/documentation/modules/file for more information
 * about securing private files.
 */
$settings['file_private_path'] = "{$app_root}/../storage/private";
$settings['php_storage']['twig']['directory'] = "../storage/php";

/**
 * The default list of directories that will be ignored by Drupal's file API.
 *
 * By default ignore node_modules and bower_components folders to avoid issues
 * with common frontend tools and recursive scanning of directories looking for
 * extensions.
 *
 * @see \Drupal\Core\File\FileSystemInterface::scanDirectory()
 * @see \Drupal\Core\Extension\ExtensionDiscovery::scanDirectory()
 */
$settings['file_scan_ignore_directories'] = [
  'node_modules',
  'bower_components',
];

/**
 * The default number of entities to update in a batch process.
 *
 * This is used by update and post-update functions that need to go through and
 * change all the entities on a site, so it is useful to increase this number
 * if your hosting configuration (i.e. RAM allocation, CPU speed) allows for a
 * larger number of entities to be processed in a single batch run.
 */
$settings['entity_update_batch_size'] = 50;

/**
 * Entity update backup.
 *
 * This is used to inform the entity storage handler that the backup tables as
 * well as the original entity type and field storage definitions should be
 * retained after a successful entity update process.
 */
$settings['entity_update_backup'] = TRUE;

/**
 * Node migration type.
 *
 * This is used to force the migration system to use the classic node migrations
 * instead of the default complete node migrations. The migration system will
 * use the classic node migration only if there are existing migrate_map tables
 * for the classic node migrations and they contain data. These tables may not
 * exist if you are developing custom migrations and do not want to use the
 * complete node migrations. Set this to TRUE to force the use of the classic
 * node migrations.
 */
$settings['migrate_node_migrate_type_classic'] = FALSE;

$settings['vite']['useDevServer'] = false;

$settings['config_exclude_modules'] = [
<<<<<<< Updated upstream
  'devel_generate', 'stage_file_proxy', 
=======
  'devel_generate', 'stage_file_proxy',
>>>>>>> Stashed changes
  'upgrade_status', 'update'
];

$config['environment_indicator.indicator']['bg_color'] = '#a51d2d';
$config['environment_indicator.indicator']['fg_color'] = '#ffffff';
$config['environment_indicator.indicator']['name'] = 'Prod';

$config['locale.settings']['translation']['use_source'] = 'local';
$config['locale.settings']['translation']['path'] = "../storage/translations";

if ($_ENV['APP_ENV'] === 'staging') {
  $config['environment_indicator.indicator']['bg_color'] = '#e66100';
  $config['environment_indicator.indicator']['fg_color'] = '#ffffff';
  $config['environment_indicator.indicator']['name'] = 'Qualif';
}

$config['file.settings']['filename_sanitization'] = [
  'transliterate' => true,
  'replace_whitespace' => true,
  'replace_non_alphanumeric' => true,
  'deduplicate_separators' => true,
  'lowercase' => true,
  'replacement_character' => '-',
];

<<<<<<< Updated upstream
=======
if(!empty($_ENV['DRUSH_OPTIONS_URI'])) {
  $sitemap_base_url = $_ENV['DRUSH_OPTIONS_URI'];
  if(!str_starts_with($sitemap_base_url, 'http')) {
    $sitemap_base_url = "https://{$sitemap_base_url}";
  }
  $config['simple_sitemap.settings']['base_url'] = $sitemap_base_url;
}
>>>>>>> Stashed changes

if ($_ENV['APP_ENV'] === 'dev') {
  include "settings.dev.php";
}

