<?php
// register.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Nếu đã đăng nhập thì chuyển hướng
if (isLoggedIn()) {
    header("Location: " . baseUrl('my-weddings.php'));
    exit;
}

$error = '';
$fullName = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        $error = 'Yêu cầu không hợp lệ hoặc phiên làm việc đã hết hạn. Vui lòng thử lại.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        // Validation
        if ($fullName === '' || $username === '' || $email === '' || $password === '') {
            $error = 'Vui lòng điền đầy đủ tất cả các trường thông tin.';
        } elseif (mb_strlen($fullName) < 2) {
            $error = 'Họ và tên phải có ít nhất 2 ký tự.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $error = 'Tên đăng nhập từ 3 - 30 ký tự, chỉ gồm chữ, số và dấu gạch dưới (_).';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Địa chỉ email không đúng định dạng.';
        } elseif (strlen($password) < 6) {
            $error = 'Mật khẩu phải chứa ít nhất 6 ký tự.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Mật khẩu xác nhận không khớp nhau.';
        } else {
            // Kiểm tra trùng lặp username hoặc email
            $checkStmt = $pdo->prepare("
                SELECT username, email 
                FROM users 
                WHERE username = :u OR email = :e 
                LIMIT 1
            ");
            $checkStmt->execute(['u' => $username, 'e' => $email]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                if (strcasecmp($existing['username'], $username) === 0) {
                    $error = 'Tên đăng nhập này đã được sử dụng. Vui lòng chọn tên khác.';
                } else {
                    $error = 'Địa chỉ email này đã được đăng ký trong hệ thống.';
                }
            } else {
                // Băm mật khẩu bằng BCRYPT
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                $insertStmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, full_name, role, is_active, created_at)
                    VALUES (:username, :email, :password_hash, :full_name, 'customer', 1, NOW())
                ");

                $insertStmt->execute([
                    'username'      => $username,
                    'email'         => $email,
                    'password_hash' => $passwordHash,
                    'full_name'     => $fullName
                ]);

                $newUserId = (int)$pdo->lastInsertId();

                // Đăng nhập tự động luôn cho khách hàng
                session_regenerate_id(true);
                $_SESSION['user_id']        = $newUserId;
                $_SESSION['user_username']  = $username;
                $_SESSION['user_full_name'] = $fullName;
                $_SESSION['user_email']     = $email;
                $_SESSION['user_role']      = 'customer';

                setFlash('success', 'Đăng ký tài khoản thành công! Hãy bắt đầu chọn mẫu thiệp cưới của bạn.');
                header("Location: " . baseUrl('mau-thiep/'));
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
    <title>Đăng ký tài khoản | Nền tảng Tạo Thiệp Cưới Online</title>
    <meta name="description" content="Tạo tài khoản miễn phí để thiết kế và xuất bản thiệp cưới online độc đáo của bạn.">

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
            max-width: 480px;
            background: var(--bg-glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 28px;
            padding: 40px 36px;
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
            margin-bottom: 24px;
        }

        .brand-logo {
            font-family: 'Great Vibes', cursive;
            font-size: 40px;
            color: var(--primary);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 4px;
        }

        .brand-subtitle {
            font-size: 13.5px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .title-h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text-dark);
            margin-top: 10px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
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
            font-size: 14px;
            pointer-events: none;
            transition: color 0.2s;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px 12px 42px;
            font-size: 14px;
            font-family: inherit;
            color: var(--text-dark);
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.2s ease;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(225, 29, 72, 0.12);
            background: #fff;
        }

        .btn-submit {
            width: 100%;
            padding: 13.5px;
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
            margin-top: 22px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px -6px rgba(225, 29, 72, 0.5);
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
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
            margin-top: 22px;
            font-size: 13.5px;
            color: var(--text-muted);
        }

        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 700;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .back-home {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-muted);
            text-decoration: none;
            margin-bottom: 16px;
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
        <div class="brand-subtitle">Khởi tạo thiệp cưới online phong cách của bạn</div>
        <h1 class="title-h1">Tạo tài khoản mới</h1>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert-box">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    <?php endif; ?>

    <form method="post" action="register.php" autocomplete="on">
        <?= csrfField() ?>

        <div class="form-group">
            <label class="form-label" for="full_name">Họ và tên của bạn</label>
            <div class="input-wrap">
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    class="form-control"
                    placeholder="ví dụ: Nguyễn Văn An"
                    value="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>"
                    required
                    autofocus
                >
                <i class="fa-solid fa-id-card input-icon"></i>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="username">Tên đăng nhập</label>
            <div class="input-wrap">
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-control"
                    placeholder="Chữ cái, số, không dấu cách (vd: vanan_wedding)"
                    value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
                <i class="fa-solid fa-user input-icon"></i>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="email">Địa chỉ Email</label>
            <div class="input-wrap">
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="name@example.com"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    required
                >
                <i class="fa-solid fa-envelope input-icon"></i>
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
                    placeholder="Tối thiểu 6 ký tự"
                    required
                >
                <i class="fa-solid fa-lock input-icon"></i>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="confirm_password">Xác nhận mật khẩu</label>
            <div class="input-wrap">
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    class="form-control"
                    placeholder="Nhập lại mật khẩu ở trên"
                    required
                >
                <i class="fa-solid fa-shield-halved input-icon"></i>
            </div>
        </div>

        <button type="submit" class="btn-submit">
            <i class="fa-solid fa-user-plus"></i> Hoàn tất đăng ký
        </button>
    </form>

    <div class="auth-footer">
        Đã có tài khoản? <a href="login.php">Đăng nhập</a>
    </div>
</div>

<?= renderFlashSwal() ?>

</body>
</html>
