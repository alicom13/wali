<?php

declare(strict_types=1);

namespace Wali\Web;

use PDO;
use PDOException;

/**
 * Class Database
 *
 * Mengelola koneksi database menggunakan PDO.
 *
 * Fitur:
 * - Singleton connection (satu koneksi per request)
 * - Aman & production ready
 * - Mudahkan penggantian driver di masa depan
 */
class Database
{
    protected static ?PDO $instance = null;

    /**
     * Mengembalikan instance koneksi PDO.
     *
     * @throws \RuntimeException jika koneksi gagal
     */
    public static function connect(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }

        return self::$instance;
    }

    /**
     * Membuat koneksi PDO baru.
     */
    protected static function createConnection(): PDO
    {
        $driver = $_ENV['DB_DRIVER'] ?? 'mysql';
        $host   = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $name   = $_ENV['DB_NAME'] ?? '';
        $user   = $_ENV['DB_USER'] ?? '';
        $pass   = $_ENV['DB_PASS'] ?? '';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

        $dsn = "{$driver}:host={$host};dbname={$name};charset={$charset}";

        try {
            return new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

        } catch (PDOException $e) {

            // jangan tampilkan detail error di production
            if (($_ENV['APP_DEBUG'] ?? false) === 'true') {
                throw new \RuntimeException($e->getMessage());
            }

            throw new \RuntimeException('Database connection failed');
        }
    }
}
