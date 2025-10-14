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

function plugin_singlesignon_display_login() {
   \Glpi\Plugin\Singlesignon\LoginRenderer::display();
}

function plugin_singlesignon_install() {
   /* @var $DB DB */
   global $DB;

   $currentVersion = '0.0.0';

   $default = [];

   $current = Config::getConfigurationValues('plugin:singlesignon');

   if (isset($current['version'])) {
      $currentVersion = $current['version'];
   }

   foreach ($default as $key => $value) {
      if (!isset($current[$key])) {
         $current[$key] = $value;
      }
   }

   Config::setConfigurationValues('plugin:singlesignon', $current);

   if (!sso_TableExists("glpi_plugin_singlesignon_providers")) {
      $query = "CREATE TABLE `glpi_plugin_singlesignon_providers` (
                  `id`                         int(11) NOT NULL auto_increment,
                  `is_default`                 tinyint(1) NOT NULL DEFAULT '0',
                  `popup`                      tinyint(1) NOT NULL DEFAULT '0',
                  `split_domain`               tinyint(1) NOT NULL DEFAULT '0',
                  `authorized_domains`         varchar(255) COLLATE utf8_unicode_ci NULL,
                  `type`                       varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                  `name`                       varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                  `client_id`                  varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                  `client_secret`              varchar(255) COLLATE utf8_unicode_ci NOT NULL,
                  `scope`                      varchar(255) COLLATE utf8_unicode_ci NULL,
                  `extra_options`              varchar(255) COLLATE utf8_unicode_ci NULL,
                  `url_authorize`              varchar(255) COLLATE utf8_unicode_ci NULL,
                  `url_access_token`           varchar(255) COLLATE utf8_unicode_ci NULL,
                  `url_resource_owner_details` varchar(255) COLLATE utf8_unicode_ci NULL,
                  `is_active`                  tinyint(1) NOT NULL DEFAULT '0',
                  `use_email_for_login`        tinyint(1) NOT NULL DEFAULT '0',
                  `split_name`                 tinyint(1) NOT NULL DEFAULT '0',
                  `is_deleted`                 tinyint(1) NOT NULL default '0',
                  `comment`                    text COLLATE utf8_unicode_ci,
                  `date_mod`                   timestamp NULL DEFAULT NULL,
                  `date_creation`              timestamp NULL DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `date_mod` (`date_mod`),
                  KEY `date_creation` (`date_creation`)
               ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

      if (!$DB->query($query)) {
         throw new \RuntimeException('error creating glpi_plugin_singlesignon_providers ' . $DB->error());
      }
   } else {
      $query = "SHOW COLUMNS FROM glpi_plugin_singlesignon_providers LIKE 'is_default'";
      $result = $DB->query($query);
      if ($result === false) {
         throw new \RuntimeException($DB->error());
      }
      if ($DB->numrows($result) != 1) {
         if (!$DB->query("ALTER TABLE glpi_plugin_singlesignon_providers ADD is_default tinyint(1) NOT NULL DEFAULT '0'")) {

            throw new \RuntimeException($DB->error());

         }

      }

      $query = "SHOW COLUMNS FROM glpi_plugin_singlesignon_providers LIKE 'popup'";
      $result = $DB->query($query);
      if ($result === false) {
         throw new \RuntimeException($DB->error());
      }
      if ($DB->numrows($result) != 1) {
         if (!$DB->query("ALTER TABLE glpi_plugin_singlesignon_providers ADD popup tinyint(1) NOT NULL DEFAULT '0'")) {

            throw new \RuntimeException($DB->error());

         }

      }
      $query = "SHOW COLUMNS FROM glpi_plugin_singlesignon_providers LIKE 'split_domain'";
      $result = $DB->query($query);
      if ($result === false) {
         throw new \RuntimeException($DB->error());
      }
      if ($DB->numrows($result) != 1) {
         if (!$DB->query("ALTER TABLE glpi_plugin_singlesignon_providers ADD split_domain tinyint(1) NOT NULL DEFAULT '0'")) {

            throw new \RuntimeException($DB->error());

         }

      }
      $query = "SHOW COLUMNS FROM glpi_plugin_singlesignon_providers LIKE 'authorized_domains'";
      $result = $DB->query($query);
      if ($result === false) {
         throw new \RuntimeException($DB->error());
      }
      if ($DB->numrows($result) != 1) {
         if (!$DB->query("ALTER TABLE glpi_plugin_singlesignon_providers ADD authorized_domains varchar(255) COLLATE utf8_unicode_ci NULL")) {

            throw new \RuntimeException($DB->error());

         }

      }
      $query = "SHOW COLUMNS FROM glpi_plugin_singlesignon_providers LIKE 'use_email_for_login'";
      $result = $DB->query($query);
      if ($result === false) {
         throw new \RuntimeException($DB->error());
      }
      if ($DB->numrows($result) != 1) {
         if (!$DB->query("ALTER TABLE glpi_plugin_singlesignon_providers ADD use_email_for_login tinyint(1) NOT NULL DEFAULT '0'")) {

            throw new \RuntimeException($DB->error());

         }

      }
      $query = "SHOW COLUMNS FROM glpi_plugin_singlesignon_providers LIKE 'split_name'";
      $result = $DB->query($query);
      if ($result === false) {
         throw new \RuntimeException($DB->error());
      }
      if ($DB->numrows($result) != 1) {
         if (!$DB->query("ALTER TABLE glpi_plugin_singlesignon_providers ADD split_name tinyint(1) NOT NULL DEFAULT '0'")) {

            throw new \RuntimeException($DB->error());

         }

      }
   }

   // add display preferences
   $query_display_pref = "SELECT id
      FROM glpi_displaypreferences
      WHERE itemtype = 'PluginSinglesignonProvider'";
   $res_display_pref = $DB->query($query_display_pref);
   if ($DB->numrows($res_display_pref) == 0) {
      $DB->query("INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginSinglesignonProvider','2','1','0');");
      $DB->query("INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginSinglesignonProvider','3','2','0');");
      $DB->query("INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginSinglesignonProvider','5','4','0');");
      $DB->query("INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginSinglesignonProvider','6','5','0');");
      $DB->query("INSERT INTO `glpi_displaypreferences` VALUES (NULL,'PluginSinglesignonProvider','10','6','0');");
   }

   if (!sso_TableExists("glpi_plugin_singlesignon_providers_users") && version_compare($currentVersion, "1.2.0", '<')) {
      $query = "ALTER TABLE `glpi_plugin_singlesignon_providers`
                ADD `picture` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
                ADD `bgcolor` varchar(7) DEFAULT NULL,
                ADD `color` varchar(7) DEFAULT NULL";
      if (!$DB->query($query)) {
         throw new \RuntimeException('error adding picture column ' . $DB->error());
      }
   }
   if (!sso_TableExists("glpi_plugin_singlesignon_providers_users") && version_compare($currentVersion, "1.3.0", '<')) {
      $query = "CREATE TABLE `glpi_plugin_singlesignon_providers_users` (
         `id` int(11) NOT NULL AUTO_INCREMENT,
         `plugin_singlesignon_providers_id` int(11) NOT NULL DEFAULT '0',
         `users_id` int(11) NOT NULL DEFAULT '0',
         `remote_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
         PRIMARY KEY (`id`),
         UNIQUE KEY `unicity` (`plugin_singlesignon_providers_id`,`users_id`),
         UNIQUE KEY `unicity_remote` (`plugin_singlesignon_providers_id`,`remote_id`)
       ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
      if (!$DB->query($query)) {
         throw new \RuntimeException('error creating glpi_plugin_singlesignon_providers_users ' . $DB->error());
      }
   }

   Config::setConfigurationValues('plugin:singlesignon', [
      'version' => PLUGIN_SINGLESIGNON_VERSION,
   ]);
   return true;
}

function plugin_singlesignon_uninstall() {
   global $DB;

   $config = new Config();
   $config->deleteConfigurationValues('plugin:singlesignon');

   // Old version tables
   if (sso_TableExists("glpi_plugin_singlesignon_providers")) {
      $query = "DROP TABLE `glpi_plugin_singlesignon_providers`";
      if (!$DB->query($query)) {
         throw new \RuntimeException('error deleting glpi_plugin_singlesignon_providers');
      }
   }

   return true;
}
