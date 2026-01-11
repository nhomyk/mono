<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use App\Database;

final class SqlInjectionTest extends TestCase
{
    public function testSqlInjectionPayloadIsStoredAndTableRemains(): void
    {
        putenv('DB_DSN=sqlite::memory:');
        $pdo = Database::pdoFromEnv();
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');

        // Initialize session + CSRF
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $_POST = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        ob_start();
        include __DIR__ . '/../../public/index.php';
        $g = ob_get_clean();
        preg_match('/name="_csrf" value="([0-9a-f]+)"/', $g, $m);
        $token = $m[1] ?? '';

        $payload = "'; DROP TABLE items; --";
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf'] = $token;
        $_POST['value'] = $payload;

        ob_start();
        include __DIR__ . '/../../public/index.php';
        ob_end_clean();

        // Table should still exist and contain the payload as data
        $count = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE value = " . $pdo->quote($payload))->fetchColumn();
        $this->assertSame(1, $count);

        // Verify table exists via sqlite_master
        $exists = (int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='items'")->fetchColumn();
        $this->assertSame(1, $exists);
    }
}
