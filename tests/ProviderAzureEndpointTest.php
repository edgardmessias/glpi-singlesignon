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

class ProviderAzureEndpointTest extends PluginTestCase
{
    public function testTenantIdStartingWithDigitIsKeptInUrls(): void
    {
        $tenant = '5250a841-0000-4000-8000-000000000000';

        $provider = new Provider();
        $id = $provider->add([
            'type'             => 'azure',
            'name'             => 'Azure',
            'client_id'        => 'client',
            'client_secret'    => 'secret',
            'azure_endpoint'   => $tenant,
            'url_authorize'    => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'url_access_token' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            '_use_slo'         => 1,
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
        $this->assertSame(
            'https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/logout',
            $provider->fields['url_slo'],
        );
    }
}
