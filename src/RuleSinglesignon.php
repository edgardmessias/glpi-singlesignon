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

declare(strict_types=1);

namespace GlpiPlugin\Singlesignon;

/**
 * Rule class for the SSO rules engine.
 *
 * Criteria mirror the user attributes available from the IdP token; actions
 * mirror GLPI's native RuleRight actions so that existing GLPI translations
 * are reused and the UI feels familiar to administrators.
 */
class RuleSinglesignon extends \Rule {
    // Use the standard 'config' right so that GLPI administrators can manage
    // these rules through the normal Rules interface.
    public static $rightname = 'config';

    public function getTitle(): string {
        // Reuse the native GLPI translation for "Authorization assignment rules".
        return __('Authorization assignment rules');
    }

    public static function canCreate(): bool {
        return static::canUpdate();
    }

    /**
     * Override to return the correct front-end path for this namespaced plugin
     * class.  GLPI's default implementation uses strtolower(static::class) which
     * produces a URL containing backslashes when the class is in a PHP namespace.
     *
     * Plugin::getWebDir() is called with the default $full=true so that it
     * prepends root_doc and returns an absolute (server-root-relative) path.
     * Passing false would return a relative path such as "plugins/singlesignon"
     * which browsers resolve relative to the current page and double the prefix.
     *
     * @param bool $full When true, appends the itemtype query parameter.
     */
    public static function getFormURL($full = true): string {
        $dir = \Plugin::getWebDir('singlesignon');
        $url = $dir . '/front/rulesinglesignon.form.php';
        return $full ? $url . '?itemtype=' . static::class : $url;
    }

    /**
     * Override to return the correct test URL for this plugin rule class.
     * GLPI's default implementation derives the URL from the class namespace,
     * producing a capitalised path (/plugins/Singlesignon/...) that does not
     * exist.  Here we explicitly use the lowercase plugin slug.
     *
     * @param bool $full Unused – kept for signature compatibility.
     */
    public static function getTestURL($full = true): string {
        $dir = \Plugin::getWebDir('singlesignon');
        return $dir . '/front/rule.test.php';
    }

    /**
     * Inform GLPI that this rule class has default rules, enabling the native "Reset rules" button.
     */
    public function hasDefaultRules() {
        return true;
    }

    /**
     * Reinitialize rules to their default state.
     * This is called by GLPI when the native "Reset rules" button is clicked.
     */
    public static function initRules() {
        $rule = new self();
        // Delete all existing SSO rules (purge = true removes related criteria and actions).
        foreach ($rule->find(['sub_type' => static::class]) as $id => $ruleData) {
            $rule->delete(['id' => $id], true);
        }

        // Re-create the default catch-all rule using the installation hook.
        if (!function_exists('plugin_singlesignon_install')) {
            include_once \Plugin::getPhpDir('singlesignon') . '/hook.php';
        }

        plugin_singlesignon_install();

        return true;
    }

    public function getCriterias(): array {
        return [
            // ── Identity ────────────────────────────────────────────────────
            'login' => [
                'name'    => __('Login'),
                'type'    => 'text',
                'table'   => '',
                'field'   => 'login',
                'virtual' => true,
            ],
            'email' => [
                'name'    => __('Email'),
                'type'    => 'text',
                'table'   => '',
                'field'   => 'email',
                'virtual' => true,
            ],
            'location' => [
                'name'    => __('Location'),
                'type'    => 'text',
                'table'   => '',
                'field'   => 'location',
                'virtual' => true,
            ],
            'supervisor' => [
                'name'    => __('Supervisor'),
                'type'    => 'text',
                'table'   => '',
                'field'   => 'supervisor',
                'virtual' => true,
            ],
            // ── IdP claim values ────────────────────────────────────────────
            'SSO_GROUPS' => [
                'name'    => __('Group'),
                'type'    => 'text',
                'table'   => '',
                'field'   => 'SSO_GROUPS',
                'virtual' => true,
            ],
            // ── Context ─────────────────────────────────────────────────────
            'provider_id' => [
                'name'  => __('SSO Provider', 'singlesignon'),
                'type'  => 'dropdown',
                'table' => 'glpi_plugin_singlesignon_providers',
                'field' => 'name',
            ],
        ];
    }

    public function getActions(): array {
        return [
            // ── Flow control ─────────────────────────────────────────────────
            '_stop_rules_processing' => [
                'name'          => __('Skip remaining rules'),
                'type'          => 'yesonly',
                'force_actions' => ['assign'],
            ],
            // ── Profile / entity / recursive ─────────────────────────────────
            'profiles_id' => [
                'name'          => __('Profiles'),
                'type'          => 'dropdown',
                'table'         => 'glpi_profiles',
                'field'         => 'name',
                'force_actions' => ['assign'],
            ],
            'is_recursive' => [
                'name'          => __('Recursive'),
                'type'          => 'yesno',
                'force_actions' => ['assign'],
            ],
            'is_active' => [
                'name'          => __('Active'),
                'type'          => 'yesno',
                'force_actions' => ['assign'],
            ],
            '_ignore_user_import' => [
                'name'          => __('Ignore import'),
                'type'          => 'yesonly',
                'force_actions' => ['assign'],
            ],
            '_entities_id_default' => [
                'name'          => __('Default entity'),
                'type'          => 'dropdown',
                'table'         => 'glpi_entities',
                'field'         => 'completename',
                'force_actions' => ['assign'],
            ],
            'specific_groups_id' => [
                'name'          => __('Groups'),
                'type'          => 'dropdown',
                'table'         => 'glpi_groups',
                'field'         => 'completename',
                'force_actions' => ['assign'],
            ],
            'groups_id' => [
                'name'          => __('Default group'),
                'type'          => 'dropdown',
                'table'         => 'glpi_groups',
                'field'         => 'completename',
                'force_actions' => ['assign'],
            ],
            '_profiles_id_default' => [
                'name'          => __('Default profile'),
                'type'          => 'dropdown',
                'table'         => 'glpi_profiles',
                'field'         => 'name',
                'force_actions' => ['assign'],
            ],
            'timezone' => [
                'name'          => __('Time zone'),
                'type'          => 'timezone',
                'force_actions' => ['assign'],
            ],
            'language' => [
                'name'          => __('Language'),
                'type'          => 'language',
                'force_actions' => ['assign'],
            ],
            '_deny_login' => [
                'name'          => __('Deny login'),
                'type'          => 'yesonly',
                'force_actions' => ['assign'],
            ],
        ];
    }
}
