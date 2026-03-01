<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\ApiAuth;
use App\ApiRouter;
use App\Database;
use App\RateLimiter;

// ── Setup ────────────────────────────────────────────────────────────────────
$pdo = Database::pdoFromEnv();
$router = new ApiRouter($pdo);
$limiter = new RateLimiter(60, 60);

// ── Auth check ───────────────────────────────────────────────────────────────
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$auth = ApiAuth::validate($authHeader);
if (!$auth['valid']) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => $auth['error']]);
    exit;
}

// ── Rate limit check ─────────────────────────────────────────────────────────
$clientKey = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$limit = $limiter->check($clientKey);
if (!$limit['allowed']) {
    http_response_code(429);
    header('Content-Type: application/json');
    header('Retry-After: 60');
    echo json_encode(['error' => 'Rate limit exceeded', 'retry_after' => 60]);
    exit;
}

// ── Routes ───────────────────────────────────────────────────────────────────

// GET /api/items — list items with pagination
$router->get('/api/items', function (array $params, array $body) use ($pdo): array {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $countStmt = $pdo->query('SELECT COUNT(*) FROM items');
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT id, value, created_at FROM items ORDER BY id DESC LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    return [200, [
        'data' => $items,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => (int)ceil($total / $perPage),
        ],
    ]];
});

// POST /api/items — create item
$router->post('/api/items', function (array $params, array $body) use ($pdo): array {
    $value = trim((string)($body['value'] ?? ''));

    if ($value === '') {
        return [400, ['error' => 'Field "value" is required and cannot be empty']];
    }

    if (strlen($value) > 1000) {
        return [400, ['error' => 'Field "value" must be 1000 characters or fewer']];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO items (value, created_at) VALUES (:v, CURRENT_TIMESTAMP)'
    );
    $stmt->execute([':v' => $value]);

    $id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT id, value, created_at FROM items WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch(\PDO::FETCH_ASSOC);

    return [201, ['data' => $item]];
});

// DELETE /api/items/:id — delete item
$router->delete('/api/items/:id', function (array $params, array $body) use ($pdo): array {
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

// GET /api/items/search — full-text search with highlighting
$router->get('/api/items/search', function (array $params, array $body) use ($pdo): array {
    $query = trim((string)($_GET['q'] ?? ''));

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

    // Add highlighting
    $highlighted = array_map(function (array $item) use ($query): array {
        $item['highlighted'] = str_ireplace(
            $query,
            '<mark>' . htmlspecialchars($query) . '</mark>',
            htmlspecialchars($item['value'])
        );
        return $item;
    }, $items);

    return [200, [
        'data' => $highlighted,
        'query' => $query,
        'total' => count($highlighted),
    ]];
});

// GET /api/items/export — export items as CSV or JSON
$router->get('/api/items/export', function (array $params, array $body) use ($pdo): array {
    $format = strtolower(trim((string)($_GET['format'] ?? 'json')));

    if (!in_array($format, ['json', 'csv'], true)) {
        return [400, ['error' => 'Format must be "json" or "csv"']];
    }

    $stmt = $pdo->query('SELECT id, value, created_at FROM items ORDER BY id DESC');
    $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if ($format === 'csv') {
        $lines = ["id,value,created_at"];
        foreach ($items as $item) {
            // Escape CSV values (double-quote fields containing commas/quotes/newlines)
            $escapedValue = '"' . str_replace('"', '""', $item['value']) . '"';
            $lines[] = $item['id'] . ',' . $escapedValue . ',' . $item['created_at'];
        }
        // Return CSV as a data field (actual Content-Type header set in dispatch)
        return [200, ['format' => 'csv', 'content' => implode("\n", $lines), 'count' => count($items)]];
    }

    return [200, ['format' => 'json', 'data' => $items, 'count' => count($items)]];
});

// ── Dispatch ─────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rawBody = file_get_contents('php://input') ?: '';

[$status, $responseBody] = $router->dispatch($method, $path, $rawBody);

http_response_code($status);
header('Content-Type: application/json');
header('X-RateLimit-Remaining: ' . $limit['remaining']);
echo json_encode($responseBody, JSON_UNESCAPED_UNICODE);
