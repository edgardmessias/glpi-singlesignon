<?php

include('../../../inc/includes.php');

Session::checkRight(User::$rightname, UPDATE);

if (isset($_POST["update"]) && isset($_POST["user_id"])) {

   $prefer = new PluginSinglesignonPreference((int) $_POST["user_id"]);
   $prefer->loadProviders();

   $prefer->update($_POST);

   Html::back();
} else {
   Html::back();
}
