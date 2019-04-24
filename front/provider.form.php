<?php

include ('../../../inc/includes.php');

if ($_SESSION["glpiactiveprofile"]["interface"] == "central") {
   Html::header("TITRE", $_SERVER['PHP_SELF'], "plugins", "pluginexampleexample", "");
} else {
   Html::helpHeader("TITRE", $_SERVER['PHP_SELF']);
}

$example = new PluginSinglesignonProvider();
$example->display($_GET);

Html::footer();

