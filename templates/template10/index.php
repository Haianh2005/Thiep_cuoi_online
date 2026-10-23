<?php
/*
|--------------------------------------------------------------------------
| TEMPLATE 10 – MINT (xanh rêu / giấy kem)
|--------------------------------------------------------------------------
| w/index.php đã tìm wedding theo slug và truyền vào $wedding (+ $pdo).
| Template chỉ ĐỌC dữ liệu và hiển thị. Không lưu gì.
| Cột tuỳ chọn (groom_photo, bride_photo, lunar_date): có thì dùng, chưa có thì tự ẩn.
|--------------------------------------------------------------------------
*/

if (!isset($wedding) || !$wedding) {
    die("Không tìm thấy thông tin đám cưới.");
}

/* ---------- helper ---------- */
if (!function_exists('t10e')) {
    function t10e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}
/* Đường dẫn tuyệt đối/http giữ nguyên, còn lại thêm /WeddingProject/ (không lặp tiền tố). */
if (!function_exists('t10url')) {
    function t10url($p) {
        $p = trim((string)($p ?? ''));
        if ($p === '') return '';
        if (preg_match('#^(https?:)?//#i', $p) || $p[0] === '/') return $p;
        return '/WeddingProject/' . ltrim($p, '/');
    }
}
/* "Nguyễn Anh Tú" -> "Anh Tú" (như ảnh mẫu); tên 1-2 chữ giữ nguyên */
if (!function_exists('t10name')) {
    function t10name($n) {
        $p = preg_split('/\s+/u', trim((string)$n));
        if (count($p) >= 3) array_shift($p);
        return implode(' ', $p);
    }
}
/* "Ông A" giữ nguyên, "A" -> "Ông A" */
if (!function_exists('t10parent')) {
    function t10parent($name, $prefix) {
        $name = trim((string)$name);
        if ($name === '') return '';
        if (preg_match('/^(ông|bà)\b/iu', $name)) return $name;
        return $prefix . ': ' . $name;
    }
}
if (!function_exists('t10initial')) {
    function t10initial($n) {
        $p = preg_split('/\s+/u', trim((string)$n));
        $l = end($p);
        return ($l === false || $l === '') ? '?' : mb_strtoupper(mb_substr($l, 0, 1, 'UTF-8'), 'UTF-8');
    }
}
/* Lấy giá trị đầu tiên có trong hàng dữ liệu theo danh sách tên cột có thể có */
if (!function_exists('t10pick')) {
    function t10pick($row, $keys) {
        foreach ($keys as $k) {
            if (isset($row[$k]) && trim((string)$row[$k]) !== '') return trim((string)$row[$k]);
        }
        return '';
    }
}

/* Thư mục template tự nhận theo tên folder */
$base = '/WeddingProject/templates/' . basename(__DIR__) . '/';

/* ---------- thông tin cơ bản ---------- */
$groomName  = $wedding['groom_name'] ?? '';
$brideName  = $wedding['bride_name'] ?? '';
$venue      = trim((string)($wedding['venue'] ?? ''));
$address    = trim((string)($wedding['address'] ?? ''));
$groomPhoto = t10url($wedding['groom_photo'] ?? '');
$bridePhoto = t10url($wedding['bride_photo'] ?? '');
$lunarDate  = trim((string)($wedding['lunar_date'] ?? ''));
$music      = t10url($wedding['music'] ?? '');
$story      = trim((string)($wedding['story'] ?? ''));
$storyImage = t10url($wedding['story_image'] ?? '');
$bgImage    = t10url($wedding['background_image'] ?? '');
$shortGroom = t10name($groomName);
$shortBride = t10name($brideName);

/* Ảnh trang hero (ảnh 2). Muốn đổi sang story_image: $heroImage = $storyImage ?: $bgImage; */
$heroImage = $bgImage !== '' ? $bgImage : $storyImage;
/* Ảnh nền màn kết (ảnh 9) = story_image */
$endImage  = $storyImage !== '' ? $storyImage : $bgImage;

/* ---------- ngày giờ ---------- */
$weekdayNames = ['CHỦ NHẬT','THỨ HAI','THỨ BA','THỨ TƯ','THỨ NĂM','THỨ SÁU','THỨ BẢY'];
$dateObject = null;
if (!empty($wedding['wedding_date'])) {
    try { $dateObject = new DateTime($wedding['wedding_date']); } catch (Exception $e) { $dateObject = null; }
}
$timeHi = '';
if (!empty($wedding['wedding_time']) && strtotime($wedding['wedding_time']) !== false) {
    $timeHi = date('H:i', strtotime($wedding['wedding_time']));
}
$weekdayLowNames = ['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
$day = $month = $year = $weekday = $weekdayLow = $dotDate = $targetIso = '';
$calCells = [];
$weddingDay = 0;
$daysLeft = 0;
if ($dateObject) {
    $day = $dateObject->format('d');
    $month = $dateObject->format('m');
    $year = $dateObject->format('Y');
    $weekday = $weekdayNames[(int)$dateObject->format('w')];
    $weekdayLow = $weekdayLowNames[(int)$dateObject->format('w')];
    $dotDate = $dateObject->format('Y.m.d');
    $targetIso = $dateObject->format('Y-m-d') . 'T' . ($timeHi !== '' ? $timeHi . ':00' : '00:00:00');
    $weddingDay = (int)$dateObject->format('j');
    $first = new DateTime($dateObject->format('Y-m-01'));
    $offset = ((int)$first->format('w') + 6) % 7;      /* tuần bắt đầu từ Thứ 2 */
    $dim = (int)$first->format('t');
    for ($i = 0; $i < $offset; $i++) $calCells[] = null;
    for ($d = 1; $d <= $dim; $d++) $calCells[] = $d;
    $tsTarget = strtotime($targetIso);
    $daysLeft = $tsTarget ? max(0, (int)floor(($tsTarget - time()) / 86400)) : 0;
}

/* ---------- album (2 slideshow xáo thứ tự khác nhau) ---------- */
$photoStmt = $pdo->prepare("SELECT image_path FROM wedding_photos WHERE wedding_id = :wedding_id ORDER BY sort_order ASC, id ASC");
$photoStmt->execute(['wedding_id' => $wedding['id']]);
$album = [];
foreach ($photoStmt->fetchAll() as $row) {
    $u = t10url($row['image_path'] ?? '');
    if ($u !== '') $album[] = $u;
}
$slidesA = $album; shuffle($slidesA);
$slidesB = $album; shuffle($slidesB);
if (count($album) > 1 && $slidesA[0] === $slidesB[0]) { $slidesB[] = array_shift($slidesB); }
$jsonFlags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

/* ---------- bố mẹ ---------- */
$parents = [
    'groom' => ['father' => '', 'mother' => ''],
    'bride' => ['father' => '', 'mother' => ''],
];
$parentStmt = $pdo->prepare("SELECT * FROM wedding_parents WHERE wedding_id = :wedding_id ORDER BY id ASC");
$parentStmt->execute(['wedding_id' => $wedding['id']]);
foreach ($parentStmt->fetchAll() as $p) {
    $side = $p['side'] ?? '';
    $role = $p['parent_role'] ?? '';
    if (isset($parents[$side][$role])) $parents[$side][$role] = $p['parent_name'] ?? '';
}

/* ---------- sự kiện (timeline) ---------- */
$events = [];
$eventStmt = $pdo->prepare("SELECT * FROM wedding_events WHERE wedding_id = :wedding_id ORDER BY id ASC");
$eventStmt->execute(['wedding_id' => $wedding['id']]);
foreach ($eventStmt->fetchAll() as $ev) {
    $title = t10pick($ev, ['event_name', 'title', 'name', 'event_title']);
    $rawT  = t10pick($ev, ['event_time', 'time', 'start_time']);
    $t     = ($rawT !== '' && strtotime($rawT) !== false) ? date('H:i', strtotime($rawT)) : $rawT;
    if ($title === '' && $t === '') continue;
    $events[] = ['title' => $title, 'time' => $t];
}
usort($events, function ($a, $b) { return strcmp($a['time'], $b['time']); });

/* ---------- quà mừng ---------- */
$giftStmt = $pdo->prepare("SELECT * FROM wedding_gifts WHERE wedding_id = :wedding_id ORDER BY id ASC");
$giftStmt->execute(['wedding_id' => $wedding['id']]);
$giftMode = 'bank';
$giftMessage = '';
$groomGift = null;
$brideGift = null;
foreach ($giftStmt->fetchAll() as $g) {
    $mode = $g['gift_mode'] ?? '';
    if ($mode === 'message') $giftMode = 'message';
    if ($giftMessage === '' && !empty($g['gift_message'])) $giftMessage = $g['gift_message'];
    if ($mode !== 'message') {
        if (($g['person_type'] ?? '') === 'groom') $groomGift = $g;
        if (($g['person_type'] ?? '') === 'bride') $brideGift = $g;
    }
}
if ($giftMessage === '') $giftMessage = 'Sự hiện diện của bạn là niềm vui của gia đình chúng tôi.';

/* ---------- chỉ đường ---------- */
$mapQuery = $venue !== '' && $address !== '' ? $venue . ', ' . $address : ($address !== '' ? $address : $venue);
$directionsUrl = $mapQuery !== '' ? 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($mapQuery) : '';
$mapEmbed = $mapQuery !== '' ? 'https://www.google.com/maps?q=' . urlencode($mapQuery) . '&output=embed&hl=vi' : '';
$addrImage = $storyImage !== '' ? $storyImage : (!empty($album) ? $album[0] : '');

/* ---------- sổ lưu bút (api/wishes.php nhận lời chúc mới, bảng wedding_wishes) ---------- */
$wishes = [];
try {
    $wishStmt = $pdo->prepare("SELECT * FROM wedding_wishes WHERE wedding_id = :wedding_id ORDER BY id DESC LIMIT 50");
    $wishStmt->execute(['wedding_id' => $wedding['id']]);
    foreach ($wishStmt->fetchAll() as $w) {
        $wm = t10pick($w, ['message', 'wish', 'content', 'wish_message', 'comment']);
        if ($wm === '') continue;
        $wn = t10pick($w, ['guest_name', 'name', 'sender_name', 'author', 'full_name']);
        $wd = t10pick($w, ['created_at', 'created', 'date']);
        $wishes[] = [
            'name' => $wn !== '' ? $wn : 'Khách mời',
            'message' => $wm,
            'time' => ($wd !== '' && strtotime($wd) !== false) ? date('H:i d/m/Y', strtotime($wd)) : '',
        ];
    }
} catch (Exception $e) { $wishes = []; }
$wishApi = t10url('api/wishes.php');

/* ---------- SVG dùng lại ---------- */
$heartRed  = '<svg viewBox="0 0 32 30" aria-hidden="true"><path d="M16 28C3 18 0 11 4 6c3-4 9-3 12 2 3-5 9-6 12-2 4 5 1 12-12 22z" fill="#d6212f"/><path d="M8 8c1-2 3-3 5-2" stroke="#fff" stroke-opacity=".45" stroke-width="1.4" fill="none" stroke-linecap="round"/></svg>';
$heartPink = '<svg viewBox="0 0 90 50" aria-hidden="true"><g fill="#f58aa3" stroke="#e8627f" stroke-width="1.2"><path d="M22 44C8 34 4 24 9 17c4-5 11-3 13 3 3-6 10-8 14-3 5 6 0 17-14 27z" transform="rotate(-14 22 30)"/><path d="M66 40C54 32 51 24 55 18c3-4 9-3 11 2 3-5 9-6 12-2 4 5 0 14-12 22z" transform="rotate(12 66 28)"/></g><g stroke="#f58aa3" stroke-width="2" stroke-linecap="round"><path d="M10 6l3 5M26 3l-1 6M4 16l5 1M80 8l-4 4M88 22l-6 0"/></g></svg>';
$orn = '<svg viewBox="0 0 120 20" aria-hidden="true"><path d="M60 17C51 11 49 6 52 3c3-2.600 6.500-1 8 2 1.500-3 5-4.600 8-2 3 3 1 8-8 14z" fill="currentColor"/></svg>';
$iconHands = '<svg viewBox="0 0 70 60" aria-hidden="true"><path d="M35 50C16 38 12 26 18 18c5-6 14-4 17 3 3-7 12-9 17-3 6 8 2 20-17 32z" fill="#eaeedf" stroke="#f8f9f2" stroke-width="1.4"/><path d="M6 40c8-6 14-6 20 0M64 40c-8-6-14-6-20 0" fill="none" stroke="#cdd6ba" stroke-width="5" stroke-linecap="round"/></svg>';
$iconRing  = '<svg viewBox="0 0 70 60" aria-hidden="true"><path d="M10 28l25-12 25 12v22L35 58 10 50z" fill="#eaeedf" stroke="#f8f9f2" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 28l25 10 25-10M35 38v20" fill="none" stroke="#cdd6ba" stroke-width="1.6"/><circle cx="35" cy="22" r="9" fill="none" stroke="#f3edcf" stroke-width="3.5"/><path d="M31 11l4-5 4 5-4 4z" fill="#f8f9f2" stroke="#cdd6ba" stroke-width="1"/></svg>';
$iconGlass = '<svg viewBox="0 0 70 60" aria-hidden="true"><path d="M26 6h18c0 14-3 22-9 24-6-2-9-10-9-24z" fill="#eaeedf" stroke="#f8f9f2" stroke-width="1.6"/><path d="M35 30v18M26 52h18" stroke="#f8f9f2" stroke-width="2" stroke-linecap="round"/><circle cx="52" cy="14" r="2" fill="#cdd6ba"/><circle cx="16" cy="22" r="2.4" fill="#cdd6ba"/><circle cx="56" cy="30" r="1.6" fill="#f3edcf"/></svg>';
$t10Icon = function ($title, $i) use ($iconHands, $iconRing, $iconGlass) {
    $t = mb_strtolower($title, 'UTF-8');
    if (preg_match('/đón|khách/u', $t)) return $iconHands;
    if (preg_match('/tiệc|nhập|ăn/u', $t)) return $iconGlass;
    if (preg_match('/lễ|cưới|hỏi|rước/u', $t)) return $iconRing;
    $set = [$iconHands, $iconRing, $iconGlass];
    return $set[$i % 3];
};

$storyParas = $story === '' ? [] : array_values(array_filter(array_map('trim', preg_split('/\R+/u', $story)), 'strlen'));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t10e($groomName) ?> &amp; <?= t10e($brideName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Lora:wght@400;500;600&family=Nunito:wght@400;600;700&family=Great+Vibes&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/WeddingProject/templates/template10/style.css">
    <noscript><style>.template10-page .t10-reveal{opacity:1!important;transform:none!important}</style></noscript>
</head>
<body class="t10-body t10-locked">

<div class="template10-page">

    <!-- ============ 1. MỞ THIỆP ============ -->
    <section id="t10Opening" class="t10-opening" role="button" tabindex="0" aria-label="Mở thiệp"<?= $bgImage !== '' ? ' style="--t10-open:url(\'' . t10e($bgImage) . '\')"' : '' ?>>
        <h1 class="t10-opening-title"><span>Thiệp</span><span>mời</span></h1>
        <div class="t10-opening-hint">Chạm để mở thiệp</div>
        <div class="t10-opening-name t10-left"><?= t10e(mb_strtoupper($shortGroom, 'UTF-8')) ?></div>
        <div class="t10-opening-name t10-right"><?= t10e(mb_strtoupper($shortBride, 'UTF-8')) ?></div>
    </section>

    <main class="t10-main">

        <!-- ============ 2. HERO (ảnh = background_image) ============ -->
       <section class="t10-hero"<?= $heroImage !== '' ? ' style="background-image:url(\'' . t10e($heroImage) . '\')"' : '' ?>>
    <div class="t10-hero-fade"></div>
    <div class="t10-hero-text">
        <p class="t10-hero-sub">Thiệp mời cưới</p>
        <h2 class="t10-hero-name"><?= t10e($shortGroom) ?></h2>
        <span class="t10-amp">&amp;</span>
        <h2 class="t10-hero-name"><?= t10e($shortBride) ?></h2>
    </div>
</section>

        <!-- ============ 3. TRÂN TRỌNG KÍNH MỜI + OUR STORY (story) ============ -->
        <section class="t10-story">
            <h2 class="t10-invite-title">Trân trọng kính mời</h2>
            <div class="t10-divider"><?= $orn ?></div>
            <?php if (!empty($storyParas)): ?>
                <div class="t10-story-wrap">
                    <h3 class="t10-story-head t10-reveal">Our Story<small>...</small></h3>
                    <?php foreach ($storyParas as $i => $para): ?>
                        <p class="t10-para t10-reveal <?= (intdiv($i, 2) % 2 === 0) ? 't10-pl' : 't10-pr' ?>"><?= nl2br(t10e($para)) ?></p>
                    <?php endforeach; ?>
                    <span class="t10-pinkhearts" aria-hidden="true"><?= $heartPink ?></span>
                </div>
            <?php endif; ?>
        </section>

        <!-- ============ 4. HAI ẢNH CHÚ RỂ / CÔ DÂU + SLIDESHOW ALBUM ============ -->
        <section class="t10-duo-sec">
            <div class="t10-duo">
                <figure class="t10-card t10-card-l">
                    <?php if ($groomPhoto !== ''): ?><img src="<?= t10e($groomPhoto) ?>" alt="Ảnh chú rể" loading="lazy">
                    <?php else: ?><span><?= t10e(t10initial($groomName)) ?></span><?php endif; ?>
                </figure>
                <figure class="t10-card t10-card-r">
                    <?php if ($bridePhoto !== ''): ?><img src="<?= t10e($bridePhoto) ?>" alt="Ảnh cô dâu" loading="lazy">
                    <?php else: ?><span><?= t10e(t10initial($brideName)) ?></span><?php endif; ?>
                </figure>
                <span class="t10-heart"><?= $heartRed ?></span>
            </div>
            <?php if (!empty($album)): ?>
            <div class="t10-slides" data-slides="<?= t10e(json_encode($slidesA, $jsonFlags)) ?>">
                <img class="is-on" src="<?= t10e($slidesA[0]) ?>" alt="Ảnh cưới">
                <img src="" alt="" aria-hidden="true">
            </div>
            <?php endif; ?>
        </section>

        <!-- ============ 5. SAVE THE DATE (1 địa điểm) ============ -->
        <?php if ($dateObject): ?>
        <section class="t10-date">
            <h2 class="t10-std"><span>Save</span><span>The Date</span></h2>
            <p class="t10-dt-time"><?= $timeHi !== '' ? '<span class="t10-num">' . t10e($timeHi) . '</span>, ' : '' ?><?= t10e($weekday) ?></p>
            <div class="t10-daterow">
                <span>THÁNG <span class="t10-num"><?= t10e($month) ?></span></span>
                <b><?= t10e($day) ?></b>
                <span>NĂM <span class="t10-num"><?= t10e($year) ?></span></span>
            </div>
            <?php if ($lunarDate !== ''): ?><p class="t10-lunar">(<?= t10e($lunarDate) ?>)</p><?php endif; ?>

            <!-- Lịch cưới + đếm ngược -->
            <div class="t10-cal">
                <p class="t10-cal-date"><?= t10e($dotDate) ?></p>
                <p class="t10-cal-when"><?= t10e($weekdayLow) ?><?= $timeHi !== '' ? ', <span class="t10-num">' . t10e($timeHi) . '</span>' : '' ?></p>
                <div class="t10-cal-box">
                    <div class="t10-cal-head"><span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span><span>CN</span></div>
                    <div class="t10-cal-grid">
                        <?php foreach ($calCells as $c): ?>
                            <?php if ($c === null): ?><span></span>
                            <?php elseif ($c === $weddingDay): ?><span class="t10-wd"><i><?= $c ?></i></span>
                            <?php else: ?><span><?= $c ?></span><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="t10-cd t10-cd-line" data-target="<?= t10e($targetIso) ?>">
                    <div><small>ngày</small><b data-cd="d">0</b></div><i>:</i>
                    <div><small>giờ</small><b data-cd="h">0</b></div><i>:</i>
                    <div><small>phút</small><b data-cd="m">0</b></div><i>:</i>
                    <div><small>giây</small><b data-cd="s">0</b></div>
                </div>
                <p class="t10-cal-left">Còn <b data-cd-days><?= (int)$daysLeft ?></b> ngày nữa là đến đám cưới của <?= t10e($shortGroom) ?> và <?= t10e($shortBride) ?>.</p>
            </div>

            <?php if ($venue !== '' || $address !== ''): ?>
            <!-- ADDRESS: ảnh + địa điểm + bản đồ + đếm ngược -->
            <div class="t10-addr">
                <div class="t10-addr-grid">
                    <div class="t10-addr-photo"<?= $addrImage !== '' ? ' style="background-image:url(\'' . t10e($addrImage) . '\')"' : '' ?>></div>
                    <div class="t10-addr-info">
                        <h3 class="t10-addr-title">ADDRESS</h3>
                        <?php if ($venue !== ''): ?><h4><?= t10e(mb_strtoupper($venue, 'UTF-8')) ?></h4><?php endif; ?>
                        <?php if ($address !== ''): ?><p><?= t10e($address) ?></p><?php endif; ?>
                        <?php if ($mapEmbed !== ''): ?><div class="t10-map"><iframe src="<?= t10e($mapEmbed) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Bản đồ"></iframe></div><?php endif; ?>
                        <?php if ($directionsUrl !== ''): ?><a class="t10-dir" href="<?= t10e($directionsUrl) ?>" target="_blank" rel="noopener">Chỉ đường</a><?php endif; ?>
                    </div>
                </div>
                <div class="t10-cd t10-cd-boxes" data-target="<?= t10e($targetIso) ?>">
                    <div><b data-cd="d">0</b><small>ngày</small></div>
                    <div><b data-cd="h">0</b><small>giờ</small></div>
                    <div><b data-cd="m">0</b><small>phút</small></div>
                    <div><b data-cd="s">0</b><small>giây</small></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="t10-fams">
                <?php foreach ([['groom', 'NHÀ TRAI'], ['bride', 'NHÀ GÁI']] as [$sd, $cap]): ?>
                    <div class="t10-fam">
                        <h3><?= $cap ?></h3>
                        <?php if ($parents[$sd]['father'] !== ''): ?><p><?= t10e(t10parent($parents[$sd]['father'], 'Ông')) ?></p><?php endif; ?>
                        <?php if ($parents[$sd]['mother'] !== ''): ?><p><?= t10e(t10parent($parents[$sd]['mother'], 'Bà')) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ============ 6. ALBUM + TIMELINE (khối vòm dài theo số sự kiện) ============ -->
        <section class="t10-time">
            <?php if (!empty($album)): ?>
            <div class="t10-slides t10-slides-top" data-slides="<?= t10e(json_encode($slidesB, $jsonFlags)) ?>">
                <img class="is-on" src="<?= t10e($slidesB[0]) ?>" alt="Ảnh cưới">
                <img src="" alt="" aria-hidden="true">
            </div>
            <?php endif; ?>
            <?php if (!empty($events)): ?>
            <h2 class="t10-time-title">Timeline</h2>
            <div class="t10-arch">
                <?php foreach ($events as $i => $ev): ?>
                    <div class="t10-ev <?= $i % 2 === 0 ? 't10-ev-a' : 't10-ev-b' ?>">
                        <span class="t10-ev-ico"><?= $t10Icon($ev['title'], $i) ?></span>
                        <?php if ($ev['time'] !== ''): ?><b class="t10-ev-time"><?= t10e($ev['time']) ?></b><?php endif; ?>
                        <?php if ($ev['title'] !== ''): ?><span class="t10-ev-name"><?= t10e($ev['title']) ?></span><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="t10-divider t10-divider-b"><?= $orn ?></div>
            <?php endif; ?>
        </section>

        <!-- ============ 7. SỔ LƯU BÚT ============ -->
        <section class="t10-wish" id="t10Wish" data-api="<?= t10e($wishApi) ?>" data-wedding-id="<?= (int)$wedding['id'] ?>" data-slug="<?= t10e($wedding['slug'] ?? '') ?>">
            <h2 class="t10-wish-title">SỔ LƯU BÚT</h2>
            <form class="t10-wish-form" id="t10WishForm" novalidate>
                <input type="text" id="wishName" name="t10_name" maxlength="80" placeholder="Nhập tên*" autocomplete="name">
                <textarea id="wishMessage" name="t10_msg" rows="4" maxlength="500" placeholder="Nhập lời chúc*"></textarea>
                <div class="t10-wish-actions">
                    <span class="t10-wish-status" id="t10WishStatus" role="status" aria-live="polite"></span>
                    <button type="submit" id="sendWish">GỬI LỜI CHÚC</button>
                </div>
            </form>
            <div class="t10-wish-list" id="wishList">
                <?php foreach ($wishes as $w): ?>
                    <article class="t10-wish-item">
                        <div class="t10-wish-top"><b><?= t10e($w['name']) ?></b><time><?= t10e($w['time']) ?></time></div>
                        <p><?= nl2br(t10e($w['message'])) ?></p>
                    </article>
                <?php endforeach; ?>
                <?php if (empty($wishes)): ?><p class="t10-wish-empty">Hãy là người đầu tiên gửi lời chúc đến cô dâu chú rể.</p><?php endif; ?>
            </div>
        </section>

        <!-- ============ 8. HỘP QUÀ ============ -->
        <section class="t10-gift">
            <p class="t10-gift-label">A LITTLE GIFT</p>
            <h2 class="t10-gift-title">Hộp quà mừng</h2>
            <p class="t10-gift-sub">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>
            <button type="button" class="t10-gift-card" id="t10OpenGift">
                <svg viewBox="0 0 90 84" fill="none" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
                    <path d="M14 36l31 12 31-12v30L45 78 14 66z" fill="#dfe6cf" stroke="#4f5d3a"/>
                    <path d="M45 48v30M30 42v28" stroke="#4f5d3a"/>
                    <path d="M10 28l35 13 35-13-35-12z" fill="#eef1e4" stroke="#4f5d3a"/>
                    <path d="M45 41V22" stroke="#4f5d3a"/>
                    <path d="M45 24C38 8 22 8 26 18c2 5 12 6 19 6zM45 24C52 8 68 8 64 18c-2 5-12 6-19 6z" fill="#fff" stroke="#4f5d3a"/>
                    <path d="M45 24l-5 12 5-3 5 3z" fill="#c3cfa9" stroke="#4f5d3a"/>
                </svg>
                <strong>Gửi quà mừng cưới</strong>
                <span>Nhấn để xem thông tin</span>
            </button>
            <p class="t10-thanks">Thank you for being with us</p>
        </section>

        <!-- ============ 9. KẾT THÚC (nền = story_image) ============ -->
        <section class="t10-ending<?= $endImage === '' ? ' t10-ending-plain' : '' ?>">
            <div class="t10-ending-bg"<?= $endImage !== '' ? ' style="background-image:url(\'' . t10e($endImage) . '\')"' : '' ?>></div>
            <div class="t10-ending-text">
                <p>Cảm ơn bạn đã ghé thăm tấm thiệp nhỏ này.</p>
                <p>Sự hiện diện và lời chúc của bạn<br>là món quà ý nghĩa nhất với chúng mình.</p>
                <p>With love,<br><?= t10e($shortGroom) ?> &amp; <?= t10e($shortBride) ?></p>
            </div>
        </section>
    </main>

    <!-- ============ MODAL HỘP QUÀ MỪNG ============ -->
    <div class="t10-modal" id="t10GiftModal" aria-hidden="true">
        <div class="t10-modal-backdrop" data-close></div>
        <div class="t10-modal-panel" role="dialog" aria-modal="true" aria-label="Hộp quà mừng">
            <div class="t10-modal-head">
                <h3>HỘP QUÀ MỪNG</h3>
                <button type="button" class="t10-modal-close" data-close aria-label="Đóng">&times;</button>
            </div>
            <div class="t10-modal-body">
                <?php if ($giftMode === 'message'): ?>
                    <p class="t10-modal-msg"><?= nl2br(t10e($giftMessage)) ?></p>
                <?php else: ?>
                <div class="t10-qr-grid">
                    <?php foreach ([['Chú Rể', $groomGift, $shortGroom, 'chú rể'], ['Cô Dâu', $brideGift, $shortBride, 'cô dâu']] as [$cap, $g, $fb, $who]):
                        $qr = $g ? t10url($g['qr_image'] ?? '') : '';
                        $pname = $g && !empty($g['person_name']) ? $g['person_name'] : $fb;
                    ?>
                        <div class="t10-qr-col">
                            <h4><?= $cap ?> - <?= t10e($pname) ?></h4>
                            <div class="t10-qr-card">
                                <?php if ($qr !== ''): ?><img src="<?= t10e($qr) ?>" alt="QR mừng cưới <?= $who ?>">
                                <?php else: ?><span>Chưa có mã QR <?= $who ?></span><?php endif; ?>
                            </div>
                            <?php if ($g): ?>
                            <div class="t10-bank">
                                <?php if (!empty($g['bank_name'])): ?><p><?= t10e($g['bank_name']) ?></p><?php endif; ?>
                                <?php if (!empty($g['account_number'])): ?><p><?= t10e($g['account_number']) ?></p><?php endif; ?>
                                <?php if (!empty($g['account_name'])): ?><p><b><?= t10e($g['account_name']) ?></b></p><?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($music !== ''): ?>
        <button type="button" class="t10-music" id="t10Music" aria-label="Bật/tắt nhạc"><i></i><i></i><i></i></button>
        <audio id="t10Audio" loop preload="none"><source src="<?= t10e($music) ?>" type="audio/mpeg"></audio>
    <?php endif; ?>
</div>

<script src="/WeddingProject/templates/template10/script.js"></script>
</body>
</html>