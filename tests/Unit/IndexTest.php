<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use App\Database;

final class IndexTest extends TestCase
{
    public function testFormSubmissionSavesToDatabase(): void
    {
        putenv('DB_DSN=sqlite::memory:');

        // Ensure table exists
        $pdo = Database::pdoFromEnv();
        $pdo->exec('CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');

        // Simulate GET to obtain CSRF token
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $_POST = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }

        ob_start();
        include __DIR__ . '/../../public/index.php';
        $htmlGet = ob_get_clean();

        // Extract CSRF token from the form
        $matches = [];
        $this->assertMatchesRegularExpression('/name="_csrf" value="([0-9a-f]+)"/', $htmlGet, 'CSRF token not found in GET output');
        preg_match('/name="_csrf" value="([0-9a-f]+)"/', $htmlGet, $matches);
        $this->assertNotEmpty($matches[1]);
        $token = $matches[1];

        // Simulate POST with the token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['value'] = 'unit-test-value';
        $_POST['_csrf'] = $token;

        ob_start();
        include __DIR__ . '/../../public/index.php';
        $htmlPost = ob_get_clean();

        $this->assertStringContainsString('Saved.', $htmlPost);

        // Verify DB contains the value
        $count = (int)$pdo->query("SELECT COUNT(*) FROM items WHERE value = 'unit-test-value'")->fetchColumn();
        $this->assertSame(1, $count);
    }
}
