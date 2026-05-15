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
use GlpiPlugin\Singlesignon\Provider_Group;
use GlpiPlugin\Singlesignon\RuleSinglesignon;
use GlpiPlugin\Singlesignon\RuleSinglesignonCollection;

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
    $providersGroupsTable = Provider_Group::getTable();
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

    if (!$DB->tableExists($providersGroupsTable)) {
        $DB->doQuery(
            "CREATE TABLE `$providersGroupsTable` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `plugin_singlesignon_providers_id` INT NOT NULL DEFAULT '0',
            `groups_id` INT NOT NULL DEFAULT '0',
            `remote_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            UNIQUE KEY `unicity_remote` (`plugin_singlesignon_providers_id`,`remote_id`),
            KEY `groups_id` (`groups_id`)
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
    // user_group_sync_mode and groups_claim were present in versions < 2.3.0 and are
    // dropped in the 2.3.0 migration below.
    // Do NOT re-add them here so that fresh installs never create these columns.
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
    // default_entities_id, match_entity_by_email_domain and default_profiles_id were
    // present in versions < 2.1.0 and are dropped in the 2.1.0 migration below.
    // Do NOT re-add them here so that fresh installs never create these columns.

    $migration->addField($providersTable, 'ssl_verify_host', 'bool', ['value' => 1]);
    $migration->addField($providersTable, 'ssl_verify_peer', 'bool', ['value' => 1]);

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

    /**
     * Version 2.1.0: migrate Registration/group-sync fields to the rules engine
     * and drop the now-redundant DB columns.
     */
    $ruleSubtype = RuleSinglesignon::class;

    if (version_compare($currentVersion, '2.1.0', '<')) {
        // ── Data migration ──────────────────────────────────────────────────
        // Create per-provider rules for any existing provider that had
        // auto_register = 1 so that existing behaviour is preserved.
        if ($DB->tableExists($providersTable) && $DB->fieldExists($providersTable, 'auto_register')) {
            foreach ($DB->request(['FROM' => $providersTable, 'WHERE' => ['auto_register' => 1]]) as $providerRow) {
                // Skip if a rule for this provider was already created in a
                // previous (failed or partial) upgrade attempt.
                $alreadyHasProviderRule = countElementsInTable('glpi_rulecriteria', [
                    'criteria' => 'provider_id',
                    'pattern'  => (int) $providerRow['id'],
                ]) > 0;

                if ($alreadyHasProviderRule) {
                    continue;
                }

                $nextRank = countElementsInTable('glpi_rules', ['sub_type' => $ruleSubtype]) + 1;

                $rule   = new RuleSinglesignon();
                $ruleId = $rule->add([
                    'sub_type'     => $ruleSubtype,
                    'entities_id'  => 0,
                    'is_recursive' => 1,
                    'is_active'    => 1,
                    'name'         => sprintf(__('Auto-migrated rule for provider: %s', 'singlesignon'), strip_tags((string) $providerRow['name'])),
                    'match'        => \Rule::AND_MATCHING,
                    'ranking'      => $nextRank,
                ]);

                if (!is_numeric($ruleId) || (int) $ruleId <= 0) {
                    continue;
                }

                $ruleAction = new \RuleAction();
                $ruleAction->add(['rules_id' => (int) $ruleId, 'action_type' => 'assign', 'field' => 'auto_register',        'value' => 1]);
                $ruleAction->add(['rules_id' => (int) $ruleId, 'action_type' => 'assign', 'field' => 'registration_preview',  'value' => (int) ($providerRow['registration_preview'] ?? 0)]);
                $ruleAction->add(['rules_id' => (int) $ruleId, 'action_type' => 'assign', 'field' => 'entities_id',           'value' => (int) ($providerRow['default_entities_id'] ?? 0)]);
                $ruleAction->add(['rules_id' => (int) $ruleId, 'action_type' => 'assign', 'field' => 'is_recursive',          'value' => 0]);
                if ((int) ($providerRow['default_profiles_id'] ?? 0) > 0) {
                    $ruleAction->add(['rules_id' => (int) $ruleId, 'action_type' => 'assign', 'field' => 'profiles_id', 'value' => (int) $providerRow['default_profiles_id']]);
                }

                $ruleCriteria = new \RuleCriteria();
                $ruleCriteria->add([
                    'rules_id'  => (int) $ruleId,
                    'criteria'  => 'provider_id',
                    'condition' => \Rule::PATTERN_IS,
                    'pattern'   => (int) $providerRow['id'],
                ]);
            }
        }

        // ── Drop migrated columns ────────────────────────────────────────────
        // migration->dropField() is a no-op when the column does not exist, so
        // this is safe for fresh installs where the columns were never created.
        $migration->dropField($providersTable, 'default_entities_id');
        $migration->dropField($providersTable, 'match_entity_by_email_domain');
        $migration->dropField($providersTable, 'default_profiles_id');
        $migration->executeMigration();
    }

    // ── Ensure the global default rule exists ────────────────────────────────
    // Run on every install/upgrade so fresh installs and upgrades both have the
    // conservative catch-all rule visible in the UI.
    $defaultRuleExists = countElementsInTable('glpi_rules', [
        'sub_type' => $ruleSubtype,
        'name'     => __('Default SSO rule', 'singlesignon'),
    ]) > 0;

    if (!$defaultRuleExists) {
        $nextRank = countElementsInTable('glpi_rules', ['sub_type' => $ruleSubtype]) + 1;

        $rule   = new RuleSinglesignon();
        $ruleId = $rule->add([
            'sub_type'     => $ruleSubtype,
            'entities_id'  => 0,
            'is_recursive' => 1,
            'is_active'    => 1,
            'name'         => __('Default SSO rule', 'singlesignon'),
            'description'  => __('No criteria — applies to all SSO logins. Edit to set default entity/profile.', 'singlesignon'),
            'match'        => \Rule::AND_MATCHING,
            'ranking'      => $nextRank,
        ]);

        if (is_numeric($ruleId) && (int) $ruleId > 0) {
            $ruleAction = new \RuleAction();
            $ruleAction->add(['rules_id' => (int) $ruleId, 'action_type' => 'assign', 'field' => 'entities_id',  'value' => 0]);
            $ruleAction->add(['rules_id' => (int) $ruleId, 'action_type' => 'assign', 'field' => 'is_recursive', 'value' => 0]);
        }
    }

    /**
     * Version 2.2.0: rename the 'entities_id' rule action field to '_entities_id_default'
     * to align with GLPI's native RuleRight action naming convention.
     */
    if (version_compare($currentVersion, '2.2.0', '<')) {
        if ($DB->tableExists('glpi_ruleactions')) {
            // Rename entities_id → _entities_id_default for all SSO rules.
            $DB->update(
                'glpi_ruleactions',
                ['field' => '_entities_id_default'],
                [
                    'field'    => 'entities_id',
                    'rules_id' => new \QuerySubQuery([
                        'SELECT' => 'id',
                        'FROM'   => 'glpi_rules',
                        'WHERE'  => ['sub_type' => $ruleSubtype],
                    ]),
                ],
            );
        }
    }

    /**
     * Version 2.3.0:
     *  - Migrate groups_claim field values to Provider_Field 'groups' mappings.
     *  - Drop user_group_sync_mode and groups_claim columns.
     *  - Move default-rule text from description to comment column.
     *  - Delete fullname field mappings (field type removed).
     */
    if (version_compare($currentVersion, '2.3.0', '<')) {
        // Migrate existing groups_claim values to the new 'groups' field mapping.
        if ($DB->tableExists($providersTable) && $DB->fieldExists($providersTable, 'groups_claim')) {
            foreach ($DB->request(['FROM' => $providersTable, 'WHERE' => ['NOT' => ['groups_claim' => ['', null]]]]) as $providerRow) {
                $providerId     = (int) $providerRow['id'];
                $groupsClaimRaw = trim((string) $providerRow['groups_claim']);
                if ($groupsClaimRaw === '') {
                    continue;
                }

                // Convert dot-notation path to a JSONPath expression.
                $jsonPath = str_starts_with($groupsClaimRaw, '$') ? $groupsClaimRaw : '$.' . $groupsClaimRaw;

                // Only create a mapping if none exists yet for this provider+type.
                $existingGroups = countElementsInTable(
                    $providersFieldsTable,
                    ['plugin_singlesignon_providers_id' => $providerId, 'field_type' => 'groups']
                );
                if ($existingGroups === 0) {
                    $maxOrder = 0;
                    foreach ($DB->request([
                        'SELECT' => ['MAX' => 'sort_order AS max_order'],
                        'FROM'   => $providersFieldsTable,
                        'WHERE'  => ['plugin_singlesignon_providers_id' => $providerId],
                    ]) as $row) {
                        $maxOrder = (int) ($row['max_order'] ?? 0);
                    }

                    $DB->insert($providersFieldsTable, [
                        'plugin_singlesignon_providers_id' => $providerId,
                        'field_type'                       => 'groups',
                        'jsonpath'                         => $jsonPath,
                        'is_active'                        => 1,
                        'sort_order'                       => $maxOrder + 10,
                        'date_creation'                    => date('Y-m-d H:i:s'),
                        'date_mod'                         => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        // Remove fullname field mappings (field type no longer supported).
        if ($DB->tableExists($providersFieldsTable)) {
            $DB->delete($providersFieldsTable, ['field_type' => 'fullname']);
        }

        // Move default-rule text from description to comment.
        if ($DB->tableExists('glpi_rules')) {
            $defaultRuleText = __('No criteria — applies to all SSO logins. Edit to enable auto-registration and set default entity/profile.', 'singlesignon');
            $DB->update(
                'glpi_rules',
                ['comment' => $defaultRuleText, 'description' => ''],
                ['sub_type' => $ruleSubtype, 'description' => $defaultRuleText],
            );
        }

        // Drop the columns now that data has been migrated.
        $migration->dropField($providersTable, 'user_group_sync_mode');
        $migration->dropField($providersTable, 'groups_claim');
        $migration->executeMigration();
    }

    /**
     * Version 2.4.0:
     *  - Move default-rule text back from comment to description column.
     *  - Remove auto_register and registration_preview rule actions (these
     *    settings are now provider-level fields, not rule actions).
     */
    if (version_compare($currentVersion, '2.4.0', '<')) {
        if ($DB->tableExists('glpi_rules')) {
            $oldRuleText = __('No criteria — applies to all SSO logins. Edit to enable auto-registration and set default entity/profile.', 'singlesignon');
            $newRuleText = __('No criteria — applies to all SSO logins. Edit to set default entity/profile.', 'singlesignon');
            // Move text back to description for rules still carrying the old comment value.
            $DB->update(
                'glpi_rules',
                ['description' => $newRuleText, 'comment' => ''],
                ['sub_type' => $ruleSubtype, 'comment' => $oldRuleText],
            );
        }
        if ($DB->tableExists('glpi_ruleactions')) {
            $DB->delete(
                'glpi_ruleactions',
                [
                    'field'    => ['auto_register', 'registration_preview'],
                    'rules_id' => new \QuerySubQuery([
                        'SELECT' => 'id',
                        'FROM'   => 'glpi_rules',
                        'WHERE'  => ['sub_type' => $ruleSubtype],
                    ]),
                ],
            );
        }
    }

    $current['version'] = PLUGIN_SINGLESIGNON_VERSION;
    Config::setConfigurationValues('plugin:singlesignon', $current);

    return true;
}

function plugin_singlesignon_uninstall()
{
    global $DB;

    Config::deleteConfigurationValues('plugin:singlesignon');

    $providersUsersTable = 'glpi_plugin_singlesignon_providers_users';
    $providersGroupsTable = Provider_Group::getTable();
    $providersTable = 'glpi_plugin_singlesignon_providers';
    $providersFieldsTable = 'glpi_plugin_singlesignon_providers_fields';

    if ($DB->tableExists($providersUsersTable)) {
        $DB->dropTable($providersUsersTable, true);
    }

    if ($DB->tableExists($providersGroupsTable)) {
        $DB->dropTable($providersGroupsTable, true);
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
