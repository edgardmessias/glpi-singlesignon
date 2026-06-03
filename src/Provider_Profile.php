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

use CommonDBTM;

/**
 * Tracks the static glpi_profiles_users entry managed by the SSO plugin for
 * a given GLPI user. Because glpi_profiles_users is a native GLPI table (not
 * provider-specific), this mapping is keyed only by users_id.
 *
 * The referenced profile authorization is always kept as is_dynamic=0 so that
 * the GLPI rule engine cannot delete it during Auth::login(). Dynamic profiles
 * assigned by the rule engine coexist separately.
 */
class Provider_Profile extends CommonDBTM
{
    public static $rightname = 'plugin_singlesignon_provider';

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_singlesignon_providers_profiles';
    }

    /**
     * Returns the managed profile mapping for a given GLPI user, or false if
     * none exists (e.g. the admin deleted it manually).
     */
    public static function getForUser(int $userId): self|false
    {
        $obj = new self();
        if ($obj->getFromDBByCrit(['users_id' => $userId])) {
            return $obj;
        }

        return false;
    }
}
