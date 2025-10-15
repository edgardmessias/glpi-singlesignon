<?php

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
        $autoRedirectUrl = null;
        foreach ($providers as $row) {
            $query = [];
            if (isset($_REQUEST['redirect']) && $_REQUEST['redirect'] !== '') {
                $query['redirect'] = $_REQUEST['redirect'];
            }

            $url = Toolbox::getCallbackUrl((int)$row['id'], $query);

            if ($autoRedirectUrl === null && Toolbox::isDefault($row) && !isset($_GET['noAUTO'])) {
                $autoRedirectUrl = $url;
            }

            $buttons[] = [
                'href'    => $url,
                'label'   => sprintf(\__sso('Login with %s'), $row['name']),
                'popup'   => (bool)$row['popup'],
                'style'   => self::buildButtonStyle($row),
                'picture' => $row['picture'] ? Toolbox::getPictureUrl($row['picture']) : null,
            ];
        }

        if (empty($buttons)) {
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

        self::injectPopupScript($autoRedirectUrl);
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

    private static function injectPopupScript(?string $autoRedirectUrl = null): void
    {
        static $injected = false;
        if ($injected) {
            return;
        }

        $injected = true;
        $scriptLines = [];
        $scriptLines[] = 'window.addEventListener("DOMContentLoaded", () => {';

        if ($autoRedirectUrl !== null) {
            $redirectUrl = \Html::convertSpecialchars($autoRedirectUrl);
            $scriptLines[] = "    const autoRedirectUrl = '{$redirectUrl}';";
            $scriptLines[] = "    if (!window.location.search.includes('noAUTO=1')) {";
            $scriptLines[] = "        window.location.assign(autoRedirectUrl);";
            $scriptLines[] = "        return;";
            $scriptLines[] = '    }';
        }

        $scriptLines[] = '    document.addEventListener("click", (event) => {';
        $scriptLines[] = '        const trigger = event.target.closest("[data-singlesignon-popup=\"true\"]");';
        $scriptLines[] = '        if (!trigger) {';
        $scriptLines[] = '            return;';
        $scriptLines[] = '        }';
        $scriptLines[] = '        event.preventDefault();';
        $scriptLines[] = '        const width = 600;';
        $scriptLines[] = '        const height = 800;';
        $scriptLines[] = '        const left = (window.innerWidth / 2) - (width / 2);';
        $scriptLines[] = '        const top = (window.innerHeight / 2) - (height / 2);';
        $scriptLines[] = '        window.open(trigger.getAttribute("href"), "singlesignon", `width=${width},height=${height},left=${left},top=${top}`);';
        $scriptLines[] = '    });';
        $scriptLines[] = '});';

        echo '<script>' . implode("\n", $scriptLines) . '</script>';
    }
}
