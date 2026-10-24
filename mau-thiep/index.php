<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$user = currentUser();

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function templateImage(?string $thumbnail, string $folder): string
{
    $thumbnail = trim((string)$thumbnail);

    if ($thumbnail === '') {
        return '';
    }

    if (preg_match('~^https?://~i', $thumbnail)) {
        return $thumbnail;
    }

    if (str_starts_with($thumbnail, '/')) {
        return $thumbnail;
    }

    if (
        str_starts_with($thumbnail, 'uploads/') ||
        str_starts_with($thumbnail, 'templates/')
    ) {
        return '../' . ltrim($thumbnail, '/');
    }

    return '../templates/' . rawurlencode($folder) . '/' . ltrim($thumbnail, '/');
}

$templates = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, name, folder, thumbnail, description
            FROM templates
            WHERE status = 1
            ORDER BY id ASC
        ");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        $templates = [];
    }
}

// Fallback nạp toàn bộ 28 mẫu thiệp nếu DB chưa sẵn sàng
if (empty($templates)) {
    for ($i = 1; $i <= 28; $i++) {
        $pad = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $templates[] = [
            'id' => $i,
            'name' => "Mẫu Thiệp Cưới Cao Cấp #$pad",
            'folder' => "template$pad",
            'thumbnail' => "templates/template$pad/thumb.jpg",
            'description' => "Thiết kế hiện đại, tinh tế với đầy đủ thiệp mừng, album ảnh cưới, bản đồ chỉ đường và đếm ngược."
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mẫu thiệp cưới online</title>
    <meta
        name="description"
        content="Khám phá các mẫu thiệp cưới online, xem bản demo và tự chỉnh sửa mẫu bạn yêu thích."
    >
    <!-- Google Fonts & Plugins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
            background: #f8fafc;
            color: #171717;
        }

        a { color: inherit; text-decoration: none; }
        button, a { font: inherit; }

        .topbar {
            height: 64px;
            background: rgba(255,255,255,.96);
            border-bottom: 1px solid #ededed;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 50;
            backdrop-filter: blur(10px);
        }

        .brand {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -.4px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .brand span { color: #e11d48; }

        .top-nav {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .top-link {
            font-size: 13.5px;
            font-weight: 600;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .top-link:hover { color: #e11d48; }

        .btn-top-auth {
            padding: 7px 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .btn-top-auth.primary {
            background: #e11d48;
            color: #ffffff;
            border-color: #e11d48;
        }

        .btn-top-auth.primary:hover {
            background: #be123c;
        }

        .hero {
            text-align: center;
            padding: 58px 20px 38px;
            background: linear-gradient(180deg, #fff 0%, #fafafa 100%);
        }

        .eyebrow {
            display: inline-block;
            color: #a51c2b;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.8px;
            margin-bottom: 10px;
        }

        .hero h1 {
            margin: 0;
            font-size: clamp(30px, 4vw, 48px);
            line-height: 1.08;
            letter-spacing: -1.6px;
        }

        .hero p {
            max-width: 690px;
            margin: 16px auto 0;
            color: #777;
            font-size: 15px;
            line-height: 1.7;
        }

        .catalog {
            width: min(1180px, calc(100% - 36px));
            margin: 0 auto;
            padding: 26px 0 90px;
        }

        .section-head {
            text-align: center;
            margin-bottom: 28px;
        }

        .section-head h2 {
            margin: 0;
            font-size: 25px;
            letter-spacing: -.6px;
        }

        .section-head p {
            margin: 8px 0 0;
            color: #888;
            font-size: 14px;
        }

        .template-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 18px;
        }

        .template-card { min-width: 0; }

        .template-visual {
            position: relative;
            aspect-ratio: 3 / 4.8;
            overflow: hidden;
            border-radius: 12px;
            background: #e9e9e9;
            box-shadow: 0 3px 14px rgba(0,0,0,.08);
        }

        .template-visual img,
        .placeholder {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            transition: transform .38s ease;
        }

        .placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 20px;
            background: linear-gradient(145deg, #eee, #ddd);
            color: #777;
            font-size: 15px;
            font-weight: 700;
        }

        .template-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 10px;
            padding: 20px;
            background: rgba(0,0,0,.50);
            opacity: 0;
            transition: opacity .25s ease;
        }

        .template-card:hover .template-overlay,
        .template-card:focus-within .template-overlay {
            opacity: 1;
        }

        .template-card:hover .template-visual img {
            transform: scale(1.04);
        }

        .demo-btn,
        .edit-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 150px;
            padding: 11px 17px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
            transition: transform .18s ease;
        }

        .demo-btn {
            background: #fff;
            color: #222;
        }

        .edit-btn {
            border: 0;
            background: #a51c2b;
            color: #fff;
            cursor: pointer;
        }

        .demo-btn:hover,
        .edit-btn:hover {
            transform: translateY(-2px);
        }

        .template-name {
            text-align: center;
            margin: 11px 2px 0;
            font-size: 14px;
            font-weight: 800;
            color: #222;
        }

        .template-description {
            text-align: center;
            margin: 5px 4px 0;
            min-height: 34px;
            color: #999;
            font-size: 11px;
            line-height: 1.45;
        }

        .empty {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }

        .how-it-works {
            margin-top: 55px;
            padding: 35px 20px;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 18px;
            text-align: center;
        }

        .how-it-works h2 {
            margin: 0 0 22px;
            font-size: 23px;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }

        .step {
            padding: 12px;
            color: #666;
            font-size: 13px;
            line-height: 1.6;
        }

        .step strong {
            display: block;
            color: #222;
            margin-bottom: 4px;
        }

        @media (max-width: 1050px) {
            .template-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }

        @media (max-width: 780px) {
            .topbar { padding: 0 16px; }
            .template-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
            .hero { padding-top: 42px; }
            .steps { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 520px) {
            .template-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .template-description { display: none; }

            .template-overlay {
                opacity: 1;
                background: linear-gradient(transparent 42%, rgba(0,0,0,.62));
                justify-content: flex-end;
                padding: 10px;
            }

            .demo-btn,
            .edit-btn {
                min-width: auto;
                width: 100%;
                padding: 8px 9px;
                font-size: 10px;
            }

            .steps { grid-template-columns: 1fr; }
        }
        @media (max-width: 520px) {
    .hero .eyebrow {
        font-size: 12px;
        letter-spacing: 1.5px;
    }

    .hero h1 {
        font-size: 32px;
        line-height: 1.2;
    }

    .hero p {
        font-size: 14px;
        line-height: 1.6;
        padding: 0 10px;
    }
}
    </style>
</head>
<body>

<header class="topbar">
    <a class="brand" href="/WeddingProject/">
        <i class="fa-solid fa-heart" style="color: #e11d48; font-size: 18px;"></i> Thiệp <span>Cưới Online</span>
    </a>
    <nav class="top-nav">
        <a class="top-link" href="/WeddingProject/"><i class="fa-solid fa-house"></i> Trang chủ</a>
        <a class="top-link" href="#mau-thiep"><i class="fa-solid fa-layer-group"></i> Mẫu thiệp</a>
        <?php if ($user): ?>
            <a class="top-link" href="/WeddingProject/my-weddings.php"><i class="fa-solid fa-heart"></i> Thiệp của tôi</a>
            <?php if (isAdmin()): ?>
                <a class="top-link" href="/WeddingProject/admin/" style="color: #d97706;"><i class="fa-solid fa-shield-halved"></i> Quản trị</a>
            <?php endif; ?>
            <span style="font-size: 13.5px; font-weight: 600; color: #1e293b;">
                Hi, <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <a class="btn-top-auth" href="/WeddingProject/logout.php" style="color: #ef4444;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Đăng xuất</a>
        <?php else: ?>
            <a class="btn-top-auth" href="/WeddingProject/login.php"><i class="fa-solid fa-right-to-bracket"></i> Đăng nhập</a>
            <a class="btn-top-auth primary" href="/WeddingProject/register.php"><i class="fa-solid fa-user-plus"></i> Đăng ký</a>
        <?php endif; ?>
    </nav>
</header>

<section class="hero">
    <div class="eyebrow">THIỆP CƯỚI ONLINE</div>
    <h1>Chọn mẫu thiệp cưới<br>cậu yêu thích</h1>
    <p>
        Xem bản thiệp mẫu hoàn chỉnh trước khi quyết định.
        Nếu ưng mẫu, cậu có thể tự chỉnh sửa thông tin của mình ngay trên hệ thống.
    </p>
</section>

<main class="catalog" id="mau-thiep">
    <div class="section-head">
        <h2>Các mẫu thiệp cưới</h2>
        <p>Ấn vào mẫu để xem thiệp thật • Kéo xuống cuối để chỉnh sửa</p>
    </div>

    <?php if (!$templates): ?>
        <div class="empty">
            Hiện chưa có mẫu thiệp nào được bật.
        </div>
    <?php else: ?>
        <div class="template-grid">
            <?php foreach ($templates as $template): ?>
                <?php
                    $image = templateImage(
                        $template['thumbnail'] ?? '',
                        (string)$template['folder']
                    );
                    $name = (string)$template['name'];
                    $description = trim((string)($template['description'] ?? ''));
                ?>

                <article class="template-card">
                    <div class="template-visual">
                        <a
                            href="demo.php?template=<?= (int)$template['id'] ?>"
                            aria-label="Xem mẫu <?= e($name) ?>"
                        >
                            <?php if ($image): ?>
                                <img
                                    src="<?= e($image) ?>"
                                    alt="<?= e($name) ?>"
                                    loading="lazy"
                                    onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=600&q=80';"
                                >
                            <?php else: ?>
                                <div class="placeholder">
                                    <?= e($name) ?>
                                </div>
                            <?php endif; ?>
                        </a>

                        <div class="template-overlay">
                            <a
                                class="demo-btn"
                                href="demo.php?template=<?= (int)$template['id'] ?>"
                            >
                                👁 XEM MẪU
                            </a>

                            <form action="start.php" method="post">
                                <input
                                    type="hidden"
                                    name="template_id"
                                    value="<?= (int)$template['id'] ?>"
                                >
                                <button type="submit" class="edit-btn">
                                    ✏️ CHỈNH SỬA
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="template-name">
                        <?= e($name) ?>
                    </div>

                    <?php if ($description !== ''): ?>
                        <div class="template-description">
                            <?= e($description) ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    
</main>

<?= renderFlashSwal() ?>

</body>
</html>
