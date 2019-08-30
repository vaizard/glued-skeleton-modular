<?php
//
// Twig to PHP compiler.
//
// Usage: php glued/Core/Bin/parse-twig.php
//
// Run `apt install poedit` or equivalent on your distro
// Start Poedit and:
// - either open the file ./private/locale/*.po (or equivalent to `$settings['locale']['path']`)
// - or click "New" to add a new po file to ./private/locale
// Open menu: Catalog > Properties > Source Path
// Add source path: ./private/cache/twig (or equivalent to `$settings['twig']['cache']`) - yep, translations are generated from cache files (possibly to get the php code equivalent)
//
// Open tab: Sources keywords
// Add keyword: __
// Click 'Ok' to store the settings
//
// Click button 'Update form source' to extract the template strings.
// Translate the text and save the file.
// Run this script.
//

use Odan\Twig\TwigCompiler;
use Slim\App;
use Twig\Environment as Twig;
/** @var App $app */
$app = require __DIR__ . '/../config/bootstrap.php';
$settings = $app->getContainer()->get('settings')['twig'];
$templatePath = (string)$settings['path'];
$cachePath = (string)$settings['cache_path'];
$twig = $app->getContainer()->get(Twig::class);
$compiler = new TwigCompiler($twig, $cachePath);
$compiler->compile();