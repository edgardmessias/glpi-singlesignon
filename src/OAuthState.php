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

namespace GlpiPlugin\Singlesignon;

use GLPIKey;
use Throwable;

/**
 * OAuth 2.0 `state` parameter, owned by the plugin.
 *
 * The state used to be a GLPI CSRF token (`Session::getNewCSRFToken()` /
 * `Session::checkCSRF()`). GLPI 12 removed token-based CSRF protection: those
 * methods are deprecated no-ops there (the first returns an empty string, the
 * second validates nothing), so the callback would accept any `code` and,
 * for a user who already has a GLPI session, link whatever identity that code
 * belongs to. This class keeps the protection on GLPI 11 and 12.
 *
 * The state is a random nonce bound to the browser that started the flow, by
 * two independent means; either one is enough to accept the callback:
 *
 * 1. the PHP session, as before (`$_SESSION[SESSION_KEY]`, several pending
 *    states so that logins started in two tabs do not invalidate each other);
 * 2. a short-lived cookie scoped to the plugin path, signed with the GLPI
 *    security key, so the callback still validates when the PHP session did
 *    not survive the round trip to the identity provider (load balancers
 *    without shared sessions, session regeneration, ...). A tossed or forged
 *    cookie fails the signature check.
 *
 * Every state is single use and expires after STATE_TTL seconds. Consumed
 * states are also recorded in the GLPI cache until they expire, so a copied
 * state cookie cannot be replayed after a successful login.
 */
final class OAuthState
{
    public const COOKIE_NAME = 'glpi_singlesignon_state';

    private const SESSION_KEY = 'glpi_singlesignon_states';
    private const STATE_TTL = 600;
    private const MAX_PENDING = 10;
    private const COOKIE_VERSION = 'v1';
    private const CACHE_KEY_PREFIX = 'plugin_singlesignon_oauth_state_';

    /**
     * Create a new state for an authorization request and bind it to the browser.
     *
     * @return string The value to send as the `state` parameter.
     */
    public static function issue(int $provider_id): string
    {
        $nonce   = bin2hex(random_bytes(32));
        $expires = time() + self::STATE_TTL;

        $pending = self::getPendingStates();
        $pending[$nonce] = ['provider' => $provider_id, 'expires' => $expires];
        // Keep only the most recent pending states.
        $_SESSION[self::SESSION_KEY] = array_slice($pending, -self::MAX_PENDING, null, true);

        $key = self::getSigningKey();
        if ($key !== null) {
            $payload = implode('.', [self::COOKIE_VERSION, (string) $provider_id, (string) $expires, $nonce]);
            self::sendCookie($payload . '.' . hash_hmac('sha256', $payload, $key), $expires);
        }

        return $nonce;
    }

    /**
     * Validate the `state` returned by the identity provider and consume it.
     *
     * @return string|null Null when the state is valid; otherwise a short, non-secret
     *                     description of why it was rejected, meant for the logs.
     */
    public static function consume(string $state, int $provider_id): ?string
    {
        $session_result = self::consumeFromSession($state, $provider_id);
        $cookie_result  = self::consumeFromCookie($state, $provider_id);

        if ($session_result === null || $cookie_result === null) {
            // The cookie path records its own nonce. A session-only success still has to,
            // so a copied cookie cannot be replayed after the login.
            if ($session_result === null && $cookie_result !== null && $state !== '') {
                self::markConsumed($state, time() + self::STATE_TTL);
            }

            return null;
        }

        return sprintf('session: %s; cookie: %s', $session_result, $cookie_result);
    }

    /**
     * @return string|null Null when valid; otherwise the rejection reason.
     */
    private static function consumeFromSession(string $state, int $provider_id): ?string
    {
        $pending = self::getPendingStates();
        if ($pending === []) {
            return 'no pending state in the PHP session';
        }
        if ($state === '' || !isset($pending[$state])) {
            return 'state not found in the PHP session';
        }

        $entry = $pending[$state];
        unset($pending[$state]);
        $_SESSION[self::SESSION_KEY] = $pending;

        if ($entry['provider'] !== $provider_id) {
            return 'state was issued for another provider';
        }

        return null;
    }

    /**
     * @return string|null Null when valid; otherwise the rejection reason.
     */
    private static function consumeFromCookie(string $state, int $provider_id): ?string
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (!is_string($raw) || $raw === '') {
            return 'no state cookie received';
        }

        // Single use: clear the cookie whatever the outcome.
        self::sendCookie('', time() - 3600);
        unset($_COOKIE[self::COOKIE_NAME]);

        $key = self::getSigningKey();
        if ($key === null) {
            return 'GLPI security key unavailable';
        }

        $parts = explode('.', $raw);
        if (count($parts) !== 5 || $parts[0] !== self::COOKIE_VERSION) {
            return 'malformed state cookie';
        }
        [$version, $cookie_provider, $cookie_expires, $cookie_nonce, $signature] = $parts;

        $payload = implode('.', [$version, $cookie_provider, $cookie_expires, $cookie_nonce]);
        if (!hash_equals(hash_hmac('sha256', $payload, $key), $signature)) {
            return 'invalid state cookie signature';
        }
        if ((int) $cookie_expires < time()) {
            return 'state cookie expired';
        }
        if ((int) $cookie_provider !== $provider_id) {
            return 'state cookie was issued for another provider';
        }
        if (!hash_equals($cookie_nonce, $state)) {
            return 'state does not match the state cookie';
        }
        if (!self::markConsumed($cookie_nonce, (int) $cookie_expires)) {
            return 'state cookie was already used';
        }

        return null;
    }

    /**
     * Record a consumed state until it expires.
     *
     * @return bool False when the state had already been consumed.
     */
    private static function markConsumed(string $nonce, int $expires): bool
    {
        global $GLPI_CACHE;

        $key = self::CACHE_KEY_PREFIX . $nonce;
        try {
            // A missing cache throws here. The signature, the expiry and the cookie deletion still apply.
            if ($GLPI_CACHE->has($key)) {
                return false;
            }
            $GLPI_CACHE->set($key, 1, max(1, $expires - time()));
        } catch (Throwable) {
            return true;
        }

        return true;
    }

    /**
     * Pending states from the session, without expired or malformed entries.
     *
     * @return array<string, array{provider: int, expires: int}>
     */
    private static function getPendingStates(): array
    {
        $stored = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($stored)) {
            return [];
        }

        $now     = time();
        $pending = [];
        foreach ($stored as $nonce => $entry) {
            if (
                is_string($nonce)
                && is_array($entry)
                && is_int($entry['provider'] ?? null)
                && is_int($entry['expires'] ?? null)
                && $entry['expires'] >= $now
            ) {
                $pending[$nonce] = ['provider' => $entry['provider'], 'expires' => $entry['expires']];
            }
        }

        return $pending;
    }

    /**
     * HMAC key derived from the GLPI security key, so the raw key is never used directly.
     */
    private static function getSigningKey(): ?string
    {
        try {
            $glpi_key = (new GLPIKey())->get();
        } catch (Throwable) {
            return null;
        }
        if ($glpi_key === null || $glpi_key === '') {
            return null;
        }

        return hash_hmac('sha256', 'glpi-singlesignon/oauth-state', $glpi_key, true);
    }

    private static function sendCookie(string $value, int $expires): void
    {
        global $CFG_GLPI;

        if (headers_sent()) {
            return;
        }

        setcookie(self::COOKIE_NAME, $value, [
            'expires'  => $expires,
            // Both legs of the flow are served under this path (see ToolboxPlugin::getCallbackUrl()).
            'path'     => ($CFG_GLPI['root_doc'] ?? '') . '/plugins/singlesignon/',
            'secure'   => str_starts_with(ToolboxPlugin::getBaseURL(), 'https://'),
            'httponly' => true,
            // Lax is required: the identity provider sends the browser back with a cross-site top-level GET.
            'samesite' => 'Lax',
        ]);
    }
}
