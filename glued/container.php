<?php

use Alcohol\ISO4217;
use DI\Container;
use Glued\Core\Classes\Auth\Auth;
use Glued\Core\Classes\Utils\Utils;
use Glued\Core\Middleware\TranslatorMiddleware;
use Glued\Stor\Classes\Stor;
use Goutte\Client;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Nyholm\Psr7\getParsedBody;
use Odan\Twig\TwigAssetsExtension;
use Odan\Twig\TwigTranslationExtension;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\Config;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Flash\Messages;
use Slim\Interfaces\RouteParserInterface;
use Slim\Views\Twig;
use Symfony\Component\Translation\Formatter\MessageFormatter;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Loader\MoFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use voku\helper\AntiXSS;


$container->set('settings', function() {
    return require_once(__ROOT__ . '/config/settings.php');
});

$container->set('logger', function (Container $c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Logger($settings['name']);
    $processor = new UidProcessor();
    $logger->pushProcessor($processor);
    $handler = new StreamHandler($settings['path'], $settings['level']);
    $logger->pushHandler($handler);
    return $logger;
});


$container->set('mysqli', function (Container $c) {
    $db = $c->get('settings')['db'];
    $mysqli = new mysqli($db['host'], $db['username'], $db['password'], $db['database']);
    $mysqli->set_charset($db['charset']);
    $mysqli->query("SET collation_connection = ".$db['collation']);
    return $mysqli;
});


$container->set('db', function (Container $c) {
    $mysqli = $c->get('mysqli');
    $db = new \MysqliDb($mysqli);
    return $db;
});

$container->set('fscache', function () {
        CacheManager::setDefaultConfig(new Config([
        "path" => '/var/www/html/glued-skeleton/private/cache/psr16',
        "itemDetailedDate" => false
      ]));
      return new Psr16Adapter('files');
});

$container->set('antixss', function () {
    return new AntiXSS();
});


$container->set('goutte', function () {
    return new Goutte\Client();
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$container->set('jsonvalidator', function () {
    return new \Opis\JsonSchema\Validator;

});

$container->set('routerParser', $app->getRouteCollector()->getRouteParser());


$container->set('view', function (Container $c) {
    $twig = Twig::create(__ROOT__ . '/glued/', $c->get('settings')['twig']);
    $loader = $twig->getLoader();
    $loader->addPath(__ROOT__ . '/public', 'public');
    $environment = $twig->getEnvironment();
    // Add twig exensions here
    $twig->addExtension(new TwigAssetsExtension($environment, (array)$c->get('settings')['assets']));
    $twig->addExtension(new TwigTranslationExtension($c->get(Translator::class)));
    $environment->addFilter(new TwigFilter('json_decode', function ($string) {
        return json_decode($string);
    }));
    return $twig;
});


$container->set(Translator::class, static function (Container $container) {
    $settings = $container->get('settings')['locale'];
    $translator = new Translator(
        $settings['locale'],
        new MessageFormatter(new IdentityTranslator()),
        $settings['cache']
    );
    $translator->addLoader('mo', new MoFileLoader());
    __($translator); // Set translator instance
    return $translator;
});


$container->set(TranslatorMiddleware::class, static function (Container $container) {
    $settings = $container->get('settings')['locale'];
    $localPath = $settings['path'];
    $translator = $container->get(Translator::class);
    return new TranslatorMiddleware($translator, $localPath);
});


$container->set('iso4217', function() {
    return new Alcohol\ISO4217();
});

// *************************************************
// GLUED CLASSES ***********************************
// ************************************************* 

// Form-data validation helper (send validation results
// via session to the original form upon failure)
$container->set('validator', function (Container $c) {
   return new Glued\Core\Classes\Validation\Validator;
});


$container->set('auth', function (Container $c) {
    return new Auth($c->get('db'), $c->get('settings'));
});

$container->set('utils', function (Container $c) {
    return new Utils($c->get('db'), $c->get('settings'));
});

// stor trida
$container->set('stor', function (Container $c) {
    return new Stor($c->get('db'));
});


// TODO 
// - classes/users.php
// - sjednotit namespace, ted mam app jako glued/core
//   v users.php bylo glued/core/classes ...
// - pouzit v accountscontrolleru na vypis 1 uzivatele
// - je na to preduelany twig, asi nehotovy accounts.twig
//   do ktereho v accountscontroleru passujeme obsah $users