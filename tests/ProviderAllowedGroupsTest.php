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

use GlpiPlugin\Singlesignon\Provider;
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

    /**
     * Provider whose 'roles' field mappings are exactly $jsonPaths (the generic defaults are removed).
     */
    private function providerWithRolesMappings(string $allowedGroups, string ...$jsonPaths): Provider
    {
        $provider = $this->createProvider(['allowed_groups' => $allowedGroups]);
        $mapping = new Provider_Field();
        $mapping->deleteByCriteria([
            'plugin_singlesignon_providers_id' => $provider->getID(),
            'field_type' => 'roles',
        ]);
        foreach ($jsonPaths as $index => $jsonPath) {
            $mappingId = $mapping->add([
                'plugin_singlesignon_providers_id' => $provider->getID(),
                'field_type' => 'roles',
                'jsonpath' => $jsonPath,
                'is_active' => 1,
                'sort_order' => ($index + 1) * 10,
            ]);
            $this->assertGreaterThan(0, (int) $mappingId);
        }

        return $provider;
    }

    public function testEmptyAllowedGroupsAllowsEveryone(): void
    {
        $provider = $this->createProvider(['allowed_groups' => '']);
        $this->assertTrue($provider->checkAllowedGroups(['sub' => 'remote-id']));

        $provider->fields['allowed_groups'] = ' , ';
        $this->assertTrue($provider->checkAllowedGroups(['sub' => 'remote-id']));
    }

    public function testDefaultRolesMappingsAreMerged(): void
    {
        $provider = $this->createProvider(['allowed_groups' => 'admin']);

        $this->assertSame(['admin', '/staff'], $provider->getUserGroups(['roles' => ['admin'], 'groups' => ['/staff']]));
        $this->assertTrue($provider->checkAllowedGroups(['roles' => ['admin'], 'groups' => ['/staff']]));
    }

    public function testWithoutRolesMappingsFallsBackToWellKnownClaims(): void
    {
        $provider = $this->providerWithRolesMappings('editor');

        $this->assertSame(['/staff', 'offline_access', 'viewer', 'editor'], $provider->getUserGroups(self::KEYCLOAK_RESOURCE));
        $this->assertTrue($provider->checkAllowedGroups(self::KEYCLOAK_RESOURCE));
    }

    public function testDeniedWhenInNoGroup(): void
    {
        $provider = $this->createProvider(['allowed_groups' => 'admin']);

        $this->assertFalse($provider->checkAllowedGroups(self::KEYCLOAK_RESOURCE));
        $this->assertFalse($provider->checkAllowedGroups(['sub' => 'remote-id']));
    }

    public function testRolesMappingReturningAList(): void
    {
        $provider = $this->providerWithRolesMappings('viewer', '$.realm_access.roles');

        $this->assertSame(['offline_access', 'viewer'], $provider->getUserGroups(self::KEYCLOAK_RESOURCE));
        $this->assertTrue($provider->checkAllowedGroups(self::KEYCLOAK_RESOURCE));

        $provider->fields['allowed_groups'] = '/staff';
        $this->assertFalse($provider->checkAllowedGroups(self::KEYCLOAK_RESOURCE));
    }

    public function testRolesMappingsAreMerged(): void
    {
        $provider = $this->providerWithRolesMappings('editor', '$.realm_access.roles', '$.resource_access.*.roles', '$.missing');

        $this->assertSame(['offline_access', 'viewer', 'editor'], $provider->getUserGroups(self::KEYCLOAK_RESOURCE));
        $this->assertTrue($provider->checkAllowedGroups(self::KEYCLOAK_RESOURCE));
    }

    public function testRecursiveRolesMapping(): void
    {
        $provider = $this->providerWithRolesMappings('editor', '$..roles');

        $this->assertSame(['offline_access', 'viewer', 'editor'], $provider->getUserGroups(self::KEYCLOAK_RESOURCE));
    }

    public function testStringClaimIsSplitOnCommasAndSpaces(): void
    {
        $provider = $this->providerWithRolesMappings('staff', '$.grp');

        $this->assertSame(['admin', 'staff'], $provider->getUserGroups(['grp' => 'admin, staff']));
        $this->assertSame(['admin', 'staff'], $provider->getUserGroups(['grp' => 'admin staff']));
        $this->assertSame(['IT Staff'], $provider->getUserGroups(['grp' => ['IT Staff']]));
        $this->assertTrue($provider->checkAllowedGroups(['grp' => 'admin,staff']));
    }

    public function testGroupObjects(): void
    {
        $provider = $this->providerWithRolesMappings('Sales', '$.memberOf');

        $this->assertSame(
            ['Sales', 'IT Staff'],
            $provider->getUserGroups(['memberOf' => [['id' => 'g1', 'displayName' => 'Sales'], ['id' => 'g2', 'displayName' => 'IT Staff']]]),
        );
    }
}
