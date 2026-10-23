<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/auth.php';

requireAdmin();

/*
|--------------------------------------------------------------------------
| 1. KIỂM TRA ID THIỆP
|--------------------------------------------------------------------------
*/

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    die('ID thiệp không hợp lệ.');
}


/*
|--------------------------------------------------------------------------
| 2. LẤY THÔNG TIN THIỆP
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM weddings
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $id
]);

$wedding = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wedding) {
    die('Không tìm thấy thiệp cưới.');
}


/*
|--------------------------------------------------------------------------
| 3. LẤY DANH SÁCH TEMPLATE
|--------------------------------------------------------------------------
*/

$templateStmt = $pdo->query("
    SELECT *
    FROM templates
    ORDER BY id ASC
");

$templates = $templateStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 4. LẤY DỮ LIỆU CƠ BẢN
|--------------------------------------------------------------------------
*/

$templateId = $wedding['template_id'] ?? '';
$slug = $wedding['slug'] ?? '';

$groomName = $wedding['groom_name'] ?? '';
$brideName = $wedding['bride_name'] ?? '';

$weddingDate = $wedding['wedding_date'] ?? '';
$weddingTime = $wedding['wedding_time'] ?? '';

$venue = $wedding['venue'] ?? '';
$address = $wedding['address'] ?? '';

$story = $wedding['story'] ?? '';

$backgroundImage = $wedding['background_image'] ?? '';
$storyImage = $wedding['story_image'] ?? '';
$music = $wedding['music'] ?? '';

$status = $wedding['status'] ?? 'draft';


/*
|--------------------------------------------------------------------------
| 5. LẤY BỐ MẸ HAI BÊN
|--------------------------------------------------------------------------
|
| Nếu bảng wedding_parents có dữ liệu,
| mình lấy toàn bộ dữ liệu để đưa vào form.
|
*/

$parentsStmt = $pdo->prepare("
    SELECT *
    FROM wedding_parents
    WHERE wedding_id = :wedding_id
    ORDER BY id ASC
");

$parentsStmt->execute([
    'wedding_id' => $id
]);

$parents = $parentsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 6. LẤY CÁC SỰ KIỆN
|--------------------------------------------------------------------------
*/

$eventsStmt = $pdo->prepare("
    SELECT *
    FROM wedding_events
    WHERE wedding_id = :wedding_id
    ORDER BY id ASC
");

$eventsStmt->execute([
    'wedding_id' => $id
]);

$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 7. LẤY ALBUM ẢNH
|--------------------------------------------------------------------------
*/

$albumStmt = $pdo->prepare("
    SELECT *
    FROM wedding_photos
    WHERE wedding_id = :wedding_id
    ORDER BY sort_order ASC, id ASC
");

$albumStmt->execute([
    'wedding_id' => $id
]);

$album = $albumStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 8. LẤY THÔNG TIN HỘP QUÀ
|--------------------------------------------------------------------------
*/

$giftStmt = $pdo->prepare("
    SELECT *
    FROM wedding_gifts
    WHERE wedding_id = :wedding_id
    LIMIT 1
");

$giftStmt->execute([
    'wedding_id' => $id
]);

$gift = $giftStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 9. GIÁ TRỊ MẶC ĐỊNH CHO HỘP QUÀ
|--------------------------------------------------------------------------
*/

$giftMode = $gift['gift_mode'] ?? 'bank';

$groomGiftName = $gift['groom_name'] ?? '';
$groomBankName = $gift['groom_bank_name'] ?? '';
$groomAccountNumber = $gift['groom_account_number'] ?? '';
$groomAccountName = $gift['groom_account_name'] ?? '';
$groomQrImage = $gift['groom_qr_image'] ?? '';

$brideGiftName = $gift['bride_name'] ?? '';
$brideBankName = $gift['bride_bank_name'] ?? '';
$brideAccountNumber = $gift['bride_account_number'] ?? '';
$brideAccountName = $gift['bride_account_name'] ?? '';
$brideQrImage = $gift['bride_qr_image'] ?? '';

$giftMessage = $gift['gift_message'] ?? '';


/*
|--------------------------------------------------------------------------
| 10. XỬ LÝ KHI BẤM "LƯU THAY ĐỔI"
|--------------------------------------------------------------------------
*/

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /*
        |--------------------------------------------------------------------------
        | Lấy dữ liệu từ FORM
        |--------------------------------------------------------------------------
        */

        $templateIdPost = isset($_POST['template_id'])
            ? (int) $_POST['template_id']
            : $templateId;

        $slugPost = trim($_POST['slug'] ?? $slug);

        $groomNamePost = trim($_POST['groom_name'] ?? '');
        $brideNamePost = trim($_POST['bride_name'] ?? '');

        $weddingDatePost = $_POST['wedding_date'] ?? '';
        $weddingTimePost = $_POST['wedding_time'] ?? '';

        $venuePost = trim($_POST['venue'] ?? '');
        $addressPost = trim($_POST['address'] ?? '');

        $storyPost = trim($_POST['story'] ?? '');


        /*
        |--------------------------------------------------------------------------
        | Kiểm tra dữ liệu bắt buộc
        |--------------------------------------------------------------------------
        */

        if ($groomNamePost === '') {
            throw new Exception(
                'Vui lòng nhập tên chú rể.'
            );
        }

        if ($brideNamePost === '') {
            throw new Exception(
                'Vui lòng nhập tên cô dâu.'
            );
        }

        if ($weddingDatePost === '') {
            throw new Exception(
                'Vui lòng chọn ngày cưới.'
            );
        }

        if ($slugPost === '') {
            throw new Exception(
                'Slug thiệp không được để trống.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Kiểm tra SLUG có bị trùng không
        |--------------------------------------------------------------------------
        */

        $slugCheck = $pdo->prepare("
            SELECT id
            FROM weddings
            WHERE slug = :slug
            AND id != :id
            LIMIT 1
        ");

        $slugCheck->execute([
            'slug' => $slugPost,
            'id' => $id
        ]);

        if ($slugCheck->fetch()) {
            throw new Exception(
                'Slug này đã được sử dụng cho một thiệp khác.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | BẮT ĐẦU TRANSACTION
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | 11. UPDATE THÔNG TIN THIỆP
        |--------------------------------------------------------------------------
        */

        $updateWedding = $pdo->prepare("
            UPDATE weddings
            SET
                template_id = :template_id,
                slug = :slug,
                groom_name = :groom_name,
                bride_name = :bride_name,
                wedding_date = :wedding_date,
                wedding_time = :wedding_time,
                venue = :venue,
                address = :address,
                story = :story,
                updated_at = NOW()
            WHERE id = :id
        ");

        $updateWedding->execute([
            'template_id' => $templateIdPost,
            'slug' => $slugPost,
            'groom_name' => $groomNamePost,
            'bride_name' => $brideNamePost,
            'wedding_date' => $weddingDatePost,
            'wedding_time' => $weddingTimePost,
            'venue' => $venuePost,
            'address' => $addressPost,
            'story' => $storyPost,
            'id' => $id
        ]);


        /*
        |--------------------------------------------------------------------------
        | 12. CẬP NHẬT HỘP QUÀ
        |--------------------------------------------------------------------------
        */

        $giftModePost = $_POST['gift_mode'] ?? 'bank';

        $groomGiftNamePost = trim(
            $_POST['groom_gift_name'] ?? ''
        );

        $groomBankNamePost = trim(
            $_POST['groom_bank_name'] ?? ''
        );

        $groomAccountNumberPost = trim(
            $_POST['groom_account_number'] ?? ''
        );

        $groomAccountNamePost = trim(
            $_POST['groom_account_name'] ?? ''
        );


        $brideGiftNamePost = trim(
            $_POST['bride_gift_name'] ?? ''
        );

        $brideBankNamePost = trim(
            $_POST['bride_bank_name'] ?? ''
        );

        $brideAccountNumberPost = trim(
            $_POST['bride_account_number'] ?? ''
        );

        $brideAccountNamePost = trim(
            $_POST['bride_account_name'] ?? ''
        );

        $giftMessagePost = trim(
            $_POST['gift_message'] ?? ''
        );


        /*
        |--------------------------------------------------------------------------
        | Kiểm tra xem đã có dòng gift chưa
        |--------------------------------------------------------------------------
        */

        $checkGift = $pdo->prepare("
            SELECT id
            FROM wedding_gifts
            WHERE wedding_id = :wedding_id
            LIMIT 1
        ");

        $checkGift->execute([
            'wedding_id' => $id
        ]);

        $existingGift = $checkGift->fetch(
            PDO::FETCH_ASSOC
        );


        if ($existingGift) {

            /*
            |--------------------------------------------------------------------------
            | UPDATE GIFT
            |--------------------------------------------------------------------------
            */

            $updateGift = $pdo->prepare("
                UPDATE wedding_gifts
                SET
                    gift_mode = :gift_mode,
                    groom_name = :groom_name,
                    groom_bank_name = :groom_bank_name,
                    groom_account_number = :groom_account_number,
                    groom_account_name = :groom_account_name,
                    bride_name = :bride_name,
                    bride_bank_name = :bride_bank_name,
                    bride_account_number = :bride_account_number,
                    bride_account_name = :bride_account_name,
                    gift_message = :gift_message
                WHERE wedding_id = :wedding_id
            ");

            $updateGift->execute([
                'gift_mode' => $giftModePost,

                'groom_name' => $groomGiftNamePost,
                'groom_bank_name' => $groomBankNamePost,
                'groom_account_number' => $groomAccountNumberPost,
                'groom_account_name' => $groomAccountNamePost,

                'bride_name' => $brideGiftNamePost,
                'bride_bank_name' => $brideBankNamePost,
                'bride_account_number' => $brideAccountNumberPost,
                'bride_account_name' => $brideAccountNamePost,

                'gift_message' => $giftMessagePost,

                'wedding_id' => $id
            ]);

        } else {

            /*
            |--------------------------------------------------------------------------
            | INSERT GIFT NẾU CHƯA CÓ
            |--------------------------------------------------------------------------
            */

            $insertGift = $pdo->prepare("
                INSERT INTO wedding_gifts (
                    wedding_id,
                    gift_mode,
                    groom_name,
                    groom_bank_name,
                    groom_account_number,
                    groom_account_name,
                    bride_name,
                    bride_bank_name,
                    bride_account_number,
                    bride_account_name,
                    gift_message
                )
                VALUES (
                    :wedding_id,
                    :gift_mode,
                    :groom_name,
                    :groom_bank_name,
                    :groom_account_number,
                    :groom_account_name,
                    :bride_name,
                    :bride_bank_name,
                    :bride_account_number,
                    :bride_account_name,
                    :gift_message
                )
            ");

            $insertGift->execute([
                'wedding_id' => $id,
                'gift_mode' => $giftModePost,

                'groom_name' => $groomGiftNamePost,
                'groom_bank_name' => $groomBankNamePost,
                'groom_account_number' => $groomAccountNumberPost,
                'groom_account_name' => $groomAccountNamePost,

                'bride_name' => $brideGiftNamePost,
                'bride_bank_name' => $brideBankNamePost,
                'bride_account_number' => $brideAccountNumberPost,
                'bride_account_name' => $brideAccountNamePost,

                'gift_message' => $giftMessagePost
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 13. CẬP NHẬT CÁC SỰ KIỆN
        |--------------------------------------------------------------------------
        |
        | Xóa các event cũ rồi tạo lại theo form.
        | Cách này giúp việc thêm/xóa/sắp xếp event đơn giản hơn.
        |
        */

        $deleteEvents = $pdo->prepare("
            DELETE FROM wedding_events
            WHERE wedding_id = :wedding_id
        ");

        $deleteEvents->execute([
            'wedding_id' => $id
        ]);


        $eventNames = $_POST['event_name'] ?? [];
        $eventDates = $_POST['event_date'] ?? [];
        $eventTimes = $_POST['event_time'] ?? [];
        $eventLocations = $_POST['event_location'] ?? [];
        $eventAddresses = $_POST['event_address'] ?? [];
        $eventDescriptions = $_POST['event_description'] ?? [];


        $insertEvent = $pdo->prepare("
            INSERT INTO wedding_events (
                wedding_id,
                event_name,
                event_date,
                event_time,
                event_location,
                event_address,
                event_description
            )
            VALUES (
                :wedding_id,
                :event_name,
                :event_date,
                :event_time,
                :event_location,
                :event_address,
                :event_description
            )
        ");


        $eventCount = count($eventNames);


        for ($i = 0; $i < $eventCount; $i++) {

            $eventName = trim(
                $eventNames[$i] ?? ''
            );

            /*
             * Nếu người dùng để trống hoàn toàn
             * thì bỏ qua event đó.
             */

            if ($eventName === '') {
                continue;
            }


            $insertEvent->execute([
                'wedding_id' => $id,

                'event_name' => $eventName,

                'event_date' =>
                    $eventDates[$i] ?? null,

                'event_time' =>
                    $eventTimes[$i] ?? null,

                'event_location' =>
                    trim(
                        $eventLocations[$i] ?? ''
                    ),

                'event_address' =>
                    trim(
                        $eventAddresses[$i] ?? ''
                    ),

                'event_description' =>
                    trim(
                        $eventDescriptions[$i] ?? ''
                    )
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | 14. COMMIT
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        /*
        |--------------------------------------------------------------------------
        | 15. THÔNG BÁO THÀNH CÔNG
        |--------------------------------------------------------------------------
        */

        $successMessage =
            'Đã lưu thay đổi thiệp cưới thành công.';


        /*
        |--------------------------------------------------------------------------
        | 16. CẬP NHẬT LẠI BIẾN ĐỂ FORM HIỂN THỊ DỮ LIỆU MỚI
        |--------------------------------------------------------------------------
        */

        $templateId = $templateIdPost;
        $slug = $slugPost;

        $groomName = $groomNamePost;
        $brideName = $brideNamePost;

        $weddingDate = $weddingDatePost;
        $weddingTime = $weddingTimePost;

        $venue = $venuePost;
        $address = $addressPost;

        $story = $storyPost;

        $giftMode = $giftModePost;

        $groomGiftName = $groomGiftNamePost;
        $groomBankName = $groomBankNamePost;
        $groomAccountNumber = $groomAccountNumberPost;
        $groomAccountName = $groomAccountNamePost;

        $brideGiftName = $brideGiftNamePost;
        $brideBankName = $brideBankNamePost;
        $brideAccountNumber = $brideAccountNumberPost;
        $brideAccountName = $brideAccountNamePost;

        $giftMessage = $giftMessagePost;


        /*
        |--------------------------------------------------------------------------
        | LẤY LẠI EVENTS SAU KHI UPDATE
        |--------------------------------------------------------------------------
        */

        $eventsStmt = $pdo->prepare("
            SELECT *
            FROM wedding_events
            WHERE wedding_id = :wedding_id
            ORDER BY id ASC
        ");

        $eventsStmt->execute([
            'wedding_id' => $id
        ]);

        $events = $eventsStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    } catch (Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | ROLLBACK NẾU CÓ LỖI
        |--------------------------------------------------------------------------
        */

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errorMessage =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| 17. HÀM ESCAPE HTML
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Sửa thiệp cưới -
        <?= e($groomName) ?> &
        <?= e($brideName) ?>
    </title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f7f4f2;
            color: #333;
        }

        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px 60px;
        }


        /* =================================================
           HEADER
        ================================================= */

        .top-header {
            background: linear-gradient(
                135deg,
                #8b1e2d,
                #b84d5d
            );

            color: #fff;

            padding: 25px 30px;

            border-radius: 18px;

            margin-bottom: 25px;

            box-shadow:
                0 10px 30px
                rgba(0,0,0,.12);
        }

        .top-header h1 {
            margin: 0 0 8px;

            font-size: 28px;
        }

        .top-header p {
            margin: 0;

            opacity: .9;

            line-height: 1.6;
        }


        /* =================================================
           BACK BUTTON
        ================================================= */

        .back-area {
            margin-bottom: 20px;
        }

        .back-button {
            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 11px 18px;

            border-radius: 10px;

            background: #fff;

            color: #8b1e2d;

            text-decoration: none;

            font-weight: 700;

            box-shadow:
                0 4px 15px
                rgba(0,0,0,.06);

            transition: .2s;
        }

        .back-button:hover {
            transform: translateY(-2px);

            box-shadow:
                0 7px 20px
                rgba(0,0,0,.1);
        }


        /* =================================================
           CARD
        ================================================= */

        .card {
            background: #fff;

            border-radius: 16px;

            padding: 25px;

            margin-bottom: 22px;

            box-shadow:
                0 5px 25px
                rgba(0,0,0,.06);
        }

        .card-title {
            display: flex;

            align-items: center;

            gap: 12px;

            margin-bottom: 22px;
        }

        .card-title h2 {
            margin: 0;

            font-size: 21px;

            color: #8b1e2d;
        }

        .number {
            width: 36px;

            height: 36px;

            min-width: 36px;

            display: flex;

            align-items: center;

            justify-content: center;

            background: #8b1e2d;

            color: #fff;

            border-radius: 50%;

            font-size: 15px;

            font-weight: 700;
        }


        /* =================================================
           FORM
        ================================================= */

        .form-group {
            margin-bottom: 18px;
        }

        .form-group:last-child {
            margin-bottom: 0;
        }

        label {
            display: block;

            margin-bottom: 7px;

            font-weight: 700;

            color: #555;
        }

        input,
        textarea,
        select {
            width: 100%;

            padding: 12px 14px;

            border: 1px solid #ddd;

            border-radius: 10px;

            font-size: 15px;

            font-family: inherit;

            background: #fff;

            outline: none;

            transition: .2s;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #a93245;

            box-shadow:
                0 0 0 3px
                rgba(169,50,69,.08);
        }

        textarea {
            min-height: 130px;

            resize: vertical;

            line-height: 1.6;
        }

        .grid-2 {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 18px;
        }

        .grid-3 {
            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 15px;
        }

        .note {
            margin-top: 7px;

            font-size: 13px;

            color: #888;

            line-height: 1.5;
        }


        /* =================================================
           ALERT
        ================================================= */

        .alert {
            padding: 15px 18px;

            border-radius: 12px;

            margin-bottom: 20px;

            line-height: 1.5;

            font-weight: 600;
        }

        .alert-success {
            background: #eaf8ef;

            color: #23733d;

            border: 1px solid #bfe5ca;
        }

        .alert-error {
            background: #fff0f0;

            color: #a12626;

            border: 1px solid #f1c1c1;
        }


        /* =================================================
           THÔNG TIN THIỆP
        ================================================= */

        .slug-row {
            display: flex;

            align-items: stretch;

            gap: 10px;
        }

        .slug-row input {
            flex: 1;
        }

        .slug-prefix {
            display: flex;

            align-items: center;

            padding: 0 14px;

            background: #f5f5f5;

            border: 1px solid #ddd;

            border-radius: 10px;

            color: #777;

            white-space: nowrap;
        }


        /* =================================================
           TEMPLATE
        ================================================= */

        .template-grid {
            display: grid;

            grid-template-columns:
                repeat(3, minmax(0, 1fr));

            gap: 18px;
        }

        .template-option {
            position: relative;
        }

        .template-option input {
            position: absolute;

            opacity: 0;

            pointer-events: none;
        }

        .template-label {
            display: block;

            border: 2px solid #eee;

            border-radius: 14px;

            padding: 15px;

            cursor: pointer;

            transition: .2s;

            height: 100%;
        }

        .template-label:hover {
            border-color: #dca0aa;

            transform: translateY(-2px);
        }

        .template-option input:checked
        + .template-label {
            border-color: #8b1e2d;

            background: #fff8f9;

            box-shadow:
                0 5px 20px
                rgba(139,30,45,.1);
        }

        .template-preview {
            height: 150px;

            background: #f4e9e7;

            border-radius: 10px;

            display: flex;

            align-items: center;

            justify-content: center;

            margin-bottom: 12px;

            color: #8b1e2d;

            font-size: 20px;

            font-weight: 700;

            overflow: hidden;
        }

        .template-preview img {
            width: 100%;

            height: 100%;

            object-fit: cover;
        }

        .template-name {
            font-weight: 700;

            color: #555;
        }


        /* =================================================
           ALBUM
        ================================================= */

        .album-upload-box {
            border: 2px dashed #ddd;

            border-radius: 15px;

            padding: 30px 20px;

            text-align: center;

            background: #fcfaf9;
        }

        .album-upload-icon {
            font-size: 42px;

            margin-bottom: 8px;
        }

        .album-upload-box h3 {
            margin: 5px 0 7px;

            color: #555;
        }

        .album-upload-box p {
            margin: 0 0 17px;

            color: #888;
        }

        .album-select-button,
        .story-select-button {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 11px 20px;

            background: #8b1e2d;

            color: #fff;

            border-radius: 10px;

            cursor: pointer;

            font-weight: 700;

            transition: .2s;
        }

        .album-select-button:hover,
        .story-select-button:hover {
            background: #701622;

            transform: translateY(-1px);
        }

        .album-upload-note,
        .story-upload-note,
        .music-note,
        .qr-note {
            margin-top: 10px;

            color: #888;

            font-size: 13px;
        }

        .album-count {
            margin-top: 15px;

            color: #8b1e2d;

            font-weight: 700;
        }

        .album-preview {
            display: grid;

            grid-template-columns:
                repeat(5, minmax(0, 1fr));

            gap: 12px;

            margin-top: 18px;
        }

        .album-preview-item {
            position: relative;

            aspect-ratio: 4 / 5;

            border-radius: 10px;

            overflow: hidden;

            background: #eee;
        }

        .album-preview-item img {
            width: 100%;

            height: 100%;

            object-fit: cover;

            display: block;
        }

        .album-preview-number {
            position: absolute;

            left: 7px;

            top: 7px;

            background: rgba(0,0,0,.65);

            color: #fff;

            padding: 4px 7px;

            border-radius: 6px;

            font-size: 12px;

            font-weight: 700;
        }

        .album-preview-remove {
            position: absolute;

            right: 7px;

            top: 7px;

            width: 28px;

            height: 28px;

            border: 0;

            border-radius: 50%;

            background: rgba(139,30,45,.9);

            color: #fff;

            font-size: 18px;

            line-height: 1;

            cursor: pointer;
        }


        /* =================================================
           ẢNH CHUYỆN TÌNH
        ================================================= */

        .story-upload-box {
            border: 2px dashed #ddd;

            border-radius: 15px;

            padding: 30px 20px;

            text-align: center;

            background: #fcfaf9;
        }

        .story-upload-icon {
            font-size: 42px;

            margin-bottom: 8px;
        }

        .story-upload-box h3 {
            margin: 5px 0 8px;

            color: #555;
        }

        .story-upload-box p {
            color: #888;

            line-height: 1.6;

            margin: 0 auto 17px;

            max-width: 650px;
        }

        .story-preview {
            margin-top: 20px;

            text-align: center;
        }

        .story-preview img {
            width: 220px;

            max-width: 100%;

            aspect-ratio: 4 / 5;

            object-fit: cover;

            border-radius: 12px;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.12);
        }

        .current-image {
            margin-top: 18px;

            text-align: center;
        }

        .current-image-title {
            color: #777;

            font-size: 14px;

            margin-bottom: 10px;
        }

        .current-image img {
            width: 200px;

            max-width: 100%;

            aspect-ratio: 4 / 5;

            object-fit: cover;

            border-radius: 12px;

            box-shadow:
                0 8px 25px
                rgba(0,0,0,.12);
        }


        /* =================================================
           EVENTS
        ================================================= */

        .event-box {
            background: #fcfaf9;

            border: 1px solid #eee;

            border-radius: 14px;

            padding: 20px;

            margin-bottom: 15px;
        }

        .event-header {
            display: flex;

            align-items: center;

            justify-content: space-between;

            margin-bottom: 17px;

            color: #8b1e2d;
        }

        .remove-event {
            border: 0;

            background: #fff0f0;

            color: #b32626;

            padding: 7px 12px;

            border-radius: 8px;

            cursor: pointer;

            font-weight: 700;
        }

        .add-event-button {
            border: 1px dashed #c58b94;

            background: #fff8f9;

            color: #8b1e2d;

            padding: 11px 16px;

            border-radius: 10px;

            cursor: pointer;

            font-weight: 700;
        }


        /* =================================================
           GIA ĐÌNH
        ================================================= */

        .family-grid {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 20px;
        }

        .family-box {
            background: #fcfaf9;

            border: 1px solid #eee;

            border-radius: 14px;

            padding: 20px;
        }

        .family-box h3 {
            margin-top: 0;

            color: #8b1e2d;
        }


        /* =================================================
           GIFT
        ================================================= */

        .gift-mode-grid {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 15px;

            margin: 20px 0;
        }

        .gift-mode-option {
            position: relative;
        }

        .gift-mode-option input {
            position: absolute;

            opacity: 0;

            pointer-events: none;
        }

        .gift-mode-label {
            display: block;

            border: 2px solid #eee;

            border-radius: 14px;

            padding: 20px;

            cursor: pointer;

            transition: .2s;

            height: 100%;
        }

        .gift-mode-label:hover {
            border-color: #dca0aa;
        }

        .gift-mode-option input:checked
        + .gift-mode-label {
            border-color: #8b1e2d;

            background: #fff8f9;
        }

        .gift-mode-icon {
            font-size: 30px;
        }

        .gift-mode-label h3 {
            margin: 8px 0;

            color: #555;
        }

        .gift-mode-label p {
            margin: 0;

            color: #888;

            line-height: 1.5;
        }

        .gift-panel {
            background: #fcfaf9;

            border-radius: 14px;

            padding: 20px;

            margin-top: 15px;
        }

        .gift-panel-title {
            margin-top: 0;

            color: #8b1e2d;
        }

        .gift-person {
            background: #fff;

            border: 1px solid #eee;

            border-radius: 12px;

            padding: 18px;

            margin-bottom: 15px;
        }

        .gift-person:last-child {
            margin-bottom: 0;
        }

        .gift-person h4 {
            margin-top: 0;

            color: #666;
        }

        .message-preview {
            margin-top: 15px;

            padding: 15px;

            background: #fff;

            border-radius: 10px;

            color: #666;

            line-height: 1.6;
        }


        /* =================================================
           ALBUM CŨ
        ================================================= */

        .existing-album {
            margin-top: 20px;
        }

        .existing-album-title {
            font-weight: 700;

            color: #666;

            margin-bottom: 12px;
        }

        .existing-album-grid {
            display: grid;

            grid-template-columns:
                repeat(5, minmax(0, 1fr));

            gap: 12px;
        }

        .existing-photo {
            position: relative;

            aspect-ratio: 4 / 5;

            overflow: hidden;

            border-radius: 10px;

            background: #eee;
        }

        .existing-photo img {
            width: 100%;

            height: 100%;

            object-fit: cover;
        }

        .existing-photo-number {
            position: absolute;

            left: 7px;

            top: 7px;

            background: rgba(0,0,0,.65);

            color: #fff;

            padding: 4px 7px;

            border-radius: 6px;

            font-size: 12px;
        }


        /* =================================================
           MUSIC
        ================================================= */

        .current-music {
            margin-top: 15px;

            background: #f8f5f4;

            padding: 15px;

            border-radius: 10px;
        }

        .current-music audio {
            width: 100%;

            margin-top: 10px;
        }


        /* =================================================
           SUBMIT
        ================================================= */

        .submit-area {
            display: flex;

            gap: 12px;

            align-items: center;

            justify-content: center;

            margin-top: 30px;

            flex-wrap: wrap;
        }

        .submit-button {
            border: 0;

            background:
                linear-gradient(
                    135deg,
                    #8b1e2d,
                    #b84d5d
                );

            color: #fff;

            padding: 15px 32px;

            border-radius: 12px;

            font-size: 16px;

            font-weight: 700;

            cursor: pointer;

            box-shadow:
                0 8px 25px
                rgba(139,30,45,.2);

            transition: .2s;
        }

        .submit-button:hover {
            transform: translateY(-2px);

            box-shadow:
                0 12px 30px
                rgba(139,30,45,.25);
        }

        .cancel-button {
            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding: 15px 25px;

            background: #fff;

            color: #555;

            border: 1px solid #ddd;

            border-radius: 12px;

            text-decoration: none;

            font-weight: 700;
        }


        /* =================================================
           RESPONSIVE
        ================================================= */

        @media (max-width: 800px) {

            .grid-3 {
                grid-template-columns: 1fr;
            }

            .template-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .album-preview,
            .existing-album-grid {
                grid-template-columns:
                    repeat(3, minmax(0, 1fr));
            }

        }


        @media (max-width: 600px) {

            .page {
                padding: 15px 12px 40px;
            }

            .top-header {
                padding: 20px;
            }

            .top-header h1 {
                font-size: 23px;
            }

            .card {
                padding: 18px;
            }

            .grid-2,
            .family-grid,
            .gift-mode-grid,
            .template-grid {
                grid-template-columns: 1fr;
            }

            .album-preview,
            .existing-album-grid {
                grid-template-columns:
                    repeat(2, minmax(0, 1fr));
            }

            .slug-row {
                flex-direction: column;
            }

            .slug-prefix {
                padding: 10px 12px;
            }

        }

    </style>

</head>


<body>


<div class="page">


    <!-- =================================================
         HEADER
    ================================================== -->

    <div class="top-header">

        <h1>
            ✏️ Sửa thiệp cưới
        </h1>

        <p>
            Chỉnh sửa thông tin của
            <strong>
                <?= e($groomName) ?>
                &
                <?= e($brideName) ?>
            </strong>
        </p>

    </div>


    <!-- =================================================
         BACK
    ================================================== -->

    <div class="back-area">

        <a
            href="index.php"
            class="back-button"
        >
            ← Quay lại danh sách thiệp
        </a>

    </div>


    <!-- =================================================
         ALERT
    ================================================== -->

    <?php if ($successMessage): ?>

        <div class="alert alert-success">

            ✅
            <?= e($successMessage) ?>

        </div>

    <?php endif; ?>


    <?php if ($errorMessage): ?>

        <div class="alert alert-error">

            ❌
            <?= e($errorMessage) ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         FORM
    ================================================== -->

    <form
        method="POST"
        enctype="multipart/form-data"
        id="editWeddingForm"
    >


        <!-- =================================================
             1. TEMPLATE
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">
                    1
                </div>

                <h2>
                    Mẫu thiệp
                </h2>

            </div>


            <div class="template-grid">

                <?php foreach ($templates as $template): ?>

                    <?php

                    $isChecked =
                        ((int) $template['id'] ===
                        (int) $templateId);

                    ?>

                    <div class="template-option">

                        <input
                            type="radio"
                            name="template_id"
                            id="template_<?= (int) $template['id'] ?>"
                            value="<?= (int) $template['id'] ?>"
                            <?= $isChecked ? 'checked' : '' ?>
                        >

                        <label
                            class="template-label"
                            for="template_<?= (int) $template['id'] ?>"
                        >

                            <div class="template-preview">

                                <?php

                                /*
                                 * Nếu bảng templates có
                                 * preview_image thì dùng ảnh.
                                 */

                                if (
                                    isset($template['preview_image'])
                                    &&
                                    $template['preview_image']
                                ):

                                ?>

                                    <img
                                        src="<?= e($template['preview_image']) ?>"
                                        alt="<?= e($template['name'] ?? 'Template') ?>"
                                    >

                                <?php else: ?>

                                    💌

                                <?php endif; ?>

                            </div>


                            <div class="template-name">

                                <?= e(
                                    $template['name']
                                    ?? 'Template #' . $template['id']
                                ) ?>

                            </div>

                        </label>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- =================================================
             2. THÔNG TIN CƠ BẢN
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">
                    2
                </div>

                <h2>
                    Thông tin cơ bản
                </h2>

            </div>


            <div class="grid-2">

                <div class="form-group">

                    <label>
                        Tên chú rể *
                    </label>

                    <input
                        type="text"
                        name="groom_name"
                        value="<?= e($groomName) ?>"
                        placeholder="Nhập tên chú rể"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Tên cô dâu *
                    </label>

                    <input
                        type="text"
                        name="bride_name"
                        value="<?= e($brideName) ?>"
                        placeholder="Nhập tên cô dâu"
                        required
                    >

                </div>

            </div>


            <div class="form-group">

                <label>
                    Đường dẫn thiệp (slug) *
                </label>

                <div class="slug-row">

                    <div class="slug-prefix">
                        /w/?slug=
                    </div>

                    <input
                        type="text"
                        name="slug"
                        value="<?= e($slug) ?>"
                        placeholder="nguyen-nam-ngoc-anh"
                        required
                    >

                </div>

                <div class="note">
                    Đây là phần dùng để mở thiệp trên đường dẫn.
                    Không nên đặt dấu cách hoặc ký tự đặc biệt.
                </div>

            </div>

        </section>


        <!-- =================================================
             3. GIA ĐÌNH HAI BÊN
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">
                    3
                </div>

                <h2>
                    Gia đình hai bên
                </h2>

            </div>


            <div class="family-grid">


                <!-- CHÚ RỂ -->

                <div class="family-box">

                    <h3>
                        💙 Gia đình chú rể
                    </h3>


                    <?php

                    $groomParents = [];

                    $brideParents = [];

                    foreach ($parents as $parent) {

                        $side =
                            strtolower(
                                trim(
                                    $parent['side']
                                    ?? $parent['family_side']
                                    ?? ''
                                )
                            );

                        if (
                            $side === 'groom'
                            ||
                            $side === 'chu_re'
                            ||
                            $side === 'chú rể'
                        ) {

                            $groomParents[] =
                                $parent;

                        } elseif (
                            $side === 'bride'
                            ||
                            $side === 'co_dau'
                            ||
                            $side === 'cô dâu'
                        ) {

                            $brideParents[] =
                                $parent;
                        }
                    }

                    $groomFather = '';
                    $groomMother = '';

                    $brideFather = '';
                    $brideMother = '';

                    foreach ($groomParents as $parent) {

                        $role =
                            strtolower(
                                trim(
                                    $parent['role']
                                    ?? $parent['parent_role']
                                    ?? ''
                                )
                            );

                        $name =
                            $parent['name']
                            ?? $parent['parent_name']
                            ?? '';

                        if (
                            strpos($role, 'father') !== false
                            ||
                            strpos($role, 'cha') !== false
                            ||
                            strpos($role, 'bố') !== false
                        ) {

                            $groomFather = $name;

                        } elseif (
                            strpos($role, 'mother') !== false
                            ||
                            strpos($role, 'me') !== false
                            ||
                            strpos($role, 'mẹ') !== false
                        ) {

                            $groomMother = $name;
                        }
                    }


                    foreach ($brideParents as $parent) {

                        $role =
                            strtolower(
                                trim(
                                    $parent['role']
                                    ?? $parent['parent_role']
                                    ?? ''
                                )
                            );

                        $name =
                            $parent['name']
                            ?? $parent['parent_name']
                            ?? '';

                        if (
                            strpos($role, 'father') !== false
                            ||
                            strpos($role, 'cha') !== false
                            ||
                            strpos($role, 'bố') !== false
                        ) {

                            $brideFather = $name;

                        } elseif (
                            strpos($role, 'mother') !== false
                            ||
                            strpos($role, 'me') !== false
                            ||
                            strpos($role, 'mẹ') !== false
                        ) {

                            $brideMother = $name;
                        }
                    }

                    ?>


                    <div class="form-group">

                        <label>
                            Bố chú rể
                        </label>

                        <input
                            type="text"
                            name="groom_father"
                            value="<?= e($groomFather) ?>"
                            placeholder="Họ tên bố chú rể"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Mẹ chú rể
                        </label>

                        <input
                            type="text"
                            name="groom_mother"
                            value="<?= e($groomMother) ?>"
                            placeholder="Họ tên mẹ chú rể"
                        >

                    </div>

                </div>


                <!-- CÔ DÂU -->

                <div class="family-box">

                    <h3>
                        💗 Gia đình cô dâu
                    </h3>


                    <div class="form-group">

                        <label>
                            Bố cô dâu
                        </label>

                        <input
                            type="text"
                            name="bride_father"
                            value="<?= e($brideFather) ?>"
                            placeholder="Họ tên bố cô dâu"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Mẹ cô dâu
                        </label>

                        <input
                            type="text"
                            name="bride_mother"
                            value="<?= e($brideMother) ?>"
                            placeholder="Họ tên mẹ cô dâu"
                        >

                    </div>

                </div>

            </div>

        </section>


        <!-- =================================================
             4. SỰ KIỆN
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">
                    4
                </div>

                <h2>
                    Các sự kiện
                </h2>

            </div>


            <div id="eventList">

                <?php if (!empty($events)): ?>

                    <?php foreach ($events as $index => $event): ?>

                        <div class="event-box">

                            <div class="event-header">

                                <strong>
                                    Sự kiện <?= $index + 1 ?>
                                </strong>

                                <button
                                    type="button"
                                    class="remove-event"
                                    onclick="removeEvent(this)"
                                >
                                    Xóa
                                </button>

                            </div>


                            <div class="form-group">

                                <label>
                                    Tên sự kiện
                                </label>

                                <input
                                    type="text"
                                    name="event_name[]"
                                    value="<?= e(
                                        $event['event_name']
                                        ?? ''
                                    ) ?>"
                                    placeholder="Ví dụ: Tiệc cưới"
                                >

                            </div>


                            <div class="grid-2">

                                <div class="form-group">

                                    <label>
                                        Ngày
                                    </label>

                                    <input
                                        type="date"
                                        name="event_date[]"
                                        value="<?= e(
                                            $event['event_date']
                                            ?? ''
                                        ) ?>"
                                    >

                                </div>


                                <div class="form-group">

                                    <label>
                                        Giờ
                                    </label>

                                    <input
                                        type="time"
                                        name="event_time[]"
                                        value="<?= e(
                                            $event['event_time']
                                            ?? ''
                                        ) ?>"
                                    >

                                </div>

                            </div>


                            <div class="form-group">

                                <label>
                                    Địa điểm
                                </label>

                                <input
                                    type="text"
                                    name="event_location[]"
                                    value="<?= e(
                                        $event['event_location']
                                        ?? ''
                                    ) ?>"
                                    placeholder="Tên địa điểm"
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Địa chỉ
                                </label>

                                <input
                                    type="text"
                                    name="event_address[]"
                                    value="<?= e(
                                        $event['event_address']
                                        ?? ''
                                    ) ?>"
                                    placeholder="Địa chỉ"
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Mô tả
                                </label>

                                <textarea
                                    name="event_description[]"
                                    placeholder="Thông tin thêm..."
                                ><?= e(
                                    $event['event_description']
                                    ?? ''
                                ) ?></textarea>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="event-box">

                        <div class="event-header">

                            <strong>
                                Sự kiện 1
                            </strong>

                            <button
                                type="button"
                                class="remove-event"
                                onclick="removeEvent(this)"
                            >
                                Xóa
                            </button>

                        </div>


                        <div class="form-group">

                            <label>
                                Tên sự kiện
                            </label>

                            <input
                                type="text"
                                name="event_name[]"
                                placeholder="Ví dụ: Tiệc cưới"
                            >

                        </div>


                        <div class="grid-2">

                            <div class="form-group">

                                <label>
                                    Ngày
                                </label>

                                <input
                                    type="date"
                                    name="event_date[]"
                                >

                            </div>


                            <div class="form-group">

                                <label>
                                    Giờ
                                </label>

                                <input
                                    type="time"
                                    name="event_time[]"
                                >

                            </div>

                        </div>


                        <div class="form-group">

                            <label>
                                Địa điểm
                            </label>

                            <input
                                type="text"
                                name="event_location[]"
                                placeholder="Tên địa điểm"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Địa chỉ
                            </label>

                            <input
                                type="text"
                                name="event_address[]"
                                placeholder="Địa chỉ"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Mô tả
                            </label>

                            <textarea
                                name="event_description[]"
                                placeholder="Thông tin thêm..."
                            ></textarea>

                        </div>

                    </div>

                <?php endif; ?>

            </div>


            <button
                type="button"
                class="add-event-button"
                onclick="addEvent()"
            >
                + Thêm sự kiện
            </button>

        </section>


        <!-- =================================================
             5. THÔNG TIN ĐÁM CƯỚI
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">
                    5
                </div>

                <h2>
                    Thông tin đám cưới
                </h2>

            </div>


            <div class="grid-2">

                <div class="form-group">

                    <label>
                        Ngày cưới *
                    </label>

                    <input
                        type="date"
                        name="wedding_date"
                        value="<?= e($weddingDate) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Giờ cưới
                    </label>

                    <input
                        type="time"
                        name="wedding_time"
                        value="<?= e($weddingTime) ?>"
                    >

                </div>

            </div>


            <div class="form-group">

                <label>
                    Địa điểm
                </label>

                <input
                    type="text"
                    name="venue"
                    value="<?= e($venue) ?>"
                    placeholder="Tên nhà hàng / tư gia..."
                >

            </div>


            <div class="form-group">

                <label>
                    Địa chỉ
                </label>

                <input
                    type="text"
                    name="address"
                    value="<?= e($address) ?>"
                    placeholder="Địa chỉ tổ chức"
                >

            </div>

        </section>


        <!-- =================================================
             6. CÂU CHUYỆN
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">
                    6
                </div>

                <h2>
                    Câu chuyện tình yêu
                </h2>

            </div>


            <div class="form-group">

                <label>
                    Nội dung câu chuyện
                </label>

                <textarea
                    name="story"
                    placeholder="Viết câu chuyện của hai bạn..."
                ><?= e($story) ?></textarea>

            </div>

        </section>


        <!-- =================================================
             7. ALBUM ẢNH
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">
                    7
                </div>

                <h2>
                    Album ảnh cưới
                </h2>

            </div>


            <?php if (!empty($album)): ?>

                <div class="existing-album">

                    <div class="existing-album-title">

                        📷 Album hiện tại
                        (<?= count($album) ?> ảnh)

                    </div>


                    <div class="existing-album-grid">

                        <?php foreach ($album as $photo): ?>

                            <div class="existing-photo">

                                <img
                                    src="<?= e(
                                        $photo['image_path']
                                    ) ?>"
                                    alt="Ảnh cưới"
                                >

                                <div class="existing-photo-number">

                                    #<?= e(
                                        $photo['sort_order']
                                        ?? ''
                                    ) ?>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>


            <div
                class="album-upload-box"
                style="margin-top:20px;"
            >

                <div class="album-upload-icon">
                    📷
                </div>

                <h3>
                    Thêm ảnh mới vào album
                </h3>

                <p>
                    Ảnh mới sẽ được thêm vào sau các ảnh hiện tại.
                </p>


                <label
                    for="album_images"
                    class="album-select-button"
                >
                    + Chọn nhiều ảnh
                </label>


                <input
                    type="file"
                    id="album_images"
                    name="album_images[]"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    hidden
                >


                <div class="album-upload-note">
                    JPG, PNG hoặc WEBP · Tối đa 10MB / ảnh
                </div>

            </div>


            <div
                id="albumPreview"
                class="album-preview"
            ></div>


            <div
                id="albumCount"
                class="album-count"
            ></div>

        </section>


        <!-- =================================================
             8. ẢNH CHUYỆN TÌNH
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">
                    8
                </div>

                <h2>
                    Ảnh Chuyện tình của chúng mình
                </h2>

            </div>


            <?php if (!empty($storyImage)): ?>

                <div class="current-image">

                    <div class="current-image-title">
                        Ảnh hiện tại
                    </div>

                    <img
                        src="<?= e($storyImage) ?>"
                        alt="Ảnh Chuyện tình hiện tại"
                    >

                </div>

            <?php endif; ?>


            <div
                class="story-upload-box"
                style="margin-top:20px;"
            >

                <div class="story-upload-icon">
                    💑
                </div>

                <h3>
                    Thay ảnh Chuyện tình
                </h3>

                <p>
                    Nếu không chọn ảnh mới,
                    ảnh hiện tại sẽ được giữ nguyên.
                </p>
                <label
                    for="story_image"
                    class="story-select-button"
                >
                    + Chọn ảnh mới
                </label>
                <input
                    type="file"
                    id="story_image"
                    name="story_image"
                    accept="image/jpeg,image/png,image/webp"
                    hidden
                >
                <div class="story-upload-note">
                    JPG, PNG hoặc WEBP ·
                    Khuyên dùng ảnh tỷ lệ 4:5 ·
                    Tối đa 10MB
                </div>
            </div>
            <div
                id="storyPreview"
                class="story-preview"
            ></div>
        </section>
                <!-- =================================================
             9. NHẠC CƯỚI
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">
                    9
                </div>

                <h2>
                    Nhạc cưới
                </h2>

            </div>


            <div class="form-group">

                <label>
                    Chọn nhạc nền mới
                </label>

                <input
                    type="file"
                    name="music"
                    id="music"
                    accept=".mp3,.wav,.ogg,audio/mpeg,audio/wav,audio/ogg"
                >

                <div class="music-note">
                    Hỗ trợ MP3, WAV hoặc OGG.
                    Tối đa 20MB.
                    Nếu không chọn nhạc mới,
                    nhạc hiện tại sẽ được giữ nguyên.
                </div>

            </div>


            <?php if (!empty($music)): ?>

                <div class="current-music">

                    <strong>
                        🎵 Nhạc hiện tại
                    </strong>

                    <audio
                        controls
                        src="<?= e($music) ?>"
                    ></audio>

                </div>

            <?php endif; ?>


            <div
                class="music-preview"
                id="musicPreview"
                style="display:none; margin-top:15px;"
            >

                <strong>
                    🎵 Nghe thử nhạc mới
                </strong>

                <audio
                    id="musicPlayer"
                    controls
                ></audio>

            </div>

        </section>


        <!-- =================================================
             10. HỘP QUÀ MỪNG
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">
                    10
                </div>

                <h2>
                    Hộp quà mừng
                </h2>

            </div>


            <p
                style="
                    color:#777;
                    line-height:1.6;
                    margin-top:0;
                "
            >
                Chọn cách hiển thị phần
                Hộp Quà Mừng trên thiệp cưới.
            </p>


            <!-- =================================================
                 CHỌN CHẾ ĐỘ
            ================================================== -->

            <div class="gift-mode-grid">


                <!-- BANK -->

                <div class="gift-mode-option">

                    <input
                        type="radio"
                        name="gift_mode"
                        id="gift_mode_bank"
                        value="bank"
                        <?= $giftMode === 'bank'
                            ? 'checked'
                            : '' ?>
                    >

                    <label
                        class="gift-mode-label"
                        for="gift_mode_bank"
                    >

                        <div class="gift-mode-icon">
                            💳
                        </div>

                        <h3>
                            Chuyển khoản ngân hàng
                        </h3>

                        <p>
                            Hiển thị thông tin tài khoản
                            và mã QR của chú rể và cô dâu.
                        </p>

                    </label>

                </div>


                <!-- MESSAGE -->

                <div class="gift-mode-option">

                    <input
                        type="radio"
                        name="gift_mode"
                        id="gift_mode_message"
                        value="message"
                        <?= $giftMode === 'message'
                            ? 'checked'
                            : '' ?>
                    >

                    <label
                        class="gift-mode-label"
                        for="gift_mode_message"
                    >

                        <div class="gift-mode-icon">
                            💌
                        </div>

                        <h3>
                            Lời nhắn
                        </h3>

                        <p>
                            Không hiển thị tài khoản ngân hàng.
                            Chỉ hiển thị lời nhắn.
                        </p>

                    </label>

                </div>

            </div>


            <!-- =================================================
                 BANK PANEL
            ================================================== -->

            <div
                id="bankGiftFields"
                class="gift-panel"
                style="<?= $giftMode === 'bank'
                    ? ''
                    : 'display:none;' ?>"
            >

                <h3 class="gift-panel-title">
                    Thông tin tài khoản nhận quà
                </h3>


                <!-- CHÚ RỂ -->

                <div class="gift-person">

                    <h4>
                        💙 Tài khoản chú rể
                    </h4>


                    <div class="form-group">

                        <label>
                            Tên hiển thị
                        </label>

                        <input
                            type="text"
                            name="groom_gift_name"
                            value="<?= e(
                                $groomGiftName
                            ) ?>"
                            placeholder="Tên chú rể"
                        >

                    </div>


                    <div class="grid-3">

                        <div class="form-group">

                            <label>
                                Ngân hàng
                            </label>

                            <input
                                type="text"
                                name="groom_bank_name"
                                value="<?= e(
                                    $groomBankName
                                ) ?>"
                                placeholder="Ví dụ: Vietcombank"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Số tài khoản
                            </label>

                            <input
                                type="text"
                                name="groom_account_number"
                                value="<?= e(
                                    $groomAccountNumber
                                ) ?>"
                                placeholder="Số tài khoản"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Tên tài khoản
                            </label>

                            <input
                                type="text"
                                name="groom_account_name"
                                value="<?= e(
                                    $groomAccountName
                                ) ?>"
                                placeholder="NGUYEN VAN NAM"
                            >

                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Ảnh QR chú rể
                        </label>

                        <input
                            type="file"
                            name="groom_qr_image"
                            id="groom_qr_image"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <div class="qr-note">
                            JPG, PNG hoặc WEBP.
                            Tối đa 5MB.
                            Nếu không chọn ảnh mới,
                            QR hiện tại sẽ được giữ nguyên.
                        </div>


                        <?php if (!empty($groomQrImage)): ?>

                            <div
                                class="current-image"
                                style="text-align:left;"
                            >

                                <div class="current-image-title">
                                    QR chú rể hiện tại
                                </div>

                                <img
                                    src="<?= e($groomQrImage) ?>"
                                    alt="QR chú rể"
                                    style="
                                        width:180px;
                                        aspect-ratio:1;
                                        object-fit:contain;
                                        background:#fff;
                                        padding:10px;
                                        border:1px solid #eee;
                                    "
                                >

                            </div>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- CÔ DÂU -->

                <div class="gift-person">

                    <h4>
                        💗 Tài khoản cô dâu
                    </h4>


                    <div class="form-group">

                        <label>
                            Tên hiển thị
                        </label>

                        <input
                            type="text"
                            name="bride_gift_name"
                            value="<?= e(
                                $brideGiftName
                            ) ?>"
                            placeholder="Tên cô dâu"
                        >

                    </div>


                    <div class="grid-3">

                        <div class="form-group">

                            <label>
                                Ngân hàng
                            </label>

                            <input
                                type="text"
                                name="bride_bank_name"
                                value="<?= e(
                                    $brideBankName
                                ) ?>"
                                placeholder="Ví dụ: BIDV"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Số tài khoản
                            </label>

                            <input
                                type="text"
                                name="bride_account_number"
                                value="<?= e(
                                    $brideAccountNumber
                                ) ?>"
                                placeholder="Số tài khoản"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Tên tài khoản
                            </label>

                            <input
                                type="text"
                                name="bride_account_name"
                                value="<?= e(
                                    $brideAccountName
                                ) ?>"
                                placeholder="TRAN NGOC ANH"
                            >

                        </div>

                    </div>


                    <div class="form-group">

                        <label>
                            Ảnh QR cô dâu
                        </label>

                        <input
                            type="file"
                            name="bride_qr_image"
                            id="bride_qr_image"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <div class="qr-note">
                            JPG, PNG hoặc WEBP.
                            Tối đa 5MB.
                            Nếu không chọn ảnh mới,
                            QR hiện tại sẽ được giữ nguyên.
                        </div>


                        <?php if (!empty($brideQrImage)): ?>

                            <div
                                class="current-image"
                                style="text-align:left;"
                            >

                                <div class="current-image-title">
                                    QR cô dâu hiện tại
                                </div>

                                <img
                                    src="<?= e($brideQrImage) ?>"
                                    alt="QR cô dâu"
                                    style="
                                        width:180px;
                                        aspect-ratio:1;
                                        object-fit:contain;
                                        background:#fff;
                                        padding:10px;
                                        border:1px solid #eee;
                                    "
                                >

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 MESSAGE PANEL
            ================================================== -->

            <div
                id="messageGiftFields"
                class="gift-panel"
                style="<?= $giftMode === 'message'
                    ? ''
                    : 'display:none;' ?>"
            >

                <h3 class="gift-panel-title">
                    Lời nhắn trên Hộp Quà Mừng
                </h3>


                <div class="form-group">

                    <label>
                        Nội dung lời nhắn
                    </label>

                    <textarea
                        name="gift_message"
                        id="giftMessage"
                        maxlength="1000"
                        placeholder="Nhập lời nhắn..."
                    ><?= e($giftMessage) ?></textarea>

                </div>


                <div class="message-preview">

                    <strong>
                        Xem trước:
                    </strong>

                    <div
                        id="giftMessagePreview"
                        style="margin-top:8px;"
                    >
                        <?= e(
                            $giftMessage
                            ?: 'Sự hiện diện của bạn là niềm vui của gia đình chúng tôi.'
                        ) ?>
                    </div>

                </div>

            </div>

        </section>


        <!-- =================================================
             NÚT LƯU
        ================================================== -->

        <div class="submit-area">

            <button
                type="submit"
                class="submit-button"
            >
                💾 LƯU THAY ĐỔI
            </button>


            <a
                href="../../w/?slug=<?= urlencode($slug) ?>"
                target="_blank"
                class="cancel-button"
            >
                👁 Xem thiệp
            </a>


            <a
                href="index.php"
                class="cancel-button"
            >
                ← Quay lại
            </a>

        </div>


    </form>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>


/* =========================================================
   THÊM SỰ KIỆN
========================================================= */

function addEvent()
{
    const eventList =
        document.getElementById('eventList');

    if (!eventList) {
        return;
    }


    const eventBoxes =
        eventList.querySelectorAll('.event-box');

    const number =
        eventBoxes.length + 1;


    const box =
        document.createElement('div');

    box.className = 'event-box';


    box.innerHTML = `

        <div class="event-header">

            <strong>
                Sự kiện ${number}
            </strong>

            <button
                type="button"
                class="remove-event"
                onclick="removeEvent(this)"
            >
                Xóa
            </button>

        </div>


        <div class="form-group">

            <label>
                Tên sự kiện
            </label>

            <input
                type="text"
                name="event_name[]"
                placeholder="Ví dụ: Tiệc cưới"
            >

        </div>


        <div class="grid-2">

            <div class="form-group">

                <label>
                    Ngày
                </label>

                <input
                    type="date"
                    name="event_date[]"
                >

            </div>


            <div class="form-group">

                <label>
                    Giờ
                </label>

                <input
                    type="time"
                    name="event_time[]"
                >

            </div>

        </div>


        <div class="form-group">

            <label>
                Địa điểm
            </label>

            <input
                type="text"
                name="event_location[]"
                placeholder="Tên địa điểm"
            >

        </div>


        <div class="form-group">

            <label>
                Địa chỉ
            </label>

            <input
                type="text"
                name="event_address[]"
                placeholder="Địa chỉ"
            >

        </div>


        <div class="form-group">

            <label>
                Mô tả
            </label>

            <textarea
                name="event_description[]"
                placeholder="Thông tin thêm..."
            ></textarea>

        </div>

    `;


    eventList.appendChild(box);

    renumberEvents();
}


/* =========================================================
   XÓA SỰ KIỆN
========================================================= */

function removeEvent(button)
{
    const eventList =
        document.getElementById('eventList');

    if (!eventList) {
        return;
    }


    const eventBoxes =
        eventList.querySelectorAll('.event-box');


    if (eventBoxes.length <= 1) {

        alert(
            'Cần giữ lại ít nhất một sự kiện.'
        );

        return;
    }


    const box =
        button.closest('.event-box');

    if (box) {
        box.remove();
    }


    renumberEvents();
}


/* =========================================================
   ĐÁNH SỐ LẠI SỰ KIỆN
========================================================= */

function renumberEvents()
{
    const eventBoxes =
        document.querySelectorAll(
            '#eventList .event-box'
        );


    eventBoxes.forEach(
        function(box, index)
        {
            const title =
                box.querySelector(
                    '.event-header strong'
                );


            if (title) {

                title.textContent =
                    'Sự kiện ' + (index + 1);

            }

        }
    );
}


/* =========================================================
   CHUYỂN CHẾ ĐỘ HỘP QUÀ
========================================================= */

function toggleGiftMode()
{
    const bankFields =
        document.getElementById(
            'bankGiftFields'
        );

    const messageFields =
        document.getElementById(
            'messageGiftFields'
        );

    const selected =
        document.querySelector(
            'input[name="gift_mode"]:checked'
        );


    if (
        !bankFields ||
        !messageFields
    ) {
        return;
    }


    if (
        selected &&
        selected.value === 'message'
    ) {

        bankFields.style.display =
            'none';

        messageFields.style.display =
            'block';

    } else {

        bankFields.style.display =
            'block';

        messageFields.style.display =
            'block';

        /*
         * Chỉ hiển thị message nếu
         * thực sự chọn message.
         */

        if (
            selected &&
            selected.value === 'bank'
        ) {

            messageFields.style.display =
                'none';

        }

    }
}


/* =========================================================
   PREVIEW LỜI NHẮN
========================================================= */

function updateGiftMessagePreview()
{
    const textarea =
        document.getElementById(
            'giftMessage'
        );

    const preview =
        document.getElementById(
            'giftMessagePreview'
        );


    if (
        !textarea ||
        !preview
    ) {
        return;
    }


    let value =
        textarea.value.trim();


    if (value === '') {

        value =
            'Sự hiện diện của bạn là niềm vui của gia đình chúng tôi.';

    }


    preview.textContent =
        value;
}


/* =========================================================
   PREVIEW NHẠC
========================================================= */

function previewMusic()
{
    const musicInput =
        document.getElementById('music');

    const musicPreview =
        document.getElementById(
            'musicPreview'
        );

    const musicPlayer =
        document.getElementById(
            'musicPlayer'
        );


    if (
        !musicInput ||
        !musicPreview ||
        !musicPlayer
    ) {
        return;
    }


    if (
        !musicInput.files ||
        musicInput.files.length === 0
    ) {

        musicPreview.style.display =
            'none';

        musicPlayer.removeAttribute(
            'src'
        );

        musicPlayer.load();

        return;
    }


    const file =
        musicInput.files[0];


    const allowedExtensions = [
        'mp3',
        'wav',
        'ogg'
    ];


    const extension =
        file.name
            .split('.')
            .pop()
            .toLowerCase();


    if (
        !allowedExtensions.includes(
            extension
        )
    ) {

        alert(
            'Nhạc chỉ chấp nhận file MP3, WAV hoặc OGG.'
        );

        musicInput.value = '';

        musicPreview.style.display =
            'none';

        return;
    }


    if (
        file.size >
        20 * 1024 * 1024
    ) {

        alert(
            'File nhạc không được lớn hơn 20MB.'
        );

        musicInput.value = '';

        musicPreview.style.display =
            'none';

        return;
    }


    const musicUrl =
        URL.createObjectURL(file);


    musicPlayer.src =
        musicUrl;

    musicPreview.style.display =
        'block';
}


/* =========================================================
   PREVIEW ALBUM
========================================================= */

const albumInput =
    document.getElementById(
        'album_images'
    );

const albumPreview =
    document.getElementById(
        'albumPreview'
    );

const albumCount =
    document.getElementById(
        'albumCount'
    );


let selectedAlbumFiles = [];


if (albumInput) {

    albumInput.addEventListener(
        'change',
        function()
        {

            const files =
                Array.from(
                    this.files
                );


            selectedAlbumFiles =
                selectedAlbumFiles.concat(
                    files
                );


            renderAlbumPreview();

        }
    );

}


function renderAlbumPreview()
{
    if (!albumPreview) {
        return;
    }


    albumPreview.innerHTML =
        '';


    selectedAlbumFiles.forEach(
        function(file, index)
        {

            const item =
                document.createElement(
                    'div'
                );

            item.className =
                'album-preview-item';


            const img =
                document.createElement(
                    'img'
                );

            img.src =
                URL.createObjectURL(
                    file
                );

            img.alt =
                'Ảnh mới ' +
                (index + 1);


            const number =
                document.createElement(
                    'div'
                );

            number.className =
                'album-preview-number';

            number.textContent =
                'Mới #' +
                (index + 1);


            const remove =
                document.createElement(
                    'button'
                );

            remove.type =
                'button';

            remove.className =
                'album-preview-remove';

            remove.textContent =
                '×';

            remove.title =
                'Xóa ảnh này';


            remove.addEventListener(
                'click',
                function()
                {

                    selectedAlbumFiles.splice(
                        index,
                        1
                    );

                    updateAlbumInput();

                    renderAlbumPreview();

                }
            );


            item.appendChild(img);

            item.appendChild(number);

            item.appendChild(remove);

            albumPreview.appendChild(item);

        }
    );


    if (albumCount) {

        if (
            selectedAlbumFiles.length > 0
        ) {

            albumCount.textContent =
                'Đã chọn thêm ' +
                selectedAlbumFiles.length +
                ' ảnh';

        } else {

            albumCount.textContent =
                '';

        }

    }
}


function updateAlbumInput()
{
    if (!albumInput) {
        return;
    }


    const dataTransfer =
        new DataTransfer();


    selectedAlbumFiles.forEach(
        function(file)
        {
            dataTransfer.items.add(
                file
            );
        }
    );


    albumInput.files =
        dataTransfer.files;
}


/* =========================================================
   PREVIEW ẢNH CHUYỆN TÌNH
========================================================= */

const storyImageInput =
    document.getElementById(
        'story_image'
    );

const storyPreview =
    document.getElementById(
        'storyPreview'
    );


if (storyImageInput) {

    storyImageInput.addEventListener(
        'change',
        function()
        {

            if (!storyPreview) {
                return;
            }


            storyPreview.innerHTML =
                '';


            const file =
                this.files[0];


            if (!file) {
                return;
            }


            if (
                !file.type.startsWith(
                    'image/'
                )
            ) {

                alert(
                    'Vui lòng chọn một file ảnh.'
                );

                this.value =
                    '';

                return;
            }


            if (
                file.size >
                10 * 1024 * 1024
            ) {

                alert(
                    'Ảnh không được lớn hơn 10MB.'
                );

                this.value =
                    '';

                return;
            }


            const image =
                document.createElement(
                    'img'
                );


            image.src =
                URL.createObjectURL(
                    file
                );

            image.alt =
                'Ảnh Chuyện tình mới';


            storyPreview.appendChild(
                image
            );

        }
    );

}


/* =========================================================
   PREVIEW QR
========================================================= */

function setupQrPreview(
    inputId
)
{
    const input =
        document.getElementById(
            inputId
        );


    if (!input) {
        return;
    }


    input.addEventListener(
        'change',
        function()
        {

            const file =
                this.files[0];


            if (!file) {
                return;
            }


            if (
                !file.type.startsWith(
                    'image/'
                )
            ) {

                alert(
                    'QR phải là file ảnh.'
                );

                this.value =
                    '';

                return;
            }


            if (
                file.size >
                5 * 1024 * 1024
            ) {

                alert(
                    'Ảnh QR không được lớn hơn 5MB.'
                );

                this.value =
                    '';

                return;
            }

        }
    );
}


setupQrPreview(
    'groom_qr_image'
);

setupQrPreview(
    'bride_qr_image'
);


/* =========================================================
   KHI TRANG LOAD
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        /*
         * Hộp quà
         */

        document
            .querySelectorAll(
                'input[name="gift_mode"]'
            )
            .forEach(
                function(radio)
                {

                    radio.addEventListener(
                        'change',
                        toggleGiftMode
                    );

                }
            );


        toggleGiftMode();


        /*
         * Lời nhắn
         */

        const giftMessage =
            document.getElementById(
                'giftMessage'
            );


        if (giftMessage) {

            giftMessage.addEventListener(
                'input',
                updateGiftMessagePreview
            );

        }


        updateGiftMessagePreview();


        /*
         * Nhạc
         */

        const musicInput =
            document.getElementById(
                'music'
            );


        if (musicInput) {

            musicInput.addEventListener(
                'change',
                previewMusic
            );

        }

    }
);


</script>


</body>

</html>
