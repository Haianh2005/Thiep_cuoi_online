<?php
declare(strict_types=1);

/*
 * TEMPLATE 03 — Mộc trà
 *
 * Dữ liệu được nhận từ w/index.php qua biến $wedding.
 * Không tạo database riêng cho template02.
 */

if (!isset($wedding) || !is_array($wedding) || empty($wedding['id'])) {
    die('Không tìm thấy thông tin đám cưới.');
}

require_once __DIR__ . '/../../config/database.php';

function t2e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function t2date(?string $value, string $format = 'd.m.Y'): string
{
    if (!$value) return '';
    try {
        return (new DateTime($value))->format($format);
    } catch (Throwable $e) {
        return '';
    }
}

$weddingId = (int)$wedding['id'];

$groomName = trim((string)($wedding['groom_name'] ?? ''));
$brideName = trim((string)($wedding['bride_name'] ?? ''));
$weddingDate = (string)($wedding['wedding_date'] ?? '');
$weddingTime = trim((string)($wedding['wedding_time'] ?? ''));
$venue = trim((string)($wedding['venue'] ?? ''));
$address = trim((string)($wedding['address'] ?? ''));
$story = trim((string)($wedding['story'] ?? ''));
$storyImage = trim((string)($wedding['story_image'] ?? ''));
$backgroundImage = trim((string)($wedding['background_image'] ?? ''));
$music = trim((string)($wedding['music'] ?? ''));

$dateObject = null;
if ($weddingDate !== '') {
    try { $dateObject = new DateTime($weddingDate); } catch (Throwable $e) { $dateObject = null; }
}

$day = $dateObject ? $dateObject->format('d') : '';
$month = $dateObject ? $dateObject->format('m') : '';
$year = $dateObject ? $dateObject->format('Y') : '';
$dateShort = $dateObject ? $dateObject->format('d.m.Y') : '';
$dateLong = $dateObject ? $dateObject->format('d') . ' tháng ' . $dateObject->format('m') . ', ' . $dateObject->format('Y') : '';
$countdownTarget = $dateObject
    ? $dateObject->format('Y-m-d') . 'T' . ($weddingTime !== '' ? $weddingTime : '00:00:00')
    : '';

/* -------------------- PARENTS -------------------- */
$parents = [
    'groom' => ['father' => '', 'mother' => ''],
    'bride' => ['father' => '', 'mother' => ''],
];

$parentStmt = $pdo->prepare('SELECT side, parent_role, parent_name FROM wedding_parents WHERE wedding_id = :id ORDER BY id ASC');
$parentStmt->execute(['id' => $weddingId]);
foreach ($parentStmt->fetchAll() as $row) {
    $side = (string)($row['side'] ?? '');
    $role = (string)($row['parent_role'] ?? '');
    if (isset($parents[$side][$role])) {
        $parents[$side][$role] = (string)$row['parent_name'];
    }
}

/* -------------------- EVENTS -------------------- */
$eventStmt = $pdo->prepare('SELECT * FROM wedding_events WHERE wedding_id = :id ORDER BY sort_order ASC, id ASC');
$eventStmt->execute(['id' => $weddingId]);
$events = $eventStmt->fetchAll();

/* -------------------- PHOTOS -------------------- */
$photoStmt = $pdo->prepare('SELECT * FROM wedding_photos WHERE wedding_id = :id ORDER BY sort_order ASC, id ASC');
$photoStmt->execute(['id' => $weddingId]);
$photos = $photoStmt->fetchAll();

$album = [];
foreach ($photos as $photo) {
    // Chấp nhận cả hai tên cột thường gặp trong wedding_photos.
    $photoPath = trim((string)($photo['image_path'] ?? $photo['photo_path'] ?? ''));
    if ($photoPath !== '') $album[] = $photoPath;
}

/* -------------------- GIFTS -------------------- */
$giftStmt = $pdo->prepare('SELECT * FROM wedding_gifts WHERE wedding_id = :id ORDER BY id ASC');
$giftStmt->execute(['id' => $weddingId]);
$gifts = $giftStmt->fetchAll();

$giftMode = 'bank';
$giftMessage = 'Sự hiện diện của bạn là niềm vui của gia đình chúng mình.';
$groomGift = null;
$brideGift = null;

foreach ($gifts as $gift) {
    if (($gift['gift_mode'] ?? '') === 'message') {
        $giftMode = 'message';
        if (!empty($gift['gift_message'])) $giftMessage = (string)$gift['gift_message'];
    }
    if (($gift['gift_mode'] ?? '') === 'bank' && ($gift['person_type'] ?? '') === 'groom') {
        $groomGift = $gift;
    }
    if (($gift['gift_mode'] ?? '') === 'bank' && ($gift['person_type'] ?? '') === 'bride') {
        $brideGift = $gift;
    }
}

/*
 * Bộ ảnh SVG bên dưới chỉ là ảnh dự phòng để template luôn có bố cục đẹp
 * khi một bản demo chưa có album. Khi khách có ảnh thật, album tự dùng DB.
 */
if (!$album) {
    for ($i = 1; $i <= 6; $i++) {
        $album[] = '/WeddingProject/templates/template02/assets/demo/demo-' . $i . '.svg';
    }
}

$heroImage = $backgroundImage !== '' ? $backgroundImage : $album[0];
$storyImageSrc = $storyImage !== '' ? $storyImage : ($album[1] ?? $album[0]);
$musicSrc = $music !== '' ? $music : '';
$mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($address !== '' ? $address : $venue);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thiệp cưới - <?= htmlspecialchars($wedding['groom_name'] ?? 'Chú rể') ?> & <?= htmlspecialchars($wedding['bride_name'] ?? 'Cô dâu') ?></title>
    <!-- Google Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/WeddingProject/templates/template03/style.css">
    <style>
        /* BỐ CỤC RIÊNG SECTION 5 TEMPLATE03: luôn xếp thành 2 hàng */
        body.template03-page .profile-section-template03 .profile-couple-grid {
            display: flex !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 22px !important;
            width: 100% !important;
            max-width: 700px !important;
            margin: 0 auto !important;
        }
        body.template03-page .profile-section-template03 .profile-row,
        body.template03-page .profile-section-template03 .profile-row.bride-row,
        body.template03-page .profile-section-template03 .profile-row.groom-row {
            display: flex !important;
            flex-direction: row !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 18px !important;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 14px !important;
            box-sizing: border-box !important;
            background: #FAF8F5 !important;
        }
        body.template03-page .profile-section-template03 .profile-img {
            flex: 0 0 42% !important;
            width: 42% !important;
            max-width: 210px !important;
            min-width: 0 !important;
        }
        body.template03-page .profile-section-template03 .profile-img img {
            display: block !important;
            width: 100% !important;
            height: auto !important;
            aspect-ratio: 3 / 4 !important;
            object-fit: cover !important;
            border-radius: 12px !important;
        }
        body.template03-page .profile-section-template03 .profile-info {
            flex: 1 1 0 !important;
            width: auto !important;
            min-width: 0 !important;
            text-align: left !important;
            overflow-wrap: anywhere !important;
        }
        body.template03-page .profile-section-template03 .profile-info h3 {
            margin: 6px 0 0 !important;
            font-size: clamp(1.15rem, 4vw, 1.5rem) !important;
        }
        @media (max-width: 480px) {
            body.template03-page .profile-section-template03 .profile-couple-grid { gap: 14px !important; }
            body.template03-page .profile-section-template03 .profile-row { gap: 12px !important; padding: 10px !important; }
            body.template03-page .profile-section-template03 .profile-img { flex-basis: 42% !important; }
        }

        /* Album template03: dùng wedding_photos, không dùng ảnh chân dung cô dâu/chú rể */
        .album-carousel-section { overflow: hidden; padding: 54px 0 48px; }
        .album-heading { text-align: center; margin: 0 auto 25px; padding: 0 16px; }
        .album-kicker { display:block; color:#a58d72; font-size:10px; letter-spacing:3px; margin-bottom:8px; }
        .album-heading h2 { margin:0; color:#756653; font-family:'Playfair Display',serif; font-size:clamp(25px,5vw,36px); font-weight:400; }
        .album-heading p { color:#988b7b; font-size:12px; margin:10px 0 0; }
        .album-carousel { width:100%; overflow-x:auto; overflow-y:hidden; cursor:grab; touch-action:pan-x; scrollbar-width:none; overscroll-behavior-x:contain; padding:10px 0 18px; }
        .album-carousel::-webkit-scrollbar { display:none; }
        .album-carousel.is-dragging { cursor:grabbing; user-select:none; }
        .album-track { display:flex; align-items:center; gap:15px; width:max-content; padding:0 calc((100vw - min(68vw, 330px))/2); }
        .album-slide { display:block; flex:0 0 min(68vw,330px); width:min(68vw,330px); height:390px; padding:0; border:0; border-radius:13px; overflow:hidden; background:#e7dfd2; opacity:.62; transform:scale(.86); transition:transform .28s ease, opacity .28s ease, box-shadow .28s ease; box-shadow:0 5px 18px rgba(65,48,31,.08); cursor:pointer; }
        .album-slide img { display:block; width:100%; height:100%; object-fit:cover; pointer-events:none; }
        .album-slide.is-active { opacity:1; transform:scale(1); box-shadow:0 12px 30px rgba(65,48,31,.16); }
        .album-instruction { text-align:center; color:#a09587; font-size:11px; letter-spacing:.5px; }
        .album-instruction span { padding:0 8px; color:#8a7863; }
        .album-dots { display:flex; justify-content:center; align-items:center; gap:7px; margin-top:14px; }
        .album-dot { width:6px; height:6px; border:0; border-radius:50%; padding:0; background:#d6cbbb; cursor:pointer; transition:all .2s ease; }
        .album-dot.is-active { width:19px; border-radius:8px; background:#8a665b; }
        @media (max-width:600px) {
            .album-carousel-section { padding:40px 0 36px; }
            .album-track { gap:10px; padding-left:calc((100vw - 68vw)/2); padding-right:calc((100vw - 68vw)/2); }
            .album-slide { flex-basis:68vw; width:68vw; height:310px; }
        }
        @media (prefers-reduced-motion:reduce) { .album-slide,.album-dot { transition:none; } }
    </style>
</head>
<body class="template03-page">

    <!-- Nhạc nền -->
    <?php if (!empty($wedding['music'])): ?>
        <audio id="wedding-audio" loop>
            <source src="<?= htmlspecialchars($wedding['music']) ?>" type="audio/mpeg">
        </audio>
        <div id="music-control" class="music-btn">
            <i class="fa-solid fa-music"></i>
        </div>
    <?php endif; ?>

    <!-- SECTION 1: HERO / MỞ ĐẦU -->
    <section class="hero-section" style="background-image: url('<?= htmlspecialchars($wedding['background_image'] ?? ($photos[0]['photo_path'] ?? '')) ?>');">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <div class="top-badge"><i class="fa-solid fa-infinity"></i></div>
            <p class="subtitle-handwriting">We get married!</p>
            <h1 class="couple-title">
                <?= htmlspecialchars($wedding['bride_name'] ?? 'Phương Nga') ?>
                <span class="amp">&</span>
                <?= htmlspecialchars($wedding['groom_name'] ?? 'Hoàng Long') ?>
            </h1>
            <p class="wedding-date-text"><?= date('d.m.Y', strtotime($wedding['wedding_date'] ?? '2025-12-20')) ?></p>
            <p class="subtitle-handwriting">We will become husband and wife in</p>
            
            <!-- Countdown -->
            <div class="countdown-box" data-date="<?= htmlspecialchars($wedding['wedding_date'] ?? '2025-12-20') ?> <?= htmlspecialchars($wedding['wedding_time'] ?? '10:30:00') ?>">
                <div class="time-item"><span id="days">00</span><p>ngày</p></div>
                <div class="time-item"><span id="hours">00</span><p>giờ</p></div>
                <div class="time-item"><span id="minutes">00</span><p>phút</p></div>
                <div class="time-item"><span id="seconds">00</span><p>giây</p></div>
            </div>
        </div>
    </section>

    <!-- SECTION 2: ALBUM ẢNH KÉO NGANG - TÁCH RIÊNG KHỎI ẢNH CHÂN DUNG -->
    <section class="photo-grid-section album-carousel-section" aria-label="Album ảnh cưới">
        <div class="container">
            <div class="album-heading">
                <span class="album-kicker">OUR MEMORIES</span>
                <h2>Album ảnh cưới</h2>
                <p>Kéo sang để xem những khoảnh khắc của chúng mình</p>
            </div>

            <div class="album-carousel" id="template03Album" tabindex="0" aria-label="Kéo ngang để xem album ảnh">
                <div class="album-track">
                    <?php foreach ($album as $index => $imagePath): ?>
                        <button class="album-slide<?= $index === 0 ? ' is-active' : '' ?>"
                                type="button"
                                data-index="<?= (int)$index ?>"
                                aria-label="Xem ảnh <?= (int)($index + 1) ?>">
                            <img src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="Ảnh cưới <?= (int)($index + 1) ?>"
                                 loading="<?= $index < 2 ? 'eager' : 'lazy' ?>">
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="album-instruction"><span>←</span> Vuốt hoặc kéo ảnh <span>→</span></div>
            <div class="album-dots" id="template03AlbumDots" aria-label="Chọn ảnh trong album">
                <?php foreach ($album as $index => $imagePath): ?>
                    <button type="button"
                            class="album-dot<?= $index === 0 ? ' is-active' : '' ?>"
                            data-index="<?= (int)$index ?>"
                            aria-label="Chuyển đến ảnh <?= (int)($index + 1) ?>"></button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- SECTION 3: SAVE THE DATE & GIA ĐÌNH -->
<section class="save-date-section">
    <div class="container text-center">
        <h2 class="section-main-title">SAVE <span class="light">the</span> DATE</h2>
        
        <div class="family-wrapper" style="display: flex; justify-content: space-around; flex-wrap: wrap;">
            <!-- Nhà gái -->
            <div class="family-column">
                <h3>Nhà gái</h3>
                <?php if (!empty($parents['bride']['father'])): ?>
                    <p><strong>Ông:</strong> <span><?= t2e($parents['bride']['father']) ?></span></p>
                <?php endif; ?>
                <?php if (!empty($parents['bride']['mother'])): ?>
                    <p><strong>Bà:</strong> <span><?= t2e($parents['bride']['mother']) ?></span></p>
                <?php endif; ?>
                
                <?php if (empty($parents['bride']['father']) && empty($parents['bride']['mother'])): ?>
                    <p><strong>Ông:</strong> Đặng Thái Công</p>
                    <p><strong>Bà:</strong> Hoàng Mai Hương</p>
                <?php endif; ?>
            
            </div>

            <div class="divider-line"></div>

            <!-- Nhà trai -->
            <div class="family-column">
                <h3>Nhà trai</h3>
                <?php if (!empty($parents['groom']['father'])): ?>
                    <p><strong>Ông:</strong> <span><?= t2e($parents['groom']['father']) ?></span></p>
                <?php endif; ?>
                <?php if (!empty($parents['groom']['mother'])): ?>
                    <p><strong>Bà:</strong> <span><?= t2e($parents['groom']['mother']) ?></span></p>
                <?php endif; ?>
                
                <?php if (empty($parents['groom']['father']) && empty($parents['groom']['mother'])): ?>
                    <p><strong>Ông:</strong> Bố Trai</p>
                    <p><strong>Bà:</strong> Mẹ Trai</p>
                <?php endif; ?>
                
            </div>
        </div>
    </div>
</section>
    <!-- SECTION 4: THỜI GIAN & ĐỊA ĐIỂM + GOOGLE MAP -->
    <section class="time-venue-section">
        <div class="container text-center">
            <p class="sub-org">Được tổ chức vào lúc</p>
            <h2 class="time-big"><?= htmlspecialchars($wedding['wedding_time'] ?? '10:30') ?> THỨ BẢY</h2>
            <h3 class="date-big"><?= date('d.m.Y', strtotime($wedding['wedding_date'] ?? '2025-12-20')) ?></h3>
            
            <!-- Calendar Widget -->
            <div class="calendar-card">
                <div class="calendar-header-dots"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
                <div class="calendar-grid"><?php 
                    $timestamp = strtotime($wedding['wedding_date'] ?? '2025-12-20');
                    $weddingDay = (int)date('d', $timestamp);
                    $weddingMonth = (int)date('m', $timestamp);
                    $weddingYear = (int)date('Y', $timestamp);
                    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $weddingMonth, $weddingYear);

                    for ($i = 1; $i <= $daysInMonth; $i++) {
                        $isActive = ($i === $weddingDay) ? ' class="active-date"' : '';
                        echo '<span' . $isActive . '>' . $i . '</span>';
                    }
                ?></div>
            </div>

            <div class="venue-details">
                <p class="venue-label">Địa điểm:</p>
                <h4><?= htmlspecialchars($wedding['venue'] ?? 'Tại tư gia nhà trai') ?></h4>
                <p><?= htmlspecialchars($wedding['address'] ?? 'Số 1 Lương Yên, Bạch Đằng, HN') ?></p>
                
                <a href="<?= htmlspecialchars($wedding['map_url'] ?? 'https://maps.google.com') ?>" target="_blank" class="map-btn">
                    <i class="fa-solid fa-map-location-dot"></i> Maps
                </a>
            </div>
        </div>
    </section>

    
<!-- SECTION 5: CÔ DÂU VÀ CHÚ RỂ -->
<section class="profile-section profile-section-template03">
    <div class="container">
        <div class="profile-couple-grid">
            <!-- CÔ DÂU: ẢNH TRÁI, TÊN PHẢI -->
            <div class="profile-row bride-row">
                <div class="profile-img">
                    <?php if (!empty($wedding['bride_photo'])): ?>
                        <img src="<?= htmlspecialchars($wedding['bride_photo'], ENT_QUOTES, 'UTF-8') ?>" alt="Ảnh cô dâu" loading="lazy">
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <span class="role-tag">CÔ DÂU</span>
                    <h3><?= htmlspecialchars($brideName ?: 'Cô dâu', ENT_QUOTES, 'UTF-8') ?></h3>
                </div>
            </div>

            <!-- CHÚ RỂ: TÊN TRÁI, ẢNH PHẢI -->
            <div class="profile-row groom-row">
                <div class="profile-info">
                    <span class="role-tag">CHÚ RỂ</span>
                    <h3><?= htmlspecialchars($groomName ?: 'Chú rể', ENT_QUOTES, 'UTF-8') ?></h3>
                </div>
                <div class="profile-img">
                    <?php if (!empty($wedding['groom_photo'])): ?>
                        <img src="<?= htmlspecialchars($wedding['groom_photo'], ENT_QUOTES, 'UTF-8') ?>" alt="Ảnh chú rể" loading="lazy">
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- SECTION 6: TIMELINE SỰ KIỆN -->
    <section class="timeline-section">
        <div class="container text-center">
            <h2 class="section-main-title">TIMELINE</h2>
            <div class="timeline-track">
                <div class="timeline-line"></div>
                <div class="timeline-items">
                    <?php if (!empty($events)): foreach($events as $ev): ?>
                        <div class="t-item">
                            <span class="t-dot"><i class="fa-solid fa-heart"></i></span>
                            <div class="t-content">
                                <span class="t-time"><?= htmlspecialchars($ev['event_time']) ?></span>
                                <h4><?= htmlspecialchars($ev['event_name']) ?></h4>
                            </div>
                        </div>
                    <?php endforeach; else: ?>
                        <div class="t-item"><span class="t-dot"><i class="fa-solid fa-heart"></i></span><div class="t-content"><span class="t-time">05:30</span><h4>Rước dâu</h4></div></div>
                        <div class="t-item"><span class="t-dot"><i class="fa-solid fa-heart"></i></span><div class="t-content"><span class="t-time">10:30</span><h4>Đón khách</h4></div></div>
                        <div class="t-item"><span class="t-dot"><i class="fa-solid fa-heart"></i></span><div class="t-content"><span class="t-time">12:00</span><h4>Lễ thành hôn</h4></div></div>
                        <div class="t-item"><span class="t-dot"><i class="fa-solid fa-heart"></i></span><div class="t-content"><span class="t-time">13:00</span><h4>Lưu niệm</h4></div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- SECTION 7: SỔ LƯU BÚT -->
    <section class="guestbook-section">
        <div class="container text-center">
            <span class="sub-tag">GUESTBOOK</span>
            <h2 class="section-main-title">Sổ lưu bút</h2>
            <div class="star-icon"><i class="fa-solid fa-star"></i></div>
            <p class="gb-desc">Một lời chúc nhỏ của bạn sẽ ở lại cùng chúng mình thật lâu.</p>
            
            <form id="wishForm" class="wish-form">
                <input type="text" name="name" placeholder="TÊN CỦA BẠN" required>
                <textarea name="message" rows="4" placeholder="LỜI CHÚC" required></textarea>
                <button type="submit" class="submit-btn">Gửi lời chúc</button>
            </form>

            <div id="wishesList" class="wishes-list">
                <!-- Danh sách lời chúc load qua ajax / api/wishes.php -->
            </div>
        </div>
    </section>

    <!-- SECTION 8: HỘP QUÀ MỪNG CƯỚI -->
    <section class="gift-section gift-section-template03 text-center">
        <div class="container">
            <span class="gift-section-kicker">A LITTLE GIFT</span>
            <h2 class="gift-section-heading">Hộp quà mừng</h2>
            <p class="gift-section-desc">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>

            <button id="openGiftModal" class="gift-box-btn gift-box-btn-vintage" type="button" aria-label="Mở hộp quà mừng">
                <span class="gift-illustration" aria-hidden="true">
                    <svg viewBox="0 0 140 130" role="img" xmlns="http://www.w3.org/2000/svg">
                        <g fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M26 48 L70 36 L114 48 L70 61 Z" fill="#efe2cf"/>
                            <path d="M26 48 V94 L70 111 V61 Z" fill="#f8f0e4"/>
                            <path d="M70 61 L114 48 V94 L70 111 Z" fill="#e6d3ba"/>
                            <path d="M64 39 C43 35 40 20 49 17 C60 14 69 30 70 38 C73 27 87 13 96 19 C105 27 87 39 76 41" fill="#f8f0e4"/>
                            <path d="M70 39 V107 M27 62 L70 77 L113 62" />
                            <path d="M64 41 L59 58 L70 66 L81 58 L76 41" fill="#d9c1a2"/>
                            <path d="M37 52 L37 89 M103 54 L103 88" opacity=".7"/>
                        </g>
                    </svg>
                </span>
                <span class="gift-box-label">Gửi quà mừng cưới</span>
                <span class="gift-box-subtitle">Nhấn để xem thông tin</span>
            </button>
            <p class="thank-you-text">Thank you for being with us</p>
        </div>
    </section>

    <?php
    // Lấy thông tin từng bên từ wedding_gifts.
    // Hỗ trợ một số tên cột phổ biến để tránh lỗi nếu schema dùng tên khác.
    $getGiftValue = static function (?array $gift, array $keys): string {
        if (!$gift) return '';
        foreach ($keys as $key) {
            if (isset($gift[$key]) && trim((string)$gift[$key]) !== '') {
                return trim((string)$gift[$key]);
            }
        }
        return '';
    };

    $groomQr = $getGiftValue($groomGift, ['qr_image', 'qr_image_path', 'qr_path', 'image_path']);
    $brideQr = $getGiftValue($brideGift, ['qr_image', 'qr_image_path', 'qr_path', 'image_path']);
    $groomBank = $getGiftValue($groomGift, ['bank_name', 'bank', 'banking_name']);
    $brideBank = $getGiftValue($brideGift, ['bank_name', 'bank', 'banking_name']);
    $groomAccount = $getGiftValue($groomGift, ['account_number', 'account_no', 'bank_account', 'stk']);
    $brideAccount = $getGiftValue($brideGift, ['account_number', 'account_no', 'bank_account', 'stk']);
    $groomAccountName = $getGiftValue($groomGift, ['account_name', 'account_holder', 'owner_name', 'account_holder_name']);
    $brideAccountName = $getGiftValue($brideGift, ['account_name', 'account_holder', 'owner_name', 'account_holder_name']);
    ?>

    <!-- MODAL QR MỪNG CƯỚI -->
    <div id="giftModal" class="modal-overlay gift-modal-overlay-template03" aria-hidden="true">
        <div class="modal-content gift-modal-template03" role="dialog" aria-modal="true" aria-labelledby="giftModalTitle">
            <div class="gift-modal-topbar">
                <h2 id="giftModalTitle">HỘP QUÀ MỪNG</h2>
                <button class="close-modal gift-modal-close" type="button" aria-label="Đóng hộp quà">&times;</button>
            </div>

            <div class="gift-modal-inner">
                <div class="gift-modal-grid">
                    <!-- Chú rể bên trái -->
                    <article class="gift-bank-card">
                        <p class="gift-person-name">
                            Chú Rể<?= $groomName !== '' ? ' - ' . t2e($groomName) : '' ?>
                        </p>
                        <div class="gift-qr-frame">
                            <?php if ($groomQr !== ''): ?>
                                <img src="<?= t2e($groomQr) ?>" alt="Mã QR mừng cưới chú rể">
                            <?php else: ?>
                                <div class="gift-qr-missing">Chưa có mã QR chú rể</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($groomBank !== ''): ?>
                            <p class="gift-bank-name"><?= t2e($groomBank) ?></p>
                        <?php endif; ?>
                        <?php if ($groomAccount !== ''): ?>
                            <p class="gift-account-number"><?= t2e($groomAccount) ?></p>
                        <?php endif; ?>
                        <?php if ($groomAccountName !== ''): ?>
                            <p class="gift-account-owner"><?= t2e($groomAccountName) ?></p>
                        <?php endif; ?>
                        <?php if ($groomQr !== ''): ?>
                            <a class="gift-download-btn" href="<?= t2e($groomQr) ?>" download>
                                <i class="fa-solid fa-download"></i> Lưu QR
                            </a>
                        <?php endif; ?>
                    </article>

                    <!-- Cô dâu bên phải -->
                    <article class="gift-bank-card">
                        <p class="gift-person-name">
                            Cô Dâu<?= $brideName !== '' ? ' - ' . t2e($brideName) : '' ?>
                        </p>
                        <div class="gift-qr-frame">
                            <?php if ($brideQr !== ''): ?>
                                <img src="<?= t2e($brideQr) ?>" alt="Mã QR mừng cưới cô dâu">
                            <?php else: ?>
                                <div class="gift-qr-missing">Chưa có mã QR cô dâu</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($brideBank !== ''): ?>
                            <p class="gift-bank-name"><?= t2e($brideBank) ?></p>
                        <?php endif; ?>
                        <?php if ($brideAccount !== ''): ?>
                            <p class="gift-account-number"><?= t2e($brideAccount) ?></p>
                        <?php endif; ?>
                        <?php if ($brideAccountName !== ''): ?>
                            <p class="gift-account-owner"><?= t2e($brideAccountName) ?></p>
                        <?php endif; ?>
                        <?php if ($brideQr !== ''): ?>
                            <a class="gift-download-btn" href="<?= t2e($brideQr) ?>" download>
                                <i class="fa-solid fa-download"></i> Lưu QR
                            </a>
                        <?php endif; ?>
                    </article>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* HỘP QUÀ MỪNG CƯỚI — phong cách Mộc Trà, giới hạn trong template03 */
        body.template03-page .gift-section-template03 {
            padding: 58px 18px 48px;
            background: #f7f2e9;
            color: #6b5948;
        }
        body.template03-page .gift-section-kicker {
            display: block;
            margin-bottom: 9px;
            color: #a28b70;
            font-size: 10px;
            letter-spacing: 3px;
        }
        body.template03-page .gift-section-heading {
            margin: 0;
            color: #75604c;
            font: 400 clamp(26px, 5vw, 36px)/1.25 'Playfair Display', Georgia, serif;
        }
        body.template03-page .gift-section-desc {
            margin: 12px auto 25px;
            color: #958574;
            font-size: 12px;
            line-height: 1.8;
        }
        body.template03-page .gift-box-btn-vintage {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 7px;
            width: min(100%, 290px);
            margin: 0 auto;
            padding: 22px 24px 20px;
            color: #79634d;
            background: #fcfaf5;
            border: 1px solid #dfd0bc;
            border-radius: 5px;
            box-shadow: 0 8px 22px rgba(89, 68, 47, .07);
            cursor: pointer;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        body.template03-page .gift-box-btn-vintage:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 27px rgba(89, 68, 47, .12);
        }
        body.template03-page .gift-illustration {
            display: block;
            width: 116px;
            color: #846b51;
        }
        body.template03-page .gift-illustration svg {
            display: block;
            width: 100%;
            height: auto;
        }
        body.template03-page .gift-box-label {
            font: 400 20px/1.4 'Playfair Display', Georgia, serif;
        }
        body.template03-page .gift-box-subtitle {
            color: #a08c76;
            font-size: 11px;
        }
        body.template03-page .gift-section-template03 .thank-you-text {
            margin-top: 22px;
            color: #aa9884;
            font: italic 15px 'Playfair Display', Georgia, serif;
        }
        body.template03-page .gift-modal-overlay-template03 {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 14px;
            background: rgba(36, 31, 24, .48);
            overflow-y: auto;
        }
        body.template03-page .gift-modal-overlay-template03.is-open {
            display: flex;
        }
        body.template03-page .gift-modal-template03 {
            position: relative;
            width: min(100%, 650px);
            max-width: 650px;
            max-height: 92vh;
            margin: auto;
            padding: 0;
            overflow: auto;
            border: 1px solid #e3d5c4;
            border-radius: 5px;
            background: #fffaf6;
            box-shadow: 0 20px 60px rgba(35, 27, 19, .2);
        }
        body.template03-page .gift-modal-topbar {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 78px;
    padding: 17px 54px;
    background: #E8D9C5;
    color: #765A43;
    border-bottom: 1px solid #D6C0A5;
}
        body.template03-page .gift-modal-topbar h2 {
            margin: 0;
            color: inherit;
            text-align: center;
            font: 400 clamp(20px, 4vw, 29px)/1.3 'Playfair Display', Georgia, serif;
            letter-spacing: .7px;
        }
        body.template03-page .gift-modal-close {
            position: absolute;
            top: 10px;
            right: 10px;
            display: grid;
            place-items: center;
            width: 42px;
            height: 42px;
            padding: 0;
            border: 2px solid #252817;
            border-radius: 50%;
            background: #eee9df;
            color: #777263;
            font-size: 29px;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 0 0 2px rgba(245, 239, 227, .45);
        }
        body.template03-page .gift-modal-inner {
            padding: 25px 22px 28px;
            background: #fffaf6;
        }
        body.template03-page .gift-modal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 28px;
            max-width: 540px;
            margin: 0 auto;
        }
        body.template03-page .gift-bank-card {
            min-width: 0;
            text-align: center;
            color: #4f4b31;
        }
        body.template03-page .gift-person-name {
            min-height: 24px;
            margin: 0 0 18px;
            color: #4f4b31;
            font-size: 12px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }
        body.template03-page .gift-qr-frame {
            display: flex;
            align-items: center;
            justify-content: center;
            width: min(100%, 178px);
            aspect-ratio: 1 / 1;
            margin: 0 auto 12px;
            padding: 10px;
            border: 1px solid #e6e3dc;
            border-radius: 13px;
            background: #fff;
            box-shadow: 0 7px 13px rgba(54, 48, 36, .12);
            box-sizing: border-box;
        }
        body.template03-page .gift-qr-frame img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        body.template03-page .gift-qr-missing {
            color: #a08d78;
            font-size: 11px;
            line-height: 1.6;
        }
        body.template03-page .gift-bank-name {
            margin: 0 0 5px;
            color: #8c8068;
            font-size: 10px;
        }
        body.template03-page .gift-account-number {
            margin: 0 0 5px;
            color: #49442c;
            font-size: 10px;
            overflow-wrap: anywhere;
        }
        body.template03-page .gift-account-owner {
            margin: 0 0 8px;
            color: #4f4b31;
            font-size: 11px;
            overflow-wrap: anywhere;
        }
        body.template03-page .gift-download-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 3px;
            padding: 7px 13px;
            border: 0;
            border-radius: 20px;
            background: #efede5;
            color: #494a30;
            font-size: 10px;
            text-decoration: none;
            transition: background .2s ease;
        }
        body.template03-page .gift-download-btn:hover {
            background: #e2dccd;
        }
        @media (max-width: 520px) {
            body.template03-page .gift-modal-overlay-template03 { padding: 8px; }
            body.template03-page .gift-modal-template03 { max-height: 94vh; }
            body.template03-page .gift-modal-topbar { min-height: 66px; padding: 14px 48px; }
            body.template03-page .gift-modal-close { top: 8px; right: 8px; width: 36px; height: 36px; font-size: 25px; }
            body.template03-page .gift-modal-inner { padding: 22px 12px; }
            body.template03-page .gift-modal-grid { gap: 12px; }
            body.template03-page .gift-person-name { min-height: 38px; margin-bottom: 12px; font-size: 11px; }
            body.template03-page .gift-qr-frame { padding: 7px; border-radius: 10px; }
            body.template03-page .gift-bank-name,
            body.template03-page .gift-account-number { font-size: 9px; }
            body.template03-page .gift-account-owner { font-size: 10px; }
        }
    </style>

    <script>
    (() => {
        const modal = document.getElementById('giftModal');
        const openButton = document.getElementById('openGiftModal');
        if (!modal || !openButton) return;

        const closeButton = modal.querySelector('.gift-modal-close');
        const openModal = () => {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        };
        const closeModal = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        openButton.addEventListener('click', openModal);
        if (closeButton) closeButton.addEventListener('click', closeModal);
        modal.addEventListener('click', event => {
            if (event.target === modal) closeModal();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
        });
    })();
    </script>

    <script src="/WeddingProject/templates/template03/script.js"></script>
    <script>
    (() => {
        const carousel = document.getElementById('template03Album');
        if (!carousel) return;
        const slides = Array.from(carousel.querySelectorAll('.album-slide'));
        const dots = Array.from(document.querySelectorAll('#template03AlbumDots .album-dot'));
        if (!slides.length) return;

        let activeIndex = 0;
        let scrollTimer = null;
        let pointerDown = false;
        let pointerStartX = 0;
        let scrollStartX = 0;
        let moved = false;

        function setActive(index, center = true) {
            activeIndex = Math.max(0, Math.min(slides.length - 1, index));
            slides.forEach((slide, i) => {
                slide.classList.toggle('is-active', i === activeIndex);
                slide.setAttribute('aria-pressed', i === activeIndex ? 'true' : 'false');
            });
            dots.forEach((dot, i) => dot.classList.toggle('is-active', i === activeIndex));
            if (center) {
                const slide = slides[activeIndex];
                carousel.scrollTo({
                    left: slide.offsetLeft + slide.offsetWidth / 2 - carousel.clientWidth / 2,
                    behavior: 'smooth'
                });
            }
        }

        slides.forEach((slide, index) => {
            slide.addEventListener('click', () => {
                if (moved) { moved = false; return; }
                setActive(index);
            });
        });
        dots.forEach(dot => dot.addEventListener('click', () => setActive(Number(dot.dataset.index || 0))));

        carousel.addEventListener('pointerdown', event => {
            if (event.pointerType === 'mouse' && event.button !== 0) return;
            pointerDown = true;
            moved = false;
            pointerStartX = event.clientX;
            scrollStartX = carousel.scrollLeft;
            carousel.classList.add('is-dragging');
            if (event.pointerType === 'mouse') carousel.setPointerCapture(event.pointerId);
        });
        carousel.addEventListener('pointermove', event => {
            if (!pointerDown) return;
            const delta = event.clientX - pointerStartX;
            if (Math.abs(delta) > 5) moved = true;
            if (moved) carousel.scrollLeft = scrollStartX - delta;
        });
        const endDrag = () => {
            if (!pointerDown) return;
            pointerDown = false;
            carousel.classList.remove('is-dragging');
            if (moved) {
                const center = carousel.scrollLeft + carousel.clientWidth / 2;
                let nearest = 0, nearestDistance = Infinity;
                slides.forEach((slide, i) => {
                    const distance = Math.abs(slide.offsetLeft + slide.offsetWidth / 2 - center);
                    if (distance < nearestDistance) { nearestDistance = distance; nearest = i; }
                });
                setActive(nearest);
                setTimeout(() => { moved = false; }, 80);
            }
        };
        carousel.addEventListener('pointerup', endDrag);
        carousel.addEventListener('pointercancel', endDrag);
        carousel.addEventListener('pointerleave', endDrag);
        carousel.addEventListener('scroll', () => {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(() => {
                const center = carousel.scrollLeft + carousel.clientWidth / 2;
                let nearest = 0, nearestDistance = Infinity;
                slides.forEach((slide, i) => {
                    const distance = Math.abs(slide.offsetLeft + slide.offsetWidth / 2 - center);
                    if (distance < nearestDistance) { nearestDistance = distance; nearest = i; }
                });
                setActive(nearest, false);
            }, 90);
        }, { passive: true });

        window.addEventListener('resize', () => setActive(activeIndex));
        requestAnimationFrame(() => setActive(0));
    })();
    </script>

</body>
</html>