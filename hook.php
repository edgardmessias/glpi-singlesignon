<?php

/**
 * ---------------------------------------------------------------------
 * SingleSignOn is a plugin which allows to use SSO for auth
 * ---------------------------------------------------------------------
 * Copyright (C) 2026 Edgard
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2021 - 2026 Edgard
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/edgardmessias/glpi-singlesignon/
 * ---------------------------------------------------------------------
 */

use GlpiPlugin\Singlesignon\LoginRenderer;
use GlpiPlugin\Singlesignon\Provider;

function plugin_singlesignon_install()
{
    /* @var $DB DB */
    global $DB;

    $currentVersion = '0.0.0';

    $current = Config::getConfigurationValues('plugin:singlesignon');

    if (isset($current['version'])) {
        $currentVersion = $current['version'];
    }

    Config::setConfigurationValues('plugin:singlesignon', $current);

    $providersTable = 'glpi_plugin_singlesignon_providers';
    $providersUsersTable = 'glpi_plugin_singlesignon_providers_users';
    $providersFieldsTable = 'glpi_plugin_singlesignon_providers_fields';

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
            `user_photo_sync_mode`       INT NOT NULL DEFAULT '0',
            `resource_owner_auth_type`   VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bearer',
            `resource_owner_custom_headers` TEXT COLLATE utf8mb4_unicode_ci NULL,
            `resource_owner_picture_auth_type` VARCHAR(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bearer',
            `resource_owner_picture_custom_headers` TEXT COLLATE utf8mb4_unicode_ci NULL,
            `ssl_verify_host`            TINYINT(1) NOT NULL DEFAULT '1',
            `ssl_verify_peer`            TINYINT(1) NOT NULL DEFAULT '1',
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
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        );
    }

    /**
     * Version 1.1.0
     */
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
        ],
    );
    $migration->addField($providersTable, 'use_email_for_login', 'bool');
    $migration->addField($providersTable, 'split_name', 'bool');

    /**
     * Version 1.2.0
     */
    $migration->addField(
        $providersTable,
        'picture',
        'string',
        [
            'nodefault' => true,
            'null'      => true,
        ],
    );
    $migration->addField(
        $providersTable,
        'bgcolor',
        "varchar(7)",
        [
            'nodefault' => true,
            'null'      => true,
        ],
    );
    $migration->addField(
        $providersTable,
        'color',
        "varchar(7)",
        [
            'nodefault' => true,
            'null'      => true,
        ],
    );

    /**
     * Version 1.3.0
     */
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
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        );
    }

    /**
     * Version 2.0.0
     */
    if (!$DB->tableExists($providersFieldsTable)) {
        $DB->doQuery(
            "CREATE TABLE `$providersFieldsTable` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `plugin_singlesignon_providers_id` INT NOT NULL DEFAULT '0',
            `field_type` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
            `jsonpath` VARCHAR(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT '1',
            `sort_order` INT NOT NULL DEFAULT '0',
            `date_mod` TIMESTAMP NULL DEFAULT NULL,
            `date_creation` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `provider_active_order` (`plugin_singlesignon_providers_id`, `is_active`, `sort_order`),
            KEY `provider_type` (`plugin_singlesignon_providers_id`, `field_type`)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        );
    }

    $migration->addField(
        $providersTable,
        'user_photo_sync_mode',
        'integer',
        [
            'value' => 0,
            'after' => 'url_resource_owner_details',
        ],
    );
    $migration->addField(
        $providersTable,
        'resource_owner_auth_type',
        'string',
        [
            'value' => 'bearer',
            'after' => 'user_photo_sync_mode',
        ],
    );
    $migration->addField(
        $providersTable,
        'resource_owner_custom_headers',
        'text',
        [
            'nodefault' => true,
            'null'      => true,
            'after'     => 'resource_owner_auth_type',
        ],
    );
    $migration->addField(
        $providersTable,
        'resource_owner_picture_auth_type',
        'string',
        [
            'value' => 'bearer',
            'after' => 'resource_owner_custom_headers',
        ],
    );
    $migration->addField(
        $providersTable,
        'resource_owner_picture_custom_headers',
        'text',
        [
            'nodefault' => true,
            'null'      => true,
            'after'     => 'resource_owner_picture_auth_type',
        ],
    );

    $migration->addField($providersTable, 'auto_register', 'bool', ['value' => 0]);
    $migration->addField($providersTable, 'registration_preview', 'bool', ['value' => 0]);
    $migration->addField(
        $providersTable,
        'default_entities_id',
        'integer',
        [
            'value' => 0,
        ],
    );
    $migration->addField($providersTable, 'match_entity_by_email_domain', 'bool', ['value' => 0]);
    $migration->addField(
        $providersTable,
        'default_profiles_id',
        'integer',
        [
            'value' => 0,
        ],
    );

    $migration->addField($providersTable, 'ssl_verify_host', 'bool', ['value' => 1]);
    $migration->addField($providersTable, 'ssl_verify_peer', 'bool', ['value' => 1]);

    /**
     * Add display preferences
     */
    if (!countElementsInTable('glpi_displaypreferences', ['itemtype' => Provider::class])) {
        $preferences = [
            ['itemtype' => Provider::class, 'num' => 2, 'rank' => 1, 'users_id' => 0],
            ['itemtype' => Provider::class, 'num' => 3, 'rank' => 2, 'users_id' => 0],
            ['itemtype' => Provider::class, 'num' => 5, 'rank' => 4, 'users_id' => 0],
            ['itemtype' => Provider::class, 'num' => 6, 'rank' => 5, 'users_id' => 0],
            ['itemtype' => Provider::class, 'num' => 10, 'rank' => 6, 'users_id' => 0],
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

    Config::deleteConfigurationValues('plugin:singlesignon');

    $providersUsersTable = 'glpi_plugin_singlesignon_providers_users';
    $providersTable = 'glpi_plugin_singlesignon_providers';
    $providersFieldsTable = 'glpi_plugin_singlesignon_providers_fields';

    if ($DB->tableExists($providersUsersTable)) {
        $DB->dropTable($providersUsersTable, true);
    }

    if ($DB->tableExists($providersFieldsTable)) {
        $DB->dropTable($providersFieldsTable, true);
    }

    if ($DB->tableExists($providersTable)) {
        $DB->dropTable($providersTable, true);
    }

    // Ensure the Twig cache is cleared when the plugin is uninstalled to avoid errors.
    LoginRenderer::clearTwigTemplateCache();

    return true;
}

/**
 * Ensure the Twig cache is cleared when the plugin is activated to avoid errors.
 */
function plugin_singlesignon_activate(): void
{
    LoginRenderer::clearTwigTemplateCache();
}

/**
 * Ensure the Twig cache is cleared when the plugin is deactivated to avoid errors.
 */
function plugin_singlesignon_deactivate(): void
{
    LoginRenderer::clearTwigTemplateCache();
}
