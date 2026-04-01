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

use CommonDBTM;
use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Plugin;
use Session;
use Toolbox;
use User;

class Preference extends CommonDBTM
{
    protected static $notable = true;
    public static $rightname = '';

    // Provider data
    public $user_id = null;
    public $providers = [];
    public $providers_users = [];

    public function __construct($user_id = null)
    {
        parent::__construct();

        $this->user_id = $user_id;
    }

    public function loadProviders()
    {
        $signon_provider = new Provider();

        $condition = '`is_active` = 1';
        if (version_compare(GLPI_VERSION, '9.4', '>=')) {
            $condition = [$condition];
        }
        $providers = $signon_provider->find($condition, 'is_default DESC, name ASC');
        $this->providers = array_values($providers);
        usort($this->providers, static function (array $a, array $b): int {
            $a_default = (int) ($a['is_default'] ?? 0);
            $b_default = (int) ($b['is_default'] ?? 0);

            if ($a_default !== $b_default) {
                return $b_default <=> $a_default;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $provider_user = new Provider_User();

        $condition = "`users_id` = {$this->user_id}";
        if (version_compare(GLPI_VERSION, '9.4', '>=')) {
            $condition = [$condition];
        }
        $this->providers_users = $provider_user->find($condition);
    }

    public function update(array $input, $history = true, $options = [])
    {
        if (!isset($input['_remove_sso']) || !is_array($input['_remove_sso'])) {
            return false;
        }

        $ids = $input['_remove_sso'];
        if ($ids === []) {
            return false;
        }

        $provider_user = new Provider_User();
        $condition = "`users_id` = {$this->user_id} AND `id` IN (" . implode(',', $ids) . ")";
        if (version_compare(GLPI_VERSION, '9.4', '>=')) {
            $condition = [$condition];
        }

        $providers_users = $provider_user->find($condition);

        foreach ($providers_users as $pu) {
            $provider_user->delete($pu);
        }

        return true;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        switch (get_class($item)) {
            case 'Preference':
            case 'User':
                return [1 => self::createTabEntry(__('Single Sign-on', 'singlesignon'), 0, null, self::getIcon())];
            default:
                return '';
        }
    }

    public static function getIcon()
    {
        return 'ti ti-user-shield';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        switch (get_class($item)) {
            case 'User':
                $prefer = new self($item->fields['id']);
                $prefer->loadProviders();
                $prefer->showFormUser($item);
                break;
            case 'Preference':
                $prefer = new self(Session::getLoginUserID());
                $prefer->loadProviders();
                $prefer->showFormPreference($item);
                break;
        }
        return true;
    }

    public function showFormUser(CommonGLPI $item)
    {
        if (!User::canView()) {
            return false;
        }
        $canedit = Session::haveRight(User::$rightname, UPDATE);
        if ($canedit) {
            $action = ToolboxPlugin::getBaseURL() . Plugin::getPhpDir('singlesignon', false) . '/front/user.form.php';
            echo '<form name="form" action="' . htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" method="post">';
        }
        echo $this->renderPreferenceFormContent($item, (bool) $canedit);
        if ($canedit) {
            Html::closeForm();
        }

        return true;
    }

    public function showFormPreference(CommonGLPI $item)
    {
        $user = new User();
        if (!$user->can($this->user_id, READ) && ($this->user_id != Session::getLoginUserID())) {
            return false;
        }
        $canedit = $this->user_id == Session::getLoginUserID();
        if ($canedit) {
            $action = Toolbox::getItemTypeFormURL(self::class);
            echo '<form name="form" action="' . htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" method="post">';
        }
        echo $this->renderPreferenceFormContent($item, $canedit);
        if ($canedit) {
            Html::closeForm();
        }

        return true;
    }

    private function renderPreferenceFormContent(CommonGLPI $item, bool $canedit): string
    {
        return TemplateRenderer::getInstance()->render('@singlesignon/preference/form_content.html.twig', [
            'user_id'               => (int) $this->user_id,
            'provider_buttons_html' => $this->buildProviderButtonsHtml($item),
            'linked_accounts'       => $this->buildLinkedAccountsForTwig(),
            'canedit'               => $canedit,
        ]);
    }

    private function buildProviderButtonsHtml(CommonGLPI $item): string
    {
        $redirect = match (get_class($item)) {
            'User'       => $item->getFormURLWithID($this->user_id, true),
            'Preference' => $item->getSearchURL(false),
            default      => '',
        };

        $buttons = [];
        foreach ($this->providers as $p) {
            $url = ToolboxPlugin::getCallbackUrl((int) $p['id'], ['redirect' => $redirect]);

            $buttons[] = [
                'href'    => $url,
                'label'   => sprintf(__('Login with %s', 'singlesignon'), (string) ($p['name'] ?? '')),
                'popup'   => !empty($p['popup']),
                'style'   => $this->buildButtonStyle($p),
                'picture' => !empty($p['picture']) ? ToolboxPlugin::getPictureUrl((string) $p['picture']) : null,
            ];
        }

        if ($buttons === []) {
            return '';
        }

        return TemplateRenderer::getInstance()->render('@singlesignon/preference/provider_buttons.html.twig', [
            'buttons' => $buttons,
        ]);
    }

    private function buildButtonStyle(array $provider): string
    {
        $styles = [];
        if (!empty($provider['bgcolor'])) {
            $styles[] = 'background-color: ' . $provider['bgcolor'];
        }
        if (!empty($provider['color'])) {
            $styles[] = 'color: ' . $provider['color'];
        }

        return implode(';', $styles);
    }

    /**
     * @return list<array{provider_name: string, remote_id: string, pu_id: int}>
     */
    private function buildLinkedAccountsForTwig(): array
    {
        $rows = [];
        foreach ($this->providers_users as $pu) {
            $provider = Provider::getById((int) $pu['plugin_singlesignon_providers_id']);
            if ($provider === false) {
                continue;
            }
            $rows[] = [
                'provider_name' => (string) $provider->fields['name'],
                'remote_id'     => (string) $pu['remote_id'],
                'pu_id'         => (int) $pu['id'],
            ];
        }

        usort($rows, static fn(array $a, array $b): int => strcmp($a['provider_name'], $b['provider_name']));

        return $rows;
    }
}
