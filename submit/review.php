<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireLogin();


/*
|--------------------------------------------------------------------------
| LẤY TOKEN
|--------------------------------------------------------------------------
*/

$token = trim(
    $_POST['token'] ??
    $_GET['token'] ??
    ''
);


if ($token === '') {

    die('Thiếu mã chỉnh sửa.');
}


/*
|--------------------------------------------------------------------------
| TÌM THIỆP
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM weddings
    WHERE edit_token = :token
    LIMIT 1
");

$stmt->execute([
    'token' => $token
]);

$wedding = $stmt->fetch();


if (!$wedding) {

    die('Không tìm thấy thiệp cưới.');
}


$weddingId = (int)$wedding['id'];

// Kiểm tra quyền sở hữu
if (!checkWeddingOwnership($pdo, $weddingId, (int)$_SESSION['user_id'])) {
    setFlash('error', 'Bạn không có quyền gửi duyệt thiệp cưới này.');
    header("Location: ../my-weddings.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| GỬI CHỜ DUYỆT
|--------------------------------------------------------------------------
*/

$currentStatus = (int)$wedding['status'];

if ($currentStatus === 1) {
    setFlash('info', 'Thiệp cưới này đã được phê duyệt và xuất bản trước đó.');
    header('Location: ../editor/?token=' . urlencode($token));
    exit;
}

$update = $pdo->prepare("
    UPDATE weddings
    SET
        status = 2,
        updated_at = NOW()
    WHERE id = :id
");

$update->execute([
    'id' => $weddingId
]);

setFlash('success', 'Thiệp đã được gửi cho Quản trị viên kiểm duyệt thành công!');

header(
    'Location: ../editor/?token=' .
    urlencode($token) .
    '&submitted=1'
);

exit;