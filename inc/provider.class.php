<?php

if (!class_exists(\Glpi\Plugin\Singlesignon\Provider::class, false)) {
   require_once __DIR__ . '/../src/Provider.php';
}

if (!class_exists('PluginSinglesignonProvider', false)) {
   class PluginSinglesignonProvider extends \Glpi\Plugin\Singlesignon\Provider {
   }
}

