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

$provider = $signon_provider->prepareProviderInstance(array(), $collaborators);

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

   echo "<script type=\"text/javascript\">
           if (window.opener) {
              window.opener.location='" . $url_redirect . "';
              window.close();
           } else {
              window.location='" . $url_redirect . "';
           }
         </script>";
   exit();
}


try {
   // Try to get an access token (using the authorization code grant)
   $token = $provider->getAccessToken('authorization_code', array(
      'code' => $_GET['code']
   ));

   var_dump($token);

   // Optional: Now you have a token you can look up a users profile data
   // We got an access token, let's now get the user's details
   $user = $provider->getResourceOwner($token);

   // Use these details to create a new profile
   printf('Hello %s!', $user->getNickname());

   var_dump($user->toArray());
} catch (Exception $e) {
   var_dump($e);

   // Failed to get user details
   exit('Oh dear...' . $e->getMessage());
}

// Use this to interact with an API on the users behalf
echo $token->getToken();
