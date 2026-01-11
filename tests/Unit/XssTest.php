<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use App\Database;

final class XssTest extends TestCase
{
    public function testOutputIsEscapedToPreventXss(): void
    {
        putenv('DB_DSN=sqlite::memory:');
        $pdo = Database::pdoFromEnv();
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');

        // Initialize session and get CSRF
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $_POST = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        ob_start();
        include __DIR__ . '/../../public/index.php';
        $getHtml = ob_get_clean();

        preg_match('/name="_csrf" value="([0-9a-f]+)"/', $getHtml, $m);
        $this->assertNotEmpty($m[1]);
        $token = $m[1];

        $malicious = '<script>alert("xss")</script>';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['_csrf'] = $token;
        $_POST['value'] = $malicious;

        ob_start();
        include __DIR__ . '/../../public/index.php';
        ob_end_clean();

        // Now render the page to see recent entries
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = [];
        $_POST = [];
        ob_start();
        include __DIR__ . '/../../public/index.php';
        $html = ob_get_clean();

        // The raw script tag should not appear in the HTML
        $this->assertStringNotContainsString($malicious, $html);
        // Escaped form should appear
        $this->assertStringContainsString(htmlspecialchars($malicious), $html);
    }
}
