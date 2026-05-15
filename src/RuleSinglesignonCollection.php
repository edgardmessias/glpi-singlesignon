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
 * Rule collection for the SSO group assignment rule engine.
 *
 * All rules in this collection are evaluated (stop_on_first_match = false)
 * so that a user can be assigned to multiple GLPI groups by separate rules.
 *
 * Input is populated in prepareInputDataForProcess() from the params array
 * provided by Provider::applyPluginGroupRules().  The caller passes:
 *   - 'sso_groups' => string[]   raw IdP group names from the token claim
 *   - 'login'      => string     the user's GLPI login name
 */
class RuleSinglesignonCollection extends \RuleCollection
{
    public static $rightname = 'config';

    /** Evaluate ALL matching rules so a user can receive multiple groups. */
    public bool $stop_on_first_match = false;

    public $menu_option = 'singlesignon';

    public function getTitle(): string
    {
        return __('SSO group assignment rules', 'singlesignon');
    }

    /**
     * Populate the criteria input from the params array supplied by Provider.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function prepareInputDataForProcess($input, $params): array
    {
        // Expose SSO group names as a (possibly multi-valued) criteria field.
        if (isset($params['sso_groups']) && is_array($params['sso_groups'])) {
            $input['SSO_GROUPS'] = $params['sso_groups'];
        }

        if (isset($params['login']) && is_string($params['login'])) {
            $input['login'] = $params['login'];
        }

        return $input;
    }
}
