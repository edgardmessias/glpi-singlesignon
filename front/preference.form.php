<?php

include ('../../../inc/includes.php');

Session::checkLoginUser();

if (isset($_POST["update"])) {

   $prefer = new PluginSinglesignonPreference(Session::getLoginUserID());
   $prefer->loadProviders();

   $prefer->update($_POST);

   Html::back();
} else {
   Html::back();
}
