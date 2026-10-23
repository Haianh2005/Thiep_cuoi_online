<?php

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . "/../../config/database.php";


/*
|--------------------------------------------------------------------------
| LẤY SLUG
|--------------------------------------------------------------------------
*/

$slug = $_GET['slug'] ?? $_POST['slug'] ?? '';

if (empty($slug)) {

    echo json_encode([
        'success' => false,
        'message' => 'Thiếu mã thiệp.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| TÌM ĐÁM CƯỚI
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM weddings
    WHERE slug = :slug
    AND status = 1
    LIMIT 1
");

$stmt->execute([
    'slug' => $slug
]);

$wedding = $stmt->fetch();


if (!$wedding) {

    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy thiệp cưới.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


$weddingId = $wedding['id'];


/*
|--------------------------------------------------------------------------
| THÊM LỜI CHÚC
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $guestName = trim($_POST['guest_name'] ?? '');
    $message = trim($_POST['message'] ?? '');


    if ($guestName === '') {

        echo json_encode([
            'success' => false,
            'message' => 'Vui lòng nhập tên của bạn.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    if ($message === '') {

        echo json_encode([
            'success' => false,
            'message' => 'Vui lòng nhập lời chúc.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    if (mb_strlen($guestName) > 50) {

        echo json_encode([
            'success' => false,
            'message' => 'Tên không được quá 50 ký tự.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    if (mb_strlen($message) > 500) {

        echo json_encode([
            'success' => false,
            'message' => 'Lời chúc không được quá 500 ký tự.'
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    $insert = $pdo->prepare("
        INSERT INTO wedding_wishes
        (
            wedding_id,
            guest_name,
            message
        )
        VALUES
        (
            :wedding_id,
            :guest_name,
            :message
        )
    ");


    $insert->execute([
        'wedding_id' => $weddingId,
        'guest_name' => $guestName,
        'message' => $message
    ]);


    echo json_encode([
        'success' => true,
        'message' => 'Gửi lời chúc thành công.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| LẤY DANH SÁCH LỜI CHÚC
|--------------------------------------------------------------------------
*/

$wishStmt = $pdo->prepare("
    SELECT
        guest_name,
        message,
        created_at
    FROM wedding_wishes
    WHERE wedding_id = :wedding_id
    ORDER BY created_at DESC
");

$wishStmt->execute([
    'wedding_id' => $weddingId
]);

$wishes = $wishStmt->fetchAll();


echo json_encode([
    'success' => true,
    'wishes' => $wishes
], JSON_UNESCAPED_UNICODE);
