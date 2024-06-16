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
   global $CFG_GLPI;

   $signon_provider = new PluginSinglesignonProvider();

   $condition = '`is_active` = 1';
   if (version_compare(GLPI_VERSION, '9.4', '>=')) {
      $condition = [$condition];
   }
   $rows = $signon_provider->find($condition);

   $html = [];

   foreach ($rows as $row) {
      $query = [];

      if (isset($_REQUEST['redirect'])) {
         $query['redirect'] = $_REQUEST['redirect'];
      }

      $url = PluginSinglesignonToolbox::getCallbackUrl($row['id'], $query);
      $isDefault = PluginSinglesignonToolbox::isDefault($row);
      if ($isDefault && !isset($_GET["noAUTO"])) {
         Html::redirect($url);
         return;
      }
      $html[] = PluginSinglesignonToolbox::renderButton($url, $row);
   }

   if (!empty($html)) {
      echo '<div class="singlesignon-box">';
      echo implode(" \n", $html);
      echo PluginSinglesignonToolbox::renderButton('#', ['name' => __('GLPI')], 'vsubmit old-login');
      echo '</div>';
      ?>
      <style>
         #display-login .singlesignon-box span {
            display: inline-block;
            margin: 5px;
         }

         #display-login .singlesignon-box .old-login {
            display: none;
         }

         #boxlogin .singlesignon-box span {
            display: block;
         }

         #boxlogin .singlesignon-box .vsubmit {
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.3em !important;
            text-align: center;
            box-sizing: border-box;
         }

         #boxlogin .singlesignon-box .vsubmit img {

            vertical-align: sub;
         }
      </style>
      <script type="text/javascript">
         $(document).ready(function() {

            // On click, open a popup
            $(document).on("click", ".singlesignon.oauth-login.popup", function(e) {
               e.preventDefault();

               var url = $(this).attr("href");
               var left = ($(window).width() / 2) - (600 / 2);
               var top = ($(window).height() / 2) - (800 / 2);
               var newWindow = window.open(url, "singlesignon", "width=600,height=800,left=" + left + ",top=" + top);
               if (window.focus) {
                  newWindow.focus();
               }
            });

      <?php if (version_compare(GLPI_VERSION, '10', '>=')) : ?>
               var $boxButtons = $('.singlesignon-box');

               $boxButtons.parent().hide();

               var $line = $boxButtons.prev('hr');
               if ($line.length) {
                  $line.remove();
               }

               var $row = $boxButtons.closest('.row');
               var $boxLogin = $row.find('div:eq(0)');

               $boxButtons.addClass('col-md-5 text-center');
               $boxButtons.prependTo($row);

               $boxButtons.find('span').addClass("row mb-2");
               $boxButtons.find('span a').addClass("col-md-12");

               $boxLogin.hide();

               $(document).on("click", ".singlesignon.old-login", function(e) {
                  e.preventDefault();
                  $boxButtons.slideUp(function() {
                     $boxLogin.slideDown(function() {
                        $boxLogin.find(':input:eq(0)').focus();
                     });
                  });
               });

               var $backLogin = $('<label />', {
                  css: {
                     cursor: 'pointer'
                  },
                  text: "<< " + <?php echo json_encode(__('Back')) ?>,
               }).prependTo($boxLogin);

               $backLogin.on('click', function(e) {
                  e.preventDefault();
                  $boxLogin.slideUp(function() {
                     $boxButtons.slideDown();
                  });
               });

            <?php else : ?>
               var $boxLogin = $('#boxlogin');

               var $form = $boxLogin.find('form');
               var $boxButtons = $('.singlesignon-box');

               // Move the buttons to before form
               $boxButtons.prependTo($boxLogin);
               $boxButtons.find('span').addClass('login_input');

               // Show old form
               $(document).on("click", ".singlesignon.old-login", function(e) {
                  e.preventDefault();
                  $boxButtons.slideUp();
                  $form.slideDown(function() {
                     $('#login_name').focus();
                  });
               });

               var $line = $('<p />', {
                  class: 'login_input'
               }).prependTo($form);

               var $backLogin = $('<label />', {
                  css: {
                     cursor: 'pointer'
                  },
                  text: "<< " + <?php echo json_encode(__('Back')) ?>,
               }).appendTo($line);

               $backLogin.on('click', function(e) {
                  e.preventDefault();
                  $boxButtons.slideDown();
                  $form.slideUp();
               });

               $form.hide();
            <?php endif; ?>
         });
      </script>
      <?php
   }
}

function plugin_singlesignon_install() {
   /* @var $DB DB */
   global $DB;

   $currentVersion = '0.0.0';

   $default = [];

   $current = Config::getConfigurationValues('singlesignon');

   if (isset($current['version'])) {
      $currentVersion = $current['version'];
   }

   foreach ($default as $key => $value) {
      if (!isset($current[$key])) {
         $current[$key] = $value;
      }
   }

   Config::setConfigurationValues('singlesignon', $current);

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

      $DB->query($query) or die("error creating glpi_plugin_singlesignon_providers " . $DB->error());
   } else {
      $query = "SHOW COLUMNS FROM glpi_plugin_singlesignon_providers LIKE 'is_default'";
      $result = $DB->query($query) or die($DB->error());
      if ($DB->numrows($result) != 1) {
         $DB->query("ALTER TABLE glpi_plugin_singlesignon_providers ADD is_default tinyint(1) NOT NULL DEFAULT '0'") or die($DB->error());
      }

      $query = "SHOW COLUMNS FROM glpi_plugin_singlesignon_providers LIKE 'popup'";
      $result = $DB->query($query) or die($DB->error());
      if ($DB->numrows($result) != 1) {
         $DB->query("ALTER TABLE glpi_plugin_singlesignon_providers ADD popup tinyint(1) NOT NULL DEFAULT '0'") or die($DB->error());
      }
      $query = "SHOW COLUMNS FROM glpi_plugin_singlesignon_providers LIKE 'split_domain'";
      $result = $DB->query($query) or die($DB->error());
      if ($DB->numrows($result) != 1) {
         $DB->query("ALTER TABLE glpi_plugin_singlesignon_providers ADD split_domain tinyint(1) NOT NULL DEFAULT '0'") or die($DB->error());
      }
      $query = "SHOW COLUMNS FROM glpi_plugin_singlesignon_providers LIKE 'authorized_domains'";
      $result = $DB->query($query) or die($DB->error());
      if ($DB->numrows($result) != 1) {
         $DB->query("ALTER TABLE glpi_plugin_singlesignon_providers ADD authorized_domains varchar(255) COLLATE utf8_unicode_ci NULL") or die($DB->error());
      }
      $query = "SHOW COLUMNS FROM glpi_plugin_singlesignon_providers LIKE 'use_email_for_login'";
      $result = $DB->query($query) or die($DB->error());
      if ($DB->numrows($result) != 1) {
         $DB->query("ALTER TABLE glpi_plugin_singlesignon_providers ADD use_email_for_login tinyint(1) NOT NULL DEFAULT '0'") or die($DB->error());
      }
      $query = "SHOW COLUMNS FROM glpi_plugin_singlesignon_providers LIKE 'split_name'";
      $result = $DB->query($query) or die($DB->error());
      if ($DB->numrows($result) != 1) {
         $DB->query("ALTER TABLE glpi_plugin_singlesignon_providers ADD split_name tinyint(1) NOT NULL DEFAULT '0'") or die($DB->error());
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
      $DB->query($query) or die("error adding picture column " . $DB->error());
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
      $DB->query($query) or die("error creating glpi_plugin_singlesignon_providers_users " . $DB->error());
   }

   Config::setConfigurationValues('singlesignon', [
      'version' => PLUGIN_SINGLESIGNON_VERSION,
   ]);
   return true;
}

function plugin_singlesignon_uninstall() {
   global $DB;

   $config = new Config();
   $condition = "`context` LIKE 'singlesignon%'";
   if (version_compare(GLPI_VERSION, '9.4', '>=')) {
      $condition = [$condition];
   }
   $rows = $config->find($condition);

   foreach ($rows as $id => $row) {
      $config->delete(['id' => $id]);
   }

   // Old version tables
   if (sso_TableExists("glpi_plugin_singlesignon_providers")) {
      $query = "DROP TABLE `glpi_plugin_singlesignon_providers`";
      $DB->query($query) or die("error deleting glpi_plugin_singlesignon_providers");
   }

   return true;
}
