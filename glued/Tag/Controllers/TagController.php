<?php

declare(strict_types=1);

namespace Glued\Tag\Controllers;

use Carbon\Carbon;
use \Opis\JsonSchema\Loaders\File as JSL;
use Glued\Core\Classes\Json\JsonResponseBuilder;
use Glued\Core\Controllers\AbstractTwigController;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\Config;
use Phpfastcache\Helper\Psr16Adapter;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Respect\Validation\Validator as v;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpInternalServerErrorException;


class TagController extends AbstractTwigController
{

    public function tag_create_get_api(Request $request, Response $response, array $args = []): Response {
      $count = (int) $args['count'] ?? 0;
      $data = [];
      $builder = new JsonResponseBuilder('tag.create', 1);
      $payload = $builder->withData($data)->withCode(200)->build();
      return $response->withJson($payload);
    }


    public function tag_doc_get_api(Request $request, Response $response, array $args = []): Response {
      $tagid = $args['tagid'] ?? null;
      $tagpw = $args['tagpw'] ?? null;
      $data = [];
      $builder = new JsonResponseBuilder('store.sellers', 1);
      $payload = $builder->withData($data)->withCode(200)->build();
      return $response->withJson($payload);
    }


    public function tag_doc_get_app(Request $request, Response $response, array $args = []): Response {
      $tagid = $args['tagid'] ?? null;
      $tagpw = $args['tagpw'] ?? null;
      $data = [];
      return $response->withStatus(302)->withHeader('Location', 'https://'.$this->settings['glued']['hostname'].$this->routerParser->pathFor('stor.serve.file'));
    }

}

