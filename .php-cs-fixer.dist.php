<?php

/**
 * ---------------------------------------------------------------------
 * SingleSignOn is a plugin which allows to use SSO for auth
 * ---------------------------------------------------------------------
 * Copyright (C) 2025
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
 */

$finder = PhpCsFixer\Finder::create()
   ->in(__DIR__)
   ->exclude([
      'vendor',
      'node_modules',
      'screenshots',
      'tools',
   ]);

return (new PhpCsFixer\Config())
   ->setUnsupportedPhpVersionAllowed(true)
   ->setCacheFile('.php-cs-fixer.cache')
   ->setRiskyAllowed(true)
   ->setRules([
      '@PER-CS3.0' => true,
      '@PHP84Migration' => true,
      'fully_qualified_strict_types' => ['import_symbols' => true],
      'ordered_imports' => ['imports_order' => ['class', 'const', 'function']],
      'no_unused_imports' => true,
      'heredoc_indentation' => false,
      'new_expression_parentheses' => false,
   ])
   ->setFinder($finder);
