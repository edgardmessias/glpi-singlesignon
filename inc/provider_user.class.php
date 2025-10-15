<?php

if (!class_exists(\Glpi\Plugin\Singlesignon\ProviderUser::class, false)) {
   require_once __DIR__ . '/../src/ProviderUser.php';
}

if (!class_exists('PluginSinglesignonProvider_User', false)) {
   class PluginSinglesignonProvider_User extends \Glpi\Plugin\Singlesignon\ProviderUser {
   }
}

