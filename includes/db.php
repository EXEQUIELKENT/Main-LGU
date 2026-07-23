<?php
require_once __DIR__ . '/env.php';

function mainLguDb(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = envValue('DB_HOST', 'localhost');
        $name = envValue('DB_NAME', 'main_lgu');
        $user = envValue('DB_USER', 'root');
        $pass = envValue('DB_PASS', '');

        $pdo = new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    return $pdo;
}
