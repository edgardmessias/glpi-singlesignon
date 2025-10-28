<?php

use GlpiPlugin\Singlesignon\Preference;

if (!class_exists(Preference::class, false)) {
    require_once __DIR__ . '/../src/Preference.php';
}

if (!class_exists('PluginSinglesignonPreference', false)) {
    class PluginSinglesignonPreference extends Preference {}
}
