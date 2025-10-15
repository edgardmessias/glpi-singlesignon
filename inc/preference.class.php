<?php

if (!class_exists(\GlpiPlugin\Singlesignon\Preference::class, false)) {
   require_once __DIR__ . '/../src/Preference.php';
}

if (!class_exists('PluginSinglesignonPreference', false)) {
   class PluginSinglesignonPreference extends \GlpiPlugin\Singlesignon\Preference {
   }
}

