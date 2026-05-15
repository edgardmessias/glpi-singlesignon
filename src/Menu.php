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

class Menu
{
    public static function getTypeName($nb = 0)
    {
        return __('Single Sign-on', 'singlesignon');
    }

    public static function getMenuName()
    {
        return static::getTypeName();
    }

    public static function getIcon()
    {
        return Provider::getIcon();
    }

    public static function getSearchURL($full = true): string
    {
        return static::getPluginFrontURL('provider.php', $full);
    }

    /**
     * Get additional menu options and breadcrumb.
     *
     * @return array<string, mixed>
     */
    public static function getAdditionalMenuOptions(): array
    {
        $providerSearchUrl = static::getPluginFrontURL('provider.php', false);
        $providerFormUrl = static::getPluginFrontURL('provider.form.php', false);
        $rulesSearchUrl = RuleSinglesignonCollection::getSearchURL(false);

        $options = [
            'provider' => [
                'title' => Provider::getTypeName(\Session::getPluralNumber()),
                'page'  => $providerSearchUrl,
                'icon'  => Provider::getIcon(),
                'links' => [
                    'search' => $providerSearchUrl,
                ],
            ],
            'rules' => [
                'title' => __('Authorization assignment rules'),
                'page'  => $rulesSearchUrl,
                'icon'  => RuleSinglesignon::getIcon(),
                'links' => [
                    'search' => $rulesSearchUrl,
                ],
            ],
        ];

        if (Provider::canCreate()) {
            $options['provider']['links']['add'] = $providerFormUrl;
        }

        $label = __('Authorization assignment rules');
        $link = "<i class=\"ti ti-user-check\" title=\"$label\""
            . "></i><span class='d-none d-xxl-block'>$label</span>";
        $options['provider']['links'][$link] = $rulesSearchUrl;

        return $options;
    }

    private static function getPluginFrontURL(string $file, bool $full = true): string
    {
        return \Plugin::getWebDir('singlesignon', $full) . '/front/' . $file;
    }
}
