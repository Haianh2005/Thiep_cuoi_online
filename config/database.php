<?php
// config/database.php
declare(strict_types=1);

if (!class_exists('SafeMockStatement')) {
    class SafeMockStatement extends PDOStatement {
        public function __construct() {}
        public function execute($params = null): bool { return true; }
        public function fetch($mode = PDO::FETCH_DEFAULT, $cursorOrientation = PDO::FETCH_ORI_NEXT, $cursorOffset = 0): mixed { return false; }
        public function fetchAll($mode = PDO::FETCH_DEFAULT, ...$args): array { return []; }
        public function fetchColumn($column = 0): mixed { return 0; }
        public function rowCount(): int { return 0; }
    }
}

if (!class_exists('SafeMockPDO')) {
    class SafeMockPDO extends PDO {
        public function __construct() {}
        public function prepare($query, $options = []): PDOStatement|false { return new SafeMockStatement(); }
        public function query($query, $fetchMode = null, ...$fetch_mode_args): PDOStatement|false { return new SafeMockStatement(); }
        public function exec($statement): int|false { return 0; }
        public function lastInsertId($name = null): string|false { return "0"; }
        public function beginTransaction(): bool { return true; }
        public function commit(): bool { return true; }
        public function rollBack(): bool { return true; }
        public function inTransaction(): bool { return false; }
        public function setAttribute($attribute, $value): bool { return true; }
    }
}

$host   = getenv('DB_HOST') ?: '';
$port   = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'wedding_db';
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') ?: '';

$isVercel = !empty(getenv('VERCEL')) || !empty(getenv('NOW_REGION')) || !empty(getenv('AWS_LAMBDA_FUNCTION_NAME'));

$shouldConnect = false;
if (!$isVercel) {
    // Môi trường Local (XAMPP / Laragon / Docker)
    $host = $host ?: 'localhost';
    $shouldConnect = true;
} else {
    // Môi trường Vercel: chỉ kết nối nếu có cấu hình DB_HOST từ xa hợp lệ
    if (!empty($host) && $host !== 'localhost' && $host !== '127.0.0.1') {
        $shouldConnect = true;
    }
}

$pdo = null;
$db_connected = false;
$db_error = null;

if ($shouldConnect) {
    try {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3,
        ];

        if ($isVercel || ($host !== 'localhost' && $host !== '127.0.0.1')) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = true;
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
            $user,
            $pass,
            $options
        );
        $db_connected = true;
    } catch (\Throwable $e) {
        $pdo = new SafeMockPDO();
        $db_connected = false;
        $db_error = $e->getMessage();
    }
} else {
    $pdo = new SafeMockPDO();
    $db_connected = false;
    $db_error = "Vercel serverless: Chưa cấu hình biến môi trường kết nối database từ xa (DB_HOST, DB_USER, DB_PASS, DB_NAME).";
}