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
use ReflectionMethod;

class OAuthStateTest extends PluginTestCase
{
    /** @var array<mixed> */
    private array $savedCookie = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedCookie = $_COOKIE;
        unset($_COOKIE[OAuthState::COOKIE_NAME]);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[OAuthState::COOKIE_NAME]);
        $_COOKIE = $this->savedCookie;
        parent::tearDown();
    }

    public function testSessionStateIsSingleUse(): void
    {
        $nonce = OAuthState::issue(4);

        $this->assertNull(OAuthState::consume($nonce, 4));
        $this->assertNotNull(OAuthState::consume($nonce, 4));
    }

    public function testEmptyStateIsRejected(): void
    {
        OAuthState::issue(4);

        $this->assertNotNull(OAuthState::consume('', 4));
    }

    public function testStateFromAnotherProviderIsRejected(): void
    {
        $nonce = OAuthState::issue(4);

        $this->assertNotNull(OAuthState::consume($nonce, 9));
    }

    public function testExpiredSessionStateIsRejected(): void
    {
        $nonce = OAuthState::issue(4);
        $_SESSION['glpi_singlesignon_states'][$nonce]['expires'] = time() - 10;

        $this->assertNotNull(OAuthState::consume($nonce, 4));
    }

    public function testTwoPendingStatesBothRemainValid(): void
    {
        $first = OAuthState::issue(4);
        $second = OAuthState::issue(4);

        $this->assertNull(OAuthState::consume($first, 4));
        $this->assertNull(OAuthState::consume($second, 4));
    }

    public function testCookieAloneSurvivesLostSession(): void
    {
        $nonce = OAuthState::issue(4);
        $expires = $_SESSION['glpi_singlesignon_states'][$nonce]['expires'];
        unset($_SESSION['glpi_singlesignon_states']);
        $_COOKIE[OAuthState::COOKIE_NAME] = $this->signedCookie(4, $expires, $nonce);

        $this->assertNull(OAuthState::consume($nonce, 4));
    }

    public function testCopiedCookieCannotBeReplayed(): void
    {
        $nonce = OAuthState::issue(4);
        $expires = $_SESSION['glpi_singlesignon_states'][$nonce]['expires'];
        $cookie = $this->signedCookie(4, $expires, $nonce);
        unset($_SESSION['glpi_singlesignon_states']);
        $_COOKIE[OAuthState::COOKIE_NAME] = $cookie;

        $this->assertNull(OAuthState::consume($nonce, 4));

        $_COOKIE[OAuthState::COOKIE_NAME] = $cookie;
        $this->assertNotNull(OAuthState::consume($nonce, 4));
    }

    public function testCopiedCookieIsRejectedAfterSessionLogin(): void
    {
        $nonce = OAuthState::issue(4);
        $expires = $_SESSION['glpi_singlesignon_states'][$nonce]['expires'];
        $cookie = $this->signedCookie(4, $expires, $nonce);

        $this->assertNull(OAuthState::consume($nonce, 4));

        $_COOKIE[OAuthState::COOKIE_NAME] = $cookie;
        $this->assertNotNull(OAuthState::consume($nonce, 4));
    }

    public function testTamperedCookieIsRejected(): void
    {
        $nonce = OAuthState::issue(4);
        $expires = $_SESSION['glpi_singlesignon_states'][$nonce]['expires'];
        unset($_SESSION['glpi_singlesignon_states']);
        $_COOKIE[OAuthState::COOKIE_NAME] = $this->signedCookie(4, $expires, $nonce) . 'ff';

        $this->assertNotNull(OAuthState::consume($nonce, 4));
    }

    private function signedCookie(int $providerId, int $expires, string $nonce): string
    {
        $method = new ReflectionMethod(OAuthState::class, 'getSigningKey');
        $key = $method->invoke(null);
        $this->assertIsString($key);

        $payload = implode('.', ['v1', (string) $providerId, (string) $expires, $nonce]);

        return $payload . '.' . hash_hmac('sha256', $payload, $key);
    }
}
