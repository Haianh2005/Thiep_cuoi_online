<?php
// admin/wishes/index.php - Quản lý Sổ lưu bút Lời chúc mừng cưới (FR-14 & FR-16)
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

requireAdmin();

$currentAdmin = currentUser();

// Xử lý Xóa lời chúc
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        setFlash('error', 'Token bảo mật không hợp lệ.');
    } else {
        $action = $_POST['action'] ?? '';
        $wishId = (int)($_POST['wish_id'] ?? 0);

        if ($action === 'delete_wish' && $wishId > 0) {
            $del = $pdo->prepare("DELETE FROM wedding_wishes WHERE id = :id");
            $del->execute(['id' => $wishId]);
            setFlash('success', 'Đã xóa lời chúc thành công khỏi hệ thống.');
        }
    }
    header("Location: index.php?" . http_build_query($_GET));
    exit;
}

// Thống kê nhanh
$totalWishes = (int)$pdo->query("SELECT COUNT(*) FROM wedding_wishes")->fetchColumn();
$wishesToday = (int)$pdo->query("SELECT COUNT(*) FROM wedding_wishes WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$totalWeddingsWithWishes = (int)$pdo->query("SELECT COUNT(DISTINCT wedding_id) FROM wedding_wishes")->fetchColumn();

// Tìm kiếm & Lọc
$keyword = trim($_GET['keyword'] ?? '');
$weddingIdFilter = isset($_GET['wedding_id']) && (int)$_GET['wedding_id'] > 0 ? (int)$_GET['wedding_id'] : null;

$countSql = "
    SELECT COUNT(*) 
    FROM wedding_wishes ww
    LEFT JOIN weddings w ON w.id = ww.wedding_id
    WHERE 1=1
";
$listSql = "
    SELECT 
        ww.*,
        w.groom_name,
        w.bride_name,
        w.slug
    FROM wedding_wishes ww
    LEFT JOIN weddings w ON w.id = ww.wedding_id
    WHERE 1=1
";
$params = [];

if ($keyword !== '') {
    $countSql .= " AND (ww.guest_name LIKE :kw OR ww.message LIKE :kw OR w.groom_name LIKE :kw OR w.bride_name LIKE :kw)";
    $listSql  .= " AND (ww.guest_name LIKE :kw OR ww.message LIKE :kw OR w.groom_name LIKE :kw OR w.bride_name LIKE :kw)";
    $params['kw'] = "%$keyword%";
}

if ($weddingIdFilter !== null) {
    $countSql .= " AND ww.wedding_id = :wid";
    $listSql  .= " AND ww.wedding_id = :wid";
    $params['wid'] = $weddingIdFilter;
}

// Phân trang
$pagination = paginate($pdo, $countSql, $params, 15);
$listSql .= " ORDER BY ww.id DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($listSql);
foreach ($params as $k => $v) {
    $stmt->bindValue(":$k", $v);
}
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$wishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Lời chúc | Admin Portal</title>

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
        .stat-icon.today { background: #ecfdf5; color: #059669; }
        .stat-icon.weddings { background: #fdf2f8; color: #db2777; }

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
            vertical-align: top;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #fafafa;
        }

        .guest-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
        }

        .wish-message {
            background: #f8fafc;
            border-radius: 8px;
            padding: 10px 14px;
            border-left: 3px solid var(--wine);
            color: #334155;
            line-height: 1.5;
            font-size: 13.5px;
            white-space: pre-wrap;
        }

        .wedding-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff1f2;
            color: var(--wine);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
        }

        .wedding-pill:hover {
            background: #ffe4e6;
        }

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-delete:hover {
            background: #fecaca;
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
            <a href="../weddings/" class="nav-item">
                <i class="fa-solid fa-envelope-open-text"></i> Quản lý thiệp
            </a>
            <a href="../users/" class="nav-item">
                <i class="fa-solid fa-users"></i> Người dùng
            </a>
            <a href="../templates/" class="nav-item">
                <i class="fa-solid fa-palette"></i> Mẫu thiệp cưới
            </a>
            <a href="index.php" class="nav-item active">
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
                <h1>Sổ Lưu Bút & Lời Chúc Mừng (FR-14)</h1>
                <p>Quản lý toàn bộ lời chúc phúc gửi từ khách mời qua REST API của các thiệp cưới</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total"><i class="fa-solid fa-comments"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($totalWishes) ?></div>
                    <div class="stat-label">Tổng số lời chúc</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon today"><i class="fa-solid fa-calendar-day"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($wishesToday) ?></div>
                    <div class="stat-label">Lời chúc mới hôm nay</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon weddings"><i class="fa-solid fa-heart"></i></div>
                <div>
                    <div class="stat-val"><?= number_format($totalWeddingsWithWishes) ?></div>
                    <div class="stat-label">Thiệp cưới nhận lời chúc</div>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="Tìm theo tên khách, nội dung lời chúc hoặc cô dâu chú rể...">
            </form>
            <div>
                <span style="font-size: 13px; color: var(--text-muted); font-weight: 600;">
                    Hiển thị <?= count($wishes) ?> / <?= number_format($pagination['total_records']) ?> lời chúc
                </span>
            </div>
        </div>

        <!-- Table -->
        <div class="table-card">
            <?php if (empty($wishes)): ?>
                <div style="padding: 48px; text-align: center;">
                    <i class="fa-regular fa-comments" style="font-size: 40px; color: #cbd5e1; margin-bottom: 12px;"></i>
                    <h3 style="font-size: 18px; color: #475569;">Không có lời chúc nào</h3>
                    <p style="color: var(--text-muted); font-size: 14px; margin-top: 4px;">Chưa có khách mời nào gửi lời chúc theo tiêu chí tìm kiếm này.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th style="width: 220px;">Khách mời</th>
                            <th>Nội dung lời chúc</th>
                            <th style="width: 240px;">Thiệp cưới nhận</th>
                            <th style="width: 160px;">Thời gian</th>
                            <th style="width: 90px; text-align: center;">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wishes as $w): ?>
                            <tr>
                                <td>
                                    <div style="display:flex; align-items:center; gap: 12px;">
                                        <div class="guest-avatar">
                                            <?= strtoupper(mb_substr($w['guest_name'], 0, 1, 'UTF-8')) ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($w['guest_name']) ?></div>
                                            <div style="font-size: 12px; color: var(--text-muted);">Khách mời</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="wish-message"><?= htmlspecialchars($w['message']) ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($w['slug'])): ?>
                                        <a href="../../w/?slug=<?= urlencode($w['slug']) ?>" target="_blank" class="wedding-pill" title="Mở xem thiệp công khai">
                                            <i class="fa-solid fa-link"></i>
                                            <?= htmlspecialchars($w['groom_name']) ?> ♥ <?= htmlspecialchars($w['bride_name']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: var(--text-muted);">Thiệp không tồn tại</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 13px; font-weight: 600; color: #334155;">
                                        <?= date('d/m/Y', strtotime($w['created_at'])) ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--text-muted);">
                                        <?= date('H:i', strtotime($w['created_at'])) ?>
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn-delete" onclick="confirmDeleteWish(<?= (int)$w['id'] ?>, '<?= htmlspecialchars(addslashes($w['guest_name'])) ?>')">
                                        <i class="fa-solid fa-trash-can"></i> Xóa
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?= renderPagination($pagination['page'], $pagination['total_pages']) ?>
    </main>

    <!-- Hidden form for deleting wish -->
    <form id="deleteWishForm" method="POST" style="display: none;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="delete_wish">
        <input type="hidden" name="wish_id" id="deleteWishId" value="">
    </form>

    <!-- SweetAlert2 Flash Notifications -->
    <?php renderFlash(); ?>

    <script>
        function confirmDeleteWish(wishId, guestName) {
            Swal.fire({
                title: 'Xóa lời chúc này?',
                text: `Bạn có chắc muốn xóa lời chúc của khách mời "${guestName}"? Thao tác này không thể hoàn tác.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Đồng ý xóa',
                cancelButtonText: 'Hủy'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteWishId').value = wishId;
                    document.getElementById('deleteWishForm').submit();
                }
            });
        }
    </script>
</body>
</html>
