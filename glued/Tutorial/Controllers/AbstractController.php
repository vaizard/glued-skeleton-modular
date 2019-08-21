<?php

declare(strict_types=1);

namespace Tutorial\Controllers;

use Odan\Twig\TwigAssetsExtension;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;
use Slim\Middleware\RoutingMiddleware;
use Slim\Routing\RouteParser;
use Slim\Interfaces\RouteParserInterface;

abstract class AbstractController
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * AbstractController constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Render the template and write it to the response.
     *
     * @param Response $response
     * @param string   $template
     * @param array    $renderData
     *
     * @return Response
     */
    protected function render(Response $response, string $template, array $renderData = []): Response
    {
        /** @var Twig $view */
        $view = $this->container->get('view');

        // ODAN TESTS START ----------------------------------<
        $loader = $view->getLoader();
        $settings['public'] = '';
        $loader->addPath($settings['public'], 'public');

        //$basePath = '';
        //$basePath2 = rtrim(str_ireplace('index.php', '', $container->get('request')->getUri()->getBasePath()), '/');
        //die($basePath2);
    
        //$view->addExtension(new \Slim\Views\TwigExtension(new \Slim\Interfaces\RouteParserInterface(), $basePath));
        //$view->addExtension(new \Slim\Views\TwigExtension($this->app->getRouteCollector()->getRouteParser(), $this->app->getBasePath()));
        
        //TwigExtension requires:
        //$this->routeParser = $routeParser;
        //$this->uri = $uri;
        //$this->basePath = $basePath;

        $assets = [ 'assets' ];
        // Add the Assets extension to Twig
        //$view->addExtension(new \Odan\Twig\TwigAssetsExtension($view->getEnvironment(), $assets));
       //$app->$twig->addExtension(new \Odan\Twig\TwigAssetsExtension($twig, $options));

        // ODAN TESTS STOPS ----------------------------------<
        return $view->render($response, $template, $renderData);
        echo "haha";
    }
}
