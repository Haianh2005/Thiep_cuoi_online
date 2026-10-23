<?php
// admin/templates/index.php - Quản lý mẫu thiệp cưới (FR-18)
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

requireAdmin();

$currentAdmin = currentUser();

// Xử lý POST actions (Đổi trạng thái ẩn/hiện, Cào mẫu)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        setFlash('error', 'Token bảo mật không hợp lệ. Vui lòng tải lại trang.');
    } else {
        $action = $_POST['action'] ?? '';
        $templateId = (int)($_POST['template_id'] ?? 0);

        if ($action === 'toggle_status' && $templateId > 0) {
            $stmt = $pdo->prepare("SELECT status, name FROM templates WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $templateId]);
            $tpl = $stmt->fetch();
            if ($tpl) {
                $newStatus = ((int)$tpl['status'] === 1) ? 0 : 1;
                $upd = $pdo->prepare("UPDATE templates SET status = :st WHERE id = :id");
                $upd->execute(['st' => $newStatus, 'id' => $templateId]);
                setFlash('success', "Đã " . ($newStatus === 1 ? 'kích hoạt hiển thị' : 'tạm ẩn') . " mẫu thiệp: " . htmlspecialchars($tpl['name']));
            } else {
                setFlash('error', 'Không tìm thấy mẫu thiệp.');
            }
        } elseif ($action === 'run_crawler') {
            // Kích hoạt cào dữ liệu từ mongmer.com
            $crawlerScript = __DIR__ . '/../../tools/crawler_mongmer.php';
            if (file_exists($crawlerScript)) {
                $phpPath = 'c:\\xampp3\\php\\php.exe';
                if (!file_exists($phpPath)) {
                    $phpPath = 'php';
                }
                $output = shell_exec("\"$phpPath\" \"$crawlerScript\" 2>&1");
                setFlash('success', 'Đã thực thi công cụ cào mẫu thiệp thành công! Kết quả: ' . htmlspecialchars(substr($output ?? 'Hoàn tất', -150)));
            } else {
                setFlash('error', 'Không tìm thấy file kịch bản cào dữ liệu tools/crawler_mongmer.php');
            }
        }
    }
    header("Location: index.php?" . http_build_query($_GET));
    exit;
}

// Thống kê nhanh
$totalTemplates = (int)$pdo->query("SELECT COUNT(*) FROM templates")->fetchColumn();
$activeTemplates = (int)$pdo->query("SELECT COUNT(*) FROM templates WHERE status = 1")->fetchColumn();
$hiddenTemplates = (int)$pdo->query("SELECT COUNT(*) FROM templates WHERE status = 0")->fetchColumn();

// Tìm kiếm & Lọc
$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;

$countSql = "SELECT COUNT(*) FROM templates WHERE 1=1";
$listSql = "
    SELECT 
        t.*,
        (SELECT COUNT(*) FROM weddings w WHERE w.template_id = t.id) AS wedding_usage
    FROM templates t
    WHERE 1=1
";
$params = [];

if ($keyword !== '') {
    $countSql .= " AND (name LIKE :kw OR folder LIKE :kw OR description LIKE :kw)";
    $listSql  .= " AND (name LIKE :kw OR folder LIKE :kw OR description LIKE :kw)";
    $params['kw'] = "%$keyword%";
}

if ($statusFilter !== null) {
    $countSql .= " AND status = :st";
    $listSql  .= " AND status = :st";
    $params['st'] = $statusFilter;
}

// Phân trang
$pagination = paginate($pdo, $countSql, $params, 12);
$listSql .= " ORDER BY t.id ASC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($listSql);
foreach ($params as $k => $v) {
    $stmt->bindValue(":$k", $v);
}
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

function resolveThumb(?string $thumbnail, string $folder): string {
    $thumbnail = trim((string)$thumbnail);
    if ($thumbnail === '') return '';
    if (preg_match('~^https?://~i', $thumbnail) || str_starts_with($thumbnail, '/')) {
        return $thumbnail;
    }
    return '/WeddingProject/templates/' . rawurlencode($folder) . '/' . ltrim($thumbnail, '/');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Mẫu thiệp cưới | Admin Portal</title>

    <!-- Google Fonts & Plugins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --wine: #8b1e2d;
            --wine-dark: #691520;
            --wine-light: #f8e9eb;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --bg-body: #f8fafc;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-body);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: #ffffff;
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 50;
        }

        .sidebar-brand {
            padding: 24px;
            font-size: 20px;
            font-weight: 800;
            color: var(--wine);
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid var(--border);
        }

        .sidebar-menu {
            padding: 20px 12px;
            flex: 1;
            overflow-y: auto;
        }

        .menu-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 700;
            padding: 12px 14px 6px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #475569;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
            transition: all 0.2s;
        }

        .nav-item:hover {
            background: var(--wine-light);
            color: var(--wine);
        }

        .nav-item.active {
            background: var(--wine);
            color: #ffffff;
        }

        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .sidebar-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--wine-light);
            color: var(--wine);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        /* Main Content */
        .main-wrapper {
            margin-left: 260px;
            flex: 1;
            padding: 32px 40px;
            max-width: 1500px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 28px;
        }

        .page-title h1 {
            font-size: 26px;
            font-weight: 800;
            color: #1e293b;
        }

        .page-title p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn-crawl {
            background: linear-gradient(135deg, #0284c7, #0ea5e9);
            color: #fff;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.25);
            transition: all 0.2s;
        }

        .btn-crawl:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }

        /* Stat Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 14px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.total { background: #eff6ff; color: #2563eb; }
        .stat-icon.active { background: #ecfdf5; color: #059669; }
        .stat-icon.hidden { background: #fef2f2; color: #dc2626; }

        .stat-val {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* Filters & Search */
        .filter-bar {
            background: #fff;
            padding: 16px 20px;
            border-radius: 14px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 260px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .filter-tabs {
            display: flex;
            gap: 6px;
        }

        .filter-tab {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s;
        }

        .filter-tab:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .filter-tab.active {
            background: var(--wine);
            color: #fff;
        }

        /* Templates Grid */
        .tpl-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .tpl-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.25s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        .tpl-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
            border-color: #cbd5e1;
        }

        .tpl-thumb-wrap {
            position: relative;
            aspect-ratio: 16/10;
            background: #f1f5f9;
            overflow: hidden;
        }

        .tpl-thumb-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .tpl-card:hover .tpl-thumb-wrap img {
            transform: scale(1.05);
        }

        .tpl-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            backdrop-filter: blur(8px);
        }

        .tpl-badge.active {
            background: rgba(16, 185, 129, 0.9);
            color: #fff;
        }

        .tpl-badge.hidden {
            background: rgba(100, 116, 139, 0.9);
            color: #fff;
        }

        .tpl-body {
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .tpl-title {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .tpl-folder {
            font-size: 12px;
            font-family: monospace;
            color: var(--wine);
            background: var(--wine-light);
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            margin-bottom: 12px;
            align-self: flex-start;
        }

        .tpl-desc {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 16px;
            flex: 1;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .tpl-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }

        .tpl-usage {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tpl-actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #fff;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 13px;
        }

        .btn-icon:hover {
            background: #f8fafc;
            color: var(--wine);
            border-color: #cbd5e1;
        }

        .btn-icon.toggle-on {
            color: #059669;
            background: #ecfdf5;
            border-color: #a7f3d0;
        }

        .btn-icon.toggle-off {
            color: #dc2626;
            background: #fef2f2;
            border-color: #fecaca;
        }

        /* Pagination */
        .pagination-wrapper {
            margin-top: 32px;
            display: flex;
            justify-content: center;
        }

        .pagination {
            display: flex;
            list-style: none;
            gap: 6px;
        }

        .page-item .page-link {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #fff;
            color: #475569;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .page-item.active .page-link {
            background: var(--wine);
            color: #fff;
            border-color: var(--wine);
        }

        .page-item .page-link:hover {
            background: #f1f5f9;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-heart-pulse"></i> Wedding Admin
        </div>

        <div class="sidebar-menu">
            <div class="menu-title">Quản lý tổng quan</div>
            <a href="../index.php" class="nav-item">
                <i class="fa-solid fa-chart-pie"></i> Dashboard
            </a>
            <a href="../weddings/" class="nav-item">
                <i class="fa-solid fa-envelope-open-text"></i> Quản lý thiệp
            </a>
            <a href="../users/" class="nav-item">
                <i class="fa-solid fa-users"></i> Người dùng
            </a>
            <a href="index.php" class="nav-item active">
                <i class="fa-solid fa-palette"></i> Mẫu thiệp cưới
            </a>
            <a href="../wishes/" class="nav-item">
                <i class="fa-solid fa-comments"></i> Lời chúc mừng
            </a>

            <div class="menu-title" style="margin-top: 20px;">Lối tắt</div>
            <a href="../../index.php" target="_blank" class="nav-item">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Xem website
            </a>
            <a href="../../logout.php" class="nav-item" style="color: #ef4444;">
                <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
            </a>
        </div>

        <div class="sidebar-footer">
            <div class="admin-info">
                <div class="admin-avatar">
                    <?= strtoupper(substr($currentAdmin['username'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 13px;"><?= htmlspecialchars($currentAdmin['full_name']) ?></div>
                    <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase;">Quản trị viên</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-wrapper">
        <div class="header-top">
            <div class="page-title">
                <h1>Quản lý Mẫu thiệp cưới (FR-18)</h1>
                <p>Quản lý kho 28+ giao diện thiệp động, cấu hình hiển thị và đồng bộ mẫu mới</p>
            </div>
            <div class="header-actions">
                <form method="POST" id="crawlForm" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="run_crawler">
                    <button type="button" class="btn-crawl" onclick="confirmCrawl()">
                        <i class="fa-solid fa-robot"></i> Cào mẫu mới (Mongmer.com)
                    </button>
                </form>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total"><i class="fa-solid fa-layer-group"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($totalTemplates) ?></div>
                    <div class="stat-label">Tổng số mẫu giao diện</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active"><i class="fa-solid fa-check-circle"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($activeTemplates) ?></div>
                    <div class="stat-label">Đang hiển thị cho khách</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon hidden"><i class="fa-solid fa-eye-slash"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($hiddenTemplates) ?></div>
                    <div class="stat-label">Đang tạm ẩn / Khóa</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="Tìm theo tên mẫu hoặc thư mục...">
                <?php if ($statusFilter !== null): ?>
                    <input type="hidden" name="status" value="<?= $statusFilter ?>">
                <?php endif; ?>
            </form>

            <div class="filter-tabs">
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => ''])) ?>" class="filter-tab <?= ($statusFilter === null) ? 'active' : '' ?>">Tất cả</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => '1'])) ?>" class="filter-tab <?= ($statusFilter === 1) ? 'active' : '' ?>">Đang hoạt động</a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => '0'])) ?>" class="filter-tab <?= ($statusFilter === 0) ? 'active' : '' ?>">Tạm ẩn</a>
            </div>
        </div>

        <!-- Grid Cards -->
        <?php if (empty($templates)): ?>
            <div style="background: #fff; padding: 48px; text-align: center; border-radius: 14px; border: 1px solid var(--border);">
                <i class="fa-solid fa-palette" style="font-size: 40px; color: #cbd5e1; margin-bottom: 12px;"></i>
                <h3 style="font-size: 18px; color: #475569;">Không tìm thấy mẫu thiệp nào phù hợp</h3>
                <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">Vui lòng thử tìm kiếm với từ khóa khác.</p>
            </div>
        <?php else: ?>
            <div class="tpl-grid">
                <?php foreach ($templates as $t): ?>
                    <?php 
                        $thumbUrl = resolveThumb($t['thumbnail'], $t['folder']); 
                        $isActive = ((int)$t['status'] === 1);
                    ?>
                    <div class="tpl-card">
                        <div class="tpl-thumb-wrap">
                            <?php if ($thumbUrl): ?>
                                <img src="<?= htmlspecialchars($thumbUrl) ?>" alt="<?= htmlspecialchars($t['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#94a3b8;font-size:24px;">
                                    <i class="fa-regular fa-image"></i>
                                </div>
                            <?php endif; ?>
                            <span class="tpl-badge <?= $isActive ? 'active' : 'hidden' ?>">
                                <?= $isActive ? 'Hoạt động' : 'Tạm ẩn' ?>
                            </span>
                        </div>

                        <div class="tpl-body">
                            <h3 class="tpl-title"><?= htmlspecialchars($t['name']) ?></h3>
                            <div class="tpl-folder"><?= htmlspecialchars($t['folder']) ?></div>
                            <div class="tpl-desc"><?= htmlspecialchars($t['description'] ?? 'Mẫu thiệp cưới online tương tác cao cấp.') ?></div>

                            <div class="tpl-footer">
                                <div class="tpl-usage">
                                    <i class="fa-solid fa-heart" style="color: #ec4899;"></i>
                                    <span><?= (int)$t['wedding_usage'] ?> thiệp dùng</span>
                                </div>
                                <div class="tpl-actions">
                                    <a href="../../mau-thiep/demo.php?template=<?= urlencode($t['folder']) ?>" target="_blank" class="btn-icon" title="Xem trước bản demo">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>">
                                        <button type="submit" class="btn-icon <?= $isActive ? 'toggle-on' : 'toggle-off' ?>" title="<?= $isActive ? 'Bấm để Tạm ẩn mẫu này' : 'Bấm để Kích hoạt mẫu này' ?>">
                                            <i class="fa-solid <?= $isActive ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Phân trang -->
            <?= renderPagination($pagination['page'], $pagination['total_pages']) ?>
        <?php endif; ?>
    </main>

    <!-- SweetAlert2 Flash Notifications -->
    <?php renderFlash(); ?>

    <script>
        function confirmCrawl() {
            Swal.fire({
                title: 'Kích hoạt thu thập mẫu thiệp?',
                text: 'Hệ thống sẽ chạy bộ cào dữ liệu từ mongmer.com/mau-thiep để tải thêm mẫu mới và tự động cập nhật vào cơ sở dữ liệu.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#0284c7',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Bắt đầu cào ngay',
                cancelButtonText: 'Hủy bỏ'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Đang cào dữ liệu...',
                        text: 'Vui lòng chờ trong giây lát trong khi hệ thống kết nối và tải mẫu thiệp.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    document.getElementById('crawlForm').submit();
                }
            });
        }
    </script>
</body>
</html>
