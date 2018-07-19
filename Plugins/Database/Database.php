<?php
/**
 * This file is part of openPanfu, a project that imitates the Flex remoting
 * and gameservers of Panfu.
 *
 * @category Utility
 * @author Altro50 <altro50@msn.com>
 */
class Database
{
    private static $host = '127.0.0.1';
    private static $database = 'openpanfu';
    private static $user = 'openPanfu';
    private static $pass = 'password';
    private static $charset = 'utf8mb4';
    private static $pdo = null;

    public static function connect()
    {
        $dsn = "mysql:host=" . Database::$host . ";dbname=" . Database::$database . ";charset=" . Database::$charset;
        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        Database::$pdo = new PDO($dsn, Database::$user, Database::$pass, $opt);
    }

    public static function getPDO()
    {
        if(Database::$pdo === null) {
            Database::connect();
        }
        if(Database::$pdo !== null) {
            return Database::$pdo;
        }
        return null;
    }
}