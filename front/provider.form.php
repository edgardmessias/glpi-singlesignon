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

use Glpi\Event;

include ('../../../inc/includes.php');

Session::checkRight("config", UPDATE);

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
