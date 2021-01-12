<?php

use Glpi\Event;

include ('../../../inc/includes.php');

if (!isset($_GET["id"])) {
   $_GET["id"] = "";
}

$provider = new PluginSinglesignonProvider();

if (isset($_POST["add"])) {
   $provider->check(-1, CREATE, $_POST);

   if ($newID = $provider->add($_POST)) {
      Event::log($newID, "singlesignon", 4, "provider",
            sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $_POST["name"]));
      if ($_SESSION['glpibackcreated']) {
         Html::redirect($provider->getLinkURL());
      }
   }
   Html::back();
} else if (isset($_POST["delete"])) {
   $provider->check($_POST["id"], DELETE);
   $provider->delete($_POST);

   Event::log($_POST["id"], "singlesignon", 4, "provider",
         //TRANS: %s is the user login
         sprintf(__('%s deletes an item'), $_SESSION["glpiname"]));

   $provider->redirectToList();
} else if (isset($_POST["restore"])) {
   $provider->check($_POST["id"], DELETE);

   $provider->restore($_POST);
   Event::log($_POST["id"], "singlesignon", 4, "provider",
         //TRANS: %s is the user login
         sprintf(__('%s restores an item'), $_SESSION["glpiname"]));
   $provider->redirectToList();
} else if (isset($_POST["purge"])) {
   $provider->check($_POST["id"], PURGE);

   $provider->delete($_POST, 1);
   Event::log($_POST["id"], "singlesignon", 4, "provider",
         //TRANS: %s is the user login
         sprintf(__('%s purges an item'), $_SESSION["glpiname"]));
   $provider->redirectToList();
} else if (isset($_POST["update"])) {
   $provider->check($_POST["id"], UPDATE);

   $provider->update($_POST);
   Event::log($_POST["id"], "singlesignon", 4, "provider",
         //TRANS: %s is the user login
         sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
   Html::back();
} else {
   if ($_SESSION["glpiactiveprofile"]["interface"] == "central") {
      Html::header(__sso('Single Sign-on'), $_SERVER['PHP_SELF'], "config", "pluginsinglesignonprovider", "");
   } else {
      Html::helpHeader(__sso('Single Sign-on'), $_SERVER['PHP_SELF']);
   }

   $provider->display($_GET);
}


Html::footer();
