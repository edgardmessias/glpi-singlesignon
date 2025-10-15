<?php

/**
 * ---------------------------------------------------------------------
 * SingleSignOn is a plugin which allows to use SSO for auth
 * ---------------------------------------------------------------------
 * Copyright (C) 2022 Edgard
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2021 - 2022 Edgard
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/edgardmessias/glpi-singlesignon/
 * ---------------------------------------------------------------------
 */

// OAuth callback endpoint - uses normal GLPI session to validate CSRF tokens

use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use Glpi\Exception\SessionExpiredException;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('../../../inc/includes.php');

// Session is automatically started by GLPI for non-stateless endpoints

$provider_id = PluginSinglesignonToolbox::getCallbackParameters('provider');

if (!$provider_id) {
   $exception = new BadRequestHttpException();
   $exception->setMessageToDisplay(__sso("Provider not defined."));
   throw $exception;
}

$signon_provider = new PluginSinglesignonProvider();

if (!$signon_provider->getFromDB($provider_id)) {
   $exception = new NotFoundHttpException();
   $exception->setMessageToDisplay(__sso("Provider not found."));
   throw $exception;
}

if (!$signon_provider->fields['is_active']) {
   $exception = new BadRequestHttpException();
   $exception->setMessageToDisplay(__sso("Provider not active."));
   throw $exception;
}

$signon_provider->checkAuthorization();

$test = PluginSinglesignonToolbox::getCallbackParameters('test');

if ($test) {
   $signon_provider->debug = true;
   Html::nullHeader("Login", PluginSinglesignonToolbox::getBaseURL() . '/index.php');
   echo '<div class="left spaced">';
   echo '<pre>';
   echo "### BEGIN ###\n";
   $signon_provider->getResourceOwner();
   echo "### END ###";
   echo '</pre>';
   Html::nullFooter();
   exit();
}

$user_id = 0;
$existing_user_id = Session::getLoginUserID();

if ($existing_user_id) {
   try {
      Session::checkValidSessionId();
      $user_id = (int)$existing_user_id;
   } catch (SessionExpiredException $e) {
      // treat stale session as anonymous and force a fresh login
      $user_id = 0;
   }
}

$REDIRECT = "";

if ($user_id || $signon_provider->login()) {

   $user_id = $user_id ?: Session::getLoginUserID();

   if ($user_id) {
      $signon_provider->linkUser($user_id);
   }

   // Retrieve redirect stored during authorization step
   if (isset($_SESSION['glpi_singlesignon_redirect'])) {
      $REDIRECT = '?redirect=' . $_SESSION['glpi_singlesignon_redirect'];
      unset($_SESSION['glpi_singlesignon_redirect']);
   } else if (isset($_GET['redirect'])) {
      $REDIRECT = '?redirect=' . $_GET['redirect'];
   }

   $url_redirect = '';

   if ($_SESSION["glpiactiveprofile"]["interface"] == "helpdesk") {
      if ($_SESSION['glpiactiveprofile']['create_ticket_on_login'] && empty($REDIRECT)) {
         $url_redirect = PluginSinglesignonToolbox::getBaseURL() . "/front/helpdesk.public.php?create_ticket=1";
      } else {
         $url_redirect = PluginSinglesignonToolbox::getBaseURL() . "/front/helpdesk.public.php$REDIRECT";
      }
   } else {
      if ($_SESSION['glpiactiveprofile']['create_ticket_on_login'] && empty($REDIRECT)) {
         $url_redirect = PluginSinglesignonToolbox::getBaseURL() . "/front/ticket.form.php";
      } else {
         $url_redirect = PluginSinglesignonToolbox::getBaseURL() . "/front/central.php$REDIRECT";
      }
   }

   Html::nullHeader("Login", PluginSinglesignonToolbox::getBaseURL() . '/index.php');
   echo '<div class="center spaced"><a href="' . $url_redirect . '">' .
   __sso('Automatic redirection, else click') . '</a>';
   echo '<script type="text/javascript">
         if (window.opener) {
           window.opener.location="' . $url_redirect . '";
           window.close();
         } else {
           window.location="' . $url_redirect . '";
         }
       </script></div>';
   Html::nullFooter();
   exit();

   // Auth::redirectIfAuthenticated();

}

// we have done at least a good login? No, we exit.
Html::nullHeader("Login", PluginSinglesignonToolbox::getBaseURL() . '/index.php');
echo '<div class="center b">' . __('User not authorized to connect in GLPI') . '<br><br>';
// Logout whit noAUto to manage auto_login with errors
echo '<a href="' . PluginSinglesignonToolbox::getBaseURL() . '/front/logout.php?noAUTO=1' .
str_replace("?", "&", $REDIRECT) . '" class="singlesignon">' . __('Log in again') . '</a></div>';
echo '<script type="text/javascript">
   if (window.opener) {
      $(".singlesignon").on("click", function (e) {
         e.preventDefault();
         window.opener.location = $(this).attr("href");
         window.focus();
         window.close();
      });
   }
</script>';
Html::nullFooter();
exit();
