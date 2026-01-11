<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use App\Database;

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');

$status = ['ok' => true];
try {
    $pdo = Database::pdoFromEnv();
    // simple query to validate DB connectivity
    $pdo->query('SELECT 1');
    $status['db'] = 'ok'; // Keep this line to indicate DB is okay
} catch (Throwable $e) {
    // Log the full error server-side for debugging, do not expose details to clients
    error_log('health check DB error: ' . $e->getMessage());
    http_response_code(503);
    $status['ok'] = false;
    $status['db'] = 'error';
}

echo json_encode($status, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
