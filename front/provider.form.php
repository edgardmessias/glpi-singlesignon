<?php

include ('../../../inc/includes.php');

if ($_SESSION["glpiactiveprofile"]["interface"] == "central") {
   Html::header(__sso('Single Sign-on'), $_SERVER['PHP_SELF'], "config", "pluginsinglesignonprovider", "");
} else {
   Html::helpHeader(__sso('Single Sign-on'), $_SERVER['PHP_SELF']);
}

$example = new PluginSinglesignonProvider();
$example->display($_GET);

Html::footer();

