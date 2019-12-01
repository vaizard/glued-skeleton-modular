<?php

namespace Glued\Core\Middleware;
use Glued\Core\Middleware\AbstractMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class ValidationErrorsMiddleware extends AbstractMiddleware
{
    public function __invoke(Request $request, Handler $handler)
    {
        if (isset($_SESSION['validation_errors'])) {
        $this->view->getEnvironment()->addGlobal('validation_errors', $_SESSION['validation_errors'] ?? null);
        unset($_SESSION['validation_errors']); }
        return $handler->handle($request);
    }
}