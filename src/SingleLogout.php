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

    /**
     * Validate an absolute web URL with a host and no embedded credentials.
     * Use HTTPS-only validation for OIDC metadata and advertised logout endpoints.
     */
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
     * Read logout metadata from the configured issuer's discovery document.
     * The returned issuer must match exactly, including any trailing slash.
     *
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
     * Cache Azure logout metadata by tenant discovery URL and plugin version.
     * A null result lets the caller use the preset's static logout URL.
     *
     * @param callable(string, array<int, mixed>): string $request
     * @return array{issuer: string, endpoint: string}|null
     */
    public static function discoverAzure(string $url, callable $request): ?array
    {
        try {
            $cache = new FilesystemAdapter('singlesignon-slo', 3600, Kernel::getCacheRootDir());
            $key = hash('sha256', PLUGIN_SINGLESIGNON_VERSION . '|' . $url);

            // Fetch on a cache miss and give failed discovery a shorter lifetime.
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
     * Fetch discovery metadata through the caller's proxy-aware HTTP transport.
     * Require verified HTTPS, bound the request time, and validate the advertised logout URL.
     *
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

    /**
     * Match the token issuer to the verified discovery issuer.
     * For Azure tenant-independent metadata, expand the template using the token's tenant ID.
     */
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

    /**
     * Build the GLPI login URL that skips automatic SSO after logout.
     */
    public static function getReturnUrl(): string
    {
        return self::withParameters(self::getGlpiBaseUrl() . '/index.php', ['noAUTO' => '1']);
    }

    /**
     * Add noAUTO=1 to a manual logout URL's post_logout_redirect_uri when it points to this GLPI installation.
     */
    public static function withNoAutoOnManualReturn(string $url): string
    {
        foreach (explode('&', (string) parse_url($url, PHP_URL_QUERY)) as $parameter) {
            [$name, $value] = array_pad(explode('=', $parameter, 2), 2, '');
            if (urldecode($name) !== 'post_logout_redirect_uri') {
                continue;
            }

            $returnUrl = urldecode($value);
            if (!self::isGlpiReturnUrl($returnUrl)) {
                return $url;
            }

            parse_str((string) parse_url($returnUrl, PHP_URL_QUERY), $query);
            if (($query['noAUTO'] ?? null) === '1') {
                return $url;
            }

            return self::withParameters($url, [
                'post_logout_redirect_uri' => self::withParameters($returnUrl, ['noAUTO' => '1']),
            ]);
        }

        return $url;
    }

    /**
     * Check that the return URL shares GLPI's origin and falls within its installation path.
     */
    private static function isGlpiReturnUrl(string $url): bool
    {
        if (!self::isWebUrl($url)) {
            return false;
        }

        $target = parse_url($url);
        $local = parse_url(self::getGlpiBaseUrl());
        if (!is_array($target) || !is_array($local)) {
            return false;
        }

        $scheme = strtolower($local['scheme'] ?? '');
        $port = $scheme === 'https' ? 443 : 80;
        $root = rtrim($local['path'] ?? '', '/');
        $path = $target['path'] ?? '/';

        return strtolower($target['scheme'] ?? '') === $scheme
            && strcasecmp($target['host'] ?? '', $local['host'] ?? '') === 0
            && ($target['port'] ?? $port) === ($local['port'] ?? $port)
            && ($path === $root || str_starts_with($path, $root . '/'));
    }

    /**
     * Resolve the public GLPI base URL, including the installation subdirectory.
     */
    private static function getGlpiBaseUrl(): string
    {
        global $CFG_GLPI;

        $base = rtrim(ToolboxPlugin::getBaseURL(), '/');
        if (empty(parse_url($base, PHP_URL_PATH))) {
            $base .= rtrim($CFG_GLPI['root_doc'] ?? '', '/');
        }

        return $base;
    }

    /**
     * Set or remove named query parameters while preserving unrelated parameters and the URL fragment.
     *
     * @param array<string, string|null> $parameters
     */
    public static function withParameters(string $url, array $parameters): string
    {
        [$url, $fragment] = array_pad(explode('#', $url, 2), 2, null);
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

        return $base . ($parts === [] ? '' : '?' . implode('&', $parts))
            . ($fragment === null ? '' : '#' . $fragment);
    }
}
