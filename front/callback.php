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

// OAuth callback endpoint - uses normal GLPI session to validate CSRF tokens

use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use Glpi\Exception\SessionExpiredException;
use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Singlesignon\Provider;
use GlpiPlugin\Singlesignon\Provider_Field;
use GlpiPlugin\Singlesignon\ToolboxPlugin;

use function Safe\json_encode;

include(__DIR__ . '/../../../inc/includes.php');

// Session is automatically started by GLPI for non-stateless endpoints

global $CFG_GLPI;

$provider_id = ToolboxPlugin::getCallbackParameters('provider');

if (!$provider_id) {
    $exception = new BadRequestHttpException();
    $exception->setMessageToDisplay(__("Provider not defined.", 'singlesignon'));
    throw $exception;
}

$signon_provider = new Provider();

if (!$signon_provider->getFromDB($provider_id)) {
    $exception = new NotFoundHttpException();
    $exception->setMessageToDisplay(__("Provider not found.", 'singlesignon'));
    throw $exception;
}

if (!$signon_provider->fields['is_active']) {
    $exception = new BadRequestHttpException();
    $exception->setMessageToDisplay(__("Provider not active.", 'singlesignon'));
    throw $exception;
}

/**
 * Handles the OAuth authorization callback from the identity provider.
 * This function validates the CSRF (state) token, extracts the authorization code,
 * and may perform a redirect depending on the authentication step.
 * If the authorization code is not present in the request, it may redirect the browser
 * to the provider's authorization page.
 */
$signon_provider->checkAuthorization();

/**
 * The "glpi_singlesignon_test" cookie is used to signal that this callback request is a test for the Single Sign-On (SSO) integration.
 * When set (by the test button in the provider configuration UI), this triggers debug output for developers or administrators
 * so they can inspect the SSO flow and returned data without performing an actual login.
 * The cookie is deleted after use to avoid repeated debug output.
 */
$test_cookie = isset($_COOKIE['glpi_singlesignon_test']) && $_COOKIE['glpi_singlesignon_test'] === '1';

if ($test_cookie) {
    setcookie('glpi_singlesignon_test', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['glpi_singlesignon_test']);
    $resource_owner = $signon_provider->getResourceOwner();
    $resource_owner_array = is_array($resource_owner) ? $resource_owner : [];

    $id_token_payload = $signon_provider->getIdTokenPayload();

    $resolved_fields = $signon_provider->getResolvedFieldsForDebug($resource_owner_array);
    $field_types = Provider_Field::getFieldTypes();
    $active_mappings = Provider_Field::getMappingsForProvider((int) $provider_id, null, true);
    $default_mappings = Provider_Field::getDefaultMappings((string) $signon_provider->fields['type']);
    $copy_payload_sections = [];

    try {
        $resource_owner_pretty = json_encode(
            $resource_owner,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    } catch (Throwable) {
        $resource_owner_pretty = (string) __('Unable to encode resource owner payload.', 'singlesignon');
    }

    if (is_array($id_token_payload)) {
        try {
            $id_token_payload_pretty = json_encode(
                $id_token_payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
        } catch (Throwable) {
            $id_token_payload_pretty = (string) __('Unable to encode ID token payload.', 'singlesignon');
        }
    } else {
        $id_token_payload_pretty = '';
    }

    $callback_context = [
        'provider_id'   => (int) $provider_id,
        'provider_name' => (string) ($signon_provider->fields['name'] ?? ''),
        'provider_type' => (string) ($signon_provider->fields['type'] ?? ''),
        'query_params'  => $_GET,
    ];

    try {
        $callback_context_pretty = json_encode(
            $callback_context,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    } catch (Throwable) {
        $callback_context_pretty = (string) __('Unable to encode callback context.', 'singlesignon');
    }

    $copy_payload_sections[] = sprintf(
        "%s: %s (%s)",
        __('Provider', 'singlesignon'),
        (string) ($signon_provider->fields['name'] ?? ''),
        (string) ($signon_provider->fields['type'] ?? ''),
    );

    $resolved_lines = [(string) __('Resolved fields', 'singlesignon')];
    foreach ($field_types as $field_key => $field_label) {
        $data = $resolved_fields[$field_key] ?? ['value' => null, 'jsonpath' => null, 'source' => null];
        $source = match ($data['source'] ?? null) {
            'provider'       => __('Provider mapping', 'singlesignon'),
            'provider (jwt)' => __('Provider mapping (JWT)', 'singlesignon'),
            'default'        => __('Default mapping', 'singlesignon'),
            'default (jwt)'  => __('Default mapping (JWT)', 'singlesignon'),
            default          => '-',
        };

        $resolved_lines[] = sprintf(
            "- %s [%s]\n  %s: %s\n  %s: %s\n  %s: %s",
            $field_label,
            $field_key,
            __('Value'),
            ($data['value'] ?? null) !== null && $data['value'] !== '' ? (string) $data['value'] : (string) __('Not resolved', 'singlesignon'),
            __('JSONPath', 'singlesignon'),
            (string) ($data['jsonpath'] ?? '-') ?: '-',
            __('Source'),
            $source,
        );
    }
    $copy_payload_sections[] = implode("\n", $resolved_lines);

    $active_lines = [(string) __('Active mappings', 'singlesignon')];
    if ($active_mappings === []) {
        $active_lines[] = (string) __('No active mappings found.', 'singlesignon');
    } else {
        foreach ($active_mappings as $row) {
            $active_lines[] = sprintf(
                "- %s | %s | %s: %s",
                (string) ($row['field_type'] ?? ''),
                (string) ($row['jsonpath'] ?? ''),
                __('Order'),
                (string) ($row['sort_order'] ?? ''),
            );
        }
    }
    $copy_payload_sections[] = implode("\n", $active_lines);

    $default_lines = [(string) __('Default mappings', 'singlesignon')];
    if ($default_mappings === []) {
        $default_lines[] = (string) __('No default mappings found for this provider type.', 'singlesignon');
    } else {
        foreach ($default_mappings as $row) {
            $default_lines[] = sprintf(
                "- %s | %s | %s: %s",
                (string) ($row['field_type'] ?? ''),
                (string) ($row['jsonpath'] ?? ''),
                __('Order'),
                (string) ($row['sort_order'] ?? ''),
            );
        }
    }
    $copy_payload_sections[] = implode("\n", $default_lines);

    $copy_payload_sections[] = (string) __('Resource Owner', 'singlesignon') . "\n" . $resource_owner_pretty;
    $copy_payload_sections[] = (string) __('ID Token (JWT)', 'singlesignon') . "\n" . $id_token_payload_pretty;
    $copy_payload_sections[] = (string) __('Callback context', 'singlesignon') . "\n" . $callback_context_pretty;
    $copy_payload = implode("\n\n", $copy_payload_sections);

    Html::nullHeader("Login", ToolboxPlugin::getBaseURL() . '/index.php');
    echo TemplateRenderer::getInstance()->render('@singlesignon/provider/callback_test_result.html.twig', [
        'provider'                => $signon_provider,
        'field_types'             => $field_types,
        'resolved_fields'         => $resolved_fields,
        'resource_owner_pretty'   => $resource_owner_pretty,
        'id_token_payload_pretty' => $id_token_payload_pretty,
        'callback_context_pretty' => $callback_context_pretty,
        'active_mappings'         => $active_mappings,
        'default_mappings'        => $default_mappings,
        'copy_payload'            => $copy_payload,
    ]);
    Html::nullFooter();
    return;
}

$user_id = 0;
$existing_user_id = Session::getLoginUserID();

if ($existing_user_id) {
    try {
        Session::checkValidSessionId();
        $user_id = (int) $existing_user_id;
    } catch (SessionExpiredException $e) {
        // treat stale session as anonymous and force a fresh login
        $user_id = 0;
    }
}

$query_params = [];

$loginResult = Provider::LOGIN_FAILURE;
$loginResult = $user_id !== 0 ? Provider::LOGIN_SUCCESS : $signon_provider->login();

if ($loginResult !== Provider::LOGIN_FAILURE) {
    // Retrieve redirect stored during authorization step, validating it
    $redirect_target = '';
    if (isset($_SESSION['glpi_singlesignon_redirect'])) {
        $redirect_target = (string) $_SESSION['glpi_singlesignon_redirect'];
        unset($_SESSION['glpi_singlesignon_redirect']);
    } elseif (isset($_GET['redirect'])) {
        $redirect_target = (string) $_GET['redirect'];
    }

    // Only allow internal redirects starting with "/" and encode them safely
    if ($redirect_target !== '') {
        $query_params['redirect'] = $redirect_target;
    }
}

if ($loginResult === Provider::LOGIN_REGISTRATION_PREVIEW) {

    $query_params['provider'] = (int) $provider_id;
    $url_redirect = $CFG_GLPI['root_doc'] . '/plugins/singlesignon/front/register_preview.php?' . http_build_query($query_params);
    $url_redirect = rtrim($url_redirect, '?'); // remove `?` when there is no parameters
    Html::redirect($url_redirect);
}

if ($user_id || $loginResult === Provider::LOGIN_SUCCESS) {

    $user_id = $user_id ?: Session::getLoginUserID();

    if ($user_id) {
        $signon_provider->linkUser($user_id);
    }

    $url_redirect = $CFG_GLPI['root_doc'] . "/index.php?" . http_build_query($query_params);
    $url_redirect = rtrim($url_redirect, '?'); // remove `?` when there is no parameters

    echo TemplateRenderer::getInstance()->render('@singlesignon/login/redirect_opener.html.twig', [
        'header_back_url' => $CFG_GLPI['root_doc'] . '/index.php',
        'url_redirect'    => $url_redirect,
    ]);
    return;
}

$query_params['noAUTO'] = 1;
$url_redirect = $CFG_GLPI['root_doc'] . "/front/logout.php?" . http_build_query($query_params);
$url_redirect = rtrim($url_redirect, '?'); // remove `?` when there is no parameters

$loginError = $signon_provider->getLastLoginError();
if ($loginError !== '') {
    $client_ip = ToolboxPlugin::getClientIp();
    Toolbox::logInFile('access-errors', "$loginError [client: $client_ip]\n", true);
}

// we have done at least a good login? No, we return.
echo TemplateRenderer::getInstance()->render('@singlesignon/login/unauthorized_retry.html.twig', [
    'header_back_url' => $CFG_GLPI['root_doc'] . '/index.php',
    'retry_url'       => $url_redirect,
]);
return;
