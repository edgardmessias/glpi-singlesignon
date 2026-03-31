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
use Toolbox;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class LoginRenderer
{
    /**
     * {@see Hooks::POST_INIT}: prepend login template override and register Twig globals after all
     * plugins have registered {@see Hooks::DISPLAY_LOGIN}.
     */
    public static function onPostInit(): void
    {
        $env = TemplateRenderer::getInstance()->getEnvironment();

        $dir = Plugin::getPhpDir('singlesignon') . '/templates/override';
        if (is_dir($dir)) {
            $loader = $env->getLoader();
            if ($loader instanceof FilesystemLoader) {
                $loader->prependPath($dir);
            }
        }

        $env->addFunction(new TwigFunction('plugin_singlesignon_render_buttons', fn() => self::renderButtons(), ['is_safe' => ['html']]));

        $env->addFunction(new TwigFunction('plugin_singlesignon_get_login_mode', function () {
            $mode = $_COOKIE['singlesignon_login_mode'] ?? 'oauth';
            return in_array($mode, ['oauth', 'classic'], true) ? $mode : 'oauth';
        }));
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
            Toolbox::deleteDir($dir);
        }
    }

    public static function renderButtons()
    {
        $provider = new Provider();
        $condition = ['`is_active` = 1'];
        $providers = $provider->find($condition, 'is_default DESC, name ASC');

        if (empty($providers)) {
            return '';
        }

        $buttons = [];
        foreach ($providers as $row) {
            $query = [];
            if (isset($_REQUEST['redirect']) && $_REQUEST['redirect'] !== '') {
                $query['redirect'] = $_REQUEST['redirect'];
            }

            $url = ToolboxPlugin::getCallbackUrl((int) $row['id'], $query);

            $buttons[] = [
                'href'    => $url,
                'label'   => sprintf(__('Login with %s', 'singlesignon'), $row['name']),
                'popup'   => (bool) $row['popup'],
                'style'   => self::buildButtonStyle($row),
                'picture' => $row['picture'] ? ToolboxPlugin::getPictureUrl($row['picture']) : null,
            ];
        }

        if (count($buttons) === 0) {
            return '';
        }

        $renderer = TemplateRenderer::getInstance();

        return $renderer->render('@singlesignon/login/buttons.html.twig', [
            'buttons' => $buttons,
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

}
