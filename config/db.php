
<?php

$host = "127.0.0.1";
$port = "3306";
$dbname = "assetflow_db";
$username = "root";
$password = ""; // Enter your MySQL password if you have one.

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log($e->getMessage());
    exit("Database connection failed. Check your database settings.");
}
