
<?php

/*
|--------------------------------------------------------------------------
| WEDDING ĐÃ ĐƯỢC w/index.php TÌM VÀ KIỂM TRA
|--------------------------------------------------------------------------
| w/index.php đã xử lý:
| - Public: slug + status = 1
| - Preview: slug + edit_token
|
| Template chỉ sử dụng biến $wedding đã được truyền vào.
|--------------------------------------------------------------------------
*/

if (!isset($wedding) || !$wedding) {
    die("Không tìm thấy thông tin đám cưới.");
}
/* =========================================================
   HỘP QUÀ MỪNG
========================================================= */

$giftMode = 'bank';

$groomGift = null;
$brideGift = null;
$giftMessage = '';

$giftStmt = $pdo->prepare("
    SELECT *
    FROM wedding_gifts
    WHERE wedding_id = :wedding_id
    ORDER BY id ASC
");

$giftStmt->execute([
    'wedding_id' => $wedding['id']
]);

$giftRows = $giftStmt->fetchAll();

foreach ($giftRows as $gift) {

    if (
        !empty($gift['gift_mode']) &&
        in_array(
            $gift['gift_mode'],
            ['bank', 'message'],
            true
        )
    ) {

        $giftMode = $gift['gift_mode'];

    }


    if (
        $gift['gift_mode'] === 'message'
    ) {

        $giftMessage =
            $gift['gift_message'] ?? '';

    }


    if (
        $gift['gift_mode'] === 'bank' &&
        $gift['person_type'] === 'groom'
    ) {

        $groomGift = $gift;

    }


    if (
        $gift['gift_mode'] === 'bank' &&
        $gift['person_type'] === 'bride'
    ) {

        $brideGift = $gift;

    }

}


/*
 * Nếu chưa có lời nhắn thì dùng câu mặc định.
 */
if ($giftMessage === '') {

    $giftMessage =
        'Sự hiện diện của bạn là niềm vui của gia đình chúng tôi.';

}
/*
|--------------------------------------------------------------------------
| THÔNG TIN CƠ BẢN
|--------------------------------------------------------------------------
*/

$groomName = $wedding['groom_name'];
$brideName = $wedding['bride_name'];

$venue = $wedding['venue'] ?? '';
$address = $wedding['address'] ?? '';

$story = $wedding['story'] ?? '';

$partyTime = $wedding['wedding_time'] ?? '';

$partyDate = $wedding['wedding_date'] ?? '';

$backgroundImage = $wedding['background_image'] ?? '';

$music = $wedding['music'] ?? '';
// ======================================================
// LẤY THÔNG TIN BỐ MẸ HAI BÊN
// ======================================================

$parents = [
    'groom' => [
        'father' => '',
        'mother' => ''
    ],
    'bride' => [
        'father' => '',
        'mother' => ''
    ]
];

$parentStmt = $pdo->prepare("
    SELECT side, parent_role, parent_name
    FROM wedding_parents
    WHERE wedding_id = :wedding_id
    ORDER BY id ASC
");

$parentStmt->execute([
    'wedding_id' => $wedding['id']
]);

$dbParents = $parentStmt->fetchAll();

foreach ($dbParents as $parent) {

    $side = $parent['side'];
    $role = $parent['parent_role'];

    if (
        isset($parents[$side]) &&
        isset($parents[$side][$role])
    ) {
        $parents[$side][$role] = $parent['parent_name'];
    }
}


// ======================================================
// LẤY LỊCH TRÌNH ĐÁM CƯỚI
// ======================================================

$eventStmt = $pdo->prepare("
    SELECT *
    FROM wedding_events
    WHERE wedding_id = :wedding_id
    ORDER BY sort_order ASC, id ASC
");

$eventStmt->execute([
    'wedding_id' => $wedding['id']
]);

$weddingEvents = $eventStmt->fetchAll();
/* =====================================================
   WEDDING GIFT
===================================================== */



$giftStmt->execute([
    'wedding_id' => $wedding['id']
]);

$weddingGifts = $giftStmt->fetchAll();


/*
 * Mặc định sử dụng chế độ ngân hàng
 */
$giftMode = 'bank';

$giftMessage =
    'Sự hiện diện của bạn là niềm vui của gia đình chúng tôi.';


/*
 * Lấy chế độ từ dữ liệu quà mừng
 *
 * Nếu có nhiều dòng:
 * chỉ cần dòng đầu tiên xác định chế độ
 */
if (!empty($weddingGifts)) {

    $firstGift = $weddingGifts[0];

    if (
        !empty($firstGift['gift_mode']) &&
        in_array(
            $firstGift['gift_mode'],
            ['bank', 'message'],
            true
        )
    ) {

        $giftMode =
            $firstGift['gift_mode'];

    }


    if (
        !empty($firstGift['gift_message'])
    ) {

        $giftMessage =
            $firstGift['gift_message'];

    }

}
/*
|--------------------------------------------------------------------------
| XỬ LÝ NGÀY CƯỚI
|--------------------------------------------------------------------------
*/

$dateObject = null;

if (!empty($wedding['wedding_date'])) {

    try {

        $dateObject = new DateTime($wedding['wedding_date']);

    } catch (Exception $e) {

        $dateObject = null;

    }
}


if ($dateObject) {

    $day = $dateObject->format('d');
    $month = $dateObject->format('m');
    $year = $dateObject->format('Y');

    $weddingDateShort =
        $dateObject->format('d.m.Y');

    $weddingDate =
        $day . " tháng " .
        $month . ", " .
        $year;

} else {

    $day = '';
    $month = '';
    $year = '';

    $weddingDate = '';
    $weddingDateShort = '';

}


/*
|--------------------------------------------------------------------------
| TẠM THỜI
|--------------------------------------------------------------------------
| Các thông tin này chưa có cột riêng trong bảng weddings.
| Sau này mình sẽ đưa chúng vào database.
|--------------------------------------------------------------------------
*/



$ceremonyTime = $partyTime;
$ceremonyDate = $weddingDate;

$phoneGroom = "";
$phoneBride = "";

$storyTitle = "Chuyện tình của chúng mình";


/*
|--------------------------------------------------------------------------
| ALBUM ẢNH
|--------------------------------------------------------------------------
| Lấy ảnh từ wedding_photos.
| Nếu chưa có ảnh trong database thì dùng ảnh mẫu hiện tại.
|--------------------------------------------------------------------------
*/

$photoStmt = $pdo->prepare("
    SELECT image_path
    FROM wedding_photos
    WHERE wedding_id = :wedding_id
    ORDER BY sort_order ASC, id ASC
");

$photoStmt->execute([
    'wedding_id' => $wedding['id']
]);

$dbPhotos = $photoStmt->fetchAll();


$album = [];

foreach ($dbPhotos as $photo) {

    if (!empty($photo['image_path'])) {

        $album[] = $photo['image_path'];

    }

}


/*
|--------------------------------------------------------------------------
| NẾU CHƯA CÓ ẢNH DATABASE → DÙNG ẢNH MẪU
|--------------------------------------------------------------------------
*/

if (empty($album)) {

   $album = [
    "/WeddingProject/templates/template01/assets/images/album1.jpg",
    "/WeddingProject/templates/template01/assets/images/album2.jpg",
    "/WeddingProject/templates/template01/assets/images/album3.jpg",
    "/WeddingProject/templates/template01/assets/images/album4.jpg",
    "/WeddingProject/templates/template01/assets/images/album5.jpg",
    "/WeddingProject/templates/template01/assets/images/album6.jpg",
    "/WeddingProject/templates/template01/assets/images/album7.jpg",
    "/WeddingProject/templates/template01/assets/images/album8.jpg",
];
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($groomName) ?>
        &
        <?= htmlspecialchars($brideName) ?>
    </title>

    <link rel="stylesheet" href="/WeddingProject/templates/template01/style.css">
</head>

<body>

<!-- =========================================================
     FLOATING CHỮ HỶ
========================================================= -->

<div class="floating-hy">
    <span>囍</span>
    <span>囍</span>
    <span>囍</span>
    <span>囍</span>
    <span>囍</span>
    <span>囍</span>
    <span>囍</span>
    <span>囍</span>
    <span>囍</span>
    <span>囍</span>
    <span>囍</span>
    <span>囍</span>
</div>


<!-- =========================================================
     INTRO / OPENING
========================================================= -->

<section id="intro" class="intro">

    <!-- họa tiết mây -->
    <img
        class="intro-cloud cloud-left"
        src="https://chungdoi.com/images/themes/longphung-v2-red/cloud.webp"
        alt=""
    >

    <img
        class="intro-cloud cloud-right"
        src="https://chungdoi.com/images/themes/longphung-v2-red/cloud.webp"
        alt=""
    >

    <!-- Phượng -->
    <img
        class="intro-phuong"
        src="https://chungdoi.com/images/themes/longphung-v2-red/phuong.webp"
        alt=""
    >

    <!-- Rồng -->
    <img
        class="intro-rong"
        src="https://chungdoi.com/images/themes/longphung-v2-red/rong.webp"
        alt=""
    >

    <!-- khung chính -->
    <div class="intro-card">

        <div class="intro-border"></div>

        <div class="intro-hy">囍</div>

        <div class="intro-small-title">
            TRÂN TRỌNG BÁO TIN
        </div>

        <h1 class="couple-name">
            <span><?= htmlspecialchars($groomName) ?></span>
            <b>&</b>
            <span><?= htmlspecialchars($brideName) ?></span>
        </h1>

        <div class="intro-divider">
            <span></span>
            <i>❦</i>
            <span></span>
        </div>

        <div class="intro-date">
            <?= htmlspecialchars($weddingDate) ?>
        </div>

        <div class="intro-invite">
            Trân trọng kính mời
        </div>

        <div class="guest-name">
            Quý khách
        </div>

        <div class="intro-message">
            đến tham dự buổi tiệc chung vui
            cùng gia đình chúng tôi
        </div>

        <button id="openInvitation" class="open-button">
            <span>Mở thiệp</span>
            <small>✦</small>
        </button>

    </div>

</section>


<!-- =========================================================
     MAIN WEBSITE
========================================================= -->

<main id="mainWedding" class="main-wedding">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <section class="section hero-section">

        <div class="decor decor-top">
            囍
        </div>

        <img
            class="section-cloud cloud-a"
            src="https://chungdoi.com/images/themes/longphung-v2-red/cloud.webp"
            alt=""
        >

        <div class="section-inner">

            <p class="small-red-title">
                Xin giới thiệu
            </p>

            <div class="hero-hy">
                囍
            </div>

            <h2>
                <?= htmlspecialchars($groomName) ?>
                <span>&</span>
                <?= htmlspecialchars($brideName) ?>
            </h2>

            <div class="gold-line"></div>

            <p class="hero-date">
                <?= htmlspecialchars($weddingDate) ?>
            </p>

            <p class="hero-text">
                Một ngày đặc biệt,
                một hành trình mới
                và một lời hứa cho cả đời.
            </p>

        </div>

        <img
            class="hero-dragon"
            src="https://chungdoi.com/images/themes/longphung-v2-red/rong.webp"
            alt=""
        >

    </section>


    <!-- =====================================================
         FAMILY
    ====================================================== -->

    <section class="section family-section">


<img
    class="decor-phuong"
    src="https://chungdoi.com/images/themes/longphung-v2-red/phuong.webp"
    alt=""
>

<div class="section-inner">

    <p class="section-label">
        THÔNG TIN LỄ CƯỚI
    </p>

    <h2 class="section-title">
        Gia đình hai bên
    </h2>

    <div class="gold-line"></div>

    <div class="family-grid">

        <!-- ================= NHÀ TRAI ================= -->

        <div class="family-box">

            <div class="family-symbol">龍</div>

            <h3>NHÀ TRAI</h3>

            <?php if (!empty($parents['groom']['father'])): ?>
                <p>
                    <?= htmlspecialchars($parents['groom']['father']) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($parents['groom']['mother'])): ?>
                <p>
                    <?= htmlspecialchars($parents['groom']['mother']) ?>
                </p>
            <?php endif; ?>

        </div>


        <!-- ================= BIỂU TƯỢNG GIỮA ================= -->

        <div class="family-center">
            <span>囍</span>
        </div>


        <!-- ================= NHÀ GÁI ================= -->

        <div class="family-box">

            <div class="family-symbol">鳳</div>

            <h3>NHÀ GÁI</h3>

            <?php if (!empty($parents['bride']['father'])): ?>
                <p>
                    <?= htmlspecialchars($parents['bride']['father']) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($parents['bride']['mother'])): ?>
                <p>
                    <?= htmlspecialchars($parents['bride']['mother']) ?>
                </p>
            <?php endif; ?>

        </div>

    </div>

</div>


</section>



    <!-- =====================================================
         COUPLE INFORMATION
    ====================================================== -->

    <section class="section couple-section">

        <img
            class="couple-cloud"
            src="https://chungdoi.com/images/themes/longphung-v2-red/cloud.webp"
            alt=""
        >

        <div class="section-inner">

            <p class="section-label">
                TRÂN TRỌNG BÁO TIN
            </p>

            <h2 class="section-title">
                Lễ Thành Hôn
            </h2>

            <div class="gold-line"></div>

            <p class="section-text">
                Sau một hành trình yêu thương,
                chúng mình quyết định cùng nhau
                bước sang một chặng đường mới.
            </p>

            <div class="names-large">

                <div>
                    <span><?= htmlspecialchars($groomName) ?></span>
                    <small>CHÚ RỂ</small>
                </div>

                <b>&</b>

                <div>
                    <span><?= htmlspecialchars($brideName) ?></span>
                    <small>CÔ DÂU</small>
                </div>

            </div>

            <div class="date-middle">

    <span>THÁNG</span>

    <strong>
        <?= htmlspecialchars($month) ?>
    </strong>

    <span>
        <?= htmlspecialchars($year) ?>
    </span>

</div>

<div>

    <small>NGÀY</small>

    <strong>
        <?= htmlspecialchars($day) ?>
    </strong>

</div>

        </div>

    </section>


    <!-- =====================================================
         STORY
    ====================================================== -->

    <section class="section story-section">

        <div class="section-inner">

            <p class="section-label">
                OUR STORY
            </p>

            <h2 class="section-title">
                <?= htmlspecialchars($storyTitle) ?>
            </h2>

            <div class="gold-line"></div>

            <div class="story-photo">

    <?php if (!empty($wedding['story_image'])): ?>

        <img
            src="<?= htmlspecialchars($wedding['story_image']) ?>"
            alt="Ảnh Chuyện tình của chúng mình"
        >

    <?php else: ?>

        <img
            src="/WeddingProject/templates/template01/assets/images/couple.jpg"
            alt="Ảnh kỷ niệm"
        >

    <?php endif; ?>

    <div class="photo-frame"></div>

</div>

            <?php if (!empty($story)): ?>

    <div class="story-text">

        <p>
            <?= nl2br(htmlspecialchars($story)) ?>
        </p>

    </div>

<?php else: ?>

    <div class="story-text">

        <p>
            Câu chuyện tình yêu của chúng mình.
        </p>

    </div>

<?php endif; ?>

        </div>

    </section>


    <!-- =====================================================
         ALBUM
    ====================================================== -->

    <section class="section album-section">


<div class="section-inner">

    <p class="section-label">
        OUR MEMORIES
    </p>

    <h2 class="section-title">
        Album Ảnh
    </h2>

    <div class="gold-line"></div>

    <div class="album-slider">

        <button
            class="album-arrow album-prev"
            type="button"
            aria-label="Ảnh trước"
        >
            ‹
        </button>


        <div class="album-track">

            <?php foreach ($album as $index => $photo): ?>

                <?php
                /*
                 * Nếu image_path trong database là:
                 * assets/images/abc.jpg
                 *
                 * thì tự thêm đường dẫn gốc của template.
                 *
                 * Nếu đã là đường dẫn tuyệt đối /WeddingProject/...
                 * thì giữ nguyên.
                 */

                $photoSrc = $photo;

                if (
                    !empty($photo) &&
                    strpos($photo, '/') !== 0 &&
                    strpos($photo, 'http://') !== 0 &&
                    strpos($photo, 'https://') !== 0
                ) {
                    $photoSrc = '/WeddingProject/templates/template01/' . ltrim($photo, '/');
                }
                ?>

                <div
                    class="album-item <?= $index === 0 ? 'active' : '' ?>"
                    data-index="<?= $index ?>"
                >

                    <img
                        src="<?= htmlspecialchars($photoSrc) ?>"
                        alt="Ảnh cưới <?= $index + 1 ?>"
                        loading="lazy"
                    >

                </div>

            <?php endforeach; ?>

        </div>


        <button
            class="album-arrow album-next"
            type="button"
            aria-label="Ảnh tiếp theo"
        >
            ›
        </button>

    </div>


    <div class="album-dots">

        <?php foreach ($album as $index => $photo): ?>

            <button
                type="button"
                data-slide="<?= $index ?>"
                class="<?= $index === 0 ? 'active' : '' ?>"
                aria-label="Xem ảnh <?= $index + 1 ?>"
            ></button>

        <?php endforeach; ?>

    </div>

</div>


</section>



    <!-- =====================================================
         WEDDING PARTY
    ====================================================== -->

    <section class="section party-section">

        <img
            class="party-dragon"
            src="https://chungdoi.com/images/themes/longphung-v2-red/rong.webp"
            alt=""
        >

        <div class="section-inner">

            <p class="section-label">
                WEDDING DAY
            </p>

            <h2 class="section-title">
                Thông Tin Tiệc Cưới
            </h2>

            <div class="gold-line"></div>

            <div class="event-card">

                <div class="event-icon">
                    囍
                </div>

                <h3>
                    TIỆC CƯỚI
                </h3>

                <strong>
                    <?= htmlspecialchars($partyTime) ?>
                </strong>

                <p>
                    <?= htmlspecialchars($partyDate) ?>
                </p>

                <div class="event-divider"></div>

                <h4>
                    <?= htmlspecialchars($venue) ?>
                </h4>

                <p>
                    <?= htmlspecialchars($address) ?>
                </p>

                <a
                    href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($address) ?>"
                    target="_blank"
                    class="map-button"
                >
                    Xem bản đồ
                </a>

            </div>

        </div>

    </section>




    <!-- =====================================================
         TIMELINE
    ====================================================== -->

    <section class="section timeline-section">


<div class="section-inner">

    <p class="section-label">
        WEDDING SCHEDULE
    </p>

    <h2 class="section-title">
        Lịch Trình Ngày Cưới
    </h2>

    <div class="gold-line"></div>

    <div class="timeline">

        <?php if (!empty($weddingEvents)): ?>

            <?php
            $timelineIcons = ['♡', '囍', '✦', '♥', '❀', '✧'];
            $iconIndex = 0;
            ?>

            <?php foreach ($weddingEvents as $event): ?>

                <div class="timeline-item">

                    <div class="timeline-time">

                        <?php
                        if (!empty($event['event_time'])) {
                            echo date(
                                'H:i',
                                strtotime($event['event_time'])
                            );
                        }
                        ?>

                    </div>


                    <div class="timeline-content">

                        <div class="timeline-icon">
                            <?= $timelineIcons[$iconIndex % count($timelineIcons)] ?>
                        </div>

                        <h3>
                            <?= htmlspecialchars($event['event_name']) ?>
                        </h3>


                        <?php if (!empty($event['event_location'])): ?>

                            <p>
                                <strong>
                                    <?= htmlspecialchars($event['event_location']) ?>
                                </strong>
                            </p>

                        <?php endif; ?>


                        <?php if (!empty($event['event_address'])): ?>

                            <p>
                                <?= htmlspecialchars($event['event_address']) ?>
                            </p>

                        <?php endif; ?>


                        <?php if (!empty($event['description'])): ?>

                            <p>
                                <?= nl2br(
                                    htmlspecialchars($event['description'])
                                ) ?>
                            </p>

                        <?php endif; ?>

                    </div>

                </div>

                <?php $iconIndex++; ?>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="timeline-item">

                <div class="timeline-content">

                    <div class="timeline-icon">
                        囍
                    </div>

                    <h3>
                        Chưa có lịch trình
                    </h3>

                    <p>
                        Lịch trình ngày cưới sẽ được cập nhật sau.
                    </p>

                </div>

            </div>

        <?php endif; ?>

    </div>

</div>
</section>



    


    <!-- =====================================================
         GUESTBOOK
    ====================================================== -->

  <!-- =====================================================
     SỔ LƯU BÚT
===================================================== -->

<section class="section guestbook-section">

    <!-- họa tiết chìm phía sau -->
    <div class="guestbook-pattern"></div>

    <div class="section-inner guestbook-inner">

        <h2 class="guestbook-title">
            Sổ lưu bút
        </h2>

        <div class="guestbook-form">

            <input
                type="text"
                id="wishName"
                class="wish-name"
                placeholder="Nhập tên của bạn"
                maxlength="50"
            >

            <textarea
                id="wishMessage"
                class="wish-message"
                placeholder="Nhập lời chúc của bạn*"
                maxlength="500"
            ></textarea>

            <div class="wish-submit-row">

                <div class="wish-decoration">
                    囍
                </div>

                <button
                    type="button"
                    id="sendWish"
                    class="send-wish"
                >
                    GỬI LỜI CHÚC
                </button>

            </div>

        </div>


        <!-- DANH SÁCH LỜI CHÚC -->

        <div
            id="wishList"
            class="wish-list"
        ></div>

    </div>

</section>

    <!-- =====================================================
         GIFT
    ====================================================== -->

    <section class="section gift-section">

        <img
            class="gift-cloud"
            src="https://chungdoi.com/images/themes/longphung-v2-red/cloud.webp"
            alt=""
        >

        <div class="section-inner">

            <p class="section-label">
                WEDDING GIFT
            </p>

            <h2 class="section-title">
                Hộp Quà Mừng
            </h2>

            <div class="gold-line"></div>

            <p class="section-text">
                Nếu bạn muốn gửi lời chúc mừng
                đến cô dâu chú rể bằng một món quà,
                bạn có thể sử dụng QR bên dưới.
            </p>

            <button type="button" id="openGift" class="gift-button">

    <span class="gift-icon gift-box-icon">
        <span class="gift-lid"></span>
        <span class="gift-body"></span>
        <span class="gift-ribbon-v"></span>
        <span class="gift-ribbon-h"></span>
        <span class="gift-bow-left"></span>
        <span class="gift-bow-right"></span>
        <span class="gift-bow-center"></span>
    </span>

    <strong>Nhấn để mở</strong>

</button>

        </div>

    </section>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <section class="section footer-section">

        <div class="footer-decor">
            囍
        </div>

        <div class="section-inner">

            <h2>
                <?= htmlspecialchars($groomName) ?>
                &
                <?= htmlspecialchars($brideName) ?>
            </h2>

            <p>
                Cảm ơn bạn đã đến và chia sẻ
                khoảnh khắc đặc biệt này cùng chúng mình.
            </p>

            <div class="footer-date">
                <?= htmlspecialchars($weddingDateShort) ?>
            </div>

            <div class="footer-hy">
                囍
            </div>

        </div>

    </section>

</main>


<!-- =====================================================
     GIFT MODAL
===================================================== -->

<div id="giftModal" class="gift-modal">

    <div class="gift-overlay"></div>

    <div class="gift-box">

        <button
            id="closeGift"
            class="close-gift"
            type="button"
        >
            ×
        </button>

        <div class="gift-title">
            <span class="gift-title-icon"></span>

            <h1>Gửi quà mừng cưới</h1>

            <h4>
                <?= htmlspecialchars($groomName) ?>
                &
                <?= htmlspecialchars($brideName) ?>
        </h4>
        </div>


        <?php if ($giftMode === 'message'): ?>

            <!-- =========================================
                 CHẾ ĐỘ LỜI NHẮN
            ========================================== -->

            <div class="gift-message-mode">

                <div class="gift-message-icon">
                    💌
                </div>

                <p class="gift-message-text">
                    <?= nl2br(
                        htmlspecialchars($giftMessage)
                    ) ?>
                </p>

            </div>


        <?php else: ?>

            <!-- =========================================
                 CHẾ ĐỘ QR NGÂN HÀNG
            ========================================== -->

            <div class="qr-grid">

                <?php if ($groomGift): ?>

                    <div class="qr-item">

                        <h4>
                            <?= htmlspecialchars(
                                $groomGift['person_name']
                            ) ?>
                        </h4>

                        <?php if (!empty($groomGift['qr_image'])): ?>

                            <img 
    src="<?= htmlspecialchars($groomGift['qr_image']) ?>" 
    alt="QR mừng cưới chú rể" 
    class="gift-qr-image" 
>


                        <?php endif; ?>

                        <?php if (!empty($groomGift['bank_name'])): ?>

                            <p>
                                <?= htmlspecialchars(
                                    $groomGift['bank_name']
                                ) ?>
                            </p>

                        <?php endif; ?>

                        <?php if (!empty($groomGift['account_number'])): ?>

                            <p>
                                STK:
                                <?= htmlspecialchars(
                                    $groomGift['account_number']
                                ) ?>
                            </p>

                        <?php endif; ?>

                        <?php if (!empty($groomGift['account_name'])): ?>

                            <p>
                                <?= htmlspecialchars(
                                    $groomGift['account_name']
                                ) ?>
                            </p>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>


                <?php if ($brideGift): ?>

                    <div class="qr-item">

                        <h4>
                            <?= htmlspecialchars(
                                $brideGift['person_name']
                            ) ?>
                        </h4>

                        <?php if (!empty($brideGift['qr_image'])): ?>

                           <img 
    src="<?= htmlspecialchars($brideGift['qr_image']) ?>" 
    alt="QR mừng cưới cô dâu" 
    class="gift-qr-image" 
>

                        <?php endif; ?>

                        <?php if (!empty($brideGift['bank_name'])): ?>

                            <p>
                                <?= htmlspecialchars(
                                    $brideGift['bank_name']
                                ) ?>
                            </p>

                        <?php endif; ?>

                        <?php if (!empty($brideGift['account_number'])): ?>

                            <p>
                                STK:
                                <?= htmlspecialchars(
                                    $brideGift['account_number']
                                ) ?>
                            </p>

                        <?php endif; ?>

                        <?php if (!empty($brideGift['account_name'])): ?>

                            <p>
                                <?= htmlspecialchars(
                                    $brideGift['account_name']
                                ) ?>
                            </p>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>

        <?php endif; ?>

    </div>

</div>
        
<!-- =========================================================
     MUSIC BUTTON
========================================================= -->

<button
    id="musicButton"
    class="music-button"
    type="button"
>
    ♪
</button>

<audio
    id="weddingMusic"
    loop
>

    <source
        src="<?= htmlspecialchars(
            !empty($music)
                ? $music
                : '/WeddingProject/templates/template01/assets/music/music.mp3'
        ) ?>"
        type="audio/mpeg"
    >

</audio>

<script>
    const WEDDING_ID = <?= (int)$wedding['id'] ?>;
</script>
<script src="/WeddingProject/templates/template01/script.js"></script>

</body>
</html>