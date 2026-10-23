<?php
/*
|--------------------------------------------------------------------------
| TEMPLATE 06 – TƠ DUYÊN XÁM
|--------------------------------------------------------------------------
| w/index.php đã tìm wedding theo slug và truyền vào $wedding (+ $pdo).
| Template chỉ ĐỌC dữ liệu và hiển thị. Không lưu gì.
| Cột tuỳ chọn (groom_photo, bride_photo, groom_bio, bride_bio, lunar_date,
| map_url, directions_url, ceremony_title): có thì dùng, chưa có thì tự ẩn.
|--------------------------------------------------------------------------
*/

if (!isset($wedding) || !$wedding) {
    die("Không tìm thấy thông tin đám cưới.");
}

/* ---------- helper ---------- */
if (!function_exists('t3e')) {
    function t3e($v) {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
/* Chuẩn hoá đường dẫn ảnh/nhạc: tuyệt đối hoặc http giữ nguyên,
   còn lại thêm /WeddingProject/ (tránh lặp tiền tố). */
if (!function_exists('t3url')) {
    function t3url($p) {
        $p = trim((string)($p ?? ''));
        if ($p === '') return '';
        if (preg_match('#^(https?:)?//#i', $p) || $p[0] === '/') return $p;
        return '/WeddingProject/' . ltrim($p, '/');
    }
}
if (!function_exists('t3short')) {
    function t3short($name) {
        $parts = preg_split('/\s+/u', trim((string)$name));
        $last = end($parts);
        return $last !== false && $last !== '' ? $last : (string)$name;
    }
}
if (!function_exists('t3first')) {
    function t3first($name) {
        $name = trim((string)$name);
        return $name === '' ? '?' : mb_strtoupper(mb_substr(t3short($name), 0, 1, 'UTF-8'), 'UTF-8');
    }
}

$base = '/WeddingProject/templates/template06/';

/* ---------- thông tin cơ bản ---------- */
$groomName = $wedding['groom_name'] ?? '';
$brideName = $wedding['bride_name'] ?? '';
$venue     = $wedding['venue'] ?? '';
$address   = $wedding['address'] ?? '';
$groomPhoto = t3url($wedding['groom_photo'] ?? '');
$bridePhoto = t3url($wedding['bride_photo'] ?? '');
$groomBio  = trim((string)($wedding['groom_bio'] ?? ''));
$brideBio  = trim((string)($wedding['bride_bio'] ?? ''));
$lunarDate = trim((string)($wedding['lunar_date'] ?? ''));
$music     = t3url($wedding['music'] ?? '');
$ceremonyTitle = trim((string)($wedding['ceremony_title'] ?? '')) ?: 'Lễ vu quy';
$shortGroom = t3short($groomName);
$shortBride = t3short($brideName);

/* ---------- ngày giờ ---------- */
$weekdayNames = ['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
$dateObject = null;
if (!empty($wedding['wedding_date'])) {
    try { $dateObject = new DateTime($wedding['wedding_date']); } catch (Exception $e) { $dateObject = null; }
}
$timeHi = '';
$timeAmPm = '';
$timeSql = '00:00:00';
if (!empty($wedding['wedding_time']) && strtotime($wedding['wedding_time']) !== false) {
    $ts = strtotime($wedding['wedding_time']);
    $timeHi = date('H:i', $ts);
    $timeAmPm = date('h:i A', $ts);
    $timeSql = date('H:i:s', $ts);
}
$day = $month = $year = $weekday = $dotDate = $targetIso = '';
$calCells = [];
$weddingDay = 0;
if ($dateObject) {
    $day = $dateObject->format('d');
    $month = $dateObject->format('m');
    $year = $dateObject->format('Y');
    $weekday = $weekdayNames[(int)$dateObject->format('w')];
    $dotDate = $dateObject->format('Y.m.d');
    $targetIso = $dateObject->format('Y-m-d') . 'T' . $timeSql;
    $weddingDay = (int)$dateObject->format('j');
    $first = new DateTime($dateObject->format('Y-m-01'));
    $offset = (int)$first->format('w');
    $dim = (int)$first->format('t');
    for ($i = 0; $i < $offset; $i++) $calCells[] = null;
    for ($d = 1; $d <= $dim; $d++) $calCells[] = $d;
}

/* ---------- album ---------- */
$photoStmt = $pdo->prepare("
    SELECT image_path FROM wedding_photos
    WHERE wedding_id = :wedding_id
    ORDER BY sort_order ASC, id ASC
");
$photoStmt->execute(['wedding_id' => $wedding['id']]);
$album = [];
foreach ($photoStmt->fetchAll() as $row) {
    $u = t3url($row['image_path'] ?? '');
    if ($u !== '') $album[] = $u;
}

/* ---------- ảnh nền / story ---------- */
$storyImage = t3url($wedding['story_image'] ?? '');
$bgImage = t3url($wedding['background_image'] ?? '');
if ($bgImage === '') $bgImage = $storyImage;
if ($bgImage === '' && !empty($album)) $bgImage = $album[0];
$inviteImage = $storyImage !== '' ? $storyImage : $bgImage;

/* ---------- bố mẹ ---------- */
$parents = [
    'groom' => ['father' => '', 'mother' => '', 'address' => ''],
    'bride' => ['father' => '', 'mother' => '', 'address' => ''],
];
$parentStmt = $pdo->prepare("SELECT * FROM wedding_parents WHERE wedding_id = :wedding_id ORDER BY id ASC");
$parentStmt->execute(['wedding_id' => $wedding['id']]);
foreach ($parentStmt->fetchAll() as $p) {
    $side = $p['side'] ?? '';
    $role = $p['parent_role'] ?? '';
    if (isset($parents[$side][$role])) {
        $parents[$side][$role] = $p['parent_name'] ?? '';
    }
    if (isset($parents[$side]) && !empty($p['address'])) {
        $parents[$side]['address'] = $p['address'];
    }
}

/* ---------- lịch trình ---------- */
$evStmt = $pdo->prepare("SELECT * FROM wedding_events WHERE wedding_id = :wedding_id ORDER BY sort_order ASC, id ASC");
$evStmt->execute(['wedding_id' => $wedding['id']]);
$events = $evStmt->fetchAll();

/* ---------- quà mừng ---------- */
$giftStmt = $pdo->prepare("SELECT * FROM wedding_gifts WHERE wedding_id = :wedding_id ORDER BY id ASC");
$giftStmt->execute(['wedding_id' => $wedding['id']]);
$giftMode = 'bank';
$giftMessage = '';
$groomGift = null;
$brideGift = null;
foreach ($giftStmt->fetchAll() as $g) {
    $mode = $g['gift_mode'] ?? '';
    if (in_array($mode, ['bank', 'message'], true) && $giftMode === 'bank' && $mode === 'message') {
        $giftMode = 'message';
    }
    if ($giftMessage === '' && !empty($g['gift_message'])) {
        $giftMessage = $g['gift_message'];
    }
    if ($mode !== 'message') {
        if (($g['person_type'] ?? '') === 'groom') $groomGift = $g;
        if (($g['person_type'] ?? '') === 'bride') $brideGift = $g;
    }
}
if ($giftMessage === '') {
    $giftMessage = 'Vợ chồng mình xin cảm ơn tất cả tình cảm của mọi người ạ ♥';
}

/* ---------- bản đồ ---------- */
$mapQuery = trim($address !== '' ? $address : $venue);
if ($address !== '' && $venue !== '') $mapQuery = $venue . ', ' . $address;
$mapEmbed = $mapQuery !== '' ? 'https://www.google.com/maps?q=' . urlencode($mapQuery) . '&output=embed&hl=vi' : '';
$mapOpen = !empty($wedding['map_url']) ? $wedding['map_url']
    : ($mapQuery !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($mapQuery) : '');
$dirUrl = !empty($wedding['directions_url']) ? $wedding['directions_url']
    : ($mapQuery !== '' ? 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($mapQuery) : '');

/* ---------- icon timeline (SVG line-art) ---------- */
$t3Icons = [
    'hand' => '<path d="M24 41C10 31 5 23 8 15c3-7 12-6 16 1 4-7 13-8 16-1 3 8-2 16-16 26z"/><path d="M13 24l8-4 5 3 3-2 6 3"/><path d="M19 28l4 3M23 25l4 3"/>',
    'ring' => '<path d="M7 12h8v24H7z"/><path d="M11 12V8"/><circle cx="29" cy="30" r="7"/><path d="M26 19l3-5 3 5-3 3z"/><path d="M10 38h30"/>',
    'camera' => '<rect x="5" y="14" width="38" height="26" rx="2"/><path d="M16 14l3-5h10l3 5"/><circle cx="24" cy="27" r="8"/><circle cx="24" cy="27" r="4"/><path d="M34 19h5"/>',
    'glass' => '<g transform="rotate(-16 15 26)"><path d="M10 16h10l-2 12a3 3 0 0 1-6 0z"/><path d="M15 31v8M11 39h8"/></g><g transform="rotate(16 33 26)"><path d="M28 16h10l-2 12a3 3 0 0 1-6 0z"/><path d="M33 31v8M29 39h8"/></g><path d="M23 5v5M20.500 7.500h5M38 7v4M36 9h4"/>',
];
$t3Cycle = ['hand', 'ring', 'camera', 'glass'];
function t3_icon_key($name, $idx, $cycle) {
    $n = mb_strtolower((string)$name, 'UTF-8');
    if (preg_match('/đón|khách|welcome/u', $n)) return 'hand';
    if (preg_match('/chụp|ảnh|photo/u', $n)) return 'camera';
    if (preg_match('/tiệc|khai|ăn|dinner|party/u', $n)) return 'glass';
    if (preg_match('/lễ|thành hôn|vu quy|rước|hỏi|nhẫn/u', $n)) return 'ring';
    return $cycle[$idx % count($cycle)];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t3e($groomName) ?> &amp; <?= t3e($brideName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Cormorant+Garamond:wght@500;600;700&family=EB+Garamond:wght@400;500;600;700&family=Montserrat:wght@500;600;700&family=Dancing+Script:wght@600;700&family=Great+Vibes&display=swap&subset=vietnamese" rel="stylesheet">
    <link rel="stylesheet" href="/WeddingProject/templates/template06/style.css">
    <noscript><style>.template06-page .t3-reveal{opacity:1!important;filter:none!important;transform:none!important}</style></noscript>
</head>
<body class="t3-body t3-locked">

<div class="template06-page"
     data-wedding-id="<?= (int)$wedding['id'] ?>"
     data-wish-api="/WeddingProject/api/wishes.php"
     data-target="<?= t3e($targetIso) ?>"
     data-short-groom="<?= t3e($shortGroom) ?>"
     data-short-bride="<?= t3e($shortBride) ?>">

    <!-- ============ 1. MỞ THIỆP ============ -->
    <section id="t3Opening" class="t3-opening" role="button" tabindex="0" aria-label="Mở thiệp"
        <?= $bgImage !== '' ? 'style="background-image:url(\'' . t3e($bgImage) . '\')"' : '' ?>>
        <div class="t3-opening-shade"></div>
        <h5 class="t3-opening-title"><span>Thiệp</span><span>mời</span></h5>
        <div class="t3-opening-name t3-left"><?= t3e($groomName) ?></div>
        <div class="t3-opening-name t3-right"><?= t3e($brideName) ?></div>
        <div class="t3-opening-hint">Chạm để mở thiệp</div>
    </section>
    <main id="t3Main" class="t3-main">
        <!-- ============ 2. INVITATION ============ -->
        <section class="t3-section t3-invitation">
    <p class="t3-label t3-reveal">INVITATION</p>
    <h2 class="t3-title t3-reveal" style="--d:.1s; font-family: var(--f-script); font-size: 46px; font-weight: 400;">Trân trọng kính mời</h2>
    <p class="t3-invite-text t3-reveal" style="--d:.2s; font-family: var(--f-serif); font-size: 20px; font-style: italic; line-height: 1.6;">
        Kính mời quý khách đến dự<br>
        lễ thành hôn của hai con<br>
        và cùng chia sẻ niềm hạnh phúc trọn vẹn nhất.
    </p>
    <?php if ($inviteImage !== ''): ?>
        <div class="t3-invite-photo t3-reveal" style="--d:.3s">
            <img src="<?= t3e($inviteImage) ?>" alt="Ảnh cưới" loading="lazy">
        </div>
    <?php endif; ?>
</section>
        <!-- ============ 3. THÔNG TIN LỄ CƯỚI ============ -->
        <section class="t3-section t3-info">
            <p class="t3-label t3-reveal">WEDDING INFO</p>
            <h2 class="t3-title t3-caps t3-reveal" style="--d:.1s">THÔNG TIN LỄ CƯỚI</h2>

            <div class="t3-families t3-reveal" style="--d:.2s">
                <?php foreach (['groom', 'bride'] as $sd): ?>
                    <div class="t3-family">
                        <span class="t3-family-label">Ông Bà</span>
                        <?php if ($parents[$sd]['father'] !== ''): ?><strong><?= t3e($parents[$sd]['father']) ?></strong><?php endif; ?>
                        <?php if ($parents[$sd]['mother'] !== ''): ?><strong><?= t3e($parents[$sd]['mother']) ?></strong><?php endif; ?>
                        <?php if ($parents[$sd]['address'] !== ''): ?><em><?= t3e($parents[$sd]['address']) ?></em><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="t3-announce t3-reveal">TRÂN TRỌNG BÁO TIN<br>LỄ THÀNH HÔN CỦA CON CHÚNG TÔI</p>

            <div class="t3-couple-names t3-reveal" style="--d:.1s; text-align: center;">
    <!-- Tên Chú Rể: Chữ nghiêng mềm mại, nét rõ dễ đọc -->
    <div class="t3-big-name" style="font-family: 'Playfair Display', 'Cormorant Garamond', serif; font-style: italic; font-weight: 600; font-size: clamp(26px, 6.5vw, 34px); line-height: 1.25; margin: 4px 0;">
        <?= t3e($groomName) ?>
    </div>
    
    <!-- Nhãn CHÚ RỂ: Giữ nguyên chữ in đứng -->
    <small style="font-family: sans-serif; font-style: normal; font-size: 11px; letter-spacing: .15em; font-weight: 600; opacity: 0.75;">CHÚ RỂ</small>
    
    <!-- Ký tự & -->
    <div class="t3-amp" style="font-family: 'Playfair Display', serif; font-style: italic; font-size: 22px; margin: 2px 0; opacity: 0.8;"><?= t3e('&') ?></div>
    
    <!-- Tên Cô Dâu: Chữ nghiêng mềm mại, nét rõ dễ đọc -->
    <div class="t3-big-name" style="font-family: 'Playfair Display', 'Cormorant Garamond', serif; font-style: italic; font-weight: 600; font-size: clamp(26px, 6.5vw, 34px); line-height: 1.25; margin: 4px 0;">
        <?= t3e($brideName) ?>
    </div>
    
    <!-- Nhãn CÔ DÂU: Giữ nguyên chữ in đứng -->
    <small style="font-family: sans-serif; font-style: normal; font-size: 11px; letter-spacing: .15em; font-weight: 600; opacity: 0.75;">CÔ DÂU</small>
</div>
        </section>

        <!-- ============ 4. PROFILE ============ -->
        <section class="t3-section t3-profile">
            
            <h1 class="t3-title t3-reveal" style="--d:.2s; font-family: var(--f-serif); font-size: 20px; font-style: italic; line-height: 1.6;">Chúng mình</h1>

            <div class="t3-person t3-person-groom t3-reveal" style="--d:.15s">
                <div class="t3-avatar">
                    <?php if ($groomPhoto !== ''): ?>
                        <img src="<?= t3e($groomPhoto) ?>" alt="Ảnh chú rể" loading="lazy">
                    <?php else: ?>
                        <span><?= t3e(t3first($groomName)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="t3-person-text">
                    <small>Chú rể</small>
                    <h3><?= t3e($groomName) ?></h3>
                    <?php if ($groomBio !== ''): ?><p><?= nl2br(t3e($groomBio)) ?></p><?php endif; ?>
                </div>
            </div>

            <div class="t3-person t3-person-bride t3-reveal" style="--d:.15s">
                <div class="t3-person-text">
                    <small>Cô dâu</small>
                    <h3><?= t3e($brideName) ?></h3>
                    <?php if ($brideBio !== ''): ?><p><?= nl2br(t3e($brideBio)) ?></p><?php endif; ?>
                </div>
                <div class="t3-avatar">
                    <?php if ($bridePhoto !== ''): ?>
                        <img src="<?= t3e($bridePhoto) ?>" alt="Ảnh cô dâu" loading="lazy">
                    <?php else: ?>
                        <span><?= t3e(t3first($brideName)) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($dateObject): ?>
        <!-- ============ 5. NGÀY LỄ ============ -->
        <section class="t3-section t3-dateblock">
            <p class="t3-label t3-reveal">WEDDING INVITATION</p>
            <h2 class="t3-title t3-reveal" style="--d:.1s">Trân trọng kính mời</h2>
            <p class="t3-ceremony t3-caps t3-reveal" style="--d:.2s"><?= t3e($ceremonyTitle) ?></p>
            <?php if ($timeHi !== ''): ?><p class="t3-time t3-reveal" style="--d:.25s"><?= t3e($timeHi) ?></p><?php endif; ?>
            <div class="t3-daterow t3-reveal" style="--d:.3s">
                <span class="t3-caps"><?= t3e($weekday) ?></span>
                <b><?= t3e($day) ?></b>
                <span class="t3-caps">THÁNG <?= t3e($month) ?></span>
            </div>
            <p class="t3-year t3-reveal"><?= t3e($year) ?></p>
            <?php if ($lunarDate !== ''): ?><p class="t3-lunar t3-reveal">(<?= t3e($lunarDate) ?>)</p><?php endif; ?>
        </section>

        <!-- ============ 6. LỊCH + COUNTDOWN ============ -->
        <section class="t3-section t3-calendar">
            <h2 class="t3-cal-date t3-reveal"><?= t3e($dotDate) ?></h2>
            <p class="t3-cal-sub t3-reveal" style="--d:.1s"><?= t3e($weekday) ?><?= $timeAmPm !== '' ? ', ' . t3e($timeAmPm) : '' ?></p>

            <div class="t3-cal t3-reveal" style="--d:.2s">
                <div class="t3-cal-head">
                    <span class="t3-sun">CN</span><span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span>
                </div>
                <div class="t3-cal-grid">
                    <?php foreach ($calCells as $i => $c): ?>
                        <?php if ($c === null): ?>
                            <span class="t3-cal-empty"></span>
                        <?php else: ?>
                            <span class="<?= ($i % 7 === 0 ? 't3-sun ' : '') . ($c === $weddingDay ? 't3-wedding-day' : '') ?>"><?= $c ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="t3-countdown t3-reveal" id="t3Countdown">
                <div><small>ngày</small><b data-cd="d">00</b></div><i>:</i>
                <div><small>giờ</small><b data-cd="h">00</b></div><i>:</i>
                <div><small>phút</small><b data-cd="m">00</b></div><i>:</i>
                <div><small>giây</small><b data-cd="s">00</b></div>
            </div>
            <p class="t3-cd-text t3-reveal" id="t3CdText">
                Còn <span data-cd="days-text">0</span> ngày nữa là đến đám cưới của <?= t3e($shortGroom) ?> và <?= t3e($shortBride) ?>.
            </p>
        </section>
        <?php endif; ?>

        <?php if (!empty($events)): ?>
        <!-- ============ 7. TIMELINE ============ -->
        <section class="t3-section t3-timeline-sec">
            <p class="t3-label t3-reveal">WEDDING TIMELINE</p>
            <h2 class="t3-title t3-reveal" style="--d:.1s">Lịch trình ngày cưới</h2>
            <div class="t3-timeline">
                <?php foreach ($events as $i => $ev):
                    $side = $i % 2 === 0 ? 'right' : 'left';
                    $key = t3_icon_key($ev['event_name'] ?? '', $i, $t3Cycle);
                    $evTime = !empty($ev['event_time']) && strtotime($ev['event_time']) !== false ? date('H:i', strtotime($ev['event_time'])) : '';
                ?>
                    <div class="t3-tl-item t3-tl-<?= $side ?> t3-reveal">
                        <div class="t3-tl-cell">
                            <span class="t3-tl-dash"></span>
                            <svg class="t3-tl-icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $t3Icons[$key] ?></svg>
                            <div class="t3-tl-text">
                                <?php if ($evTime !== ''): ?><small><?= t3e($evTime) ?></small><?php endif; ?>
                                <span><?= t3e($ev['event_name'] ?? '') ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($album)): ?>
        <!-- ============ 8. GALLERY ============ -->
        <section class="t3-section t3-gallery">
            <p class="t3-label t3-reveal">GALLERY</p>
            <h2 class="t3-title t3-reveal" style="--d:.1s">Khoảnh khắc của chúng mình</h2>
            <div class="t3-gal-main t3-reveal" id="t3GalMain" style="--d:.2s">
                <img id="t3GalImg" src="<?= t3e($album[0]) ?>" alt="Ảnh cưới 1">
            </div>
            <?php if (count($album) > 1): ?>
            <div class="t3-gal-thumbs" id="t3GalThumbs">
                <?php foreach ($album as $i => $src): ?>
                    <button type="button" class="t3-thumb<?= $i === 0 ? ' is-active' : '' ?>" data-src="<?= t3e($src) ?>" data-i="<?= $i ?>" aria-label="Ảnh <?= $i + 1 ?>">
                        <img src="<?= t3e($src) ?>" alt="" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($mapEmbed !== ''): ?>
        <!-- ============ 9. LOCATION ============ -->
        <section class="t3-section t3-location">
            <p class="t3-label t3-reveal">LOCATION</p>
            <h2 class="t3-title t3-reveal" style="--d:.1s">Địa điểm tổ chức</h2>
            <p class="t3-loc-title t3-reveal"><?= t3e($ceremonyTitle) ?></p>
            <p class="t3-loc-info t3-reveal">
                <?php if ($timeHi !== ''): ?>Thời gian: <?= t3e($timeHi) ?><br><?php endif; ?>
               
                <?php if ($venue !== '' || $address !== ''): ?>
                    <br><span class="t3-loc-addr"><?= t3e(trim($venue . ($venue !== '' && $address !== '' ? ' — ' : '') . $address)) ?></span>
                <?php endif; ?>
            </p>
            <div class="t3-map t3-reveal">
                <iframe src="<?= t3e($mapEmbed) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Bản đồ"></iframe>
            </div>
            <?php if ($dirUrl !== ''): ?>
                <a class="t3-dir-btn t3-reveal" href="<?= t3e($dirUrl) ?>" target="_blank" rel="noopener">Chỉ đường - <?= t3e($ceremonyTitle) ?></a>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- ============ 10. SỔ LƯU BÚT ============ -->
        <section class="t3-guestbook">
            <div class="t3-gb-inner">
                <h2 class="t3-gb-title t3-reveal">SỔ LƯU BÚT</h2>
                <div class="t3-gb-form t3-reveal" style="--d:.1s">
                    <input type="text" id="t3WishName" placeholder="Nhập tên*" maxlength="50" autocomplete="name">
                    <textarea id="t3WishMessage" placeholder="Nhập lời chúc*" maxlength="500"></textarea>
                    <div class="t3-gb-actions">
                        <button type="button" id="t3SendWish">GỬI LỜI CHÚC</button>
                    </div>
                </div>
                <div class="t3-wish-list" id="t3WishList"></div>
            </div>
        </section>

        <!-- ============ 11. HỘP QUÀ ============ -->
        <section class="t3-gift">
            <h2 class="t3-gift-title t3-reveal">Hộp quà mừng</h2>
            <p class="t3-gift-sub t3-reveal" style="--d:.1s">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>
            <button type="button" class="t3-gift-card t3-reveal" id="t3OpenGift" style="--d:.2s">
                <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="8" y="26" width="48" height="12" rx="2"/>
                    <path d="M12 38v18h40V38"/>
                    <path d="M32 26v30"/>
                    <path d="M32 26c-4-10-16-12-16-5 0 4 8 5 16 5zM32 26c4-10 16-12 16-5 0 4-8 5-16 5z"/>
                </svg>
                <strong>Gửi quà mừng cưới</strong>
                <span>Nhấn để xem thông tin</span>
            </button>
            <p class="t3-thanks t3-reveal">Thank you for being with us</p>
        </section>

        <!-- ============ 12. KẾT THÚC ============ -->
        <section class="t3-ending">
    <div class="t3-ending-bg"<?= $storyImage !== '' ? ' style="background-image:url(\'' . t3e($storyImage) . '\')"' : '' ?>></div>

    <div class="t3-ending-text t3-reveal">
        <p>Cảm ơn bạn đã ghé thăm tấm thiệp nhỏ này.</p>
        <p>Sự hiện diện và lời chúc của bạn<br>là món quà ý nghĩa nhất với chúng mình.</p>
        <p>With love,<br><?= t3e($shortGroom) ?> &amp; <?= t3e($shortBride) ?></p>
    </div>
</section>
    </main>

    <!-- ============ MODAL HỘP MỪNG CƯỚI ============ -->
    <div class="t3-modal" id="t3GiftModal" aria-hidden="true">
        <div class="t3-modal-backdrop" data-close></div>
        <div class="t3-modal-panel" role="dialog" aria-modal="true" aria-label="Hộp mừng cưới">
            <button type="button" class="t3-modal-close" data-close aria-label="Đóng">&times;</button>
            <h3 class="t3-modal-title">Hộp mừng cưới</h3>
            <p class="t3-modal-msg"><?= nl2br(t3e($giftMessage)) ?></p>

            <?php if ($giftMode !== 'message'): ?>
            <div class="t3-qr-grid">
                <?php foreach ([['MỪNG CƯỚI ĐẾN CHÚ RỂ', $groomGift, 'chú rể'], ['MỪNG CƯỚI ĐẾN CÔ DÂU', $brideGift, 'cô dâu']] as [$cap, $g, $who]):
                    $qr = $g ? t3url($g['qr_image'] ?? '') : '';
                ?>
                    <div class="t3-qr-card">
                        <h4><?= $cap ?></h4>
                        <div class="t3-qr-box">
                            <?php if ($qr !== ''): ?>
                                <img src="<?= t3e($qr) ?>" alt="QR mừng cưới <?= $who ?>">
                            <?php else: ?>
                                <span>Chưa có QR</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($g): ?>
                            <div class="t3-bank">
                                <?php if (!empty($g['bank_name'])): ?><p><?= t3e($g['bank_name']) ?></p><?php endif; ?>
                                <?php if (!empty($g['account_number'])): ?><p>STK: <?= t3e($g['account_number']) ?></p><?php endif; ?>
                                <?php if (!empty($g['account_name'])): ?><p><?= t3e($g['account_name']) ?></p><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($music !== ''): ?>
        <button type="button" class="t3-music" id="t3Music" aria-label="Bật/tắt nhạc">♪</button>
        <audio id="t3Audio" loop preload="none"><source src="<?= t3e($music) ?>" type="audio/mpeg"></audio>
    <?php endif; ?>
</div>

<script src="/WeddingProject/templates/template06/script.js"></script>
</body>
</html>