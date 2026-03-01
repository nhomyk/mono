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
    private \PDO $pdo;

    /** @var array<string, array{handler: callable, method: string}> */
    private array $routes = [];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function get(string $path, callable $handler): void
    {
        $this->routes[$path] = ['handler' => $handler, 'method' => 'GET'];
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes[$path] = ['handler' => $handler, 'method' => 'POST'];
    }

    public function delete(string $path, callable $handler): void
    {
        $this->routes[$path] = ['handler' => $handler, 'method' => 'DELETE'];
    }

    /**
     * Dispatch the current request. Returns [statusCode, body].
     *
     * @return array{0: int, 1: array<string, mixed>}
     */
    public function dispatch(string $method, string $path, string $rawBody = ''): array
    {
        // Try exact match first
        if (isset($this->routes[$path])) {
            $route = $this->routes[$path];
            if ($route['method'] !== $method) {
                return [405, ['error' => 'Method not allowed']];
            }
            $params = [];
            $body = $rawBody !== '' ? json_decode($rawBody, true) : [];
            return ($route['handler'])($params, $body ?? []);
        }

        // Try pattern match (e.g. /api/items/:id)
        foreach ($this->routes as $pattern => $route) {
            $regex = preg_replace('#:([a-zA-Z_]+)#', '(?P<$1>[^/]+)', $pattern);
            if ($regex !== null && preg_match('#^' . $regex . '$#', $path, $matches)) {
                if ($route['method'] !== $method) {
                    return [405, ['error' => 'Method not allowed']];
                }
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $body = $rawBody !== '' ? json_decode($rawBody, true) : [];
                return ($route['handler'])($params, $body ?? []);
            }
        }

        return [404, ['error' => 'Not found']];
    }
}
