<?php

/**
 * ---------------------------------------------------------------------
 * SingleSignOn is a plugin which allows to use SSO for auth
 * ---------------------------------------------------------------------
 * Copyright (C) 2022 Edgard
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @copyright Copyright © 2021 - 2022 Edgard
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/edgardmessias/glpi-singlesignon/
 * ---------------------------------------------------------------------
 */

use Symfony\Component\Finder\Finder;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks {

   protected $name = "singlesignon";
   protected $issues = "https://github.com/edgardmessias/glpi-singlesignon/issues";

   protected function getLocaleFiles() {
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

   public function compile_locales() {
      $files = $this->getLocaleFiles();

      foreach ($files as $file) {
         $lang = basename($file, ".po");

         $this->taskExec('msgfmt')->args([
            "locales/$lang.po",
            "-o",
            "locales/$lang.mo",
         ])->run();
      }
   }

   public function update_locales() {
      $finder = new Finder();
      $finder
         ->files()
         ->name('*.php')
         ->in(__DIR__)
         ->exclude([
            'vendor'
         ])
         ->sortByName();

      if (!$finder->hasResults()) {
         return false;
      }

      $args = [];

      foreach ($finder as $file) {
         $args[] = str_replace('\\', '/', $file->getRelativePathname());
      }

      $args[] = '-D';
      $args[] = '.';
      $args[] = '-o';
      $args[] = "locales/{$this->name}.pot";
      $args[] = '-L';
      $args[] = 'PHP';
      $args[] = '--add-comments=TRANS';
      $args[] = '--from-code=UTF-8';
      $args[] = '--force-po';
      $args[] = '--keyword=__sso';
      $args[] = "--package-name={$this->name}";

      if ($this->issues) {
         $args[] = "--msgid-bugs-address={$this->issues}";
      }

      try {
         $content = file_get_contents('setup.php');
         $name = 'PLUGIN_' . strtoupper($this->name) . '_VERSION';
         preg_match("/'$name',\s*'([\w\.]+)'/", $content, $matches);
         $args[] = '--package-version=' . $matches[1];
      } catch (\Exception $ex) {
         echo $ex->getMessage();
      }

      putenv("LANG=C");

      $this->taskExec('xgettext')->args($args)->run();

      $this->taskReplaceInFile("locales/{$this->name}.pot")
         ->from('CHARSET')
         ->to('UTF-8')
         ->run();

      $this->taskExec('msginit')->args([
         '--no-translator',
         '-i',
         "locales/{$this->name}.pot",
         '-l',
         'en_GB.UTF8',
         '-o',
         'locales/en_GB.po',
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

   public function build() {
      $this->_remove(["$this->name.zip", "$this->name.tgz"]);

      $this->compile_locales();

      $tmpPath = $this->_tmpDir();

      $exclude = glob(__DIR__ . '/.*');
      $exclude[] = 'plugin.xml';
      $exclude[] = 'RoboFile.php';
      $exclude[] = 'screenshots';
      $exclude[] = 'tools';
      $exclude[] = 'vendor';
      $exclude[] = "$this->name.zip";
      $exclude[] = "$this->name.tgz";

      $this->taskCopyDir([__DIR__ => $tmpPath])
         ->exclude($exclude)
         ->run();

      $composer_file = "$tmpPath/composer.json";
      if (file_exists($composer_file)) {
         $hasDep = false;
         try {
            $data = json_decode(file_get_contents($composer_file), true);
            $hasDep = isset($data['require']) && count($data['require']) > 0;
         } catch (\Exception $ex) {
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
   }
}
