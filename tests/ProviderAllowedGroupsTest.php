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

namespace GlpiPlugin\Singlesignon\Tests;

use GlpiPlugin\Singlesignon\Provider_Field;

class ProviderAllowedGroupsTest extends PluginTestCase
{
    /** @var array<string, mixed> */
    private const KEYCLOAK_RESOURCE = [
        'sub'             => 'remote-id',
        'groups'          => ['/staff'],
        'realm_access'    => ['roles' => ['offline_access', 'viewer']],
        'resource_access' => ['glpi' => ['roles' => ['editor']]],
    ];

    private function addRolesMapping(int $providerId, string $jsonPath): void
    {
        $mapping = new Provider_Field();
        $mappingId = $mapping->add([
            'plugin_singlesignon_providers_id' => $providerId,
            'field_type' => 'roles',
            'jsonpath' => $jsonPath,
            'is_active' => 1,
            'sort_order' => 10,
        ]);
        $this->assertGreaterThan(0, (int) $mappingId);
    }

    public function testEmptyAllowedGroupsAllowsEveryone(): void
    {
        $provider = $this->createProvider(['allowed_groups' => '']);
        $this->assertTrue($provider->checkAllowedGroups(['sub' => 'remote-id']));

        $provider->fields['allowed_groups'] = ' , ';
        $this->assertTrue($provider->checkAllowedGroups(['sub' => 'remote-id']));
    }

    public function testDefaultRolesMappings(): void
    {
        $provider = $this->createProvider(['allowed_groups' => 'admin, /staff']);

        $this->assertSame(['/staff'], $provider->getUserGroups(self::KEYCLOAK_RESOURCE));
        $this->assertTrue($provider->checkAllowedGroups(self::KEYCLOAK_RESOURCE));
    }

    public function testDeniedWhenInNoGroup(): void
    {
        $provider = $this->createProvider(['allowed_groups' => 'admin']);

        $this->assertFalse($provider->checkAllowedGroups(self::KEYCLOAK_RESOURCE));
        $this->assertFalse($provider->checkAllowedGroups(['sub' => 'remote-id']));
    }

    public function testRolesFieldMappingTakesPrecedence(): void
    {
        $provider = $this->createProvider(['allowed_groups' => 'viewer']);
        $this->addRolesMapping((int) $provider->getID(), '$.realm_access.roles');

        $this->assertSame(['offline_access', 'viewer'], $provider->getUserGroups(self::KEYCLOAK_RESOURCE));
        $this->assertTrue($provider->checkAllowedGroups(self::KEYCLOAK_RESOURCE));

        $provider->fields['allowed_groups'] = '/staff';
        $this->assertFalse($provider->checkAllowedGroups(self::KEYCLOAK_RESOURCE));
    }

    public function testRolesFieldMappingWithWildcard(): void
    {
        $provider = $this->createProvider(['allowed_groups' => 'editor']);
        $this->addRolesMapping((int) $provider->getID(), '$.resource_access.*.roles');

        $this->assertSame(['editor'], $provider->getUserGroups(self::KEYCLOAK_RESOURCE));
        $this->assertTrue($provider->checkAllowedGroups(self::KEYCLOAK_RESOURCE));
    }
}
