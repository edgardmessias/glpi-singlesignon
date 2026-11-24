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
use GlpiPlugin\Singlesignon\SingleLogout;

class ProviderAzureEndpointTest extends PluginTestCase
{
    public function testTenantIdStartingWithDigitIsKeptInUrls(): void
    {
        $tenant = '5250a841-0000-4000-8000-000000000000';

        $provider = new FakeHttpProvider();
        $id = $provider->add([
            'type'             => 'azure',
            'name'             => 'Azure',
            'client_id'        => 'client',
            'client_secret'    => 'secret',
            'azure_endpoint'   => $tenant,
            'url_authorize'    => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'url_access_token' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'preset_slo_mode'  => Provider::SLO_ON,
        ]);

        $this->assertGreaterThan(0, $id);
        $this->assertSame(
            'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/authorize',
            $provider->fields['url_authorize'],
        );
        $this->assertSame(
            'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/token',
            $provider->fields['url_access_token'],
        );
        $this->assertNull($provider->fields['url_slo']);
        $this->assertSame(Provider::SLO_ON, $provider->getSloMode());
        $issuer = 'https://login.microsoftonline.com/' . $tenant . '/v2.0';
        $logoutUrl = 'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/logout';
        $originalDefaults = Provider::$default;
        $defaults = Provider::$default;
        $this->assertIsArray($defaults);
        $template = $defaults['azure']['slo_discovery_url'];
        $defaults['azure']['slo_discovery_url'] = $template . '?test=' . uniqid();
        Provider::$default = $defaults;

        try {
            $provider->responses[] = ['error' => 'discovery unavailable'];
            $this->assertSame($logoutUrl, $provider->getSloRedirectUrl());
            $this->assertStringStartsWith($issuer . '/.well-known/openid-configuration?', $provider->requestUrls[0]);

            $defaults['azure']['slo_discovery_url'] = $template . '?test=' . uniqid();
            Provider::$default = $defaults;
            $provider->responses[] = ['body' => (string) json_encode([
                'issuer' => $issuer,
                'end_session_endpoint' => $logoutUrl . '?source=discovery',
            ])];
            $this->assertSame($logoutUrl . '?source=discovery', $provider->getSloRedirectUrl());
            $this->assertSame($logoutUrl . '?source=discovery', $provider->getSloRedirectUrl());
            $this->assertCount(2, $provider->requestUrls);
        } finally {
            Provider::$default = $originalDefaults;
        }
    }

    /**
     * Verify that Azure's tenant-independent issuer matches the tenant from the ID token.
     */
    public function testTenantIndependentIssuerMatchesTokenTenant(): void
    {
        $tenantId = '5250a841-0000-4000-8000-000000000000';
        $issuerTemplate = 'https://login.microsoftonline.com/{tenantid}/v2.0';
        $issuer = 'https://login.microsoftonline.com/' . $tenantId . '/v2.0';

        $this->assertTrue(SingleLogout::issuerMatches($issuerTemplate, $issuer, $tenantId, true));
        $this->assertFalse(SingleLogout::issuerMatches($issuerTemplate, $issuer, '', true));
        $this->assertFalse(SingleLogout::issuerMatches($issuerTemplate, 'https://other.example/v2.0', $tenantId, true));
    }
}
