<?php

declare(strict_types=1);

namespace GlpiPlugin\Singlesignon;

use Glpi\Application\View\TemplateRenderer;

class LoginRenderer
{
    public static function display(): void
    {
        $provider = new Provider();
        $condition = ['`is_active` = 1', '`is_deleted` = 0'];
        $providers = $provider->find($condition, 'is_default DESC, name ASC');

        if (empty($providers)) {
            return;
        }

        $buttons = [];
        $autoLoginAllowed = self::isAutoLoginAllowed();

        $autoLoginUrl = null;
        $autoLoginPopup = null;

        foreach ($providers as $row) {
            $query = [];
            if (isset($_REQUEST['redirect']) && $_REQUEST['redirect'] !== '') {
                $query['redirect'] = $_REQUEST['redirect'];
            }

            $url = Toolbox::getCallbackUrl((int) $row['id'], $query);

            if ($autoLoginAllowed && self::shouldAutoLoginWithProvider($row) && $autoLoginUrl === null) {
                $autoLoginUrl = $url;
            } elseif ($autoLoginAllowed && self::shouldAutoPopupWithProvider($row) && $autoLoginPopup === null) {
                $autoLoginPopup = $url;
            }

            $buttons[] = [
                'href'    => $url,
                'label'   => sprintf(\__sso('Login with %s'), $row['name']),
                'popup'   => (bool) $row['popup'],
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

        if ($autoLoginUrl !== null) {
            self::injectAutoLoginScript($autoLoginUrl);
        } elseif ($autoLoginPopup !== null) {
            self::injectAutoPopupScript($autoLoginPopup);
        }

        self::injectPopupScript();
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

    private static function injectPopupScript(): void
    {
        static $injected = false;
        if ($injected) {
            return;
        }

        $injected = true;
        $scriptLines = [];

        $scriptLines[] = '(function() {';
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
        $scriptLines[] = '})();';

        echo '<script>' . implode("\n", $scriptLines) . '</script>';
    }

    private static function shouldAutoLoginWithProvider(array $row): bool
    {
        if (empty($row['is_default'])) {
            return false;
        }

        if (!empty($row['popup'])) {
            return false;
        }

        return true;
    }

    private static function shouldAutoPopupWithProvider(array $row): bool
    {
        if (empty($row['is_default'])) {
            return false;
        }

        if (empty($row['popup'])) {
            return false;
        }

        return true;
    }

    private static function isAutoLoginAllowed(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            return false;
        }

        $noAuto = $_GET['noAUTO'] ?? null;

        if ($noAuto !== null && $noAuto !== '0') {
            return false;
        }

        return true;
    }

    private static function injectAutoLoginScript(string $url): void
    {
        static $injected = false;
        if ($injected) {
            return;
        }

        $injected = true;

        $encodedUrl = json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        if ($encodedUrl === false) {
            return;
        }

        echo '<script>window.location.href = ' . $encodedUrl . ';</script>';
    }

    private static function injectAutoPopupScript(string $url): void
    {
        static $injected = false;
        if ($injected) {
            return;
        }

        $injected = true;

        $encodedUrl = json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        if ($encodedUrl === false) {
            return;
        }

        $script = <<<JS
(function() {
    const width = 600;
    const height = 800;
    const left = (window.innerWidth / 2) - (width / 2);
    const top = (window.innerHeight / 2) - (height / 2);
    const popup = window.open({$encodedUrl}, "singlesignon", `width=\${width},height=\${height},left=\${left},top=\${top}`);
    if (!popup) {
        window.location.href = {$encodedUrl};
    }
})();
JS;

        echo '<script>' . $script . '</script>';
    }
}
