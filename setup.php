<?php

/**
 * ---------------------------------------------------------------------
 * SingleSignOn is a plugin which allows to use SSO for auth
 * ---------------------------------------------------------------------
 * Copyright (C) 2026 Edgard
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2021 - 2026 Edgard
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/edgardmessias/glpi-singlesignon/
 * ---------------------------------------------------------------------
 */

use Glpi\Http\Firewall;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Singlesignon\LoginRenderer;
use GlpiPlugin\Singlesignon\Preference;
use GlpiPlugin\Singlesignon\Provider;
use GlpiPlugin\Singlesignon\Provider_Field;

use function Safe\define;

$plugin_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($plugin_autoload)) {
    require_once $plugin_autoload;
}

define('PLUGIN_SINGLESIGNON_VERSION', '2.0.1');

// Minimal GLPI version, inclusive
define('PLUGIN_SINGLESIGNON_MIN_GLPI', '11.0.0');
// Maximum GLPI version, exclusive
define('PLUGIN_SINGLESIGNON_MAX_GLPI', '11.0.99');

$folder = basename(__DIR__);

if ($folder !== "singlesignon") {
    $msg = sprintf(__s("Please, rename the plugin folder \"%s\" to \"singlesignon\"", 'singlesignon'), $folder);
    Session::addMessageAfterRedirect($msg, true, ERROR);
}

// GLPI 11: allow the OAuth callback to run without an authenticated session
function plugin_singlesignon_boot(): void
{
    Firewall::addPluginStrategyForLegacyScripts(
        'singlesignon',
        '#^/front/callback\\.php$#',
        Firewall::STRATEGY_NO_CHECK,
    );
    Firewall::addPluginStrategyForLegacyScripts(
        'singlesignon',
        '#^/front/picture\\.send\\.php$#',
        Firewall::STRATEGY_NO_CHECK,
    );
    Firewall::addPluginStrategyForLegacyScripts(
        'singlesignon',
        '#^/front/register_preview\\.php$#',
        Firewall::STRATEGY_NO_CHECK,
    );
}

// Init the hooks of the plugins -Needed
function plugin_init_singlesignon()
{
    global $PLUGIN_HOOKS;


    Plugin::registerClass(Preference::class, [
        'addtabon' => ['Preference', 'User'],
    ]);

    Plugin::registerClass(Provider::class);
    Plugin::registerClass(Provider_Field::class);

    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['singlesignon'] = 'front/provider.php';
    $PLUGIN_HOOKS[Hooks::POST_INIT]['singlesignon'] = [LoginRenderer::class, 'onPostInit'];
    $PLUGIN_HOOKS[Hooks::MENU_TOADD]['singlesignon'] = [
        'config'  => Provider::class,
    ];
    $PLUGIN_HOOKS[Hooks::ADD_CSS]['singlesignon'] = ['css/preference.css'];
    $PLUGIN_HOOKS[Hooks::ADD_CSS_ANONYMOUS_PAGE]['singlesignon'] = ['css/login.css'];
}

// Get the name and the version of the plugin - Needed
function plugin_version_singlesignon()
{
    return [
        'name'           => __('Single Sign-on', 'singlesignon'),
        'version'        => PLUGIN_SINGLESIGNON_VERSION,
        'author'         => 'Edgard Lorraine Messias',
        'license'        => 'GPLv3+',
        'homepage'       => 'https://github.com/edgardmessias/glpi-singlesignon',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_SINGLESIGNON_MIN_GLPI,
                'max' => PLUGIN_SINGLESIGNON_MAX_GLPI,
            ],
        ],
    ];
}

// Optional : check prerequisites before install : may print errors or add to message after redirect
function plugin_singlesignon_check_prerequisites()
{
    if (version_compare(GLPI_VERSION, PLUGIN_SINGLESIGNON_MIN_GLPI, '<')) {
        echo htmlspecialchars(__("This plugin requires GLPI >= 11.0.0", 'singlesignon'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return false;
    }

    if (version_compare(GLPI_VERSION, PLUGIN_SINGLESIGNON_MAX_GLPI, '>=')) {
        echo htmlspecialchars(__("This plugin is not yet validated for this GLPI version", 'singlesignon'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return false;
    }

    if (version_compare(PHP_VERSION, '8.2', '<')) {
        echo htmlspecialchars(__("This plugin requires PHP >= 8.2", 'singlesignon'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return false;
    }

    return true;
}

function plugin_singlesignon_check_config()
{
    return true;
}
