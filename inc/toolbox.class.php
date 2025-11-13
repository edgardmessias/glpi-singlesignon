<?php

if (!class_exists(\GlpiPlugin\Singlesignon\Toolbox::class, false)) {
   require_once __DIR__ . '/../src/Toolbox.php';
}

if (!class_exists('PluginSinglesignonToolbox', false)) {
   class PluginSinglesignonToolbox extends \GlpiPlugin\Singlesignon\Toolbox {
   }
}

