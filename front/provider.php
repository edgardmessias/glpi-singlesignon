<?php

include ('../../../inc/includes.php');

Session::checkRight("config", UPDATE);

if ($_SESSION["glpiactiveprofile"]["interface"] == "central") {
   Html::header(__sso('Single Sign-on'), $_SERVER['PHP_SELF'], "config", "pluginsinglesignonprovider", "");
} else {
   Html::helpHeader(__sso('Single Sign-on'), $_SERVER['PHP_SELF']);
}


//checkTypeRight('PluginExampleExample',"r");

Search::show('PluginSinglesignonProvider');

Html::footer();
