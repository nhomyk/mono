<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use App\Database;

final class CsrfTest extends TestCase
{
    public function testInvalidCsrfPreventsInsert(): void
    {
        $dbFile = sys_get_temp_dir() . '/' . uniqid('mono_test_', true) . '.sqlite';
        putenv('DB_DSN=sqlite:' . $dbFile);
        $pdo = Database::pdoFromEnv();
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');

        // Start fresh session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        // Render GET to initialize session and token
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $_POST = [];
        ob_start();
        include __DIR__ . '/../../public/index.php';
        ob_end_clean();

        // Submit with an invalid token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf'] = 'deadbeef';
        $_POST['value'] = 'bad-csrf';

        ob_start();
        include __DIR__ . '/../../public/index.php';
        $out = ob_get_clean();

        $this->assertStringContainsString('Invalid CSRF token.', $out);
        $count = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
        $this->assertSame(0, $count);
    }
}
