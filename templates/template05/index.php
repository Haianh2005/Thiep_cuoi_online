<?php
/* =====================================================================
   TEMPLATE 05 - SONG HỶ XANH
   Biến $wedding do w/index.php truyền vào.
   ===================================================================== */

// ---------- Kết nối DB (nếu w/index.php chưa có $pdo) ----------
if (!isset($pdo)) {
    @include_once __DIR__ . '/../../config/database.php';
}

// ---------- Đường dẫn gốc: /WeddingProject ----------
$base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');

// ---------- Hàm tiện ích ----------
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function pick($row, $keys, $default = '') {
    foreach ($keys as $k) {
        if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) return $row[$k];
    }
    return $default;
}

function fileUrl($base, $path, $folder = '') {
    $path = trim((string)$path);
    if ($path === '') return '';
    if (preg_match('#^(https?:)?//#', $path)) return $path;   // link đầy đủ
    if ($path[0] === '/') return $path;                       // đã có /WeddingProject/... thì giữ nguyên
    return $base . '/' . ltrim($path, '/');
}

$wid = (int)($wedding['id'] ?? 0);

function fetchRows($pdo, $sql, $wid) {
    if (!isset($pdo) || !$pdo) return [];
    try {
        $st = $pdo->prepare($sql);
        $st->execute([$wid]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        return [];
    }
}

// ---------- Lấy dữ liệu ----------
$parentRows = fetchRows($pdo ?? null, 'SELECT side, parent_role, parent_name FROM wedding_parents WHERE wedding_id = ? ORDER BY id ASC', $wid);
$eventRows  = fetchRows($pdo ?? null, 'SELECT * FROM wedding_events  WHERE wedding_id = ? ORDER BY sort_order ASC, id ASC', $wid);
$photoRows  = fetchRows($pdo ?? null, 'SELECT * FROM wedding_photos  WHERE wedding_id = ? ORDER BY sort_order ASC, id ASC', $wid);
$wishRows   = fetchRows($pdo ?? null, 'SELECT * FROM wedding_wishes  WHERE wedding_id = ? ORDER BY id DESC', $wid);
$giftRows   = fetchRows($pdo ?? null, 'SELECT * FROM wedding_gifts   WHERE wedding_id = ? ORDER BY id ASC', $wid);

// ---------- Thông tin cơ bản ----------
$groom = e($wedding['groom_name'] ?? '');
$bride = e($wedding['bride_name'] ?? '');
$venue = e($wedding['venue'] ?? '');
$addr  = e($wedding['address'] ?? '');
$story = trim((string)($wedding['story'] ?? ''));

$ts       = !empty($wedding['wedding_date']) ? strtotime($wedding['wedding_date']) : null;
$isoDate  = $ts ? date('Y-m-d', $ts) : '';
$dateText = $ts ? date('j', $ts) . ' tháng ' . date('n', $ts) . ', ' . date('Y', $ts) : '';
$timeText = !empty($wedding['wedding_time']) ? substr($wedding['wedding_time'], 0, 5) : '';

$weekdays = ['CHỦ NHẬT', 'THỨ HAI', 'THỨ BA', 'THỨ TƯ', 'THỨ NĂM', 'THỨ SÁU', 'THỨ BẢY'];
$weekday  = $ts ? $weekdays[(int)date('w', $ts)] : '';
$dayNum   = $ts ? date('d', $ts) : '';
$monthNum = $ts ? date('m', $ts) : '';
$yearNum  = $ts ? date('Y', $ts) : '';

// Nơi cử hành lễ: chưa có cột riêng trong DB nên để chữ mặc định, sửa tại đây nếu cần
$ceremonyPlace = 'TƯ GIA';

// ---------- Sự kiện: sắp xếp theo giờ ----------
$events = [];
foreach ($eventRows as $r) {
    $events[] = [
        'title' => pick($r, ['title', 'event_title', 'event_name', 'name', 'label']),
        'time'  => substr((string)pick($r, ['event_time', 'time', 'start_time']), 0, 5),
    ];
}
usort($events, function ($a, $b) { return strcmp($a['time'], $b['time']); });

$receiveTime = '';
$partyTime   = '';
foreach ($events as $ev) {
    $t = mb_strtolower($ev['title'], 'UTF-8');
    if (!$receiveTime && mb_strpos($t, 'đón') !== false)  $receiveTime = $ev['time'];
    if (!$partyTime   && (mb_strpos($t, 'tiệc') !== false || mb_strpos($t, 'khai') !== false)) $partyTime = $ev['time'];
}
if (!$partyTime) $partyTime = $timeText;

// ---------- Cha mẹ hai họ ----------
$sides = [
    'groom' => ['father' => '', 'mother' => '', 'address' => ''],
    'bride' => ['father' => '', 'mother' => '', 'address' => ''],
];
foreach ($parentRows as $r) {
    $side = (string)($r['side'] ?? '');
    $role = (string)($r['parent_role'] ?? '');
    if (isset($sides[$side][$role])) {
        $sides[$side][$role] = (string)($r['parent_name'] ?? '');
    }
}
$groomSide = array_filter($sides['groom']) ? $sides['groom'] : null;
$brideSide = array_filter($sides['bride']) ? $sides['bride'] : null;

// ---------- Album ----------
$photos = [];
foreach ($photoRows as $r) {
    $p = trim((string)($r['image_path'] ?? $r['photo_path'] ?? ''));
    if ($p !== '') $photos[] = fileUrl($base, $p);
}

$projectRoot = '';

// Xem lỗi album: mở link thiệp thêm &debug=1
$debugAlbum = isset($_GET['debug']);

// ---------- Ảnh cô dâu, chú rể, câu chuyện, ảnh nền ----------
function resolveUpload($base, $root, $wid, $p) {
    return fileUrl($base, $p);
}

$groomPhoto = resolveUpload($base, $projectRoot, $wid, $wedding['groom_photo'] ?? '');
$bridePhoto = resolveUpload($base, $projectRoot, $wid, $wedding['bride_photo'] ?? '');
$storyImage = resolveUpload($base, $projectRoot, $wid, $wedding['story_image'] ?? '');
$bgImage    = resolveUpload($base, $projectRoot, $wid, $wedding['background_image'] ?? '');

// ---------- Lịch tháng ----------
$calendarCells = [];
$calendarTitle = '';
if ($ts) {
    $first  = strtotime(date('Y-m-01', $ts));
    $days   = (int)date('t', $ts);
    $offset = (int)date('N', $first) - 1;
    $today  = (int)date('j', $ts);
    for ($i = 0; $i < $offset; $i++) $calendarCells[] = '';
    for ($d = 1; $d <= $days; $d++) $calendarCells[] = $d;
    $calendarTitle = 'Tháng ' . date('n', $ts) . ' / ' . date('Y', $ts);
}

// ---------- Link thêm vào Google Calendar ----------
$calLink = '';
if ($ts) {
    $startTime = $partyTime ?: '18:00';
    $start = date('Ymd', $ts) . 'T' . str_replace(':', '', $startTime) . '00';
    $end   = date('Ymd', $ts) . 'T' . str_pad((string)min(23, (int)substr($startTime, 0, 2) + 3), 2, '0', STR_PAD_LEFT) . substr($startTime, 3, 2) . '00';
    $calLink = 'https://calendar.google.com/calendar/render?action=TEMPLATE'
        . '&text=' . rawurlencode('Đám cưới ' . strip_tags($groom) . ' & ' . strip_tags($bride))
        . '&dates=' . $start . '/' . $end
        . '&location=' . rawurlencode(strip_tags($venue . ' ' . $addr));
}

// ---------- Bản đồ ----------
$mapQuery = trim(strip_tags($addr ?: $venue));

// ---------- Quà mừng ----------
$giftMode = '';
$giftMsg  = '';
$gifts    = [];
$bankRows = [];

foreach ($giftRows as $r) {
    $mode = mb_strtolower(trim((string)($r['gift_mode'] ?? '')), 'UTF-8');
    if ($mode === 'bank')    $giftMode = 'bank';
    if ($mode === 'message' && $giftMode !== 'bank') $giftMode = 'message';
    if (!empty($r['gift_message']) && $giftMsg === '') $giftMsg = (string)$r['gift_message'];

    // Dòng nào có QR hoặc số tài khoản thì coi là thông tin chuyển khoản
    $hasQr  = pick($r, ['qr_image', 'qr_image_path', 'qr_path', 'qr', 'image_path']);
    $hasNum = pick($r, ['account_number', 'account_no', 'bank_account', 'stk', 'number']);
    if ($hasQr !== '' || $hasNum !== '') $bankRows[] = $r;
}

foreach ($bankRows as $i => $r) {
    $pt = mb_strtolower((string)($r['person_type'] ?? ''), 'UTF-8');
    $isBride = (strpos($pt, 'bride') !== false || strpos($pt, 'gai') !== false || strpos($pt, 'dâu') !== false || strpos($pt, 'dau') !== false);
    $isGroom = (strpos($pt, 'groom') !== false || strpos($pt, 'trai') !== false || strpos($pt, 'rể') !== false || strpos($pt, 're') === 0);
    if (!$isBride && !$isGroom) $isBride = ($i === 1);   // không nhận ra: dòng 1 chú rể, dòng 2 cô dâu
    $gifts[] = [
        'label'  => $isBride ? 'Cô Dâu' : 'Chú Rể',
        'name'   => pick($r, ['account_name', 'receiver_name', 'account_holder', 'holder_name', 'owner_name', 'name']),
        'bank'   => pick($r, ['bank_name', 'bank', 'banking_name']),
        'number' => pick($r, ['account_number', 'account_no', 'bank_account', 'stk', 'number']),
        'qr'     => fileUrl($base, pick($r, ['qr_image', 'qr_image_path', 'qr_path', 'qr', 'image_path'])),
    ];
}
// Chú rể luôn đứng trước cô dâu
usort($gifts, function ($x, $y) { return strcmp($y['label'], $x['label']); });

if ($giftMode === '') $giftMode = $gifts ? 'bank' : 'message';
if ($giftMode === 'bank' && !$gifts) $giftMode = 'message';

// ---------- Nhạc ----------
$musicFile = $wedding['music'] ?? '';

$assetBase = $base . '/templates/template05';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $groom ?> &amp; <?= $bride ?> - Thiệp cưới</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Great+Vibes&family=Lora:wght@400;500;600&family=Noto+Serif+SC:wght@600&family=Dancing+Script:wght@600;700&display=swap" rel="stylesheet">

        <link rel="stylesheet" href="/WeddingProject/templates/template05/style.css">
</head>
<body class="is-locked">

<div class="sparkles" id="sparkles"></div>
<div class="frame" aria-hidden="true"></div>

<!-- ============ OPENING ============ -->
<section class="opening" id="opening">
    <div class="opening-card">
        <div class="opening-heart">
            <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 2.7 5 6 5c2 0 3.4 1.1 4 2.3h4C14.6 6.1 16 5 18 5c3.300 0 5.100 3.400 3.600 6.800C19.500 16.400 12 21 12 21z"/></svg>
        </div>
        <h1 class="opening-names">
            <span><?= $groom ?></span>
            <b>&amp;</b>
            <span><?= $bride ?></span>
        </h1>
        <div class="divider"><i></i><em>❦</em><i></i></div>
        <?php if ($dateText): ?><p class="opening-date"><?= e($dateText) ?></p><?php endif; ?>
        <p class="opening-invite">Thân Mời</p>
        <button type="button" class="opening-btn" id="openBtn">Mở thiệp</button>
    </div>
</section>

<!-- ============ NỘI DUNG ============ -->
<main class="page" id="page">

    <!-- 1. HERO -->
    <section class="hero<?= $bgImage ? ' hero-has-bg' : '' ?>"<?= $bgImage ? ' style="background-image: linear-gradient(rgba(2,20,10,.62), rgba(2,20,10,.78)), url(\'' . e($bgImage) . '\')"' : '' ?>>
        <p class="hero-small">THE WEDDING OF</p>
        <h2 class="hero-names">
            <span><?= $groom ?></span>
            <b>&amp;</b>
            <span><?= $bride ?></span>
        </h2>
    </section>

    <!-- 1b. CHÚ RỂ & CÔ DÂU -->
    <?php if ($groomPhoto || $bridePhoto): ?>
    <section class="sec sec-couple">
        <h3 class="sec-title sec-title-sm">CHÚ RỂ &amp; CÔ DÂU</h3>
        <div class="couple">
            <?php foreach ([['CHÚ RỂ', $groom, $groomPhoto], ['CÔ DÂU', $bride, $bridePhoto]] as $p): if (!$p[2]) continue; ?>
            <figure class="couple-card">
                <div class="couple-photo"><img src="<?= e($p[2]) ?>" alt="<?= e($p[0]) ?> <?= $p[1] ?>" loading="lazy"></div>
                <figcaption>
                    <small><?= $p[0] ?></small>
                    <strong><?= $p[1] ?></strong>
                </figcaption>
            </figure>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- 1c. CÂU CHUYỆN -->
    <?php if ($story !== '' || $storyImage): ?>
    <section class="sec sec-story">
        <h3 class="sec-title sec-title-sm">CÂU CHUYỆN CỦA CHÚNG TÔI</h3>
        <?php if ($storyImage): ?>
            <div class="story-img"><img src="<?= e($storyImage) ?>" alt="Câu chuyện của chúng tôi" loading="lazy"></div>
        <?php endif; ?>
        <?php if ($story !== ''): ?>
            <div class="story-text"><?= nl2br(e($story)) ?></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- 2. THÔNG TIN LỄ CƯỚI -->
    <section class="sec sec-ceremony">
        <h3 class="sec-title">THÔNG TIN LỄ CƯỚI</h3>
        <?php if ($debugAlbum): ?>
            <pre style="margin:0 0 16px;padding:10px;text-align:left;white-space:pre-wrap;font-size:11px;color:#fff;background:#400;border-radius:6px">
Số dòng đọc được từ wedding_parents: <?= count($parentRows) ?>

Dòng 1: <?= e(json_encode($parentRows[0] ?? null, JSON_UNESCAPED_UNICODE)) ?>

Dòng 2: <?= e(json_encode($parentRows[1] ?? null, JSON_UNESCAPED_UNICODE)) ?>

Nhà trai đọc được: <?= e(json_encode($groomSide, JSON_UNESCAPED_UNICODE)) ?>

Nhà gái đọc được: <?= e(json_encode($brideSide, JSON_UNESCAPED_UNICODE)) ?>
            </pre>
        <?php endif; ?>

        <?php if ($groomSide || $brideSide): ?>
        <div class="family">
            <?php
            $familyCols = [['NHÀ TRAI', $groomSide ?: []], ['NHÀ GÁI', $brideSide ?: []]];
            foreach ($familyCols as $ci => $col):
                if ($ci === 1): ?>
                    <div class="family-hy" aria-hidden="true">囍</div>
                <?php endif; ?>
                <div class="family-col">
                    <h4 class="family-label"><?= $col[0] ?></h4>
                    <?php if (!empty($col[1]['father'])): ?>
                        <div class="family-line"><small>Ông</small><strong><?= e($col[1]['father']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($col[1]['mother'])): ?>
                        <div class="family-line"><small>Bà</small><strong><?= e($col[1]['mother']) ?></strong></div>
                    <?php endif; ?>
                    <?php if (!empty($col[1]['address'])): ?>
                        <p class="parents-addr"><?= e($col[1]['address']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <p class="notice">TRÂN TRỌNG BÁO TIN<br>LỄ THÀNH HÔN CỦA CON CHÚNG TÔI</p>

        <p class="person"><?= $groom ?></p>
        <small class="person-role">CHÚ RỂ</small>
        <p class="amp">&amp;</p>
        <p class="person"><?= $bride ?></p>
        <small class="person-role">CÔ DÂU</small>

        <p class="notice notice-2">LỄ THÀNH HÔN ĐƯỢC CỬ HÀNH TẠI<br><?= e($ceremonyPlace) ?><br>VÀO LÚC</p>
        <?php if ($timeText): ?><p class="big-time"><?= e($timeText) ?></p><?php endif; ?>

        <?php if ($ts): ?>
        <div class="date-row">
            <div><small><?= e($weekday) ?></small></div>
            <div class="date-day"><?= e($dayNum) ?></div>
            <div><small>THÁNG <?= e($monthNum) ?></small></div>
        </div>
        <p class="date-year"><?= e($yearNum) ?></p>
        <p class="lunar js-lunar" data-date="<?= e($isoDate) ?>"></p>
        <?php endif; ?>
    </section>

    <!-- 3. ALBUM ẢNH -->
    <?php if ($photos || $debugAlbum): ?>
    <section class="sec sec-album">
        <h3 class="sec-title sec-title-sm">ALBUM ẢNH</h3>
        <?php if ($debugAlbum): ?>
            <pre style="margin:0 16px 16px;padding:10px;text-align:left;white-space:pre-wrap;font-size:11px;color:#fff;background:#400;border-radius:6px">
wedding_id: <?= (int)$wid ?>

Số dòng đọc được từ wedding_photos: <?= count($photoRows) ?>

Dòng đầu tiên: <?= e(json_encode($photoRows[0] ?? null, JSON_UNESCAPED_UNICODE)) ?>

Số ảnh dùng được: <?= count($photos) ?>

Ảnh đầu tiên: <?= e($photos[0] ?? '(không có)') ?>
            </pre>
        <?php endif; ?>
        <div class="coverflow" id="coverflow" tabindex="0" aria-roledescription="carousel" aria-label="Album ảnh cưới">
            <div class="cf-stage" id="cfStage">
                <?php foreach ($photos as $i => $src): ?>
                    <div class="cf-slide" data-index="<?= $i ?>">
                        <img src="<?= e($src) ?>" alt="Ảnh cưới <?= $i + 1 ?>" draggable="false" <?= $i > 2 ? 'loading="lazy"' : '' ?>>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($photos) > 1): ?>
                <button type="button" class="cf-arrow cf-prev" id="cfPrev" aria-label="Ảnh trước">&#8249;</button>
                <button type="button" class="cf-arrow cf-next" id="cfNext" aria-label="Ảnh sau">&#8250;</button>
            <?php endif; ?>
        </div>
        <?php if (count($photos) > 1): ?>
            <div class="cf-dots" id="cfDots"></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- 4. THÔNG TIN TIỆC CƯỚI -->
    <section class="sec sec-party">
        <h3 class="sec-title sec-title-sm">THÔNG TIN TIỆC CƯỚI</h3>
        <p class="notice">TIỆC CƯỚI SẼ DIỄN RA VÀO LÚC:</p>
        <?php if ($partyTime): ?><p class="big-time"><?= e($partyTime) ?></p><?php endif; ?>

        <?php if ($ts): ?>
        <div class="date-row">
            <div><small><?= e($weekday) ?></small></div>
            <div class="date-day"><?= e($dayNum) ?></div>
            <div><small>THÁNG <?= e($monthNum) ?></small></div>
        </div>
        <p class="date-year"><?= e($yearNum) ?></p>
        <p class="lunar js-lunar" data-date="<?= e($isoDate) ?>"></p>
        <?php endif; ?>

        <div class="times">
            <?php if ($receiveTime): ?><div><small>ĐÓN KHÁCH</small><strong><?= e($receiveTime) ?></strong></div><?php endif; ?>
            <?php if ($partyTime): ?><div><small>KHAI TIỆC</small><strong><?= e($partyTime) ?></strong></div><?php endif; ?>
        </div>

        <?php if ($calendarCells): ?>
        <div class="calendar">
            <div class="calendar-title"><?= e($calendarTitle) ?></div>
            <div class="calendar-grid">
                <?php foreach (['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $h): ?><span class="cal-h"><?= $h ?></span><?php endforeach; ?>
                <?php foreach ($calendarCells as $c): ?>
                    <?php if ($c === ''): ?><span></span>
                    <?php elseif ($c === $today): ?><span class="cal-today"><?= $c ?></span>
                    <?php else: ?><span><?= $c ?></span><?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($calLink): ?>
            <a class="link-underline" href="<?= e($calLink) ?>" target="_blank" rel="noopener">Thêm vào lịch</a>
        <?php endif; ?>
        
    </section>

    <!-- 5. BẢN ĐỒ -->
    <?php if ($mapQuery): ?>
    <section class="sec sec-map">
        <h3 class="sec-title sec-title-sm">TIỆC CƯỚI SẼ TỔ CHỨC TẠI</h3>
        <?php if ($venue): ?><p class="venue-name"><?= $venue ?></p><?php endif; ?>
        <?php if ($addr): ?><p class="venue-addr"><?= $addr ?></p><?php endif; ?>
        <div class="map-box">
            <iframe src="https://www.google.com/maps?q=<?= rawurlencode($mapQuery) ?>&amp;output=embed"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Bản đồ"></iframe>
        </div>
    </section>
    <?php endif; ?>

    <!-- 6. LỊCH TRÌNH NGÀY CƯỚI -->
    <?php if ($events): ?>
    <section class="sec sec-timeline">
        <h3 class="sec-title sec-title-sm">LỊCH TRÌNH NGÀY CƯỚI</h3>
        <ul class="timeline">
            <?php foreach ($events as $ev): ?>
                <li><span><?= e($ev['time']) ?></span><i></i><b><?= e($ev['title']) ?></b></li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <!-- 7. SỔ LƯU BÚT -->
    <section class="sec sec-guestbook" id="guestbook">
        <h3 class="sec-title sec-title-sm">SỔ LƯU BÚT</h3>

        <div class="wish-form">
            <input type="text" id="wishName" placeholder="Nhập tên của bạn?" maxlength="60">
            <textarea id="wishMessage" rows="4" placeholder="Gửi lời chúc đến cô dâu chú rể" maxlength="500"></textarea>
            <button type="button" id="sendWish" class="pill-btn pill-btn-sm" data-wedding="<?= $wid ?>" data-slug="<?= e($wedding['slug'] ?? '') ?>" data-api="<?= e($base) ?>/api/wishes.php">GỬI LỜI CHÚC</button>
            <p class="wish-note" id="wishNote" hidden></p>
        </div>

        <div class="wish-list" id="wishList">
            <?php foreach ($wishRows as $w): ?>
                <article class="wish-item">
                    <header>
                        <strong><?= e(pick($w, ['guest_name', 'name', 'author'])) ?></strong>
                        <time><?= e(pick($w, ['created_at'])) ?></time>
                    </header>
                    <p><?= nl2br(e(pick($w, ['message', 'content', 'wish']))) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 8. HỘP QUÀ MỪNG -->
    <section class="sec sec-gift">
        <h3 class="gift-heading">Hộp quà mừng</h3>
        <p class="gift-sub">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>

        <button type="button" class="gift-card" id="giftBtn" aria-haspopup="dialog">
            <svg viewBox="0 0 48 48" width="46" height="46" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="6" y="17" width="36" height="8" rx="1.5"/>
                <path d="M9 25v17a1.5 1.5 0 0 0 1.5 1.5h27A1.5 1.5 0 0 0 39 42V25"/>
                <path d="M24 17v26.5"/>
                <path d="M24 17c-1-5-4.500-9-8.500-9-3 0-4.500 2.200-3.500 4.500C13 14.800 16.500 17 24 17z"/>
                <path d="M24 17c1-5 4.500-9 8.500-9 3 0 4.500 2.200 3.500 4.500C35 14.800 31.500 17 24 17z"/>
            </svg>
            <strong>Gửi quà mừng cưới</strong>
            <span>Nhấn để xem thông tin</span>
        </button>

        <p class="gift-script">Thank you for being with us</p>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <p><?= $groom ?> &amp; <?= $bride ?></p>
    </footer>
</main>

<!-- ============ POPUP QUÀ MỪNG ============ -->
<div class="modal" id="giftModal" hidden>
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="giftTitle">
        <button type="button" class="modal-close" id="giftClose" aria-label="Đóng">&times;</button>

        <h4 class="modal-title" id="giftTitle">Hộp mừng cưới</h4>
        <?php if ($debugAlbum): ?>
            <pre style="margin:0 0 12px;padding:8px;text-align:left;white-space:pre-wrap;font-size:10px;color:#fff;background:#400;border-radius:6px">
Số dòng wedding_gifts: <?= count($giftRows) ?>

Dòng 1: <?= e(json_encode($giftRows[0] ?? null, JSON_UNESCAPED_UNICODE)) ?>

Chế độ: <?= e($giftMode) ?> | Số thẻ QR: <?= count($gifts) ?>
            </pre>
        <?php endif; ?>
        <p class="modal-sub">Vợ chồng mình xin cảm ơn tất cả tình cảm của mọi người ♥</p>

        <?php if ($giftMode === 'message' || !$gifts): ?>
            <p class="gift-message"><?= nl2br(e($giftMsg ?: 'Sự hiện diện của quý khách là niềm vinh hạnh của gia đình chúng tôi.')) ?></p>
        <?php else: ?>
            <div class="qr-grid">
                <?php foreach ($gifts as $g): ?>
                <div class="qr-item">
                    <h5>MỪNG CƯỚI ĐẾN <?= e(mb_strtoupper($g['label'], 'UTF-8')) ?></h5>
                    <?php if ($g['qr']): ?>
                        <div class="qr-img"><img src="<?= e($g['qr']) ?>" alt="QR <?= e($g['label']) ?>"></div>
                    <?php endif; ?>
                    <div class="qr-info">
                        <?php if ($g['bank']): ?><span><?= e($g['bank']) ?></span><?php endif; ?>
                        <?php if ($g['number']): ?><span>STK: <?= e($g['number']) ?></span><?php endif; ?>
                        <?php if ($g['name']): ?><span><?= e($g['name']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($g['qr']): ?><a class="qr-save" href="<?= e($g['qr']) ?>" download>Lưu QR</a><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($musicFile): ?>
<audio id="weddingMusic" loop>
    <source src="<?= e($base) ?>/uploads/music/<?= e($musicFile) ?>">
</audio>
<?php endif; ?>

<script src="/WeddingProject/templates/template05/script.js"></script>
</body>
</html>