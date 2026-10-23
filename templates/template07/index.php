<?php
/*
|--------------------------------------------------------------------------
| TEMPLATE 07 – TƠ DUYÊN - ĐỎ
|--------------------------------------------------------------------------
| w/index.php đã tìm wedding theo slug và truyền vào $wedding (+ $pdo).
| Template chỉ ĐỌC dữ liệu và hiển thị. Không lưu gì.
| Cột tuỳ chọn (groom_photo, bride_photo, lunar_date, map_url, directions_url):
| có thì dùng, chưa có thì tự ẩn / dùng fallback.
|--------------------------------------------------------------------------
*/

if (!isset($wedding) || !$wedding) {
    die("Không tìm thấy thông tin đám cưới.");
}

/* ---------- helper ---------- */
if (!function_exists('t7e')) {
    function t7e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}
/* Đường dẫn tuyệt đối/http giữ nguyên, còn lại thêm /WeddingProject/ (không lặp tiền tố). */
if (!function_exists('t7url')) {
    function t7url($p) {
        $p = trim((string)($p ?? ''));
        if ($p === '') return '';
        if (preg_match('#^(https?:)?//#i', $p) || $p[0] === '/') return $p;
        return '/WeddingProject/' . ltrim($p, '/');
    }
}
/* "Nguyễn Duy Minh" -> "Duy Minh" (như ảnh mẫu); tên 1-2 chữ giữ nguyên */
if (!function_exists('t7name')) {
    function t7name($n) {
        $p = preg_split('/\s+/u', trim((string)$n));
        if (count($p) >= 3) array_shift($p);
        return implode(' ', $p);
    }
}
if (!function_exists('t7last')) {
    function t7last($n) {
        $p = preg_split('/\s+/u', trim((string)$n));
        $l = end($p);
        return ($l !== false && $l !== '') ? $l : (string)$n;
    }
}
if (!function_exists('t7initial')) {
    function t7initial($n) {
        $n = trim((string)$n);
        return $n === '' ? '?' : mb_strtoupper(mb_substr(t7last($n), 0, 1, 'UTF-8'), 'UTF-8');
    }
}

/* ---------- Sổ lưu bút: xử lý ngay trong template (không cần api/wishes.php) ---------- */
if (!function_exists('t7cols')) {
    function t7cols($pdo, $table) {
        try {
            $cols = [];
            foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $r) $cols[] = $r['Field'];
            return $cols;
        } catch (Throwable $e) { return []; }
    }
    function t7firstcol($cols, $cands) {
        foreach ($cands as $c) if (in_array($c, $cols, true)) return $c;
        return null;
    }
    function t7pick($row, $cands) {
        foreach ($cands as $c) if (isset($row[$c]) && trim((string)$row[$c]) !== '') return trim((string)$row[$c]);
        return '';
    }
}
$t7wid = (int)($wedding['id'] ?? 0);

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
        $cols    = t7cols($pdo, 'wedding_wishes');
        $nameCol = t7firstcol($cols, ['guest_name', 'name', 'sender_name', 'fullname', 'full_name']);
        $msgCol  = t7firstcol($cols, ['message', 'content', 'wish', 'wish_message', 'wishes']);
        if (!$nameCol || !$msgCol) throw new Exception('Bảng wedding_wishes thiếu cột tên/lời chúc.');
        $fields = ['wedding_id', $nameCol, $msgCol];
        $marks  = ['?', '?', '?'];
        $vals   = [$t7wid, $wName, $wMsg];
        if (in_array('created_at', $cols, true)) { $fields[] = 'created_at'; $marks[] = 'NOW()'; }
        $sql = 'INSERT INTO wedding_wishes (`' . implode('`,`', $fields) . '`) VALUES (' . implode(',', $marks) . ')';
        $pdo->prepare($sql)->execute($vals);
        echo json_encode(['ok' => true, 'name' => $wName, 'message' => $wMsg, 'time' => date('H:i - d/m/Y')]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => 'Không gửi được lời chúc: ' . $e->getMessage()]);
    }
    exit;
}

/* Lời chúc đã gửi (hiện sẵn khi tải trang) */
$t7wishes = [];
try {
    $st = $pdo->prepare('SELECT * FROM wedding_wishes WHERE wedding_id = ? ORDER BY id DESC LIMIT 50');
    $st->execute([$t7wid]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $t7wishes[] = [
            'name' => t7pick($r, ['guest_name', 'name', 'sender_name', 'fullname', 'full_name']),
            'msg'  => t7pick($r, ['message', 'content', 'wish', 'wish_message', 'wishes']),
            'time' => !empty($r['created_at']) ? date('H:i - d/m/Y', strtotime($r['created_at'])) : '',
        ];
    }
} catch (Throwable $e) { $t7wishes = []; }

/* Thư mục template tự nhận theo tên folder -> đổi tên folder vẫn chạy */
$base = '/WeddingProject/templates/' . basename(__DIR__) . '/';

/* Họa tiết rồng phượng: đang dùng link giống template01.
   Muốn chạy offline: tải file về templates/<folder>/assets/images/ rồi đổi 3 dòng này. */
$imgPattern = 'https://chungdoi.com/images/themes/longphung-v2-red/rong-phuong.webp';
$imgCloud   = 'https://chungdoi.com/images/themes/longphung-v2-red/cloud.webp';

/* ---------- thông tin cơ bản ---------- */
$groomName = $wedding['groom_name'] ?? '';
$brideName = $wedding['bride_name'] ?? '';
$venue     = $wedding['venue'] ?? '';
$address   = $wedding['address'] ?? '';
$groomPhoto = t7url($wedding['groom_photo'] ?? '');
$bridePhoto = t7url($wedding['bride_photo'] ?? '');
$lunarDate = trim((string)($wedding['lunar_date'] ?? ''));
$music     = t7url($wedding['music'] ?? '');
$storyImage = t7url($wedding['story_image'] ?? '');   /* ảnh 3 */
$bgImage    = t7url($wedding['background_image'] ?? ''); /* ảnh nền màn kết */
$shortGroom = t7last($groomName);
$shortBride = t7last($brideName);

/* ---------- ngày giờ ---------- */
$weekdayNames = ['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
$dateObject = null;
if (!empty($wedding['wedding_date'])) {
    try { $dateObject = new DateTime($wedding['wedding_date']); } catch (Exception $e) { $dateObject = null; }
}
$timeHi = $timeAmPm = '';
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
$photoStmt = $pdo->prepare("SELECT image_path FROM wedding_photos WHERE wedding_id = :wedding_id ORDER BY sort_order ASC, id ASC");
$photoStmt->execute(['wedding_id' => $wedding['id']]);
$album = [];
foreach ($photoStmt->fetchAll() as $row) {
    $u = t7url($row['image_path'] ?? '');
    if ($u !== '') $album[] = $u;
}

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
    if (isset($parents[$side][$role])) $parents[$side][$role] = $p['parent_name'] ?? '';
    if (isset($parents[$side]) && !empty($p['address'])) $parents[$side]['address'] = $p['address'];
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
    if ($mode === 'message') $giftMode = 'message';
    if ($giftMessage === '' && !empty($g['gift_message'])) $giftMessage = $g['gift_message'];
    if ($mode !== 'message') {
        if (($g['person_type'] ?? '') === 'groom') $groomGift = $g;
        if (($g['person_type'] ?? '') === 'bride') $brideGift = $g;
    }
}
if ($giftMessage === '') $giftMessage = 'Sự hiện diện của bạn là niềm vui của gia đình chúng tôi.';

/* ---------- bản đồ ---------- */
$mapQuery = trim($venue !== '' && $address !== '' ? $venue . ', ' . $address : ($address !== '' ? $address : $venue));
$mapEmbed = $mapQuery !== '' ? 'https://www.google.com/maps?q=' . urlencode($mapQuery) . '&output=embed&hl=vi' : '';
$dirUrl = !empty($wedding['directions_url']) ? $wedding['directions_url']
    : ($mapQuery !== '' ? 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($mapQuery) : '');

/* ---------- icon timeline (theo thứ tự như ảnh mẫu) ---------- */
$t7Icons = [
    '<circle cx="24" cy="9" r="4.5"/><path d="M17 20h14v14h-3v11h-8V34h-3z"/>',
    '<circle cx="18" cy="11" r="4.5"/><path d="M11 22h14v14h-3v9h-8v-9h-3z"/><path d="M36 9c-2-3-6-1-5 2 .8 2.500 5 5 5 5s4.200-2.500 5-5c1-3-3-5-5-2z"/>',
    '<circle cx="24" cy="31" r="11"/><path d="M19 15l5-6 5 6-5 5z"/><path d="M8 10v5M5.500 12.500h5M40 8v4M38 10h4"/>',
    '<path d="M6 28h24v16H6z"/><path d="M6 28l4-5h16l4 5"/><circle cx="18" cy="36" r="4"/><path d="M34 12h8v32h-8z"/>',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t7e($groomName) ?> &amp; <?= t7e($brideName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Cormorant+Garamond:wght@500;600;700&family=Montserrat:wght@400;500;600;700&family=Great+Vibes&display=swap&subset=vietnamese" rel="stylesheet">
    <link rel="stylesheet" href="/WeddingProject/templates/template07/style.css">
    <noscript><style>.template07-page .t7-reveal{opacity:1!important;filter:none!important;transform:none!important}</style></noscript>
</head>
<body class="t7-body t7-locked">

<div class="template07-page"
     data-target="<?= t7e($targetIso) ?>"
     data-short-groom="<?= t7e($shortGroom) ?>"
     data-short-bride="<?= t7e($shortBride) ?>"
     style="--t7-pattern:url('<?= t7e($imgPattern) ?>')">

    <!-- ============ 1. MỞ THIỆP ============ -->
    <section id="t7Opening" class="t7-opening">
        <div class="t7-hy-rain" aria-hidden="true">
            <?php for ($i = 0; $i < 12; $i++): ?><span>囍</span><?php endfor; ?>
        </div>
        <div class="t7-open-card" style="--t7-pattern:url('<?= t7e($imgPattern) ?>')">
            <img class="t7-open-cloud t7-cl-a" src="<?= t7e($imgCloud) ?>" alt="">
            <img class="t7-open-cloud t7-cl-b" src="<?= t7e($imgCloud) ?>" alt="">
            <div class="t7-seal"><span>囍</span></div>
            <h1 class="t7-open-names">
                <span><?= t7e(t7name($groomName)) ?></span>
                <b>&amp;</b>
                <span><?= t7e(t7name($brideName)) ?></span>
            </h1>
            <div class="t7-open-divider"><i></i><em>❦</em><i></i></div>
            <button type="button" id="t7OpenBtn" class="t7-open-btn">Mở thiệp</button>
        </div>
    </section>

    <main id="t7Main" class="t7-main">
        <div class="t7-topbar" aria-hidden="true"></div>

        <!-- ============ 2. CHÚ RỂ - CÔ DÂU + INVITATION ============ -->
        <section class="t7-couple">
            <div class="t7-band" aria-hidden="true"></div>
            <div class="t7-couple-row t7-reveal">
                <div class="t7-cp">
                    <div class="t7-circle">
                        <?php if ($groomPhoto !== ''): ?>
                            <img src="<?= t7e($groomPhoto) ?>" alt="Ảnh chú rể" loading="lazy">
                        <?php else: ?><span><?= t7e(t7initial($groomName)) ?></span><?php endif; ?>
                    </div>
                    <small>Chú rể</small>
                    <strong><?= t7e(t7name($groomName)) ?></strong>
                </div>
                <div class="t7-emblem" aria-hidden="true"><span>囍</span></div>
                <div class="t7-cp">
                    <div class="t7-circle">
                        <?php if ($bridePhoto !== ''): ?>
                            <img src="<?= t7e($bridePhoto) ?>" alt="Ảnh cô dâu" loading="lazy">
                        <?php else: ?><span><?= t7e(t7initial($brideName)) ?></span><?php endif; ?>
                    </div>
                    <small>Cô dâu</small>
                    <strong><?= t7e(t7name($brideName)) ?></strong>
                </div>
            </div>
        </section>

        <section class="t7-section t7-invitation">
            <h2 class="t7-title t7-reveal" style="--d:.1s; font-family:'Great Vibes','Allura',cursive; font-size:46px; font-weight:400;">Trân trọng kính mời</h2>
            <p class="t7-invite-text t7-reveal" style="--d:.2s; font-family:'Cormorant Garamond','Times New Roman',Times,serif; font-size:20px; font-style:italic; line-height:1.6;">
                Kính mời quý khách đến dự<br>
                lễ thành hôn của hai con<br>
                và cùng chia sẻ niềm hạnh phúc trọn vẹn nhất.
            </p>
            <?php if ($storyImage !== ''): ?>
                <div class="t7-story-photo t7-reveal" style="--d:.3s">
                    <img src="<?= t7e($storyImage) ?>" alt="Ảnh cưới" loading="lazy">
                </div>
            <?php endif; ?>
        </section>

        <!-- ============ 3. THÔNG TIN LỄ CƯỚI ============ -->
        <section class="t7-section t7-info">
            <p class="t7-label t7-reveal">WEDDING INFO</p>
            <h2 class="t7-title t7-caps t7-reveal" style="--d:.1s">THÔNG TIN LỄ CƯỚI</h2>

            <div class="t7-families t7-reveal" style="--d:.2s">
                <?php foreach (['groom', 'bride'] as $sd): ?>
                    <div class="t7-family">
                        <span class="t7-family-label">Ông Bà</span>
                        <?php if ($parents[$sd]['father'] !== ''): ?><strong><?= t7e($parents[$sd]['father']) ?></strong><?php endif; ?>
                        <?php if ($parents[$sd]['mother'] !== ''): ?><strong><?= t7e($parents[$sd]['mother']) ?></strong><?php endif; ?>
                        <?php if ($parents[$sd]['address'] !== ''): ?><em><?= t7e($parents[$sd]['address']) ?></em><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="t7-announce t7-reveal">TRÂN TRỌNG BÁO TIN<br>LỄ THÀNH HÔN CỦA CON CHÚNG TÔI</p>

            <div class="t7-couple-names t7-reveal" style="--d:.1s">
                <div class="t7-big-name"><?= t7e($groomName) ?></div>
                <small>CHÚ RỂ</small>
                <div class="t7-amp">&amp;</div>
                <div class="t7-big-name"><?= t7e($brideName) ?></div>
                <small>CÔ DÂU</small>
            </div>
        </section>

        <?php if ($dateObject): ?>
        <!-- ============ 4. NGÀY TIỆC + TIMELINE ============ -->
        <section class="t7-section t7-dateblock">
            <p class="t7-label t7-reveal">WEDDING INVITATION</p>
            <h2 class="t7-title t7-reveal" style="--d:.1s">Trân trọng kính mời</h2>
            <p class="t7-ceremony t7-caps t7-reveal" style="--d:.2s">Dự tiệc cưới</p>
            <?php if ($timeHi !== ''): ?><p class="t7-time t7-reveal" style="--d:.25s"><?= t7e($timeHi) ?></p><?php endif; ?>
            <div class="t7-daterow t7-reveal" style="--d:.3s">
                <span class="t7-caps"><?= t7e($weekday) ?></span>
                <b><?= t7e($day) ?></b>
                <span class="t7-caps">THÁNG <?= t7e($month) ?></span>
            </div>
            <p class="t7-year t7-reveal"><?= t7e($year) ?></p>
            <?php if ($lunarDate !== ''): ?><p class="t7-lunar t7-reveal">(<?= t7e($lunarDate) ?>)</p><?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if (!empty($events)): ?>
        <section class="t7-section t7-timeline-sec">
            <p class="t7-label t7-reveal">WEDDING TIMELINE</p>
            <h2 class="t7-title t7-reveal" style="--d:.1s">Lịch trình ngày cưới</h2>
            <div class="t7-tl t7-reveal" style="--d:.2s">
                <?php foreach ($events as $i => $ev):
                    $evTime = !empty($ev['event_time']) && strtotime($ev['event_time']) !== false ? date('H:i', strtotime($ev['event_time'])) : '';
                ?>
                    <div class="t7-tl-item">
                        <b><?= t7e($evTime) ?></b>
                        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $t7Icons[$i % count($t7Icons)] ?></svg>
                        <em><?= t7e($ev['event_name'] ?? '') ?></em>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($album)): ?>
        <!-- ============ 5. GALLERY ============ -->
        <section class="t7-section t7-gallery">
            <p class="t7-label t7-reveal">GALLERY</p>
            <h2 class="t7-title t7-reveal" style="--d:.1s">Khoảnh khắc của chúng mình</h2>
            <div class="t7-gal-main t7-reveal" id="t7GalMain" style="--d:.2s">
                <img id="t7GalImg" src="<?= t7e($album[0]) ?>" alt="Ảnh cưới 1">
            </div>
            <?php if (count($album) > 1): ?>
            <div class="t7-gal-thumbs" id="t7GalThumbs">
                <?php foreach ($album as $i => $src): ?>
                    <button type="button" class="t7-thumb<?= $i === 0 ? ' is-active' : '' ?>" data-src="<?= t7e($src) ?>" aria-label="Ảnh <?= $i + 1 ?>">
                        <img src="<?= t7e($src) ?>" alt="" loading="lazy">
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($dateObject): ?>
        <!-- ============ 6. LỊCH + COUNTDOWN ============ -->
        <section class="t7-section t7-calendar">
            <h2 class="t7-cal-date t7-reveal"><?= t7e($dotDate) ?></h2>
            <p class="t7-cal-sub t7-reveal" style="--d:.1s"><?= t7e($weekday) ?><?= $timeAmPm !== '' ? ', ' . t7e($timeAmPm) : '' ?></p>
            <div class="t7-cal t7-reveal" style="--d:.2s">
                <div class="t7-cal-head">
                    <span class="t7-sun">CN</span><span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span>
                </div>
                <div class="t7-cal-grid">
                    <?php foreach ($calCells as $i => $c): ?>
                        <?php if ($c === null): ?><span class="t7-cal-empty"></span>
                        <?php else: ?><span class="<?= ($i % 7 === 0 ? 't7-sun ' : '') . ($c === $weddingDay ? 't7-wedding-day' : '') ?>"><?= $c ?></span><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="t7-countdown t7-reveal" id="t7Countdown">
                <div><small>ngày</small><b data-cd="d">00</b></div><i>:</i>
                <div><small>giờ</small><b data-cd="h">00</b></div><i>:</i>
                <div><small>phút</small><b data-cd="m">00</b></div><i>:</i>
                <div><small>giây</small><b data-cd="s">00</b></div>
            </div>
            <p class="t7-cd-text t7-reveal" id="t7CdText">
                Còn <span data-cd="days-text">0</span> ngày nữa là đến đám cưới của <?= t7e($shortGroom) ?> và <?= t7e($shortBride) ?>.
            </p>
        </section>
        <?php endif; ?>

        <?php if ($mapEmbed !== ''): ?>
        <!-- ============ 7. ĐỊA ĐIỂM ============ -->
        <section class="t7-section t7-location">
            <h2 class="t7-loc-title t7-caps t7-reveal">Địa điểm</h2>
            <p class="t7-loc-info t7-reveal" style="--d:.1s">
                <?php if ($venue !== ''): ?><span class="t7-loc-venue"><?= t7e($venue) ?></span><br><?php endif; ?>
                <?php if ($address !== ''): ?><?= t7e($address) ?><?php endif; ?>
            </p>
            <div class="t7-map t7-reveal" style="--d:.2s">
                <iframe src="<?= t7e($mapEmbed) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Bản đồ"></iframe>
            </div>
            <?php if ($dirUrl !== ''): ?>
                <a class="t7-dir-btn t7-reveal" href="<?= t7e($dirUrl) ?>" target="_blank" rel="noopener">Chỉ đường - ĐỊA ĐIỂM</a>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        
        <!-- ============ 7B. SỔ LƯU BÚT ============ -->
        <section class="t7-section t7-guestbook">
            <div class="t7-hearts" aria-hidden="true">
                <span style="left:6%;top:30%;--s:.8">♥</span>
                <span style="left:66%;top:36%;--s:1.2;animation-delay:-2s">♥</span>
                <span style="left:14%;top:52%;--s:1;animation-delay:-4s">♥</span>
                <span style="left:80%;top:62%;--s:.7;animation-delay:-1s">♥</span>
                <span style="left:40%;top:70%;--s:.9;animation-delay:-3s">♥</span>
            </div>
            <h2 class="t7-gb-title t7-reveal">Sổ Lưu Bút</h2>
            <p class="t7-gb-sub t7-reveal" style="--d:.1s">Cảm ơn bạn rất nhiều vì đã gửi những lời chúc mừng tốt đẹp nhất đến đám cưới của chúng tôi!</p>

            <form class="t7-wish-form t7-reveal" id="t7WishForm" data-wedding-id="<?= (int)$wedding['id'] ?>" style="--d:.2s" autocomplete="off">
                <input type="text" id="t7WishName" maxlength="100" placeholder="Nhập tên của bạn*" required>
                <textarea id="t7WishMessage" rows="3" maxlength="2000" placeholder="Nhập lời chúc của bạn*" required></textarea>
                <button type="submit" id="t7SendWish" class="t7-wish-send">Gửi lời chúc</button>
                <p class="t7-wish-status" id="t7WishStatus" role="status" aria-live="polite"></p>
            </form>

            <div class="t7-wish-list" id="t7WishList">
                <?php if (!$t7wishes): ?>
                    <div class="t7-wish-empty">Chưa có lời chúc nào. Hãy là người đầu tiên gửi lời chúc nhé.</div>
                <?php else: foreach ($t7wishes as $w): ?>
                    <article class="t7-wish-card">
                        <strong><?= t7e($w['name'] !== '' ? $w['name'] : 'Một người bạn') ?></strong>
                        <p><?= t7e($w['msg']) ?></p>
                        <?php if ($w['time'] !== ''): ?><time><?= t7e($w['time']) ?></time><?php endif; ?>
                    </article>
                <?php endforeach; endif; ?>
            </div>
        </section>

        <!-- ============ 8. HỘP QUÀ ============ -->
        <section class="t7-gift">
            <h2 class="t7-gift-title t7-reveal">Hộp quà mừng</h2>
            <p class="t7-gift-sub t7-reveal" style="--d:.1s">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>
            <button type="button" class="t7-gift-card t7-reveal" id="t7OpenGift" style="--d:.2s">
                <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="8" y="26" width="48" height="12" rx="2"/>
                    <path d="M12 38v18h40V38"/>
                    <path d="M32 26v30"/>
                    <path d="M32 26c-4-10-16-12-16-5 0 4 8 5 16 5zM32 26c4-10 16-12 16-5 0 4-8 5-16 5z"/>
                </svg>
                <strong>Gửi quà mừng cưới</strong>
                <span>Nhấn để xem thông tin</span>
            </button>
            <p class="t7-thanks t7-reveal">Thank you for being with us</p>
        </section>

        <!-- ============ 9. KẾT THÚC (nửa màn hình) ============ -->
        <section class="t7-ending<?= $bgImage === '' ? ' t7-ending-plain' : '' ?>">
            <div class="t7-ending-bg"<?= $bgImage !== '' ? ' style="background-image:url(\'' . t7e($bgImage) . '\')"' : '' ?>></div>
            <div class="t7-ending-text t7-reveal">
                <p>Cảm ơn bạn đã ghé thăm tấm thiệp nhỏ này.</p>
                <p>Sự hiện diện và lời chúc của bạn<br>là món quà ý nghĩa nhất với chúng mình.</p>
                <p>With love,<br><?= t7e($shortGroom) ?> &amp; <?= t7e($shortBride) ?></p>
            </div>
        </section>
    </main>

    <!-- ============ MODAL HỘP QUÀ MỪNG ============ -->
    <div class="t7-modal" id="t7GiftModal" aria-hidden="true">
        <div class="t7-modal-backdrop" data-close></div>
        <div class="t7-modal-panel" role="dialog" aria-modal="true" aria-label="Hộp quà mừng">
            <div class="t7-modal-head">
                <h3>HỘP QUÀ MỪNG</h3>
                <button type="button" class="t7-modal-close" data-close aria-label="Đóng">&times;</button>
            </div>
            <div class="t7-modal-body">
                <?php if ($giftMode === 'message'): ?>
                    <p class="t7-modal-msg"><?= nl2br(t7e($giftMessage)) ?></p>
                <?php else: ?>
                <div class="t7-qr-grid">
                    <?php foreach ([['Chú Rể', $groomGift, $groomName, 'chú rể'], ['Cô Dâu', $brideGift, $brideName, 'cô dâu']] as [$cap, $g, $fallbackName, $who]):
                        $qr = $g ? t7url($g['qr_image'] ?? '') : '';
                        $pname = $g && !empty($g['person_name']) ? $g['person_name'] : $fallbackName;
                    ?>
                        <div class="t7-qr-col">
                            <h4><?= $cap ?> - <?= t7e($pname) ?></h4>
                            <div class="t7-qr-card">
                                <?php if ($qr !== ''): ?>
                                    <img src="<?= t7e($qr) ?>" alt="QR mừng cưới <?= $who ?>">
                                <?php else: ?>
                                    <span class="t7-qr-empty">Chưa có QR</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($g): ?>
                            <div class="t7-bank">
                                <?php if (!empty($g['bank_name'])): ?><p><?= t7e($g['bank_name']) ?></p><?php endif; ?>
                                <?php if (!empty($g['account_number'])): ?><p><?= t7e($g['account_number']) ?></p><?php endif; ?>
                                <?php if (!empty($g['account_name'])): ?><p><b><?= t7e($g['account_name']) ?></b></p><?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($qr !== ''): ?>
                                <a class="t7-save" href="<?= t7e($qr) ?>" download>
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 11l5 5 5-5M5 20h14"/></svg>
                                    Lưu QR
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($music !== ''): ?>
        <button type="button" class="t7-music" id="t7Music" aria-label="Bật/tắt nhạc">♪</button>
        <audio id="t7Audio" loop preload="none"><source src="<?= t7e($music) ?>" type="audio/mpeg"></audio>
    <?php endif; ?>
</div>

<script src="/WeddingProject/templates/template07/script.js"></script>
</body>
</html>