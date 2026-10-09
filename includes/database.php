<?php

/*
|--------------------------------------------------------------------------
| BookEase Database Class
|--------------------------------------------------------------------------
*/

class Database
{
    private $host;
    private $database;
    private $username;
    private $password;
    private $charset;

    private $connection = null;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        $this->host =
            defined('DB_HOST')
                ? DB_HOST
                : '127.0.0.1';

        $this->database =
            defined('DB_NAME')
                ? DB_NAME
                : 'booking_system';

        $this->username =
            defined('DB_USER')
                ? DB_USER
                : 'root';

        $this->password =
            defined('DB_PASS')
                ? DB_PASS
                : '';

        $this->charset =
            defined('DB_CHARSET')
                ? DB_CHARSET
                : 'utf8mb4';
    }

    /*
    |--------------------------------------------------------------------------
    | Get Connection
    |--------------------------------------------------------------------------
    */

    public function getConnection()
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $dsn =
            'mysql:host=' .
            $this->host .
            ';dbname=' .
            $this->database .
            ';charset=' .
            $this->charset;

        try {

            $this->connection = new PDO(
                $dsn,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE =>
                        PDO::ERRMODE_EXCEPTION,

                    PDO::ATTR_DEFAULT_FETCH_MODE =>
                        PDO::FETCH_ASSOC,

                    PDO::ATTR_EMULATE_PREPARES =>
                        false
                ]
            );

            return $this->connection;

        } catch (PDOException $e) {

            throw new RuntimeException(
                'Database connection error: ' .
                $e->getMessage(),
                0,
                $e
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Close Connection
    |--------------------------------------------------------------------------
    */

    public function close()
    {
        $this->connection = null;
    }
}