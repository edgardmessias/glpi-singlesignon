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
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Plugin;
use Session;

class Provider_Field extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return _n('Field mapping', 'Field mappings', $nb, 'singlesignon');
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

    public static function getFieldTypes(): array
    {
        return [
            'id'         => __('ID'),
            'username'   => __('Username'),
            'email'      => __('Email'),
            'firstname'  => __('First name'),
            'lastname'   => __('Last name'),
            'fullname'   => __('Full name'),
            'avatar_url' => __('Avatar URL', 'singlesignon'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getMappingsForProvider(int $providerId, ?string $fieldType = null, bool $onlyActive = false): array
    {
        global $DB;

        $where = [
            'plugin_singlesignon_providers_id' => $providerId,
        ];

        if ($fieldType !== null) {
            $where['field_type'] = $fieldType;
        }

        if ($onlyActive) {
            $where['is_active'] = 1;
        }

        $rows = [];
        foreach ($DB->request([
            'FROM'  => (new self())->getTable(),
            'WHERE' => $where,
            'ORDER' => ['sort_order ASC', 'id ASC'],
        ]) as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array{field_type: string, jsonpath: string, is_active: int, sort_order: int}>
     */
    public static function getDefaultMappings(string $providerType): array
    {
        $defaults = Provider::getDefault($providerType, 'field_mappings', []);
        if (!is_array($defaults)) {
            $defaults = [];
        }

        $types = array_keys(static::getFieldTypes());
        $normalized = [];
        foreach ($defaults as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $fieldType = (string) ($row['field_type'] ?? '');
            $jsonpath = trim((string) ($row['jsonpath'] ?? ''));
            if (!in_array($fieldType, $types, true) || $jsonpath === '') {
                continue;
            }

            $normalized[] = [
                'field_type' => $fieldType,
                'jsonpath'   => $jsonpath,
                'is_active'  => (int) (($row['is_active'] ?? 1) ? 1 : 0),
                'sort_order' => (int) ($row['sort_order'] ?? (($index + 1) * 10)),
            ];
        }

        if ($normalized === [] && $providerType === 'generic') {
            return self::getGenericDefaultMappings();
        }

        return $normalized;
    }

    /**
     * @return list<array{field_type: string, jsonpath: string, is_active: int, sort_order: int}>
     */
    private static function getGenericDefaultMappings(): array
    {
        return [
            ['field_type' => 'id', 'jsonpath' => '$.id', 'is_active' => 1, 'sort_order' => 10],
            ['field_type' => 'id', 'jsonpath' => '$.username', 'is_active' => 1, 'sort_order' => 20],
            ['field_type' => 'id', 'jsonpath' => '$.sub', 'is_active' => 1, 'sort_order' => 30],
            ['field_type' => 'email', 'jsonpath' => '$.email', 'is_active' => 1, 'sort_order' => 40],
            ['field_type' => 'email', 'jsonpath' => '$[\'e-mail\']', 'is_active' => 1, 'sort_order' => 50],
            ['field_type' => 'email', 'jsonpath' => '$[\'email-address\']', 'is_active' => 1, 'sort_order' => 60],
            ['field_type' => 'email', 'jsonpath' => '$.mail', 'is_active' => 1, 'sort_order' => 70],
            ['field_type' => 'username', 'jsonpath' => '$.userPrincipalName', 'is_active' => 1, 'sort_order' => 80],
            ['field_type' => 'username', 'jsonpath' => '$.login', 'is_active' => 1, 'sort_order' => 90],
            ['field_type' => 'username', 'jsonpath' => '$.username', 'is_active' => 1, 'sort_order' => 100],
            ['field_type' => 'username', 'jsonpath' => '$.id', 'is_active' => 1, 'sort_order' => 110],
            ['field_type' => 'username', 'jsonpath' => '$.name', 'is_active' => 1, 'sort_order' => 120],
            ['field_type' => 'username', 'jsonpath' => '$.displayName', 'is_active' => 1, 'sort_order' => 130],
            ['field_type' => 'firstname', 'jsonpath' => '$.givenName', 'is_active' => 1, 'sort_order' => 135],
            ['field_type' => 'lastname', 'jsonpath' => '$.surname', 'is_active' => 1, 'sort_order' => 136],
            ['field_type' => 'fullname', 'jsonpath' => '$.displayName', 'is_active' => 1, 'sort_order' => 137],
            ['field_type' => 'avatar_url', 'jsonpath' => '$.picture', 'is_active' => 1, 'sort_order' => 140],
        ];
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Provider) {
            return self::createTabEntry(__('Field mappings', 'singlesignon'), 0, self::class, 'ti ti-list-search');
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

    public function showProviderTab(Provider $provider): void
    {
        if (!$provider->getID()) {
            echo '<div class="center">' . htmlspecialchars(
                __('Save this provider before editing field mappings.', 'singlesignon'),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8',
            ) . '</div>';
            return;
        }

        $mappings = static::getMappingsForProvider((int) $provider->getID());
        echo TemplateRenderer::getInstance()->render('@singlesignon/provider/show_field_mappings_tab.html.twig', [
            'provider'    => $provider,
            'provider_id' => (int) $provider->getID(),
            'mappings'    => $mappings,
            'field_types' => static::getFieldTypes(),
            'form_action' => ToolboxPlugin::getBaseURL() . Plugin::getPhpDir('singlesignon', false) . '/front/provider_field.form.php',
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

        $types = array_keys(static::getFieldTypes());
        $rows = $input['_field_mappings'] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mappingId = (int) ($row['id'] ?? 0);
            $delete = (int) ($row['_delete'] ?? 0) === 1;
            $fieldType = (string) ($row['field_type'] ?? '');
            $jsonpath = trim((string) ($row['jsonpath'] ?? ''));
            $sortOrder = (int) ($row['sort_order'] ?? 0);
            $isActive = (int) (($row['is_active'] ?? 0) ? 1 : 0);

            if ($mappingId > 0 && $delete) {
                $this->deleteByCriteria([
                    'id' => $mappingId,
                    'plugin_singlesignon_providers_id' => $providerId,
                ]);
            } elseif (!in_array($fieldType, $types, true) || $jsonpath === '') {
                continue;
            } else {
                $payload = [
                    'plugin_singlesignon_providers_id' => $providerId,
                    'field_type'                       => $fieldType,
                    'jsonpath'                         => $jsonpath,
                    'is_active'                        => $isActive,
                    'sort_order'                       => $sortOrder,
                ];

                if ($mappingId > 0) {
                    $payload['id'] = $mappingId;
                    $this->update($payload);
                    continue;
                }

                $this->add($payload);
            }
        }

        Session::addMessageAfterRedirect(__s('Field mappings updated.', 'singlesignon'));
        Html::back();
    }
}
