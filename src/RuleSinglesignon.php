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
 * Rule class for the SSO group assignment rule engine.
 *
 * Each rule can match on raw IdP group name strings (SSO_GROUPS) or on the
 * user's GLPI login and produce group assignment actions that map to an
 * existing GLPI group.  No GLPI group is created automatically by this
 * mechanism; only existing groups are referenced by the actions.
 */
class RuleSinglesignon extends \Rule
{
    // Use the standard 'config' right so that GLPI administrators can manage
    // these rules through the normal Rules interface.
    public static $rightname = 'config';

    public function getTitle(): string
    {
        return __('SSO group assignment rules', 'singlesignon');
    }

    public function getCriterias(): array
    {
        return [
            'SSO_GROUPS' => [
                'name'    => __('SSO Group (IdP claim)', 'singlesignon'),
                'type'    => 'text',
                // virtual = true tells the rule engine not to try a DB lookup
                // for the criteria values; matching is done against the array
                // of raw group-name strings provided at evaluation time.
                'virtual' => true,
            ],
            'login' => [
                'name' => __('Login'),
                'type' => 'text',
            ],
        ];
    }

    public function getActions(): array
    {
        return [
            'groups_id' => [
                'name'          => __('Group'),
                'type'          => 'dropdown',
                'table'         => 'glpi_groups',
                'field'         => 'completename',
                // force_actions keeps only the "assign" operator visible in
                // the rule form, which is the only meaningful operation here.
                'force_actions' => ['assign'],
            ],
        ];
    }
}
