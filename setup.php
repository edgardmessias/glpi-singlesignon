<?php

/**
 * ---------------------------------------------------------------------
 * SingleSignOn is a plugin which allows to use SSO for auth
 * ---------------------------------------------------------------------
 * Copyright (C) 2022 Edgard
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2021 - 2022 Edgard
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/edgardmessias/glpi-singlesignon/
 * ---------------------------------------------------------------------
 */

define('PLUGIN_SINGLESIGNON_VERSION', '1.4.0');

$folder = basename(dirname(__FILE__));

if ($folder !== "singlesignon") {
   $msg = sprintf(__sso("Please, rename the plugin folder \"%s\" to \"singlesignon\""), $folder);
   Session::addMessageAfterRedirect($msg, true, ERROR);
}

// Init the hooks of the plugins -Needed
function plugin_init_singlesignon() {
   global $PLUGIN_HOOKS, $CFG_GLPI, $CFG_SSO;

   $autoload = __DIR__ . '/vendor/autoload.php';

   if (file_exists($autoload)) {
      include_once $autoload;
   }

   Plugin::registerClass('PluginSinglesignonPreference', [
      'addtabon' => ['Preference', 'User']
   ]);

   $PLUGIN_HOOKS['csrf_compliant']['singlesignon'] = true;

   $PLUGIN_HOOKS['config_page']['singlesignon'] = 'front/provider.php';

   $CFG_SSO = Config::getConfigurationValues('singlesignon');

   $PLUGIN_HOOKS['display_login']['singlesignon'] = "plugin_singlesignon_display_login";

   $PLUGIN_HOOKS['menu_toadd']['singlesignon'] = [
      'config'  => 'PluginSinglesignonProvider',
   ];
}

// Get the name and the version of the plugin - Needed
function plugin_version_singlesignon() {
   return [
      'name'           => __sso('Single Sign-on'),
      'version'        => PLUGIN_SINGLESIGNON_VERSION,
      'author'         => 'Edgard Lorraine Messias',
      'homepage'       => 'https://github.com/edgardmessias/glpi-singlesignon',
      'minGlpiVersion' => '0.85'
   ];
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_singlesignon_check_prerequisites() {
   $autoload = __DIR__ . '/vendor/autoload.php';

   // if (!file_exists($autoload)) {
   //    echo __sso("Run first: composer install");
   //    return false;
   // }
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

function sso_TableExists($table) {
   if (function_exists("TableExists")) {
      return TableExists($table);
   }

   global $DB;
   return $DB->TableExists($table);
}

function sso_FieldExists($table, $field, $usecache = true) {
   if (function_exists("FieldExists")) {
      return FieldExists($table);
   }

   global $DB;
   return $DB->FieldExists($table, $field, $usecache);
}
