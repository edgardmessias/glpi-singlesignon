<?php

if (!class_exists(\Glpi\Plugin\Singlesignon\Toolbox::class, false)) {
   require_once __DIR__ . '/../src/Toolbox.php';
}

if (!class_exists('PluginSinglesignonToolbox', false)) {
   class PluginSinglesignonToolbox extends \Glpi\Plugin\Singlesignon\Toolbox {
   }
}

