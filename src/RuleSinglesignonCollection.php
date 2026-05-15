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
 * A rule may set the `_stop_rules_processing` action to terminate evaluation
 * early, mimicking the native RuleRight behaviour.
 *
 * The caller passes a params array to prepareInputDataForProcess().  Supported
 * params keys are:
 *   - 'sso_groups'     => string[]   raw IdP group names from the token claim
 *   - 'login'          => string     GLPI login name
 *   - 'email'          => string     full e-mail address
 *   - 'firstname'      => string
 *   - 'realname'       => string
 *   - 'location'       => string     IdP location claim value (officeLocation fallback)
 *   - 'supervisor'     => string     supervisor user name (for criterion matching)
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
        // Reuse the native GLPI translation for "Authorization assignment rules".
        return __('Authorization assignment rules');
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
        }
        if (isset($params['firstname']) && is_string($params['firstname'])) {
            $input['firstname'] = $params['firstname'];
        }
        if (isset($params['realname']) && is_string($params['realname'])) {
            $input['realname'] = $params['realname'];
        }
        if (isset($params['phone']) && is_string($params['phone'])) {
            $input['phone'] = $params['phone'];
        }
        if (isset($params['phone2']) && is_string($params['phone2'])) {
            $input['phone2'] = $params['phone2'];
        }
        if (isset($params['mobile']) && is_string($params['mobile'])) {
            $input['mobile'] = $params['mobile'];
        }
        if (isset($params['location']) && is_string($params['location'])) {
            $input['location'] = $params['location'];
        }
        if (isset($params['supervisor']) && is_string($params['supervisor'])) {
            $input['supervisor'] = $params['supervisor'];
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
     *   _entities_id_default: int,
     *   is_recursive: bool,
     *   is_active: bool|null,
     *   profiles_id: int,
     *   _profiles_id_default: int,
     *   specific_groups_id: int[],
     *   groups_id: int,
     *   timezone: string,
     *   language: string,
     *   _ignore_user_import: bool,
     *   _deny_login: bool,
     *   _stop_rules_processing: bool
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
            'auto_register'          => false,
            'registration_preview'   => false,
            '_entities_id_default'   => 0,
            'is_recursive'           => false,
            'is_active'              => null,  // null = do not change
            'profiles_id'            => 0,
            '_profiles_id_default'   => 0,
            'specific_groups_id'     => [],
            'groups_id'              => 0,
            'timezone'               => '',
            'language'               => '',
            '_ignore_user_import'    => false,
            '_deny_login'            => false,
            '_stop_rules_processing' => false,
        ];

        $actions = $this->testAllRules(
            [],
            [],
            [
                'login'        => $login,
                'email'        => $email ?? '',
                'sso_groups'   => $ssoGroups,
                'is_new_user'  => $isNewUser,
                'firstname'    => (string) ($resourceArray['given_name'] ?? $resourceArray['firstname'] ?? ''),
                'realname'     => (string) ($resourceArray['family_name'] ?? $resourceArray['realname'] ?? ''),
                'phone'        => (string) ($resourceArray['phone'] ?? (is_array($resourceArray['businessPhones'] ?? null) ? ($resourceArray['businessPhones'][0] ?? '') : '') ?: ''),
                'phone2'       => (string) ($resourceArray['phone2'] ?? (is_array($resourceArray['businessPhones'] ?? null) ? ($resourceArray['businessPhones'][1] ?? '') : '') ?: ''),
                'mobile'       => (string) ($resourceArray['mobile'] ?? $resourceArray['mobilePhone'] ?? ''),
                'location'     => (string) ($resourceArray['location'] ?? $resourceArray['officeLocation'] ?? ''),
                'supervisor'   => (string) ($resourceArray['supervisor'] ?? $resourceArray['manager'] ?? ''),
                'provider_id'  => $providerId,
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
                case '_entities_id_default':
                    $result['_entities_id_default'] = (int) $value;
                    break;
                case 'is_recursive':
                    $result['is_recursive'] = (bool) $value;
                    break;
                case 'is_active':
                    $result['is_active'] = (bool) $value;
                    break;
                case 'profiles_id':
                    $result['profiles_id'] = (int) $value;
                    break;
                case '_profiles_id_default':
                    $result['_profiles_id_default'] = (int) $value;
                    break;
                case 'specific_groups_id':
                    if (is_numeric($value) && (int) $value > 0) {
                        $result['specific_groups_id'][] = (int) $value;
                    }
                    break;
                case 'groups_id':
                    if (is_numeric($value) && (int) $value > 0) {
                        $result['groups_id'] = (int) $value;
                    }
                    break;
                case 'timezone':
                    if (is_string($value) && $value !== '') {
                        $result['timezone'] = $value;
                    }
                    break;
                case 'language':
                    if (is_string($value) && $value !== '') {
                        $result['language'] = $value;
                    }
                    break;
                case '_ignore_user_import':
                    $result['_ignore_user_import'] = (bool) $value;
                    break;
                case '_deny_login':
                    $result['_deny_login'] = (bool) $value;
                    break;
                case '_stop_rules_processing':
                    if ((bool) $value) {
                        $result['_stop_rules_processing'] = true;
                        // Stop processing further actions from this point.
                        break 2;
                    }
                    break;
            }
        }

        $result['specific_groups_id'] = array_values(array_unique($result['specific_groups_id']));
        return $result;
    }
}
