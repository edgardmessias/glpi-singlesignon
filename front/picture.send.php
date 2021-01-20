<?php
include('../../../inc/includes.php');

$provider = new PluginSinglesignonProvider();
$path = false;

if (isset($_GET['id'])) { // docid for document
   if (!$provider->getFromDB($_GET['id'])) {
      Html::displayErrorAndDie(__('Unknown file'), true);
   }

   $path = $provider->fields['picture'];
} else if (isset($_GET['path'])) {
   $path = $_GET['path'];
} else {
   Html::displayErrorAndDie(__('Invalid filename'), true);
}

$path = GLPI_PLUGIN_DOC_DIR . "/singlesignon/" . $path;

if (!file_exists($path)) {
   Html::displayErrorAndDie(__('File not found'), true); // Not found
}

Toolbox::sendFile($path, "logo.png");
