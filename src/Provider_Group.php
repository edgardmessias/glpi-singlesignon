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
use User;

/**
 * Persists the mapping between a remote group identifier (the raw claim value
 * received from the IdP) and a GLPI group ID, per SSO provider.
 *
 * This stable record survives group renames in GLPI: when a GLPI group is
 * renamed the `groups_id` foreign key still points to the correct row, so
 * subsequent logins continue to resolve the right GLPI group for each remote
 * group claim value.
 *
 * Static helper methods implement the full group-synchronization logic that
 * used to live in Provider.php so that group-related concerns are consolidated
 * here.
 */
class Provider_Group extends CommonDBRelation
{
    // From CommonDBRelation
    public static $itemtype_1 = Provider::class;
    public static $items_id_1 = 'plugin_singlesignon_providers_id';

    public static $itemtype_2 = 'Group';
    public static $items_id_2 = 'groups_id';

    private const MAX_GROUPS_PER_SYNC = 200;
    private const MAX_GROUP_CLAIM_STRING_LENGTH = 8192;
    private const MAX_GROUP_CLAIM_PATH_LENGTH = 1024;
    private const MAX_GROUP_CLAIM_PATH_DEPTH = 10;
    private const MAX_GROUP_NAME_LENGTH = 255;

    // ─────────────────────────────────────────────────────────────────────────
    // Public entry points
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Synchronize OAuth groups for the given user according to the provider's
     * configured synchronization mode.
     */
    public static function syncGroups(Provider $provider, User $user): void
    {
        if ($user->getID() <= 0) {
            return;
        }

        $mode = $provider->sanitizeUserGroupSyncMode(
            $provider->fields['user_group_sync_mode'] ?? Provider::USER_GROUP_SYNC_DISABLED
        );

        if ($mode === Provider::USER_GROUP_SYNC_DISABLED) {
            return;
        }

        $resourceOwner = $provider->getResourceOwner();
        if (!$resourceOwner || !is_array($resourceOwner)) {
            return;
        }

        $groups = static::extractUserGroupsFromResource($provider, $resourceOwner);

        if ($mode === Provider::USER_GROUP_SYNC_RULE_MATCHED) {
            static::applyPluginGroupRules($provider, $user, $groups, $resourceOwner);
        } else {
            // Evaluate rules to obtain entity/is_recursive values for any
            // groups that may need to be auto-created in SYNC_ALL mode.
            $ruleResult = $provider->evaluateRulesForUser(
                (string) ($user->fields['name'] ?? ''),
                null,
                $groups,
                false,
                $resourceOwner
            );
            static::assignGroupsToUser(
                $provider,
                $user,
                $groups,
                $mode,
                $ruleResult['_entities_id_default'],
                $ruleResult['is_recursive']
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Extract the list of group-name strings from the OAuth resource-owner
     * payload according to the provider's configured group claim path.
     *
     * @param array<string, mixed> $resource_array
     * @return string[]
     */
    public static function extractUserGroupsFromResource(Provider $provider, array $resource_array): array
    {
        $groups = [];
        $groupsClaim = trim((string) ($provider->fields['groups_claim'] ?? ''));
        if ($groupsClaim !== '') {
            return array_slice(
                static::normalizeGroupClaimValue(
                    static::getResourceOwnerValueByClaimPath($resource_array, $groupsClaim)
                ),
                0,
                self::MAX_GROUPS_PER_SYNC,
            );
        }

        $groupFields = ['groups', 'roles'];
        foreach ($groupFields as $field) {
            if (array_key_exists($field, $resource_array)) {
                $groups = array_merge($groups, static::normalizeGroupClaimValue($resource_array[$field]));
            }
        }

        if (
            isset($resource_array['realm_access'])
            && is_array($resource_array['realm_access'])
            && array_key_exists('roles', $resource_array['realm_access'])
        ) {
            $groups = array_merge($groups, static::normalizeGroupClaimValue($resource_array['realm_access']['roles']));
        }

        if (isset($resource_array['resource_access']) && is_array($resource_array['resource_access'])) {
            foreach ($resource_array['resource_access'] as $clientRoles) {
                if (!is_array($clientRoles) || !array_key_exists('roles', $clientRoles)) {
                    continue;
                }
                $groups = array_merge($groups, static::normalizeGroupClaimValue($clientRoles['roles']));
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
            if ($items === false) {
                return [];
            }
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

    /**
     * Walk a dot-separated claim path into the resource-owner array and return
     * the value found, or null when the path does not match.
     *
     * @param array<string, mixed> $resource_array
     * @return mixed
     */
    private static function getResourceOwnerValueByClaimPath(array $resource_array, string $claimPath)
    {
        if (strlen($claimPath) > self::MAX_GROUP_CLAIM_PATH_LENGTH || str_contains($claimPath, '..')) {
            return null;
        }

        $parts = array_values(array_filter(explode('.', $claimPath), static fn($part): bool => $part !== ''));
        if ($parts === [] || count($parts) > self::MAX_GROUP_CLAIM_PATH_DEPTH) {
            return null;
        }

        $value = $resource_array;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }

    /**
     * Assign (or remove) dynamic group memberships for a user based on the
     * provided list of IdP group-name strings.
     *
     * Groups that exist in GLPI are linked dynamically.  In SYNC_ALL mode,
     * groups that do not yet exist in GLPI are created automatically.
     * Any dynamic membership that is no longer covered by the current group
     * list is removed.
     *
     * @param string[] $groups
     */
    private static function assignGroupsToUser(
        Provider $provider,
        User $user,
        array $groups,
        int $mode,
        int $entitiesId = 0,
        bool $isRecursive = false
    ): void {
        $groupUser = new \Group_User();
        $group = new \Group();
        $links = $groupUser->find([
            'users_id'   => $user->getID(),
            'is_dynamic' => 1,
        ]);

        $keepDynGroupIds = [];

        foreach ($groups as $groupValue) {
            $groupValue = static::sanitizeGroupClaimEntry((string) $groupValue);
            if ($groupValue === null) {
                continue;
            }

            $groupId = static::resolveGroupIdFromClaimValue($provider, $group, $groupValue);

            if ($groupId === null && $mode === Provider::USER_GROUP_SYNC_ALL) {
                $groupId = $group->add([
                    'name'         => $groupValue,
                    'entities_id'  => $entitiesId,
                    'is_recursive' => $isRecursive ? 1 : 0,
                    'comment'      => __('Created automatically by Single Sign-On', 'singlesignon'),
                    'add'          => 1,
                ]);
                $groupId = is_numeric($groupId) ? (int) $groupId : null;
            }

            if ($groupId === null) {
                continue;
            }

            // Persist (or update) the remote_id → groups_id mapping so future
            // logins can find the correct GLPI group even after a rename.
            static::ensureGroupMapping($provider, $groupValue, $groupId);

            if ($groupUser->getFromDBByCrit([
                'users_id'  => $user->getID(),
                'groups_id' => $groupId,
            ])) {
                $keepDynGroupIds[] = $groupId;
                continue;
            }

            $linkId = $groupUser->add([
                'users_id'   => $user->getID(),
                'groups_id'  => $groupId,
                'is_dynamic' => 1,
            ]);
            if (is_numeric($linkId)) {
                $keepDynGroupIds[] = $groupId;
            }
        }

        $keepDynGroupIds = array_values(array_unique(array_map('intval', $keepDynGroupIds)));
        foreach ($links as $link) {
            $linkedGroupId = (int) ($link['groups_id'] ?? 0);
            if ($linkedGroupId <= 0 || in_array($linkedGroupId, $keepDynGroupIds, true)) {
                continue;
            }
            $groupUser->delete(['id' => (int) $link['id']], true);
        }
    }

    /**
     * Evaluate the plugin's SSO rules and assign the user to every GLPI group
     * returned by matching rules.  Dynamic group memberships that are no longer
     * covered by the rules are removed.
     *
     * This method is the entry-point for USER_GROUP_SYNC_RULE_MATCHED.  It
     * never creates new GLPI groups; it only references existing ones.
     *
     * @param string[]             $ssoGroups    Raw IdP group name strings from the token claim.
     * @param array<string, mixed> $resourceArray OAuth resource-owner data (for rule criteria).
     */
    private static function applyPluginGroupRules(
        Provider $provider,
        User $user,
        array $ssoGroups,
        array $resourceArray = []
    ): void {
        $groupUser = new \Group_User();

        // Snapshot all current dynamic group memberships before we start.
        $links = $groupUser->find([
            'users_id'   => $user->getID(),
            'is_dynamic' => 1,
        ]);

        $ruleResult = $provider->evaluateRulesForUser(
            (string) ($user->fields['name'] ?? ''),
            null,
            $ssoGroups,
            false,
            $resourceArray
        );
        $targetGroupIds = $ruleResult['specific_groups_id'];

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
            if (is_numeric($linkId)) {
                $keepGroupIds[] = $groupId;
            }
        }

        // Remove dynamic groups no longer covered by the current rule results.
        $keepGroupIds = array_values(array_unique(array_map('intval', $keepGroupIds)));
        foreach ($links as $link) {
            $linkedGroupId = (int) ($link['groups_id'] ?? 0);
            if ($linkedGroupId <= 0 || in_array($linkedGroupId, $keepGroupIds, true)) {
                continue;
            }
            $groupUser->delete(['id' => (int) $link['id']], true);
        }
    }

    /**
     * Sanitise a raw group claim string entry for safe storage and comparison.
     * Returns null when the value should be skipped.
     */
    private static function sanitizeGroupClaimEntry(string $groupValue): ?string
    {
        $groupValue = trim($groupValue);
        if ($groupValue === '') {
            return null;
        }

        // Keep only predictable UTF-8 group names from external OAuth payloads.
        if (preg_match('/^[\p{L}\p{N}\s._@:\-]+$/u', $groupValue) !== 1) {
            return null;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($groupValue, 'UTF-8') : strlen($groupValue);
        if ($length > self::MAX_GROUP_NAME_LENGTH) {
            return null;
        }

        return $groupValue;
    }

    /**
     * Resolve a GLPI group ID from an IdP claim value by checking the
     * stable provider→group mapping table, then numeric direct lookup, then
     * falling back to a name-based lookup in the GLPI groups table.
     */
    private static function resolveGroupIdFromClaimValue(Provider $provider, \Group $group, string $groupValue): ?int
    {
        // Check the stable provider→group mapping table first.  This survives
        // GLPI group renames because the record is keyed by GLPI groups_id.
        $mapping = (new Provider_Group())->find([
            'plugin_singlesignon_providers_id' => $provider->fields['id'],
            'remote_id' => $groupValue,
        ], '', 1);
        if ($mapping !== []) {
            $row = current($mapping);
            $mappedId = (int) ($row['groups_id'] ?? 0);
            if ($mappedId > 0 && $group->getFromDB($mappedId)) {
                return $mappedId;
            }
        }

        // Numeric claim values may be used as a direct GLPI group ID.
        if (is_numeric($groupValue)) {
            $groupId = (int) $groupValue;
            if ($groupId > 0 && $group->getFromDB($groupId)) {
                return $groupId;
            }
        }

        // Fall back to a name-based lookup in the GLPI groups table.
        $found = $group->find(['name' => $groupValue], '', 1);
        if ($found === []) {
            return null;
        }

        $firstKey = array_key_first($found);
        if (!is_numeric($firstKey)) {
            return null;
        }

        return (int) $firstKey;
    }

    /**
     * Upserts the remote_id → groups_id mapping in the providers_groups table.
     *
     * Existing GLPI group names and properties are never touched; only the
     * cross-reference record in this plugin's own table is created or updated.
     * If the mapping already exists and points to the same GLPI group, the
     * method is a no-op.
     */
    public static function ensureGroupMapping(Provider $provider, string $remoteId, int $groupId): void
    {
        global $DB;

        $table = self::getTable();
        $providerId = (int) $provider->fields['id'];

        $existing = $DB->request([
            'SELECT' => ['id', 'groups_id'],
            'FROM'   => $table,
            'WHERE'  => [
                'plugin_singlesignon_providers_id' => $providerId,
                'remote_id' => $remoteId,
            ],
        ]);

        foreach ($existing as $row) {
            if ((int) $row['groups_id'] !== $groupId) {
                // The GLPI group ID changed (e.g. deleted and recreated) — update it.
                $DB->update($table, ['groups_id' => $groupId], ['id' => (int) $row['id']]);
            }
            // Either way the record exists; nothing further to do.
            return;
        }

        $DB->insert($table, [
            'plugin_singlesignon_providers_id' => $providerId,
            'groups_id' => $groupId,
            'remote_id' => $remoteId,
        ]);
    }
}
