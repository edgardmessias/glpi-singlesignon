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

    /** @var string GitHub repository slug (owner/name) for Shields badges */
    protected string $repository = 'edgardmessias/glpi-singlesignon';

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
            '-', // Ignore dev versions
        ])->run();

        if (!$result->wasSuccessful()) {
            throw new RuntimeException("Failed to generate changelog using tag $tag");
        }
    }

    /**
     * Normalize a semver for git tags (v prefix).
     */
    protected function normalizeVersionTag(string $version): string
    {
        $version = trim($version);

        return str_starts_with($version, 'v') ? $version : 'v' . $version;
    }

    /**
     * Run git-cliff for a release body (strip, ignore prerelease tags). Returns markdown stdout.
     *
     * @param string|null $range Git range when set (e.g. v1.4.0..v2.0.0); otherwise use --tag with $tagForSingleTag
     * @param string|null $tagForSingleTag Tag for single-tag mode when $range is empty; if empty, uses setup.php version
     * @return string Markdown output from git-cliff
     */
    protected function generateReleaseChangelogBody(?string $tagForSingleTag = null, ?string $range = null): string
    {
        $args = [
            'git-cliff@latest',
            '--config', 'cliff.toml',
            '--strip', 'all',
            '--ignore-tags', '-',
        ];

        if ($tagForSingleTag !== null && $tagForSingleTag !== '') {
            $args[] = '--tag';
            $args[] = $this->normalizeVersionTag($tagForSingleTag);
        }

        if ($range !== null && $range !== '') {
            $args[] = $range;
        }

        $result = $this->taskExec('pnpx')
            ->args($args)
            ->dir(__DIR__)
            ->timeout(300)
            ->printOutput(false)
            ->printMetadata(false)
            ->run();

        if (!$result->wasSuccessful()) {
            throw new RuntimeException(
                'git-cliff failed: ' . trim($result->getMessage()),
            );
        }

        return $result->getMessage();
    }

    /**
     * Print git-cliff release body to stdout.
     * With no args: uses plugin version from setup.php and --tag.
     * With one arg: tag for --tag (e.g. v2.0.0 or 2.0.0).
     * With two args: second is a git range (e.g. v1.4.0..v2.0.0); range mode ignores the first arg for git-cliff (first is kept for symmetry with generate_prepare_notes).
     *
     * @param string $tag   Tag for single-tag mode when $range is empty; if empty, uses setup.php version
     * @param string $range Optional git-cliff range (two refs with ..)
     */
    public function generate_changelog_body(string $tag = '', string $range = ''): void
    {
        $tag = $tag !== '' ? $tag : $this->getPluginVersionFromSetupFile();
        $body = $this->generateReleaseChangelogBody(
            $tag,
            $range,
        );
        fwrite(STDOUT, $body);
        if ($body !== '' && !str_ends_with($body, "\n")) {
            fwrite(STDOUT, "\n");
        }
    }

    /**
     * @return array{0: string, 1: string} [min, max]
     */
    protected function getGlpiMinMaxFromSetup(): array
    {
        $setupPath = __DIR__ . '/setup.php';
        $content = file_get_contents($setupPath);

        if ($content === false) {
            throw new RuntimeException("Unable to read $setupPath");
        }

        $minPattern = "/define\\(\\s*'PLUGIN_SINGLESIGNON_MIN_GLPI'\\s*,\\s*'([^']+)'\\s*\\)/";
        $maxPattern = "/define\\(\\s*'PLUGIN_SINGLESIGNON_MAX_GLPI'\\s*,\\s*'([^']+)'\\s*\\)/";
        if (!preg_match($minPattern, $content, $minMatch) || !preg_match($maxPattern, $content, $maxMatch)) {
            throw new RuntimeException("Could not find PLUGIN_SINGLESIGNON_MIN_GLPI / MAX_GLPI in $setupPath");
        }

        return [$minMatch[1], $maxMatch[1]];
    }

    protected function formatGlpiShortRange(string $minGlpi, string $maxGlpi): string
    {
        if (preg_match('/^(\d+)\.(\d+)\.0$/', $minGlpi, $m) && $maxGlpi === "{$m[1]}.{$m[2]}.99") {
            return "{$m[1]}.{$m[2]}.x";
        }

        return "{$minGlpi} - {$maxGlpi}";
    }

    protected function shieldStaticV1(string $label, string $message, string $color = 'informational'): string
    {
        $query = http_build_query(
            [
                'label' => $label,
                'message' => $message,
                'color' => $color,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        return 'https://img.shields.io/static/v1?' . $query;
    }

    /**
     * Print GitHub release notes to stdout: Shields badges + git-cliff body (via captureReleaseChangelogBody).
     *
     * @param string $tag   Release tag for download badge (e.g. v2.0.0)
     * @param string $range Optional git-cliff range (e.g. v1.4.0..v2.0.0). Empty uses --tag with $tag
     */
    public function generate_prepare_notes(string $tag = '', string $range = ''): void
    {
        if ($tag === '' || $tag === '0') {
            $tag = 'v' . $this->getPluginVersionFromSetupFile();
        }

        $body = $this->generateReleaseChangelogBody(
            $tag,
            $range,
        );
        if ($body === '') {
            throw new RuntimeException('git-cliff returned an empty release body.');
        }

        [$minGlpi, $maxGlpi] = $this->getGlpiMinMaxFromSetup();
        $glpiShort = $this->formatGlpiShortRange($minGlpi, $maxGlpi);
        $glpiRange = sprintf('>=%s <%s', $minGlpi, $maxGlpi);

        $lines = [
            sprintf('![GitHub Downloads](https://img.shields.io/github/downloads/%s/%s/total)', $this->repository, $tag),
            sprintf('![GLPI compatible](%s)', $this->shieldStaticV1('GLPI', $glpiShort, 'informational')),
            sprintf('![GLPI version range](%s)', $this->shieldStaticV1('GLPI version', $glpiRange, 'blue')),
            '',
            $body,
        ];

        $markdown = implode("\n", $lines);
        fwrite(STDOUT, $markdown);
        if ($markdown !== '' && !str_ends_with($markdown, "\n")) {
            fwrite(STDOUT, "\n");
        }
    }
}
