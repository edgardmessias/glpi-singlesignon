<?php

use GlpiPlugin\Singlesignon\LoginRenderer;

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

function plugin_singlesignon_display_login()
{
    LoginRenderer::display();
}

function plugin_singlesignon_post_init()
{
    // Don't execute in CLI mode
    if (PHP_SAPI === 'cli' || !isset($_SERVER['REQUEST_URI'])) {
        return;
    }

    // Don't inject on asset requests (JS, CSS, images, etc.)
    $uri = $_SERVER['REQUEST_URI'];
    if (preg_match('/\.(js|css|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot|ico|json|xml)(\?|$)/i', $uri)) {
        return;
    }

    // Don't inject on AJAX or asset directories
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        || strpos($uri, '/ajax/') !== false
        || strpos($uri, '/js/') !== false
        || strpos($uri, '/css/') !== false
        || strpos($uri, '/pics/') !== false
        || strpos($uri, '/files/') !== false) {
        return;
    }

    // If user logged in via SSO, inject script to redirect logout links
    if (isset($_SESSION['glpi_sso_login']) && $_SESSION['glpi_sso_login']) {
        $plugin_logout = Plugin::getWebDir('singlesignon') . '/front/logout.php';
        $plugin_logout_js = json_encode($plugin_logout, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        // Use output buffering to inject script at the end of the page
        // This prevents breaking the document structure and TinyMCE initialization
        ob_start(function ($buffer) use ($plugin_logout_js) {
            $script = '<script type="text/javascript">
(function() {
    "use strict";
    var pluginLogout = ' . $plugin_logout_js . ';
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll("a[href*=\'/front/logout.php\']").forEach(function(link) {
            var href = link.getAttribute("href");
            if (href && href.indexOf("plugins/singlesignon") === -1) {
                link.setAttribute("href", pluginLogout);
            }
        });
    });
})();
</script>';

            // Inject before closing body tag
            $pos = strripos($buffer, '</body>');
            if ($pos !== false) {
                $buffer = substr_replace($buffer, $script . "\n", $pos, 0);
            } else {
                // Fallback: append at the end if no </body> tag found
                $buffer .= $script;
            }

            return $buffer;
        });
    }
}

function plugin_singlesignon_install()
{
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

    $providersTable = 'glpi_plugin_singlesignon_providers';
    $providersUsersTable = 'glpi_plugin_singlesignon_providers_users';

    $migration = new Migration(PLUGIN_SINGLESIGNON_VERSION);

    if (!$DB->tableExists($providersTable)) {
        $DB->doQuery(
            "CREATE TABLE `$providersTable` (
            `id`                         INT NOT NULL AUTO_INCREMENT,
            `is_default`                 TINYINT(1) NOT NULL DEFAULT '0',
            `popup`                      TINYINT(1) NOT NULL DEFAULT '0',
            `split_domain`               TINYINT(1) NOT NULL DEFAULT '0',
            `authorized_domains`         VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            `type`                       VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `name`                       VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `client_id`                  VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `client_secret`              VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
            `scope`                      VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            `extra_options`              VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            `url_authorize`              VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            `url_access_token`           VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            `url_resource_owner_details` VARCHAR(255) COLLATE utf8mb4_unicode_ci NULL,
            `is_active`                  TINYINT(1) NOT NULL DEFAULT '0',
            `use_email_for_login`        TINYINT(1) NOT NULL DEFAULT '0',
            `split_name`                 TINYINT(1) NOT NULL DEFAULT '0',
            `is_deleted`                 TINYINT(1) NOT NULL DEFAULT '0',
            `comment`                    TEXT COLLATE utf8mb4_unicode_ci,
            `date_mod`                   TIMESTAMP NULL DEFAULT NULL,
            `date_creation`              TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `date_mod` (`date_mod`),
            KEY `date_creation` (`date_creation`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } else {
        $migration->addField($providersTable, 'is_default', 'bool');
        $migration->addField($providersTable, 'popup', 'bool');
        $migration->addField($providersTable, 'split_domain', 'bool');
        $migration->addField(
            $providersTable,
            'authorized_domains',
            'string',
            [
                'nodefault' => true,
                'null'      => true,
            ]
        );
        $migration->addField($providersTable, 'use_email_for_login', 'bool');
        $migration->addField($providersTable, 'split_name', 'bool');
    }

    if (version_compare($currentVersion, '1.2.0', '<')) {
        $migration->addField(
            $providersTable,
            'picture',
            'string',
            [
                'nodefault' => true,
                'null'      => true,
            ]
        );
        $migration->addField(
            $providersTable,
            'bgcolor',
            "varchar(7)",
            [
                'nodefault' => true,
                'null'      => true,
            ]
        );
        $migration->addField(
            $providersTable,
            'color',
            "varchar(7)",
            [
                'nodefault' => true,
                'null'      => true,
            ]
        );
    }

    if (!$DB->tableExists($providersUsersTable)) {
        $DB->doQuery(
            "CREATE TABLE `$providersUsersTable` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `plugin_singlesignon_providers_id` INT NOT NULL DEFAULT '0',
            `users_id` INT NOT NULL DEFAULT '0',
            `remote_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity` (`plugin_singlesignon_providers_id`,`users_id`),
            UNIQUE KEY `unicity_remote` (`plugin_singlesignon_providers_id`,`remote_id`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    if (!countElementsInTable('glpi_displaypreferences', ['itemtype' => 'PluginSinglesignonProvider'])) {
        $preferences = [
            ['itemtype' => 'PluginSinglesignonProvider', 'num' => 2, 'rank' => 1, 'users_id' => 0],
            ['itemtype' => 'PluginSinglesignonProvider', 'num' => 3, 'rank' => 2, 'users_id' => 0],
            ['itemtype' => 'PluginSinglesignonProvider', 'num' => 5, 'rank' => 4, 'users_id' => 0],
            ['itemtype' => 'PluginSinglesignonProvider', 'num' => 6, 'rank' => 5, 'users_id' => 0],
            ['itemtype' => 'PluginSinglesignonProvider', 'num' => 10, 'rank' => 6, 'users_id' => 0],
        ];

        foreach ($preferences as $preference) {
            $DB->insert('glpi_displaypreferences', $preference);
        }
    }

    $migration->executeMigration();

    $current['version'] = PLUGIN_SINGLESIGNON_VERSION;
    Config::setConfigurationValues('plugin:singlesignon', $current);

    return true;
}

function plugin_singlesignon_uninstall()
{
    global $DB;

    $config = new Config();
    $config->deleteConfigurationValues('plugin:singlesignon');

    $providersUsersTable = 'glpi_plugin_singlesignon_providers_users';
    $providersTable = 'glpi_plugin_singlesignon_providers';

    if ($DB->tableExists($providersUsersTable)) {
        $DB->dropTable($providersUsersTable, true);
    }

    if ($DB->tableExists($providersTable)) {
        $DB->dropTable($providersTable, true);
    }

    return true;
}
