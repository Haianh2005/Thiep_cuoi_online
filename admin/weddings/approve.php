<?php
// admin/weddings/approve.php
declare(strict_types=1);

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/auth.php";

requireAdmin();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    setFlash('error', 'ID thiệp cưới không hợp lệ.');
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM weddings WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$wedding = $stmt->fetch();

if (!$wedding) {
    setFlash('error', 'Không tìm thấy thông tin thiệp cưới cần duyệt.');
    header("Location: index.php");
    exit;
}

if ((int)$wedding['status'] !== 2) {
    setFlash('warning', 'Thiệp này hiện không ở trạng thái Chờ duyệt.');
    header("Location: index.php");
    exit;
}

$update = $pdo->prepare("
    UPDATE weddings
    SET status = 1, updated_at = NOW()
    WHERE id = :id
");
$update->execute(['id' => $id]);

setFlash('success', "Đã phê duyệt xuất bản thành công thiệp cưới của " . htmlspecialchars($wedding['groom_name']) . " & " . htmlspecialchars($wedding['bride_name']) . "!");
header("Location: index.php");
exit;