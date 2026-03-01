<?php
declare(strict_types=1);

use App\ApiRouter;
use PHPUnit\Framework\TestCase;

class ApiRouterTest extends TestCase
{
    private \PDO $pdo;
    private ApiRouter $router;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
        $this->router = new ApiRouter($this->pdo);
    }

    public function testGetRouteReturnsHandlerResult(): void
    {
        $this->router->get('/api/test', function (array $params, array $body): array {
            return [200, ['ok' => true]];
        });

        [$status, $body] = $this->router->dispatch('GET', '/api/test');
        $this->assertSame(200, $status);
        $this->assertTrue($body['ok']);
    }

    public function testPostRouteReceivesBody(): void
    {
        $this->router->post('/api/test', function (array $params, array $body): array {
            return [201, ['received' => $body['name'] ?? '']];
        });

        [$status, $body] = $this->router->dispatch('POST', '/api/test', '{"name":"hello"}');
        $this->assertSame(201, $status);
        $this->assertSame('hello', $body['received']);
    }

    public function testDeleteRouteWithPathParam(): void
    {
        $this->router->delete('/api/items/:id', function (array $params, array $body): array {
            return [200, ['deleted_id' => (int)$params['id']]];
        });

        [$status, $body] = $this->router->dispatch('DELETE', '/api/items/42');
        $this->assertSame(200, $status);
        $this->assertSame(42, $body['deleted_id']);
    }

    public function testUnknownRouteReturns404(): void
    {
        [$status, $body] = $this->router->dispatch('GET', '/api/nope');
        $this->assertSame(404, $status);
        $this->assertArrayHasKey('error', $body);
    }

    public function testWrongMethodReturns405(): void
    {
        $this->router->get('/api/test', function (array $params, array $body): array {
            return [200, ['ok' => true]];
        });

        [$status, $body] = $this->router->dispatch('POST', '/api/test');
        $this->assertSame(405, $status);
    }
}
