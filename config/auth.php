<?php
// config/auth.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * Kiểm tra xem người dùng hiện tại đã đăng nhập hay chưa
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Lấy thông tin người dùng hiện tại từ Session
 */
function currentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'        => (int)($_SESSION['user_id'] ?? 0),
        'username'  => (string)($_SESSION['user_username'] ?? ''),
        'full_name' => (string)($_SESSION['user_full_name'] ?? ''),
        'email'     => (string)($_SESSION['user_email'] ?? ''),
        'role'      => (string)($_SESSION['user_role'] ?? 'customer')
    ];
}

/**
 * Kiểm tra xem người dùng hiện tại có vai trò Quản trị viên (Admin) hay không
 */
function isAdmin(): bool {
    return isLoggedIn() && (($_SESSION['user_role'] ?? '') === 'admin');
}

/**
 * Yêu cầu người dùng phải đăng nhập trước khi tiếp tục
 */
function requireLogin(?string $redirectUrl = null): void {
    if (!isLoggedIn()) {
        $target = $redirectUrl ?: ($_SERVER['REQUEST_URI'] ?? '/WeddingProject/');
        // Đảm bảo không redirect lặp lại về chính trang login
        if (str_contains($target, 'login.php')) {
            $target = '/WeddingProject/my-weddings.php';
        }
        header("Location: /WeddingProject/login.php?redirect=" . urlencode($target));
        exit;
    }
}

/**
 * Yêu cầu bắt buộc quyền Quản trị viên (Admin)
 */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 Forbidden - Truy cập bị từ chối</title>
            <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
            <style>
                body {
                    margin: 0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #0f172a;
                    color: #f8fafc;
                    font-family: 'Plus Jakarta Sans', sans-serif;
                }
                .box {
                    max-width: 480px;
                    padding: 40px;
                    background: rgba(30, 41, 59, 0.7);
                    backdrop-filter: blur(12px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 20px;
                    text-align: center;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.5);
                }
                .icon { font-size: 56px; margin-bottom: 16px; }
                h1 { margin: 0 0 12px; font-size: 26px; color: #f43f5e; }
                p { margin: 0 0 24px; color: #94a3b8; line-height: 1.6; }
                .btn {
                    display: inline-block;
                    padding: 12px 24px;
                    background: linear-gradient(135deg, #e11d48, #be123c);
                    color: #fff;
                    text-decoration: none;
                    border-radius: 999px;
                    font-weight: 600;
                    transition: transform 0.2s;
                }
                .btn:hover { transform: translateY(-2px); }
            </style>
        </head>
        <body>
            <div class="box">
                <div class="icon">🚫</div>
                <h1>403 Forbidden</h1>
                <p>Bạn không có quyền truy cập vào khu vực Quản trị. Vui lòng đăng nhập với tài khoản Admin.</p>
                <a href="/WeddingProject/" class="btn">Về Trang Chủ</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

/**
 * Kiểm tra quyền sở hữu thiệp của khách hàng
 * Quản trị viên (admin) luôn có quyền truy cập mọi thiệp
 */
function checkWeddingOwnership(PDO $pdo, int $weddingId, int $userId): bool {
    if (isAdmin()) {
        return true;
    }
    $stmt = $pdo->prepare("SELECT id FROM weddings WHERE id = :id AND user_id = :user_id LIMIT 1");
    $stmt->execute(['id' => $weddingId, 'user_id' => $userId]);
    return (bool) $stmt->fetch();
}

/**
 * Trả về mảng phân trang gồm limit, offset và metadata
 */
function paginate(PDO $pdo, string $countSql, array $params = [], int $perPage = 10): array {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = (int)$stmt->fetchColumn();
    $totalPages = (int)ceil($totalRecords / $perPage);
    $offset = ($page - 1) * $perPage;

    return [
        'page'          => $page,
        'per_page'      => $perPage,
        'total_records' => $totalRecords,
        'total_pages'   => $totalPages,
        'offset'        => $offset
    ];
}

/**
 * Hiển thị thanh phân trang chuẩn giữ nguyên các query params hiện tại ($_GET)
 */
function renderPagination(int $currentPage, int $totalPages): string {
    if ($totalPages <= 1) return '';
    $html = '<nav class="pagination-wrapper" aria-label="Page navigation"><ul class="pagination">';
    
    // Nút Trước
    if ($currentPage > 1) {
        $queryParams = $_GET;
        $queryParams['page'] = $currentPage - 1;
        $url = '?' . http_build_query($queryParams);
        $html .= "<li class='page-item'><a class='page-link' href='{$url}' aria-label='Previous'>&laquo;</a></li>";
    }

    // Các trang số
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    if ($start > 1) {
        $queryParams = $_GET;
        $queryParams['page'] = 1;
        $url = '?' . http_build_query($queryParams);
        $html .= "<li class='page-item'><a class='page-link' href='{$url}'>1</a></li>";
        if ($start > 2) {
            $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $queryParams = $_GET;
        $queryParams['page'] = $i;
        $url = '?' . http_build_query($queryParams);
        $activeClass = ($i === $currentPage) ? 'active' : '';
        $html .= "<li class='page-item {$activeClass}'><a class='page-link' href='{$url}'>{$i}</a></li>";
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= "<li class='page-item disabled'><span class='page-link'>...</span></li>";
        }
        $queryParams = $_GET;
        $queryParams['page'] = $totalPages;
        $url = '?' . http_build_query($queryParams);
        $html .= "<li class='page-item'><a class='page-link' href='{$url}'>{$totalPages}</a></li>";
    }

    // Nút Kế tiếp
    if ($currentPage < $totalPages) {
        $queryParams = $_GET;
        $queryParams['page'] = $currentPage + 1;
        $url = '?' . http_build_query($queryParams);
        $html .= "<li class='page-item'><a class='page-link' href='{$url}' aria-label='Next'>&raquo;</a></li>";
    }

    $html .= '</ul></nav>';
    return $html;
}

/**
 * Sinh mã CSRF token an toàn
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Sinh thẻ input ẩn chứa CSRF token
 */
function csrfField(): string {
    $token = htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Xác thực mã CSRF token gửi lên
 */
function verifyCsrfToken(?string $token = null): bool {
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Lưu thông báo flash vào session
 */
function setFlash(string $type, string $message, ?string $title = null): void {
    $_SESSION['flash_message'] = [
        'type'    => $type, // 'success', 'error', 'warning', 'info'
        'message' => $message,
        'title'   => $title ?? ($type === 'success' ? 'Thành công!' : 'Thông báo')
    ];
}

/**
 * Lấy và xóa thông báo flash
 */
function getFlash(): ?array {
    if (!empty($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Render script SweetAlert2 hiển thị flash message nếu có
 */
function renderFlashSwal(): string {
    $flash = getFlash();
    if (!$flash) {
        return '';
    }
    $icon = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($flash['title'], ENT_QUOTES, 'UTF-8');
    $msg = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    return "<script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: '{$icon}',
                    title: '{$title}',
                    text: '{$msg}',
                    confirmButtonColor: '#e11d48'
                });
            }
        });
    </script>";
}
