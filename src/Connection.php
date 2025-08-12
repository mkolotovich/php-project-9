<?php

namespace App;

final class Connection
{
    private static ?Connection $conn = null;

    public function readEnv()
    {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->safeLoad();
        $params = parse_url($_ENV['DATABASE_URL']);
        $port = is_array($params) && array_key_exists('port', $params) ? $params['port'] : 5432;
        return sprintf(
            "pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
            $params['host'],
            $port,
            ltrim($params['path'], '/'),
            $params['user'],
            $params['pass']
        );
    }

    public function connect()
    {
        define('DATABASE_URL', $this->readEnv());
        $pdo = new \PDO(DATABASE_URL);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    public static function get()
    {
        if (null === static::$conn) {
            static::$conn = new self();
        }

        return static::$conn;
    }

    protected function __construct()
    {
    }
}
