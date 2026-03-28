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

use Glpi\Application\View\TemplateRenderer;

class LoginRenderer
{
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
                'label'   => sprintf(\__sso('Login with %s'), $row['name']),
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

        echo $renderer->render('@singlesignon/login/buttons.html.twig', [
            'title'         => \__sso('Single Sign-on'),
            'buttons'       => $buttons,
            'classic_label' => \__sso('Use GLPI login form'),
            'classic_url'   => $classicUrl,
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
