<?php
// login.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$redirect = $_GET['redirect'] ?? '/WeddingProject/my-weddings.php';
// Bảo vệ không redirect về trang login hoặc logout
if (str_contains($redirect, 'login.php') || str_contains($redirect, 'logout.php')) {
    $redirect = '/WeddingProject/my-weddings.php';
}

// Nếu đã đăng nhập rồi thì chuyển hướng luôn
if (isLoggedIn()) {
    if (isAdmin() && !isset($_GET['redirect'])) {
        header("Location: /WeddingProject/admin/");
    } else {
        header("Location: " . $redirect);
    }
    exit;
}

$error = '';
$usernameOrEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Yêu cầu không hợp lệ hoặc phiên làm việc đã hết hạn. Vui lòng tải lại trang.';
    } else {
        $usernameOrEmail = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($usernameOrEmail === '' || $password === '') {
            $error = 'Vui lòng nhập đầy đủ tên đăng nhập/email và mật khẩu.';
        } else {
            $stmt = $pdo->prepare("
                SELECT id, username, email, password_hash, full_name, role, is_active
                FROM users
                WHERE (username = :u1 OR email = :u2)
                LIMIT 1
            ");
            $stmt->execute(['u1' => $usernameOrEmail, 'u2' => $usernameOrEmail]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
            } elseif ((int)$user['is_active'] !== 1) {
                $error = 'Tài khoản của bạn đã bị tạm khóa. Vui lòng liên hệ ban quản trị.';
            } else {
                // Đăng nhập thành công -> Tái tạo session id chống Session Fixation
                session_regenerate_id(true);
                $_SESSION['user_id']        = (int)$user['id'];
                $_SESSION['user_username']  = $user['username'];
                $_SESSION['user_full_name'] = $user['full_name'];
                $_SESSION['user_email']     = $user['email'];
                $_SESSION['user_role']      = $user['role'];

                setFlash('success', 'Chào mừng ' . htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') . ' quay trở lại!');

                // Điều hướng phân quyền
                if ($user['role'] === 'admin' && (!isset($_GET['redirect']) || empty($_GET['redirect']))) {
                    header("Location: /WeddingProject/admin/");
                } else {
                    header("Location: " . $redirect);
                }
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập | Nền tảng Tạo Thiệp Cưới Online</title>
    <meta name="description" content="Đăng nhập tài khoản để quản lý và tạo thiệp cưới online đẹp mắt, chuyên nghiệp.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 Plugin -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- SweetAlert2 Plugin -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #e11d48;
            --primary-hover: #be123c;
            --primary-light: #fff1f2;
            --accent-gold: #d97706;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --bg-glass: rgba(255, 255, 255, 0.92);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #ffe4e6 0%, #fecdd3 30%, #fbcfe8 70%, #f3e8ff 100%);
            position: relative;
            padding: 24px;
            overflow-x: hidden;
        }

        /* Hiệu ứng background hoa văn lãng mạn */
        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(225, 29, 72, 0.15) 0%, rgba(225, 29, 72, 0) 70%);
            top: -100px;
            right: -100px;
            z-index: 0;
        }

        body::after {
            content: '';
            position: absolute;
            width: 450px;
            height: 450px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(217, 119, 6, 0.12) 0%, rgba(217, 119, 6, 0) 70%);
            bottom: -80px;
            left: -80px;
            z-index: 0;
        }

        .auth-container {
            width: 100%;
            max-width: 460px;
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 28px;
            padding: 44px 36px;
            box-shadow: 0 25px 60px -15px rgba(225, 29, 72, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.5);
            position: relative;
            z-index: 1;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand-logo {
            font-family: 'Great Vibes', cursive;
            font-size: 42px;
            color: var(--primary);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 6px;
            transition: transform 0.3s ease;
        }

        .brand-logo:hover {
            transform: scale(1.04);
        }

        .brand-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .title-h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
            margin-top: 14px;
            margin-bottom: 6px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13.5px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            font-size: 15px;
            pointer-events: none;
            transition: color 0.2s;
        }

        .form-control {
            width: 100%;
            padding: 13px 16px 13px 44px;
            font-size: 14.5px;
            font-family: inherit;
            color: var(--text-dark);
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 14px;
            transition: all 0.2s ease;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(225, 29, 72, 0.12);
            background: #fff;
        }

        .form-control:focus + .input-icon {
            color: var(--primary);
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 15px;
            padding: 4px;
        }

        .toggle-password:hover {
            color: var(--text-dark);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            color: #fff;
            background: linear-gradient(135deg, var(--primary) 0%, #be123c 100%);
            border: none;
            border-radius: 14px;
            cursor: pointer;
            box-shadow: 0 10px 24px -6px rgba(225, 29, 72, 0.4);
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 26px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px -6px rgba(225, 29, 72, 0.5);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .alert-box {
            padding: 13px 16px;
            border-radius: 12px;
            font-size: 13.5px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff1f2;
            border: 1px solid #fecdd3;
            color: #be123c;
            animation: shake 0.4s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }

        .auth-footer {
            text-align: center;
            margin-top: 26px;
            font-size: 14px;
            color: var(--text-muted);
        }

        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
            transition: color 0.2s;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        /* Demo Fast-fill Badges */
        .demo-bar {
            margin-top: 26px;
            padding-top: 20px;
            border-top: 1px dashed #e2e8f0;
            text-align: center;
        }

        .demo-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        .demo-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .demo-btn {
            padding: 7px 14px;
            font-size: 12.5px;
            font-weight: 600;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            color: #334155;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .demo-btn:hover {
            background: var(--primary-light);
            border-color: #fecdd3;
            color: var(--primary);
        }

        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-muted);
            text-decoration: none;
            margin-bottom: 20px;
            transition: color 0.2s;
        }

        .back-home:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>

<div class="auth-container">
    <a href="/WeddingProject/" class="back-home">
        <i class="fa-solid fa-arrow-left"></i> Về trang chủ
    </a>

    <div class="brand-header">
        <a href="/WeddingProject/" class="brand-logo">Wedding Project</a>
        <div class="brand-subtitle">Nền tảng khởi tạo thiệp cưới phong cách & hiện đại</div>
        <h1 class="title-h1">Đăng nhập tài khoản</h1>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert-box">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endif; ?>

    <form method="post" action="login.php<?= isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" autocomplete="on">
        <?= csrfField() ?>

        <div class="form-group">
            <label class="form-label" for="username">Tên đăng nhập hoặc Email</label>
            <div class="input-wrap">
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-control"
                    placeholder="ví dụ: admin hoặc user@email.com"
                    value="<?= htmlspecialchars($usernameOrEmail, ENT_QUOTES, 'UTF-8') ?>"
                    required
                    autofocus
                >
                <i class="fa-solid fa-user input-icon"></i>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Mật khẩu</label>
            <div class="input-wrap">
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="Nhập mật khẩu..."
                    required
                >
                <i class="fa-solid fa-lock input-icon"></i>
                <button type="button" class="toggle-password" onclick="togglePasswordVisibility()" aria-label="Hiện/ẩn mật khẩu">
                    <i class="fa-regular fa-eye" id="toggleIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-submit">
            <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập ngay
        </button>
    </form>

    <div class="auth-footer">
        Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
    </div>

    <!-- Quick test demo credentials -->
    <div class="demo-bar">
        <div class="demo-title">Điền nhanh tài khoản thử nghiệm</div>
        <div class="demo-buttons">
            <button type="button" class="demo-btn" onclick="fillDemo('admin', 'admin123')">
                <i class="fa-solid fa-shield-halved"></i> Admin (admin / admin123)
            </button>
            <button type="button" class="demo-btn" onclick="fillDemo('khachhang', '123456')">
                <i class="fa-solid fa-heart"></i> Khách (khachhang / 123456)
            </button>
        </div>
    </div>
</div>

<?= renderFlashSwal() ?>

<script>
function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function fillDemo(user, pass) {
    document.getElementById('username').value = user;
    document.getElementById('password').value = pass;
    // Highlight effect
    const btn = document.querySelector('.btn-submit');
    btn.style.transform = 'scale(1.02)';
    setTimeout(() => { btn.style.transform = 'none'; }, 200);
}
</script>

</body>
</html>
