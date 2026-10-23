<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Yêu cầu đăng nhập trước khi tạo thiệp theo FR-05
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./');
    exit;
}

$templateId = (int)($_POST['template_id'] ?? 0);

if ($templateId <= 0) {
    die('Mẫu thiệp không hợp lệ.');
}

/* Kiểm tra mẫu còn đang được bật */
$templateStmt = $pdo->prepare("
    SELECT id, name
    FROM templates
    WHERE id = :id
      AND status = 1
    LIMIT 1
");

$templateStmt->execute([
    'id' => $templateId
]);

$template = $templateStmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die('Mẫu thiệp này hiện không còn khả dụng.');
}

/*
|--------------------------------------------------------------------------
| TỰ TẠO THIỆP NHÁP CHO KHÁCH
|--------------------------------------------------------------------------
| status = 0 = bản nháp.
| Khách sẽ được đưa thẳng sang Editor bằng edit_token.
|--------------------------------------------------------------------------
*/
$editToken = bin2hex(random_bytes(16));

/* Slug tạm thời, sau này có thể cho khách chỉnh lại trong Editor. */
$slug = 'thiep-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));

$userId = (int)$_SESSION['user_id'];

$insert = $pdo->prepare("
    INSERT INTO weddings (
        user_id,
        template_id,
        slug,
        edit_token,
        groom_name,
        bride_name,
        wedding_date,
        wedding_time,
        venue,
        address,
        story,
        background_image,
        music,
        story_image,
        status,
        created_at,
        updated_at
    ) VALUES (
        :user_id,
        :template_id,
        :slug,
        :edit_token,
        :groom_name,
        :bride_name,
        NULL,
        NULL,
        '',
        '',
        '',
        NULL,
        NULL,
        NULL,
        0,
        NOW(),
        NOW()
    )
");

$insert->execute([
    'user_id'     => $userId,
    'template_id' => $templateId,
    'slug'        => $slug,
    'edit_token'  => $editToken,
    'groom_name'  => 'Tên chú rể',
    'bride_name'  => 'Tên cô dâu'
]);

/*
|--------------------------------------------------------------------------
| ĐƯA KHÁCH VÀO EDITOR
|--------------------------------------------------------------------------
*/
header(
    'Location: ../editor/?token=' .
    urlencode($editToken) .
    '&new=1'
);
exit;
