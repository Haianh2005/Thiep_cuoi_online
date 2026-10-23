<?php
// admin/weddings/index.php - Quản lý danh sách thiệp cưới & Kiểm duyệt (FR-17)
declare(strict_types=1);

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/auth.php";

requireAdmin();

$currentAdmin = currentUser();

/* =========================================================
   BỘ LỌC VÀ TÌM KIẾM
========================================================= */
$keyword = trim($_GET['keyword'] ?? '');
$statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;

// Thống kê nhanh
$totalWeddings = (int)$pdo->query("SELECT COUNT(*) FROM weddings")->fetchColumn();
$publishedCount = (int)$pdo->query("SELECT COUNT(*) FROM weddings WHERE status = 1")->fetchColumn();
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM weddings WHERE status = 2")->fetchColumn();
$draftCount = (int)$pdo->query("SELECT COUNT(*) FROM weddings WHERE status = 0")->fetchColumn();
$hiddenCount = (int)$pdo->query("SELECT COUNT(*) FROM weddings WHERE status = 3")->fetchColumn();

$countSql = "
    SELECT COUNT(*) 
    FROM weddings w
    LEFT JOIN templates t ON w.template_id = t.id
    LEFT JOIN users u ON w.user_id = u.id
    WHERE 1=1
";

$listSql = "
    SELECT
        w.*,
        t.name AS template_name,
        u.username AS owner_username,
        u.full_name AS owner_name,
        (SELECT COUNT(*) FROM wedding_photos wp WHERE wp.wedding_id = w.id) AS photo_count,
        (SELECT COUNT(*) FROM wedding_wishes ww WHERE ww.wedding_id = w.id) AS wish_count
    FROM weddings w
    LEFT JOIN templates t ON w.template_id = t.id
    LEFT JOIN users u ON w.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($keyword !== '') {
    $countSql .= " AND (w.groom_name LIKE :kw OR w.bride_name LIKE :kw OR w.slug LIKE :kw OR w.venue LIKE :kw OR u.username LIKE :kw)";
    $listSql  .= " AND (w.groom_name LIKE :kw OR w.bride_name LIKE :kw OR w.slug LIKE :kw OR w.venue LIKE :kw OR u.username LIKE :kw)";
    $params['kw'] = "%$keyword%";
}

if ($statusFilter !== null) {
    $countSql .= " AND w.status = :st";
    $listSql  .= " AND w.status = :st";
    $params['st'] = $statusFilter;
}

// Phân trang chuẩn Section 3.2
$pagination = paginate($pdo, $countSql, $params, 12);
$listSql .= " ORDER BY w.id DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($listSql);
foreach ($params as $k => $v) {
    $stmt->bindValue(":$k", $v);
}
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$weddings = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý thiệp cưới | Admin Portal</title>

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

        .badge-pending {
            background: #f59e0b;
            color: #fff;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            margin-left: auto;
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

        .btn-create {
            background: var(--wine);
            color: #fff;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(139, 30, 45, 0.2);
            transition: all 0.2s;
        }

        .btn-create:hover {
            background: var(--wine-dark);
            transform: translateY(-1px);
        }

        /* Filter Box */
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
            min-width: 280px;
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
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-tab:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .filter-tab.active {
            background: var(--wine);
            color: #fff;
        }

        .tab-badge {
            font-size: 11px;
            padding: 1px 6px;
            border-radius: 10px;
            background: rgba(0, 0, 0, 0.1);
        }

        .filter-tab.active .tab-badge {
            background: rgba(255, 255, 255, 0.25);
        }

        /* Table */
        .table-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: #f8fafc;
            padding: 14px 20px;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #fafafa;
        }

        .couple-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .couple-avatar {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--wine-light);
            color: var(--wine);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .couple-title {
            font-weight: 700;
            color: #0f172a;
        }

        .couple-slug {
            font-size: 12px;
            color: var(--text-muted);
            font-family: monospace;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .status-badge.published { background: #ecfdf5; color: #059669; }
        .status-badge.pending { background: #fffbeb; color: #d97706; }
        .status-badge.draft { background: #f1f5f9; color: #64748b; }
        .status-badge.hidden { background: #fef2f2; color: #dc2626; }

        .btn-table {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #fff;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s;
            font-size: 13px;
        }

        .btn-table:hover {
            background: #f8fafc;
            color: var(--wine);
            border-color: #cbd5e1;
        }

        .btn-table.btn-approve {
            background: #ecfdf5;
            color: #059669;
            border-color: #a7f3d0;
        }

        .btn-table.btn-approve:hover {
            background: #059669;
            color: #fff;
        }

        .btn-table.btn-toggle {
            background: #f8fafc;
            color: #475569;
        }

        .btn-table.btn-toggle:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        /* Pagination */
        .pagination-wrapper {
            margin-top: 28px;
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
            <a href="index.php" class="nav-item active">
                <i class="fa-solid fa-envelope-open-text"></i> Quản lý thiệp
                <?php if ($pendingCount > 0): ?>
                    <span class="badge-pending"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <a href="../users/" class="nav-item">
                <i class="fa-solid fa-users"></i> Người dùng
            </a>
            <a href="../templates/" class="nav-item">
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
                <h1>Quản lý & Kiểm duyệt Thiệp cưới (FR-17)</h1>
                <p>Theo dõi trạng thái xuất bản, phê duyệt thiệp gửi lên và kiểm soát nội dung</p>
            </div>
            <div class="header-actions">
                <a href="create.php" class="btn-create">
                    <i class="fa-solid fa-plus"></i> Khởi tạo thiệp mới
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="Tìm kiếm theo tên cô dâu, chú rể, slug, địa điểm hoặc người tạo...">
                <?php if ($statusFilter !== null): ?>
                    <input type="hidden" name="status" value="<?= $statusFilter ?>">
                <?php endif; ?>
            </form>

            <div class="filter-tabs">
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => ''])) ?>" class="filter-tab <?= ($statusFilter === null) ? 'active' : '' ?>">
                    Tất cả <span class="tab-badge"><?= $totalWeddings ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => '2'])) ?>" class="filter-tab <?= ($statusFilter === 2) ? 'active' : '' ?>">
                    <i class="fa-solid fa-clock" style="color: #f59e0b;"></i> Chờ duyệt <span class="tab-badge"><?= $pendingCount ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => '1'])) ?>" class="filter-tab <?= ($statusFilter === 1) ? 'active' : '' ?>">
                    <i class="fa-solid fa-circle-check" style="color: #10b981;"></i> Đã xuất bản <span class="tab-badge"><?= $publishedCount ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => '0'])) ?>" class="filter-tab <?= ($statusFilter === 0) ? 'active' : '' ?>">
                    Bản nháp <span class="tab-badge"><?= $draftCount ?></span>
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => '3'])) ?>" class="filter-tab <?= ($statusFilter === 3) ? 'active' : '' ?>">
                    Đã ẩn <span class="tab-badge"><?= $hiddenCount ?></span>
                </a>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <?php if (empty($weddings)): ?>
                <div style="padding: 48px; text-align: center;">
                    <i class="fa-solid fa-inbox" style="font-size: 40px; color: #cbd5e1; margin-bottom: 12px;"></i>
                    <h3 style="font-size: 18px; color: #475569;">Không tìm thấy thiệp cưới nào</h3>
                    <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">Vui lòng thử tìm kiếm với bộ lọc hoặc từ khóa khác.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Cặp đôi & Slug</th>
                            <th>Mẫu thiệp</th>
                            <th>Người tạo</th>
                            <th>Thời gian & Địa điểm</th>
                            <th>Tương tác</th>
                            <th>Trạng thái</th>
                            <th style="text-align: right; width: 140px;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weddings as $w): ?>
                            <?php $status = (int)$w['status']; ?>
                            <tr>
                                <td>
                                    <div class="couple-cell">
                                        <div class="couple-avatar">
                                            <i class="fa-solid fa-heart"></i>
                                        </div>
                                        <div>
                                            <div class="couple-title"><?= e($w['groom_name']) ?> ♥ <?= e($w['bride_name']) ?></div>
                                            <div class="couple-slug">/w/?slug=<?= e($w['slug']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-weight: 600; font-size: 13px; color: #334155;">
                                        <?= e($w['template_name'] ?? 'Mặc định') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 13px;"><?= e($w['owner_name'] ?? $w['owner_username'] ?? 'Admin') ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);">@<?= e($w['owner_username'] ?? 'admin') ?></div>
                                </td>
                                <td>
                                    <div style="font-size: 13px; font-weight: 600; color: #334155;">
                                        <?= !empty($w['wedding_date']) ? date('d/m/Y', strtotime($w['wedding_date'])) : 'Chưa đặt' ?>
                                    </div>
                                    <div style="font-size: 11.5px; color: var(--text-muted); max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= e($w['venue'] ?? $w['address'] ?? 'Chưa có địa điểm') ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 12px; color: var(--text-muted); display: flex; flex-direction: column; gap: 2px;">
                                        <span><i class="fa-regular fa-comment" style="width: 14px;"></i> <?= (int)$w['wish_count'] ?> lời chúc</span>
                                        <span><i class="fa-regular fa-image" style="width: 14px;"></i> <?= (int)$w['photo_count'] ?> ảnh album</span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($status === 1): ?>
                                        <span class="status-badge published"><i class="fa-solid fa-circle-check"></i> Đã xuất bản</span>
                                    <?php elseif ($status === 2): ?>
                                        <span class="status-badge pending"><i class="fa-solid fa-clock"></i> Chờ duyệt</span>
                                    <?php elseif ($status === 0): ?>
                                        <span class="status-badge draft"><i class="fa-solid fa-pen-ruler"></i> Bản nháp</span>
                                    <?php else: ?>
                                        <span class="status-badge hidden"><i class="fa-solid fa-eye-slash"></i> Đã ẩn</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        <a href="../../w/?slug=<?= urlencode($w['slug']) ?>" target="_blank" class="btn-table" title="Mở xem thiệp công khai">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?= (int)$w['id'] ?>" class="btn-table" title="Chỉnh sửa chi tiết">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>

                                        <?php if ($status === 2): ?>
                                            <a href="approve.php?id=<?= (int)$w['id'] ?>" class="btn-table btn-approve" title="Phê duyệt xuất bản ngay" onclick="return confirmApprove(event, this.href, '<?= e(addslashes($w['groom_name'])) ?> & <?= e(addslashes($w['bride_name'])) ?>')">
                                                <i class="fa-solid fa-check"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="toggle-status.php?id=<?= (int)$w['id'] ?>" class="btn-table btn-toggle" title="<?= $status === 1 ? 'Ẩn thiệp khỏi công khai' : 'Mở xuất bản thiệp' ?>" onclick="return confirmToggle(event, this.href, <?= $status ?>)">
                                            <i class="fa-solid <?= $status === 1 ? 'fa-lock' : 'fa-lock-open' ?>"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Phân trang chuẩn -->
        <?= renderPagination($pagination['page'], $pagination['total_pages']) ?>
    </main>

    <!-- SweetAlert2 Flash Notifications -->
    <?php renderFlash(); ?>

    <script>
        function confirmApprove(e, url, title) {
            e.preventDefault();
            Swal.fire({
                title: 'Phê duyệt thiệp cưới?',
                text: `Bạn muốn phê duyệt và xuất bản chính thức thiệp cưới của "${title}"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#059669',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Đồng ý duyệt',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
            return false;
        }

        function confirmToggle(e, url, currentStatus) {
            e.preventDefault();
            const actionText = (currentStatus === 1) ? 'Ẩn thiệp cưới này khỏi chế độ công khai?' : 'Mở xuất bản công khai thiệp cưới này?';
            Swal.fire({
                title: 'Thay đổi trạng thái thiệp?',
                text: actionText,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#8b1e2d',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Xác nhận thay đổi',
                cancelButtonText: 'Đóng'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
            return false;
        }
    </script>
</body>
</html>