<?php

use GlpiPlugin\Singlesignon\ProviderUser;

if (!class_exists(ProviderUser::class, false)) {
    require_once __DIR__ . '/../src/ProviderUser.php';
}

if (!class_exists('PluginSinglesignonProvider_User', false)) {
    class PluginSinglesignonProvider_User extends ProviderUser {}
}
