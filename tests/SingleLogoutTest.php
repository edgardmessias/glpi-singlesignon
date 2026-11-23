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

use GlpiPlugin\Singlesignon\OAuthState;
use GlpiPlugin\Singlesignon\Provider;
use GlpiPlugin\Singlesignon\SingleLogout;
use User;

class SingleLogoutTest extends PluginTestCase
{
    private const ISSUER = 'https://idp.example/realms/glpi';
    private const ENDPOINT = self::ISSUER . '/logout?tenant=glpi';

    public function testManualLogoutRequiresUrlAndPreservesQuery(): void
    {
        $provider = $this->createProvider();
        $this->assertSame(Provider::SLO_OFF, $provider->getSloMode());
        $this->assertNull($provider->getSloRedirectUrl());

        $input = $provider->fields;
        $input['slo_mode'] = Provider::SLO_ON;
        $input['url_slo'] = '';
        $this->assertFalse($provider->update($input));

        $url = 'https://idp.example/logout?post_logout_redirect_uri=https%3A%2F%2Fglpi.example%2F%3FnoAUTO%3D1';
        $input['url_slo'] = $url;
        $this->assertTrue($provider->update($input));
        $this->assertSame($url, $provider->getSloRedirectUrl());
        unset($input['slo_mode'], $input['url_slo']);
        $this->assertTrue($provider->update($input));
        $this->assertSame($url, $provider->getSloRedirectUrl());
        $this->assertSame([], $provider->requestUrls);
    }

    public function testDiscoveryRejectsMismatchedIssuerAndPreservesSavedEndpoint(): void
    {
        $provider = $this->createProvider();
        $this->enableDiscovery($provider);

        $provider->responses[] = ['body' => (string) json_encode([
            'issuer' => 'https://unrelated.example',
            'end_session_endpoint' => self::ENDPOINT,
        ])];
        $this->assertFalse($provider->update($provider->fields));
        $this->assertTrue($provider->getFromDB($provider->getID()));
        $this->assertSame(self::ENDPOINT, $provider->fields['slo_endpoint']);

        $provider->responses[] = ['body' => (string) json_encode(['issuer' => self::ISSUER])];
        $this->assertFalse($provider->update($provider->fields));
        $this->assertTrue($provider->getFromDB($provider->getID()));
        $this->assertSame(self::ENDPOINT, $provider->fields['slo_endpoint']);
    }

    public function testDiscoveryRetainsAnIssuerTrailingSlash(): void
    {
        $issuer = self::ISSUER . '/';
        $metadata = SingleLogout::discover($issuer, function (string $url) use ($issuer): string {
            $this->assertSame(self::ISSUER . '/.well-known/openid-configuration', $url);
            return (string) json_encode(['issuer' => $issuer, 'end_session_endpoint' => self::ENDPOINT]);
        });

        $this->assertSame(['issuer' => $issuer, 'endpoint' => self::ENDPOINT], $metadata);
    }

    public function testRegistrationPreviewCarriesOnlyItsOwnIdTokenIntoLogout(): void
    {
        $provider = $this->createProvider();
        $this->enableDiscovery($provider);
        $id = (int) $provider->getID();
        $_GET = ['code' => 'auth-code', 'state' => OAuthState::issue($id)];
        $this->assertSame('auth-code', $provider->checkAuthorization());

        $payload = rtrim(strtr(base64_encode((string) json_encode(['iss' => self::ISSUER, 'sub' => 'user-1'])), '+/', '-_'), '=');
        $idToken = 'header.' . $payload . '.signature';
        $provider->responses[] = ['body' => (string) json_encode(['access_token' => 'access', 'id_token' => $idToken])];
        $this->assertSame('access', $provider->getAccessToken());
        $this->assertArrayNotHasKey(Provider::SLO_SESSION_KEY, $_SESSION);

        $provider->storePendingRegistrationSession(['id' => 'user-1']);
        $pending = Provider::getPendingRegistrationSession();
        $this->assertNotNull($pending);
        Provider::clearPendingRegistrationSession();

        $confirmedProvider = new Provider();
        $this->assertTrue($confirmedProvider->getFromDB($id));
        $user = new User();
        $this->assertTrue($user->getFromDBbyName('glpi'));
        $this->assertTrue($confirmedProvider->performGlpiLogin($user, $pending['logout_token']));

        $expected = self::ENDPOINT . '&id_token_hint=' . $idToken
            . '&client_id=client-id&post_logout_redirect_uri=' . rawurlencode(SingleLogout::getReturnUrl());
        $this->assertSame($expected, $confirmedProvider->getSloRedirectUrl());

        $unhintedUrl = self::ENDPOINT . '&client_id=client-id&post_logout_redirect_uri=' . rawurlencode(SingleLogout::getReturnUrl());
        $_SESSION[Provider::SLO_SESSION_KEY]['provider_id'] = $id + 1;
        $this->assertSame($unhintedUrl, $confirmedProvider->getSloRedirectUrl());
        $_SESSION[Provider::SLO_SESSION_KEY]['provider_id'] = $id;
        $_SESSION[Provider::SLO_SESSION_KEY]['issuer'] = 'https://unrelated.example';
        $this->assertSame($unhintedUrl, $confirmedProvider->getSloRedirectUrl());
        $this->assertTrue($confirmedProvider->performGlpiLogin($user));
        $this->assertArrayNotHasKey(Provider::SLO_SESSION_KEY, $_SESSION);
        $this->assertSame($unhintedUrl, $confirmedProvider->getSloRedirectUrl());
    }

    public function testGoogleUsesCurrentPresetWithoutResaving(): void
    {
        $provider = $this->createProvider(['type' => 'google', 'slo_mode' => Provider::SLO_ON]);
        $this->assertNull($provider->fields['url_slo']);
        $this->assertSame('https://www.google.com/accounts/Logout', $provider->getSloRedirectUrl());

        $defaults = Provider::$default;
        $this->assertIsArray($defaults);
        try {
            Provider::$default['google']['url_slo'] = 'https://accounts.google.com/new-logout';
            $this->assertSame('https://accounts.google.com/new-logout', $provider->getSloRedirectUrl());
            $this->assertSame([], $provider->requestUrls);
        } finally {
            Provider::$default = $defaults;
        }
    }

    private function enableDiscovery(FakeHttpProvider $provider): void
    {
        $provider->responses[] = ['body' => (string) json_encode([
            'issuer' => self::ISSUER,
            'end_session_endpoint' => self::ENDPOINT,
        ])];
        $input = $provider->fields;
        $input['slo_mode'] = Provider::SLO_DISCOVERY;
        $input['slo_issuer'] = self::ISSUER;
        $this->assertTrue($provider->update($input));
        $this->assertSame([self::ISSUER . '/.well-known/openid-configuration'], $provider->requestUrls);
        $this->assertSame(self::ISSUER, $provider->fields['slo_issuer']);
        $this->assertSame(self::ENDPOINT, $provider->fields['slo_endpoint']);
    }
}
