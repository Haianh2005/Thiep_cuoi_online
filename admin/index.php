<?php
// admin/index.php - Bảng điều khiển Quản trị viên (FR-16)
declare(strict_types=1);

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/auth.php";

requireAdmin();

$currentAdmin = currentUser();

/* =========================================================
   THỐNG KÊ DASHBOARD (FR-16)
========================================================= */

// Tổng số thiệp
$totalWeddings = (int)$pdo->query("SELECT COUNT(*) FROM weddings")->fetchColumn();
// Thiệp đã duyệt / xuất bản (status = 1)
$publishedWeddings = (int)$pdo->query("SELECT COUNT(*) FROM weddings WHERE status = 1")->fetchColumn();
// Thiệp đang chờ duyệt (status = 2)
$pendingWeddings = (int)$pdo->query("SELECT COUNT(*) FROM weddings WHERE status = 2")->fetchColumn();
// Thiệp bản nháp / đang ẩn (status = 0 or 3)
$hiddenWeddings = (int)$pdo->query("SELECT COUNT(*) FROM weddings WHERE status IN (0, 3)")->fetchColumn();

// Tổng số thành viên
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
// Tổng số mẫu thiệp
$totalTemplates = (int)$pdo->query("SELECT COUNT(*) FROM templates WHERE status = 1")->fetchColumn();
// Tổng lời chúc
$totalWishes = (int)$pdo->query("SELECT COUNT(*) FROM wedding_wishes")->fetchColumn();
// Tổng ảnh album
$totalPhotos = (int)$pdo->query("SELECT COUNT(*) FROM wedding_photos")->fetchColumn();

/* =========================================================
   DANH SÁCH THIỆP MỚI NHẤT
========================================================= */
$stmt = $pdo->query("
    SELECT
        w.*,
        t.name AS template_name,
        u.username AS owner_username,
        u.full_name AS owner_name,
        (SELECT COUNT(*) FROM wedding_wishes ww WHERE ww.wedding_id = w.id) AS wish_count
    FROM weddings w
    LEFT JOIN templates t ON w.template_id = t.id
    LEFT JOIN users u ON w.user_id = u.id
    ORDER BY w.id DESC
    LIMIT 8
");
$recentWeddings = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Wedding Project</title>

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

        /* KPI Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: #fff;
            padding: 20px 22px;
            border-radius: 16px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-icon.total-weddings { background: #fdf2f8; color: #db2777; }
        .stat-icon.pending { background: #fffbeb; color: #d97706; }
        .stat-icon.published { background: #ecfdf5; color: #059669; }
        .stat-icon.users { background: #eff6ff; color: #2563eb; }
        .stat-icon.wishes { background: #f5f3ff; color: #7c3aed; }
        .stat-icon.templates { background: #f0fdf4; color: #16a34a; }

        .stat-val {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 4px;
        }

        /* Pending Alert Banner */
        <?php if ($pendingWeddings > 0): ?>
        .pending-banner {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 1px solid #fde68a;
            border-radius: 14px;
            padding: 16px 22px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .pending-banner-text {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #92400e;
            font-size: 14px;
            font-weight: 600;
        }
        .pending-banner-btn {
            background: #d97706;
            color: #fff;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }
        <?php endif; ?>

        /* Section Container */
        .section-box {
            background: #fff;
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        .section-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-header h2 {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-link {
            font-size: 13px;
            color: var(--wine);
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .section-link:hover {
            text-decoration: underline;
        }

        /* Table */
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
            <a href="index.php" class="nav-item active">
                <i class="fa-solid fa-chart-pie"></i> Dashboard
            </a>
            <a href="weddings/" class="nav-item">
                <i class="fa-solid fa-envelope-open-text"></i> Quản lý thiệp
                <?php if ($pendingWeddings > 0): ?>
                    <span class="badge-pending"><?= $pendingWeddings ?></span>
                <?php endif; ?>
            </a>
            <a href="users/" class="nav-item">
                <i class="fa-solid fa-users"></i> Người dùng
            </a>
            <a href="templates/" class="nav-item">
                <i class="fa-solid fa-palette"></i> Mẫu thiệp cưới
            </a>
            <a href="wishes/" class="nav-item">
                <i class="fa-solid fa-comments"></i> Lời chúc mừng
            </a>

            <div class="menu-title" style="margin-top: 20px;">Lối tắt</div>
            <a href="../index.php" target="_blank" class="nav-item">
                <i class="fa-solid fa-arrow-up-right-from-square"></i> Xem website
            </a>
            <a href="../logout.php" class="nav-item" style="color: #ef4444;">
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
                <h1>Bảng điều khiển Quản trị (FR-16)</h1>
                <p>Tổng hợp dữ liệu hệ thống, kiểm duyệt nội dung và quản lý người dùng</p>
            </div>
            <div class="header-actions">
                <a href="weddings/create.php" class="btn-create">
                    <i class="fa-solid fa-plus"></i> Khởi tạo thiệp mới
                </a>
            </div>
        </div>

        <?php if ($pendingWeddings > 0): ?>
            <div class="pending-banner">
                <div class="pending-banner-text">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 18px;"></i>
                    <span>Có <strong><?= $pendingWeddings ?></strong> thiệp cưới đang ở trạng thái Chờ duyệt cần bạn kiểm tra và phê duyệt.</span>
                </div>
                <a href="weddings/index.php?status=2" class="pending-banner-btn">
                    Kiểm duyệt ngay
                </a>
            </div>
        <?php endif; ?>

        <!-- KPI Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total-weddings"><i class="fa-solid fa-envelopes-bulk"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($totalWeddings) ?></div>
                    <div class="stat-label">Tổng số thiệp cưới</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon published"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($publishedWeddings) ?></div>
                    <div class="stat-label">Thiệp đã xuất bản</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending"><i class="fa-solid fa-clock"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($pendingWeddings) ?></div>
                    <div class="stat-label">Thiệp chờ duyệt</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon users"><i class="fa-solid fa-user-group"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($totalUsers) ?></div>
                    <div class="stat-label">Thành viên hệ thống</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon templates"><i class="fa-solid fa-palette"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($totalTemplates) ?></div>
                    <div class="stat-label">Mẫu thiệp hoạt động</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon wishes"><i class="fa-solid fa-comments"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($totalWishes) ?></div>
                    <div class="stat-label">Lời chúc gửi tới thiệp</div>
                </div>
            </div>
        </div>

        <!-- Recent Weddings Table -->
        <div class="section-box">
            <div class="section-header">
                <h2><i class="fa-solid fa-list-check" style="color: var(--wine);"></i> Thiệp cưới cập nhật gần đây</h2>
                <a href="weddings/" class="section-link">
                    Xem toàn bộ thiệp <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>

            <?php if (empty($recentWeddings)): ?>
                <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                    Chưa có thiệp cưới nào được khởi tạo trong hệ thống.
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Cặp đôi</th>
                            <th>Mẫu giao diện</th>
                            <th>Người tạo</th>
                            <th>Ngày cưới</th>
                            <th>Trạng thái</th>
                            <th style="text-align: right;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentWeddings as $w): ?>
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
                                        <a href="../w/?slug=<?= urlencode($w['slug']) ?>" target="_blank" class="btn-table" title="Xem thiệp">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="weddings/edit.php?id=<?= (int)$w['id'] ?>" class="btn-table" title="Chỉnh sửa thiệp">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <?php if ($status === 2): ?>
                                            <a href="weddings/approve.php?id=<?= (int)$w['id'] ?>" class="btn-table btn-approve" title="Phê duyệt ngay" onclick="return confirmApprove(event, this.href, '<?= e(addslashes($w['groom_name'])) ?> & <?= e(addslashes($w['bride_name'])) ?>')">
                                                <i class="fa-solid fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <!-- SweetAlert2 Flash Notifications -->
    <?php renderFlash(); ?>

    <script>
        function confirmApprove(e, url, title) {
            e.preventDefault();
            Swal.fire({
                title: 'Phê duyệt thiệp cưới?',
                text: `Xuất bản công khai thiệp cưới của ${title}?`,
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
    </script>
</body>
</html>