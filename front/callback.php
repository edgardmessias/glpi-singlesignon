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

//Disable CSRF token
define('GLPI_USE_CSRF_CHECK', 0);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('../../../inc/includes.php');

$provider_id = PluginSinglesignonToolbox::getCallbackParameters('provider');

if (!$provider_id) {
   Html::displayErrorAndDie(__sso("Provider not defined."), false);
}

$signon_provider = new PluginSinglesignonProvider();

if (!$signon_provider->getFromDB($provider_id)) {
   Html::displayErrorAndDie(__sso("Provider not found."), true);
}

if (!$signon_provider->fields['is_active']) {
   Html::displayErrorAndDie(__sso("Provider not active."), true);
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

$user_id = Session::getLoginUserID();

$REDIRECT = "";

if ($user_id || $signon_provider->login()) {

   $user_id = $user_id ?: Session::getLoginUserID();

   if ($user_id) {
      $signon_provider->linkUser($user_id);
   }

   $params = PluginSinglesignonToolbox::getCallbackParameters('q');

   if (isset($params['redirect'])) {
      $REDIRECT = '?redirect=' . $params['redirect'];
   } else if (isset($_GET['state']) && is_integer(strpos($_GET['state'], ";redirect="))) {
      $REDIRECT = '?' . substr($_GET['state'], strpos($_GET['state'], ";redirect=") + 1);
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
