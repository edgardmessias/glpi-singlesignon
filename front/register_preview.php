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

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use GlpiPlugin\Singlesignon\Provider;

include __DIR__ . '/../../../inc/includes.php';

global $CFG_GLPI;

$provider_id = (int) ($_GET['provider'] ?? $_POST['provider'] ?? 0);

$signon_provider = new Provider();
if (!$provider_id || !$signon_provider->getFromDB($provider_id)) {
    $exception = new NotFoundHttpException();
    $exception->setMessageToDisplay(__('Provider not found.', 'singlesignon'));
    throw $exception;
}

if (empty($signon_provider->fields['is_active'])) {
    $exception = new BadRequestHttpException();
    $exception->setMessageToDisplay(__('Provider not active.', 'singlesignon'));
    throw $exception;
}

$pending = Provider::getPendingRegistrationSession();
if (!$pending || (int) $pending['provider_id'] !== $provider_id) {
    Session::addMessageAfterRedirect(
        __s('Registration session expired or invalid. Please sign in again.', 'singlesignon'),
        true,
        ERROR,
    );
    Html::redirect($CFG_GLPI['root_doc'] . '/index.php');
}

$renderRegistrationForm = static function (
    int $provider_id,
    string $login,
    string $firstname,
    string $realname,
    string $email,
    bool $show_queued_messages,
) use ($CFG_GLPI): void {
    Html::nullHeader(__('Confirm registration', 'singlesignon'), $CFG_GLPI['root_doc'] . '/index.php');
    if ($show_queued_messages) {
        Html::displayMessageAfterRedirect();
    }

    $query_params = [
        'noAUTO' => 1,
    ];
    if (isset($_REQUEST['redirect']) && !empty($_REQUEST['redirect'])) {
        $query_params['redirect'] = (string) $_REQUEST['redirect'];
    }

    $cancel_url = $CFG_GLPI['root_doc'] . '/index.php?' . http_build_query($query_params);
    $cancel_url = rtrim($cancel_url, '?'); // remove `?` when there is no parameters

    echo TemplateRenderer::getInstance()->render('@singlesignon/provider/register_preview.html.twig', [
        'form_action' => $CFG_GLPI['root_doc'] . '/plugins/singlesignon/front/register_preview.php?provider=' . $provider_id,
        'provider_id' => $provider_id,
        'login'       => $login,
        'firstname'   => $firstname,
        'realname'    => $realname,
        'email'       => $email,
        'cancel_url'  => $cancel_url,
        'redirect'    => $_REQUEST['redirect'] ?? '',
    ]);
    Html::nullFooter();
};

if (isset($_POST['confirm_register'])) {
    $name = trim((string) ($_POST['name'] ?? ''));
    $firstname = trim((string) ($_POST['firstname'] ?? ''));
    $realname = trim((string) ($_POST['realname'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    $user = $signon_provider->createUserFromOAuthResource(
        ['id' => $pending['remote_id']],
        [
            '__registration_from_preview' => true,
            'name'                          => $name,
            'firstname'                     => $firstname,
            'realname'                      => $realname,
            '_email'                        => $email,
            'remote_id'                     => (string) $pending['remote_id'],
        ],
    );

    if (!$user || !$user->getID()) {
        $renderRegistrationForm(
            $provider_id,
            $name,
            $firstname,
            $realname,
            $email,
            true,
        );
        return;
    }

    Provider::clearPendingRegistrationSession();

    if (!$signon_provider->performGlpiLogin($user)) {
        Html::nullHeader(__('Login', 'singlesignon'), $CFG_GLPI['root_doc'] . '/index.php');
        echo '<div class="center b">' . htmlspecialchars(
            __('User created but login failed. Please try signing in again.', 'singlesignon'),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8',
        ) . '</div>';
        Html::nullFooter();
        return;
    }

    $query_params = [];
    if (isset($_REQUEST['redirect']) && !empty($_REQUEST['redirect'])) {
        $query_params['redirect'] = (string) $_REQUEST['redirect'];
    }

    $url_redirect = $CFG_GLPI['root_doc'] . "/index.php?" . http_build_query($query_params);
    $url_redirect = rtrim($url_redirect, '?'); // remove `?` when there is no parameters

    echo TemplateRenderer::getInstance()->render('@singlesignon/login/redirect_opener.html.twig', [
        'header_back_url' => $CFG_GLPI['root_doc'] . '/index.php',
        'url_redirect'    => $url_redirect,
    ]);
    return;
}

$renderRegistrationForm(
    $provider_id,
    (string) ($pending['login'] ?? ''),
    (string) ($pending['firstname'] ?? ''),
    (string) ($pending['realname'] ?? ''),
    (string) ($pending['email'] ?? ''),
    false,
);
