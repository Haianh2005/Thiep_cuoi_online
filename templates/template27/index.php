<?php
/**
 * TEMPLATE 27 – Burgundy Save The Date
 * Mọi dữ liệu lấy từ SQL. File này được w/index.php require, nên đã có sẵn $wedding (và thường là $pdo).
 */

/* ---------- Kết nối DB nếu w/index.php chưa tạo sẵn ---------- */
if (!isset($pdo)) {
    if (isset($db) && $db instanceof PDO) { $pdo = $db; }
    elseif (isset($conn) && $conn instanceof PDO) { $pdo = $conn; }
    else { require_once dirname(__DIR__, 2) . '/config/database.php'; }
}
if (!isset($wedding) || !is_array($wedding)) { http_response_code(404); exit('Không tìm thấy thiệp.'); }

/* ---------- Hàm hỗ trợ ---------- */
if (!function_exists('t27_h')) {
    function t27_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('t27_pick')) {
    function t27_pick($row, $keys) {
        foreach ($keys as $k) {
            if (isset($row[$k]) && trim((string)$row[$k]) !== '') return trim((string)$row[$k]);
        }
        return '';
    }
}
if (!function_exists('t27_sidepick')) { // groom_qr hoặc qr_groom
    function t27_sidepick($row, $side, $keys) {
        $try = [];
        foreach ($keys as $k) { $try[] = $side . '_' . $k; $try[] = $k . '_' . $side; }
        return t27_pick($row, $try);
    }
}
if (!function_exists('t27_rows')) {
    function t27_rows($pdo, $sql, $params) {
        try { $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $e) { return []; }
    }
}
if (!function_exists('t27_url')) {
    function t27_url($v, $base, $folder) {
        $v = trim((string)$v);
        if ($v === '') return '';
        if (preg_match('#^(https?:)?//#', $v) || $v[0] === '/' || strpos($v, 'data:') === 0) return $v;
        $v = ltrim(str_replace('\\', '/', $v), '/');
        $v = str_replace(' ', '%20', $v);
        if (strpos($v, 'uploads/') === 0) return $base . '/' . $v;
        if (preg_match('#^(weddings|gifts|music)/#', $v)) return $base . '/uploads/' . $v;
        return $base . '/uploads/' . $folder . '/' . $v;
    }
}
if (!function_exists('t27_short')) { // "Ngô Bá Anh" -> "Bá Anh"
    function t27_short($n) {
        $p = preg_split('/\s+/', trim($n));
        return count($p) >= 3 ? implode(' ', array_slice($p, 1)) : trim($n);
    }
}

/* ---------- Âm lịch (thuật toán Hồ Ngọc Đức) ---------- */
if (!function_exists('t27_lunar')) {
    function t27_jd($d, $m, $y) {
        $a = (int)floor((14 - $m) / 12); $yy = $y + 4800 - $a; $mm = $m + 12 * $a - 3;
        return $d + (int)floor((153 * $mm + 2) / 5) + 365 * $yy + (int)floor($yy / 4) - (int)floor($yy / 100) + (int)floor($yy / 400) - 32045;
    }
    function t27_newmoon($k, $tz) {
        $T = $k / 1236.85; $T2 = $T * $T; $T3 = $T2 * $T; $dr = M_PI / 180;
        $J = 2415020.75933 + 29.53058868 * $k + 0.0001178 * $T2 - 0.000000155 * $T3;
        $J += 0.00033 * sin((166.56 + 132.87 * $T - 0.009173 * $T2) * $dr);
        $M = 359.2242 + 29.10535608 * $k - 0.0000333 * $T2 - 0.00000347 * $T3;
        $Mp = 306.0253 + 385.81691806 * $k + 0.0107306 * $T2 + 0.00001236 * $T3;
        $F = 21.2964 + 390.67050646 * $k - 0.0016528 * $T2 - 0.00000239 * $T3;
        $C = (0.1734 - 0.000393 * $T) * sin($M * $dr) + 0.0021 * sin(2 * $dr * $M);
        $C -= 0.4068 * sin($Mp * $dr) + 0.0161 * sin($dr * 2 * $Mp);
        $C -= 0.0004 * sin($dr * 3 * $Mp);
        $C += 0.0104 * sin($dr * 2 * $F) - 0.0051 * sin($dr * ($M + $Mp));
        $C += -0.0074 * sin($dr * ($M - $Mp)) + 0.0004 * sin($dr * (2 * $F + $M));
        $C -= 0.0004 * sin($dr * (2 * $F - $M)) - 0.0006 * sin($dr * (2 * $F + $Mp));
        $C += 0.0010 * sin($dr * (2 * $F - $Mp)) + 0.0005 * sin($dr * (2 * $Mp + $M));
        $dt = ($T < -11) ? 0.001 + 0.000839 * $T + 0.0002261 * $T2 - 0.00000845 * $T3 - 0.000000081 * $T * $T3
                         : -0.000278 + 0.000265 * $T + 0.000262 * $T2;
        return (int)floor($J + $C - $dt + 0.5 + $tz / 24);
    }
    function t27_sunlong($jdn, $tz) {
        $T = ($jdn - 2451545.5 - $tz / 24) / 36525; $T2 = $T * $T; $dr = M_PI / 180;
        $M = 357.52910 + 35999.05030 * $T - 0.0001559 * $T2 - 0.00000048 * $T * $T2;
        $L0 = 280.46645 + 36000.76983 * $T + 0.0003032 * $T2;
        $DL = (1.914600 - 0.004817 * $T - 0.000014 * $T2) * sin($dr * $M) + (0.019993 - 0.000101 * $T) * sin($dr * 2 * $M) + 0.000290 * sin($dr * 3 * $M);
        $L = ($L0 + $DL) * $dr; $L -= M_PI * 2 * floor($L / (M_PI * 2));
        return (int)floor($L / M_PI * 6);
    }
    function t27_m11($y, $tz) {
        $off = t27_jd(31, 12, $y) - 2415021; $k = (int)floor($off / 29.530588853);
        $nm = t27_newmoon($k, $tz);
        if (t27_sunlong($nm, $tz) >= 9) $nm = t27_newmoon($k - 1, $tz);
        return $nm;
    }
    function t27_leapoff($a11, $tz) {
        $k = (int)floor(($a11 - 2415021.076998695) / 29.530588853 + 0.5);
        $i = 1; $arc = t27_sunlong(t27_newmoon($k + $i, $tz), $tz);
        do { $last = $arc; $i++; $arc = t27_sunlong(t27_newmoon($k + $i, $tz), $tz); } while ($arc != $last && $i < 14);
        return $i - 1;
    }
    function t27_lunar($d, $m, $y, $tz = 7) {
        $dn = t27_jd($d, $m, $y); $k = (int)floor(($dn - 2415021.076998695) / 29.530588853);
        $ms = t27_newmoon($k + 1, $tz); if ($ms > $dn) $ms = t27_newmoon($k, $tz);
        $a11 = t27_m11($y, $tz); $b11 = $a11;
        if ($a11 >= $ms) { $a11 = t27_m11($y - 1, $tz); } else { $b11 = t27_m11($y + 1, $tz); }
        $ld = $dn - $ms + 1; $diff = (int)floor(($ms - $a11) / 29); $lm = $diff + 11;
        if ($b11 - $a11 > 365) { $lo = t27_leapoff($a11, $tz); if ($diff >= $lo) { $lm = $diff + 10; } }
        if ($lm > 12) $lm -= 12;
        return [$ld, $lm];
    }
}

/* ---------- Đường dẫn gốc ---------- */
$docRoot  = rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$projRoot = str_replace('\\', '/', (string)realpath(dirname(__DIR__, 2)));
$BASE = ($docRoot !== '' && stripos($projRoot, $docRoot) === 0) ? substr($projRoot, strlen($docRoot)) : '/WeddingProject';
$TPL  = $BASE . '/templates/template27';
$wid  = (int)$wedding['id'];

/* ---------- Nhận & lưu lời chúc (POST từ chính trang này) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['t27_wish'])) {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json; charset=UTF-8');
    $reply = function ($ok, $msg) {
        echo json_encode(['success' => $ok, 'message' => $msg], JSON_UNESCAPED_UNICODE);
        exit;
    };
    $wn = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 60);
    $wm = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 500);
    if ($wn === '' || $wm === '') $reply(false, 'Vui lòng nhập tên và lời chúc.');
    try {
        $have = [];
        foreach ($pdo->query('SHOW COLUMNS FROM wedding_wishes')->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $have[strtolower($c['Field'])] = $c['Field'];
        }
        $data = [];
        foreach (['guest_name', 'name', 'sender_name', 'fullname'] as $k) if (isset($have[$k])) $data[$have[$k]] = $wn;
        foreach (['message', 'content', 'wish', 'wish_message'] as $k) if (isset($have[$k])) $data[$have[$k]] = $wm;
        if (!isset($have['wedding_id']) || count($data) < 2) throw new RuntimeException('Bảng wedding_wishes thiếu cột cần thiết');
        $data[$have['wedding_id']] = $wid;
        foreach (['is_approved', 'approved', 'is_visible', 'is_public', 'visible'] as $k) if (isset($have[$k])) $data[$have[$k]] = 1;
        if (isset($have['created_at'])) $data[$have['created_at']] = date('Y-m-d H:i:s');
        $cols = array_keys($data);
        $sql = 'INSERT INTO wedding_wishes (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $pdo->prepare($sql)->execute(array_values($data));
        $reply(true, 'OK');
    } catch (Throwable $e) {
        error_log('[template27 wishes] ' . $e->getMessage());
        $reply(false, 'Gửi chưa được, bạn thử lại nhé.');
    }
}

/* ---------- Dữ liệu cơ bản ---------- */
$groom = trim($wedding['groom_name'] ?? '');
$bride = trim($wedding['bride_name'] ?? '');
$venue = trim($wedding['venue'] ?? '');
$addr  = trim($wedding['address'] ?? '');
$story = trim($wedding['story'] ?? '');

$bg    = t27_url($wedding['background_image'] ?? '', $BASE, 'weddings');
$st    = t27_url($wedding['story_image'] ?? '', $BASE, 'weddings');
if ($bg === '') $bg = $st;
if ($st === '') $st = $bg;
$music = t27_url($wedding['music'] ?? '', $BASE, 'music');

$ts = false;
if (!empty($wedding['wedding_date'])) {
    $ts = strtotime($wedding['wedding_date'] . ' ' . (!empty($wedding['wedding_time']) ? $wedding['wedding_time'] : '00:00'));
}
$weekdays = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];
$dD = $ts ? (int)date('j', $ts) : 0;
$dM = $ts ? (int)date('n', $ts) : 0;
$dY = $ts ? (int)date('Y', $ts) : 0;
$dateDot  = $ts ? date('d.m.Y', $ts) : '';
$timeText = (!empty($wedding['wedding_time'])) ? substr($wedding['wedding_time'], 0, 5) : '';
$weekday  = $ts ? $weekdays[(int)date('w', $ts)] : '';
$lunarTxt = '';
if ($ts) { $lu = t27_lunar($dD, $dM, $dY); $lunarTxt = 'Tức ngày ' . $lu[0] . ' tháng ' . $lu[1] . ' âm lịch'; }
$targetJs = $ts ? date('Y-m-d\TH:i:s', $ts) : '';

/* ---------- Gia đình (wedding_parents) ---------- */
$fam = ['groom' => ['f' => '', 'm' => '', 'a' => ''], 'bride' => ['f' => '', 'm' => '', 'a' => '']];
foreach (t27_rows($pdo, 'SELECT * FROM wedding_parents WHERE wedding_id = ? ORDER BY id ASC', [$wid]) as $r) {
    foreach (['groom', 'bride'] as $sd) { // 1 dòng, nhiều cột
        $f = t27_sidepick($r, $sd, ['father', 'father_name']);
        $m = t27_sidepick($r, $sd, ['mother', 'mother_name']);
        $a = t27_sidepick($r, $sd, ['address', 'hometown', 'home']);
        if ($f !== '' && $fam[$sd]['f'] === '') $fam[$sd]['f'] = $f;
        if ($m !== '' && $fam[$sd]['m'] === '') $fam[$sd]['m'] = $m;
        if ($a !== '' && $fam[$sd]['a'] === '') $fam[$sd]['a'] = $a;
    }
    $side = mb_strtolower(t27_pick($r, ['side', 'type', 'family', 'party', 'belongs_to']) . ' ' . t27_pick($r, ['role', 'relation', 'relationship']));
    $sd = '';
    if (preg_match('/groom|trai|chú|chu|male/u', $side)) $sd = 'groom';
    elseif (preg_match('/bride|gái|gai|dâu|dau|female/u', $side)) $sd = 'bride';
    if ($sd) { // nhiều dòng, mỗi dòng 1 bên
        $f = t27_pick($r, ['father_name', 'father', 'dad']);
        $m = t27_pick($r, ['mother_name', 'mother', 'mom']);
        $a = t27_pick($r, ['address', 'hometown', 'home', 'location']);
        $nm = t27_pick($r, ['name', 'full_name', 'parent_name']);
        if ($nm !== '' && $f === '' && $m === '') {
            if (preg_match('/father|cha|bố|ông/u', $side)) $f = $nm;
            elseif (preg_match('/mother|mẹ|bà/u', $side)) $m = $nm;
        }
        if ($f !== '' && $fam[$sd]['f'] === '') $fam[$sd]['f'] = $f;
        if ($m !== '' && $fam[$sd]['m'] === '') $fam[$sd]['m'] = $m;
        if ($a !== '' && $fam[$sd]['a'] === '') $fam[$sd]['a'] = $a;
    }
}
$hasFamily = ($fam['groom']['f'] . $fam['groom']['m'] . $fam['bride']['f'] . $fam['bride']['m']) !== '';

/* ---------- Timeline (wedding_events) ---------- */
$events = [];
foreach (t27_rows($pdo, 'SELECT * FROM wedding_events WHERE wedding_id = ? ORDER BY id ASC', [$wid]) as $r) {
    $title = t27_pick($r, ['title', 'name', 'event_name', 'event_title']);
    if ($title === '') continue;
    $time  = t27_pick($r, ['event_time', 'time', 'start_time']);
    $place = t27_pick($r, ['location', 'side', 'place', 'venue', 'note', 'description']);
    $events[] = ['title' => $title, 'time' => substr($time, 0, 5), 'place' => $place];
}
usort($events, function ($a, $b) { return strcmp($a['time'], $b['time']); });
$mainEvent = !empty($events) ? $events[0]['title'] : 'lễ cưới';

/* ---------- Album (wedding_photos) ---------- */
$photos = [];
foreach (t27_rows($pdo, 'SELECT * FROM wedding_photos WHERE wedding_id = ? ORDER BY id ASC', [$wid]) as $r) {
    $u = t27_url(t27_pick($r, ['image', 'photo', 'image_path', 'photo_path', 'file_name', 'filename', 'path', 'url']), $BASE, 'weddings');
    if ($u !== '') $photos[] = $u;
}
if (empty($photos)) { foreach ([$bg, $st] as $u) { if ($u !== '') $photos[] = $u; } }

/* ---------- Lưu bút (wedding_wishes) ---------- */
$wishes = [];
foreach (t27_rows($pdo, 'SELECT * FROM wedding_wishes WHERE wedding_id = ? ORDER BY id DESC LIMIT 100', [$wid]) as $r) {
    $wishes[] = [
        'name' => t27_pick($r, ['guest_name', 'name', 'sender_name', 'fullname']),
        'msg'  => t27_pick($r, ['message', 'content', 'wish', 'wish_message']),
        'time' => !empty($r['created_at']) ? date('H:i:s d/m/Y', strtotime($r['created_at'])) : '',
    ];
}

/* ---------- Quà mừng (wedding_gifts) ---------- */
$giftRows = t27_rows($pdo, 'SELECT * FROM wedding_gifts WHERE wedding_id = ? ORDER BY id ASC', [$wid]);
$giftMode = t27_pick($wedding, ['gift_mode']);
if ($giftMode === '' && !empty($giftRows)) $giftMode = t27_pick($giftRows[0], ['gift_mode']);
$giftMsg = t27_pick($wedding, ['gift_message']);
if ($giftMsg === '' && !empty($giftRows)) $giftMsg = t27_pick($giftRows[0], ['gift_message']);
$gift = ['groom' => ['qr' => '', 'bank' => '', 'num' => '', 'name' => ''], 'bride' => ['qr' => '', 'bank' => '', 'num' => '', 'name' => '']];
foreach ($giftRows as $r) {
    $side = mb_strtolower(t27_pick($r, ['side', 'type', 'owner', 'role', 'for']));
    $only = '';
    if (preg_match('/groom|trai|chú|chu/u', $side)) $only = 'groom';
    elseif (preg_match('/bride|gái|gai|dâu|dau/u', $side)) $only = 'bride';
    foreach (['groom', 'bride'] as $sd) {
        if ($only !== '' && $only !== $sd) continue;
        $pick = function ($keys) use ($r, $sd, $only) {
            $v = t27_sidepick($r, $sd, $keys);
            return ($v === '' && $only === $sd) ? t27_pick($r, $keys) : $v;
        };
        $qr   = $pick(['qr', 'qr_image', 'qr_code']);
        $bank = $pick(['bank', 'bank_name']);
        $num  = $pick(['account_number', 'account_no', 'bank_account', 'number']);
        $nm   = $pick(['account_name', 'receiver', 'receiver_name', 'holder']);
        if ($qr !== '' && $gift[$sd]['qr'] === '')     $gift[$sd]['qr'] = t27_url($qr, $BASE, 'gifts');
        if ($bank !== '' && $gift[$sd]['bank'] === '') $gift[$sd]['bank'] = $bank;
        if ($num !== '' && $gift[$sd]['num'] === '')   $gift[$sd]['num'] = $num;
        if ($nm !== '' && $gift[$sd]['name'] === '')   $gift[$sd]['name'] = $nm;
    }
}

/* ---------- Icon timeline ---------- */
$icons = [
  'flower' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="32" cy="20" r="7" fill="#f3cfd2" stroke="#b9525a"/><circle cx="21" cy="26" r="7" fill="#f3cfd2" stroke="#b9525a"/><circle cx="43" cy="26" r="7" fill="#f3cfd2" stroke="#b9525a"/><circle cx="26" cy="35" r="7" fill="#f3cfd2" stroke="#b9525a"/><circle cx="38" cy="35" r="7" fill="#f3cfd2" stroke="#b9525a"/><path d="M32 44v14M32 52c-6-2-9-5-10-9M32 50c6-2 9-5 10-9"/></svg>',
  'camera' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M10 22h10l4-6h16l4 6h10v30H10z"/><circle cx="32" cy="36" r="10"/><circle cx="32" cy="36" r="5"/><path d="M14 22v-4h6"/></svg>',
  'glass'  => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M27 6h10v10l5 8v34H22V24l5-8z"/><path d="M22 34h20M22 46h20M27 10h10"/></svg>',
  'rings'  => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="24" cy="38" r="14"/><circle cx="40" cy="38" r="14"/><path d="M32 12l-5 6 5 6 5-6z"/></svg>',
];
$iconOrder = ['flower', 'camera', 'glass', 'rings'];
$pickIcon = function ($title, $i) use ($icons, $iconOrder) {
    $t = mb_strtolower($title);
    if (preg_match('/vu quy|rước|đón dâu/u', $t)) return $icons['flower'];
    if (preg_match('/check|tiếp khách|đón khách|chụp/u', $t)) return $icons['camera'];
    if (preg_match('/tiệc|khai|ăn/u', $t)) return $icons['glass'];
    if (preg_match('/thành hôn|hôn lễ|lễ cưới/u', $t)) return $icons['rings'];
    return $icons[$iconOrder[$i % 4]];
};

/* ---------- Lịch tháng ---------- */
$calCells = [];
if ($ts) {
    $first = mktime(0, 0, 0, $dM, 1, $dY);
    $offset = (int)date('N', $first) - 1;
    $total = (int)date('t', $first);
    for ($i = 0; $i < $offset; $i++) $calCells[] = 0;
    for ($d = 1; $d <= $total; $d++) $calCells[] = $d;
}

$cfg = [
    'wid' => $wid, 'slug' => $wedding['slug'] ?? '', 'api' => $BASE . '/api/wishes.php',
    'target' => $targetJs, 'photos' => array_values($photos),
];
$pageTitle = trim($groom . ' & ' . $bride);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Thiệp cưới <?= t27_h($pageTitle) ?></title>
<?php if ($bg): ?><meta property="og:image" content="<?= t27_h($bg) ?>"><?php endif; ?>
<meta property="og:title" content="Thiệp cưới <?= t27_h($pageTitle) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Montserrat:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600;1,700&display=swap&subset=vietnamese" rel="stylesheet">
<?php
// Nhúng thẳng CSS từ cùng thư mục → không phụ thuộc đường dẫn hay cache
$cssFile = __DIR__ . '/style.css'; $jsFile = __DIR__ . '/script.js';
if (is_file($cssFile)) { echo '<style>' . file_get_contents($cssFile) . '</style>'; }
else { echo '<link rel="stylesheet" href="' . $TPL . '/style.css">'; }
?>
</head>
<body>

<?php if ($music): ?>
<audio id="weddingMusic" loop preload="none"><source src="<?= t27_h($music) ?>"></audio>
<button class="music-btn" id="musicBtn" type="button" aria-label="Bật/tắt nhạc">
  <svg viewBox="0 0 24 24" fill="currentColor"><rect class="bar b1" x="4" y="8" width="3" height="8" rx="1.5"/><rect class="bar b2" x="10.5" y="4" width="3" height="16" rx="1.5"/><rect class="bar b3" x="17" y="9" width="3" height="6" rx="1.5"/></svg>
</button>
<?php endif; ?>

<main class="t27">

  <!-- 1. MỞ ĐẦU -->
  <section class="hero" style="<?= $bg ? "background-image:url('" . t27_h($bg) . "')" : '' ?>">
    <div class="hero-save">
      <span class="script">Save the date</span>
      <span class="hero-date"><?= t27_h($dateDot) ?></span>
    </div>
    <div class="hero-names script">
      <span><?= t27_h(t27_short($groom)) ?></span>
      <span class="amp">&amp;</span>
      <span class="n2"><?= t27_h(t27_short($bride)) ?></span>
    </div>
    <div class="hero-tag script">Nhà Có Hỷ</div>
  </section>

  <!-- 2. GIA ĐÌNH -->
  <?php if ($hasFamily): ?>
  <section class="family">
    <div class="fam-wrap">
      <?php foreach (['groom' => 'NHÀ TRAI', 'bride' => 'NHÀ GÁI'] as $sd => $label): $f = $fam[$sd]; ?>
        <?php if ($f['f'] . $f['m'] . $f['a'] !== ''): ?>
        <div class="fam-block">
          <h3><?= $label ?></h3>
          <?php if ($f['f'] !== ''): ?><p class="fam-name">Ông : <?= t27_h($f['f']) ?></p><?php endif; ?>
          <?php if ($f['m'] !== ''): ?><p class="fam-name">Bà : <?= t27_h($f['m']) ?></p><?php endif; ?>
          <?php if ($f['a'] !== ''): ?><p class="fam-addr"><?= t27_h($f['a']) ?></p><?php endif; ?>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
    <div class="tag-banner" aria-hidden="true">
      <i class="hole"></i>
      <span class="seal">囍</span>
    </div>
  </section>
  <?php endif; ?>

  <!-- 3. CÔ DÂU CHÚ RỂ -->
  <section class="couple">
    <p class="couple-lead">Trân Trọng Báo Tin Lễ Thành Hôn của</p>
    <h2 class="script couple-name"><?= t27_h($groom) ?></h2>
    <div class="script couple-amp">&amp;</div>
    <h2 class="script couple-name"><?= t27_h($bride) ?></h2>
    <?php if ($st): ?><div class="couple-photo" style="background-image:url('<?= t27_h($st) ?>')"></div><?php endif; ?>
    <p class="couple-invite">Trân trọng hình mời</p>
    <p class="script couple-guest">Quý Khách</p>
  </section>

  <!-- 4. ĐỊA ĐIỂM -->
  <section class="venue">
    <div class="pill-row"><h3 class="pill">DỰ <?= t27_h(mb_strtoupper($mainEvent)) ?></h3><span class="pill-line"></span></div>
    <div class="venue-grid">
      <div class="venue-photo" style="<?= $bg ? "background-image:url('" . t27_h($bg) . "')" : '' ?>"></div>
      <div class="venue-info">
        <p class="v-when">VÀO <?= t27_h(mb_strtoupper($weekday)) ?><?= $timeText ? ' - ' . t27_h($timeText) : '' ?></p>
        <p class="v-date"><?= t27_h($dateDot) ?></p>
        <?php if ($lunarTxt): ?><p class="v-lunar"><?= t27_h($lunarTxt) ?></p><?php endif; ?>
        <?php if ($venue): ?><p class="v-at">TẠI <?= t27_h(mb_strtoupper($venue)) ?></p><?php endif; ?>
        <?php if ($addr): ?><p class="v-addr"><?= t27_h($addr) ?></p><?php endif; ?>
        <?php $mapQ = $addr !== '' ? $addr : $venue; if ($mapQ !== ''): ?>
        <div class="v-map"><iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Bản đồ" src="https://www.google.com/maps?q=<?= rawurlencode($mapQ) ?>&amp;output=embed"></iframe></div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- 5. TIMELINE -->
  <?php if (!empty($events)): ?>
  <section class="timeline">
    <h2 class="script sec-title">Timeline</h2>
    <div class="tl-grid" style="--cols:<?= min(4, count($events)) ?>">
      <?php foreach ($events as $i => $e): ?>
      <div class="tl-item">
        <div class="tl-icon"><?= $pickIcon($e['title'], $i) ?></div>
        <div class="tl-time"><?= t27_h($e['time']) ?></div>
        <div class="tl-title"><?= t27_h($e['title']) ?></div>
        <?php if ($e['place'] !== ''): ?><div class="tl-place"><?= t27_h(preg_match('/^\(.*\)$/u', $e['place']) ? $e['place'] : '(' . $e['place'] . ')') ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- 6. LỊCH CƯỚI -->
  <?php if ($ts): ?>
  <section class="cal" style="<?= $st ? "background-image:url('" . t27_h($st) . "')" : '' ?>">
    <div class="cal-in">
      <div class="cal-big">
        <span class="cal-day"><?= $dD ?></span>
        <span class="cal-num"><?= str_pad((string)$dM, 2, '0', STR_PAD_LEFT) ?></span>
        <span class="cal-num"><?= substr((string)$dY, -2) ?></span>
      </div>
      <div class="cal-grid">
        <?php foreach (['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'] as $w): ?><span class="cal-h"><?= $w ?></span><?php endforeach; ?>
        <?php foreach ($calCells as $c): ?>
          <?php if ($c === 0): ?><span></span>
          <?php else: ?><span class="cal-d<?= $c === $dD ? ' on' : '' ?>"><?= $c ?></span><?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- 7. COUNTDOWN -->
  <?php if ($ts): ?>
  <section class="countdown">
    <h2 class="script sec-title">Countdown</h2>
    <div class="cd-row">
      <div class="cd-box"><b id="cdD">0</b><small>ngày</small></div>
      <div class="cd-box"><b id="cdH">0</b><small>giờ</small></div>
      <div class="cd-box"><b id="cdM">0</b><small>phút</small></div>
      <div class="cd-box"><b id="cdS">0</b><small>giây</small></div>
    </div>
  </section>
  <?php endif; ?>

  <!-- 8. ALBUM -->
  <?php if (!empty($photos)): ?>
  <section class="album">
    <div class="album-frame<?= count($photos) < 2 ? ' single' : '' ?>">
      <div class="album-thumbs" id="albumThumbs"></div>
      <div class="album-big" id="albumBig">
        <img id="albumBigImg" src="<?= t27_h($photos[0]) ?>" alt="Ảnh cưới" draggable="false">
        <span class="album-cap script">Beautiful chapter</span>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- 9. SỔ LƯU BÚT -->
  <section class="wishes">
    <div class="wb-frame"><div class="wb-inner">
      <h2 class="wb-title">SỔ LƯU BÚT</h2>
      <input id="wishName" type="text" maxlength="60" placeholder="Nhập tên của bạn..." autocomplete="name">
      <textarea id="wishMessage" rows="3" maxlength="500" placeholder="Nhập lời chúc của bạn..."></textarea>
      <div class="wb-actions"><span id="wishNote" class="wb-note"></span><button id="sendWish" type="button">GỬI LỜI CHÚC</button></div>
    </div></div>
    <div class="wish-list" id="wishList">
      <?php foreach ($wishes as $w): ?>
      <article class="wish-item">
        <header><strong><?= t27_h($w['name']) ?></strong><time><?= t27_h($w['time']) ?></time></header>
        <p><?= nl2br(t27_h($w['msg'])) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 10. QUÀ MỪNG -->
  <section class="gift">
    <p class="gift-eyebrow">A LITTLE GIFT</p>
    <h2 class="gift-title">Hộp quà mừng</h2>
    <p class="gift-sub">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>
    <button class="gift-card" id="giftOpen" type="button">
      <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="10" y="26" width="44" height="30" rx="2" fill="#ffffff"/><rect x="7" y="19" width="50" height="9" rx="2" fill="#fdfdfd"/><path d="M32 19v37M32 19c-4-10-16-12-16-5 0 4 8 5 16 5zM32 19c4-10 16-12 16-5 0 4-8 5-16 5z"/></svg>
      <span class="gift-card-t">Gửi quà mừng cưới</span>
      <span class="gift-card-s">Nhấn để xem thông tin</span>
    </button>
    <p class="gift-thanks">Thank you for being with us</p>
  </section>

  <!-- 11. LỜI CẢM ƠN -->
  <section class="thanks">
    <div class="thanks-bg" style="<?= $st ? "background-image:url('" . t27_h($st) . "')" : '' ?>"></div>
    <div class="thanks-txt">
      <p>Cảm ơn bạn đã ghé thăm tấm thiệp nhỏ này.</p>
      <p>Sự hiện diện và lời chúc của bạn<br>là món quà ý nghĩa nhất với chúng mình.</p>
      <p class="thanks-with">With love,</p>
      <p class="thanks-names"><?= t27_h($groom) ?> &amp; <?= t27_h($bride) ?></p>
    </div>
  </section>

</main>

<!-- MODAL QUÀ MỪNG -->
<div class="modal" id="giftModal" aria-hidden="true">
  <div class="modal-sheet" role="dialog" aria-label="Hộp quà mừng">
    <div class="modal-head"><h3>HỘP QUÀ MỪNG</h3><button type="button" class="modal-x" id="giftClose" aria-label="Đóng">&times;</button></div>
    <div class="modal-body">
      <?php if ($giftMode === 'message' && $giftMsg !== ''): ?>
        <p class="gift-msg"><?= nl2br(t27_h($giftMsg)) ?></p>
      <?php else: ?>
        <div class="qr-grid">
          <?php foreach (['groom' => ['Chú Rể', $groom, 'chú rể'], 'bride' => ['Cô Dâu', $bride, 'cô dâu']] as $sd => $meta): $g = $gift[$sd]; ?>
          <div class="qr-col">
            <p class="qr-label"><?= $meta[0] ?> - <?= t27_h(t27_short($meta[1])) ?></p>
            <div class="qr-box">
              <?php if ($g['qr']): ?><img src="<?= t27_h($g['qr']) ?>" alt="QR <?= t27_h($meta[2]) ?>"><?php else: ?><span>Chưa có mã QR <?= $meta[2] ?></span><?php endif; ?>
            </div>
            <?php if ($g['bank'] . $g['num'] . $g['name'] !== ''): ?>
            <p class="qr-bank"><?= t27_h($g['bank']) ?><br><b><?= t27_h($g['num']) ?></b><br><?= t27_h($g['name']) ?></p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php if ($giftMsg !== ''): ?><p class="gift-msg small"><?= nl2br(t27_h($giftMsg)) ?></p><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>window.T27 = <?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php
if (is_file($jsFile)) { echo '<script>' . str_replace('</script', '<\\/script', file_get_contents($jsFile)) . '</script>'; }
else { echo '<script src="' . $TPL . '/script.js"></script>'; }
?>
</body>
</html>