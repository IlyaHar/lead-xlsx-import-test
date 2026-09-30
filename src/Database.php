<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Database
{
    public static function connect(int $attempts = 15): PDO
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_DATABASE') ?: 'lead_import';
        $username = getenv('DB_USERNAME') ?: 'lead_user';
        $password = getenv('DB_PASSWORD') ?: 'lead_password';
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        do {
            try {
                return new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => true,
                ]);
            } catch (PDOException $exception) {
                if (--$attempts === 0) {
                    throw $exception;
                }
                usleep(200_000);
            }
        } while (true);
    }
}

