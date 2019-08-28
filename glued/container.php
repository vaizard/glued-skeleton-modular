<?php

use DI\Container;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Odan\Twig\TwigAssetsExtension;
use Odan\Twig\TwigTranslationExtension;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Flash\Messages;
use Slim\Interfaces\RouteParserInterface;
use Slim\Views\Twig;
use Twig\Loader\FilesystemLoader;
use Symfony\Component\Translation\Formatter\MessageFormatter; // Twig-translation
use Symfony\Component\Translation\IdentityTranslator; // Twig-translation
use Symfony\Component\Translation\Loader\MoFileLoader; // Twig-translation
use Symfony\Component\Translation\Translator; // Twig-translation
use App\Middleware\TranslatorMiddleware; // Twig-translation

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


//$container->set('routerParser', $app->getRouteCollector()->getRouteParser());


$container->set('view', function (Container $c) {
    $twig = new Twig(__ROOT__ . '/glued/', $c->get('settings')['twig']);
    $loader = $twig->getLoader();
    $loader->addPath(__ROOT__ . '/public', 'public');
    $twig->addExtension(new TwigAssetsExtension($twig->getEnvironment(), (array)$c->get('settings')['assets']));
    $twig->addExtension(new TwigTranslationExtension()); // GETTEXT
    return $twig;
});


// GETTEXT
$container->set(Translator::class, static function (Container $container) {
    $settings = $container->get('settings')['locale'];
    $translator = new Translator(
        $settings['locale'],
        new MessageFormatter(new IdentityTranslator()),
        $settings['cache']
    );
    $translator->addLoader('mo', new MoFileLoader());
    // Set translator instance
    __($translator);
    return $translator;
}); // removed ->addArgument($container);

$container->set(TranslatorMiddleware::class, static function (Container $container) {
    $settings = $container->get('settings')['locale'];
    $localPath = $settings['path'];
    $translator = $container->get(Translator::class);
    return new TranslatorMiddleware($translator, $localPath);
}); // removed ->addArgument($container);