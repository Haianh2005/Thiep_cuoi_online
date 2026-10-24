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

$pdo = null;
$db_connected = false;
$db_error = null;
$db_driver = 'none';

// 1. Thử kết nối MySQL nếu có cấu hình DB_HOST từ xa hoặc đang chạy Local có MySQL
$shouldConnectMysql = false;
if (!$isVercel) {
    // Môi trường Local: thử MySQL localhost
    $host = $host ?: 'localhost';
    $shouldConnectMysql = true;
} else {
    // Trên Vercel: chỉ kết nối MySQL nếu có biến DB_HOST từ xa
    if (!empty($host) && $host !== 'localhost' && $host !== '127.0.0.1') {
        $shouldConnectMysql = true;
    }
}

if ($shouldConnectMysql) {
    try {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 2,
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
        $db_driver = 'mysql';
    } catch (\Throwable $e) {
        $pdo = null;
        $db_error = $e->getMessage();
    }
}

// 2. Tự động chuyển sang SQLite nhúng dữ liệu từ wedding_db.sql (Dùng cho Vercel không cần Cloud DB)
if (!$pdo) {
    $bundledSqlite = dirname(__DIR__) . '/wedding.sqlite';
    $tmpSqlite     = '/tmp/wedding.sqlite';
    $targetSqlite  = $bundledSqlite;

    // Trên Vercel: sao chép sang /tmp để hỗ trợ ghi dữ liệu (gửi lời chúc, tạo thiệp)
    if ($isVercel && file_exists($bundledSqlite)) {
        if (!file_exists($tmpSqlite) || filesize($tmpSqlite) === 0) {
            @copy($bundledSqlite, $tmpSqlite);
        }
        if (file_exists($tmpSqlite)) {
            $targetSqlite = $tmpSqlite;
        }
    }

    if (file_exists($targetSqlite)) {
        try {
            $pdo = new PDO("sqlite:" . $targetSqlite);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            // Đăng ký các hàm MySQL tương thích cho SQLite (hỗ trợ cả PHP 8.5+)
            $createFn = method_exists($pdo, 'createFunction') ? [$pdo, 'createFunction'] : (method_exists($pdo, 'sqliteCreateFunction') ? [$pdo, 'sqliteCreateFunction'] : null);
            if ($createFn) {
                @$createFn('NOW', fn() => date('Y-m-d H:i:s'));
                @$createFn('CURDATE', fn() => date('Y-m-d'));
                @$createFn('CONCAT', fn(...$args) => implode('', $args));
                @$createFn('IF', fn($cond, $v1, $v2) => $cond ? $v1 : $v2);
                @$createFn('IFNULL', fn($val, $alt) => $val ?? $alt);
                @$createFn('YEAR', fn($d) => date('Y', strtotime((string)$d)));
                @$createFn('MONTH', fn($d) => date('m', strtotime((string)$d)));
                @$createFn('DAY', fn($d) => date('d', strtotime((string)$d)));
            }

            $db_connected = true;
            $db_driver = 'sqlite';
        } catch (\Throwable $e) {
            $pdo = new SafeMockPDO();
            $db_connected = false;
            $db_error = $e->getMessage();
        }
    } else {
        $pdo = new SafeMockPDO();
        $db_connected = false;
        $db_error = "Không tìm thấy tệp cơ sở dữ liệu wedding.sqlite.";
    }
}