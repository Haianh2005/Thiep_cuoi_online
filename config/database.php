<?php
// config/database.php
$host   = getenv('DB_HOST') ?: 'localhost';
$port   = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'wedding_db';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') ?: '';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// Kích hoạt SSL nếu kết nối tới cơ sở dữ liệu đám mây từ xa (TiDB/Aiven)
if (getenv('DB_HOST') && getenv('DB_HOST') !== 'localhost' && getenv('DB_HOST') !== '127.0.0.1') {
    $options[PDO::MYSQL_ATTR_SSL_CA] = true;
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        $options
    );
} catch (PDOException $e) {
    die("Kết nối database thất bại: " . $e->getMessage());
}