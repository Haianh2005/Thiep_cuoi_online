<?php
// admin/users/index.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

// Bắt buộc quyền Quản trị viên (Admin)
requireAdmin();

$currentAdmin = currentUser();

// Xử lý Thay đổi trạng thái khóa/mở hoặc vai trò
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken()) {
        setFlash('error', 'Token bảo mật không hợp lệ.');
    } else {
        $action = $_POST['action'] ?? '';
        $targetUserId = (int)($_POST['user_id'] ?? 0);

        if ($targetUserId > 0) {
            // Không cho phép tự khóa hoặc đổi quyền của chính mình (chống self-lockout)
            if ($targetUserId === (int)$currentAdmin['id']) {
                setFlash('warning', 'Bạn không thể tự khóa hoặc đổi vai trò của chính tài khoản đang đăng nhập.');
            } else {
                if ($action === 'toggle_active') {
                    $newStatus = (int)($_POST['is_active'] ?? 1);
                    $stmt = $pdo->prepare("UPDATE users SET is_active = :st WHERE id = :id");
                    $stmt->execute(['st' => $newStatus, 'id' => $targetUserId]);
                    setFlash('success', 'Đã cập nhật trạng thái hoạt động của người dùng.');
                } elseif ($action === 'change_role') {
                    $newRole = $_POST['role'] === 'admin' ? 'admin' : 'customer';
                    $stmt = $pdo->prepare("UPDATE users SET role = :r WHERE id = :id");
                    $stmt->execute(['r' => $newRole, 'id' => $targetUserId]);
                    setFlash('success', 'Đã thay đổi quyền thành ' . ($newRole === 'admin' ? 'Quản trị viên' : 'Khách hàng') . '.');
                }
            }
        }
    }
    header("Location: index.php?" . http_build_query($_GET));
    exit;
}

// Tìm kiếm & Lọc
$keyword = trim($_GET['keyword'] ?? '');
$roleFilter = $_GET['role'] ?? '';

$countSql = "SELECT COUNT(*) FROM users WHERE 1=1";
$listSql = "
    SELECT 
        u.*,
        (SELECT COUNT(*) FROM weddings w WHERE w.user_id = u.id) AS wedding_count
    FROM users u
    WHERE 1=1
";
$params = [];

if ($keyword !== '') {
    $countSql .= " AND (username LIKE :kw OR email LIKE :kw OR full_name LIKE :kw)";
    $listSql  .= " AND (username LIKE :kw OR email LIKE :kw OR full_name LIKE :kw)";
    $params['kw'] = "%$keyword%";
}

if ($roleFilter !== '' && in_array($roleFilter, ['admin', 'customer'])) {
    $countSql .= " AND role = :role";
    $listSql  .= " AND role = :role";
    $params['role'] = $roleFilter;
}

// Phân trang theo module Section 3.2
$pagination = paginate($pdo, $countSql, $params, 10);
$listSql .= " ORDER BY u.id DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($listSql);
foreach ($params as $key => $val) {
    $stmt->bindValue(":$key", $val);
}
$stmt->bindValue(':limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Người dùng | Admin Wedding</title>

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
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar Styling tương thích Admin */
        .sidebar {
            width: 260px;
            background: #ffffff;
            border-right: 1px solid var(--border);
            padding: 24px 18px;
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            min-height: 100vh;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            text-decoration: none;
            color: inherit;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            background: var(--wine-light);
            color: var(--wine);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .brand-title {
            font-weight: 800;
            font-size: 16px;
            color: var(--wine);
            line-height: 1.2;
        }

        .brand-subtitle {
            font-size: 11.5px;
            color: var(--text-muted);
        }

        .menu-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
            margin-bottom: 10px;
            padding-left: 10px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-radius: 12px;
            color: #475569;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 4px;
            transition: all 0.2s;
        }

        .menu-link:hover {
            background: #f1f5f9;
            color: var(--wine);
        }

        .menu-link.active {
            background: var(--wine-light);
            color: var(--wine);
            font-weight: 700;
        }

        .main-content {
            flex: 1;
            padding: 32px 36px;
            overflow-y: auto;
        }

        .top-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }

        .top-header h1 {
            font-size: 26px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .top-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .filter-panel {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px 22px;
            margin-bottom: 24px;
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input {
            flex: 1;
            min-width: 240px;
            padding: 10px 16px;
            border-radius: 10px;
            border: 1.5px solid var(--border);
            font-family: inherit;
            font-size: 13.5px;
            outline: none;
        }

        .search-input:focus {
            border-color: var(--wine);
        }

        .filter-select {
            padding: 10px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--border);
            font-family: inherit;
            font-size: 13.5px;
            outline: none;
            background: #fff;
        }

        .btn-filter {
            padding: 10px 18px;
            border-radius: 10px;
            background: var(--wine);
            color: #fff;
            border: none;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-filter:hover {
            background: var(--wine-dark);
        }

        .btn-reset {
            padding: 10px 16px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #475569;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
        }

        /* Table Styling */
        .card-table {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }

        th {
            background: #f8fafc;
            padding: 14px 18px;
            font-weight: 700;
            color: #475569;
            border-bottom: 1px solid var(--border);
            text-transform: uppercase;
            font-size: 11.5px;
            letter-spacing: 0.5px;
        }

        td {
            padding: 14px 18px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        tr:hover td {
            background: #fafafa;
        }

        .badge-role {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .role-admin { background: #fef3c7; color: #92400e; }
        .role-customer { background: #e0f2fe; color: #0369a1; }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12.5px;
            font-weight: 600;
        }

        .status-active { color: #16a34a; }
        .status-locked { color: #dc2626; }

        .btn-sm-action {
            padding: 6px 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #fff;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-sm-action:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        /* Pagination Styling */
        .pagination-wrapper {
            margin-top: 24px;
            display: flex;
            justify-content: center;
        }

        .pagination {
            display: flex;
            list-style: none;
            gap: 6px;
        }

        .page-item .page-link {
            display: block;
            padding: 8px 14px;
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 8px;
            color: #334155;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
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

<aside class="sidebar">
    <a href="/WeddingProject/admin/" class="brand">
        <div class="brand-icon">💍</div>
        <div>
            <div class="brand-title">Wedding Admin</div>
            <div class="brand-subtitle">Invitation Platform</div>
        </div>
    </a>

    <div class="menu-title">Quản lý tổng quan</div>
    <a href="/WeddingProject/admin/" class="menu-link">
        <i class="fa-solid fa-chart-pie"></i> Dashboard
    </a>
    <a href="/WeddingProject/admin/weddings/" class="menu-link">
        <i class="fa-solid fa-envelope-open-text"></i> Quản lý thiệp
    </a>
    <a href="/WeddingProject/admin/users/" class="menu-link active">
        <i class="fa-solid fa-users"></i> Người dùng
    </a>
    <a href="/WeddingProject/admin/templates/" class="menu-link">
        <i class="fa-solid fa-palette"></i> Mẫu thiệp cưới
    </a>
    <a href="/WeddingProject/admin/wishes/" class="menu-link">
        <i class="fa-solid fa-comments"></i> Lời chúc mừng
    </a>

    <div class="menu-title" style="margin-top: 24px;">Lối tắt</div>
    <a href="/WeddingProject/index.php" target="_blank" class="menu-link">
        <i class="fa-solid fa-arrow-up-right-from-square"></i> Xem website
    </a>
    <a href="/WeddingProject/logout.php" class="menu-link" style="color: #ef4444; margin-top: auto;">
        <i class="fa-solid fa-right-from-bracket"></i> Đăng xuất
    </a>
</aside>

<main class="main-content">
    <div class="top-header">
        <div>
            <h1>Quản lý Người dùng</h1>
            <p>Danh sách tài khoản thành viên, quyền hạn và trạng thái hoạt động trên hệ thống (FR-19).</p>
        </div>
    </div>

    <!-- Filter Bar -->
    <form method="get" action="index.php" class="filter-panel">
        <input 
            type="text" 
            name="keyword" 
            class="search-input" 
            placeholder="Tìm theo tên đăng nhập, email hoặc họ tên..." 
            value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
        >
        <select name="role" class="filter-select">
            <option value="">-- Tất cả vai trò --</option>
            <option value="customer" <?= $roleFilter === 'customer' ? 'selected' : '' ?>>Khách hàng (customer)</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Quản trị viên (admin)</option>
        </select>
        <button type="submit" class="btn-filter">
            <i class="fa-solid fa-magnifying-glass"></i> Tìm kiếm
        </button>
        <?php if ($keyword !== '' || $roleFilter !== ''): ?>
            <a href="index.php" class="btn-reset">Đặt lại</a>
        <?php endif; ?>
    </form>

    <!-- Users Table -->
    <div class="card-table">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Họ và tên</th>
                    <th>Tài khoản / Email</th>
                    <th>Vai trò</th>
                    <th>Thiệp sở hữu</th>
                    <th>Trạng thái</th>
                    <th>Ngày tạo</th>
                    <th style="text-align: right;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #94a3b8;">
                            Không tìm thấy người dùng nào phù hợp.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong>#<?= (int)$u['id'] ?></strong></td>
                            <td>
                                <strong><?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if ((int)$u['id'] === (int)$currentAdmin['id']): ?>
                                    <span style="font-size:11px; background:#f1f5f9; padding:2px 6px; border-radius:4px; margin-left:4px; color:#64748b;">(Bạn)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><i class="fa-solid fa-user" style="color:#94a3b8; font-size:12px;"></i> <?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div style="color:#64748b; font-size:12px; margin-top:2px;"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <span class="badge-role <?= $u['role'] === 'admin' ? 'role-admin' : 'role-customer' ?>">
                                    <?= $u['role'] === 'admin' ? 'Quản trị' : 'Khách hàng' ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= (int)$u['wedding_count'] ?></strong> thiệp
                            </td>
                            <td>
                                <?php if ((int)$u['is_active'] === 1): ?>
                                    <span class="badge-status status-active"><i class="fa-solid fa-circle-check"></i> Hoạt động</span>
                                <?php else: ?>
                                    <span class="badge-status status-locked"><i class="fa-solid fa-lock"></i> Đã khóa</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#64748b; font-size:12.5px;">
                                <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ((int)$u['id'] !== (int)$currentAdmin['id']): ?>
                                    <!-- Khóa / Mở khóa -->
                                    <form method="post" action="index.php?<?= http_build_query($_GET) ?>" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <input type="hidden" name="is_active" value="<?= (int)$u['is_active'] === 1 ? 0 : 1 ?>">
                                        <button type="submit" class="btn-sm-action" title="<?= (int)$u['is_active'] === 1 ? 'Khóa tài khoản' : 'Mở khóa' ?>">
                                            <i class="fa-solid <?= (int)$u['is_active'] === 1 ? 'fa-lock text-danger' : 'fa-lock-open text-success' ?>"></i>
                                            <?= (int)$u['is_active'] === 1 ? 'Khóa' : 'Mở' ?>
                                        </button>
                                    </form>

                                    <!-- Đổi vai trò -->
                                    <form method="post" action="index.php?<?= http_build_query($_GET) ?>" style="display:inline;">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                        <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'customer' : 'admin' ?>">
                                        <button type="submit" class="btn-sm-action" title="Chuyển sang <?= $u['role'] === 'admin' ? 'Khách hàng' : 'Quản trị' ?>">
                                            <i class="fa-solid fa-arrows-rotate"></i> <?= $u['role'] === 'admin' ? 'Gỡ Admin' : 'Thành Admin' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Phân trang theo Section 3.2 -->
    <?= renderPagination($pagination['page'], $pagination['total_pages']) ?>
</main>

<?= renderFlashSwal() ?>

</body>
</html>
