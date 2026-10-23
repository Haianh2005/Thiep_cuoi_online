<?php
/**
 * TEMPLATE 23 – Wedding Sen hồng (Lotus Garden)
 * Được require từ /w/index.php nên đã có sẵn $wedding và $pdo.
 * Template chỉ ĐỌC dữ liệu để hiển thị; riêng sổ lưu bút tự xử lý POST ngay trong file này
 * (không phụ thuộc api/wishes.php).
 * Cột tuỳ chọn trong bảng weddings: groom_photo, bride_photo, lunar_date, directions_url
 * (có thì dùng, chưa có thì tự ẩn / dùng ảnh thay thế).
 * Chạy với: ?guest=Tên khách  -> hiện "Tên khách" ở dòng Trân trọng kính mời.
 */

if (!isset($wedding) || !$wedding) {
    die('Không tìm thấy thông tin đám cưới.');
}
if (!defined('TP_BASE')) define('TP_BASE', '/WeddingProject');

/* ---------- PDO dự phòng ---------- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    foreach (['db', 'conn', 'dbh'] as $n) {
        if (isset($$n) && $$n instanceof PDO) { $pdo = $$n; break; }
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $cfg = __DIR__ . '/../../config/database.php';
    if (is_file($cfg)) require_once $cfg;
}

/* ---------- helper ---------- */
if (!function_exists('t23e')) {
    function t23e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

    /** Chấp nhận URL đầy đủ, đường dẫn uploads/..., hoặc chỉ tên file (tự thêm uploads/$dir/) */
    function t23url($v, $dir = 'weddings') {
        $v = trim((string)($v ?? ''));
        if ($v === '') return '';
        if (preg_match('~^(https?:)?//~i', $v)) return $v;
        $v = ltrim(str_replace('\\', '/', $v), '/');
        if (strpos($v, 'WeddingProject/') === 0) return '/' . $v;
        if (strpos($v, 'uploads/') === 0) return TP_BASE . '/' . $v;
        if (strpos($v, '/') !== false) return TP_BASE . '/' . $v;
        return TP_BASE . '/uploads/' . $dir . '/' . $v;
    }

    /** "Nguyễn Văn Tuấn" -> "Văn Tuấn" (3 chữ trở lên bỏ họ) */
    function t23name($n) {
        $p = preg_split('/\s+/u', trim((string)$n));
        if (count($p) >= 3) array_shift($p);
        return implode(' ', $p);
    }
    function t23last($n) {
        $p = preg_split('/\s+/u', trim((string)$n));
        $l = end($p);
        return ($l !== false && $l !== '') ? $l : (string)$n;
    }
    function t23initial($n) {
        $n = trim((string)$n);
        return $n === '' ? '?' : mb_strtoupper(mb_substr(t23last($n), 0, 1, 'UTF-8'), 'UTF-8');
    }

    function t23cols($pdo, $table) {
        try {
            $cols = [];
            foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $r) $cols[] = $r['Field'];
            return $cols;
        } catch (Throwable $e) { return []; }
    }
    function t23firstcol($cols, $cands) {
        foreach ($cands as $c) if (in_array($c, $cols, true)) return $c;
        return null;
    }
    function t23pick($row, $cands) {
        foreach ($cands as $c) {
            if (isset($row[$c]) && trim((string)$row[$c]) !== '') return trim((string)$row[$c]);
        }
        return '';
    }
    function t23rows($pdo, $sql, $params) {
        if (!($pdo instanceof PDO)) return [];
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return []; }
    }
    /** groom / bride / '' */
    function t23side($row) {
        $s = '';
        foreach (['side', 'type', 'owner', 'party', 'person_type', 'gift_type'] as $k) {
            if (isset($row[$k])) $s .= ' ' . mb_strtolower((string)$row[$k], 'UTF-8');
        }
        if (preg_match('/groom|trai|chu_re|chure|male/', $s) && !preg_match('/female/', $s)) return 'groom';
        if (preg_match('/bride|gai|co_dau|codau|female/', $s)) return 'bride';
        return '';
    }
    /** "Ông Ngô Văn Đức" -> "Ngô Văn Đức" */
    function t23strip_title($s) {
        return trim(preg_replace('/^(ông|bà|ong|ba)\b[\s.:]*/iu', '', trim((string)$s)));
    }
}

$t23wid = (int)($wedding['id'] ?? 0);

/* ---------- Sổ lưu bút: nhận lời chúc gửi thẳng về trang này ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'wish') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $wName = trim((string)($_POST['name'] ?? ''));
    $wMsg  = trim((string)($_POST['message'] ?? ''));
    if ($wName === '' || $wMsg === '') {
        echo json_encode(['ok' => false, 'error' => 'Vui lòng nhập tên và lời chúc.']); exit;
    }
    $wName = mb_substr($wName, 0, 100);
    $wMsg  = mb_substr($wMsg, 0, 1000);
    try {
        if (!($pdo instanceof PDO)) throw new Exception('Không kết nối được cơ sở dữ liệu.');
        $cols    = t23cols($pdo, 'wedding_wishes');
        $nameCol = t23firstcol($cols, ['guest_name', 'name', 'sender_name', 'fullname', 'full_name']);
        $msgCol  = t23firstcol($cols, ['message', 'content', 'wish', 'wish_message', 'wishes']);
        if (!$nameCol || !$msgCol) throw new Exception('Bảng wedding_wishes thiếu cột tên/lời chúc.');
        $fields = ['wedding_id', $nameCol, $msgCol];
        $marks  = ['?', '?', '?'];
        $vals   = [$t23wid, $wName, $wMsg];
        $hasTime = in_array('created_at', $cols, true);
        if ($hasTime) { $fields[] = 'created_at'; $marks[] = 'NOW()'; }
        $sql = 'INSERT INTO wedding_wishes (`' . implode('`,`', $fields) . '`) VALUES (' . implode(',', $marks) . ')';
        $pdo->prepare($sql)->execute($vals);

        /* giờ hiển thị lấy đúng giờ của database, cùng cách với danh sách lời chúc */
        $timeText = '';
        if ($hasTime) {
            try {
                $st = $pdo->prepare('SELECT created_at FROM wedding_wishes WHERE id = ?');
                $st->execute([$pdo->lastInsertId()]);
                $ca = $st->fetchColumn();
                if ($ca) $timeText = date('H:i - d/m/Y', strtotime($ca));
            } catch (Throwable $e) { $timeText = ''; }
        }
        if ($timeText === '') $timeText = date('H:i - d/m/Y');
        echo json_encode(['ok' => true, 'name' => $wName, 'message' => $wMsg, 'time' => $timeText]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => 'Không gửi được lời chúc: ' . $e->getMessage()]);
    }
    exit;
}

/* ---------- thông tin cơ bản ---------- */
$groomFull  = trim((string)($wedding['groom_name'] ?? ''));
$brideFull  = trim((string)($wedding['bride_name'] ?? ''));
$groomShort = t23name($groomFull);
$brideShort = t23name($brideFull);
$venue      = trim((string)($wedding['venue'] ?? ''));
$address    = trim((string)($wedding['address'] ?? ''));
$lunarDate  = trim((string)($wedding['lunar_date'] ?? ''));
$story      = trim((string)($wedding['story'] ?? ''));
if ($story === '') {
    $story = 'Hai chúng mình gặp nhau như một sự sắp đặt dịu dàng của duyên phận. Cảm ơn bạn đã đồng hành cùng chúng mình trên chặng đường ấy và cùng chúng mình đón ngày trọng đại.';
}
$bgUrl      = t23url($wedding['background_image'] ?? '', 'weddings');
$storyImg   = t23url($wedding['story_image'] ?? '', 'weddings');
$groomPhoto = t23url($wedding['groom_photo'] ?? '', 'weddings');
$bridePhoto = t23url($wedding['bride_photo'] ?? '', 'weddings');
$musicUrl   = t23url($wedding['music'] ?? '', 'music');
$guest      = mb_substr(trim(strip_tags((string)($_GET['guest'] ?? ''))), 0, 60);
if ($guest === '') $guest = 'Quý Khách';

$base = TP_BASE . '/templates/' . basename(__DIR__) . '/';
$A    = $base . 'assets/';

/* ---------- ngày giờ ---------- */
$weekdayNames = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];
$dateObject = null;
if (!empty($wedding['wedding_date'])) {
    try { $dateObject = new DateTime($wedding['wedding_date']); } catch (Exception $e) { $dateObject = null; }
}
$wTime = '';
if (!empty($wedding['wedding_time']) && strtotime($wedding['wedding_time']) !== false) {
    $wTime = date('H:i', strtotime($wedding['wedding_time']));
}
$day = $month = $year = $weekday = '';
$monthN = 0; $dayN = 0; $calCells = [];
if ($dateObject) {
    $day = $dateObject->format('d');
    $month = $dateObject->format('m');
    $year = $dateObject->format('Y');
    $monthN = (int)$dateObject->format('n');
    $dayN = (int)$dateObject->format('j');
    $weekday = mb_strtoupper($weekdayNames[(int)$dateObject->format('w')], 'UTF-8');
    $first = new DateTime($dateObject->format('Y-m-01'));
    $offset = (int)$first->format('N') - 1;          /* tuần bắt đầu từ thứ Hai */
    $dim = (int)$first->format('t');
    for ($i = 0; $i < $offset; $i++) $calCells[] = null;
    for ($d = 1; $d <= $dim; $d++) $calCells[] = $d;
}

/* ---------- album ---------- */
$pc  = ($pdo instanceof PDO) ? t23cols($pdo, 'wedding_photos') : [];
$ord = in_array('sort_order', $pc, true) ? 'sort_order ASC, id ASC' : 'id ASC';
$album = [];
foreach (t23rows($pdo, "SELECT * FROM wedding_photos WHERE wedding_id = ? ORDER BY $ord", [$t23wid]) as $r) {
    $u = t23url(t23pick($r, ['image_path', 'image', 'photo', 'photo_path', 'file', 'filename', 'path', 'url']), 'weddings');
    if ($u !== '') $album[] = $u;
}
/* ảnh cặp đôi ở phần "Love Story": ảnh album đầu tiên, không có thì dùng ảnh nền */
$coupleTop = $album[0] ?? ($bgUrl ?: $storyImg);
/* lưới album: nếu album nhiều ảnh thì bỏ ảnh đầu (đã dùng ở trên) */
$mosaicSrc = count($album) > 6 ? array_slice($album, 1) : $album;
$mosaic = array_slice($mosaicSrc, 0, 5);
$moreAlbum = array_slice($mosaicSrc, 5);

/* ---------- bố mẹ hai bên ---------- */
$parents = [
    'groom' => ['father' => '', 'mother' => ''],
    'bride' => ['father' => '', 'mother' => ''],
];
foreach (t23rows($pdo, 'SELECT * FROM wedding_parents WHERE wedding_id = ? ORDER BY id ASC', [$t23wid]) as $i => $r) {
    $side = t23side($r);
    if ($side === '') $side = $i === 0 ? 'groom' : 'bride';
    $f = t23pick($r, ['father_name', 'father']);
    $m = t23pick($r, ['mother_name', 'mother']);
    if ($f !== '') $parents[$side]['father'] = $f;
    if ($m !== '') $parents[$side]['mother'] = $m;
    if ($f === '' && $m === '') {
        $n = t23pick($r, ['parent_name', 'name', 'full_name']);
        $role = mb_strtolower(t23pick($r, ['parent_role', 'role', 'relation', 'relationship']), 'UTF-8');
        if ($n !== '') {
            if (preg_match('/mother|mẹ|me|má|bà/u', $role)) $parents[$side]['mother'] = $n;
            else $parents[$side]['father'] = $n;
        }
    }
}

/* ---------- lịch trình ---------- */
$events = [];
foreach (t23rows($pdo, 'SELECT * FROM wedding_events WHERE wedding_id = ? ORDER BY id ASC', [$t23wid]) as $r) {
    $t = t23pick($r, ['event_time', 'time', 'start_time']);
    if ($t !== '' && strtotime($t) !== false) $t = date('H:i', strtotime($t));
    $title = t23pick($r, ['event_name', 'title', 'name', 'label', 'event_title']);
    $events[] = ['sort' => isset($r['sort_order']) ? (int)$r['sort_order'] : 0, 'time' => $t, 'title' => $title];
}
usort($events, function ($a, $b) { return $a['sort'] <=> $b['sort']; });

/* ---------- quà mừng ---------- */
$giftMode = 'bank';
$giftMessage = '';
$groomGift = null;
$brideGift = null;
foreach (t23rows($pdo, 'SELECT * FROM wedding_gifts WHERE wedding_id = ? ORDER BY id ASC', [$t23wid]) as $i => $g) {
    $mode = $g['gift_mode'] ?? '';
    if ($mode === 'message') $giftMode = 'message';
    if ($giftMessage === '' && !empty($g['gift_message'])) $giftMessage = $g['gift_message'];
    if ($mode !== 'message') {
        $s = t23side($g);
        if ($s === '') $s = $i === 0 ? 'groom' : 'bride';
        if ($s === 'groom') $groomGift = $g;
        if ($s === 'bride') $brideGift = $g;
    }
}
if ($giftMessage === '') $giftMessage = 'Sự hiện diện của bạn là niềm vui của gia đình chúng tôi.';

/* ---------- lời chúc đã gửi ---------- */
$wishes = [];
foreach (t23rows($pdo, 'SELECT * FROM wedding_wishes WHERE wedding_id = ? ORDER BY id DESC LIMIT 50', [$t23wid]) as $r) {
    $wishes[] = [
        'name' => t23pick($r, ['guest_name', 'name', 'sender_name', 'fullname', 'full_name']),
        'msg'  => t23pick($r, ['message', 'content', 'wish', 'wish_message', 'wishes']),
        'time' => !empty($r['created_at']) ? date('H:i - d/m/Y', strtotime($r['created_at'])) : '',
    ];
}

/* ---------- bản đồ ---------- */
$mapQuery = trim($venue !== '' && $address !== '' ? $venue . ', ' . $address : ($address !== '' ? $address : $venue));
$dirUrl = !empty($wedding['directions_url']) ? $wedding['directions_url']
    : ($mapQuery !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($mapQuery) : '');
$venueLine = $venue !== '' ? (preg_match('/^tại\b/iu', $venue) ? $venue : 'Tại ' . $venue) : '';

/* ---------- icon timeline ---------- */
$t23Icons = [
    '<circle cx="24" cy="9" r="4.5"/><path d="M17 20h14v14h-3v11h-8V34h-3z"/>',
    '<circle cx="18" cy="11" r="4.5"/><path d="M11 22h14v14h-3v9h-8v-9h-3z"/><path d="M36 9c-2-3-6-1-5 2 .8 2.500 5 5 5 5s4.200-2.500 5-5c1-3-3-5-5-2z"/>',
    '<circle cx="24" cy="31" r="11"/><path d="M19 15l5-6 5 6-5 5z"/><path d="M8 10v5M5.500 12.500h5M40 8v4M38 10h4"/>',
    '<path d="M6 28h24v16H6z"/><path d="M6 28l4-5h16l4 5"/><circle cx="18" cy="36" r="4"/><path d="M34 12h8v32h-8z"/>',
];
$giftSvg = '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="26" width="48" height="12" rx="2"/><path d="M12 38v18h40V38"/><path d="M32 26v30"/><path d="M32 26c-4-10-16-12-16-5 0 4 8 5 16 5zM32 26c4-10 16-12 16-5 0 4-8 5-16 5z"/></svg>';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thiệp cưới <?= t23e($groomFull) ?> &amp; <?= t23e($brideFull) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&display=swap&subset=vietnamese" rel="stylesheet">
    <link rel="stylesheet" href="/WeddingProject/templates/template24/style.css">
    <noscript><style>.template23-page .t23-reveal{opacity:1!important;transform:none!important}.template23-page .t23-opening{display:none!important}body.t23-locked{overflow:auto!important}</style></noscript>
</head>
<body class="t23-body t23-locked">

<div class="template23-page">

    <!-- ============ 0. MỞ THIỆP ============ -->
    <section class="t23-opening" id="t23Opening" role="button" tabindex="0" aria-label="Chạm để mở thiệp"
             style="--t23-wash:url('<?= t23e($A) ?>watercolor-bg.webp')">
        <img class="t23-open-crane" src="<?= t23e($A) ?>crane.webp" alt="" aria-hidden="true">
        <div class="t23-open-body">
            <p class="t23-open-kicker">Thiệp mời đám cưới</p>
            <h1 class="t23-open-names">
                <span><?= t23e($groomShort) ?></span>
                <em>&amp;</em>
                <span><?= t23e($brideShort) ?></span>
            </h1>
            <?php if ($dateObject): ?><p class="t23-open-date"><?= t23e($day) ?> · <?= t23e($month) ?> · <?= t23e($year) ?></p><?php endif; ?>
            <p class="t23-open-tap">Chạm để mở thiệp</p>
        </div>
        <img class="t23-open-lotus" src="<?= t23e($A) ?>lotus-cluster.webp" alt="" aria-hidden="true">
    </section>

    <main class="t23-main">

        <!-- ============ 1. BÌA ============ -->
        <section class="t23-hero">
            <?php if ($bgUrl !== ''): ?><img src="<?= t23e($bgUrl) ?>" alt="Ảnh cưới"><?php endif; ?>
            <div class="t23-hero-names">
                <span class="gn"><?= t23e($groomShort) ?></span>
                <span class="amp">&amp;</span>
                <span class="bn"><?= t23e($brideShort) ?></span>
            </div>
        </section>

        <section class="t23-cover">
            <h2 class="t23-cover-title t23-reveal">Ngày Chung Đôi</h2>
            <?php if ($dateObject): ?><p class="t23-cover-date t23-reveal" style="--d:.1s"><?= t23e($day) ?>·<?= t23e($month) ?>·<?= t23e($year) ?></p><?php endif; ?>
            <p class="t23-cover-text t23-reveal" style="--d:.2s">
                Chúng tôi đã cùng nhau viết nên<br>
                câu chuyện tình yêu suốt những năm qua<br>
                và chương tiếp theo sẽ bắt đầu về sau
            </p>
            <img class="t23-cover-lotus" src="<?= t23e($A) ?>lotus-corner.webp" alt="" aria-hidden="true">
        </section>

        <!-- ============ 2. THÔNG TIN LỄ CƯỚI ============ -->
        <section class="t23-info">
            <div class="t23-family t23-reveal">
                <?php foreach (['groom' => 'Nhà trai', 'bride' => 'Nhà gái'] as $sd => $cap): ?>
                    <div class="t23-fam">
                        <h3><?= t23e($cap) ?></h3>
                        <?php if ($parents[$sd]['father'] !== ''): ?><p>Ông: <?= t23e(t23strip_title($parents[$sd]['father'])) ?></p><?php endif; ?>
                        <?php if ($parents[$sd]['mother'] !== ''): ?><p>Bà: <?= t23e(t23strip_title($parents[$sd]['mother'])) ?></p><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="t23-names t23-reveal" style="--d:.1s">
                <span class="t23-hy" aria-hidden="true">囍</span>
                <span class="n1"><?= t23e(mb_strtoupper($groomShort, 'UTF-8')) ?></span>
                <span class="n2"><?= t23e(mb_strtoupper($brideShort, 'UTF-8')) ?></span>
            </div>

            <p class="t23-invite t23-reveal">TRÂN TRỌNG KÍNH MỜI</p>
            <p class="t23-guest t23-reveal" style="--d:.1s"><?= t23e($guest) ?></p>
            <p class="t23-feast t23-reveal">DỰ BỮA TIỆC CHUNG VUI</p>
            <?php if ($wTime !== '' || $weekday !== ''): ?>
                <p class="t23-when t23-reveal">ĐƯỢC TỔ CHỨC VÀO LÚC <?= t23e($wTime) ?><?= ($wTime !== '' && $weekday !== '') ? ', ' : '' ?><?= t23e($weekday) ?></p>
            <?php endif; ?>
            <?php if ($dateObject): ?><p class="t23-bigdate t23-reveal"><?= t23e($day) ?>.<?= t23e($month) ?>.<?= t23e($year) ?></p><?php endif; ?>
            <?php if ($lunarDate !== ''): ?><p class="t23-lunar t23-reveal">(<?= t23e($lunarDate) ?>)</p><?php endif; ?>

            <?php if ($venueLine !== '' || $address !== ''): ?>
                <div class="t23-place t23-reveal">
                    <?php if ($venueLine !== ''): ?><strong><?= t23e(mb_strtoupper($venueLine, 'UTF-8')) ?></strong><?php endif; ?>
                    <?php if ($address !== ''): ?><span><?= t23e($address) ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($dirUrl !== ''): ?>
                <a class="t23-dir t23-reveal" href="<?= t23e($dirUrl) ?>" target="_blank" rel="noopener">XEM CHỈ ĐƯỜNG</a>
            <?php endif; ?>
            <img class="t23-info-lotus" src="<?= t23e($A) ?>lotus-cluster.webp" alt="" aria-hidden="true" loading="lazy">
        </section>

        <!-- ============ 3. LOVE STORY + CHÚ RỂ / CÔ DÂU SO LE ============ -->
        <section class="t23-story">
            <div class="t23-story-top">
                <?php if ($coupleTop !== ''): ?><img src="<?= t23e($coupleTop) ?>" alt="Ảnh cặp đôi" loading="lazy"><?php endif; ?>
                <span class="t23-story-script">Love Story</span>
            </div>

            <div class="t23-duo">
                <div class="t23-duo-col is-groom t23-reveal">
                    <div class="t23-duo-label"><em>Chú rể</em><strong><?= t23e(mb_strtoupper($groomShort, 'UTF-8')) ?></strong></div>
                    <div class="t23-duo-photo">
                        <?php if ($groomPhoto !== ''): ?><img src="<?= t23e($groomPhoto) ?>" alt="Ảnh chú rể" loading="lazy">
                        <?php else: ?><span><?= t23e(t23initial($groomFull)) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="t23-duo-col is-bride t23-reveal" style="--d:.15s">
                    <div class="t23-duo-photo">
                        <?php if ($bridePhoto !== ''): ?><img src="<?= t23e($bridePhoto) ?>" alt="Ảnh cô dâu" loading="lazy">
                        <?php else: ?><span><?= t23e(t23initial($brideFull)) ?></span><?php endif; ?>
                    </div>
                    <div class="t23-duo-label"><em>Cô dâu</em><strong><?= t23e(mb_strtoupper($brideShort, 'UTF-8')) ?></strong></div>
                </div>
            </div>

            <p class="t23-story-text t23-reveal"><?= nl2br(t23e($story)) ?></p>
        </section>

        <!-- ============ 4. LỊCH ============ -->
        <?php if ($dateObject): ?>
        <section class="t23-cal">
            <div class="t23-cal-head">
                <img src="<?= t23e($A) ?>lotus-corner.webp" alt="" aria-hidden="true" loading="lazy">
                <h2>Tháng <?= (int)$monthN ?></h2>
            </div>
            <div class="t23-cal-box t23-reveal">
                <div class="t23-cal-grid">
                    <?php foreach ($calCells as $c): ?>
                        <?php if ($c === null): ?><i></i>
                        <?php else: ?><span class="<?= $c === $dayN ? 'is-day' : '' ?>"><?= $c ?></span><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ============ 5. ALBUM ============ -->
        <?php if ($mosaic): ?>
        <section class="t23-album">
            <?php if (count($mosaic) >= 5): ?>
                <div class="t23-mosaic">
                    <figure class="m1"><img src="<?= t23e($mosaic[0]) ?>" alt="" loading="lazy"><figcaption>ALBUM</figcaption></figure>
                    <figure class="m2"><img src="<?= t23e($mosaic[1]) ?>" alt="" loading="lazy"></figure>
                    <div class="m-text"><span>of love</span></div>
                    <figure class="m3"><img src="<?= t23e($mosaic[2]) ?>" alt="" loading="lazy"></figure>
                    <figure class="m4"><img src="<?= t23e($mosaic[3]) ?>" alt="" loading="lazy"></figure>
                    <figure class="m5"><img src="<?= t23e($mosaic[4]) ?>" alt="" loading="lazy"></figure>
                </div>
            <?php else: ?>
                <div class="t23-simple">
                    <?php foreach ($mosaic as $i => $u): ?>
                        <figure class="<?= $i === 0 ? 'is-wide' : '' ?>"><img src="<?= t23e($u) ?>" alt="" loading="lazy"><?php if ($i === 0): ?><figcaption>ALBUM</figcaption><?php endif; ?></figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($moreAlbum): ?>
                <div class="t23-simple t23-simple-more">
                    <?php foreach ($moreAlbum as $u): ?><figure><img src="<?= t23e($u) ?>" alt="" loading="lazy"></figure><?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- ============ 6. TIMELINE ============ -->
        <?php if ($events): ?>
        <section class="t23-timeline">
            <p class="t23-tl-label t23-reveal">WEDDING TIMELINE</p>
            <h2 class="t23-tl-title t23-reveal" style="--d:.1s">Lịch trình ngày cưới</h2>
            <div class="t23-tl t23-reveal" style="--d:.2s">
                <?php foreach ($events as $i => $ev): ?>
                    <div class="t23-tl-item">
                        <b><?= t23e($ev['time']) ?></b>
                        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $t23Icons[$i % count($t23Icons)] ?></svg>
                        <em><?= t23e($ev['title']) ?></em>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ============ 7. ẢNH STORY LÀM MỜ DẦN THEO TONE ẢNH ============ -->
        <?php if ($storyImg !== ''): ?>
        <section class="t23-mist">
            <div class="t23-mist-bg" style="background-image:url('<?= t23e($storyImg) ?>')"></div>
            <img class="t23-mist-img" src="<?= t23e($storyImg) ?>" alt="Ảnh cưới" loading="lazy">
        </section>
        <?php endif; ?>

        <!-- ============ 8. SỔ LƯU BÚT ============ -->
        <div class="t23-wash" style="--t23-wash:url('<?= t23e($A) ?>watercolor-bg.webp')">
            <section class="t23-guestbook" id="t23Guestbook">
                <div class="t23-gb-card t23-reveal">
                    <h2>SỔ LƯU BÚT</h2>
                    <form id="t23WishForm" autocomplete="off">
                        <input type="text" id="t23WishName" maxlength="100" placeholder="Nhập tên*" required>
                        <textarea id="t23WishMessage" maxlength="1000" placeholder="Nhập lời chúc*" required></textarea>
                        <div class="t23-gb-actions"><button type="submit" id="t23SendWish">GỬI LỜI CHÚC</button></div>
                        <p class="t23-gb-status" id="t23WishStatus" role="status" aria-live="polite"></p>
                    </form>
                    <div class="t23-wish-list" id="t23WishList">
                        <?php foreach ($wishes as $w): ?>
                            <article class="t23-wish">
                                <strong><?= t23e($w['name'] !== '' ? $w['name'] : 'Một người bạn') ?></strong>
                                <p><?= t23e($w['msg']) ?></p>
                                <?php if ($w['time'] !== ''): ?><time><?= t23e($w['time']) ?></time><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <!-- ============ 9. HỘP QUÀ ============ -->
            <section class="t23-gift">
                <p class="t23-gift-eyebrow t23-reveal">A LITTLE GIFT</p>
                <h2 class="t23-gift-title t23-reveal" style="--d:.1s">Hộp quà mừng</h2>
                <p class="t23-gift-sub t23-reveal" style="--d:.15s">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>
                <button type="button" class="t23-gift-card t23-reveal" id="t23OpenGift" style="--d:.2s">
                    <?= $giftSvg ?>
                    <strong>Gửi quà mừng cưới</strong>
                    <span>Nhấn để xem thông tin</span>
                </button>
                <p class="t23-gift-foot t23-reveal">Thank you for being with us</p>
            </section>
        </div>

        <!-- ============ 10. KẾT: ẢNH STORY CAO BẰNG NỬA MÀN HÌNH ĐIỆN THOẠI ============ -->
        <section class="t23-finale<?= ($storyImg === '' && $bgUrl === '') ? ' is-plain' : '' ?>">
            <?php $finalImg = $storyImg !== '' ? $storyImg : $bgUrl; ?>
            <div class="t23-finale-bg"<?= $finalImg !== '' ? ' style="background-image:url(\'' . t23e($finalImg) . '\')"' : '' ?>></div>
            <div class="t23-finale-text t23-reveal">
                <span class="script">Thank you</span>
                <p>Cảm ơn bạn đã dành tình cảm cho chúng mình! Sự hiện diện của bạn là món quà ý nghĩa nhất.</p>
                <small><?= t23e($groomShort) ?> &amp; <?= t23e($brideShort) ?></small>
            </div>
        </section>
    </main>

    <!-- ============ THANH NỔI: GỬI LỜI CHÚC / QUÀ / NHẠC ============ -->
    <div class="t23-dock" id="t23Dock">
        <a class="t23-dock-wish" href="#t23Guestbook" id="t23DockWish">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
            Gửi lời chúc...
        </a>
        <div class="t23-dock-right">
            <?php if ($musicUrl !== ''): ?>
            <button type="button" class="t23-dock-btn is-off" id="t23Music" aria-label="Bật/tắt nhạc" aria-pressed="false">
                <svg class="ic-on" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10v4h4l5 4V6L7 10H3z" fill="currentColor"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18 6a8.5 8.5 0 0 1 0 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                <svg class="ic-off" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10v4h4l5 4V6L7 10H3z" fill="currentColor"/><path d="M16 9l5 6M21 9l-5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
            <?php endif; ?>
            <button type="button" class="t23-dock-btn" id="t23DockGift" aria-label="Hộp quà mừng"><?= $giftSvg ?></button>
        </div>
    </div>

    <!-- ============ MODAL HỘP QUÀ MỪNG ============ -->
    <div class="t23-modal" id="t23GiftModal" aria-hidden="true">
        <div class="t23-modal-backdrop" data-close></div>
        <div class="t23-modal-panel" role="dialog" aria-modal="true" aria-label="Hộp quà mừng">
            <div class="t23-modal-head">
                <h3>HỘP QUÀ MỪNG</h3>
                <button type="button" class="t23-modal-close" data-close aria-label="Đóng">&times;</button>
            </div>
            <div class="t23-modal-body">
                <?php if ($giftMode === 'message'): ?>
                    <p class="t23-modal-msg"><?= nl2br(t23e($giftMessage)) ?></p>
                <?php else: ?>
                <div class="t23-qr-grid">
                    <?php foreach ([['Chú Rể', $groomGift, $groomFull, 'chú rể'], ['Cô Dâu', $brideGift, $brideFull, 'cô dâu']] as $col):
                        list($cap, $g, $fallbackName, $who) = $col;
                        $qr = $g ? t23url($g['qr_image'] ?? ($g['qr'] ?? ''), 'gifts') : '';
                        $pname = ($g && !empty($g['person_name'])) ? $g['person_name'] : $fallbackName;
                    ?>
                        <div class="t23-qr-col">
                            <h4><?= t23e($cap) ?> - <?= t23e($pname) ?></h4>
                            <div class="t23-qr-card">
                                <?php if ($qr !== ''): ?><img src="<?= t23e($qr) ?>" alt="QR mừng cưới <?= t23e($who) ?>">
                                <?php else: ?><span>Chưa có mã QR <?= t23e($who) ?></span><?php endif; ?>
                            </div>
                            <?php if ($g): ?>
                            <div class="t23-bank">
                                <?php if (!empty($g['bank_name'])): ?><p><?= t23e($g['bank_name']) ?></p><?php endif; ?>
                                <?php if (!empty($g['account_number'])): ?><p><?= t23e($g['account_number']) ?></p><?php endif; ?>
                                <?php if (!empty($g['account_name'])): ?><p><b><?= t23e($g['account_name']) ?></b></p><?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($qr !== ''): ?>
                                <a class="t23-save" href="<?= t23e($qr) ?>" download>Lưu QR</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($musicUrl !== ''): ?>
        <audio id="t23Audio" loop preload="none"><source src="<?= t23e($musicUrl) ?>" type="audio/mpeg"></audio>
    <?php endif; ?>
</div>

<script src="/WeddingProject/templates/template24/script.js"></script>
</body>
</html>