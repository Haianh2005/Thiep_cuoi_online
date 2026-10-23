<?php
// admin/weddings/toggle-status.php
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

$stmt = $pdo->prepare("SELECT id, status, groom_name, bride_name FROM weddings WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $id]);
$wedding = $stmt->fetch();

if (!$wedding) {
    setFlash('error', 'Không tìm thấy thiệp cưới yêu cầu.');
    header("Location: index.php");
    exit;
}

$currentStatus = (int)$wedding['status'];
$newStatus = 1;
$msg = 'Đã mở xuất bản thiệp cưới.';
$type = 'success';

if ($currentStatus === 1) {
    // Đang hiển thị -> Chuyển sang ẩn (status = 3)
    $newStatus = 3;
    $msg = 'Đã ẩn thiệp cưới khỏi chế độ công khai.';
    $type = 'info';
} elseif ($currentStatus === 2) {
    // Đang chờ duyệt -> Duyệt và xuất bản (status = 1)
    $newStatus = 1;
    $msg = 'Đã phê duyệt và xuất bản thiệp cưới thành công!';
    $type = 'success';
} else {
    // Đang nháp (0) hoặc đang ẩn (3) -> Xuất bản (status = 1)
    $newStatus = 1;
    $msg = 'Đã xuất bản thiệp cưới thành công!';
    $type = 'success';
}

$update = $pdo->prepare("UPDATE weddings SET status = :status, updated_at = NOW() WHERE id = :id");
$update->execute([
    'status' => $newStatus,
    'id'     => $id
]);

setFlash($type, $msg);
header("Location: index.php");
exit;
