<?php

if (!class_exists(\Glpi\Plugin\Singlesignon\Preference::class, false)) {
   require_once __DIR__ . '/../src/Preference.php';
}

if (!class_exists('PluginSinglesignonPreference', false)) {
   class PluginSinglesignonPreference extends \Glpi\Plugin\Singlesignon\Preference {
   }
}

