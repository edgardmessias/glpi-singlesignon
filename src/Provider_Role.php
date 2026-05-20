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
use Plugin;
use Session;

/**
 * Persists the mapping between a remote group identifier (the raw claim value
 * received from the IdP) and a GLPI group ID, per SSO provider.
 *
 * This stable record survives group renames in GLPI: when a GLPI group is
 * renamed the `groups_id` foreign key still points to the correct row, so
 * subsequent logins continue to resolve the right GLPI group for each remote
 * group claim value.
 *
 * Row in `glpi_plugin_singlesignon_providers_roles`:
 *   provider_id + remote_id  →  groups_id  (configured mapping)
 *
 * A companion table `glpi_plugin_singlesignon_providers_groups` tracks the
 * dynamic group memberships that were actually applied at login time; those
 * rows are managed by {@see Provider_Group::syncRoleGroupsForUser()}, which also cleans
 * up stale memberships whenever the user logs in.
 */
class Provider_Role extends CommonDBRelation
{
    /**
     * Stores the role-mapping configuration:
     * provider + remote role/group key → GLPI group target.
     */
    public static $table = 'glpi_plugin_singlesignon_providers_roles';

    // From CommonDBRelation
    public static $itemtype_1 = Provider::class;
    public static $items_id_1 = 'plugin_singlesignon_providers_id';

    public static $itemtype_2 = 'Group';
    public static $items_id_2 = 'groups_id';

    public static function getDynamicGroupsTable(): string
    {
        return Provider_Group::getTable();
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
                    ],
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
                $payload['id'] = $mappingId;
                $this->update($payload);
            } else {
                $this->add($payload);
            }
        }

        Session::addMessageAfterRedirect(__s('Role mappings updated.', 'singlesignon'));
        Html::back();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

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
    public static function getConfiguredGlpiGroups(int $providerId): array
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
}
