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
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Html;
use JsonPath\JsonObject;
use Plugin;
use Session;
use Throwable;
use User;
use function Safe\preg_split;

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
    private const DYNAMIC_GROUPS_TABLE = 'glpi_plugin_singlesignon_providers_groups';
    public static $table = 'glpi_plugin_singlesignon_providers_roles';

    // From CommonDBRelation
    public static $itemtype_1 = Provider::class;
    public static $items_id_1 = 'plugin_singlesignon_providers_id';

    public static $itemtype_2 = 'Group';
    public static $items_id_2 = 'groups_id';

    private const MAX_GROUPS_PER_SYNC = 200;
    private const MAX_GROUP_CLAIM_STRING_LENGTH = 8192;

    public static function getDynamicGroupsTable(): string
    {
        return self::DYNAMIC_GROUPS_TABLE;
    }

    public static function getTypeName($nb = 0): string
    {
        return _n('Role mapping', 'Role mappings', $nb, 'singlesignon');
    }

    public static function canCreate(): bool
    {
        return static::canUpdate();
    }

    public static function canDelete(): bool
    {
        return static::canUpdate();
    }

    public static function canPurge(): bool
    {
        return static::canUpdate();
    }

    public static function canView(): bool
    {
        return static::canUpdate();
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Provider) {
            $count = 0;
             if ($_SESSION['glpishow_count_on_tabs']) {
                 $count = countElementsInTable(
                     (new self())->getTable(),
                    [
                        'plugin_singlesignon_providers_id' => $item->getID(),
                        'is_active' => 1,
                    ]
                 );
             }
            return self::createTabEntry(__('Role mappings', 'singlesignon'), $count, self::class, 'ti ti-users');
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!$item instanceof Provider) {
            return false;
        }

        $tab = new self();
        $tab->showProviderTab($item);
        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public entry points
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Synchronize OAuth groups for the given user using the SSO rules engine.
     * Groups are always processed via rule-based assignment; there is no
     * automatic group import mode.
     */
    public static function syncGroups(Provider $provider, User $user): void
    {
        global $DB;

        if ($user->getID() <= 0) {
            return;
        }

        $resourceOwner = $provider->getResourceOwner();
        if (!$resourceOwner || !is_array($resourceOwner)) {
            return;
        }

        $providerId = (int) ($provider->fields['id'] ?? 0);
        if ($providerId <= 0) {
            return;
        }

        $claims = static::extractUserGroupsFromResource($provider, $resourceOwner);
        $targetMappings = self::getRoleMappingsForClaims($providerId, $claims);
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
        $managedGroupIds = self::getConfiguredGlpiGroups($providerId);

        $groupUser = new \Group_User();
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
            if (is_numeric($linkId)) {
                $keepGroupIds[] = $groupId;
            }
        }

        $dynamicTable = self::getDynamicGroupsTable();
        /** @var array<int, array<string, mixed>> $existingDynamicRows */
        $existingDynamicRows = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'plugin_singlesignon_providers_roles_id', 'groups_id'],
            'FROM'   => $dynamicTable,
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
                    $DB->update($dynamicTable, [
                        'groups_id' => $groupId,
                    ], [
                        'id' => (int) $existingRow['id'],
                    ]);
                }
                continue;
            }

            $DB->insert($dynamicTable, [
                'users_id'                                 => (int) $user->getID(),
                'plugin_singlesignon_providers_roles_id'  => (int) $roleId,
                'groups_id'                                => (int) $groupId,
            ]);
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
                'FROM'   => self::getTable(),
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

            $DB->delete($dynamicTable, ['id' => $rowId]);
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
            $groupUser->delete(['id' => (int) $link['id']], true);
        }
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

    /**
     * @param int $providerId
     * @param array<int, mixed> $claimValues
     * @return list<array{id: int, groups_id: int}>
     */
    public static function getRoleMappingsForClaims(int $providerId, array $claimValues): array
    {
        global $DB;

        $normalizedClaims = [];
        foreach ($claimValues as $value) {
            $claim = trim((string) $value);
            if ($claim !== '') {
                $normalizedClaims[] = $claim;
            }
        }
        $normalizedClaims = array_values(array_unique($normalizedClaims));
        if ($providerId <= 0 || $normalizedClaims === []) {
            return [];
        }

        $mappings = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'groups_id'],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'plugin_singlesignon_providers_id' => $providerId,
                'remote_id'                        => $normalizedClaims,
                'is_active'                        => 1,
            ],
        ]) as $row) {
            $roleId = (int) ($row['id'] ?? 0);
            $groupId = (int) ($row['groups_id'] ?? 0);
            if ($roleId > 0 && $groupId > 0) {
                $mappings[] = [
                    'id'        => $roleId,
                    'groups_id' => $groupId,
                ];
            }
        }

        return $mappings;
    }

    /**
     * @return int[]
     */
    private static function getConfiguredGlpiGroups(int $providerId): array
    {
        global $DB;

        if ($providerId <= 0) {
            return [];
        }

        $groupIds = [];
        foreach ($DB->request([
            'SELECT' => ['groups_id'],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'plugin_singlesignon_providers_id' => $providerId,
            ],
        ]) as $row) {
            $groupId = (int) ($row['groups_id'] ?? 0);
            if ($groupId > 0) {
                $groupIds[] = $groupId;
            }
        }

        return array_values(array_unique($groupIds));
    }

    /**
     * Remove all dynamic-group tracking rows for the given role mapping and, for
     * each affected user, delete the corresponding Group_User link only when it
     * is flagged as `is_dynamic`.
     *
     * This is called both when a role mapping is purged and when it is deactivated
     * (is_active set to 0), so that GLPI group memberships are kept in sync
     * without waiting for the next SSO login.
     */
    public static function removeDynamicGroupsForRole(int $roleId): void
    {
        global $DB;

        if ($roleId <= 0) {
            return;
        }

        $dynamicTable = self::getDynamicGroupsTable();

        $groupUser    = new \Group_User();

        foreach ($DB->request([
            'SELECT' => ['id', 'users_id', 'groups_id'],
            'FROM'   => $dynamicTable,
            'WHERE'  => ['plugin_singlesignon_providers_roles_id' => $roleId],
        ]) as $row) {
            $userId  = (int) ($row['users_id']  ?? 0);
            $groupId = (int) ($row['groups_id'] ?? 0);

            // Remove the Group_User membership only when it is a dynamic link.
            if ($userId > 0 && $groupId > 0) {
                $links = $groupUser->find([
                    'users_id'   => $userId,
                    'groups_id'  => $groupId,
                    'is_dynamic' => 1,
                ]);
                foreach ($links as $link) {
                    $groupUser->delete(['id' => (int) $link['id']], true);
                }
            }

            // Remove the SSO tracking row.
            $DB->delete($dynamicTable, ['id' => (int) $row['id']]);
        }
    }

    /**
     * Called by GLPI when a role-mapping record is permanently deleted.
     * Cleans up all dynamic group memberships that referenced this mapping.
     */
    public function cleanDBonPurge(): void
    {
        self::removeDynamicGroupsForRole($this->getID());
    }

    /**
     * Upserts the remote_id → groups_id mapping in the providers_roles table.
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

    /**
     * @return list<array<string, mixed>>
     */
    public static function getMappingsForProvider(int $providerId): array
    {
        global $DB;

        if ($providerId <= 0) {
            return [];
        }

        $rows = [];
        foreach ($DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['plugin_singlesignon_providers_id' => $providerId],
            'ORDER' => ['remote_id ASC', 'id ASC'],
        ]) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function showProviderTab(Provider $provider): void
    {
        if (!$provider->getID()) {
            echo '<div class="center">' . htmlspecialchars(
                __('Save this provider before editing role mappings.', 'singlesignon'),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            ) . '</div>';
            return;
        }

        echo TemplateRenderer::getInstance()->render('@singlesignon/provider/show_role_mappings_tab.html.twig', [
            'provider'    => $provider,
            'provider_id' => (int) $provider->getID(),
            'mappings'    => static::getMappingsForProvider((int) $provider->getID()),
            'form_action' => ToolboxPlugin::getBaseURL() . Plugin::getPhpDir('singlesignon', false) . '/front/provider_group.form.php',
        ]);
    }

    public function executeFormAction(array $input): void
    {
        if (!isset($input['plugin_singlesignon_providers_id'])) {
            return;
        }

        $providerId = (int) $input['plugin_singlesignon_providers_id'];
        if ($providerId <= 0) {
            return;
        }

        $provider = new Provider();
        if (!$provider->getFromDB($providerId)) {
            return;
        }

        if (!$provider->can($providerId, UPDATE)) {
            return;
        }

        $rows = $input['_role_mappings'] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }

        // First pass: apply deletions so later updates/inserts can reuse remote_id values
        // protected by the unique key (provider_id, remote_id).
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mappingId = (int) ($row['id'] ?? 0);
            $delete = (int) ($row['_delete'] ?? 0) === 1;
            if ($mappingId > 0 && $delete) {
                $this->deleteByCriteria([
                    'id' => $mappingId,
                    'plugin_singlesignon_providers_id' => $providerId,
                ]);
            }
        }

        // Second pass: apply updates/inserts for active rows.
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mappingId = (int) ($row['id'] ?? 0);
            $delete = (int) ($row['_delete'] ?? 0) === 1;
            if ($delete) {
                continue;
            }

            $remoteId = trim((string) ($row['remote_id'] ?? ''));
            $groupId = (int) ($row['groups_id'] ?? 0);
            $isActive = (int) (($row['is_active'] ?? 0) ? 1 : 0);

            if ($remoteId === '' || $groupId <= 0) {
                continue;
            }

            $payload = [
                'plugin_singlesignon_providers_id' => $providerId,
                'remote_id'                        => $remoteId,
                'groups_id'                        => $groupId,
                'is_active'                        => $isActive,
            ];

            if ($mappingId > 0) {
                // If the mapping is being deactivated, remove dynamic group links now
                // so users do not retain the group until their next SSO login.
                if (!$isActive) {
                    self::removeDynamicGroupsForRole($mappingId);
                }
                $payload['id'] = $mappingId;
                $this->update($payload);
            } else {
                $this->add($payload);
            }
        }

        Session::addMessageAfterRedirect(__s('Role mappings updated.', 'singlesignon'));
        Html::back();
    }

}
