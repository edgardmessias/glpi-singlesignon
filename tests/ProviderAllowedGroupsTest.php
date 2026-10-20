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

class ProviderAllowedGroupsTest extends PluginTestCase
{
    /** @var array<string, mixed> */
    private const KEYCLOAK_RESOURCE = [
        'sub'             => 'remote-id',
        'groups'          => ['/staff'],
        'realm_access'    => ['roles' => ['offline_access', 'viewer']],
        'resource_access' => ['glpi' => ['roles' => ['editor']]],
    ];

    private function provider(string $allowedGroups, string $groupsClaim = ''): Provider
    {
        $provider = new Provider();
        $provider->fields['allowed_groups'] = $allowedGroups;
        $provider->fields['groups_claim'] = $groupsClaim;

        return $provider;
    }

    public function testEmptyAllowedGroupsAllowsEveryone(): void
    {
        $this->assertTrue($this->provider('')->checkAllowedGroups(['sub' => 'remote-id']));
        $this->assertTrue($this->provider(' , ')->checkAllowedGroups(['sub' => 'remote-id']));
    }

    public function testDefaultClaims(): void
    {
        $this->assertSame(
            ['/staff', 'offline_access', 'viewer', 'editor'],
            $this->provider('')->extractUserGroups(self::KEYCLOAK_RESOURCE),
        );
    }

    public function testAllowedWhenInAnyGroup(): void
    {
        $this->assertTrue($this->provider('admin, editor')->checkAllowedGroups(self::KEYCLOAK_RESOURCE));
    }

    public function testDeniedWhenInNoGroup(): void
    {
        $this->assertFalse($this->provider('admin')->checkAllowedGroups(self::KEYCLOAK_RESOURCE));
        $this->assertFalse($this->provider('admin')->checkAllowedGroups(['sub' => 'remote-id']));
    }

    public function testCustomClaimIsTheOnlySource(): void
    {
        $provider = $this->provider('editor', 'realm_access.roles');

        $this->assertSame(['offline_access', 'viewer'], $provider->extractUserGroups(self::KEYCLOAK_RESOURCE));
        $this->assertFalse($provider->checkAllowedGroups(self::KEYCLOAK_RESOURCE));
    }

    public function testCustomClaimAsString(): void
    {
        $this->assertSame(['admin', 'staff'], $this->provider('', 'grp')->extractUserGroups(['grp' => 'admin, staff']));
    }

    public function testMissingCustomClaim(): void
    {
        $this->assertSame([], $this->provider('', 'missing.path')->extractUserGroups(self::KEYCLOAK_RESOURCE));
    }
}
