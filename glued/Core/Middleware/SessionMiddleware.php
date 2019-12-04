<?php
namespace Glued\Core\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
/**
 * Session middleware.
 */
final class SessionMiddleware implements MiddlewareInterface
{

    protected $settings;
    public function __construct($settings) 
    {
        $this->settings = $settings;
    }
    
    /**
     * Invoke middleware.
     *
     * @param ServerRequestInterface $request The request
     * @param RequestHandlerInterface $handler The handler
     *
     * @return ResponseInterface The response
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {

            if (ini_get('session.use_cookies')) {
                $ini_defs = session_get_cookie_params();
            }
            session_set_cookie_params([
                'lifetime' => $this->settings['glued']['session_cookie_lifetime'],
                'path' => $ini_defs['path'],
                'domain' => $ini_defs['domain'],
                'secure' => $this->settings['glued']['session_cookie_secure'],
                'httponly' => $this->settings['glued']['session_cookie_httponly'],
                'samesite' => $this->settings['glued']['session_cookie_samesite'],
            ]);
            session_start();
        }
        $response = $handler->handle($request);
        session_write_close();
        return $response;
    }
}