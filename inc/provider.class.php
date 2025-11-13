<?php

if (!class_exists(\GlpiPlugin\Singlesignon\Provider::class, false)) {
   require_once __DIR__ . '/../src/Provider.php';
}

if (!class_exists('PluginSinglesignonProvider', false)) {
   class PluginSinglesignonProvider extends \GlpiPlugin\Singlesignon\Provider {
   }
}

