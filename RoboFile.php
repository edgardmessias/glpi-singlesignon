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
use Robo\Tasks;
use Symfony\Component\Finder\Finder;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends Tasks
{
    protected $name = "singlesignon";

    protected function getPluginVersionFromSetupFile(): string
    {
        $setupPath = __DIR__ . '/setup.php';
        $content = file_get_contents($setupPath);

        if ($content === false) {
            throw new RuntimeException("Unable to read $setupPath");
        }

        $pattern = "/define\\(\\s*'PLUGIN_SINGLESIGNON_VERSION'\\s*,\\s*'([^']+)'\\s*\\)/";
        if (!preg_match($pattern, $content, $matches)) {
            throw new RuntimeException("Could not find PLUGIN_SINGLESIGNON_VERSION in $setupPath");
        }

        return $matches[1];
    }

    protected function getLocaleFiles()
    {
        $finder = new Finder();
        $finder
           ->files()
           ->name('*.po')
           ->in('locales');

        $files = [];
        foreach ($finder as $file) {
            $files[] = str_replace('\\', '/', $file->getRelativePathname());
        }

        return $files;
    }

    public function compile_locales()
    {
        $this->taskExec('php')->args([
            '../../bin/console',
            'tools:locales:compile',
            "--plugin",
            $this->name,
        ])->run();
    }

    public function update_locales()
    {
        $this->taskExec('php')->args([
            '../../bin/console',
            'tools:locales:extract',
            "--plugin",
            $this->name,
        ])->run();

        $files = $this->getLocaleFiles();

        foreach ($files as $file) {
            $lang = basename($file, ".po");

            if ($lang === "en_GB") {
                continue;
            }

            $this->taskExec('msgmerge')->args([
                "--update",
                "locales/$lang.po",
                "locales/{$this->name}.pot",
                "--lang=$lang",
                "--backup=off",
            ])->run();
        }

        $this->compile_locales();
    }

    public function build()
    {
        $this->_remove(["$this->name.zip", "$this->name.tgz", "$this->name.tar.bz2"]);

        $this->compile_locales();

        $tmpPath = $this->_tmpDir();

        // Exclude hidden files (dotfiles)
        $exclude = glob(__DIR__ . '/.*');

        // Exclude single files by name
        $exclude[] = 'plugin.xml';
        $exclude[] = 'RoboFile.php';

        // Exclude directories
        $exclude[] = 'screenshots';
        $exclude[] = 'tools';
        $exclude[] = 'vendor';
        $exclude[] = 'tests';
        $exclude[] = '.circleci';

        // Exclude test/config files
        $exclude[] = '.atoum.php';
        $exclude[] = '.travis.yml';
        $exclude[] = '.ignore-release';
        $exclude[] = '.stylelintrc.js';
        $exclude[] = '.twig_cs.dist.php';
        $exclude[] = 'rector.php';
        $exclude[] = 'phpstan.neon';
        $exclude[] = '.phpcs.xml';
        $exclude[] = 'phpunit.xml';
        $exclude[] = 'phpunit.xml.dist';
        $exclude[] = 'psalm.xml';
        $exclude[] = 'transifex.yml';

        // Exclude release artifacts
        $exclude[] = "$this->name.zip";
        $exclude[] = "$this->name.tgz";
        $exclude[] = "$this->name.tar.bz2";

        $this->taskCopyDir([__DIR__ => $tmpPath])
           ->exclude($exclude)
           ->run();

        $composer_file = "$tmpPath/composer.json";
        if (file_exists($composer_file)) {
            $hasDep = false;
            try {
                $data = json_decode(file_get_contents($composer_file), true);
                $hasPHPDependency = isset($data['require']) && isset($data['require']['php']);
                $hasDep = isset($data['require']) && count($data['require']) > ($hasPHPDependency ? 1 : 0);
            } catch (Exception $ex) {
                $hasDep = true;
            }

            if ($hasDep) {
                $this->taskComposerInstall()
                   ->workingDir($tmpPath)
                   ->noDev()
                   ->run();
            }
        }

        $this->_remove("$tmpPath/composer.lock");

        // Pack
        $this->taskPack("$this->name.zip")
           ->addDir($this->name, $tmpPath)
           ->run();

        $this->taskPack("$this->name.tgz")
           ->addDir($this->name, $tmpPath)
           ->run();

        $this->taskPack("$this->name.tar.bz2")
           ->addDir($this->name, $tmpPath)
           ->run();
    }

    public function changelog()
    {
        $tag = $this->getPluginVersionFromSetupFile();
        $this->say("Generating CHANGELOG.md with git-cliff tag: $tag");

        $result = $this->taskExec('pnpx')->args([
            'git-cliff@latest',
            '--config',
            'cliff.toml',
            '--tag',
            "v$tag",
            '-o',
            'CHANGELOG.md',
            '--ignore-tags',
            '.*-.*', // Ignore dev versions
        ])->run();

        if (!$result->wasSuccessful()) {
            throw new RuntimeException("Failed to generate changelog using tag $tag");
        }
    }
}
