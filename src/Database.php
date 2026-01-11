<?php
declare(strict_types=1);
namespace App;

use PDO;
use PDOException;

class Database
{
    public static function pdoFromEnv(): PDO
    {
        $dsn = getenv('DB_DSN') ?: ('sqlite:' . __DIR__ . '/../data/db.sqlite');
        $user = getenv('DB_USER') ?: null;
        $pass = getenv('DB_PASS') ?: null;

        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            return new PDO($dsn, $user, $pass, $opts);
        } catch (PDOException $e) {
            throw $e;
        }
    }
}
