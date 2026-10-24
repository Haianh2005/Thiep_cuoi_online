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
    // Trên Vercel: hiển thị trang lỗi thân thiện thay vì die() gây 500
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Lỗi kết nối cơ sở dữ liệu</title>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">';
    echo '<style>';
    echo 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#f8fafc;font-family:"Plus Jakarta Sans",sans-serif;}';
    echo '.box{max-width:560px;padding:40px;background:rgba(30,41,59,.7);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.1);border-radius:20px;text-align:center;box-shadow:0 20px 40px rgba(0,0,0,.5);}';
    echo '.icon{font-size:56px;margin-bottom:16px;}';
    echo 'h1{margin:0 0 12px;font-size:24px;color:#f43f5e;}';
    echo 'p{margin:0 0 16px;color:#94a3b8;line-height:1.7;font-size:14.5px;}';
    echo 'code{background:rgba(255,255,255,.08);padding:2px 8px;border-radius:6px;font-size:13px;color:#fbbf24;}';
    echo '</style></head><body><div class="box">';
    echo '<div class="icon">🔌</div>';
    echo '<h1>Không thể kết nối Cơ sở Dữ liệu</h1>';
    echo '<p>Hệ thống chưa được cấu hình kết nối database. Vui lòng thiết lập các biến môi trường trên Vercel Dashboard:</p>';
    echo '<p><code>DB_HOST</code> · <code>DB_PORT</code> · <code>DB_NAME</code> · <code>DB_USER</code> · <code>DB_PASS</code></p>';
    echo '<p style="color:#64748b;font-size:13px;margin-top:20px;">Chi tiết lỗi: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    echo '</div></body></html>';
    exit;
}