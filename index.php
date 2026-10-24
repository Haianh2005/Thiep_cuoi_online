<?php
// index.php - Trang chủ nền tảng tạo thiệp cưới WeddingProject
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$user = currentUser();

// Lấy 6 mẫu thiệp nổi bật nhất từ database hoặc fallback
$featuredTemplates = [];
$totalTemplates = 28;
$totalWeddings = 120;

if ($pdo) {
    try {
        $featuredTemplatesStmt = $pdo->query("
            SELECT id, name, folder, thumbnail, description 
            FROM templates 
            WHERE status = 1 
            ORDER BY id ASC 
            LIMIT 6
        ");
        $featuredTemplates = $featuredTemplatesStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalTemplates = (int)$pdo->query("SELECT COUNT(*) FROM templates WHERE status = 1")->fetchColumn();
        $totalWeddings  = (int)$pdo->query("SELECT COUNT(*) FROM weddings WHERE status = 1")->fetchColumn();
    } catch (\Throwable $e) {
        $featuredTemplates = [];
    }
}

// Fallback tự động từ 6 mẫu thiệp nếu chưa kết nối Database
if (empty($featuredTemplates)) {
    for ($i = 1; $i <= 6; $i++) {
        $pad = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $featuredTemplates[] = [
            'id' => $i,
            'name' => "Mẫu Thiệp Cưới Sang Trọng #$pad",
            'folder' => "template$pad",
            'thumbnail' => "templates/template$pad/thumb.jpg",
            'description' => "Thiết kế hiện đại, trang nhã với đầy đủ tính năng tương tác, album ảnh và đếm ngược ngày cưới."
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wedding Project - Nền tảng Tạo Thiệp Cưới Online Đẹp & Hiện Đại</title>
    <meta name="description" content="Tạo thiệp cưới online tương tác miễn phí trong 3 phút. Đầy đủ nhạc cưới lãng mạn, album ảnh HD, mã QR mừng cưới và bản đồ thông minh.">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

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
            --gold: #d97706;
            --gold-light: #fef3c7;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --bg-body: #fafaf9;
            --card-border: #f1f5f9;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            color: var(--text-dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Header Navigation */
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            transition: all 0.3s ease;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo-heart {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), #fb7185);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 8px 16px -4px rgba(225, 29, 72, 0.35);
        }

        .logo-text {
            font-family: 'Great Vibes', cursive;
            font-size: 34px;
            color: var(--primary);
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .nav-link {
            color: #475569;
            text-decoration: none;
            font-weight: 600;
            font-size: 14.5px;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .nav-link:hover { color: var(--primary); }

        .auth-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-auth-outline {
            padding: 9px 20px;
            border-radius: 999px;
            border: 1.5px solid #cbd5e1;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 600;
            font-size: 13.5px;
            transition: all 0.2s;
        }

        .btn-auth-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }

        .btn-auth-solid {
            padding: 9px 22px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--primary), #be123c);
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 13.5px;
            box-shadow: 0 8px 18px -4px rgba(225, 29, 72, 0.4);
            transition: all 0.25s;
        }

        .btn-auth-solid:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -4px rgba(225, 29, 72, 0.5);
        }

        /* User Profile Pill */
        .user-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #fff;
            padding: 6px 14px 6px 6px;
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .avatar-sm {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), #f43f5e);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
        }

        /* Hero Section */
        .hero {
            position: relative;
            padding: 90px 24px 100px;
            text-align: center;
            background: radial-gradient(circle at 50% 20%, #ffe4e6 0%, #fff1f2 40%, #fafaf9 80%);
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(225, 29, 72, 0.08) 0%, transparent 70%);
            top: -200px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 840px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            background: #ffffff;
            border-radius: 999px;
            border: 1px solid #fecdd3;
            color: var(--primary);
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(225, 29, 72, 0.08);
            margin-bottom: 24px;
        }

        .hero-title {
            font-size: clamp(38px, 6vw, 62px);
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.15;
            letter-spacing: -1.5px;
            margin-bottom: 22px;
        }

        .hero-title span {
            background: linear-gradient(135deg, #e11d48, #d97706);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: clamp(16px, 2vw, 19px);
            color: var(--text-muted);
            line-height: 1.7;
            max-width: 680px;
            margin: 0 auto 36px;
        }

        .hero-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn-cta-primary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 34px;
            background: linear-gradient(135deg, var(--primary), #be123c);
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            border-radius: 16px;
            box-shadow: 0 14px 30px -6px rgba(225, 29, 72, 0.45);
            transition: all 0.25s ease;
        }

        .btn-cta-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 36px -6px rgba(225, 29, 72, 0.55);
        }

        .btn-cta-secondary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 30px;
            background: #ffffff;
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            border-radius: 16px;
            border: 1.5px solid #e2e8f0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            transition: all 0.25s ease;
        }

        .btn-cta-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        /* Trust Stats Bar */
        .stats-strip {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 48px;
            margin-top: 50px;
            padding-top: 36px;
            border-top: 1px solid rgba(226, 232, 240, 0.6);
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-num {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .stat-desc {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Features Section */
        .features-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 90px 24px;
        }

        .section-header {
            text-align: center;
            max-width: 640px;
            margin: 0 auto 60px;
        }

        .section-tag {
            color: var(--primary);
            font-size: 12.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 10px;
        }

        .section-title {
            font-size: 34px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.25;
            letter-spacing: -0.8px;
            margin-bottom: 14px;
        }

        .section-desc {
            color: var(--text-muted);
            font-size: 15.5px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 28px;
        }

        .feature-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 34px 28px;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }

        .feature-card:hover {
            transform: translateY(-6px);
            border-color: #fecdd3;
            box-shadow: 0 16px 36px -8px rgba(225, 29, 72, 0.12);
        }

        .feature-icon-wrap {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 22px;
        }

        .icon-music { background: #ffe4e6; color: var(--primary); }
        .icon-album { background: #fef3c7; color: #b45309; }
        .icon-wish { background: #e0f2fe; color: #0284c7; }
        .icon-qr { background: #dcfce7; color: #16a34a; }
        .icon-map { background: #ede9fe; color: #7c3aed; }
        .icon-cloud { background: #f1f5f9; color: #475569; }

        .feature-card h3 {
            font-size: 19px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .feature-card p {
            color: var(--text-muted);
            font-size: 14.5px;
            line-height: 1.6;
        }

        /* Template Showcase */
        .showcase-section {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 90px 24px;
        }

        .showcase-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 28px;
            margin-bottom: 48px;
        }

        .template-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .template-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 32px rgba(0,0,0,0.08);
            border-color: #cbd5e1;
        }

        .template-thumb {
            height: 240px;
            position: relative;
            background: #f1f5f9;
            overflow: hidden;
        }

        .template-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .template-card:hover .template-thumb img {
            transform: scale(1.06);
        }

        .template-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .template-card:hover .template-overlay {
            opacity: 1;
        }

        .btn-overlay {
            padding: 10px 18px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.2s;
        }

        .btn-overlay.demo {
            background: #ffffff;
            color: var(--text-dark);
        }

        .btn-overlay.choose {
            background: var(--primary);
            color: #ffffff;
        }

        .btn-overlay:hover {
            transform: scale(1.06);
        }

        .template-body {
            padding: 18px 20px;
        }

        .template-body h4 {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .template-body p {
            color: var(--text-muted);
            font-size: 13.5px;
            line-height: 1.5;
        }

        .btn-view-all {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 32px;
            background: #ffffff;
            color: var(--primary);
            border: 2px solid var(--primary);
            border-radius: 999px;
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
            transition: all 0.25s;
        }

        .btn-view-all:hover {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 8px 20px rgba(225, 29, 72, 0.3);
        }

        /* How it works */
        .process-section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 90px 24px;
        }

        .process-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px;
            position: relative;
        }

        .process-step {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 36px 28px;
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            margin: 0 auto 20px;
        }

        .process-step h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .process-step p {
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /* CTA Banner */
        .cta-banner {
            max-width: 1152px;
            margin: 0 auto 90px;
            padding: 60px 36px;
            border-radius: 32px;
            background: linear-gradient(135deg, #be123c 0%, #e11d48 50%, #fb7185 100%);
            color: #ffffff;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(225, 29, 72, 0.4);
            position: relative;
            overflow: hidden;
        }

        .cta-banner h2 {
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 800;
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .cta-banner p {
            font-size: 17px;
            max-width: 600px;
            margin: 0 auto 32px;
            opacity: 0.95;
        }

        .btn-cta-white {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 36px;
            background: #ffffff;
            color: var(--primary);
            border-radius: 16px;
            font-weight: 800;
            font-size: 16px;
            text-decoration: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            transition: all 0.25s;
        }

        .btn-cta-white:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.25);
        }

        /* Footer */
        .site-footer {
            background: #0f172a;
            color: #94a3b8;
            padding: 60px 24px 30px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 50px;
        }

        @media (max-width: 860px) {
            .footer-container { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 540px) {
            .footer-container { grid-template-columns: 1fr; }
        }

        .footer-brand h4 {
            font-family: 'Great Vibes', cursive;
            font-size: 36px;
            color: #fff;
            margin-bottom: 12px;
        }

        .footer-col h5 {
            color: #ffffff;
            font-size: 14.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 18px;
        }

        .footer-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .footer-links a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: #f43f5e;
        }

        .footer-bottom {
            max-width: 1200px;
            margin: 0 auto;
            padding-top: 24px;
            border-top: 1px solid #1e293b;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            font-size: 13px;
        }
    </style>
</head>
<body>

<!-- Header Navigation -->
<header class="header">
    <div class="nav-container">
        <a href="/WeddingProject/" class="brand-logo">
            <div class="logo-heart"><i class="fa-solid fa-heart"></i></div>
            <div class="logo-text">Wedding Project</div>
        </a>

        <nav class="nav-links">
            <a href="/WeddingProject/" class="nav-link active">Trang chủ</a>
            <a href="/WeddingProject/mau-thiep/" class="nav-link">Kho mẫu thiệp (<?= $totalTemplates ?>+)</a>
            <a href="#tinh-nang" class="nav-link">Tính năng</a>
            <a href="#quy-trinh" class="nav-link">Quy trình</a>

            <?php if ($user): ?>
                <a href="/WeddingProject/my-weddings.php" class="nav-link" style="color: var(--primary);">
                    <i class="fa-solid fa-heart"></i> Thiệp của tôi
                </a>
                <?php if (isAdmin()): ?>
                    <a href="/WeddingProject/admin/" class="nav-link" style="color: #d97706;">
                        <i class="fa-solid fa-shield-halved"></i> Quản trị
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="auth-actions">
            <?php if ($user): ?>
                <div class="user-pill">
                    <div class="avatar-sm">
                        <?= mb_strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
                    </div>
                    <span style="font-size: 13.5px; font-weight: 700; color: #1e293b;">
                        <?= htmlspecialchars(mb_strimwidth($user['full_name'], 0, 16, '...'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <a href="/WeddingProject/logout.php" style="color: #ef4444; font-size: 13px; text-decoration: none; margin-left: 4px;" title="Đăng xuất">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    </a>
                </div>
            <?php else: ?>
                <a href="/WeddingProject/login.php" class="btn-auth-outline">Đăng nhập</a>
                <a href="/WeddingProject/register.php" class="btn-auth-solid">Đăng ký</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Hero Banner Section -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Nền tảng Tạo Thiệp Cưới Điện Tử Thông Minh
        </div>

        <h1 class="hero-title">
            Gửi trọn khoảnh khắc yêu thương qua <span>Thiệp Cưới Online</span>
        </h1>

        <p class="hero-subtitle">
            Tự tay tạo và xuất bản thiệp cưới điện tử sang trọng chỉ trong 3 phút. Kho hơn 28+ mẫu thiệp thiết kế tinh xảo, nhúng nhạc lãng mạn, album ảnh kỷ niệm và nhận tiền mừng qua mã QR VietQR.
        </p>

        <div class="hero-cta">
            <a href="/WeddingProject/mau-thiep/" class="btn-cta-primary">
                <i class="fa-solid fa-palette"></i> Khám phá kho mẫu thiệp
            </a>
            <?php if ($user): ?>
                <a href="/WeddingProject/my-weddings.php" class="btn-cta-secondary">
                    <i class="fa-solid fa-heart"></i> Quản lý thiệp của tôi
                </a>
            <?php else: ?>
                <a href="/WeddingProject/register.php" class="btn-cta-secondary">
                    <i class="fa-solid fa-user-plus"></i> Đăng ký tạo thiệp
                </a>
            <?php endif; ?>
        </div>

        <div class="stats-strip">
            <div class="stat-item">
                <div class="stat-num"><?= $totalTemplates ?>+</div>
                <div class="stat-desc">Mẫu thiệp đa phong cách</div>
            </div>
            <div class="stat-item">
                <div class="stat-num"><?= $totalWeddings ?>+</div>
                <div class="stat-desc">Thiệp cưới đã xuất bản</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">100%</div>
                <div class="stat-desc">Tương thích di động & Tablet</div>
            </div>
            <div class="stat-item">
                <div class="stat-num">0 VNĐ</div>
                <div class="stat-desc">Bắt đầu hoàn toàn miễn phí</div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="features-section" id="tinh-nang">
    <div class="section-header">
        <div class="section-tag">Tính năng vượt trội</div>
        <h2 class="section-title">Trải nghiệm tương tác sinh động cho ngày trọng đại</h2>
        <p class="section-desc">Không chỉ là tấm thiệp thông báo, đây là trang kỷ niệm ngày cưới tương tác hiện đại dành riêng cho hai bạn.</p>
    </div>

    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon-wrap icon-music"><i class="fa-solid fa-music"></i></div>
            <h3>Nhạc nền cưới lãng mạn</h3>
            <p>Khách mời được thưởng thức những giai điệu tình yêu du dương tự động phát hoặc tùy chọn phát/dừng êm ái khi mở thiệp.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon-wrap icon-album"><i class="fa-solid fa-images"></i></div>
            <h3>Album ảnh cưới chất lượng cao</h3>
            <p>Hiển thị trọn vẹn bộ ảnh cưới lung linh với hiệu ứng phóng to Lightbox và vuốt mượt mà trên mọi thiết bị di động.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon-wrap icon-wish"><i class="fa-solid fa-comments"></i></div>
            <h3>Sổ lưu bút lời chúc Online</h3>
            <p>Khách mời dễ dàng gửi gắm lời chúc phúc qua API thời gian thực. Tự động hiển thị và lưu giữ mãi mãi trong thiệp.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon-wrap icon-qr"><i class="fa-solid fa-qrcode"></i></div>
            <h3>Mã QR mừng cưới VietQR</h3>
            <p>Tích hợp tài khoản ngân hàng nhà trai và nhà gái. Khách ở xa quét mã chuyển khoản mừng cưới tiện lợi và chuẩn xác.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon-wrap icon-map"><i class="fa-solid fa-map-location-dot"></i></div>
            <h3>Bản đồ chỉ đường Google Maps</h3>
            <p>Định vị trực tiếp địa điểm tổ chức hôn lễ tại nhà hàng hoặc tư gia, giúp khách mời dễ dàng tìm đường qua Google Maps.</p>
        </div>

        <div class="feature-card">
            <div class="feature-icon-wrap icon-cloud"><i class="fa-solid fa-bolt"></i></div>
            <h3>Kiến trúc Serverless tốc độ cao</h3>
            <p>Lưu trữ đám mây hiện đại, tối ưu hóa hình ảnh, tải trang tức thì dưới 1 giây và sẵn sàng chia sẻ lên Zalo, Facebook.</p>
        </div>
    </div>
</section>

<!-- Template Showcase Section -->
<section class="showcase-section">
    <div class="showcase-container">
        <div class="section-header">
            <div class="section-tag">Kho giao diện</div>
            <h2 class="section-title">Mẫu thiệp cưới được yêu thích nhất</h2>
            <p class="section-desc">Từ phong cách truyền thống trang nghiêm đến hiện đại phương Tây, phong cách hoa lá tự nhiên lãng mạn.</p>
        </div>

        <div class="template-grid">
            <?php foreach ($featuredTemplates as $tpl): ?>
                <?php
                    $thumb = $tpl['thumbnail'] ?: '';
                    if (str_starts_with($thumb, 'uploads/') || str_starts_with($thumb, 'templates/')) {
                        $thumb = '/WeddingProject/' . ltrim($thumb, '/');
                    } elseif (!str_starts_with($thumb, '/') && !str_starts_with($thumb, 'http')) {
                        $thumb = '/WeddingProject/templates/' . rawurlencode($tpl['folder']) . '/' . ltrim($thumb, '/');
                    }
                ?>
                <article class="template-card">
                    <div class="template-thumb">
                        <img src="<?= htmlspecialchars($thumb, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($tpl['name'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=600&q=80'">
                        <div class="template-overlay">
                            <a href="/WeddingProject/mau-thiep/demo.php?template=<?= (int)$tpl['id'] ?>" class="btn-overlay demo">
                                <i class="fa-solid fa-eye"></i> Xem Demo
                            </a>
                            <form action="/WeddingProject/mau-thiep/start.php" method="post" style="display:inline;">
                                <input type="hidden" name="template_id" value="<?= (int)$tpl['id'] ?>">
                                <button type="submit" class="btn-overlay choose" style="border:none; cursor:pointer;">
                                    <i class="fa-solid fa-pen"></i> Chọn mẫu
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="template-body">
                        <h4><?= htmlspecialchars($tpl['name'], ENT_QUOTES, 'UTF-8') ?></h4>
                        <p><?= htmlspecialchars($tpl['description'] ?: 'Giao diện thiệp cưới trực quan và sang trọng', ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center;">
            <a href="/WeddingProject/mau-thiep/" class="btn-view-all">
                Xem tất cả <?= $totalTemplates ?> mẫu thiệp cưới <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>
</section>

<!-- Process Section -->
<section class="process-section" id="quy-trinh">
    <div class="section-header">
        <div class="section-tag">Cách thức hoạt động</div>
        <h2 class="section-title">Tạo thiệp cưới chỉ với 3 bước đơn giản</h2>
        <p class="section-desc">Không cần biết lập trình hay thiết kế, bất kỳ ai cũng có thể tự tạo thiệp cưới đẹp như ý.</p>
    </div>

    <div class="process-grid">
        <div class="process-step">
            <div class="step-number">1</div>
            <h3>Chọn mẫu ưng ý</h3>
            <p>Duyệt qua hơn 28+ mẫu thiệp được thiết kế sẵn, xem bản demo trực tiếp trên điện thoại để chọn phong cách phù hợp.</p>
        </div>

        <div class="process-step">
            <div class="step-number">2</div>
            <h3>Điền thông tin & Ảnh cưới</h3>
            <p>Nhập tên cô dâu chú rể, ngày giờ, địa điểm hôn lễ, câu chuyện tình yêu, tải album ảnh và nhạc nền trong trình soạn thảo trực quan.</p>
        </div>

        <div class="process-step">
            <div class="step-number">3</div>
            <h3>Xem trước & Gửi thiệp</h3>
            <p>Xem trước bản nháp thời gian thực, gửi Admin kiểm duyệt và nhận ngay liên kết độc quyền để gửi tới bạn bè, người thân.</p>
        </div>
    </div>
</section>

<!-- Call to Action Banner -->
<div class="cta-banner">
    <h2>Sẵn sàng bắt đầu chuẩn bị cho ngày vui?</h2>
    <p>Khởi tạo tấm thiệp cưới phong cách riêng của bạn ngay hôm nay. Hoàn toàn dễ dàng và miễn phí!</p>
    <a href="/WeddingProject/mau-thiep/" class="btn-cta-white">
        <i class="fa-solid fa-wand-magic-sparkles"></i> Bắt đầu tạo thiệp ngay
    </a>
</div>

<!-- Site Footer -->
<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-brand">
            <h4>Wedding Project</h4>
            <p>Nền tảng thiệp cưới điện tử trực quan, mang đến trải nghiệm hiện đại và trang trọng cho từng sự kiện cưới hỏi.</p>
        </div>

        <div class="footer-col">
            <h5>Liên kết nhanh</h5>
            <ul class="footer-links">
                <li><a href="/WeddingProject/">Trang chủ</a></li>
                <li><a href="/WeddingProject/mau-thiep/">Kho mẫu thiệp</a></li>
                <li><a href="/WeddingProject/my-weddings.php">Thiệp của tôi</a></li>
                <li><a href="/WeddingProject/login.php">Đăng nhập tài khoản</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h5>Tính năng</h5>
            <ul class="footer-links">
                <li><a href="#tinh-nang">Nhạc nền cưới</a></li>
                <li><a href="#tinh-nang">Album ảnh kỷ niệm</a></li>
                <li><a href="#tinh-nang">Mã QR mừng cưới</a></li>
                <li><a href="#tinh-nang">Sổ lưu bút Online</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h5>Hệ thống</h5>
            <ul class="footer-links">
                <li><a href="/WeddingProject/admin/">Quản trị Admin</a></li>
                <li><a href="/WeddingProject/register.php">Đăng ký thành viên</a></li>
                <li><a href="#">Chính sách bảo mật</a></li>
                <li><a href="#">Điều khoản sử dụng</a></li>
            </ul>
        </div>
    </div>

    <div class="footer-bottom">
        <div>&copy; <?= date('Y') ?> WeddingProject. All rights reserved. Phát triển theo chuẩn đồ án môn học.</div>
        <div style="display: flex; gap: 16px;">
            <span><i class="fa-solid fa-code"></i> PHP 8.x + MySQL</span>
            <span><i class="fa-solid fa-server"></i> Serverless Ready</span>
        </div>
    </div>
</footer>

<?= renderFlashSwal() ?>

</body>
</html>