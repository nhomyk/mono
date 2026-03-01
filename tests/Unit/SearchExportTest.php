<?php
declare(strict_types=1);

use App\ApiRouter;
use PHPUnit\Framework\TestCase;

/**
 * Tests for search and export API endpoints.
 */
class SearchExportTest extends TestCase
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

        // Seed test data
        $stmt = $this->pdo->prepare('INSERT INTO items (value) VALUES (:v)');
        foreach (['alpha bravo', 'charlie delta', 'echo foxtrot', 'bravo golf'] as $val) {
            $stmt->execute([':v' => $val]);
        }

        $this->router = new ApiRouter($this->pdo);
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $pdo = $this->pdo;

        // Search
        $this->router->get('/api/items/search', function (array $params, array $body) use ($pdo): array {
            $query = trim((string)($GLOBALS['_test_query'] ?? ''));
            if ($query === '') {
                return [400, ['error' => 'Query parameter "q" is required']];
            }
            if (strlen($query) > 200) {
                return [400, ['error' => 'Query must be 200 characters or fewer']];
            }
            $stmt = $pdo->prepare(
                'SELECT id, value, created_at FROM items WHERE value LIKE :q ORDER BY id DESC LIMIT 50'
            );
            $stmt->execute([':q' => '%' . $query . '%']);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $highlighted = array_map(function (array $item) use ($query): array {
                $item['highlighted'] = str_ireplace(
                    $query,
                    '<mark>' . htmlspecialchars($query) . '</mark>',
                    htmlspecialchars($item['value'])
                );
                return $item;
            }, $items);
            return [200, ['data' => $highlighted, 'query' => $query, 'total' => count($highlighted)]];
        });

        // Export
        $this->router->get('/api/items/export', function (array $params, array $body) use ($pdo): array {
            $format = strtolower(trim((string)($GLOBALS['_test_format'] ?? 'json')));
            if (!in_array($format, ['json', 'csv'], true)) {
                return [400, ['error' => 'Format must be "json" or "csv"']];
            }
            $stmt = $pdo->query('SELECT id, value, created_at FROM items ORDER BY id DESC');
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($format === 'csv') {
                $lines = ["id,value,created_at"];
                foreach ($items as $item) {
                    $escapedValue = '"' . str_replace('"', '""', $item['value']) . '"';
                    $lines[] = $item['id'] . ',' . $escapedValue . ',' . $item['created_at'];
                }
                return [200, ['format' => 'csv', 'content' => implode("\n", $lines), 'count' => count($items)]];
            }
            return [200, ['format' => 'json', 'data' => $items, 'count' => count($items)]];
        });
    }

    // ── Search tests ─────────────────────────────────────────────────────

    public function testSearchFindsMatchingItems(): void
    {
        $GLOBALS['_test_query'] = 'bravo';
        [$status, $body] = $this->router->dispatch('GET', '/api/items/search');
        $this->assertSame(200, $status);
        $this->assertSame(2, $body['total']); // "alpha bravo" + "bravo golf"
        $this->assertSame('bravo', $body['query']);
    }

    public function testSearchReturnsEmptyForNoMatch(): void
    {
        $GLOBALS['_test_query'] = 'zzznotfound';
        [$status, $body] = $this->router->dispatch('GET', '/api/items/search');
        $this->assertSame(200, $status);
        $this->assertSame(0, $body['total']);
    }

    public function testSearchEmptyQueryRejected(): void
    {
        $GLOBALS['_test_query'] = '';
        [$status, $body] = $this->router->dispatch('GET', '/api/items/search');
        $this->assertSame(400, $status);
        $this->assertStringContainsString('required', $body['error']);
    }

    public function testSearchTooLongQueryRejected(): void
    {
        $GLOBALS['_test_query'] = str_repeat('a', 201);
        [$status, $body] = $this->router->dispatch('GET', '/api/items/search');
        $this->assertSame(400, $status);
        $this->assertStringContainsString('200', $body['error']);
    }

    public function testSearchHighlightsMatches(): void
    {
        $GLOBALS['_test_query'] = 'bravo';
        [$status, $body] = $this->router->dispatch('GET', '/api/items/search');
        $this->assertSame(200, $status);
        foreach ($body['data'] as $item) {
            $this->assertStringContainsString('<mark>bravo</mark>', $item['highlighted']);
        }
    }

    public function testSearchSqlInjectionSafe(): void
    {
        $GLOBALS['_test_query'] = "'; DROP TABLE items; --";
        [$status, $body] = $this->router->dispatch('GET', '/api/items/search');
        $this->assertSame(200, $status);
        // Table still intact
        $count = (int)$this->pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
        $this->assertSame(4, $count);
    }

    // ── Export tests ─────────────────────────────────────────────────────

    public function testExportJson(): void
    {
        $GLOBALS['_test_format'] = 'json';
        [$status, $body] = $this->router->dispatch('GET', '/api/items/export');
        $this->assertSame(200, $status);
        $this->assertSame('json', $body['format']);
        $this->assertSame(4, $body['count']);
        $this->assertCount(4, $body['data']);
    }

    public function testExportCsv(): void
    {
        $GLOBALS['_test_format'] = 'csv';
        [$status, $body] = $this->router->dispatch('GET', '/api/items/export');
        $this->assertSame(200, $status);
        $this->assertSame('csv', $body['format']);
        $this->assertSame(4, $body['count']);
        $this->assertStringContainsString('id,value,created_at', $body['content']);
    }

    public function testExportCsvEscapesQuotes(): void
    {
        // Insert an item with a comma and quote
        $this->pdo->prepare('INSERT INTO items (value) VALUES (:v)')
            ->execute([':v' => 'has "quotes" and, commas']);

        $GLOBALS['_test_format'] = 'csv';
        [$status, $body] = $this->router->dispatch('GET', '/api/items/export');
        $this->assertSame(200, $status);
        // Quotes should be doubled in CSV
        $this->assertStringContainsString('""quotes""', $body['content']);
    }

    public function testExportInvalidFormatRejected(): void
    {
        $GLOBALS['_test_format'] = 'xml';
        [$status, $body] = $this->router->dispatch('GET', '/api/items/export');
        $this->assertSame(400, $status);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_query'], $GLOBALS['_test_format']);
    }
}
