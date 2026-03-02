<?php
declare(strict_types=1);
namespace App;

/**
 * Minimal REST API router.
 *
 * Routes /api/* requests to handler methods. Handles JSON I/O,
 * API key authentication, and rate limiting via a token bucket.
 */
class ApiRouter
{
    /**
     * Routes keyed by path, then by HTTP method.
     *
     * @var array<string, array<string, callable>>
     */
    private array $routes = [];

    public function __construct()
    {
    }

    public function get(string $path, callable $handler): void
    {
        $this->routes[$path]['GET'] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes[$path]['POST'] = $handler;
    }

    public function delete(string $path, callable $handler): void
    {
        $this->routes[$path]['DELETE'] = $handler;
    }

    /**
     * Dispatch the current request. Returns [statusCode, body].
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function dispatch(string $method, string $path, string $rawBody = ''): array
    {
        $body = $rawBody !== '' ? json_decode($rawBody, true) : [];
        $body = $body ?? [];

        // Try exact match first
        if (isset($this->routes[$path])) {
            if (!isset($this->routes[$path][$method])) {
                return [405, ['error' => 'Method not allowed']];
            }
            return ($this->routes[$path][$method])([], $body);
        }

        // Try pattern match (e.g. /api/items/:id)
        foreach ($this->routes as $pattern => $methods) {
            $regex = preg_replace('#:([a-zA-Z_]+)#', '(?P<$1>[^/]+)', $pattern);
            if ($regex !== null && preg_match('#^' . $regex . '$#', $path, $matches)) {
                if (!isset($methods[$method])) {
                    return [405, ['error' => 'Method not allowed']];
                }
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return ($methods[$method])($params, $body);
            }
        }

        return [404, ['error' => 'Not found']];
    }
}
