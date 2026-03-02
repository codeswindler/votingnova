<?php
/**
 * Database Connection Handler
 * Provides PDO connection singleton
 */

require_once __DIR__ . '/../config/database.php';

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $config = require __DIR__ . '/../config/database.php';
        
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
        
        try {
            $this->connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please check your configuration.");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function __clone() {
        throw new Exception("Cannot clone database instance");
    }

    public function __wakeup() {
        throw new Exception("Cannot unserialize database instance");
    }
}

// Helper function for easy access
function getDB() {
    return Database::getInstance()->getConnection();
}
