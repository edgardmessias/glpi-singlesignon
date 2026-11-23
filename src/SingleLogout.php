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

namespace GlpiPlugin\Singlesignon;

use Glpi\Kernel\Kernel;
use Safe\Exceptions\JsonException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

use function Safe\json_decode;
use function Safe\parse_url;
use function Safe\preg_match;

final class SingleLogout
{
    private const DISCOVERY_PATH = '/.well-known/openid-configuration';
    private const AZURE_ISSUER = 'https://login.microsoftonline.com/{tenantid}/v2.0';
    private const TENANT_ID_PATTERN = '[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}';

    public static function isWebUrl(string $url, bool $httpsOnly = false): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), $httpsOnly ? ['https'] : ['http', 'https'], true)
            && !empty($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    /**
     * @param callable(string, array<int, mixed>): string $request
     * @return array{issuer: string, endpoint: string}|null
     */
    public static function discover(string $issuer, callable $request): ?array
    {
        if (!self::isWebUrl($issuer, true) || strpbrk($issuer, '?#') !== false) {
            return null;
        }

        $metadata = self::fetch(rtrim($issuer, '/') . self::DISCOVERY_PATH, $request);
        return $metadata !== null && $metadata['issuer'] === $issuer ? $metadata : null;
    }

    /**
     * @param callable(string, array<int, mixed>): string $request
     * @return array{issuer: string, endpoint: string}|null
     */
    public static function discoverAzure(string $url, callable $request): ?array
    {
        try {
            $cache = new FilesystemAdapter('singlesignon-slo', 3600, Kernel::getCacheRootDir());
            $key = hash('sha256', PLUGIN_SINGLESIGNON_VERSION . '|' . $url);

            return $cache->get($key, static function (ItemInterface $item) use ($url, $request): ?array {
                $metadata = self::fetch($url, $request);
                if ($metadata !== null && (
                    $metadata['issuer'] === self::AZURE_ISSUER
                    || preg_match('~^https://login\.microsoftonline\.com/' . self::TENANT_ID_PATTERN . '/v2\.0$~D', $metadata['issuer']) === 1
                )) {
                    return $metadata;
                }

                $item->expiresAfter(60);
                return null;
            });
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param callable(string, array<int, mixed>): string $request
     * @return array{issuer: string, endpoint: string}|null
     */
    private static function fetch(string $url, callable $request): ?array
    {
        if (!self::isWebUrl($url, true)) {
            return null;
        }

        try {
            $data = json_decode($request($url, [
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_FAILONERROR => true,
                CURLOPT_TIMEOUT => 5,
            ]), true);
        } catch (JsonException) {
            return null;
        }

        $issuer = $data['issuer'] ?? null;
        $endpoint = $data['end_session_endpoint'] ?? null;
        if (!is_string($issuer) || !is_string($endpoint) || !self::isWebUrl($endpoint, true) || str_contains($endpoint, '#')) {
            return null;
        }

        return ['issuer' => $issuer, 'endpoint' => $endpoint];
    }

    public static function issuerMatches(string $issuer, string $tokenIssuer, string $tenantId, bool $azure): bool
    {
        if ($azure && $issuer === self::AZURE_ISSUER) {
            if (preg_match('~^' . self::TENANT_ID_PATTERN . '$~D', $tenantId) !== 1) {
                return false;
            }
            $issuer = str_replace('{tenantid}', $tenantId, $issuer);
        }

        return $issuer === $tokenIssuer;
    }

    public static function getReturnUrl(): string
    {
        global $CFG_GLPI;

        $base = rtrim(ToolboxPlugin::getBaseURL(), '/');
        if (empty(parse_url($base, PHP_URL_PATH))) {
            $base .= rtrim($CFG_GLPI['root_doc'] ?? '', '/');
        }

        return $base . '/index.php?noAUTO=1';
    }

    /** @param array<string, string|null> $parameters */
    public static function withParameters(string $url, array $parameters): string
    {
        [$base, $query] = array_pad(explode('?', $url, 2), 2, '');
        $parts = [];
        foreach (explode('&', $query) as $part) {
            $name = urldecode(explode('=', $part, 2)[0]);
            if ($part !== '' && !array_key_exists($name, $parameters)) {
                $parts[] = $part;
            }
        }
        foreach ($parameters as $name => $value) {
            if ($value !== null) {
                $parts[] = rawurlencode($name) . '=' . rawurlencode($value);
            }
        }

        return $base . ($parts === [] ? '' : '?' . implode('&', $parts));
    }
}
