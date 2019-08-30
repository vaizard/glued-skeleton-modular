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

use DI\Container;
use Odan\Twig\TwigCompiler;
use Slim\App;
use Slim\Factory\AppFactory;
use Twig\Environment as TwigEnv;
use Slim\Views\Twig;

/** @var App $app */

/*
$app = require __DIR__ . '/../config/bootstrap.php';
*/

define("__ROOT__", realpath(__DIR__ . '/../../..'));
//require_once __DIR__ . '/../vendor/autoload.php';
require_once(__ROOT__ . '/vendor/autoload.php');

// $container = require __ROOT__ . '/container.php';
$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();     // Create App instance

$container->set('settings', function() {
    return require_once(__ROOT__ . '/glued/settings.php');
});
$settings = $container->get('settings');

require_once (__ROOT__ . '/glued/container.php');
require_once (__ROOT__ . '/glued/middleware.php');
require_once (__ROOT__ . '/glued/routes.php');

$cachePath = (string) $settings['twig']['cache']; 
$cachePath = (string) "./private/cache/twig/"; // requires relative uri due to the trim() in TwigCompiler's constructor. Will change '/absolute/path' to 'absolute/path' and warn with path not found.
$twig = $container->get('view');
$compiler = new TwigCompiler($twig->getEnvironment(), $cachePath, true);
$compiler->compile();
echo "Done\n";

/*
compile fails with:

dev@glued:/var/www/html/glued-skeleton# php glued/Core/Bin/parse-twig.php
Parsing: Tutorial/Views/home.twig
PHP Fatal error:  Uncaught Twig\Error\SyntaxError: Unknown "url_for" function. in /srv/varwwwhtml/glued-skeleton/glued/Tutorial/Views/home.twig:23
Stack trace:
#0 /srv/varwwwhtml/glued-skeleton/vendor/twig/twig/src/ExpressionParser.php(451): Twig\ExpressionParser->getFunctionNodeClass('url_for', 23)
#1 /srv/varwwwhtml/glued-skeleton/vendor/twig/twig/src/ExpressionParser.php(235): Twig\ExpressionParser->getFunctionNode('url_for', 23)
#2 /srv/varwwwhtml/glued-skeleton/vendor/twig/twig/src/ExpressionParser.php(175): Twig\ExpressionParser->parsePrimaryExpression()
#3 /srv/varwwwhtml/glued-skeleton/vendor/twig/twig/src/ExpressionParser.php(70): Twig\ExpressionParser->getPrimary()
#4 /srv/varwwwhtml/glued-skeleton/vendor/twig/twig/src/Parser.php(142): Twig\ExpressionParser->parseExpression()
#5 /srv/varwwwhtml/glued-skeleton/vendor/twig/twig/src/TokenParser/BlockTokenParser.php(45): Twig\Parser->subparse(Array, true)
#6 /srv/varwwwhtml/glued-skeleton/vendor/twig/twig/src/Parser.php(185): Twig\TokenParser\BlockTokenParser->parse(Obje in /srv/varwwwhtml/glued-skeleton/glued/Tutorial/Views/home.twig on line 23
*/

/* Original code
https://github.com/odan/slim4-skeleton/blob/master/bin/parse-twig.php
*/