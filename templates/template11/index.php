<?php
/**
 * TEMPLATE 11 – RED ROMANCE
 * Được require từ w/index.php  → biến $wedding (mảng 1 dòng bảng weddings) đã có sẵn.
 * Nếu w/index.php có $pdo thì dùng luôn, không thì tự nạp config/database.php.
 */
if (!isset($pdo)) {
    @require_once __DIR__ . '/../../config/database.php';
}

if (!defined('T11_BASE'))  define('T11_BASE', '/WeddingProject/');
if (!defined('T11_ASSET')) define('T11_ASSET', '/WeddingProject/templates/' . basename(__DIR__) . '/');  // tự lấy đúng tên thư mục
$TPL = rtrim(T11_ASSET, '/');
// Đặt true để xem chẩn đoán (trong mã nguồn trang, tìm chữ "T11 DEBUG"). Xong nhớ đặt lại false.
if (!defined('T11_DEBUG')) define('T11_DEBUG', false);

/* ---------- Helpers (bọc function_exists để không trùng với demo.php / w/index.php) ---------- */
if (!function_exists('e')) {
    function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('pick')) {
    function pick($row, array $keys, $default = '') {
        foreach ($keys as $k) {
            if (is_array($row) && isset($row[$k]) && trim((string)$row[$k]) !== '') return $row[$k];
        }
        return $default;
    }
}
if (!function_exists('imgUrl')) {
    function imgUrl($path) {
        $path = trim((string)$path);
        if ($path === '') return '';
        if (preg_match('#^(https?:)?//#', $path) || $path[0] === '/') return $path;
        if (strpos($path, '/') !== false) return T11_BASE . ltrim($path, '/');
        return T11_BASE . 'uploads/weddings/' . $path;
    }
}
if (!function_exists('rows')) {
    /* Đọc các dòng của 1 bảng theo wedding_id. Nếu ORDER BY lỗi (bảng không có cột id) thì thử lại không ORDER BY. */
    function rows($pdo, $table, $wid, $order = 'id ASC') {
        if (!$pdo || !$wid) return [];
        foreach ([$order, ''] as $ord) {
            try {
                $sql = "SELECT * FROM `$table` WHERE wedding_id = ?" . ($ord !== '' ? " ORDER BY $ord" : '');
                $st = $pdo->prepare($sql);
                $st->execute([$wid]);
                return $st->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $ex) {
                error_log('[T11] rows(' . $table . '): ' . $ex->getMessage());
            }
        }
        // Bảng có khóa ngoại tên khác (wedding, wid...): lọc theo cột liên quan đến wedding
        try {
            $all = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $ex) {
            error_log('[T11] rows(' . $table . ') fallback: ' . $ex->getMessage());
            return [];
        }
        $out = [];
        foreach ($all as $r) {
            foreach ($r as $k => $v) {
                if (array_intersect(t11_tokens($k), ['wedding', 'wid', 'weddingid']) && (string)$v === (string)$wid) {
                    $out[] = $r;
                    break;
                }
            }
        }
        return $out;
    }
}

/* ---------- Đọc thông tin gia đình, nhận diện theo tên cột (vd: groom_father, father_name, side=groom...) ---------- */
/* Bọc chữ số trong span.num để đoạn chữ serif vẫn hiển thị số bằng Montserrat (cùng chiều cao với chữ) */
if (!function_exists('numfont')) {
    function numfont($raw) {
        $parts = preg_split('/(\d+)/', (string)$raw, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        foreach ($parts as $i => $p) {
            $out .= ($i % 2) ? '<span class="num">' . e($p) . '</span>' : e($p);
        }
        return $out;
    }
}
if (!function_exists('t11_norm')) {
    /* Bỏ dấu tiếng Việt và chữ hoa: "Nhà gái" -> "nha gai", "Bố" -> "bo", "Đ" -> "d" */
    function t11_norm($s) {
        $s = mb_strtolower(trim((string)$s), 'UTF-8');
        $s = strtr($s, ['đ' => 'd']);
        if (class_exists('Normalizer')) {
            $n = Normalizer::normalize($s, Normalizer::FORM_D);
            if ($n !== false) $s = preg_replace('/\p{Mn}+/u', '', $n);
        } else {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($t !== false) $s = $t;
        }
        return (string)$s;
    }
}
if (!function_exists('t11_tokens')) {
    function t11_tokens($s) {
        return preg_split('/[^a-z]+/', t11_norm($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
if (!function_exists('t11_side')) {
    function t11_side(array $t) {
        if (array_intersect($t, ['groom', 'trai', 'nhatrai', 'chure', 'male', 'nam'])) return 'groom';
        if (array_intersect($t, ['bride', 'gai', 'nhagai', 'codau', 'dau', 'female', 'nu'])) return 'bride';
        return '';
    }
}
if (!function_exists('t11_role')) {
    function t11_role(array $t) {
        if (array_intersect($t, ['address', 'addr', 'diachi'])) return 'addr';   // kiểm tra địa chỉ trước
        if (array_intersect($t, ['father', 'dad', 'papa', 'bo', 'cha', 'ong'])) return 'father';
        if (array_intersect($t, ['mother', 'mom', 'mum', 'me'])) return 'mother';
        return '';
    }
}
if (!function_exists('t11_role_value')) {
    /* Giá trị trong cột quan hệ, ví dụ: "Ông", "Bố", "Father", "Bà", "Mẹ" */
    function t11_role_value(array $t) {
        if (array_intersect($t, ['father', 'dad', 'papa', 'bo', 'cha', 'ong'])) return 'father';
        if (array_intersect($t, ['mother', 'mom', 'mum', 'me', 'ba'])) return 'mother';
        return '';
    }
}
if (!function_exists('t11_fill_family')) {
    function t11_fill_family($row, array &$fam) {
        if (!is_array($row)) return;
        $metaKeys = ['side', 'type', 'role', 'ben', 'phia', 'nha', 'family', 'group', 'relation', 'relationship', 'quanhe', 'loai'];
        $nameKeys = ['name', 'ten', 'hoten', 'full', 'fullname'];

        // Bước 1: cột phân loại trong dòng, ví dụ side = "Nhà gái" hoặc relation = "Mẹ"
        $rowSide = ''; $rowRole = '';
        foreach ($row as $k => $v) {
            if (!array_intersect(t11_tokens($k), $metaKeys)) continue;
            $vt = t11_tokens($v);
            $rowSide = t11_side($vt) ?: $rowSide;
            $rowRole = t11_role_value($vt) ?: $rowRole;
        }

        // Bước 2: gán từng cột có giá trị vào đúng phía + đúng vai trò
        foreach ($row as $k => $v) {
            $v = trim((string)$v);
            if ($v === '') continue;
            $t    = t11_tokens($k);
            $side = t11_side($t) ?: $rowSide;
            $role = t11_role($t);
            if ($role === '' && $rowRole !== '' && array_intersect($t, $nameKeys)) $role = $rowRole;
            if ($side === '' || $role === '') continue;
            $fam[$side][$role] = $v;
        }
    }
}

/* ---------- Dữ liệu chính ---------- */
$wid     = $wedding['id'] ?? 0;
$groom   = $wedding['groom_name'] ?? '';
$bride   = $wedding['bride_name'] ?? '';
$venue   = $wedding['venue'] ?? '';
$address = $wedding['address'] ?? '';
$story   = $wedding['story'] ?? '';
$ts      = !empty($wedding['wedding_date']) ? strtotime($wedding['wedding_date']) : time();
$timeStr = !empty($wedding['wedding_time']) ? substr($wedding['wedding_time'], 0, 5) : '';
$weekdays = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'];
$weekday = $weekdays[(int)date('w', $ts)];
$d = (int)date('j', $ts); $m = (int)date('n', $ts); $y = (int)date('Y', $ts);
$targetISO = date('Y-m-d', $ts) . 'T' . ($timeStr ?: '08:00') . ':00';

$bg       = imgUrl($wedding['background_image'] ?? '');
$storyImg = imgUrl(pick($wedding, ['story_image', 'story_photo', 'story_img', 'story_pic']));  // ảnh dùng cho chân trang
$footImg  = $storyImg ?: $bg;
$gPhoto   = imgUrl($wedding['groom_photo'] ?? '');
$bPhoto   = imgUrl($wedding['bride_photo'] ?? '');
$music    = $wedding['music'] ?? '';
$musicUrl = $music ? (strpos($music, '/') !== false ? T11_BASE . ltrim($music, '/') : T11_BASE . 'uploads/music/' . $music) : '';
$lunar    = $wedding['lunar_date'] ?? '';   // tuỳ chọn: nếu có cột lunar_date thì hiển thị

/* ---------- Nhận lời chúc từ form (form gửi POST về chính trang này) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['t11_action'] ?? '') === 'wish') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');

    $wName = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 60, 'UTF-8');
    $wMsg  = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 500, 'UTF-8');
    if (!$wid || $wName === '' || $wMsg === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên và lời chúc.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    try {
        if (empty($pdo)) throw new RuntimeException('Không có kết nối CSDL');
        // Tự nhận cột của bảng wedding_wishes (giống cách đọc lời chúc ở phía dưới)
        $cols = array_map('strtolower', array_column(
            $pdo->query('SHOW COLUMNS FROM `wedding_wishes`')->fetchAll(PDO::FETCH_ASSOC), 'Field'));
        $nameCol = in_array('name', $cols, true) ? 'name' : (in_array('guest_name', $cols, true) ? 'guest_name' : 'author');
        $msgCol  = in_array('message', $cols, true) ? 'message' : (in_array('content', $cols, true) ? 'content' : 'wish');
        $fields  = ['wedding_id', $nameCol, $msgCol];
        $vals    = [$wid, $wName, $wMsg];
        if (in_array('created_at', $cols, true)) { $fields[] = 'created_at'; $vals[] = date('Y-m-d H:i:s'); }
        $sql = 'INSERT INTO `wedding_wishes` (`' . implode('`,`', $fields) . '`) VALUES (' . implode(',', array_fill(0, count($vals), '?')) . ')';
        $pdo->prepare($sql)->execute($vals);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $ex) {
        error_log('[T11] wish: ' . $ex->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Chưa gửi được lời chúc. Vui lòng thử lại.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* Gia đình: đọc từ cột trong bảng weddings + bảng wedding_parents (mọi kiểu tên cột) */
$fam = ['groom' => ['father'=>'','mother'=>'','addr'=>''], 'bride' => ['father'=>'','mother'=>'','addr'=>'']];
t11_fill_family($wedding, $fam);
foreach (rows($pdo ?? null, 'wedding_parents', $wid) as $r) {
    t11_fill_family($r, $fam);
}

/* Chẩn đoán: chỉ thu thập khi T11_DEBUG = true */
$t11Debug = null;
if (T11_DEBUG) {
    $t11Debug = [
        'db_connected'             => !empty($pdo),
        'wedding_id'               => $wid,
        'wedding_columns'          => array_keys((array)$wedding),
        'parents_for_this_wedding' => rows($pdo ?? null, 'wedding_parents', $wid),
        'family_parsed'            => $fam,
    ];
    if (!empty($pdo)) {
        try { $t11Debug['tables'] = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN); }
        catch (Throwable $ex) { $t11Debug['tables_error'] = $ex->getMessage(); }
        try { $t11Debug['wedding_parents_sample'] = $pdo->query('SELECT * FROM `wedding_parents` LIMIT 3')->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $ex) { $t11Debug['wedding_parents_error'] = $ex->getMessage(); }
    }
}

/* Timeline */
$events = [];
foreach (rows($pdo ?? null, 'wedding_events', $wid, 'id ASC') as $r) {
    $t = pick($r, ['event_time','time','start_time']);
    $events[] = ['time' => $t ? substr($t, 0, 5) : '', 'title' => pick($r, ['title','name','event_name','label'])];
}
if (!$events && $timeStr) {
    $events = [
        ['time' => $timeStr, 'title' => 'Đón khách'],
        ['time' => date('H:i', strtotime($timeStr) + 1800), 'title' => 'Khai tiệc'],
    ];
}

/* Album: lấy đường dẫn ảnh từ cột quen thuộc, hoặc bất kỳ cột nào có đuôi ảnh */
$photos = [];
foreach (rows($pdo ?? null, 'wedding_photos', $wid) as $r) {
    $p = pick($r, ['image','photo','file_path','path','filename','file','url','image_path','img','src']);
    if ($p === '') {
        foreach ($r as $v) {
            if (is_string($v) && preg_match('/\.(jpe?g|png|webp|gif|avif)$/i', trim($v))) { $p = trim($v); break; }
        }
    }
    if ($p !== '') $photos[] = imgUrl($p);
}

/* Quà cưới */
$gifts = rows($pdo ?? null, 'wedding_gifts', $wid);
$giftMode = 'bank'; $giftMsg = '';
foreach ($gifts as $g) {
    if (!empty($g['gift_mode']))    $giftMode = $g['gift_mode'];
    if (!empty($g['gift_message'])) $giftMsg  = $g['gift_message'];
}

/* Lời chúc */
$wishes = rows($pdo ?? null, 'wedding_wishes', $wid, 'id DESC LIMIT 100');

/* Lịch tháng */
$firstDow   = (int)date('N', strtotime(date('Y-m-01', $ts))); // 1=T2 … 7=CN
$daysInMonth = (int)date('t', $ts);

$mapQ = urlencode(trim($address ?: $venue));
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Thiệp cưới <?= e($groom) ?> &amp; <?= e($bride) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Playfair+Display:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&family=Ma+Shan+Zheng&display=swap&subset=vietnamese" rel="stylesheet">
<?php
// Nhúng thẳng CSS/JS từ cùng thư mục với file này → không phụ thuộc đường dẫn URL hay cache trình duyệt
$cssFile = __DIR__ . '/style.css'; $jsFile = __DIR__ . '/script.js';
if (is_file($cssFile)) { echo '<style>' . file_get_contents($cssFile) . '</style>'; }
else { echo '<link rel="stylesheet" href="' . $TPL . '/style.css">'; }
?>
</head>
<body>
<canvas id="sparkles" aria-hidden="true"></canvas>

<main class="card-wrap">

<!-- 1. HERO -->
<section class="hero" style="<?= $bg ? "background-image:url('".e($bg)."')" : '' ?>">
    <button class="fab fab-music" id="musicBtn" type="button" aria-label="Bật/tắt nhạc">♪</button>
    <div class="hero-text">
        <h1 class="names script">
            <span><?= e($groom) ?></span>
            <i>&amp;</i>
            <span><?= e($bride) ?></span>
        </h1>
        <p class="hero-date"><?= date('d.m.Y', $ts) ?></p>
    </div>
</section>
<section class="intro">
    <p>Nhân danh tình yêu, hãy cùng đi hết quãng đời còn lại<br>Từ nay trở đi, đông có nắng ấm, còn mình có nhau!</p>
    <div class="hy-big" aria-hidden="true">囍</div>
    <svg class="brush" viewBox="0 0 200 14" aria-hidden="true"><path d="M2 8 C40 2, 80 12, 120 6 S180 4, 198 8" fill="none" stroke="#7a0f12" stroke-width="3" stroke-linecap="round"/></svg>
</section>

<!-- 2. CÔ DÂU – CHÚ RỂ -->
<section class="couple">
    <div class="person bride">
        <span class="role">Cô dâu</span>
        <figure style="<?= $bPhoto ? "background-image:url('".e($bPhoto)."')" : '' ?>">
            <figcaption class="script"><?= e($bride) ?></figcaption>
        </figure>
    </div>
    <div class="person groom">
        <span class="role">Chú rể</span>
        <figure style="<?= $gPhoto ? "background-image:url('".e($gPhoto)."')" : '' ?>">
            <figcaption class="script"><?= e($groom) ?></figcaption>
        </figure>
    </div>
</section>

<!-- 3. CHUYỆN TÌNH + GIA ĐÌNH -->
<?php if (trim($story) !== ''): ?>
<section class="story">
    <h2><span class="script">Câu chuyện</span> <b>CHÚNG MÌNH</b></h2>
    <div class="story-body"><?= nl2br(e($story)) ?></div>
    <svg class="heart-line" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 3 5 6.4 5c2.1 0 3.6 1.2 5.6 3.2C14 6.2 15.5 5 17.6 5 21 5 23.1 8.4 21.6 11.8 19.5 16.4 12 21 12 21z" fill="none" stroke="#fff" stroke-width="1.4"/></svg>
</section>
<div class="hy-box" aria-hidden="true">囍</div>
<?php endif; ?>

<section class="family">
    <h3 class="fam-title">LỄ THÀNH HÔN</h3>
    <?php foreach (['groom' => 'NHÀ TRAI', 'bride' => 'NHÀ GÁI'] as $s => $sideLabel): $f = $fam[$s]; ?>
    <div class="fam-block">
        <h4 class="fam-side"><?= $sideLabel ?></h4>
        <div class="parent-block">
            <?php if ($f['father'] !== ''): ?><p><span class="lbl">Ông</span><b><?= numfont($f['father']) ?></b></p><?php endif; ?>
            <?php if ($f['mother'] !== ''): ?><p><span class="lbl">Bà</span><b><?= numfont($f['mother']) ?></b></p><?php endif; ?>
            <?php if ($f['addr'] !== ''): ?><em><?= numfont($f['addr']) ?></em><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <p class="announce">Trân trọng báo tin<br>Lễ Thành Hôn của con chúng tôi</p>
    <h3 class="couple-names"><?= e(mb_strtoupper($groom)) ?><br><?= e(mb_strtoupper($bride)) ?></h3>
    <p class="script together">Together with our family!</p>

    <div class="date-block">
        <div class="month">THÁNG <?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></div>
        <div class="date-row">
            <span><?= e(mb_strtoupper($weekday)) ?></span>
            <strong><?= $d ?></strong>
            <span><?= $timeStr ? str_replace(':', ' GIỜ ', $timeStr) : '' ?></span>
        </div>
        <div class="year">NĂM <?= $y ?></div>
        <?php if ($lunar): ?><div class="lunar">(Tức ngày <?= e($lunar) ?>)</div><?php endif; ?>
    </div>
    <p class="party">TIỆC THÂN MẬT ĐƯỢC TỔ CHỨC<br>VÀO LÚC <span class="num"><?= e($timeStr) ?></span></p>
</section>

<!-- 4. ĐỊA ĐIỂM -->
<section class="place">
    <p class="place-at">TẠI <?= numfont(mb_strtoupper($venue)) ?></p>
    <?php if ($address): ?><p class="place-addr"><?= numfont(mb_strtoupper($address)) ?></p><?php endif; ?>
    <a class="place-dir" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?= $mapQ ?>">CHỈ ĐƯỜNG</a>
    <div class="map">
        <iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Bản đồ"
                src="https://www.google.com/maps?q=<?= $mapQ ?>&output=embed"></iframe>
    </div>
</section>

<!-- 5. TIMELINE -->
<?php if ($events): ?>
<section class="timeline">
    <span class="tl-eyebrow">WEDDING TIMELINE</span>
    <h2 class="script">Lịch trình ngày cưới</h2>
    <ol class="tl-line" style="--n:<?= count($events) ?>">
        <?php foreach ($events as $ev): ?>
        <li>
            <time><?= e($ev['time']) ?></time>
            <svg class="tl-ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="#fbf6f1" stroke="#7a0f12" stroke-width="1.5"/><path d="M12 7v5l3 2" fill="none" stroke="#7a0f12" stroke-width="1.5" stroke-linecap="round"/></svg>
            <span><?= e(mb_strtoupper($ev['title'])) ?></span>
        </li>
        <?php endforeach; ?>
    </ol>
</section>
<?php endif; ?>

<!-- 6. LỊCH + ĐẾM NGƯỢC -->
<section class="calendar">
    <div class="cal-box">
        <div class="cal-head">Tháng <span class="num"><?= $m ?></span> &nbsp;<b class="num"><?= $y ?></b></div>
        <div class="cal-grid">
            <?php foreach (['T2','T3','T4','T5','T6','T7','CN'] as $h) echo "<span class=\"dow\">$h</span>"; ?>
            <?php for ($i = 1; $i < $firstDow; $i++) echo '<span></span>'; ?>
            <?php for ($i = 1; $i <= $daysInMonth; $i++): ?>
                <span class="<?= $i === $d ? 'on' : '' ?>"><?= $i ?></span>
            <?php endfor; ?>
        </div>
        <div class="cal-big"><?= str_pad($d,2,'0',STR_PAD_LEFT) ?> <i>—</i> <?= str_pad($m,2,'0',STR_PAD_LEFT) ?> <i>—</i> <?= $y ?></div>
        <div class="cal-sub"><span class="num"><?= e($timeStr) ?></span> &nbsp; <?= e($weekday) ?></div>
    </div>
</section>
<section class="countdown">
    <span class="tl-eyebrow">SAVE THE DATE</span>
    <h2 class="script">Our special day</h2>
    <p class="cd-note">Đếm ngược đến ngày chúng mình nên duyên</p>
    <div class="cd" data-target="<?= e($targetISO) ?>">
        <div><b data-u="d">00</b><small>ngày</small></div>
        <div><b data-u="h">00</b><small>giờ</small></div>
        <div><b data-u="m">00</b><small>phút</small></div>
        <div><b data-u="s">00</b><small>giây</small></div>
    </div>
    <p class="cd-foot">Thật mong chờ được cùng bạn chia sẻ ngày vui này.</p>
</section>

<!-- 7. ALBUM -->
<?php if ($photos): $show = array_slice($photos, 0, 5); $more = count($photos) - 5; ?>
<section class="album">
    <span class="alb-top">INVITATION</span>
    <span class="alb-side l script">I love you forever</span>
    <span class="alb-side r script">Nice to meet you</span>
    <div class="alb-grid">
        <?php foreach ($show as $i => $src): ?>
        <button type="button" class="ph p<?= $i + 1 ?>" data-index="<?= $i ?>" style="background-image:url('<?= e($src) ?>')" aria-label="Xem ảnh <?= $i + 1 ?>">
            <?php if ($i === 4 && $more > 0): ?><span class="more">+<?= $more ?></span><?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>
</section>
<script>window.T11_PHOTOS = <?= json_encode($photos, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>;</script>
<?php endif; ?>

<!-- 8. SỔ LƯU BÚT -->
<section class="guestbook">
    <h2 class="script">Sổ Lưu Bút</h2>
    <p class="gb-note">Cảm ơn bạn rất nhiều vì đã gửi những lời chúc mừng tốt đẹp nhất đến đôi mình!</p>
    <div class="gb-form">
        <input id="wishName" type="text" maxlength="60" placeholder="Nhập tên của bạn*" autocomplete="name">
        <textarea id="wishMessage" rows="3" maxlength="500" placeholder="Nhập lời chúc của bạn*"></textarea>
        <button id="sendWish" type="button">GỬI LỜI CHÚC</button>
        <small id="wishStatus" class="gb-status" role="status" aria-live="polite"></small>
    </div>
    <div id="wishList" class="wish-list">
        <?php foreach ($wishes as $w): ?>
        <article class="wish">
            <b><?= e(pick($w, ['name','guest_name','author'])) ?></b>
            <p><?= nl2br(e(pick($w, ['message','content','wish']))) ?></p>
            <time><?= e(pick($w, ['created_at'])) ?></time>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- 9. HỘP QUÀ -->
<section class="gift">
    <h2 class="script">Hộp quà mừng</h2>
    <p class="gift-note">Cảm ơn bạn đã gửi tới chúc phúc đến chúng mình.</p>
    <button type="button" class="gift-card" id="openGift">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9h18v4H3zM5 13v8h14v-8M12 9v12M12 9c-2-4-6-4-6-1.5S10 9 12 9zm0 0c2-4 6-4 6-1.5S14 9 12 9z" fill="none" stroke="#9c1c24" stroke-width="1.5" stroke-linejoin="round"/></svg>
        <strong>Gửi quà mừng cưới</strong>
        <span>Nhấn để xem thông tin</span>
    </button>
    <p class="script thanks">Thank you for being with us</p>
</section>

<!-- FOOTER -->
<footer class="foot" style="<?= $footImg ? "background-image:url('".e($footImg)."')" : '' ?>">
    <div class="foot-text">
        <p>Cảm ơn bạn đã ghé thăm nhé.<br>Sự hiện diện của bạn là niềm vinh hạnh của đôi mình.</p>
        <div class="sig">
            <div class="sig-names">
                <span><?= e($groom) ?></span><span class="sig-amp" aria-hidden="true">&amp;</span><span><?= e($bride) ?></span>
            </div>
            <svg class="sig-swash" viewBox="0 0 300 40" aria-hidden="true">
                <path d="M8 16 C60 4, 96 30, 150 20 S236 6, 292 14" fill="none" stroke="#f4d98c" stroke-width="1.4" stroke-linecap="round"/>
                <path d="M96 30 C140 37, 196 30, 232 34" fill="none" stroke="#f4d98c" stroke-width="1" stroke-linecap="round" opacity=".6"/>
            </svg>
        </div>
    </div>
</footer>
</main>

<button class="fab fab-gift" id="fabGift" type="button" aria-label="Hộp quà mừng">🎁</button>

<!-- MODAL QUÀ -->
<div class="modal" id="giftModal" hidden>
    <div class="modal-box" role="dialog" aria-modal="true" aria-label="Hộp quà mừng">
        <header>HỘP QUÀ MỪNG <button type="button" class="x" data-close aria-label="Đóng">×</button></header>
        <?php if ($giftMode === 'message' && $giftMsg): ?>
            <p class="gift-msg"><?= nl2br(e($giftMsg)) ?></p>
        <?php else: ?>
        <?php if (!$gifts): ?><p class="gift-msg">Thông tin mừng cưới sẽ sớm được cập nhật.</p><?php endif; ?>
        <div class="qr-row">
            <?php foreach ($gifts as $i => $g):
                $qr = imgUrl(pick($g, ['qr_image','qr','qr_code','qr_path']));
                $side = strtolower((string)pick($g, ['side','type','role']));
                $label = in_array($side, ['groom','nha_trai','trai'], true) ? 'Chú Rể'
                       : (in_array($side, ['bride','nha_gai','gai'], true) ? 'Cô Dâu' : ($i === 0 ? 'Chú Rể' : 'Cô Dâu'));
            ?>
            <div class="qr">
                <small><?= e($label) ?> · <?= e(pick($g, ['receiver_name','account_name','name'])) ?></small>
                <?php if ($qr): ?><img src="<?= e($qr) ?>" alt="QR <?= e($label) ?>"><?php endif; ?>
                <small><?= e(pick($g, ['bank_name','bank'])) ?> <?= e(pick($g, ['account_number','bank_account','account'])) ?></small>
                <?php if ($qr): ?><a href="<?= e($qr) ?>" download>⇩ Lưu QR</a><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- LIGHTBOX -->
<div class="lightbox" id="lightbox" hidden>
    <button type="button" class="lb-x" aria-label="Đóng">×</button>
    <button type="button" class="lb-nav prev" aria-label="Ảnh trước">‹</button>
    <img id="lbImg" alt="">
    <button type="button" class="lb-nav next" aria-label="Ảnh sau">›</button>
</div>

<?php if ($musicUrl): ?><audio id="weddingMusic" src="<?= e($musicUrl) ?>" loop preload="none"></audio><?php endif; ?>
<script>window.T11 = { api: <?= json_encode($_SERVER['REQUEST_URI'] ?? '', JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>, weddingId: <?= (int)$wid ?> };</script>
<?php
if (is_file($jsFile)) { echo '<script>' . str_replace('</script', '<\\/script', file_get_contents($jsFile)) . '</script>'; }
else { echo '<script src="' . $TPL . '/script.js"></script>'; }
?>
<?php if ($t11Debug !== null):
    $dbg = json_encode($t11Debug, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR);
    $dbg = str_replace('--', '- -', (string)$dbg); ?>
<!-- T11 DEBUG
<?= $dbg ?>

-->
<?php endif; ?>
</body>
</html>