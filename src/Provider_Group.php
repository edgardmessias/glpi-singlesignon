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

use Group_User;
use CommonDBRelation;
use JsonPath\JsonObject;
use Provider;
use Throwable;
use User;

use function Safe\preg_split;

/**
 * Synchronises OAuth group/role claims for a user at SSO login time.
 *
 * This class is responsible for the runtime group-sync logic: extracting
 * group/role claim values from the IdP resource-owner payload, resolving them
 * against the configured role mappings (stored in
 * `glpi_plugin_singlesignon_providers_roles` via {@see Provider_Role}), and
 * applying or removing the corresponding dynamic GLPI group memberships.
 *
 * The dynamic assignments are tracked in
 * `glpi_plugin_singlesignon_providers_groups` so that stale memberships can be
 * revoked on the user's next SSO login.
 */
class Provider_Group extends CommonDBRelation
{
    private const MAX_GROUPS_PER_SYNC = 200;
    private const MAX_GROUP_CLAIM_STRING_LENGTH = 8192;

    public static $table = 'glpi_plugin_singlesignon_providers_groups';

    // From CommonDBRelation
    public static $itemtype_1 = Provider_Role::class;
    public static $items_id_1 = 'plugin_singlesignon_providers_roles_id';

    public static $itemtype_2 = 'User';
    public static $items_id_2 = 'users_id';

    // ─────────────────────────────────────────────────────────────────────────
    // Public entry points
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Synchronize OAuth groups for the given user using the SSO role mappings.
     * Groups are always processed via role mapping; there is no
     * automatic group import mode.
     */
    public static function syncRoleGroupsForUser(Provider $provider, User $user): bool
    {
        global $DB;

        if ($user->getID() <= 0) {
            self::logFailure($provider, $user, __FUNCTION__, 'failed because user id is empty before dynamic group synchronization');
            return false;
        }

        $resourceOwner = $provider->getResourceOwner();
        if (!$resourceOwner || !is_array($resourceOwner)) {
            self::logFailure($provider, $user, __FUNCTION__, 'failed because the resource owner payload is empty before dynamic group synchronization');
            return false;
        }

        $providerId = (int) ($provider->fields['id'] ?? 0);
        if ($providerId <= 0) {
            self::logFailure($provider, $user, __FUNCTION__, 'failed because provider id is empty before dynamic group synchronization');
            return false;
        }

        $claims = static::extractUserGroupsFromResource($provider, $resourceOwner);
        $targetMappings = Provider_Role::getRoleMappingsForClaims($providerId, $claims);
        $targetRoleToGroup = [];
        $targetGroupIds = [];
        foreach ($targetMappings as $mapping) {
            $roleId = (int) ($mapping['id'] ?? 0);
            $groupId = (int) ($mapping['groups_id'] ?? 0);
            if ($roleId <= 0 || $groupId <= 0) {
                continue;
            }
            $targetRoleToGroup[$roleId] = $groupId;
            $targetGroupIds[] = $groupId;
        }
        $targetGroupIds = array_values(array_unique($targetGroupIds));
        $managedGroupIds = Provider_Role::getConfiguredGlpiGroups($providerId);

        $groupUser = new Group_User();
        $links = $groupUser->find([
            'users_id'   => $user->getID(),
            'is_dynamic' => 1,
        ]);

        $keepGroupIds = [];
        foreach ($targetGroupIds as $groupId) {
            if ($groupUser->getFromDBByCrit([
                'users_id'  => $user->getID(),
                'groups_id' => $groupId,
            ])) {
                $keepGroupIds[] = $groupId;
                continue;
            }

            $linkId = $groupUser->add([
                'users_id'   => $user->getID(),
                'groups_id'  => $groupId,
                'is_dynamic' => 1,
            ]);
            if (!is_numeric($linkId) || (int) $linkId <= 0) {
                self::logFailure(
                    $provider,
                    $user,
                    __FUNCTION__,
                    sprintf('failed to create dynamic Group_User link for groups_id=%d', $groupId)
                );
                return false;
            }
            $keepGroupIds[] = $groupId;
        }

        /** @var array<int, array<string, mixed>> $existingDynamicRows */
        $existingDynamicRows = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'plugin_singlesignon_providers_roles_id', 'groups_id'],
            'FROM'   => self::getTable(),
            'WHERE'  => ['users_id' => (int) $user->getID()],
        ]) as $row) {
            $existingDynamicRows[] = $row;
        }

        $existingByRole = [];
        foreach ($existingDynamicRows as $row) {
            $roleId = (int) ($row['plugin_singlesignon_providers_roles_id'] ?? 0);
            if ($roleId > 0) {
                $existingByRole[$roleId] = $row;
            }
        }

        foreach ($targetRoleToGroup as $roleId => $groupId) {
            $existingRow = $existingByRole[$roleId] ?? null;
            if ($existingRow !== null) {
                if ((int) ($existingRow['groups_id'] ?? 0) !== $groupId) {
                    $updated = $DB->update(self::getTable(), [
                        'groups_id' => $groupId,
                    ], [
                        'id' => (int) $existingRow['id'],
                    ]);
                    if ($updated === false) {
                        self::logFailure(
                            $provider,
                            $user,
                            __FUNCTION__,
                            sprintf('failed to update dynamic provider-group link id=%d to groups_id=%d', (int) $existingRow['id'], $groupId)
                        );
                    }
                }
                continue;
            }

            $inserted = $DB->insert(self::getTable(), [
                'users_id'                                 => (int) $user->getID(),
                'plugin_singlesignon_providers_roles_id'  => (int) $roleId,
                'groups_id'                                => (int) $groupId,
            ]);
            if ($inserted === false) {
                self::logFailure(
                    $provider,
                    $user,
                    __FUNCTION__,
                    sprintf('failed to insert dynamic provider-group link for role_id=%d groups_id=%d', $roleId, $groupId)
                );
                return false;
            }
        }

        $existingRoleIds = [];
        foreach ($existingDynamicRows as $row) {
            $roleId = (int) ($row['plugin_singlesignon_providers_roles_id'] ?? 0);
            if ($roleId > 0) {
                $existingRoleIds[] = $roleId;
            }
        }
        $existingRoleIds = array_values(array_unique($existingRoleIds));

        $roleInfoById = [];
        if ($existingRoleIds !== []) {
            foreach ($DB->request([
                'SELECT' => ['id', 'plugin_singlesignon_providers_id', 'is_active'],
                'FROM'   => Provider_Role::getTable(),
                'WHERE'  => ['id' => $existingRoleIds],
            ]) as $row) {
                $roleInfoById[(int) $row['id']] = $row;
            }
        }

        $removedGroupIds = [];
        foreach ($existingDynamicRows as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            $roleId = (int) ($row['plugin_singlesignon_providers_roles_id'] ?? 0);
            $groupId = (int) ($row['groups_id'] ?? 0);
            if ($rowId <= 0 || $roleId <= 0 || isset($targetRoleToGroup[$roleId])) {
                continue;
            }

            $roleRow = $roleInfoById[$roleId] ?? false;

            $shouldDelete = $roleRow === false
                || (int) ($roleRow['plugin_singlesignon_providers_id'] ?? 0) === $providerId;

            if (!$shouldDelete) {
                continue;
            }

            $deleted = $DB->delete(self::getTable(), ['id' => $rowId]);
            if ($deleted === false) {
                self::logFailure(
                    $provider,
                    $user,
                    __FUNCTION__,
                    sprintf('failed to delete obsolete dynamic provider-group link id=%d', $rowId)
                );
            }
            if ($groupId > 0) {
                $removedGroupIds[] = $groupId;
            }
        }

        $managedGroupIds = array_values(array_unique(array_merge($managedGroupIds, $removedGroupIds)));
        $managedMap = array_fill_keys(array_map('intval', $managedGroupIds), true);
        $keepMap = array_fill_keys(array_map('intval', $keepGroupIds), true);

        foreach ($links as $link) {
            $linkedGroupId = (int) ($link['groups_id'] ?? 0);
            if ($linkedGroupId <= 0 || !isset($managedMap[$linkedGroupId]) || isset($keepMap[$linkedGroupId])) {
                continue;
            }
            $deleted = $groupUser->delete(['id' => (int) $link['id']], true);
            if ($deleted === false) {
                self::logFailure(
                    $provider,
                    $user,
                    __FUNCTION__,
                    sprintf('failed to delete obsolete dynamic Group_User link id=%d groups_id=%d', (int) $link['id'], $linkedGroupId)
                );
                return false;
            }
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Extract the list of group-name strings from the OAuth resource-owner
     * payload.  Checks the provider's 'roles' field mappings first, then
     * falls back to well-known role/group claim keys.
     *
     * @param array<string, mixed> $resource_array
     * @return string[]
     */
    public static function extractUserGroupsFromResource(Provider $provider, array $resource_array): array
    {
        $groups = [];
        $providerId = (int) ($provider->fields['id'] ?? 0);
        $idTokenPayload = $provider->getIdTokenPayload();

        // Check for 'roles' type field mappings configured by the admin.
        if ($providerId > 0) {
            $groupMappings = Provider_Field::getMappingsForProvider($providerId, 'roles', true);
            foreach ($groupMappings as $mapping) {
                $jsonPath = trim((string) ($mapping['jsonpath'] ?? ''));
                if ($jsonPath === '') {
                    continue;
                }
                $result = null;
                try {
                    $json   = new JsonObject($resource_array);
                    $result = $json->get($jsonPath);
                } catch (Throwable) {
                    $result = null;
                }

                if ($result === null && is_array($idTokenPayload)) {
                    try {
                        $jsonJwt   = new JsonObject($idTokenPayload);
                        $result = $jsonJwt->get($jsonPath);
                    } catch (Throwable) {
                        $result = null;
                    }
                }

                $extracted = self::normalizeGroupClaimValue($result);
                if ($extracted !== []) {
                    return array_slice($extracted, 0, self::MAX_GROUPS_PER_SYNC);
                }
            }
        }

        // Default extraction: well-known role/group claim keys.
        $groupFields = ['roles', 'groups'];
        foreach ($groupFields as $field) {
            if (array_key_exists($field, $resource_array)) {
                $groups = array_merge($groups, self::normalizeGroupClaimValue($resource_array[$field]));
            } elseif (is_array($idTokenPayload) && array_key_exists($field, $idTokenPayload)) {
                $groups = array_merge($groups, self::normalizeGroupClaimValue($idTokenPayload[$field]));
            }
        }

        if (
            isset($resource_array['realm_access'])
            && is_array($resource_array['realm_access'])
            && array_key_exists('roles', $resource_array['realm_access'])
        ) {
            $groups = array_merge($groups, self::normalizeGroupClaimValue($resource_array['realm_access']['roles']));
        } elseif (
            is_array($idTokenPayload)
            && isset($idTokenPayload['realm_access'])
            && is_array($idTokenPayload['realm_access'])
            && array_key_exists('roles', $idTokenPayload['realm_access'])
        ) {
            $groups = array_merge($groups, self::normalizeGroupClaimValue($idTokenPayload['realm_access']['roles']));
        }

        if (isset($resource_array['resource_access']) && is_array($resource_array['resource_access'])) {
            foreach ($resource_array['resource_access'] as $clientRoles) {
                if (!is_array($clientRoles) || !array_key_exists('roles', $clientRoles)) {
                    continue;
                }
                $groups = array_merge($groups, self::normalizeGroupClaimValue($clientRoles['roles']));
            }
        } elseif (is_array($idTokenPayload) && isset($idTokenPayload['resource_access']) && is_array($idTokenPayload['resource_access'])) {
            foreach ($idTokenPayload['resource_access'] as $clientRoles) {
                if (!is_array($clientRoles) || !array_key_exists('roles', $clientRoles)) {
                    continue;
                }
                $groups = array_merge($groups, self::normalizeGroupClaimValue($clientRoles['roles']));
            }
        }

        return array_slice(array_values(array_unique($groups)), 0, self::MAX_GROUPS_PER_SYNC);
    }

    /**
     * Normalise a raw group-claim value (string, int, or array) into a flat
     * list of group-name strings.
     *
     * @param mixed $value
     * @return string[]
     */
    private static function normalizeGroupClaimValue($value): array
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '' || strlen($value) > self::MAX_GROUP_CLAIM_STRING_LENGTH) {
                return [];
            }
            $items = preg_split('/[,\s]+/u', $value, -1, PREG_SPLIT_NO_EMPTY);
            return array_values(array_unique($items));
        }

        if (is_numeric($value)) {
            return [(string) $value];
        }

        if (!is_array($value)) {
            return [];
        }

        $groups = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $item = trim($item);
                if ($item !== '') {
                    $groups[] = $item;
                }
                continue;
            }

            if (is_numeric($item)) {
                $groups[] = (string) $item;
                continue;
            }

            if (is_array($item)) {
                if (isset($item['displayName']) && is_string($item['displayName']) && trim($item['displayName']) !== '') {
                    $groups[] = trim($item['displayName']);
                    continue;
                }
                if (isset($item['name']) && is_string($item['name']) && trim($item['name']) !== '') {
                    $groups[] = trim($item['name']);
                    continue;
                }
                if (isset($item['id']) && (is_string($item['id']) || is_numeric($item['id']))) {
                    $groups[] = (string) $item['id'];
                }
            }
        }

        return array_values(array_unique($groups));
    }

}
