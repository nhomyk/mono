<?php
declare(strict_types=1);

use App\ApiRouter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Items REST API routes.
 *
 * Tests the full request→handler→database→response flow using
 * an in-memory SQLite database.
 */
class ItemsApiTest extends TestCase
{
    private \PDO $pdo;
    private ApiRouter $router;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)'
        );

        $this->router = new ApiRouter($this->pdo);
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $pdo = $this->pdo;

        // GET /api/items
        $this->router->get('/api/items', function (array $params, array $body) use ($pdo): array {
            $stmt = $pdo->prepare('SELECT id, value, created_at FROM items ORDER BY id DESC LIMIT 20 OFFSET 0');
            $stmt->execute();
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $countStmt = $pdo->query('SELECT COUNT(*) FROM items');
            $total = (int)$countStmt->fetchColumn();
            return [200, ['data' => $items, 'pagination' => ['total' => $total]]];
        });

        // POST /api/items
        $this->router->post('/api/items', function (array $params, array $body) use ($pdo): array {
            $value = trim((string)($body['value'] ?? ''));
            if ($value === '') {
                return [400, ['error' => 'Field "value" is required and cannot be empty']];
            }
            if (strlen($value) > 1000) {
                return [400, ['error' => 'Field "value" must be 1000 characters or fewer']];
            }
            $stmt = $pdo->prepare('INSERT INTO items (value, created_at) VALUES (:v, CURRENT_TIMESTAMP)');
            $stmt->execute([':v' => $value]);
            $id = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare('SELECT id, value, created_at FROM items WHERE id = :id');
            $stmt->execute([':id' => $id]);
            return [201, ['data' => $stmt->fetch(\PDO::FETCH_ASSOC)]];
        });

        // DELETE /api/items/:id
        $this->router->delete('/api/items/:id', function (array $params, array $body) use ($pdo): array {
            $id = (int)($params['id'] ?? 0);
            if ($id <= 0) {
                return [400, ['error' => 'Invalid item ID']];
            }
            $stmt = $pdo->prepare('SELECT id FROM items WHERE id = :id');
            $stmt->execute([':id' => $id]);
            if (!$stmt->fetch()) {
                return [404, ['error' => 'Item not found']];
            }
            $stmt = $pdo->prepare('DELETE FROM items WHERE id = :id');
            $stmt->execute([':id' => $id]);
            return [200, ['deleted' => true, 'id' => $id]];
        });
    }

    // ── POST /api/items ──────────────────────────────────────────────────

    public function testCreateItem(): void
    {
        [$status, $body] = $this->router->dispatch('POST', '/api/items', '{"value":"test item"}');
        $this->assertSame(201, $status);
        $this->assertSame('test item', $body['data']['value']);
        $this->assertArrayHasKey('id', $body['data']);
    }

    public function testCreateItemEmptyValueRejected(): void
    {
        [$status, $body] = $this->router->dispatch('POST', '/api/items', '{"value":""}');
        $this->assertSame(400, $status);
        $this->assertStringContainsString('required', $body['error']);
    }

    public function testCreateItemMissingValueRejected(): void
    {
        [$status, $body] = $this->router->dispatch('POST', '/api/items', '{}');
        $this->assertSame(400, $status);
    }

    public function testCreateItemTooLongRejected(): void
    {
        $longValue = str_repeat('a', 1001);
        [$status, $body] = $this->router->dispatch('POST', '/api/items', json_encode(['value' => $longValue]));
        $this->assertSame(400, $status);
        $this->assertStringContainsString('1000', $body['error']);
    }

    // ── GET /api/items ───────────────────────────────────────────────────

    public function testListItemsEmpty(): void
    {
        [$status, $body] = $this->router->dispatch('GET', '/api/items');
        $this->assertSame(200, $status);
        $this->assertSame([], $body['data']);
        $this->assertSame(0, $body['pagination']['total']);
    }

    public function testListItemsAfterCreate(): void
    {
        $this->router->dispatch('POST', '/api/items', '{"value":"item one"}');
        $this->router->dispatch('POST', '/api/items', '{"value":"item two"}');

        [$status, $body] = $this->router->dispatch('GET', '/api/items');
        $this->assertSame(200, $status);
        $this->assertCount(2, $body['data']);
        $this->assertSame(2, $body['pagination']['total']);
        // Most recent first
        $this->assertSame('item two', $body['data'][0]['value']);
    }

    // ── DELETE /api/items/:id ────────────────────────────────────────────

    public function testDeleteItem(): void
    {
        $this->router->dispatch('POST', '/api/items', '{"value":"to delete"}');

        [$status, $body] = $this->router->dispatch('DELETE', '/api/items/1');
        $this->assertSame(200, $status);
        $this->assertTrue($body['deleted']);

        // Verify gone
        [$status, $body] = $this->router->dispatch('GET', '/api/items');
        $this->assertSame(0, $body['pagination']['total']);
    }

    public function testDeleteNonexistentReturns404(): void
    {
        [$status, $body] = $this->router->dispatch('DELETE', '/api/items/999');
        $this->assertSame(404, $status);
    }

    public function testDeleteInvalidIdReturns400(): void
    {
        [$status, $body] = $this->router->dispatch('DELETE', '/api/items/0');
        $this->assertSame(400, $status);
    }

    // ── SQL Injection resistance ─────────────────────────────────────────

    public function testSqlInjectionInCreateIsHarmless(): void
    {
        $payload = '{"value":"test\'; DROP TABLE items; --"}';
        [$status, $body] = $this->router->dispatch('POST', '/api/items', $payload);
        $this->assertSame(201, $status);

        // Table still exists and has the item
        [$status, $body] = $this->router->dispatch('GET', '/api/items');
        $this->assertSame(200, $status);
        $this->assertCount(1, $body['data']);
    }
}
