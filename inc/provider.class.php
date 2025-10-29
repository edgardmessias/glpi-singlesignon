<?php

use GlpiPlugin\Singlesignon\Provider;

if (!class_exists(Provider::class, false)) {
    require_once __DIR__ . '/../src/Provider.php';
}

if (!class_exists('PluginSinglesignonProvider', false)) {
    class PluginSinglesignonProvider extends Provider {}
}
