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
        foreach ($providers as $row) {
            $query = [];
            if (isset($_REQUEST['redirect']) && $_REQUEST['redirect'] !== '') {
                $query['redirect'] = $_REQUEST['redirect'];
            }

            $url = Toolbox::getCallbackUrl((int)$row['id'], $query);

            if (Toolbox::isDefault($row) && !isset($_GET['noAUTO'])) {
                \Html::redirect($url);
                return;
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
        $renderer->addPath(__DIR__ . '/../templates', 'singlesignon');

        echo $renderer->render('@singlesignon/login/buttons.html.twig', [
            'buttons'       => $buttons,
            'classic_label' => \__sso('Use GLPI login form'),
            'classic_url'   => $classicUrl,
        ]);

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
        echo <<<'JS'
<script>
window.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-singlesignon-popup="true"]');
        if (!trigger) {
            return;
        }
        event.preventDefault();
        const width = 600;
        const height = 800;
        const left = (window.innerWidth / 2) - (width / 2);
        const top = (window.innerHeight / 2) - (height / 2);
        window.open(trigger.getAttribute('href'), 'singlesignon', `width=${width},height=${height},left=${left},top=${top}`);
    });
});
</script>
JS;
    }
}
