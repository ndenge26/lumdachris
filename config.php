<?php
declare(strict_types=1);

$databaseHost = getenv('localhost') ?: '127.0.0.1';
$databaseName = getenv('lumchris');
$databaseUser = getenv('root');
$databasePassword = getenv('');

if ($databaseName === false || $databaseName === '' || $databaseUser === false || $databaseUser === '' || $databasePassword === false) {
    throw new RuntimeException('Database configuration is incomplete. Set DB_NAME, DB_USER, and DB_PASS.');
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $databaseHost, $databaseName);

try {
    $pdo = new PDO($dsn, $databaseUser, $databasePassword, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    error_log('Database connection failed: ' . $exception->getMessage());
    throw new RuntimeException('Unable to connect to the database.');
}