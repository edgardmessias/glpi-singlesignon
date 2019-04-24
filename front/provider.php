<?php

include ('../../../inc/includes.php');

if ($_SESSION["glpiactiveprofile"]["interface"] == "central") {
   Html::header("TITRE", $_SERVER['PHP_SELF'], "config", "pluginsinglesignonprovider", "");
} else {
   Html::helpHeader("TITRE", $_SERVER['PHP_SELF']);
}


//checkTypeRight('PluginExampleExample',"r");

Search::show('PluginSinglesignonProvider');

Html::footer();

