<?php

use GlpiPlugin\Singlesignon\Toolbox;

if (!class_exists(Toolbox::class, false)) {
    require_once __DIR__ . '/../src/Toolbox.php';
}

if (!class_exists('PluginSinglesignonToolbox', false)) {
    class PluginSinglesignonToolbox extends Toolbox {}
}
