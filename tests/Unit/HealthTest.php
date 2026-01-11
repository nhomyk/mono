<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use App\Database;

final class HealthTest extends TestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        putenv('DB_DSN=sqlite::memory:');
        // No table needed; Database::pdoFromEnv should work

        // Capture health output
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        include __DIR__ . '/../../public/health.php';
        $json = ob_get_clean();

        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('ok', $data);
        $this->assertTrue($data['ok']);
        $this->assertSame('ok', $data['db']);
    }
}
