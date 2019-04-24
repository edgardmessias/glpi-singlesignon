<?php

define('PLUGIN_SINGLESIGNON_VERSION', '1.0.0');

// Init the hooks of the plugins -Needed
function plugin_init_singlesignon() {
   global $PLUGIN_HOOKS, $CFG_GLPI, $CFG_SSO;

   $autoload = __DIR__ . '/vendor/autoload.php';

   if (file_exists($autoload)) {
      include_once $autoload;
   }

   $PLUGIN_HOOKS['csrf_compliant']['singlesignon'] = true;

   $CFG_SSO = Config::getConfigurationValues('singlesignon');

   $PLUGIN_HOOKS['display_login']['singlesignon'] = "plugin_singlesignon_display_login";

   $PLUGIN_HOOKS['menu_toadd']['singlesignon'] = [
      'plugins' => 'PluginSinglesignonProvider',
      'config'  => 'PluginSinglesignonProvider',
   ];
}

// Get the name and the version of the plugin - Needed
function plugin_version_singlesignon() {
   return array(
      'name'           => __sso('Single Sign-on'),
      'version'        => PLUGIN_SINGLESIGNON_VERSION,
      'author'         => 'Edgard Lorraine Messias',
      'homepage'       => 'https://github.com/edgardmessias/glpi-singlesignon',
      'minGlpiVersion' => '0.85'
   );
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_singlesignon_check_prerequisites() {
   $autoload = __DIR__ . '/vendor/autoload.php';

   if (!file_exists($autoload)) {
      echo __sso("Run first: composer install");
      return false;
   }
   if (version_compare(GLPI_VERSION, '0.85', 'lt')) {
      echo __sso("This plugin requires GLPI >= 0.85");
      return false;
   } else {
      return true;
   }
}

function plugin_singlesignon_check_config() {
   return true;
}

function __sso($str) {
   return __($str, 'singlesignon');
}
