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
 * Rule collection for the SSO rules engine.
 *
 * All rules in this collection are evaluated (stop_on_first_match = false) so
 * that a user can be assigned to multiple GLPI groups by separate rules and
 * so that a "default" catch-all rule can be overridden by more specific ones.
 *
 * The caller passes a params array to prepareInputDataForProcess().  Supported
 * params keys are:
 *   - 'sso_groups'     => string[]   raw IdP group names from the token claim
 *   - 'login'          => string     GLPI login name
 *   - 'email'          => string     full e-mail address
 *   - 'firstname'      => string
 *   - 'realname'       => string
 *   - 'officeLocation' => string     IdP officeLocation claim value
 *   - 'is_new_user'    => bool       true when the user does not yet exist in GLPI
 *   - 'provider_id'    => int        ID of the SSO provider that triggered the login
 */
class RuleSinglesignonCollection extends \RuleCollection
{
    public static $rightname = 'config';

    /** Evaluate ALL matching rules so a user can receive multiple actions. */
    // No type annotation: parent RuleCollection declares this property untyped,
    // so PHP requires the child to omit the type as well.
    public $stop_on_first_match = false;

    public $menu_option = 'singlesignon';

    public function getTitle(): string
    {
        return __('SSO rules', 'singlesignon');
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
        if (isset($params['sso_groups']) && is_array($params['sso_groups'])) {
            $input['SSO_GROUPS'] = $params['sso_groups'];
        }
        if (isset($params['login']) && is_string($params['login'])) {
            $input['login'] = $params['login'];
        }
        if (isset($params['email']) && is_string($params['email'])) {
            $input['email'] = $params['email'];
            $parts = explode('@', $params['email'], 2);
            if (isset($parts[1])) {
                $input['email_domain'] = strtolower($parts[1]);
            }
        }
        if (isset($params['firstname']) && is_string($params['firstname'])) {
            $input['firstname'] = $params['firstname'];
        }
        if (isset($params['realname']) && is_string($params['realname'])) {
            $input['realname'] = $params['realname'];
        }
        if (isset($params['officeLocation']) && is_string($params['officeLocation'])) {
            $input['officeLocation'] = $params['officeLocation'];
        }
        if (isset($params['is_new_user'])) {
            $input['is_new_user'] = $params['is_new_user'] ? '1' : '0';
        }
        if (isset($params['provider_id'])) {
            $input['provider_id'] = (int) $params['provider_id'];
        }

        return $input;
    }

    /**
     * Evaluate all rules for a user and return a structured result.
     *
     * The returned array always contains every key with a safe default so
     * callers do not have to guard against missing keys.
     *
     * @param string   $login        GLPI login name.
     * @param ?string  $email        Full e-mail address, or null if not known.
     * @param string[] $ssoGroups    Raw IdP group-name strings from the token.
     * @param bool     $isNewUser    True when the user does not yet exist in GLPI.
     * @param array    $resourceArray Full OAuth resource-owner data array.
     * @param int      $providerId   ID of the SSO provider.
     *
     * @return array{
     *   auto_register: bool,
     *   registration_preview: bool,
     *   entities_id: int,
     *   is_recursive: bool,
     *   profiles_id: int,
     *   groups_id: int[]
     * }
     */
    public function evaluateForUser(
        string $login,
        ?string $email,
        array $ssoGroups = [],
        bool $isNewUser = false,
        array $resourceArray = [],
        int $providerId = 0
    ): array {
        $result = [
            'auto_register'        => false,
            'registration_preview' => false,
            'entities_id'          => 0,
            'is_recursive'         => false,
            'profiles_id'          => 0,
            'groups_id'            => [],
        ];

        $actions = $this->testAllRules(
            [],
            [],
            [
                'login'          => $login,
                'email'          => $email ?? '',
                'sso_groups'     => $ssoGroups,
                'is_new_user'    => $isNewUser,
                'firstname'      => (string) ($resourceArray['given_name'] ?? $resourceArray['firstname'] ?? ''),
                'realname'       => (string) ($resourceArray['family_name'] ?? $resourceArray['realname'] ?? ''),
                'officeLocation' => (string) ($resourceArray['officeLocation'] ?? ''),
                'provider_id'    => $providerId,
            ]
        );

        if (!is_array($actions)) {
            return $result;
        }

        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            $field = $action['field'] ?? null;
            $value = $action['value'] ?? null;
            switch ($field) {
                case 'auto_register':
                    $result['auto_register'] = (bool) $value;
                    break;
                case 'registration_preview':
                    $result['registration_preview'] = (bool) $value;
                    break;
                case 'entities_id':
                    $result['entities_id'] = (int) $value;
                    break;
                case 'is_recursive':
                    $result['is_recursive'] = (bool) $value;
                    break;
                case 'profiles_id':
                    $result['profiles_id'] = (int) $value;
                    break;
                case 'groups_id':
                    if (is_numeric($value) && (int) $value > 0) {
                        $result['groups_id'][] = (int) $value;
                    }
                    break;
            }
        }

        $result['groups_id'] = array_values(array_unique($result['groups_id']));
        return $result;
    }
}
