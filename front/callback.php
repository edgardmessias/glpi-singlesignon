<?php

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
   Html::nullHeader("Login", $CFG_GLPI["root_doc"] . '/index.php');
   echo '<div class="left spaced">';
   echo '<pre>';
   echo "### BEGIN ###\n";
   print_r($signon_provider->getResourceOwner());
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

   $url_redirect = '';


   if (isset($params['redirect'])) {
      $REDIRECT = '?redirect=' . $params['redirect'];
   } elseif (isset($_GET['state']) && is_integer(strpos($_GET['state'], "&redirect="))) {
      $REDIRECT = '?' . substr($_GET['state'],strpos($_GET['state'], "&redirect=")+1);
   }

   if ($_SESSION["glpiactiveprofile"]["interface"] == "helpdesk") {
      if ($_SESSION['glpiactiveprofile']['create_ticket_on_login'] && empty($REDIRECT)) {
         $url_redirect = $CFG_GLPI['root_doc'] . "/front/helpdesk.public.php?create_ticket=1";
      } else {
         $url_redirect = $CFG_GLPI['root_doc'] . "/front/helpdesk.public.php$REDIRECT";
      }
   } else {
      if ($_SESSION['glpiactiveprofile']['create_ticket_on_login'] && empty($REDIRECT)) {
         $url_redirect = $CFG_GLPI['root_doc'] . "/front/ticket.form.php";
      } else {
         $url_redirect = $CFG_GLPI['root_doc'] . "/front/central.php$REDIRECT";
      }
   }

   Html::nullHeader("Login", $CFG_GLPI["root_doc"] . '/index.php');
   echo '<div class="center spaced"><a href="' . $url_redirect . '">' .
   __('Automatic redirection, else click') . '</a>';
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
}

// we have done at least a good login? No, we exit.
Html::nullHeader("Login", $CFG_GLPI["root_doc"] . '/index.php');
echo '<div class="center b">' . __('User not authorized to connect in GLPI') . '<br><br>';
// Logout whit noAUto to manage auto_login with errors
echo '<a href="' . $CFG_GLPI["root_doc"] . '/front/logout.php?noAUTO=1' .
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
