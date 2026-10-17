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

/**
 * Intermediate classes whose inherited properties match the GLPI parent type.
 * GLPI 11 leaves these properties untyped; GLPI 12 types them.
 * A single class body cannot satisfy both, and a trait cannot change the
 * default value, so each plugin class extends the matching base below.
 */

declare(strict_types=1);

namespace GlpiPlugin\Singlesignon;

use CommonDBRelation;
use CommonDBTM;

$glpi12 = defined('GLPI_VERSION') && version_compare(GLPI_VERSION, '12.0.0', '>=');

if ($glpi12) {
    abstract class ProviderBase extends CommonDBTM
    {
        public bool $dohistory = true;
        public static string $rightname = 'config';
    }

    abstract class ProviderFieldBase extends CommonDBTM
    {
        public static string $rightname = 'config';
    }

    abstract class ProviderProfileBase extends CommonDBTM
    {
        public static string $rightname = 'plugin_singlesignon_provider';
    }

    abstract class PreferenceBase extends CommonDBTM
    {
        protected static bool $notable = true;
        public static string $rightname = '';
    }

    abstract class ProviderUserBase extends CommonDBRelation
    {
        public static ?string $itemtype_1 = Provider::class;
        public static ?string $items_id_1 = 'plugin_singlesignon_providers_id';
        public static ?string $itemtype_2 = 'User';
        public static ?string $items_id_2 = 'users_id';
    }

    abstract class ProviderRoleBase extends CommonDBRelation
    {
        public static ?string $itemtype_1 = Provider::class;
        public static ?string $items_id_1 = 'plugin_singlesignon_providers_id';
        public static ?string $itemtype_2 = 'Group';
        public static ?string $items_id_2 = 'groups_id';
    }

    abstract class ProviderGroupBase extends CommonDBRelation
    {
        public static ?string $itemtype_1 = Provider_Role::class;
        public static ?string $items_id_1 = 'plugin_singlesignon_providers_roles_id';
        public static ?string $itemtype_2 = 'User';
        public static ?string $items_id_2 = 'users_id';
    }
} else {
    abstract class ProviderBase extends CommonDBTM
    {
        public $dohistory = true;
        public static $rightname = 'config';
    }

    abstract class ProviderFieldBase extends CommonDBTM
    {
        public static $rightname = 'config';
    }

    abstract class ProviderProfileBase extends CommonDBTM
    {
        public static $rightname = 'plugin_singlesignon_provider';
    }

    abstract class PreferenceBase extends CommonDBTM
    {
        protected static $notable = true;
        public static $rightname = '';
    }

    abstract class ProviderUserBase extends CommonDBRelation
    {
        public static $itemtype_1 = Provider::class;
        public static $items_id_1 = 'plugin_singlesignon_providers_id';
        public static $itemtype_2 = 'User';
        public static $items_id_2 = 'users_id';
    }

    abstract class ProviderRoleBase extends CommonDBRelation
    {
        public static $itemtype_1 = Provider::class;
        public static $items_id_1 = 'plugin_singlesignon_providers_id';
        public static $itemtype_2 = 'Group';
        public static $items_id_2 = 'groups_id';
    }

    abstract class ProviderGroupBase extends CommonDBRelation
    {
        public static $itemtype_1 = Provider_Role::class;
        public static $items_id_1 = 'plugin_singlesignon_providers_roles_id';
        public static $itemtype_2 = 'User';
        public static $items_id_2 = 'users_id';
    }
}
