<?php
// api/index.php - Vercel Serverless Gateway & Front Controller
declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Bỏ tiền tố thư mục nếu chạy dưới subpath
$uri = preg_replace('~^/WeddingProject~i', '', $uri);
$uri = '/' . ltrim((string)$uri, '/');

// Xử lý Route định danh đặc biệt từ rewrite
$route = $_GET['_route'] ?? '';
if ($route === 'w') {
    require __DIR__ . '/../w/index.php';
    exit;
} elseif ($route === 'editor') {
    require __DIR__ . '/../editor/index.php';
    exit;
}

// Chuẩn hóa đường dẫn tệp tin mục tiêu
$root = dirname(__DIR__);
$targetPath = $root . $uri;

if ($uri === '/' || $uri === '') {
    require $root . '/index.php';
    exit;
}

// Nếu là file PHP cụ thể
if (is_file($targetPath) && str_ends_with($targetPath, '.php')) {
    require $targetPath;
    exit;
}

// Nếu là thư mục có index.php
if (is_dir($targetPath) && is_file($targetPath . '/index.php')) {
    require $targetPath . '/index.php';
    exit;
}

// Thử gắn thêm .php
if (is_file($targetPath . '.php')) {
    require $targetPath . '.php';
    exit;
}

// Kiểm tra các định tuyến phụ phổ biến
if (str_starts_with($uri, '/w/')) {
    // Có thể là /w/slug-name nếu không qua query
    $slug = trim(substr($uri, 3), '/');
    if ($slug !== '' && !isset($_GET['slug'])) {
        $_GET['slug'] = $slug;
    }
    require $root . '/w/index.php';
    exit;
}

if (str_starts_with($uri, '/editor/')) {
    $token = trim(substr($uri, 8), '/');
    if ($token !== '' && !isset($_GET['token'])) {
        $_GET['token'] = $token;
    }
    require $root . '/editor/index.php';
    exit;
}

// 404 Không tìm thấy trang
http_response_code(404);
echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>404 Not Found</title>";
echo "<style>body{font-family:sans-serif;text-align:center;padding:80px 20px;color:#334155;background:#f8fafc;}";
echo "h1{font-size:48px;color:#e11d48;margin-bottom:12px;}p{font-size:16px;color:#64748b;}";
echo "a{color:#e11d48;text-decoration:none;font-weight:700;display:inline-block;margin-top:20px;padding:10px 20px;background:#ffe4e6;border-radius:8px;}";
echo "</style></head><body><h1>404</h1><p>Đường dẫn yêu cầu không tồn tại trên hệ thống.</p>";
echo "<a href='/'>Về trang chủ Wedding Project</a></body></html>";
exit;
