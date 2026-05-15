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

use CommonDBRelation;

/**
 * Persists the mapping between a remote group identifier (the raw claim value
 * received from the IdP) and a GLPI group ID, per SSO provider.
 *
 * This stable record survives group renames in GLPI: when a GLPI group is
 * renamed the `groups_id` foreign key still points to the correct row, so
 * subsequent logins continue to resolve the right GLPI group for each remote
 * group claim value.
 */
class Provider_Group extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = Provider::class;
    public static $items_id_1 = 'plugin_singlesignon_providers_id';

    public static $itemtype_2 = 'Group';
    public static $items_id_2 = 'groups_id';
}
