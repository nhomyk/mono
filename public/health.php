<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Database.php';

use App\Database;

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');

$status = ['ok' => true];
try {
    $pdo = Database::pdoFromEnv();
    // simple query to validate DB connectivity
    $pdo->query('SELECT 1');
    $status['db'] = 'ok';
} catch (Throwable $e) {
    http_response_code(503);
    $status['ok'] = false;
    $status['db'] = 'error';
    $status['error'] = $e->getMessage();
}

echo json_encode($status);
