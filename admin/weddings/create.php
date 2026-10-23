<?php

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/auth.php";

requireAdmin();
/* =========================================================
   HÀM TẠO SLUG
========================================================= */
function createSlug($text)
{
    $text = trim($text);

    $text = mb_strtolower($text, 'UTF-8');

    $trans = [
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a',
        'â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a',
        'ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',

        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e',
        'ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',

        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',

        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o',
        'ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o',
        'ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',

        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u',
        'ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',

        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',

        'đ'=>'d'
    ];

    $text = strtr($text, $trans);

    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');

    return $text;
}


/* =========================================================
   HÀM UPLOAD QR
========================================================= */
function uploadGiftQr($fieldName, $weddingId)
{
    if (
        !isset($_FILES[$fieldName]) ||
        $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return '';
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Không thể upload file QR.");
    }

    $file = $_FILES[$fieldName];

    /* Giới hạn 5MB */
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("Ảnh QR không được lớn hơn 5MB.");
    }

    /* Kiểm tra đây có thực sự là ảnh không */
    $imageInfo = @getimagesize($file['tmp_name']);

    if ($imageInfo === false) {
        throw new Exception("File QR không phải là hình ảnh hợp lệ.");
    }

    $mime = $imageInfo['mime'];

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowedTypes[$mime])) {
        throw new Exception(
            "Ảnh QR chỉ chấp nhận JPG, PNG hoặc WEBP."
        );
    }

    /* Thư mục lưu QR */
    $uploadDir = __DIR__ . "/../../uploads/gifts/";

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception(
                "Không thể tạo thư mục lưu ảnh QR."
            );
        }
    }

    /* Tạo tên file */
    $extension = $allowedTypes[$mime];

    $fileName =
        'gift_' .
        $weddingId .
        '_' .
        uniqid() .
        '.' .
        $extension;

    $destination = $uploadDir . $fileName;

    if (!move_uploaded_file(
        $file['tmp_name'],
        $destination
    )) {
        throw new Exception(
            "Không thể lưu ảnh QR lên máy chủ."
        );
    }

    /*
        Đường dẫn được lưu vào database.
        Đây là đường dẫn website, không phải đường dẫn Windows.
    */
    return "/WeddingProject/uploads/gifts/" . $fileName;
}


/* =========================================================
   HÀM UPLOAD NHẠC CƯỚI
========================================================= */
function uploadMusic($fieldName, $weddingId)
{
    if (
        !isset($_FILES[$fieldName]) ||
        $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return '';
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Không thể upload file nhạc.");
    }

    $file = $_FILES[$fieldName];

    /* Giới hạn 20MB */
    if ($file['size'] > 20 * 1024 * 1024) {
        throw new Exception("File nhạc không được lớn hơn 20MB.");
    }

    /* Kiểm tra phần mở rộng */
    $extension = strtolower(
        pathinfo($file['name'], PATHINFO_EXTENSION)
    );

    $allowedExtensions = [
        'mp3',
        'wav',
        'ogg'
    ];

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new Exception(
            "Nhạc chỉ chấp nhận file MP3, WAV hoặc OGG."
        );
    }

    /* Thư mục lưu nhạc */
    $uploadDir = __DIR__ . "/../../uploads/music/";

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception(
                "Không thể tạo thư mục lưu nhạc."
            );
        }
    }

    /* Tạo tên file mới */
    $fileName =
        'music_' .
        $weddingId .
        '_' .
        uniqid() .
        '.' .
        $extension;

    $destination = $uploadDir . $fileName;

    if (!move_uploaded_file(
        $file['tmp_name'],
        $destination
    )) {
        throw new Exception(
            "Không thể lưu file nhạc lên máy chủ."
        );
    }

    /*
        Lưu đường dẫn website vào database,
        không lưu đường dẫn Windows.
    */
    return "/WeddingProject/uploads/music/" . $fileName;
}


/* =========================================================
   LẤY DANH SÁCH TEMPLATE
========================================================= */

$templateStmt = $pdo->query("
    SELECT *
    FROM templates
    WHERE status = 1
    ORDER BY id ASC
");

$templates = $templateStmt->fetchAll();


/* =========================================================
   GIÁ TRỊ MẶC ĐỊNH
========================================================= */

$groomName = trim($_POST['groom_name'] ?? '');
$brideName = trim($_POST['bride_name'] ?? '');

$weddingDate = $_POST['wedding_date'] ?? '';
$weddingTime = $_POST['wedding_time'] ?? '';

$venue = trim($_POST['venue'] ?? '');
$address = trim($_POST['address'] ?? '');

$story = trim($_POST['story'] ?? '');

$templateId = (int)($_POST['template_id'] ?? 1);

/* Đường dẫn nhạc */
$music = '';


/* =========================================================
   THÔNG TIN BỐ MẸ
========================================================= */

$groomFather = trim($_POST['groom_father'] ?? '');
$groomMother = trim($_POST['groom_mother'] ?? '');

$brideFather = trim($_POST['bride_father'] ?? '');
$brideMother = trim($_POST['bride_mother'] ?? '');


/* =========================================================
   THÔNG TIN HỘP QUÀ
========================================================= */

$giftMode = $_POST['gift_mode'] ?? 'bank';

if (!in_array($giftMode, ['bank', 'message'], true)) {
    $giftMode = 'bank';
}


/* Thông tin tài khoản chú rể */

$groomGiftName = trim(
    $_POST['groom_gift_name'] ?? $groomName
);

$groomBankName = trim(
    $_POST['groom_bank_name'] ?? ''
);

$groomAccountNumber = trim(
    $_POST['groom_account_number'] ?? ''
);

$groomAccountName = trim(
    $_POST['groom_account_name'] ?? ''
);


/* Thông tin tài khoản cô dâu */

$brideGiftName = trim(
    $_POST['bride_gift_name'] ?? $brideName
);

$brideBankName = trim(
    $_POST['bride_bank_name'] ?? ''
);

$brideAccountNumber = trim(
    $_POST['bride_account_number'] ?? ''
);

$brideAccountName = trim(
    $_POST['bride_account_name'] ?? ''
);


/* Lời nhắn */

$defaultGiftMessage =
    "Sự hiện diện của bạn là niềm vui của gia đình chúng tôi.";

$giftMessage = trim(
    $_POST['gift_message'] ?? $defaultGiftMessage
);

if ($giftMessage === '') {
    $giftMessage = $defaultGiftMessage;
}


/* =========================================================
   XỬ LÝ FORM
========================================================= */

$error = '';
$success = '';
$createdWeddingUrl = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /* =====================================================
           VALIDATE CƠ BẢN
        ===================================================== */

        if ($templateId <= 0) {
            throw new Exception(
                "Vui lòng chọn mẫu thiệp."
            );
        }

        if ($groomName === '') {
            throw new Exception(
                "Vui lòng nhập tên chú rể."
            );
        }

        if ($brideName === '') {
            throw new Exception(
                "Vui lòng nhập tên cô dâu."
            );
        }

        if ($weddingDate === '') {
            throw new Exception(
                "Vui lòng chọn ngày cưới."
            );
        }


        /* =====================================================
           VALIDATE HỘP QUÀ
        ===================================================== */

        if ($giftMode === 'bank') {

            if (
                $groomBankName === '' ||
                $groomAccountNumber === '' ||
                $groomAccountName === ''
            ) {
                throw new Exception(
                    "Vui lòng nhập đầy đủ thông tin tài khoản của chú rể."
                );
            }

            if (
                $brideBankName === '' ||
                $brideAccountNumber === '' ||
                $brideAccountName === ''
            ) {
                throw new Exception(
                    "Vui lòng nhập đầy đủ thông tin tài khoản của cô dâu."
                );
            }
        }


        /* =====================================================
           TẠO SLUG
        ===================================================== */

        $baseSlug = createSlug(
            $groomName . '-' . $brideName
        );

        if ($baseSlug === '') {
            $baseSlug = 'wedding';
        }

        $slug = $baseSlug;

        $counter = 1;

        while (true) {

            $checkSlug = $pdo->prepare("
                SELECT id
                FROM weddings
                WHERE slug = :slug
                LIMIT 1
            ");

            $checkSlug->execute([
                'slug' => $slug
            ]);

            if (!$checkSlug->fetch()) {
                break;
            }

            $counter++;

            $slug = $baseSlug . '-' . $counter;
        }

/* =====================================================
   TẠO MÃ CHỈNH SỬA
===================================================== */

$editToken = strtoupper(
    substr(
        bin2hex(random_bytes(6)),
        0,
        8
    )
);
        /* =====================================================
           BẮT ĐẦU TRANSACTION
        ===================================================== */

        $pdo->beginTransaction();


        /* =====================================================
           TẠO WEDDING
        ===================================================== */

        $insertWedding = $pdo->prepare("
            INSERT INTO weddings (
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
        story_image,
        music,
        status
)
            VALUES (
     :template_id,
    :slug,
    :edit_token,
    :groom_name,
    :bride_name,
    :wedding_date,
    :wedding_time,
    :venue,
    :address,
    :story,
    '',
    '',
    '',
    0
)
        ");

        $insertWedding->execute([
            'template_id'  => $templateId,
    'slug'         => $slug,
    'edit_token'   => $editToken,
    'groom_name'   => $groomName,
    'bride_name'   => $brideName,
    'wedding_date' => $weddingDate,
    'wedding_time' => $weddingTime,
    'venue'        => $venue,
    'address'      => $address,
    'story'        => $story
        ]);

        $weddingId = $pdo->lastInsertId();
        /* =====================================================
   TẠO MÃ CHỈNH SỬA CHO KHÁCH
===================================================== */

$editToken = bin2hex(random_bytes(16));

$updateEditToken = $pdo->prepare("
    UPDATE weddings
    SET edit_token = :edit_token
    WHERE id = :id
");

$updateEditToken->execute([
    'edit_token' => $editToken,
    'id' => $weddingId
]);
        /* =================================================
   UPLOAD ẢNH CHUYỆN TÌNH
================================================= */

if (
    isset($_FILES['story_image']) &&
    $_FILES['story_image']['error'] === UPLOAD_ERR_OK
) {

    $file = $_FILES['story_image'];

    $imageInfo = @getimagesize($file['tmp_name']);

if ($imageInfo === false) {
    throw new Exception(
        "File Chuyện tình không phải là hình ảnh hợp lệ."
    );
}

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
];

$mime = $imageInfo['mime'];

if (!isset($allowedTypes[$mime])) {
    throw new Exception(
        "Ảnh Chuyện tình chỉ hỗ trợ JPG, PNG hoặc WEBP."
    );
}

$extension = $allowedTypes[$mime];

    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception(
            "Ảnh Chuyện tình không được lớn hơn 10MB."
        );
    }

    $uploadDir = __DIR__
        . '/../../uploads/weddings/'
        . $weddingId
        . '/';

    if (!is_dir($uploadDir)) {

    if (!mkdir($uploadDir, 0777, true)) {

        throw new Exception(
            "Không thể tạo thư mục lưu ảnh Chuyện tình."
        );

    }
}

    $fileName =
    'story_' .
    $weddingId .
    '_' .
    uniqid() .
    '.' .
    $extension;

    $destination = $uploadDir . $fileName;

    if (!move_uploaded_file(
        $file['tmp_name'],
        $destination
    )) {
        throw new Exception(
            "Không thể lưu ảnh Chuyện tình lên máy chủ."
        );
    }

    $storyImagePath =
        '/WeddingProject/uploads/weddings/'
        . $weddingId
        . '/'
        . $fileName;

    $updateStoryImage = $pdo->prepare("
        UPDATE weddings
        SET story_image = :story_image
        WHERE id = :id
    ");

    $updateStoryImage->execute([
        'story_image' => $storyImagePath,
        'id' => $weddingId
    ]);
}

/* =====================================================
   LƯU ALBUM ẢNH CƯỚI
===================================================== */

if (
    isset($_FILES['album_images']) &&
    isset($_FILES['album_images']['name']) &&
    is_array($_FILES['album_images']['name'])
) {

    $uploadDir =
        __DIR__ .
        "/../../uploads/weddings/" .
        $weddingId .
        "/";


    /*
     * Tạo thư mục riêng cho từng thiệp
     */

    if (!is_dir($uploadDir)) {

        if (!mkdir($uploadDir, 0777, true)) {

            throw new Exception(
                "Không thể tạo thư mục lưu album."
            );

        }

    }


    /*
     * Định dạng ảnh cho phép
     */

    $allowedTypes = [

        'image/jpeg' => 'jpg',

        'image/png' => 'png',

        'image/webp' => 'webp'

    ];


    /*
     * Câu lệnh lưu ảnh vào database
     */

    $insertPhoto = $pdo->prepare("
        INSERT INTO wedding_photos
        (
            wedding_id,
            image_path,
            sort_order
        )
        VALUES
        (
            :wedding_id,
            :image_path,
            :sort_order
        )
    ");


    /*
     * Tổng số ảnh PHP nhận được
     */

    $imageCount =
        count(
            $_FILES['album_images']['name']
        );


    /*
     * Duyệt TẤT CẢ ảnh
     */

    for (
        $i = 0;
        $i < $imageCount;
        $i++
    ) {

        /*
         * Nếu ảnh bị lỗi thì bỏ qua
         */

        if (
            $_FILES['album_images']['error'][$i]
            !== UPLOAD_ERR_OK
        ) {

            continue;

        }


        $tmpFile =
            $_FILES['album_images']['tmp_name'][$i];


        /*
         * Kiểm tra ảnh thật
         */

        $imageInfo =
            @getimagesize($tmpFile);


        if ($imageInfo === false) {

            continue;

        }


        $mime =
            $imageInfo['mime'];


        /*
         * Kiểm tra định dạng
         */

        if (
            !isset(
                $allowedTypes[$mime]
            )
        ) {

            continue;

        }


        $extension =
            $allowedTypes[$mime];


        /*
         * Tạo tên file riêng
         */

        $fileName =
            'album_' .
            $weddingId .
            '_' .
            ($i + 1) .
            '_' .
            uniqid() .
            '.' .
            $extension;


        /*
         * Đường dẫn lưu thật trên máy tính
         */

        $destination =
            $uploadDir .
            $fileName;


        /*
         * Di chuyển ảnh
         */

        if (
            move_uploaded_file(
                $tmpFile,
                $destination
            )
        ) {

            /*
             * Đường dẫn website
             */

            $imagePath =
                "/WeddingProject/uploads/weddings/" .
                $weddingId .
                "/" .
                $fileName;


            /*
             * Lưu vào wedding_photos
             */

            $insertPhoto->execute([

                'wedding_id' =>
                    $weddingId,

                'image_path' =>
                    $imagePath,

                'sort_order' =>
                    $i + 1

            ]);

        }

    }

}
        /* =====================================================
           UPLOAD NHẠC CƯỚI
        ===================================================== */

        $music = uploadMusic(
            'music',
            $weddingId
        );


        /* =====================================================
           CẬP NHẬT ĐƯỜNG DẪN NHẠC VÀO WEDDINGS
        ===================================================== */

        if ($music !== '') {

            $updateMusic = $pdo->prepare("
                UPDATE weddings
                SET music = :music
                WHERE id = :wedding_id
            ");

            $updateMusic->execute([
                'music' => $music,
                'wedding_id' => $weddingId
            ]);
        }


        /* =====================================================
           LƯU BỐ MẸ
        ===================================================== */

        $insertParent = $pdo->prepare("
            INSERT INTO wedding_parents (
                wedding_id,
                side,
                parent_role,
                parent_name
            )
            VALUES (
                :wedding_id,
                :side,
                :parent_role,
                :parent_name
            )
        ");


        if ($groomFather !== '') {

            $insertParent->execute([
                'wedding_id' => $weddingId,
                'side' => 'groom',
                'parent_role' => 'father',
                'parent_name' => $groomFather
            ]);
        }


        if ($groomMother !== '') {

            $insertParent->execute([
                'wedding_id' => $weddingId,
                'side' => 'groom',
                'parent_role' => 'mother',
                'parent_name' => $groomMother
            ]);
        }


        if ($brideFather !== '') {

            $insertParent->execute([
                'wedding_id' => $weddingId,
                'side' => 'bride',
                'parent_role' => 'father',
                'parent_name' => $brideFather
            ]);
        }


        if ($brideMother !== '') {

            $insertParent->execute([
                'wedding_id' => $weddingId,
                'side' => 'bride',
                'parent_role' => 'mother',
                'parent_name' => $brideMother
            ]);
        }


        /* =====================================================
           LƯU SỰ KIỆN
        ===================================================== */

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
                description,
                sort_order
            )
            VALUES (
                :wedding_id,
                :event_name,
                :event_date,
                :event_time,
                :event_location,
                :event_address,
                :description,
                :sort_order
            )
        ");


        $eventCount = count($eventNames);

        for ($i = 0; $i < $eventCount; $i++) {

            $eventName = trim(
                $eventNames[$i] ?? ''
            );

            $eventDate = trim(
                $eventDates[$i] ?? ''
            );

            $eventTime = trim(
                $eventTimes[$i] ?? ''
            );

            $eventLocation = trim(
                $eventLocations[$i] ?? ''
            );

            $eventAddress = trim(
                $eventAddresses[$i] ?? ''
            );

            $eventDescription = trim(
                $eventDescriptions[$i] ?? ''
            );


            /*
             * Nếu người dùng để trống hoàn toàn một sự kiện
             * thì bỏ qua.
             */
            if (
                $eventName === '' &&
                $eventDate === '' &&
                $eventTime === '' &&
                $eventLocation === '' &&
                $eventAddress === '' &&
                $eventDescription === ''
            ) {
                continue;
            }


            $insertEvent->execute([
                'wedding_id' => $weddingId,
                'event_name' => $eventName,
                'event_date' => $eventDate,
                'event_time' => $eventTime,
                'event_location' => $eventLocation,
                'event_address' => $eventAddress,
                'description' => $eventDescription,
                'sort_order' => $i
            ]);
        }


        /* =====================================================
           LƯU HỘP QUÀ MỪNG
        ===================================================== */

        if ($giftMode === 'bank') {

            /*
             * Upload QR chú rể
             */
            $groomQrImage = uploadGiftQr(
                'groom_qr_image',
                $weddingId
            );


            /*
             * Upload QR cô dâu
             */
            $brideQrImage = uploadGiftQr(
                'bride_qr_image',
                $weddingId
            );


            /*
             * Insert tài khoản chú rể
             */
            $insertGift = $pdo->prepare("
                INSERT INTO wedding_gifts (
                    wedding_id,
                    gift_mode,
                    person_type,
                    person_name,
                    bank_name,
                    account_number,
                    account_name,
                    qr_image,
                    gift_message
                )
                VALUES (
                    :wedding_id,
                    'bank',
                    :person_type,
                    :person_name,
                    :bank_name,
                    :account_number,
                    :account_name,
                    :qr_image,
                    NULL
                )
            ");


            $insertGift->execute([
                'wedding_id' => $weddingId,
                'person_type' => 'groom',
                'person_name' => $groomGiftName,
                'bank_name' => $groomBankName,
                'account_number' => $groomAccountNumber,
                'account_name' => $groomAccountName,
                'qr_image' => $groomQrImage
            ]);


            /*
             * Insert tài khoản cô dâu
             */
            $insertGift->execute([
                'wedding_id' => $weddingId,
                'person_type' => 'bride',
                'person_name' => $brideGiftName,
                'bank_name' => $brideBankName,
                'account_number' => $brideAccountNumber,
                'account_name' => $brideAccountName,
                'qr_image' => $brideQrImage
            ]);
        }
        /* =====================================================
           CHẾ ĐỘ LỜI NHẮN
        ===================================================== */

        if ($giftMode === 'message') {

            /*
             * Chỉ cần một dòng vì lời nhắn dành cho cả hai.
             */
            $insertGiftMessage = $pdo->prepare("
                INSERT INTO wedding_gifts (
                    wedding_id,
                    gift_mode,
                    person_type,
                    person_name,
                    bank_name,
                    account_number,
                    account_name,
                    qr_image,
                    gift_message
                )
                VALUES (
                    :wedding_id,
                    'message',
                    'couple',
                    :person_name,
                    '',
                    '',
                    '',
                    '',
                    :gift_message
                )
            ");

            $insertGiftMessage->execute([
                'wedding_id' => $weddingId,
                'person_name' => $groomName . ' & ' . $brideName,
                'gift_message' => $giftMessage
            ]);
        }


        /* =====================================================
           HOÀN TẤT
        ===================================================== */

        $pdo->commit();


        $success =
            "Tạo thiệp cưới thành công!";


        $createdWeddingUrl =
            "/WeddingProject/w/?slug=" .
            urlencode($slug);

    } catch (Exception $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error = $e->getMessage();
    }
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

    <title>Tạo thiệp cưới</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .page {
            width: min(1100px, 94%);
            margin: 40px auto 80px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            font-size: 30px;
            color: #8b1e2d;
        }

        .page-header p {
            margin: 0;
            color: #777;
        }

        .card {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 22px;
            box-shadow:
                0 5px 25px rgba(0,0,0,.06);
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
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

    font-size: 16px;
    font-weight: 700;
}


.card-title h2 {
    margin: 0;
    font-size: 21px;
    color: #8b1e2d;
}

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        input,
        textarea,
        select {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 15px;
            outline: none;
            background: #fff;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #a63d4d;
            box-shadow:
                0 0 0 3px rgba(166,61,77,.08);
        }

        textarea {
            min-height: 130px;
            resize: vertical;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .grid-3 {
            display: grid;
            grid-template-columns:
                repeat(3, 1fr);
            gap: 18px;
        }


        /* ===============================
           TEMPLATE
        =============================== */

        .template-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .template-option {
            position: relative;
        }

        .template-option input {
            position: absolute;
            opacity: 0;
        }

        .template-label {
            display: block;
            border: 2px solid #eee;
            border-radius: 14px;
            padding: 12px;
            cursor: pointer;
            transition: .2s;
        }

        .template-label:hover {
            border-color: #c98993;
        }

        .template-option input:checked
        + .template-label {
            border-color: #8b1e2d;
            box-shadow:
                0 0 0 3px
                rgba(139,30,45,.08);
        }

        .template-preview {
            height: 70px;
            border-radius: 10px;
            overflow: hidden;
            background: #eee;
            margin-bottom: 10px;
        }

        .template-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .template-name {
            font-weight: bold;
            text-align: center;
        }


        /* ===============================
           EVENTS
        =============================== */

        .event-box {
            border: 1px solid #e5e5e5;
            border-radius: 13px;
            padding: 18px;
            margin-bottom: 15px;
            background: #fafafa;
        }

        .event-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .event-header strong {
            color: #8b1e2d;
        }

        .remove-event {
            border: 0;
            background: #eee;
            color: #a00020;
            padding: 7px 12px;
            border-radius: 8px;
            cursor: pointer;
        }

        .remove-event:hover {
            background: #f5dfe3;
        }

        .add-event {
            border: 1px dashed #8b1e2d;
            background: #fff;
            color: #8b1e2d;
            padding: 11px 18px;
            border-radius: 9px;
            cursor: pointer;
            font-weight: 600;
        }


        /* ===============================
           MUSIC
        =============================== */

        .music-note {
            color: #777;
            font-size: 13px;
            line-height: 1.6;
            margin-top: 7px;
        }

        .music-preview {
            margin-top: 15px;
            padding: 15px;
            border: 1px solid #eadde0;
            background: #fffafa;
            border-radius: 12px;
        }

        .music-preview strong {
            display: block;
            color: #8b1e2d;
            margin-bottom: 10px;
        }

        .music-preview audio {
            width: 100%;
        }


        /* ===============================
           GIFT MODE
        =============================== */

        .gift-mode-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 25px;
        }

        .gift-mode-option {
            position: relative;
        }

        .gift-mode-option input {
            position: absolute;
            opacity: 0;
        }

        .gift-mode-label {
            display: block;
            border: 2px solid #e5e5e5;
            border-radius: 14px;
            padding: 20px;
            cursor: pointer;
            transition: .2s;
            height: 100%;
        }

        .gift-mode-label:hover {
            border-color: #c98993;
        }

        .gift-mode-option input:checked
        + .gift-mode-label {
            border-color: #8b1e2d;
            background: #fff9fa;
            box-shadow:
                0 0 0 3px
                rgba(139,30,45,.08);
        }

        .gift-mode-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .gift-mode-label h3 {
            margin: 0 0 7px;
            color: #8b1e2d;
        }

        .gift-mode-label p {
            margin: 0;
            color: #777;
            line-height: 1.5;
            font-size: 14px;
        }

        .gift-panel {
            border: 1px solid #eadde0;
            background: #fffafa;
            border-radius: 14px;
            padding: 20px;
            margin-top: 15px;
        }

        .gift-panel-title {
            margin: 0 0 18px;
            color: #8b1e2d;
            font-size: 18px;
        }

        .gift-person {
            background: white;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 18px;
        }

        .gift-person:last-child {
            margin-bottom: 0;
        }

        .gift-person h4 {
            margin: 0 0 18px;
            color: #8b1e2d;
            font-size: 17px;
        }

        .qr-note {
            color: #777;
            font-size: 13px;
            margin-top: 7px;
        }

        .message-preview {
            margin-top: 15px;
            background: #fff;
            border-left: 4px solid #8b1e2d;
            padding: 15px;
            border-radius: 8px;
            color: #555;
            line-height: 1.6;
        }


        /* ===============================
           BUTTON
        =============================== */

        .submit-area {
            text-align: center;
            margin-top: 30px;
        }

        .submit-button {
            border: 0;
            background: #8b1e2d;
            color: white;
            padding: 15px 35px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .submit-button:hover {
            background: #701624;
        }


        /* ===============================
           ALERT
        =============================== */

        .alert {
            border-radius: 12px;
            padding: 15px 18px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fff0f1;
            border: 1px solid #f1c4ca;
            color: #a00020;
        }

        .alert-success {
            background: #eefaf1;
            border: 1px solid #bfe5c8;
            color: #23733a;
        }

        .success-link {
            display: inline-block;
            margin-top: 10px;
            color: #23733a;
            font-weight: bold;
        }


        /* ===============================
           RESPONSIVE
        =============================== */

        @media (max-width: 700px) {

            .page {
                width: 94%;
                margin-top: 20px;
            }

            .card {
                padding: 18px;
            }

            .grid-2,
            .grid-3,
            .gift-mode-grid {
                grid-template-columns: 1fr;
            }

            .page-header h1 {
                font-size: 25px;
            }

            .template-preview {
                height: 220px;
            }
        }
        /* =========================================================
   ALBUM ẢNH CƯỚI
========================================================= */

.album-upload-box {
    border: 2px dashed #d8a3ab;
    background: #fffafa;
    border-radius: 18px;
    padding: 35px 20px;
    text-align: center;
    transition: 0.25s;
}

.album-upload-box:hover {
    border-color: #8b1e2d;
    background: #fff7f8;
}

.album-upload-icon {
    font-size: 45px;
    margin-bottom: 12px;
}

.album-upload-box h3 {
    margin: 0 0 8px;
    color: #8b1e2d;
    font-size: 20px;
}

.album-upload-box p {
    margin: 0 0 20px;
    color: #777;
}

.album-select-button {
    display: inline-block;
    background: #8b1e2d;
    color: white;
    padding: 12px 24px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: bold;
    transition: 0.2s;
}

.album-select-button:hover {
    background: #701624;
    transform: translateY(-1px);
}

.album-upload-note {
    margin-top: 12px;
    color: #999;
    font-size: 13px;
}
/* =========================================================
   KÍCH THƯỚC ẢNH PREVIEW ALBUM
========================================================= */

.album-preview {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-top: 25px;
}

.album-preview-item {
    position: relative;
    width: 100%;
    height: 190px;
    border-radius: 14px;
    overflow: hidden;
    background: #eee;
    box-shadow: 0 5px 18px rgba(0, 0, 0, 0.08);
}

.album-preview-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

@media (max-width: 800px) {

    .album-preview {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 500px) {

    .album-preview {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .album-preview-item {
        height: 160px;
    }

}
/* =================================================
   ẢNH CHUYỆN TÌNH
================================================= */

.story-upload-box {
    border: 2px dashed #d8a3ab;
    background: #fffafa;
    border-radius: 18px;
    padding: 35px 20px;
    text-align: center;
    transition: 0.25s;
}

.story-upload-box:hover {
    border-color: #8b1e2d;
    background: #fff7f8;
}

.story-upload-icon {
    font-size: 45px;
    margin-bottom: 12px;
}

.story-upload-box h3 {
    margin: 0 0 8px;
    color: #8b1e2d;
    font-size: 20px;
}

.story-upload-box p {
    margin: 0 0 20px;
    color: #777;
}

.story-select-button {
    display: inline-block;
    background: #8b1e2d;
    color: white;
    padding: 12px 24px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: bold;
    transition: 0.2s;
}

.story-select-button:hover {
    background: #701624;
    transform: translateY(-1px);
}

.story-upload-note {
    margin-top: 12px;
    color: #999;
    font-size: 13px;
}


/* Preview ảnh */

.story-preview {
    margin-top: 25px;
    display: flex;
    justify-content: center;
}

.story-preview img {
    width: 280px;
    aspect-ratio: 4 / 5;
    object-fit: cover;
    object-position: center;
    display: block;

    border-radius: 16px;

    box-shadow:
        0 10px 30px
        rgba(0, 0, 0, .12);
}


@media (max-width: 600px) {

    .story-preview img {
        width: 220px;
    }

}
    </style>

</head>


<body>

<div class="page">

    <div class="page-header">

        <h1>Tạo thiệp cưới</h1>

        <p>
            Nhập thông tin để tạo một thiệp cưới mới.
        </p>

    </div>


    <?php if ($error): ?>

        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <?php if ($success): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars($success) ?>

            <?php if ($createdWeddingUrl): ?>

                <br>

                <a
                    class="success-link"
                    href="<?= htmlspecialchars($createdWeddingUrl) ?>"
                    target="_blank"
                >
                    Mở thiệp cưới
                </a>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        enctype="multipart/form-data"
    >


        <!-- =================================================
             1. CHỌN TEMPLATE
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">1</div>

                <h2>Chọn mẫu thiệp</h2>

            </div>


            <div class="template-grid">

                <?php foreach ($templates as $template): ?>

                    <div class="template-option">

                        <input
                            type="radio"
                            name="template_id"
                            id="template_<?= (int)$template['id'] ?>"
                            value="<?= (int)$template['id'] ?>"
                            <?= (
                                (int)$templateId ===
                                (int)$template['id']
                            ) ? 'checked' : '' ?>
                        >

                        <label
                            class="template-label"
                            for="template_<?= (int)$template['id'] ?>"
                        >

                            <div class="template-preview">

                                <?php if (!empty($template['preview_image'])): ?>

                                    <img
                                        src="<?= htmlspecialchars(
                                            $template['preview_image']
                                        ) ?>"
                                        alt=""
                                    >

                                <?php else: ?>

                                    <div style="
                                        width:100%;
                                        height:100%;
                                        display:flex;
                                        align-items:center;
                                        justify-content:center;
                                        color:#999;
                                    ">
                                        Chưa có ảnh preview
                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="template-name">

                                <?= htmlspecialchars(
                                    $template['name']
                                ) ?>

                            </div>

                        </label>

                    </div>

                <?php endforeach; ?>

            </div>

        </section>


        <!-- =================================================
             2. CÔ DÂU CHÚ RỂ
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">2</div>

                <h2>Thông tin cô dâu & chú rể</h2>

            </div>


            <div class="grid-2">

                <div class="form-group">

                    <label>
                        Tên chú rể *
                    </label>

                    <input
                        type="text"
                        name="groom_name"
                        value="<?= htmlspecialchars($groomName) ?>"
                        placeholder="Ví dụ: Nguyễn Văn Nam"
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
                        value="<?= htmlspecialchars($brideName) ?>"
                        placeholder="Ví dụ: Trần Ngọc Anh"
                        required
                    >

                </div>

            </div>

        </section>


        <!-- =================================================
             3. GIA ĐÌNH
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">3</div>

                <h2>Thông tin gia đình</h2>

            </div>


            <h3 style="color:#8b1e2d;">
                Gia đình chú rể
            </h3>

            <div class="grid-2">

                <div class="form-group">

                    <label>
                        Bố chú rể
                    </label>

                    <input
                        type="text"
                        name="groom_father"
                        value="<?= htmlspecialchars($groomFather) ?>"
                        placeholder="Họ tên bố"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Mẹ chú rể
                    </label>

                    <input
                        type="text"
                        name="groom_mother"
                        value="<?= htmlspecialchars($groomMother) ?>"
                        placeholder="Họ tên mẹ"
                    >

                </div>

            </div>


            <h3 style="color:#8b1e2d; margin-top:25px;">
                Gia đình cô dâu
            </h3>


            <div class="grid-2">

                <div class="form-group">

                    <label>
                        Bố cô dâu
                    </label>

                    <input
                        type="text"
                        name="bride_father"
                        value="<?= htmlspecialchars($brideFather) ?>"
                        placeholder="Họ tên bố"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Mẹ cô dâu
                    </label>

                    <input
                        type="text"
                        name="bride_mother"
                        value="<?= htmlspecialchars($brideMother) ?>"
                        placeholder="Họ tên mẹ"
                    >

                </div>

            </div>

        </section>


        <!-- =================================================
             4. SỰ KIỆN
        ================================================== -->

        <section class="card">

            <div class="card-title">

                <div class="number">4</div>

                <h2>Các sự kiện trong ngày cưới</h2>

            </div>


            <div id="eventList">


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
                            placeholder="Ví dụ: Lễ thành hôn"
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
                            placeholder="Ví dụ: Nhà hàng ABC"
                        >

                    </div>


                    <div class="form-group">

                        <label>
                            Địa chỉ
                        </label>

                        <input
                            type="text"
                            name="event_address[]"
                            placeholder="Địa chỉ tổ chức"
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

            </div>


            <button
                type="button"
                class="add-event"
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
                <div class="number">5</div>
                <h2>Thông tin đám cưới</h2>
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>
                        Ngày cưới *
                    </label>
                    <input
                        type="date"
                        name="wedding_date"
                        value="<?= htmlspecialchars($weddingDate) ?>"
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
                        value="<?= htmlspecialchars($weddingTime) ?>"
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
                    value="<?= htmlspecialchars($venue) ?>"
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
                    value="<?= htmlspecialchars($address) ?>"
                    placeholder="Địa chỉ tổ chức"
                >
            </div>
        </section>
        <!-- =================================================
             6. CÂU CHUYỆN
        ================================================== -->
        <section class="card">
            <div class="card-title">
                <div class="number">6</div>
                <h2>Câu chuyện tình yêu</h2>
            </div>
            <div class="form-group">
                <label>
                    Nội dung câu chuyện
                </label>
                <textarea
                    name="story"
                    placeholder="Viết câu chuyện của hai bạn..."
                ><?= htmlspecialchars($story) ?></textarea>
            </div>
        </section>
<!-- =================================================
     7. ALBUM ẢNH CƯỚI
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
    <div class="album-upload-box">
        <div class="album-upload-icon">
            📷
        </div>
        <h3>
            Thêm ảnh vào album
        </h3>
        <p>
            Cậu có thể chọn nhiều ảnh cưới cùng lúc.
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
<!-- =================================================
     KHU VỰC HIỂN THỊ ẢNH ĐÃ CHỌN
================================================= -->
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
     8. ẢNH CHUYỆN TÌNH CỦA CHÚNG MÌNH
================================================== -->
<section
    class="card"
    style="display:block !important; visibility:visible !important; opacity:1 !important; height:auto !important; overflow:visible !important;"
>
    <div class="card-title">
        <div class="number">8</div>
        <h2>Ảnh Chuyện tình của chúng mình</h2>
    </div>
    <div class="story-upload-box">
        <div class="story-upload-icon">💑</div>
        <h3>Chọn ảnh cho câu chuyện tình yêu</h3>
        <p>
            Đây là ảnh sẽ xuất hiện trong phần
            “Chuyện tình của chúng mình”.
        </p>
        <label
            for="story_image"
            class="story-select-button"
        >
            + Chọn ảnh
        </label>
        <input
            type="file"
            id="story_image"
            name="story_image"
            accept="image/jpeg,image/png,image/webp"
            hidden
        >
        <div class="story-upload-note">
            JPG, PNG hoặc WEBP · Khuyên dùng ảnh tỷ lệ 4:5
            · Tối đa 10MB
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
                <div class="number">9</div>
                <h2>Nhạc cưới</h2>
            </div>
            <div class="form-group">
                <label>
                    Chọn nhạc nền cho thiệp
                </label>

                <input
                    type="file"
                    name="music"
                    id="music"
                    accept=".mp3,.wav,.ogg,audio/mpeg,audio/wav,audio/ogg"
                >

                <div class="music-note">
                    Hỗ trợ MP3, WAV hoặc OGG. Tối đa 20MB.
                </div>

            </div>


            <div
                class="music-preview"
                id="musicPreview"
                style="display:none;"
            >

                <strong>
                    🎵 Nghe thử nhạc đã chọn
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

                <div class="number">10</div>

                <h2>Hộp quà mừng</h2>

            </div>


            <p style="
                color:#777;
                line-height:1.6;
                margin-top:0;
            ">
                Chọn cách hiển thị phần Hộp Quà Mừng
                trên thiệp cưới.
            </p>


            <!-- CHỌN CHẾ ĐỘ -->

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
                            Chỉ hiển thị lời nhắn do bạn nhập.
                        </p>

                    </label>

                </div>

            </div>


            <!-- =============================================
                 BANK PANEL
            ============================================== -->

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
                            value="<?= htmlspecialchars(
                                $groomGiftName
                            ) ?>"
                            placeholder="Tên chú rể"
                        >

                    </div>


                    <div class="grid-3">

                        <div class="form-group">

                            <label>
                                Ngân hàng *
                            </label>

                            <input
                                type="text"
                                name="groom_bank_name"
                                value="<?= htmlspecialchars(
                                    $groomBankName
                                ) ?>"
                                placeholder="Ví dụ: Vietcombank"
                            >

                        </div>
                        <div class="form-group">

                            <label>
                                Số tài khoản *
                            </label>
                            <input
                                type="text"
                                name="groom_account_number"
                                value="<?= htmlspecialchars(
                                    $groomAccountNumber
                                ) ?>"
                                placeholder="Số tài khoản"
                            >

                        </div>
                        <div class="form-group">

                            <label>
                                Tên tài khoản *
                            </label>

                            <input
                                type="text"
                                name="groom_account_name"
                                value="<?= htmlspecialchars(
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
    accept="image/jpeg,image/png,image/webp"
>

                        <div class="qr-note">
                            JPG, PNG hoặc WEBP. Tối đa 5MB.
                        </div>

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
                            value="<?= htmlspecialchars(
                                $brideGiftName
                            ) ?>"
                            placeholder="Tên cô dâu"
                        >

                    </div>


                    <div class="grid-3">

                        <div class="form-group">

                            <label>
                                Ngân hàng *
                            </label>

                            <input
                                type="text"
                                name="bride_bank_name"
                                value="<?= htmlspecialchars(
                                    $brideBankName
                                ) ?>"
                                placeholder="Ví dụ: BIDV"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Số tài khoản *
                            </label>

                            <input
                                type="text"
                                name="bride_account_number"
                                value="<?= htmlspecialchars(
                                    $brideAccountNumber
                                ) ?>"
                                placeholder="Số tài khoản"
                            >

                        </div>


                        <div class="form-group">

                            <label>
                                Tên tài khoản *
                            </label>

                            <input
                                type="text"
                                name="bride_account_name"
                                value="<?= htmlspecialchars(
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
    accept="image/jpeg,image/png,image/webp"
>

                        <div class="qr-note">
                            JPG, PNG hoặc WEBP. Tối đa 5MB.
                        </div>

                    </div>

                </div>

            </div>


            <!-- =============================================
                 MESSAGE PANEL
            ============================================== -->

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
                    ><?= htmlspecialchars(
                        $giftMessage
                    ) ?></textarea>

                </div>


                <div class="message-preview">

                    <strong>
                        Xem trước:
                    </strong>

                    <div
                        id="giftMessagePreview"
                        style="margin-top:8px;"
                    >
                        <?= htmlspecialchars(
                            $giftMessage
                        ) ?>
                    </div>

                </div>

            </div>

        </section>


        <!-- =================================================
             SUBMIT
        ================================================== -->

        <div class="submit-area">

            <button
                type="submit"
                class="submit-button"
            >
                💌 TẠO THIỆP CƯỚI
            </button>

        </div>


    </form>

</div>


<script>

/* =========================================================
   THÊM SỰ KIỆN
========================================================= */

function addEvent()
{
    const eventList =
        document.getElementById('eventList');

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

    const eventBoxes =
        eventList.querySelectorAll('.event-box');


    /*
     * Luôn giữ lại ít nhất một ô sự kiện.
     */
    if (eventBoxes.length <= 1) {

        alert(
            'Cần giữ lại ít nhất một sự kiện.'
        );

        return;
    }


    const box =
        button.closest('.event-box');

    box.remove();

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
        document.getElementById('bankGiftFields');

    const messageFields =
        document.getElementById('messageGiftFields');

    const selected =
        document.querySelector(
            'input[name="gift_mode"]:checked'
        );


    if (!bankFields || !messageFields) {
        return;
    }


    if (!selected) {
        bankFields.style.display = 'block';
        messageFields.style.display = 'none';
        return;
    }


    if (selected.value === 'message') {

        bankFields.style.display = 'none';
        messageFields.style.display = 'block';

    } else {

        bankFields.style.display = 'block';
        messageFields.style.display = 'none';

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


    if (!textarea || !preview) {
        return;
    }


    let value =
        textarea.value.trim();


    if (value === '') {

        value =
            'Sự hiện diện của bạn là niềm vui của gia đình chúng tôi.';
    }


    preview.textContent = value;
}


/* =========================================================
   PREVIEW NHẠC
========================================================= */

function previewMusic()
{
    const musicInput =
        document.getElementById('music');

    const musicPreview =
        document.getElementById('musicPreview');

    const musicPlayer =
        document.getElementById('musicPlayer');


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

        musicPreview.style.display = 'none';
        musicPlayer.removeAttribute('src');
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


    if (!allowedExtensions.includes(extension)) {

        alert(
            'Nhạc chỉ chấp nhận file MP3, WAV hoặc OGG.'
        );

        musicInput.value = '';

        musicPreview.style.display = 'none';
        musicPlayer.removeAttribute('src');
        musicPlayer.load();

        return;
    }


    if (file.size > 20 * 1024 * 1024) {

        alert(
            'File nhạc không được lớn hơn 20MB.'
        );

        musicInput.value = '';

        musicPreview.style.display = 'none';
        musicPlayer.removeAttribute('src');
        musicPlayer.load();

        return;
    }


    const musicUrl =
        URL.createObjectURL(file);


    musicPlayer.src = musicUrl;

    musicPreview.style.display = 'block';
}


/* =========================================================
   KHI TRANG LOAD
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        /* -----------------------------------------
           Chế độ hộp quà
        ----------------------------------------- */

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


        /* -----------------------------------------
           Preview lời nhắn
        ----------------------------------------- */

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


        /* -----------------------------------------
           Preview nhạc
        ----------------------------------------- */

        const musicInput =
            document.getElementById('music');


        if (musicInput) {

            musicInput.addEventListener(
                'change',
                previewMusic
            );

        }

    }
);

</script>
<script>
/* =========================================================
   ALBUM ẢNH - HIỂN THỊ PREVIEW
========================================================= */

const albumInput = document.getElementById('album_images');
const albumPreview = document.getElementById('albumPreview');
const albumCount = document.getElementById('albumCount');

let selectedAlbumFiles = [];


/* Khi chọn ảnh */
if (albumInput) {

    albumInput.addEventListener('change', function () {

        const files = Array.from(this.files);

        selectedAlbumFiles = selectedAlbumFiles.concat(files);

        renderAlbumPreview();

    });

}


/* Hiển thị ảnh */
function renderAlbumPreview() {

    if (!albumPreview) {
        return;
    }

    albumPreview.innerHTML = '';


    selectedAlbumFiles.forEach(function (file, index) {

        const item = document.createElement('div');

        item.className = 'album-preview-item';


        /* Ảnh */
        const img = document.createElement('img');

        img.src = URL.createObjectURL(file);

        img.alt = 'Ảnh cưới ' + (index + 1);
        /* Số thứ tự */
        const number = document.createElement('div');
        number.className = 'album-preview-number';
        number.textContent = '#' + (index + 1);

        /* Nút xóa */
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'album-preview-remove';
        remove.textContent = '×';
        remove.title = 'Xóa ảnh này';
        remove.addEventListener('click', function () {
            selectedAlbumFiles.splice(index, 1);
            updateAlbumInput();
            renderAlbumPreview();

        });

        item.appendChild(img);
        item.appendChild(number);

        item.appendChild(remove);

        albumPreview.appendChild(item);

    });
    /* Hiện số lượng ảnh */
    if (albumCount) {
        if (selectedAlbumFiles.length > 0) {
            albumCount.textContent =
                'Đã chọn ' +
                selectedAlbumFiles.length +
                ' ảnh';
        } else {

            albumCount.textContent = '';
        }
    }
}
/* Cập nhật lại input file */
function updateAlbumInput() {

    if (!albumInput) {
        return;
    }
    const dataTransfer = new DataTransfer();
    selectedAlbumFiles.forEach(function (file) {
        dataTransfer.items.add(file);
    });

albumInput.files = dataTransfer.files;

}
</script>
<script>
const storyImageInput = document.getElementById('story_image');
const storyPreview = document.getElementById('storyPreview');

if (storyImageInput) {

    storyImageInput.addEventListener('change', function () {

        storyPreview.innerHTML = '';

        const file = this.files[0];

        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            alert('Vui lòng chọn một file ảnh.');
            this.value = '';
            return;
        }

        const image = document.createElement('img');

        image.src = URL.createObjectURL(file);

        image.alt = 'Ảnh Chuyện tình của chúng mình';

        image.onload = function () {
            URL.revokeObjectURL(image.src);
        };

        storyPreview.appendChild(image);
    });

}
</script>

</body>

</html>