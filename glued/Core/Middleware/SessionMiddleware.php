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
                'lifetime' => $this->settings['auth']['session']['lifetime'] ?? 0,
                'path'     => $this->settings['auth']['cookie']['path'] ?? $ini_defs['path'],
                'domain'   => $this->settings['auth']['cookie']['domain'] ?? $ini_defs['domain'],
                'secure'   => $this->settings['auth']['cookie']['secure'] ?? $ini_defs['secure'],
                'httponly' => $this->settings['auth']['cookie']['httponly'] ?? $ini_defs['httponly'],
                'samesite' => $this->settings['auth']['cookie']['samesite'] ?? $ini_defs['samesite'],
            ]);
            session_name($this->settings['auth']['session']['cookie'] ?? 'g_sid'); 
            session_start();
        }
        $response = $handler->handle($request);
        session_write_close();
        // TODO bump jwt token expiry too. currently, we are fixed to 15m and thats it, then you need to logout and login again.
        return $response;
    }
}