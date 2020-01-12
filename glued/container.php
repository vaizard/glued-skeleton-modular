<?php

use DI\Container;
use Glued\Core\Classes\Auth\Auth;
use Glued\Core\Middleware\TranslatorMiddleware;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Odan\Twig\TwigAssetsExtension;
use Odan\Twig\TwigTranslationExtension;
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

$container->set('settings', function() {
    return require_once(__ROOT__ . '/glued/settings.php');
});

$container->set(LoggerInterface::class, function (Container $c) {
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


$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});


$container->set('routerParser', $app->getRouteCollector()->getRouteParser());


$container->set('view', function (Container $c) {
    $twig = Twig::create(__ROOT__ . '/glued/', $c->get('settings')['twig']);
    $loader = $twig->getLoader();
    $loader->addPath(__ROOT__ . '/public', 'public');
    $twig->addExtension(new TwigAssetsExtension($twig->getEnvironment(), (array)$c->get('settings')['assets']));
    $twig->addExtension(new TwigTranslationExtension());
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
// TODO 
// - classes/users.php
// - sjednotit namespace, ted mam app jako glued/core
//   v users.php bylo glued/core/classes ...
// - pouzit v accountscontrolleru na vypis 1 uzivatele
// - je na to preduelany twig, asi nehotovy accounts.twig
//   do ktereho v accountscontroleru passujeme obsah $users