<?php

use Drush\Drupal\DrupalKernel;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Acquia envs.
 *
 * Note that the values of environmental variables are set differently on Acquia
 * Cloud Free tier vs Acquia Cloud Professional and Enterprise.
 */
$repo_root = dirname(DRUPAL_ROOT);
$ah_env = isset($_ENV['AH_SITE_ENVIRONMENT']) ? $_ENV['AH_SITE_ENVIRONMENT'] : NULL;
$ah_group = isset($_ENV['AH_SITE_GROUP']) ? $_ENV['AH_SITE_GROUP'] : NULL;
$ah_site = isset($_ENV['AH_SITE_NAME']) ? $_ENV['AH_SITE_NAME'] : NULL;
$is_ah_env = (bool) $ah_env;
// ACE prod is 'prod'; ACSF can be '01prod', '02prod', ...
$is_ah_prod_env = $ah_env == 'prod' || preg_match('/^\d*live$/', $ah_env);
// ACE staging is 'test' or 'stg'; ACSF is '01test', '02test', ...
$is_ah_stage_env = preg_match('/^\d*test$/', $ah_env) || $ah_env == 'stg';
$is_ah_dev_cloud = (!empty($_SERVER['HTTP_HOST']) && strstr($_SERVER['HTTP_HOST'], 'devcloud'));
// ACE dev is 'dev', 'dev1', ...; ACSF dev is '01dev', '02dev', ...
$is_ah_dev_env = (preg_match('/^\d*dev\d*$/', $ah_env));
// CDEs (formerly 'ODEs') can be 'ode1', 'ode2', ...
$is_ah_ode_env = (preg_match('/^ode\d*$/', $ah_env));
$is_acsf_env = (!empty($ah_group) && file_exists("/mnt/files/$ah_group.$ah_env/files-private/sites.json"));
// @todo Maybe check for acsf-tools.
$is_acsf_inited = file_exists(DRUPAL_ROOT . "/sites/g");
$acsf_db_name = $is_acsf_env ? $GLOBALS['gardens_site_settings']['conf']['acsf_db_name'] : NULL;

/**
 * Local envs.
 */
$is_local_env = !$is_ah_env;

/**
 * Common variables.
 */
$is_dev_env = $is_ah_dev_env;
$is_stage_env = $is_ah_stage_env;
$is_prod_env = $is_ah_prod_env;

/**
 * Site directory detection.
 */
if (!isset($site_path)) {
  try {
    $site_path = DrupalKernel::findSitePath(Request::createFromGlobals());
  }
  catch (BadRequestHttpException $e) {
    $site_path = 'sites/default';
  }
}
$site_dir = str_replace('sites/', '', $site_path);

/*******************************************************************************
 * Acquia Cloud settings.
 *
 * These includes are intentionally loaded before all others because we do not
 * have control over their contents. By loading all other includes after this,
 * we have the opportunity to override any configuration values provided by the
 * hosted files. This is not necessary for files that we control.
 ******************************************************************************/
if ($is_ah_env) {
  // Tempoary fix for CL-21595.
  $_SERVER['PWD'] = DRUPAL_ROOT;
  $group_settings_file = "/var/www/site-php/$ah_group/$ah_group-settings.inc";
  $site_settings_file = "/var/www/site-php/$ah_group/$site_dir-settings.inc";
  if (!$is_acsf_env && file_exists($group_settings_file)) {
    if ($site_dir == 'default') {
      require $group_settings_file;
    }
    // Includes multisite settings for given site.
    elseif (file_exists($site_settings_file)) {
      require $site_settings_file;
    }
  }
  // Store API Keys and things outside of version control.
  // @see settings/sample-secrets.settings.php for sample code.
  $secrets_file = "/mnt/gfs/home/$ah_group/secrets.settings.php";
  if (file_exists($secrets_file)) {
    require $secrets_file;
  }
  // Includes secrets file for given site.
  $site_secrets_file = "/mnt/gfs/home/$ah_group/$site_dir/secrets.settings.php";
  if (file_exists($site_secrets_file)) {
    require $site_secrets_file;
  }
}
