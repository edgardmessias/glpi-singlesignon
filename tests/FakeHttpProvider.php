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

class FakeHttpProvider extends Provider
{
    /**
     * @var list<array{body?: string, error?: string}>
     */
    public array $responses = [];

    public static function getTable($classname = null)
    {
        return Provider::getTable();
    }

    /**
     * @param array<int, mixed> $options
     */
    protected function requestHttp(string $url, array $options, ?string &$msgerr = null): string
    {
        $next = array_shift($this->responses);
        if (!is_array($next)) {
            $msgerr = 'no scripted response for ' . $url;
            return '';
        }

        if (($next['error'] ?? '') !== '') {
            $msgerr = (string) $next['error'];
            return '';
        }

        $msgerr = null;

        return (string) ($next['body'] ?? '');
    }
}
