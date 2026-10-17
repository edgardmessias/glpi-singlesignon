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

use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\RedirectException;
use GlpiPlugin\Singlesignon\Provider;
use GlpiPlugin\Singlesignon\Provider_User;
use User;

class OAuthFlowTest extends PluginTestCase
{
    public function testAuthorizationRedirectContainsOAuthParameters(): void
    {
        $provider = $this->createProvider();
        $query = $this->authorizationQuery($provider);

        $this->assertSame('client-id', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertArrayHasKey('state', $query);
        if (version_compare(GLPI_VERSION, '12.0.0', '<')) {
            $this->assertNotSame('', (string) $query['state']);
        }
        $this->assertStringContainsString(
            '/plugins/singlesignon/front/callback.php/provider/' . $provider->getID(),
            (string) $query['redirect_uri'],
        );
    }

    public function testAuthorizationErrorThrows(): void
    {
        $provider = $this->createProvider();
        $_GET = ['error' => 'access_denied'];

        $this->expectException(BadRequestHttpException::class);
        $provider->checkAuthorization();
    }

    public function testCallbackAcceptsCodeMatchingRedirectState(): void
    {
        $provider = $this->createProvider();
        $query = $this->authorizationQuery($provider);

        $_GET = [
            'code' => 'auth-code',
            'state' => $query['state'],
        ];

        $this->assertSame('auth-code', $provider->checkAuthorization());
    }

    public function testAccessTokenAndIdTokenPayload(): void
    {
        $provider = $this->providerWithCode();
        $payload = rtrim(strtr(base64_encode('{"sub":"user-1"}'), '+/', '-_'), '=');
        $provider->responses[] = [
            'body' => (string) json_encode([
                'access_token' => 'token-1',
                'id_token' => 'hdr.' . $payload . '.sig',
            ]),
        ];

        $this->assertSame('token-1', $provider->getAccessToken());
        $this->assertSame('user-1', $provider->getIdTokenPayload()['sub'] ?? null);
    }

    public function testAccessTokenErrorDescriptionReturnsFalse(): void
    {
        $provider = $this->providerWithCode();
        $provider->responses[] = [
            'body' => (string) json_encode(['error_description' => 'invalid code']),
        ];

        $this->assertFalse($provider->getAccessToken());
    }

    public function testAccessTokenMissingTokenReturnsFalse(): void
    {
        $provider = $this->providerWithCode();
        $provider->responses[] = [
            'body' => (string) json_encode(['token_type' => 'bearer']),
        ];

        $this->assertFalse($provider->getAccessToken());
    }

    public function testAccessTokenConnectionErrorReturnsFalse(): void
    {
        $provider = $this->providerWithCode();
        $provider->responses[] = ['error' => 'connection refused'];

        $this->assertFalse($provider->getAccessToken());
    }

    public function testResourceOwnerUsesScriptedJson(): void
    {
        $provider = $this->providerWithCode();
        $provider->responses = [
            ['body' => (string) json_encode(['access_token' => 'token-1'])],
            ['body' => (string) json_encode(['id' => 'remote-1', 'username' => 'ada'])],
        ];

        $owner = $provider->getResourceOwner();

        $this->assertIsArray($owner);
        $this->assertSame('ada', $owner['username']);
    }

    public function testLinkedInResourceOwnerReadsEmailFromSecondCall(): void
    {
        $provider = $this->providerWithCode(['type' => 'linkedin']);
        $provider->responses = [
            ['body' => (string) json_encode(['access_token' => 'token-1'])],
            ['body' => (string) json_encode(['id' => 'li-1'])],
            ['body' => (string) json_encode([
                'elements' => [
                    ['handle~' => ['emailAddress' => 'ada@linkedin.test']],
                ],
            ])],
        ];

        $owner = $provider->getResourceOwner();

        $this->assertIsArray($owner);
        $this->assertSame('ada@linkedin.test', $owner['email-address']);
    }

    public function testFindUserByLoginName(): void
    {
        $provider = $this->createProvider();
        $user = $provider->findUser([
            'id' => 'not-linked',
            'username' => 'glpi',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('glpi', $user->fields['name']);
    }

    public function testFindUserByEmail(): void
    {
        $account = new User();
        $name = 'sso_phpunit_' . uniqid();
        $id = $account->add([
            'name' => $name,
            'password' => 'PhpUnit_4',
            'password2' => 'PhpUnit_4',
            '_useremails' => ['sso-phpunit@example.com'],
        ]);
        $this->assertGreaterThan(0, (int) $id);

        $provider = $this->createProvider(['use_email_for_login' => 1]);
        $user = $provider->findUser([
            'id' => 'email-remote',
            'email' => 'sso-phpunit@example.com',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($name, $user->fields['name']);
    }

    public function testFindUserRejectsUnauthorizedDomain(): void
    {
        $provider = $this->createProvider([
            'authorized_domains' => 'example.com',
            'use_email_for_login' => 1,
        ]);

        $this->assertFalse($provider->findUser([
            'id' => 'outside',
            'email' => 'person@other.test',
        ]));
    }

    public function testFindUserByRemoteId(): void
    {
        $provider = $this->createProvider();
        $glpi = new User();
        $this->assertTrue($glpi->getFromDBbyName('glpi'));

        $link = new Provider_User();
        $linkId = $link->add([
            'plugin_singlesignon_providers_id' => $provider->getID(),
            'users_id' => $glpi->getID(),
            'remote_id' => 'remote-77',
        ]);
        $this->assertGreaterThan(0, (int) $linkId);

        $user = $provider->findUser(['id' => 'remote-77']);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame((int) $glpi->getID(), (int) $user->getID());
    }

    public function testPhotoSyncStoresScriptedImage(): void
    {
        $provider = $this->providerWithCode([
            'user_photo_sync_mode' => Provider::PHOTO_SYNC_ALWAYS,
        ]);
        $provider->responses = [
            ['body' => (string) json_encode(['access_token' => 'token-1'])],
            ['body' => (string) json_encode(['picture' => 'https://idp.example/avatar.jpg'])],
            ['body' => $this->tinyJpeg()],
        ];

        $user = new User();
        $this->assertTrue($user->getFromDBbyName('glpi'));
        $stored = $provider->syncOAuthPhoto($user);

        $this->assertIsString($stored);
        User::dropPictureFiles($stored);
    }

    public function testPhotoSyncIgnoresJsonErrorBody(): void
    {
        $provider = $this->providerWithCode([
            'user_photo_sync_mode' => Provider::PHOTO_SYNC_ALWAYS,
        ]);
        $provider->responses = [
            ['body' => (string) json_encode(['access_token' => 'token-1'])],
            ['body' => (string) json_encode(['picture' => 'https://idp.example/avatar.jpg'])],
            ['body' => '{"error":"denied"}'],
        ];

        $user = new User();
        $this->assertTrue($user->getFromDBbyName('glpi'));

        $this->assertFalse($provider->syncOAuthPhoto($user));
    }

    public function testPerformGlpiLoginSetsProviderSession(): void
    {
        $provider = $this->providerWithCode([
            'user_photo_sync_mode' => Provider::PHOTO_SYNC_ALWAYS,
        ]);
        $provider->responses = [
            ['body' => (string) json_encode(['access_token' => 'token-1'])],
            ['body' => (string) json_encode(['picture' => 'https://idp.example/avatar.jpg'])],
            ['body' => $this->tinyJpeg()],
        ];

        $user = new User();
        $this->assertTrue($user->getFromDBbyName('glpi'));
        $this->assertTrue($provider->performGlpiLogin($user));
        $this->assertSame((int) $provider->getID(), (int) $_SESSION['glpi_singlesignon_provider']);

        $picture = (string) ($user->fields['picture'] ?? '');
        if ($picture !== '') {
            User::dropPictureFiles($picture);
        }
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function providerWithCode(array $extra = []): FakeHttpProvider
    {
        $provider = $this->createProvider($extra);
        $query = $this->authorizationQuery($provider);
        $_GET = [
            'code' => 'auth-code',
            'state' => $query['state'],
        ];
        $this->assertSame('auth-code', $provider->checkAuthorization());

        return $provider;
    }

    /**
     * @return array<string, mixed>
     */
    private function authorizationQuery(FakeHttpProvider $provider): array
    {
        unset($_GET['code'], $_GET['error'], $_GET['state']);

        try {
            $provider->checkAuthorization();
            $this->fail('authorization step should redirect');
        } catch (RedirectException $exception) {
            $url = $exception->getResponse()->getTargetUrl();
        }

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }

    private function tinyJpeg(): string
    {
        $image = imagecreatetruecolor(1, 1);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return is_string($bytes) ? $bytes : '';
    }
}
