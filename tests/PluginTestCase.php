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

use Auth;
use GlpiPlugin\Singlesignon\Provider;
use PHPUnit\Framework\TestCase;
use Session;

abstract class PluginTestCase extends TestCase
{
    /** @var array<mixed> */
    private array $savedGet = [];

    /** @var array<mixed> */
    private array $savedRequest = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedGet = $_GET;
        $this->savedRequest = $_REQUEST;

        global $DB;
        $DB->beginTransaction();

        $auth = new Auth();
        $this->assertTrue($auth->login('glpi', 'glpi', true));
    }

    protected function tearDown(): void
    {
        global $DB;
        $DB->rollback();

        $_GET = $this->savedGet;
        $_REQUEST = $this->savedRequest;
        Session::destroy();

        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function createProvider(array $extra = []): FakeHttpProvider
    {
        $provider = new FakeHttpProvider();
        $id = $provider->add(array_merge([
            'name' => 'PHPUnit provider',
            'type' => 'generic',
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'url_authorize' => 'https://idp.example/authorize',
            'url_access_token' => 'https://idp.example/token',
            'url_resource_owner_details' => 'https://idp.example/userinfo',
            'is_active' => 1,
            'use_email_for_login' => 0,
            'user_photo_sync_mode' => Provider::PHOTO_SYNC_DISABLED,
        ], $extra));

        $this->assertGreaterThan(0, (int) $id);
        $this->assertTrue($provider->getFromDB((int) $id));

        return $provider;
    }
}
