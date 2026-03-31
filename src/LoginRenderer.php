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

declare(strict_types=1);

namespace GlpiPlugin\Singlesignon;

use Plugin;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Kernel\Kernel;
use Glpi\Plugin\Hooks;
use Twig\Loader\FilesystemLoader;

class LoginRenderer
{
    /**
     * Plugin-specific login output hook (does not use {@see Hooks::DISPLAY_LOGIN}).
     * Other plugins keep using {@see Hooks::DISPLAY_LOGIN} without interference from this layout.
     */
    public const HOOK_LOGIN_BLOCK = 'plugin_singlesignon_login_block';

    /**
     * {@see Hooks::POST_INIT}: prepend login template override and register Twig globals after all
     * plugins have registered {@see Hooks::DISPLAY_LOGIN}.
     */
    public static function onPostInit(): void
    {
        $env = TemplateRenderer::getInstance()->getEnvironment();

        $dir = Plugin::getPhpDir('singlesignon') . '/templates-override';
        if (is_dir($dir)) {
            $loader = $env->getLoader();
            if ($loader instanceof FilesystemLoader) {
                $loader->prependPath($dir);
            }
        }

        global $PLUGIN_HOOKS;
        $other_display_login = isset($PLUGIN_HOOKS[Hooks::DISPLAY_LOGIN])
            && is_array($PLUGIN_HOOKS[Hooks::DISPLAY_LOGIN])
            && count($PLUGIN_HOOKS[Hooks::DISPLAY_LOGIN]) > 0;

        $env->addGlobal('plugin_singlesignon_has_active_provider', self::hasActiveProviders());
        $env->addGlobal('plugin_singlesignon_other_display_login', $other_display_login);
    }

    public static function hasActiveProviders(): bool
    {
        $provider = new Provider();

        return count($provider->find(['`is_active` = 1'])) > 0;
    }

    /**
     * Delete compiled Twig cache ({@see TemplateRenderer} uses {@see Kernel::getCacheRootDir()}/templates).
     */
    public static function clearTwigTemplateCache(): void
    {
        $dir = Kernel::getCacheRootDir() . '/templates';
        if (is_dir($dir)) {
            \Toolbox::deleteDir($dir);
        }
    }

    public static function display(): void
    {
        $provider = new Provider();
        $condition = ['`is_active` = 1'];
        $providers = $provider->find($condition, 'is_default DESC, name ASC');

        if (empty($providers)) {
            return;
        }

        $buttons = [];
        foreach ($providers as $row) {
            $query = [];
            if (isset($_REQUEST['redirect']) && $_REQUEST['redirect'] !== '') {
                $query['redirect'] = $_REQUEST['redirect'];
            }

            $url = Toolbox::getCallbackUrl((int) $row['id'], $query);

            $buttons[] = [
                'href'    => $url,
                'label'   => sprintf(__('Login with %s', 'singlesignon'), $row['name']),
                'popup'   => (bool) $row['popup'],
                'style'   => self::buildButtonStyle($row),
                'picture' => $row['picture'] ? Toolbox::getPictureUrl($row['picture']) : null,
            ];
        }

        if (count($buttons) === 0) {
            return;
        }

        $classicUrl = self::buildClassicLoginUrl();

        $renderer = TemplateRenderer::getInstance();

        $showClassicLink = !isset($_GET['noAUTO']) || (string) $_GET['noAUTO'] !== '1';

        echo $renderer->render('@singlesignon/login/buttons.html.twig', [
            'buttons'           => $buttons,
            'classic_url'       => $classicUrl,
            'show_classic_link' => $showClassicLink,
        ]);
    }

    private static function buildButtonStyle(array $row): string
    {
        $styles = [];
        if (!empty($row['bgcolor'])) {
            $styles[] = 'background-color: ' . $row['bgcolor'];
        }
        if (!empty($row['color'])) {
            $styles[] = 'color: ' . $row['color'];
        }

        return implode(';', $styles);
    }

    private static function buildClassicLoginUrl(): string
    {
        $url = Toolbox::getCurrentURL();
        $params = ['noAUTO' => 1];
        if (isset($_REQUEST['redirect']) && $_REQUEST['redirect'] !== '') {
            $params['redirect'] = $_REQUEST['redirect'];
        }

        return $url . '?' . http_build_query($params);
    }
}
