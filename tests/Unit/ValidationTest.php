<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use App\Database;

final class ValidationTest extends TestCase
{
    public function testEmptyValueShowsErrorAndDoesNotInsert(): void
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

        // Render GET to get CSRF
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $_POST = [];
        ob_start();
        include __DIR__ . '/../../public/index.php';
        $html = ob_get_clean();

        // Extract token
        preg_match('/name="_csrf" value="([0-9a-f]+)"/', $html, $m);
        $this->assertNotEmpty($m[1]);
        $token = $m[1];

        // Submit empty value
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf'] = $token;
        $_POST['value'] = '';

        ob_start();
        include __DIR__ . '/../../public/index.php';
        $postHtml = ob_get_clean();

        $this->assertStringContainsString('Enter a value.', $postHtml);

        $count = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
        $this->assertSame(0, $count);
    }
}
