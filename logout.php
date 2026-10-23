<?php
// logout.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Bắt đầu session mới chỉ để lưu flash message
session_start();
$_SESSION['flash_message'] = [
    'type'    => 'success',
    'title'   => 'Đã đăng xuất',
    'message' => 'Bạn đã đăng xuất an toàn khỏi hệ thống.'
];

header("Location: /WeddingProject/login.php");
exit;
