<?php
// my-weddings.php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Bắt buộc đăng nhập
requireLogin();

$user = currentUser();
$userId = (int)$user['id'];

// Xử lý Xóa thiệp nháp (chỉ cho phép xóa khi status = 0 hoặc là Admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verifyCsrfToken()) {
        setFlash('error', 'Mã xác thực không hợp lệ. Vui lòng thử lại.');
    } else {
        $weddingId = (int)($_POST['wedding_id'] ?? 0);
        if ($weddingId > 0 && checkWeddingOwnership($pdo, $weddingId, $userId)) {
            // Kiểm tra trạng thái nếu không phải admin
            $checkStmt = $pdo->prepare("SELECT status FROM weddings WHERE id = :id LIMIT 1");
            $checkStmt->execute(['id' => $weddingId]);
            $wStatus = (int)$checkStmt->fetchColumn();

            if (isAdmin() || $wStatus === 0) {
                $delStmt = $pdo->prepare("DELETE FROM weddings WHERE id = :id");
                $delStmt->execute(['id' => $weddingId]);
                setFlash('success', 'Đã xóa thiệp cưới thành công.');
            } else {
                setFlash('error', 'Chỉ có thể xóa thiệp đang ở trạng thái Bản nháp.');
            }
        } else {
            setFlash('error', 'Bạn không có quyền xóa thiệp cưới này.');
        }
    }
    header("Location: my-weddings.php");
    exit;
}

// Thống kê nhanh của user
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS published,
        SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS draft
    FROM weddings
    WHERE user_id = :uid
");
$statsStmt->execute(['uid' => $userId]);
$stats = $statsStmt->fetch() ?: ['total' => 0, 'published' => 0, 'pending' => 0, 'draft' => 0];

// Lọc trạng thái
$filterStatus = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;

// Lấy danh sách thiệp của user
$sql = "
    SELECT 
        w.*,
        t.name AS template_name,
        t.folder AS template_folder,
        t.thumbnail AS template_thumb,
        (SELECT COUNT(*) FROM wedding_wishes ww WHERE ww.wedding_id = w.id) AS wish_count,
        (SELECT COUNT(*) FROM wedding_photos wp WHERE wp.wedding_id = w.id) AS photo_count
    FROM weddings w
    LEFT JOIN templates t ON t.id = w.template_id
    WHERE w.user_id = :uid
";
$params = ['uid' => $userId];

if ($filterStatus !== null) {
    $sql .= " AND w.status = :status";
    $params['status'] = $filterStatus;
}

$sql .= " ORDER BY w.id DESC";
$weddingsStmt = $pdo->prepare($sql);
$weddingsStmt->execute($params);
$myWeddings = $weddingsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thiệp cưới của tôi | Wedding Project</title>
    
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
            --accent-gold: #d97706;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --bg-page: #f8fafc;
            --card-border: #e2e8f0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-page);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Navigation Bar */
        .topbar {
            background: #ffffff;
            border-bottom: 1px solid var(--card-border);
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 4px 20px -5px rgba(0,0,0,0.05);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .brand-title {
            font-family: 'Great Vibes', cursive;
            font-size: 32px;
            color: var(--primary);
            font-weight: 700;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-item {
            color: #475569;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .nav-item:hover, .nav-item.active {
            color: var(--primary);
        }

        .user-dropdown {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-left: 16px;
            border-left: 1px solid #e2e8f0;
        }

        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #e11d48, #f43f5e);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .user-meta {
            line-height: 1.2;
        }

        .user-name {
            font-size: 13.5px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .user-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 999px;
            display: inline-block;
            background: #e2e8f0;
            color: #475569;
            text-transform: uppercase;
        }

        .badge-admin {
            background: #fef3c7;
            color: #92400e;
        }

        .btn-logout {
            color: #ef4444;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            margin-left: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Main Content Container */
        .main-wrapper {
            max-width: 1200px;
            margin: 36px auto;
            padding: 0 24px;
            flex: 1;
            width: 100%;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
        }

        .header-title h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .header-title p {
            color: var(--text-muted);
            font-size: 14.5px;
        }

        .btn-create-new {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            background: linear-gradient(135deg, var(--primary), #be123c);
            color: #ffffff;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            border-radius: 14px;
            box-shadow: 0 10px 20px -5px rgba(225, 29, 72, 0.4);
            transition: all 0.25s ease;
        }

        .btn-create-new:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 25px -5px rgba(225, 29, 72, 0.5);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: #fff;
            padding: 20px 22px;
            border-radius: 18px;
            border: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-info h3 {
            font-size: 12.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .icon-total { background: #f1f5f9; color: #475569; }
        .icon-published { background: #dcfce7; color: #16a34a; }
        .icon-pending { background: #fef3c7; color: #d97706; }
        .icon-draft { background: #fee2e2; color: #dc2626; }

        /* Filter Tab Bar */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
            overflow-x: auto;
            padding-bottom: 6px;
        }

        .filter-btn {
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            color: #475569;
            background: #ffffff;
            border: 1px solid var(--card-border);
            transition: all 0.2s;
            white-space: nowrap;
        }

        .filter-btn:hover {
            border-color: #cbd5e1;
            color: var(--text-dark);
        }

        .filter-btn.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(225, 29, 72, 0.25);
        }

        /* Wedding Card Grid */
        .wedding-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }

        .wedding-card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid var(--card-border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            transition: all 0.25s ease;
        }

        .wedding-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.08);
            border-color: #cbd5e1;
        }

        .card-cover {
            height: 190px;
            position: relative;
            background: #f1f5f9;
            overflow: hidden;
        }

        .card-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .wedding-card:hover .card-cover img {
            transform: scale(1.05);
        }

        .cover-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 60%);
            display: flex;
            align-items: flex-end;
            padding: 16px;
        }

        .couple-names {
            color: #ffffff;
            font-size: 19px;
            font-weight: 700;
            text-shadow: 0 2px 8px rgba(0,0,0,0.6);
        }

        .badge-status {
            position: absolute;
            top: 14px;
            right: 14px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            backdrop-filter: blur(8px);
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-0 { background: rgba(239, 68, 68, 0.9); color: #fff; } /* Nháp */
        .status-1 { background: rgba(34, 197, 94, 0.9); color: #fff; } /* Đã duyệt */
        .status-2 { background: rgba(245, 158, 11, 0.9); color: #fff; } /* Chờ duyệt */
        .status-3 { background: rgba(100, 116, 139, 0.9); color: #fff; } /* Ẩn */

        .card-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .wedding-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
            font-size: 13.5px;
            color: #64748b;
        }

        .meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .meta-row i {
            color: var(--primary);
            width: 16px;
            text-align: center;
        }

        .card-stats {
            display: flex;
            gap: 16px;
            padding: 12px 0;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 16px;
            font-size: 12.5px;
            color: #64748b;
        }

        .card-actions {
            margin-top: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .btn-action {
            padding: 9px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-edit {
            background: #fff1f2;
            color: var(--primary);
            flex: 1;
            justify-content: center;
        }

        .btn-edit:hover {
            background: var(--primary);
            color: #ffffff;
        }

        .btn-preview {
            background: #f1f5f9;
            color: #334155;
        }

        .btn-preview:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .btn-public {
            background: #dcfce7;
            color: #16a34a;
        }

        .btn-public:hover {
            background: #16a34a;
            color: #ffffff;
        }

        .btn-submit-review {
            background: #fef3c7;
            color: #b45309;
        }

        .btn-submit-review:hover {
            background: #f59e0b;
            color: #ffffff;
        }

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
            padding: 9px 12px;
        }

        .btn-delete:hover {
            background: #dc2626;
            color: #fff;
        }

        /* Empty State */
        .empty-state {
            background: #ffffff;
            border-radius: 24px;
            border: 2px dashed #e2e8f0;
            padding: 60px 24px;
            text-align: center;
            max-width: 560px;
            margin: 40px auto;
        }

        .empty-icon {
            font-size: 54px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-muted);
            font-size: 14.5px;
            margin-bottom: 24px;
            line-height: 1.6;
        }

        /* Footer */
        .app-footer {
            margin-top: 60px;
            padding: 24px 0;
            border-top: 1px solid var(--card-border);
            text-align: center;
            color: #94a3b8;
            font-size: 13.5px;
            background: #fff;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<header class="topbar">
    <div class="nav-container">
        <a href="/WeddingProject/" class="brand-link">
            <span class="brand-title">Wedding Project</span>
        </a>

        <nav class="nav-links">
            <a href="/WeddingProject/" class="nav-item">
                <i class="fa-solid fa-house"></i> Trang chủ
            </a>
            <a href="/WeddingProject/mau-thiep/" class="nav-item">
                <i class="fa-solid fa-layer-group"></i> Kho mẫu thiệp
            </a>
            <a href="/WeddingProject/my-weddings.php" class="nav-item active">
                <i class="fa-solid fa-heart"></i> Thiệp của tôi
            </a>

            <?php if (isAdmin()): ?>
                <a href="/WeddingProject/admin/" class="nav-item" style="color: #d97706;">
                    <i class="fa-solid fa-shield-halved"></i> Quản trị Admin
                </a>
            <?php endif; ?>

            <div class="user-dropdown">
                <div class="avatar-circle">
                    <?= mb_strtoupper(mb_substr($user['full_name'], 0, 1)) ?>
                </div>
                <div class="user-meta">
                    <div class="user-name"><?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
                    <span class="user-badge <?= $user['role'] === 'admin' ? 'badge-admin' : '' ?>">
                        <?= $user['role'] === 'admin' ? 'Quản trị viên' : 'Khách hàng' ?>
                    </span>
                </div>
                <a href="/WeddingProject/logout.php" class="btn-logout" title="Đăng xuất">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </nav>
    </div>
</header>

<!-- Main Dashboard Container -->
<main class="main-wrapper">
    <div class="page-header">
        <div class="header-title">
            <h1>Thiệp cưới của tôi</h1>
            <p>Quản lý các bản thiệp cưới, theo dõi trạng thái kiểm duyệt và chỉnh sửa nội dung dễ dàng.</p>
        </div>
        <a href="/WeddingProject/mau-thiep/" class="btn-create-new">
            <i class="fa-solid fa-plus"></i> Tạo thiệp mới
        </a>
    </div>

    <!-- Quick Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-info">
                <h3>Tổng số thiệp</h3>
                <div class="stat-value"><?= (int)$stats['total'] ?></div>
            </div>
            <div class="stat-icon icon-total">
                <i class="fa-solid fa-envelopes-bulk"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <h3>Đã xuất bản</h3>
                <div class="stat-value"><?= (int)$stats['published'] ?></div>
            </div>
            <div class="stat-icon icon-published">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <h3>Chờ duyệt</h3>
                <div class="stat-value"><?= (int)$stats['pending'] ?></div>
            </div>
            <div class="stat-icon icon-pending">
                <i class="fa-solid fa-clock"></i>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-info">
                <h3>Bản nháp</h3>
                <div class="stat-value"><?= (int)$stats['draft'] ?></div>
            </div>
            <div class="stat-icon icon-draft">
                <i class="fa-solid fa-file-pen"></i>
            </div>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div class="filter-bar">
        <a href="my-weddings.php" class="filter-btn <?= $filterStatus === null ? 'active' : '' ?>">
            Tất cả (<?= (int)$stats['total'] ?>)
        </a>
        <a href="my-weddings.php?status=1" class="filter-btn <?= $filterStatus === 1 ? 'active' : '' ?>">
            <i class="fa-solid fa-circle-check"></i> Đã xuất bản (<?= (int)$stats['published'] ?>)
        </a>
        <a href="my-weddings.php?status=2" class="filter-btn <?= $filterStatus === 2 ? 'active' : '' ?>">
            <i class="fa-solid fa-clock"></i> Chờ duyệt (<?= (int)$stats['pending'] ?>)
        </a>
        <a href="my-weddings.php?status=0" class="filter-btn <?= $filterStatus === 0 ? 'active' : '' ?>">
            <i class="fa-solid fa-file-pen"></i> Bản nháp (<?= (int)$stats['draft'] ?>)
        </a>
    </div>

    <?php if (empty($myWeddings)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fa-regular fa-folder-open"></i></div>
            <h3>Bạn chưa có thiệp cưới nào ở mục này</h3>
            <p>Hãy duyệt kho mẫu thiệp cưới phong phú và khởi tạo ngay tấm thiệp điện tử độc đáo cho ngày trọng đại!</p>
            <a href="/WeddingProject/mau-thiep/" class="btn-create-new">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Khám phá kho mẫu thiệp
            </a>
        </div>
    <?php else: ?>
        <div class="wedding-grid">
            <?php foreach ($myWeddings as $w): ?>
                <?php
                    $status = (int)$w['status'];
                    $groom = htmlspecialchars($w['groom_name'] ?: 'Chú rể', ENT_QUOTES, 'UTF-8');
                    $bride = htmlspecialchars($w['bride_name'] ?: 'Cô dâu', ENT_QUOTES, 'UTF-8');
                    $slug  = htmlspecialchars($w['slug'], ENT_QUOTES, 'UTF-8');
                    $token = htmlspecialchars($w['edit_token'], ENT_QUOTES, 'UTF-8');
                    
                    // Ảnh bìa ưu tiên: background_image -> template_thumb -> fallback
                    $coverImg = '/WeddingProject/uploads/default-wedding.jpg';
                    if (!empty($w['background_image'])) {
                        $coverImg = $w['background_image'];
                    } elseif (!empty($w['template_thumb'])) {
                        $coverImg = $w['template_thumb'];
                    }

                    $statusLabels = [
                        0 => ['label' => 'Bản nháp', 'icon' => 'fa-file-pen', 'class' => 'status-0'],
                        1 => ['label' => 'Đã xuất bản', 'icon' => 'fa-circle-check', 'class' => 'status-1'],
                        2 => ['label' => 'Đang chờ duyệt', 'icon' => 'fa-clock', 'class' => 'status-2'],
                        3 => ['label' => 'Đang ẩn', 'icon' => 'fa-eye-slash', 'class' => 'status-3']
                    ];
                    $stInfo = $statusLabels[$status] ?? $statusLabels[0];
                ?>
                <article class="wedding-card">
                    <div class="card-cover">
                        <img src="<?= htmlspecialchars($coverImg, ENT_QUOTES, 'UTF-8') ?>" alt="Ảnh thiệp cưới" onerror="this.src='https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=600&q=80'">
                        <div class="cover-overlay">
                            <div class="couple-names"><?= $groom ?> &hearts; <?= $bride ?></div>
                        </div>
                        <div class="badge-status <?= $stInfo['class'] ?>">
                            <i class="fa-solid <?= $stInfo['icon'] ?>"></i> <?= $stInfo['label'] ?>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="wedding-meta">
                            <div class="meta-row">
                                <i class="fa-solid fa-palette"></i>
                                <span>Mẫu: <strong><?= htmlspecialchars($w['template_name'] ?: 'Mặc định', ENT_QUOTES, 'UTF-8') ?></strong></span>
                            </div>
                            <div class="meta-row">
                                <i class="fa-regular fa-calendar-days"></i>
                                <span>Ngày cưới: <?= !empty($w['wedding_date']) ? date('d/m/Y', strtotime($w['wedding_date'])) : 'Chưa thiết lập' ?></span>
                            </div>
                            <div class="meta-row">
                                <i class="fa-solid fa-location-dot"></i>
                                <span><?= htmlspecialchars(mb_strimwidth($w['venue'] ?: ($w['address'] ?: 'Chưa cập nhật địa điểm'), 0, 38, '...'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>

                        <div class="card-stats">
                            <span><i class="fa-regular fa-images"></i> <?= (int)$w['photo_count'] ?> ảnh</span>
                            <span><i class="fa-regular fa-comment-dots"></i> <?= (int)$w['wish_count'] ?> lời chúc</span>
                            <span><i class="fa-regular fa-clock"></i> <?= date('d/m/y', strtotime($w['created_at'])) ?></span>
                        </div>

                        <div class="card-actions">
                            <!-- Nút chỉnh sửa -->
                            <a href="/WeddingProject/editor/?token=<?= urlencode($w['edit_token']) ?>" class="btn-action btn-edit" title="Chỉnh sửa thiệp cưới">
                                <i class="fa-solid fa-pen-to-square"></i> Chỉnh sửa
                            </a>

                            <!-- Nút xem trước -->
                            <a href="/WeddingProject/w/?slug=<?= urlencode($w['slug']) ?>&preview=1&token=<?= urlencode($w['edit_token']) ?>" target="_blank" class="btn-action btn-preview" title="Xem trước bản nháp">
                                <i class="fa-solid fa-eye"></i> Xem trước
                            </a>

                            <!-- Nếu đã xuất bản (status=1) -> Nút mở công khai & copy link -->
                            <?php if ($status === 1): ?>
                                <a href="/WeddingProject/w/?slug=<?= urlencode($w['slug']) ?>" target="_blank" class="btn-action btn-public" title="Xem thiệp công khai">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Mở link
                                </a>
                                <button type="button" class="btn-action btn-preview" onclick="copyWeddingLink('<?= htmlspecialchars($w['slug'], ENT_QUOTES, 'UTF-8') ?>')" title="Sao chép liên kết">
                                    <i class="fa-regular fa-copy"></i>
                                </button>
                            <?php endif; ?>

                            <!-- Nếu là bản nháp (status=0) -> Nút gửi Admin duyệt -->
                            <?php if ($status === 0): ?>
                                <form action="/WeddingProject/submit/review.php" method="post" style="display:inline;">
                                    <input type="hidden" name="token" value="<?= htmlspecialchars($w['edit_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn-action btn-submit-review" title="Gửi Admin duyệt thiệp để xuất bản">
                                        <i class="fa-solid fa-paper-plane"></i> Gửi duyệt
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Nút xóa (chỉ khi là bản nháp hoặc admin) -->
                            <?php if ($status === 0 || isAdmin()): ?>
                                <button type="button" class="btn-action btn-delete" onclick="confirmDelete(<?= (int)$w['id'] ?>, '<?= addslashes($groom . ' - ' . $bride) ?>')" title="Xóa thiệp">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Delete Hidden Form -->
<form id="deleteForm" method="post" action="my-weddings.php" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="wedding_id" id="deleteWeddingId" value="">
</form>

<footer class="app-footer">
    <p>&copy; <?= date('Y') ?> WeddingProject - Nền tảng tạo thiệp cưới online cao cấp.</p>
</footer>

<?= renderFlashSwal() ?>

<script>
function copyWeddingLink(slug) {
    const url = window.location.origin + '/WeddingProject/w/?slug=' + encodeURIComponent(slug);
    navigator.clipboard.writeText(url).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Đã sao chép liên kết!',
            text: url,
            timer: 2000,
            showConfirmButton: false,
            confirmButtonColor: '#e11d48'
        });
    });
}

function confirmDelete(id, name) {
    Swal.fire({
        title: 'Xóa thiệp cưới?',
        text: 'Bạn có chắc chắn muốn xóa thiệp "' + name + '"? Thao tác này không thể hoàn tác.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Đồng ý xóa',
        cancelButtonText: 'Hủy bỏ'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('deleteWeddingId').value = id;
            document.getElementById('deleteForm').submit();
        }
    });
}
</script>

</body>
</html>
