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
 * Each rule can match on IdP claim values (login, email, groups, location, …)
 * and produce actions that drive user registration, entity/profile assignment,
 * and group membership.  This replaces the old per-provider "Registration"
 * fields and the "Entity / Recursive for new groups" settings.
 */
class RuleSinglesignon extends \Rule
{
    // Use the standard 'config' right so that GLPI administrators can manage
    // these rules through the normal Rules interface.
    public static $rightname = 'config';

    public function getTitle(): string
    {
        return __('SSO rules', 'singlesignon');
    }

    /**
     * Override to return the correct front-end path for this namespaced plugin
     * class.  GLPI's default implementation uses strtolower(static::class) which
     * produces a URL containing backslashes when the class is in a PHP namespace.
     *
     * @param bool $full When true, appends the itemtype query parameter.
     */
    public static function getFormURL($full = true): string
    {
        $dir = \Plugin::getWebDir('singlesignon', false);
        $url = $dir . '/front/rulesinglesignon.form.php';
        return $full ? $url . '?itemtype=' . static::class : $url;
    }

    public function getCriterias(): array
    {
        return [
            // ── Identity ────────────────────────────────────────────────────
            'login' => [
                'name' => __('Login'),
                'type' => 'text',
            ],
            'email' => [
                'name' => __('Email'),
                'type' => 'text',
            ],
            'email_domain' => [
                'name'    => __('Email domain', 'singlesignon'),
                'type'    => 'text',
                // virtual = true: value is injected by prepareInputDataForProcess()
                // and the rule engine does not attempt a DB lookup.
                'virtual' => true,
            ],
            'firstname' => [
                'name' => __('First name'),
                'type' => 'text',
            ],
            'realname' => [
                'name' => __('Last name'),
                'type' => 'text',
            ],
            // ── IdP claim values ────────────────────────────────────────────
            'SSO_GROUPS' => [
                'name'    => __('SSO Group (IdP claim)', 'singlesignon'),
                'type'    => 'text',
                'virtual' => true,
            ],
            'officeLocation' => [
                'name'    => __('Office location (IdP claim)', 'singlesignon'),
                'type'    => 'text',
                'virtual' => true,
            ],
            // ── Context ─────────────────────────────────────────────────────
            'is_new_user' => [
                'name'    => __('Is new user (first registration)', 'singlesignon'),
                'type'    => 'yesonly',
                'virtual' => true,
            ],
            'provider_id' => [
                'name'  => __('SSO Provider', 'singlesignon'),
                'type'  => 'dropdown',
                'table' => 'glpi_plugin_singlesignon_providers',
                'field' => 'name',
            ],
        ];
    }

    public function getActions(): array
    {
        return [
            // ── Registration gate ────────────────────────────────────────────
            'auto_register' => [
                'name'          => __('Allow automatic registration', 'singlesignon'),
                'type'          => 'yesno',
                'force_actions' => ['assign'],
            ],
            'registration_preview' => [
                'name'          => __('Confirm registration before creating account', 'singlesignon'),
                'type'          => 'yesno',
                'force_actions' => ['assign'],
            ],
            // ── Assignment ──────────────────────────────────────────────────
            'entities_id' => [
                'name'          => __('Default entity for new users and groups', 'singlesignon'),
                'type'          => 'dropdown',
                'table'         => 'glpi_entities',
                'field'         => 'completename',
                'force_actions' => ['assign'],
            ],
            'is_recursive' => [
                'name'          => __('Recursive for new users and groups', 'singlesignon'),
                'type'          => 'yesno',
                'force_actions' => ['assign'],
            ],
            'profiles_id' => [
                'name'          => __('Default profile when GLPI has no default', 'singlesignon'),
                'type'          => 'dropdown',
                'table'         => 'glpi_profiles',
                'field'         => 'name',
                'force_actions' => ['assign'],
            ],
            'groups_id' => [
                'name'          => __('Group'),
                'type'          => 'dropdown',
                'table'         => 'glpi_groups',
                'field'         => 'completename',
                'force_actions' => ['assign'],
            ],
        ];
    }
}
