<?php

//Disable CSRF token
//define('GLPI_USE_CSRF_CHECK', 0);

include ('../../../inc/includes.php');

$params = array();

if (isset($_SERVER['PATH_INFO'])) {
   $path_info = trim($_SERVER['PATH_INFO'], '/');

   $parts = explode('/', $path_info);

   $key = null;

   foreach ($parts as $part) {
      $part = str_replace('~', '/', $part);
      if ($key === null) {
         $key = $part;
      } else {
         $params[$key] = $part;
         $key = null;
      }
   }
}

if (!isset($params['provider'])) {
   Html::displayErrorAndDie(__sso("Provider not defined."), false);
}

$provider_id = (int) $params['provider'];


$signon_provider = new PluginSinglesignonProvider();

if (!$signon_provider->getFromDB($provider_id)) {
   Html::displayErrorAndDie(__sso("Provider not found."), true);
}

if (!$signon_provider->fields['is_active']) {
   Html::displayErrorAndDie(__sso("Provider not active."), true);
}

$httpClient = new GuzzleHttp\Client(array(
   'verify' => false,
      ));

$collaborators = array(
   'httpClient' => $httpClient,
);

$signon_provider->prepareProviderInstance(array(), $collaborators);

$signon_provider->checkAuthorization();

if ($signon_provider->login()) {
   $url_redirect = '';

   $REDIRECT = "";

   if (isset($params['redirect'])) {
      $REDIRECT = '?redirect=' . $params['redirect'];
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
   echo '<div class="center spaced"><a href="' + $url_redirect + '">' .
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
 str_replace("?", "&", $REDIRECT) . '">' . __('Log in again') . '</a></div>';
Html::nullFooter();
exit();
