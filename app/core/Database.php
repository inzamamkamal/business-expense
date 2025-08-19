<?php
namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    /**
     * Disallow creating instances from outside to enforce singleton.
     */
    private function __construct()
    {
        // Load DB config. Keep sensitive data outside the public directory.
        $configPath = dirname(__DIR__, 2) . '/config/config.php';
        if (!file_exists($configPath)) {
            throw new PDOException('Database configuration not found.');
        }

        $config = require $configPath;
        $host     = $config['host'] ?? 'localhost';
        $dbname   = $config['dbname'] ?? '';
        $user     = $config['user'] ?? '';
        $password = $config['pass'] ?? '';
        $charset  = $config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->connection = new PDO($dsn, $user, $password, $options);
            $this->connection->exec("SET time_zone = '+05:30'");
        } catch (PDOException $e) {
            // Hide sensitive error details from users
            die('Database connection error.');
        }
    }

    public static function getInstance(): Database
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}