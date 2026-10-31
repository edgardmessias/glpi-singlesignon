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

namespace GlpiPlugin\Singlesignon\Tests;

use GlpiPlugin\Singlesignon\Provider;
use Glpi\Application\View\TemplateRenderer;
use Plugin;
use ReflectionProperty;

class ProviderCompatTest extends PluginTestCase
{
    public function testProviderTemplatesCompile(): void
    {
        $twig = TemplateRenderer::getInstance()->getEnvironment();

        foreach ([
            'show_field_mappings_tab.html.twig',
            'show_role_mappings_tab.html.twig',
            'callback_test_result.html.twig',
        ] as $template) {
            $source = $twig->getLoader()->getSourceContext('@singlesignon/provider/' . $template);
            $this->assertNotEmpty($twig->compileSource($source));
        }
    }

    public function testRightnameMatchesRunningGlpi(): void
    {
        $property = new ReflectionProperty(Provider::class, 'rightname');

        $this->assertTrue($property->isStatic());
        $this->assertSame('config', $property->getValue());

        if (version_compare(GLPI_VERSION, '12.0.0', '>=')) {
            $this->assertTrue($property->hasType());
            $this->assertSame('string', $property->getType()->getName());
        } else {
            $this->assertFalse($property->hasType());
        }
    }

    public function testPluginTablesExist(): void
    {
        global $DB;

        $this->assertTrue(Plugin::isPluginActive('singlesignon'));

        foreach (plugin_singlesignon_get_tables() as $table) {
            $this->assertTrue($DB->tableExists($table), $table);
        }
    }
}
