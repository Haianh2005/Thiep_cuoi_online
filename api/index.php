<?php
// api/index.php - Vercel Serverless Gateway & Front Controller
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

try {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

    // Bỏ tiền tố thư mục nếu chạy dưới subpath
    $uri = preg_replace('~^/WeddingProject~i', '', $uri);
    $uri = '/' . ltrim((string)$uri, '/');

    // Endpoint kiểm tra sức khỏe hệ thống
    if ($uri === '/ping' || $uri === '/api/ping' || $uri === '/api/ping.php') {
        require_once __DIR__ . '/../config/database.php';
        $wCount = 0;
        $tCount = 0;
        if ($pdo && $db_connected) {
            try {
                $wCount = (int)$pdo->query("SELECT COUNT(*) FROM weddings")->fetchColumn();
                $tCount = (int)$pdo->query("SELECT COUNT(*) FROM templates")->fetchColumn();
            } catch (\Throwable $e) {}
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status'          => 'ok',
            'runtime'         => 'vercel-php',
            'php_version'     => PHP_VERSION,
            'time'            => date('c'),
            'env_vercel'      => !empty(getenv('VERCEL')),
            'tmp_writable'    => is_writable('/tmp'),
            'db_connected'    => $db_connected,
            'db_driver'       => $db_driver,
            'weddings_count'  => $wCount,
            'templates_count' => $tCount
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

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

    // Fallback: nếu là file tĩnh lọt vào gateway
    if (is_file($targetPath)) {
        $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css; charset=UTF-8',
            'js'    => 'application/javascript; charset=UTF-8',
            'json'  => 'application/json; charset=UTF-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'webp'  => 'image/webp',
            'svg'   => 'image/svg+xml',
            'mp3'   => 'audio/mpeg',
            'wav'   => 'audio/wav',
            'ico'   => 'image/x-icon',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf'
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . (string)filesize($targetPath));
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($targetPath);
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

} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>Application Error</title>";
    echo "<style>body{font-family:system-ui,-apple-system,sans-serif;padding:40px 20px;background:#0f172a;color:#f8fafc;max-width:800px;margin:0 auto;}";
    echo "h1{color:#f43f5e;font-size:24px;}pre{background:#1e293b;padding:16px;border-radius:8px;overflow:auto;border:1px solid #334155;color:#e2e8f0;font-size:13px;line-height:1.6;}";
    echo ".tip{margin-top:20px;padding:14px;background:#1e3a5f;border-left:4px solid #38bdf8;border-radius:4px;color:#93c5fd;font-size:14px;}";
    echo "</style></head><body>";
    echo "<h1>Lỗi Khởi Động Ứng Dụng (Application Runtime Notice)</h1>";
    echo "<p>Đã xảy ra ngoại lệ trong quá trình xử lý yêu cầu:</p>";
    echo "<pre><strong>" . htmlspecialchars($e->getMessage()) . "</strong>\n\n";
    echo "Tại tệp: " . htmlspecialchars($e->getFile()) . ":" . $e->getLine() . "\n\n";
    echo "Stack Trace:\n" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "<div class='tip'>Gợi ý: Nếu đây là lỗi liên quan tới Database, hãy thiết lập các biến môi trường <code>DB_HOST</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_NAME</code> trong Vercel Settings &gt; Environment Variables.</div>";
    echo "</body></html>";
    exit;
}
