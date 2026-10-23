<?php

require __DIR__ . "/../config/database.php";

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    die("Thiếu mã thiệp.");
}

$preview = isset($_GET['preview'])
    && $_GET['preview'] === '1';

$previewToken = trim(
    $_GET['token'] ?? ''
);


/*
|--------------------------------------------------------------------------
| TÌM THIỆP
|--------------------------------------------------------------------------
*/

if ($preview) {

    /*
     * XEM TRƯỚC:
     * Phải có token đúng với edit_token
     */

    if ($previewToken === '') {
        die("Thiếu mã xem trước.");
    }

    $stmt = $pdo->prepare("
        SELECT *
        FROM weddings
        WHERE slug = :slug
        AND edit_token = :token
        LIMIT 1
    ");

    $stmt->execute([
        'slug'  => $slug,
        'token' => $previewToken
    ]);

} else {

    /*
     * PUBLIC:
     * Chỉ thiệp đã được Admin duyệt
     */

    $stmt = $pdo->prepare("
        SELECT *
        FROM weddings
        WHERE slug = :slug
        AND status = 1
        LIMIT 1
    ");

    $stmt->execute([
        'slug' => $slug
    ]);
}


$wedding = $stmt->fetch();


if (!$wedding) {
    if ($preview) {
        die("Không tìm thấy bản xem trước.");
    }

    $chk = $pdo->prepare("SELECT status FROM weddings WHERE slug = :slug LIMIT 1");
    $chk->execute(['slug' => $slug]);
    $existingStatus = $chk->fetchColumn();

    if ($existingStatus !== false) {
        die("Thiệp cưới chưa được xuất bản hoặc đang trong quá trình xét duyệt.");
    }

    die("Không tìm thấy thiệp cưới.");
}



/*
|--------------------------------------------------------------------------
| Xác định template
|--------------------------------------------------------------------------
*/

$templateId = $wedding['template_id'];

$templateStmt = $pdo->prepare("
    SELECT *
    FROM templates
    WHERE id = :id
    LIMIT 1
");

$templateStmt->execute([
    'id' => $templateId
]);

$template = $templateStmt->fetch();

if (!$template) {
    die("Không tìm thấy mẫu thiệp.");
}


/*
|--------------------------------------------------------------------------
| Chuyển dữ liệu sang template
|--------------------------------------------------------------------------
*/

$templateFolder = $template['folder'];

$templatePath =
    __DIR__ .
    "/../templates/" .
    $templateFolder .
    "/index.php";

if (!file_exists($templatePath)) {
    die("Không tìm thấy file template.");
}


/*
|--------------------------------------------------------------------------
| Gọi template
|--------------------------------------------------------------------------
*/

require $templatePath;