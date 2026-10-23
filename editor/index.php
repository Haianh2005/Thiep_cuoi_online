<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

// Yêu cầu đăng nhập để truy cập Editor (FR-06)
requireLogin();

/*
=========================================================
EDITOR KHÁCH HÀNG
URL:
editor/?token=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
=========================================================
*/


/* =====================================================
   HÀM HTML
===================================================== */

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}
/* =====================================================
   HÀM XÓA FILE UPLOAD AN TOÀN
===================================================== */
function deleteUploadFile(?string $webPath): void
{
    if (!$webPath) {
        return;
    }
    if (strpos($webPath, '/WeddingProject/uploads/') !== 0) {
        return;
    }
    $relative = str_replace(
        '/WeddingProject/',
        '',
        $webPath
    );
    $file = dirname(__DIR__) . '/' . $relative;

    if (is_file($file)) {
        @unlink($file);
    }
}
/* =====================================================
   UPLOAD ẢNH
===================================================== */
/* =====================================================
   UPLOAD QR
   Dùng riêng cho QR giống create.php
===================================================== */
function uploadGiftQr(
    string $fieldName,
    int $weddingId
): string {

    if (
        !isset($_FILES[$fieldName]) ||
        $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return '';
    }

    if (
        $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK
    ) {
        throw new Exception(
            "Không thể upload file QR."
        );
    }

    $file = $_FILES[$fieldName];

    /*
     * Kiểm tra file có thực sự là ảnh
     */
    $imageInfo = @getimagesize(
        $file['tmp_name']
    );

    if ($imageInfo === false) {
        throw new Exception(
            "File QR không phải là hình ảnh hợp lệ."
        );
    }

    /*
     * Loại ảnh cho phép
     */
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $mime = $imageInfo['mime'];

    if (!isset($allowedTypes[$mime])) {
        throw new Exception(
            "Ảnh QR chỉ chấp nhận JPG, PNG hoặc WEBP."
        );
    }

    /*
     * Thư mục lưu QR
     */
    $uploadDir =
        __DIR__ .
        '/../uploads/gifts/';

    /*
     * Nếu chưa có thư mục thì tạo
     */
    if (!is_dir($uploadDir)) {

        if (
            !mkdir(
                $uploadDir,
                0777,
                true
            )
        ) {
            throw new Exception(
                "Không thể tạo thư mục lưu ảnh QR."
            );
        }
    }

    /*
     * Tạo tên file mới
     */
    $extension =
        $allowedTypes[$mime];

    $fileName =
        'gift_' .
        $weddingId .
        '_' .
        uniqid() .
        '.' .
        $extension;

    $destination =
        $uploadDir .
        $fileName;

    /*
     * Di chuyển file từ thư mục tạm
     * vào uploads/gifts/
     */
    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {
        throw new Exception(
            "Không thể lưu ảnh QR lên máy chủ."
        );
    }

    /*
     * Đường dẫn lưu vào MySQL
     */
    return
        '/WeddingProject/uploads/gifts/' .
        $fileName;
}
function uploadImage(
    string $fieldName,
    int $weddingId,
    string $prefix
): string {

    if (
        !isset($_FILES[$fieldName]) ||
        $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return '';
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(
            "Không thể upload ảnh."
        );
    }
    $file = $_FILES[$fieldName];
    $imageInfo = @getimagesize(
        $file['tmp_name']
    );

    if ($imageInfo === false) {
        throw new Exception(
            "File tải lên không phải hình ảnh hợp lệ."
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
            "Ảnh chỉ hỗ trợ JPG, PNG hoặc WEBP."
        );
    }

    $extension =
        $allowedTypes[$mime];

    $uploadDir =
        __DIR__ .
        '/../uploads/weddings/' .
        $weddingId .
        '/';

    if (!is_dir($uploadDir)) {

        if (
            !mkdir(
                $uploadDir,
                0777,
                true
            )
        ) {
            throw new Exception(
                "Không thể tạo thư mục upload."
            );
        }
    }

    $fileName =
        $prefix .
        '_' .
        $weddingId .
        '_' .
        uniqid() .
        '.' .
        $extension;

    $destination =
        $uploadDir .
        $fileName;

    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {
        throw new Exception(
            "Không thể lưu ảnh."
        );
    }

    return
        '/WeddingProject/uploads/weddings/' .
        $weddingId .
        '/' .
        $fileName;
}


/* =====================================================
   UPLOAD NHẠC
===================================================== */

function uploadMusicEditor(
    string $fieldName,
    int $weddingId
): string {

    if (
        !isset($_FILES[$fieldName]) ||
        $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return '';
    }

    if (
        $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK
    ) {
        throw new Exception(
            "Không thể upload nhạc."
        );
    }

    $file = $_FILES[$fieldName];

    if (
        $file['size'] >
        20 * 1024 * 1024
    ) {
        throw new Exception(
            "File nhạc không được lớn hơn 20MB."
        );
    }

    $extension =
        strtolower(
            pathinfo(
                $file['name'],
                PATHINFO_EXTENSION
            )
        );

    $allowedExtensions = [
        'mp3',
        'wav',
        'ogg'
    ];

    if (
        !in_array(
            $extension,
            $allowedExtensions,
            true
        )
    ) {
        throw new Exception(
            "Nhạc chỉ hỗ trợ MP3, WAV hoặc OGG."
        );
    }

    $uploadDir =
        __DIR__ .
        '/../uploads/music/';

    if (!is_dir($uploadDir)) {

        if (
            !mkdir(
                $uploadDir,
                0777,
                true
            )
        ) {
            throw new Exception(
                "Không thể tạo thư mục nhạc."
            );
        }
    }

    $fileName =
        'music_' .
        $weddingId .
        '_' .
        uniqid() .
        '.' .
        $extension;

    $destination =
        $uploadDir .
        $fileName;

    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {
        throw new Exception(
            "Không thể lưu file nhạc."
        );
    }

    return
        '/WeddingProject/uploads/music/' .
        $fileName;
}


/* =====================================================
   LẤY TOKEN
===================================================== */

$token = trim(
    $_GET['token'] ??
    $_POST['token'] ??
    ''
);

if ($token === '') {

    die(
        '<div style="
            font-family:Arial;
            padding:40px;
            text-align:center;
        ">
            <h2>Không tìm thấy mã chỉnh sửa</h2>
            <p>Đường dẫn Editor không có token.</p>
        </div>'
    );
}


/* =====================================================
   TÌM THIỆP
===================================================== */

$stmt = $pdo->prepare("
    SELECT
        w.*,
        t.name AS template_name,
        t.folder AS template_folder
    FROM weddings w
    LEFT JOIN templates t
        ON t.id = w.template_id
    WHERE w.edit_token = :token
    LIMIT 1
");

$stmt->execute([
    'token' => $token
]);

$wedding = $stmt->fetch();

if (!$wedding) {
    die(
        '<div style="
            font-family:Arial;
            padding:40px;
            text-align:center;
        ">
            <h2>Không tìm thấy thiệp cưới</h2>
            <p>Mã chỉnh sửa không đúng hoặc không tồn tại.</p>
        </div>'
    );
}

// Kiểm tra quyền sở hữu thiệp theo FR-06 & checkWeddingOwnership
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
if (!checkWeddingOwnership($pdo, (int)$wedding['id'], $currentUserId)) {
    http_response_code(403);
    die(
        '<div style="
            font-family:Arial;
            padding:50px 20px;
            text-align:center;
            color:#be123c;
            max-width:500px;
            margin:40px auto;
            border:1px solid #fecdd3;
            border-radius:16px;
            background:#fff1f2;
        ">
            <h2 style="margin-top:0;">403 Forbidden: Quyền truy cập bị từ chối</h2>
            <p style="color:#475569;line-height:1.6;">Bạn không có quyền chỉnh sửa thiệp cưới này vì tài khoản hiện tại không phải là chủ sở hữu thiệp.</p>
            <p><a href="/WeddingProject/my-weddings.php" style="display:inline-block;margin-top:10px;padding:10px 20px;background:#e11d48;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;">Về trang Thiệp của tôi</a></p>
        </div>'
    );
}


$weddingId =
    (int)$wedding['id'];


/* =====================================================
   LẤY BỐ MẸ
===================================================== */

$parentStmt = $pdo->prepare("
    SELECT *
    FROM wedding_parents
    WHERE wedding_id = :wedding_id
");

$parentStmt->execute([
    'wedding_id' => $weddingId
]);

$parents = [];

foreach (
    $parentStmt->fetchAll()
    as $parent
) {

    $key =
        $parent['side'] .
        '_' .
        $parent['parent_role'];

    $parents[$key] =
        $parent['parent_name'];
}


/* =====================================================
   LẤY SỰ KIỆN
===================================================== */

$eventStmt = $pdo->prepare("
    SELECT *
    FROM wedding_events
    WHERE wedding_id = :wedding_id
    ORDER BY sort_order ASC, id ASC
");

$eventStmt->execute([
    'wedding_id' => $weddingId
]);

$events =
    $eventStmt->fetchAll();


/* =====================================================
   LẤY ALBUM
===================================================== */

$photoStmt = $pdo->prepare("
    SELECT *
    FROM wedding_photos
    WHERE wedding_id = :wedding_id
    ORDER BY sort_order ASC, id ASC
");

$photoStmt->execute([
    'wedding_id' => $weddingId
]);

$photos =
    $photoStmt->fetchAll();


/* =====================================================
   LẤY HỘP QUÀ
===================================================== */

$giftStmt = $pdo->prepare("
    SELECT *
    FROM wedding_gifts
    WHERE wedding_id = :wedding_id
    ORDER BY id ASC
");

$giftStmt->execute([
    'wedding_id' => $weddingId
]);

$gifts =
    $giftStmt->fetchAll();


$giftMode =
    'bank';

$groomGift = [];
$brideGift = [];
$messageGift = [];


foreach ($gifts as $gift) {

    if (
        ($gift['gift_mode'] ?? '') === 'message'
    ) {

        $giftMode = 'message';

        $messageGift = $gift;

        continue;
    }

    if (
        ($gift['person_type'] ?? '') === 'groom'
    ) {

        $giftMode = 'bank';

        $groomGift = $gift;
    }

    if (
        ($gift['person_type'] ?? '') === 'bride'
    ) {

        $giftMode = 'bank';

        $brideGift = $gift;
    }
}


/* =====================================================
   GIÁ TRỊ FORM
===================================================== */

$groomName =
    $wedding['groom_name'] ?? '';

$brideName =
    $wedding['bride_name'] ?? '';

$weddingDate =
    $wedding['wedding_date'] ?? '';

$weddingTime =
    $wedding['wedding_time'] ?? '';

$venue =
    $wedding['venue'] ?? '';

$address =
    $wedding['address'] ?? '';

$story =
    $wedding['story'] ?? '';

$storyImage =
    $wedding['story_image'] ?? '';

$music =
    $wedding['music'] ?? '';

$success = '';
$error = '';


/* =====================================================
   XỬ LÝ LƯU
===================================================== */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
$action = $_POST['action'] ?? 'save';
    try {

        $groomName =
            trim(
                $_POST['groom_name'] ?? ''
            );

        $brideName =
            trim(
                $_POST['bride_name'] ?? ''
            );

        $weddingDate =
            $_POST['wedding_date'] ?? '';

        $weddingTime =
            $_POST['wedding_time'] ?? '';

        $venue =
            trim(
                $_POST['venue'] ?? ''
            );

        $address =
            trim(
                $_POST['address'] ?? ''
            );

        $story =
            trim(
                $_POST['story'] ?? ''
            );


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


        /* =================================================
           TRANSACTION
        ================================================= */

        $pdo->beginTransaction();

        /* =================================================
           UPLOAD ẢNH NỀN + ẢNH CHÂN DUNG
        ================================================= */

        $newBackgroundImage = uploadImage(
            'background_image',
            $weddingId,
            'background'
        );
        if ($newBackgroundImage !== '') {
            $backgroundImage = $newBackgroundImage;
        }

        $newGroomPhoto = uploadImage(
            'groom_photo',
            $weddingId,
            'groom'
        );
        if ($newGroomPhoto !== '') {
            $groomPhoto = $newGroomPhoto;
        }

        $newBridePhoto = uploadImage(
            'bride_photo',
            $weddingId,
            'bride'
        );
        if ($newBridePhoto !== '') {
            $bridePhoto = $newBridePhoto;
        }


        /* =================================================
           1. CẬP NHẬT THÔNG TIN CHÍNH
        ================================================= */

        $updateWedding = $pdo->prepare("
    UPDATE weddings
    SET
    groom_name = :groom_name,
    bride_name = :bride_name,
    wedding_date = :wedding_date,
    wedding_time = :wedding_time,
    venue = :venue,
    address = :address,
    story = :story,
    background_image = :background_image,
    groom_photo = :groom_photo,
    bride_photo = :bride_photo,
    music = :music,
    story_image = :story_image,
    updated_at = NOW()
WHERE id = :id
");

        $updateWedding->execute([
    'groom_name' =>
        $groomName,

    'bride_name' =>
        $brideName,

    'wedding_date' =>
        $weddingDate,

    'wedding_time' =>
        $weddingTime,

    'venue' =>
        $venue,

    'address' =>
        $address,

    'story' =>
        $story,

    'background_image' =>
        $backgroundImage,

    'groom_photo' =>
        $groomPhoto,

    'bride_photo' =>
        $bridePhoto,

    'music' =>
        $music,

    'story_image' =>
        $storyImage,

    'id' =>
        $weddingId
]);

        /* =================================================
           2. BỐ MẸ
        ================================================= */

        $pdo->prepare("
            DELETE FROM wedding_parents
            WHERE wedding_id = ?
        ")->execute([
            $weddingId
        ]);


        $insertParent = $pdo->prepare("
            INSERT INTO wedding_parents
            (
                wedding_id,
                side,
                parent_role,
                parent_name
            )
            VALUES
            (
                :wedding_id,
                :side,
                :parent_role,
                :parent_name
            )
        ");


        $parentData = [
            [
                'side' => 'groom',
                'role' => 'father',
                'name' =>
                    trim(
                        $_POST['groom_father'] ?? ''
                    )
            ],
            [
                'side' => 'groom',
                'role' => 'mother',
                'name' =>
                    trim(
                        $_POST['groom_mother'] ?? ''
                    )
            ],
            [
                'side' => 'bride',
                'role' => 'father',
                'name' =>
                    trim(
                        $_POST['bride_father'] ?? ''
                    )
            ],
            [
                'side' => 'bride',
                'role' => 'mother',
                'name' =>
                    trim(
                        $_POST['bride_mother'] ?? ''
                    )
            ]
        ];


        foreach ($parentData as $parent) {

            if ($parent['name'] === '') {
                continue;
            }

            $insertParent->execute([
                'wedding_id' =>
                    $weddingId,

                'side' =>
                    $parent['side'],

                'parent_role' =>
                    $parent['role'],

                'parent_name' =>
                    $parent['name']
            ]);
        }


        /* =================================================
           3. SỰ KIỆN
        ================================================= */

        $pdo->prepare("
            DELETE FROM wedding_events
            WHERE wedding_id = ?
        ")->execute([
            $weddingId
        ]);


        $eventNames =
            $_POST['event_name'] ?? [];

        $eventDates =
            $_POST['event_date'] ?? [];

        $eventTimes =
            $_POST['event_time'] ?? [];

        $eventLocations =
            $_POST['event_location'] ?? [];

        $eventAddresses =
            $_POST['event_address'] ?? [];

        $eventDescriptions =
            $_POST['event_description'] ?? [];


        $insertEvent = $pdo->prepare("
            INSERT INTO wedding_events
            (
                wedding_id,
                event_name,
                event_date,
                event_time,
                event_location,
                event_address,
                description,
                sort_order
            )
            VALUES
            (
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


        $eventCount =
            count($eventNames);


        for (
            $i = 0;
            $i < $eventCount;
            $i++
        ) {

            $eventName =
                trim(
                    $eventNames[$i] ?? ''
                );

            $eventDate =
                trim(
                    $eventDates[$i] ?? ''
                );

            $eventTime =
                trim(
                    $eventTimes[$i] ?? ''
                );

            $eventLocation =
                trim(
                    $eventLocations[$i] ?? ''
                );

            $eventAddress =
                trim(
                    $eventAddresses[$i] ?? ''
                );

            $eventDescription =
                trim(
                    $eventDescriptions[$i] ?? ''
                );


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
                'wedding_id' =>
                    $weddingId,

                'event_name' =>
                    $eventName,

                'event_date' =>
                    $eventDate,

                'event_time' =>
                    $eventTime,

                'event_location' =>
                    $eventLocation,

                'event_address' =>
                    $eventAddress,

                'description' =>
                    $eventDescription,

                'sort_order' =>
                    $i
            ]);
        }


        /* =================================================
           4. XÓA ẢNH ALBUM ĐƯỢC CHỌN
        ================================================= */

        $deletePhotos =
            $_POST['delete_photos'] ?? [];


        if (
            is_array($deletePhotos) &&
            count($deletePhotos) > 0
        ) {

            $findPhoto = $pdo->prepare("
                SELECT image_path
                FROM wedding_photos
                WHERE id = :id
                AND wedding_id = :wedding_id
                LIMIT 1
            ");

            $deletePhoto =
                $pdo->prepare("
                    DELETE FROM wedding_photos
                    WHERE id = :id
                    AND wedding_id = :wedding_id
                ");


            foreach (
                $deletePhotos
                as $photoId
            ) {

                $photoId =
                    (int)$photoId;

                $findPhoto->execute([
                    'id' =>
                        $photoId,

                    'wedding_id' =>
                        $weddingId
                ]);

                $photo =
                    $findPhoto->fetch();


                if ($photo) {

                    deleteUploadFile(
                        $photo['image_path']
                    );

                    $deletePhoto->execute([
                        'id' =>
                            $photoId,

                        'wedding_id' =>
                            $weddingId
                    ]);
                }
            }
        }


        /* =================================================
           5. UPLOAD ALBUM MỚI
        ================================================= */

        if (
            isset($_FILES['album_images']) &&
            isset(
                $_FILES['album_images']['name']
            ) &&
            is_array(
                $_FILES['album_images']['name']
            )
        ) {

            $insertPhoto =
                $pdo->prepare("
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


            $maxSortStmt =
                $pdo->prepare("
                    SELECT
                        COALESCE(
                            MAX(sort_order),
                            0
                        )
                    FROM wedding_photos
                    WHERE wedding_id = ?
                ");

            $maxSortStmt->execute([
                $weddingId
            ]);

            $sortOrder =
                (int)$maxSortStmt->fetchColumn();


            $count =
                count(
                    $_FILES['album_images']['name']
                );


            for (
                $i = 0;
                $i < $count;
                $i++
            ) {

                if (
                    $_FILES['album_images']['error'][$i]
                    !== UPLOAD_ERR_OK
                ) {
                    continue;
                }


                $tmp =
                    $_FILES['album_images']['tmp_name'][$i];


                if (
                    $_FILES['album_images']['size'][$i]
                    >
                    10 * 1024 * 1024
                ) {
                    continue;
                }


                $imageInfo =
                    @getimagesize($tmp);


                if ($imageInfo === false) {
                    continue;
                }


                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];


                $mime =
                    $imageInfo['mime'];


                if (
                    !isset(
                        $allowedTypes[$mime]
                    )
                ) {
                    continue;
                }


                $extension =
                    $allowedTypes[$mime];


                $uploadDir =
                    __DIR__ .
                    '/../uploads/weddings/' .
                    $weddingId .
                    '/';


                if (!is_dir($uploadDir)) {
                    mkdir(
                        $uploadDir,
                        0777,
                        true
                    );
                }


                $fileName =
                    'album_' .
                    $weddingId .
                    '_' .
                    uniqid() .
                    '.' .
                    $extension;


                $destination =
                    $uploadDir .
                    $fileName;


                if (
                    move_uploaded_file(
                        $tmp,
                        $destination
                    )
                ) {

                    $sortOrder++;


                    $imagePath =
                        '/WeddingProject/uploads/weddings/' .
                        $weddingId .
                        '/' .
                        $fileName;


                    $insertPhoto->execute([
                        'wedding_id' =>
                            $weddingId,

                        'image_path' =>
                            $imagePath,

                        'sort_order' =>
                            $sortOrder
                    ]);
                }
            }
        }
        /* =================================================
           6. ẢNH CHUYỆN TÌNH
        ================================================= */

        $newStoryImage =
            uploadImage(
                'story_image',
                $weddingId,
                'story',
            );


        if ($newStoryImage !== '') {

            if ($storyImage !== '') {
                deleteUploadFile(
                    $storyImage
                );
            }


            $updateStory =
                $pdo->prepare("
                    UPDATE weddings
                    SET story_image = :story_image
                    WHERE id = :id
                ");

            $updateStory->execute([
                'story_image' =>
                    $newStoryImage,

                'id' =>
                    $weddingId
            ]);


            $storyImage =
                $newStoryImage;
        }


        /* =================================================
           7. NHẠC
        ================================================= */

        $newMusic =
            uploadMusicEditor(
                'music',
                $weddingId
            );


        if ($newMusic !== '') {

            if ($music !== '') {
                deleteUploadFile(
                    $music
                );
            }


            $updateMusic =
                $pdo->prepare("
                    UPDATE weddings
                    SET music = :music
                    WHERE id = :id
                ");

            $updateMusic->execute([
                'music' =>
                    $newMusic,

                'id' =>
                    $weddingId
            ]);


            $music =
                $newMusic;
        }


        /* =================================================
           8. HỘP QUÀ
        ================================================= */

        $newGiftMode =
            $_POST['gift_mode'] ??
            'bank';


        if (
            !in_array(
                $newGiftMode,
                ['bank', 'message'],
                true
            )
        ) {
            $newGiftMode = 'bank';
        }


        /*
         * Lưu QR cũ trước khi xóa dữ liệu
         */

        $oldGroomQr =
            $groomGift['qr_image'] ?? '';

        $oldBrideQr =
            $brideGift['qr_image'] ?? '';


        /*
 * Upload QR chú rể
 */
$newGroomQr =
    uploadGiftQr(
        'groom_qr_image',
        $weddingId
    );
/*
 * Upload QR cô dâu
 */
$newBrideQr =
    uploadGiftQr(
        'bride_qr_image',
        $weddingId
    );


        if ($newGroomQr === '') {
            $newGroomQr =
                $oldGroomQr;
        }

        if ($newBrideQr === '') {
            $newBrideQr =
                $oldBrideQr;
        }


        /*
         * Xóa QR cũ nếu đã thay ảnh
         */

        if (
            $newGroomQr !== $oldGroomQr &&
            $oldGroomQr !== ''
        ) {
            deleteUploadFile(
                $oldGroomQr
            );
        }

        if (
            $newBrideQr !== $oldBrideQr &&
            $oldBrideQr !== ''
        ) {
            deleteUploadFile(
                $oldBrideQr
            );
        }


        /*
         * Xóa dữ liệu hộp quà cũ
         */

        $pdo->prepare("
            DELETE FROM wedding_gifts
            WHERE wedding_id = ?
        ")->execute([
            $weddingId
        ]);


        if ($newGiftMode === 'bank') {

            $insertGift =
                $pdo->prepare("
                    INSERT INTO wedding_gifts
                    (
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
                    VALUES
                    (
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
                'wedding_id' =>
                    $weddingId,

                'person_type' =>
                    'groom',

                'person_name' =>
                    trim(
                        $_POST['groom_gift_name']
                        ?? $groomName
                    ),

                'bank_name' =>
                    trim(
                        $_POST['groom_bank_name']
                        ?? ''
                    ),

                'account_number' =>
                    trim(
                        $_POST['groom_account_number']
                        ?? ''
                    ),

                'account_name' =>
                    trim(
                        $_POST['groom_account_name']
                        ?? ''
                    ),

                'qr_image' =>
                    $newGroomQr
            ]);


            $insertGift->execute([
                'wedding_id' =>
                    $weddingId,

                'person_type' =>
                    'bride',

                'person_name' =>
                    trim(
                        $_POST['bride_gift_name']
                        ?? $brideName
                    ),

                'bank_name' =>
                    trim(
                        $_POST['bride_bank_name']
                        ?? ''
                    ),

                'account_number' =>
                    trim(
                        $_POST['bride_account_number']
                        ?? ''
                    ),

                'account_name' =>
                    trim(
                        $_POST['bride_account_name']
                        ?? ''
                    ),

                'qr_image' =>
                    $newBrideQr
            ]);
        }


        if ($newGiftMode === 'message') {

            $giftMessage =
                trim(
                    $_POST['gift_message']
                    ??
                    'Sự hiện diện của bạn là niềm vui của gia đình chúng tôi.'
                );


            $insertMessage =
                $pdo->prepare("
                    INSERT INTO wedding_gifts
                    (
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
                    VALUES
                    (
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


            $insertMessage->execute([
                'wedding_id' =>
                    $weddingId,

                'person_name' =>
                    $groomName .
                    ' & ' .
                    $brideName,

                'gift_message' =>
                    $giftMessage
            ]);
        }    
/* =================================================
   NẾU KHÁCH BẤM GỬI DUYỆT
================================================= */

if ($action === 'submit_review') {

    $updateStatus = $pdo->prepare("
        UPDATE weddings
        SET
            status = 2,
            updated_at = NOW()
        WHERE id = :id
    ");

    $updateStatus->execute([
        'id' => $weddingId
    ]);
}


/* =================================================
   COMMIT
================================================= */

$pdo->commit();


/* =================================================
   CHUYỂN HƯỚNG
================================================= */

if ($action === 'submit_review') {

    header(
        'Location: index.php?token=' .
        urlencode($token) .
        '&submitted=1'
    );

} else {

    header(
        'Location: index.php?token=' .
        urlencode($token) .
        '&saved=1'
    );
}

exit;


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $error = $e->getMessage();
}

}


/* =====================================================
   THÔNG BÁO SAU REDIRECT
===================================================== */

if (
    isset($_GET['saved']) &&
    $_GET['saved'] === '1'
) {
    $success =
        "Đã lưu thay đổi thành công!";
}

if (
    isset($_GET['submitted']) &&
    $_GET['submitted'] === '1'
) {
    $success =
        "Đã gửi thiệp cho Admin duyệt!";
}
/* =====================================================
   ĐỌC LẠI DỮ LIỆU SAU KHI LƯU
===================================================== */

$stmt->execute([
    'token' => $token
]);

$wedding =
    $stmt->fetch();


$parentStmt->execute([
    'wedding_id' => $weddingId
]);

$parents = [];

foreach (
    $parentStmt->fetchAll()
    as $parent
) {

    $key =
        $parent['side'] .
        '_' .
        $parent['parent_role'];

    $parents[$key] =
        $parent['parent_name'];
}


$eventStmt->execute([
    'wedding_id' => $weddingId
]);

$events =
    $eventStmt->fetchAll();


$photoStmt->execute([
    'wedding_id' => $weddingId
]);

$photos =
    $photoStmt->fetchAll();


$giftStmt->execute([
    'wedding_id' => $weddingId
]);

$gifts =
    $giftStmt->fetchAll();


$giftMode =
    'bank';

$groomGift = [];
$brideGift = [];
$messageGift = [];

foreach ($gifts as $gift) {

    if (
        ($gift['gift_mode'] ?? '') === 'message'
    ) {

        $giftMode = 'message';

        $messageGift = $gift;

    } elseif (
        ($gift['person_type'] ?? '') === 'groom'
    ) {

        $giftMode = 'bank';

        $groomGift = $gift;

    } elseif (
        ($gift['person_type'] ?? '') === 'bride'
    ) {

        $giftMode = 'bank';

        $brideGift = $gift;
    }
}


$groomName =
    $wedding['groom_name'] ?? '';

$brideName =
    $wedding['bride_name'] ?? '';

$weddingDate =
    $wedding['wedding_date'] ?? '';

$weddingTime =
    $wedding['wedding_time'] ?? '';

$venue =
    $wedding['venue'] ?? '';

$address =
    $wedding['address'] ?? '';

$story =
    $wedding['story'] ?? '';

$storyImage =
    $wedding['story_image'] ?? '';
$backgroundImage =
    $wedding['background_image'] ?? '';
$groomPhoto =
    $wedding['groom_photo'] ?? '';
$bridePhoto =
    $wedding['bride_photo'] ?? '';
$music =
    $wedding['music'] ?? '';

?>

<!DOCTYPE html>

<html lang="vi">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Chỉnh sửa thiệp cưới</title>


<style>

/* =====================================================
   RESET
===================================================== */

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


/* =====================================================
   PAGE
===================================================== */

.page {

    width: min(
        1100px,
        94%
    );

    margin: 30px auto 60px;
}


/* =====================================================
   HEADER
===================================================== */

.page-header {

    background: white;

    border-radius: 16px;

    padding: 30px;

    margin-bottom: 20px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.06);

    border-top:
        5px solid #8b1e2d;
}


.page-header h1 {

    margin:
        0 0 8px;

    color: #8b1e2d;

    font-size: 30px;
}


.page-header p {

    margin: 0;

    color: #777;

    line-height: 1.6;
}


/* =====================================================
   THÔNG BÁO
===================================================== */

.alert {

    padding: 16px 20px;

    border-radius: 12px;

    margin-bottom: 20px;

    line-height: 1.6;
}


.alert-error {

    background: #fff0f0;

    border:
        1px solid #e5a5a5;

    color: #9b1c1c;
}


.alert-success {

    background: #edf9f0;

    border:
        1px solid #9ad0a5;

    color: #246b35;
}


/* =====================================================
   CARD
===================================================== */

.card {

    background: white;

    border-radius: 16px;

    padding: 28px;

    margin-bottom: 22px;

    box-shadow:
        0 5px 20px
        rgba(0,0,0,.06);
}


.card-title {

    display: flex;

    align-items: center;

    gap: 12px;

    margin-bottom: 24px;

    border-bottom:
        1px solid #eee;

    padding-bottom: 15px;
}


.card-title .number {

    width: 36px;

    height: 36px;

    border-radius: 50%;

    background: #8b1e2d;

    color: white;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: bold;
}


.card-title h2 {

    margin: 0;

    color: #8b1e2d;

    font-size: 21px;
}


/* =====================================================
   GRID
===================================================== */

.grid-2 {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0,1fr));

    gap: 18px;
}


/* =====================================================
   FORM
===================================================== */

.form-group {

    margin-bottom: 18px;
}


.form-group label {

    display: block;

    margin-bottom: 7px;

    font-weight: bold;

    color: #555;
}


input[type="text"],
input[type="date"],
input[type="time"],
input[type="file"],
textarea {

    width: 100%;

    padding: 12px 14px;

    border:
        1px solid #ddd;

    border-radius: 10px;

    font-size: 15px;

    font-family: inherit;

    outline: none;

    background: white;
}


input:focus,
textarea:focus {

    border-color: #8b1e2d;

    box-shadow:
        0 0 0 3px
        rgba(139,30,45,.08);
}


textarea {

    min-height: 120px;

    resize: vertical;
}


/* =====================================================
   TEMPLATE READONLY
===================================================== */

.template-info {

    display: flex;

    align-items: center;

    gap: 18px;

    padding: 18px;

    background: #fafafa;

    border:
        1px solid #eee;

    border-radius: 14px;
}


.template-badge {

    padding: 8px 14px;

    border-radius: 20px;

    background: #8b1e2d;

    color: white;

    font-weight: bold;
}


.template-info p {

    margin: 0;

    color: #777;
}


/* =====================================================
   EVENTS
===================================================== */

.event-box {

    border:
        1px solid #eee;

    border-radius: 14px;

    padding: 20px;

    margin-bottom: 16px;

    background: #fafafa;
}


.event-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 15px;
}


.event-header strong {

    color: #8b1e2d;
}


.remove-event {

    border: none;

    background: #f4dddd;

    color: #9b1c1c;

    padding: 8px 12px;

    border-radius: 8px;

    cursor: pointer;
}


.add-event {

    border: none;

    background: #8b1e2d;

    color: white;

    padding: 11px 18px;

    border-radius: 9px;

    cursor: pointer;

    font-weight: bold;
}


/* =====================================================
   ALBUM
===================================================== */

.album-grid {

    display: grid;

    grid-template-columns:
        repeat(4,1fr);

    gap: 15px;
}


.album-item {

    position: relative;

    border-radius: 12px;

    overflow: hidden;

    border:
        1px solid #eee;

    background: #fafafa;
}


.album-item img {

    width: 100%;

    aspect-ratio: 1 / 1;

    object-fit: cover;

    display: block;
}


.album-delete {

    position: absolute;

    left: 8px;

    top: 8px;

    background: rgba(255,255,255,.95);

    padding: 6px 9px;

    border-radius: 7px;

    font-size: 12px;
}


.album-delete input {

    margin-right: 5px;
}


/* =====================================================
   CURRENT IMAGE
===================================================== */

.current-image {

    margin-top: 15px;

    text-align: center;
}


.current-image img {

    max-width: 280px;

    width: 100%;

    border-radius: 14px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,.12);
}


/* =====================================================
   GIFT
===================================================== */

.gift-mode-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0,1fr));

    gap: 18px;

    margin-bottom: 25px;
}


.gift-mode-option input {

    display: none;
}


.gift-mode-label {

    display: block;

    border: 2px solid #eee;
    border-radius: 14px;
    padding: 20px;
    cursor: pointer;
    height: 100%;
}
.gift-mode-option
input:checked
+ .gift-mode-label {
    border-color: #8b1e2d;
    background: #fff7f8;
}
.gift-mode-icon { font-size: 28px;}
.gift-mode-label h3 {
    margin:  10px 0 6px;
    color: #8b1e2d;}
.gift-mode-label p {margin: 0;color: #777;line-height: 1.5;}
.gift-person {
    border:
        1px solid #eee;
    border-radius: 14px;
    padding: 20px;
    margin-top: 18px;
}
.gift-person h3 {
    margin-top: 0; color: #8b1e2d;
}
.qr-preview { margin-top: 12px;
}
.qr-preview img {
    width: 130px;
    height: 130px;
    object-fit: cover;
    border-radius: 10px;
    border:
        1px solid #eee;
}
/* =====================================================
   BUTTON
===================================================== */

.save-area {
    position: sticky;
    bottom: 15px;
    z-index: 10;
    background: rgba(255,255,255,.96);
    border-radius: 16px;
    padding: 15px;
    box-shadow:
        0 8px 30px
        rgba(0,0,0,.12);
    display: flex;
    gap: 12px;
    justify-content: center;
}
.save-button {
    border: none;
    background: #8b1e2d;
    color: white;
    padding:
        15px 30px;
    border-radius: 11px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}
.save-button:hover {
    background: #701624;
}
.preview-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    background: #eee;
    color: #444;
    padding: 15px 25px;
    border-radius: 11px;
    font-weight: bold;
}
/* =====================================================
   MOBILE
===================================================== */
@media (
    max-width: 700px
) {
    .page {
        width: 94%;
        margin-top: 15px;
    }
.card {
        padding: 20px;}
    .grid-2 {
        grid-template-columns: 1fr;
    }
    .gift-mode-grid {
        grid-template-columns: 1fr;
    }
    .album-grid {

        grid-template-columns: repeat(2,1fr);
    }
    .save-area { flex-direction: column;
    }
    .save-button,
    .preview-button {
        width: 100%;
    }
}
</style>
</head>
<body>
<div class="page">
<!-- =================================================
     HEADER
================================================== -->
<div class="page-header">
    <h1>💌 Chỉnh sửa thiệp cưới</h1>
    <p>
        Cậu có thể chỉnh sửa thông tin của thiệp.
        Mẫu thiệp và thiết kế gốc được giữ nguyên.
    </p>
</div>
<?php if ($error): ?>
<div class="alert alert-error">
    <?= e($error) ?>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success">
    <?= e($success) ?>
</div>
<?php endif; ?>
<form
    method="POST"
    enctype="multipart/form-data"
>
<input
    type="hidden"
    name="token"
    value="<?= e($token) ?>"
>
<!-- =================================================
     1. MẪU THIỆP
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
    <div class="template-info">
        <div class="template-badge">
            <?= e(
                $wedding['template_name']
                ?: 'Mẫu thiệp'
            ) ?>
        </div>
        <p>
            Mẫu thiệp do hệ thống quản lý.
            Khách hàng không thể thay đổi
            HTML / CSS / JS của mẫu.
        </p>
    </div>
</section>
<!-- =================================================
     2. CÔ DÂU & CHÚ RỂ
================================================== -->
<section class="card">
    <div class="card-title">
        <div class="number">
            2
        </div>
        <h2> Thông tin cô dâu & chú rể</h2>
    </div>
    <div class="grid-2">
        <div class="form-group">
            <label>   Tên chú rể </label>
            <input
                type="text"
                name="groom_name"
                value="<?= e($groomName) ?>"
                required
            >
        </div>
        <div class="form-group">
            <label>
                Tên cô dâu
            </label>
            <input
                type="text"
                name="bride_name"
                value="<?= e($brideName) ?>"
                required
            >
        </div>
    </div>
</section>
<!-- =================================================
     ẢNH NỀN VÀ ẢNH CHÂN DUNG
================================================== -->
<section class="card">
    <div class="card-title">
        <div class="number">Ảnh</div>
        <h2>Ảnh nền và ảnh cô dâu, chú rể</h2>
    </div>
    <p style="color:#777;line-height:1.6;">
        Ảnh được lưu riêng cho thiệp này. Chọn ảnh mới rồi bấm LƯU THAY ĐỔI.
        Mỗi mẫu thiệp cần được kết nối trong template để hiển thị ảnh ở đúng vị trí.
        Nếu không chọn ảnh mới, ảnh hiện tại sẽ được giữ nguyên.
    </p>
    <div class="grid-2">
        <div class="form-group">
            <label>Ảnh chân dung chú rể</label>
            <?php if ($groomPhoto !== ''): ?>
                <div class="current-image">
                    <img src="<?= e($groomPhoto) ?>" alt="Ảnh chú rể hiện tại" style="max-width:100%;max-height:260px;object-fit:contain;border-radius:10px;">
                </div>
            <?php endif; ?>
            <input type="file" name="groom_photo" accept="image/jpeg,image/png,image/webp">
            <p style="color:#888;font-size:13px;">JPG, PNG hoặc WEBP. Để trống nếu không muốn thay ảnh.</p>
        </div>
        <div class="form-group">
            <label>Ảnh chân dung cô dâu</label>
            <?php if ($bridePhoto !== ''): ?>
                <div class="current-image">
                    <img src="<?= e($bridePhoto) ?>" alt="Ảnh cô dâu hiện tại" style="max-width:100%;max-height:260px;object-fit:contain;border-radius:10px;">
                </div>
            <?php endif; ?>
            <input type="file" name="bride_photo" accept="image/jpeg,image/png,image/webp">
            <p style="color:#888;font-size:13px;">JPG, PNG hoặc WEBP. Để trống nếu không muốn thay ảnh.</p>
        </div>
    </div>
    <div class="form-group">
        <label>Ảnh nền thiệp</label>
        <?php if ($backgroundImage !== ''): ?>
            <div class="current-image">
                <img src="<?= e($backgroundImage) ?>" alt="Ảnh nền hiện tại" style="max-width:100%;max-height:300px;object-fit:contain;border-radius:10px;">
            </div>
        <?php endif; ?>
        <input type="file" name="background_image" accept="image/jpeg,image/png,image/webp">
        <p style="color:#888;font-size:13px;">Chọn ảnh muốn dùng làm nền. Vị trí hiển thị sẽ được thiết lập riêng trong code của từng mẫu thiệp.</p>
    </div>
</section>
<!-- =================================================
     3. GIA ĐÌNH
================================================== -->
<section class="card">
    <div class="card-title">
        <div class="number">  3</div>
        <h2>  Thông tin gia đình</h2>
    </div>
    <h3 style="color:#8b1e2d;"> Gia đình chú rể</h3>
    <div class="grid-2">
        <div class="form-group">
            <label> Bố chú rể </label>
            <input
                type="text"
                name="groom_father"
                value="<?= e(
                    $parents['groom_father']
                    ?? ''
                ) ?>"
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
                value="<?= e(
                    $parents['groom_mother']
                    ?? ''
                ) ?>"
                placeholder="Họ tên mẹ"
            >
        </div>
    </div>
    <h3 style="color:#8b1e2d;">
        Gia đình cô dâu
    </h3>
    <div class="grid-2">
        <div class="form-group">
            <label>  Bố cô dâu</label>
            <input
                type="text"
                name="bride_father"
                value="<?= e(
                    $parents['bride_father']
                    ?? ''
                ) ?>"
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
                value="<?= e(
                    $parents['bride_mother']
                    ?? ''
                ) ?>"
                placeholder="Họ tên mẹ"
            >
        </div>
    </div>
</section>
<!-- =================================================
     4. THÔNG TIN ĐÁM CƯỚI
================================================== -->
<section class="card">
    <div class="card-title">
        <div class="number">
            4
        </div>
        <h2>
            Thông tin đám cưới
        </h2>
    </div>
    <div class="grid-2">
        <div class="form-group">
            <label>
                Ngày cưới
            </label>
            <input
                type="date"
                name="wedding_date"
                value="<?= e(
                    $weddingDate
                ) ?>"
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
                value="<?= e(
                    substr(
                        $weddingTime,
                        0,
                        5
                    )
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
            name="venue"
            value="<?= e($venue) ?>"
            placeholder="Tên nhà hàng / địa điểm"
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
    <div class="form-group">
        <label>
            Chuyện tình của chúng mình
        </label>
        <textarea
            name="story"
            placeholder="Viết câu chuyện tình yêu..."
        ><?= e($story) ?></textarea>
    </div>
</section>
<!-- =================================================
     5. SỰ KIỆN
================================================== -->
<section class="card">
    <div class="card-title">
        <div class="number"> 5
        </div>
        <h2>
            Các sự kiện
        </h2>
    </div>
    <div id="eventList">
<?php if (
    count($events) === 0
): ?>
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
                <label> Giờ</label>
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
            >
        </div>
        <div class="form-group">
            <label>
                Địa chỉ
            </label>
            <input
                type="text"
                name="event_address[]"
            >
        </div>
        <div class="form-group">
            <label>
                Mô tả
            </label>
            <textarea
                name="event_description[]"
            ></textarea>
        </div>
    </div>
<?php else: ?>
<?php foreach (
    $events as $index => $event
): ?>
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
            ) ?>"
        >
    </div>
    <div class="grid-2">
        <div class="form-group">
            <label> Ngày</label>
            <input
                type="date"
                name="event_date[]"
                value="<?= e(
                    $event['event_date']
                ) ?>"
            >
        </div>
        <div class="form-group">
            <label> Giờ  </label>
            <input
                type="time"
                name="event_time[]"
                value="<?= e(
                    substr(
                        $event['event_time']
                        ?? '',
                        0,
                        5
                    )
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
            ) ?>"
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
            ) ?>"
        >
    </div>
    <div class="form-group">
        <label>  Mô tả</label>
        <textarea
            name="event_description[]"
        ><?= e(
            $event['description']
        ) ?></textarea>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
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
     6. ALBUM
================================================== -->
<section class="card">
    <div class="card-title">
        <div class="number">
            6
        </div>
        <h2>
            Album ảnh cưới
        </h2>
    </div>
<?php if (
    count($photos) > 0
): ?>
    <div class="album-grid">
<?php foreach (
    $photos as $photo
): ?>
  <div class="album-item">

            <img
                src="<?= e(
                    $photo['image_path']
                ) ?>"
                alt="Ảnh cưới"
            >
            <label class="album-delete">
                <input
                    type="checkbox"
                    name="delete_photos[]"
                    value="<?= (int)$photo['id'] ?>"
                >
                Xóa ảnh
            </label>
        </div>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <p style="color:#777;">
        Chưa có ảnh trong album.
    </p>
<?php endif; ?>
    <div
        class="form-group"
        style="margin-top:25px;"
    >
        <label>
            Thêm ảnh mới
        </label>
        <input
            type="file"
            name="album_images[]"
            accept="image/jpeg,image/png,image/webp"
            multiple
        >
        <p style="
            color:#888;
            font-size:13px;
        ">
            Có thể chọn nhiều ảnh.
            JPG, PNG hoặc WEBP · Tối đa 10MB / ảnh.
        </p>
    </div>
</section>
<!-- =================================================
     7. ẢNH CHUYỆN TÌNH
================================================== -->
<section class="card">
    <div class="card-title">
        <div class="number">
            7
        </div>
        <h2>
            Ảnh Chuyện tình của chúng mình
        </h2>
    </div>
<?php if (
    $storyImage !== ''
): ?>
    <div class="current-image">
        <p style="color:#777;">
            Ảnh hiện tại
        </p>
        <img
            src="<?= e(
                $storyImage
            ) ?>"
            alt="Ảnh chuyện tình"
        >
    </div>
<?php endif; ?>
    <div
        class="form-group"
        style="margin-top:25px;"
    >
        <label>
            Thay ảnh chuyện tình
        </label>
        <input
            type="file"
            name="story_image"
            accept="image/jpeg,image/png,image/webp"
        >
        <p style="
            color:#888;
            font-size:13px;
        ">
            JPG, PNG hoặc WEBP · Tối đa 10MB.
        </p>
    </div>
</section>
<!-- =================================================
     8. NHẠC
================================================== -->
<section class="card">
    <div class="card-title">
        <div class="number">  8 </div>
        <h2>
            Nhạc cưới
        </h2>
    </div>
<?php if (
    $music !== ''
): ?>
    <div class="form-group">
        <label>
            Nhạc hiện tại
        </label>
        <audio
            controls
            style="width:100%;"
        >
            <source
                src="<?= e($music) ?>"
            >
        </audio>
    </div>
<?php endif; ?>
    <div class="form-group">
        <label>
            Thay nhạc cưới
        </label>
        <input
            type="file"
            name="music"
            accept=".mp3,.wav,.ogg,audio/mpeg,audio/wav,audio/ogg"
        >
        <p style="
            color:#888;
            font-size:13px;
        ">
            MP3, WAV hoặc OGG · Tối đa 20MB.
        </p>
    </div>
</section>
<!-- =================================================
     9. HỘP QUÀ
================================================== -->

<section class="card">

    <div class="card-title">

        <div class="number">
            9
        </div>

        <h2>
            Hộp quà mừng
        </h2>

    </div>


    <div class="gift-mode-grid">


        <div class="gift-mode-option">

            <input
                type="radio"
                name="gift_mode"
                id="gift_bank"
                value="bank"
                <?= $giftMode === 'bank'
                    ? 'checked'
                    : '' ?>
            >

            <label
                class="gift-mode-label"
                for="gift_bank"
            >

                <div class="gift-mode-icon">
                    💳
                </div>

                <h3>
                    Chuyển khoản ngân hàng
                </h3>

                <p>
                    Hiển thị tài khoản
                    và mã QR.
                </p>

            </label>

        </div>


        <div class="gift-mode-option">

            <input
                type="radio"
                name="gift_mode"
                id="gift_message"
                value="message"
                <?= $giftMode === 'message'
                    ? 'checked'
                    : '' ?>
            >

            <label
                class="gift-mode-label"
                for="gift_message"
            >

                <div class="gift-mode-icon">
                    💌
                </div>

                <h3>
                    Lời nhắn
                </h3>

                <p>
                    Chỉ hiển thị lời nhắn
                    thay cho tài khoản.
                </p>

            </label>

        </div>

    </div>


<div id="giftBankArea"
    style="
        display:
        <?= $giftMode === 'bank'
            ? 'block'
            : 'none' ?>;
    "
>


<!-- CHÚ RỂ -->

<div class="gift-person">

    <h3>
        💙 Tài khoản chú rể
    </h3>


    <div class="grid-2">


        <div class="form-group">

            <label>
                Tên hiển thị
            </label>

            <input
                type="text"
                name="groom_gift_name"
                value="<?= e(
                    $groomGift['person_name']
                    ?? $groomName
                ) ?>"
            >

        </div>


        <div class="form-group">

            <label>
                Ngân hàng
            </label>

            <input
                type="text"
                name="groom_bank_name"
                value="<?= e(
                    $groomGift['bank_name']
                    ?? ''
                ) ?>"
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
                    $groomGift['account_number']
                    ?? ''
                ) ?>"
            >

        </div>


        <div class="form-group">

            <label>
                Tên chủ tài khoản
            </label>

            <input
                type="text"
                name="groom_account_name"
                value="<?= e(
                    $groomGift['account_name']
                    ?? ''
                ) ?>"
            >

        </div>


    </div>


<?php if (
    !empty(
        $groomGift['qr_image']
        ?? ''
    )
): ?>

    <div class="qr-preview">

        <p>
            QR hiện tại:
        </p>

        <img
            src="<?= e(
                $groomGift['qr_image']
            ) ?>"
            alt="QR chú rể"
        >

    </div>

<?php endif; ?>


    <div class="form-group">

        <label>
            Thay QR chú rể
        </label>

        <input
            type="file"
            name="groom_qr_image"
            accept="image/jpeg,image/png,image/webp"
        >

    </div>

</div>


<!-- CÔ DÂU -->

<div class="gift-person">

    <h3>
        💗 Tài khoản cô dâu
    </h3>


    <div class="grid-2">


        <div class="form-group">

            <label>
                Tên hiển thị
            </label>

            <input
                type="text"
                name="bride_gift_name"
                value="<?= e(
                    $brideGift['person_name']
                    ?? $brideName
                ) ?>"
            >

        </div>


        <div class="form-group">

            <label>
                Ngân hàng
            </label>

            <input
                type="text"
                name="bride_bank_name"
                value="<?= e(
                    $brideGift['bank_name']
                    ?? ''
                ) ?>"
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
                    $brideGift['account_number']
                    ?? ''
                ) ?>"
            >

        </div>


        <div class="form-group">

            <label>
                Tên chủ tài khoản
            </label>

            <input
                type="text"
                name="bride_account_name"
                value="<?= e(
                    $brideGift['account_name']
                    ?? ''
                ) ?>"
            >

        </div>


    </div>


<?php if (
    !empty(
        $brideGift['qr_image']
        ?? ''
    )
): ?>

    <div class="qr-preview">

        <p>
            QR hiện tại:
        </p>

        <img
            src="<?= e(
                $brideGift['qr_image']
            ) ?>"
            alt="QR cô dâu"
        >

    </div>

<?php endif; ?>


    <div class="form-group">

        <label>
            Thay QR cô dâu
        </label>

        <input
            type="file"
            name="bride_qr_image"
            accept="image/jpeg,image/png,image/webp"
        >

    </div>

</div>

</div>


<!-- LỜI NHẮN -->

<div
    id="giftMessageArea"
    style="
        display:
        <?= $giftMode === 'message'
            ? 'block'
            : 'none' ?>;
    "
>

    <div class="gift-person">

        <h3>
            💌 Lời nhắn
        </h3>

        <div class="form-group">

            <label>
                Nội dung
            </label>

            <textarea
                name="gift_message"
                placeholder="Sự hiện diện của bạn..."
            ><?= e(
                $messageGift['gift_message']
                ??
                'Sự hiện diện của bạn là niềm vui của gia đình chúng tôi.'
            ) ?></textarea>

        </div>

    </div>

</div>


</section>


<!-- =================================================
     NÚT LƯU
================================================== -->

<div class="save-area">

    <button
        type="submit"
        class="save-button"
    >
        💾 LƯU THAY ĐỔI
    </button>


   <a
    href="../w/?slug=<?= urlencode(
        $wedding['slug']
    ) ?>&preview=1&token=<?= urlencode(
        $token
    ) ?>"
    target="_blank"
    class="preview-button"
>
    👁 XEM TRƯỚC
</a>

</div>


<?php if ((int)$wedding['status'] !== 1): ?>

    <button
        type="submit"
        name="action"
        value="submit_review"
        class="submit-review-button"
        onclick="
            return confirm(
                'Cậu có chắc muốn gửi thiệp này cho Admin duyệt không?'
            );
        "
    >
        📤 GỬI CHỜ DUYỆT
    </button>

<?php endif; ?>

</form>


</div>


<script>

/* =====================================================
   SỰ KIỆN
===================================================== */

function addEvent()
{

    const list =
        document.getElementById(
            'eventList'
        );


    const box =
        document.createElement(
            'div'
        );


    box.className =
        'event-box';


    box.innerHTML = `

        <div class="event-header">

            <strong>
                Sự kiện
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
            >

        </div>


        <div class="form-group">

            <label>
                Địa chỉ
            </label>

            <input
                type="text"
                name="event_address[]"
            >

        </div>


        <div class="form-group">

            <label>
                Mô tả
            </label>

            <textarea
                name="event_description[]"
            ></textarea>

        </div>

    `;


    list.appendChild(
        box
    );


    renumberEvents();
}


/* =====================================================
   XÓA SỰ KIỆN
===================================================== */

function removeEvent(button)
{

    const list =
        document.getElementById(
            'eventList'
        );


    const boxes =
        list.querySelectorAll(
            '.event-box'
        );


    if (boxes.length <= 1) {

        alert(
            'Phải giữ lại ít nhất một sự kiện.'
        );

        return;
    }


    button
        .closest('.event-box')
        .remove();


    renumberEvents();
}


/* =====================================================
   ĐÁNH SỐ SỰ KIỆN
===================================================== */

function renumberEvents()
{

    const boxes =
        document.querySelectorAll(
            '.event-box'
        );


    boxes.forEach(
        (box, index) => {

            const title =
                box.querySelector(
                    '.event-header strong'
                );


            if (title) {

                title.textContent =
                    'Sự kiện ' +
                    (index + 1);
            }

        }
    );
}


/* =====================================================
   GIFT MODE
===================================================== */

function updateGiftMode()
{

    const mode =
        document.querySelector(
            'input[name="gift_mode"]:checked'
        );


    if (!mode) {
        return;
    }


    const bankArea =
        document.getElementById(
            'giftBankArea'
        );


    const messageArea =
        document.getElementById(
            'giftMessageArea'
        );


    if (
        mode.value === 'bank'
    ) {

        bankArea.style.display =
            'block';

        messageArea.style.display =
            'none';

    } else {

        bankArea.style.display =
            'none';

        messageArea.style.display =
            'block';
    }
}


document
    .querySelectorAll(
        'input[name="gift_mode"]'
    )
    .forEach(
        radio => {

            radio.addEventListener(
                'change',
                updateGiftMode
            );

        }
    );


</script>


</body>

</html>