<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use App\Database;

final class DatabaseTest extends TestCase
{
    public function testPdoCanBeCreatedUsingSqliteMemory(): void
    {
        putenv('DB_DSN=sqlite::memory:');
        $pdo = Database::pdoFromEnv();
        $this->assertInstanceOf(PDO::class, $pdo);
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, value TEXT)');
        $stmt = $pdo->prepare('INSERT INTO items (value) VALUES (:v)');
        $stmt->execute([':v' => 'x']);
        $this->assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM items')->fetchColumn());
    }
}
