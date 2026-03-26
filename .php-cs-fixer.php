<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->ignoreVCSIgnored(true);

$config = new Config();

$rules = [
    '@PER-CS2.0'                  => true,
    'trailing_comma_in_multiline' => ['elements' => ['arguments', 'array_destructuring', 'arrays']], // For PHP 7.4 compatibility
];

return $config
    ->setRules($rules)
    ->setFinder($finder)
    ->setUsingCache(false);
