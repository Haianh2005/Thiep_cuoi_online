<?php
/**
 * Template 09 – "Green Story"
 * Được require từ /w/index.php, nên đã có sẵn biến $wedding (và thường là $pdo).
 * Đường dẫn CSS/JS tự lấy theo tên thư mục nên đổi tên thư mục vẫn chạy.
 */

if (!defined('TP_BASE')) define('TP_BASE', '/WeddingProject');
$tplUrl = TP_BASE . '/templates/' . basename(__DIR__);

/* ---------- PDO ---------- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    foreach (['db', 'conn', 'dbh'] as $n) {
        if (isset($$n) && $$n instanceof PDO) { $pdo = $$n; break; }
    }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $f = __DIR__ . '/../../config/database.php';
    if (is_file($f)) require_once $f;
}

/* ---------- Helper ---------- */
if (!function_exists('tp_h')) {
    function tp_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

    /** Đổi giá trị trong DB thành URL ảnh/nhạc dùng được (chấp nhận cả tên file lẫn đường dẫn đầy đủ) */
    function tp_url($v, $dir) {
        $v = trim((string)$v);
        if ($v === '') return '';
        if (preg_match('~^(https?:)?//~i', $v)) return $v;
        $v = ltrim(str_replace('\\', '/', $v), '/');
        if (strpos($v, 'WeddingProject/') === 0) return '/' . $v;
        if (strpos($v, 'uploads/') === 0) return TP_BASE . '/' . $v;
        return TP_BASE . '/uploads/' . $dir . '/' . $v;
    }

    function tp_cols($pdo, $table) {
        static $cache = [];
        if (isset($cache[$table])) return $cache[$table];
        try {
            $cols = [];
            foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $r) $cols[] = $r['Field'];
        } catch (Throwable $e) { $cols = []; }
        return $cache[$table] = $cols;
    }

    function tp_pick($row, $cands) {
        foreach ($cands as $c) {
            if (isset($row[$c]) && trim((string)$row[$c]) !== '') return trim((string)$row[$c]);
        }
        return '';
    }

    function tp_first_col($cols, $cands) {
        foreach ($cands as $c) if (in_array($c, $cols, true)) return $c;
        return null;
    }

    function tp_short($name) {
        $p = preg_split('/\s+/u', trim((string)$name));
        $last = end($p);
        return $last !== false && $last !== '' ? $last : (string)$name;
    }

    function tp_rows($pdo, $sql, $params) {
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return []; }
    }

    /** Nhận diện bên: groom / bride / '' */
    function tp_side($row) {
        $s = '';
        foreach (['side', 'type', 'owner', 'party', 'person', 'gift_type', 'for'] as $k) {
            if (isset($row[$k])) $s .= ' ' . mb_strtolower((string)$row[$k]);
        }
        if (preg_match('/groom|trai|nam|chu_re|chure|male/', $s) && !preg_match('/female/', $s)) return 'groom';
        if (preg_match('/bride|gai|nu|co_dau|codau|female/', $s)) return 'bride';
        return '';
    }
}

$wid = (int)($wedding['id'] ?? 0);

/* ---------- Gửi lời chúc (xử lý ngay trong template, không phụ thuộc api/wishes.php) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'wish') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $name = trim((string)($_POST['name'] ?? ''));
    $msg  = trim((string)($_POST['message'] ?? ''));
    if ($name === '' || $msg === '') {
        echo json_encode(['ok' => false, 'error' => 'Vui lòng nhập tên và lời chúc.']); exit;
    }
    $name = mb_substr($name, 0, 100);
    $msg  = mb_substr($msg, 0, 1000);
    try {
        $cols    = tp_cols($pdo, 'wedding_wishes');
        $nameCol = tp_first_col($cols, ['guest_name', 'name', 'sender_name', 'fullname', 'full_name']);
        $msgCol  = tp_first_col($cols, ['message', 'content', 'wish', 'wish_message', 'wishes']);
        if (!$nameCol || !$msgCol) throw new Exception('Bảng wedding_wishes thiếu cột tên/lời chúc.');
        $fields = ['wedding_id', $nameCol, $msgCol];
        $marks  = ['?', '?', '?'];
        $vals   = [$wid, $name, $msg];
        if (in_array('created_at', $cols, true)) { $fields[] = 'created_at'; $marks[] = 'NOW()'; }
        $sql = 'INSERT INTO wedding_wishes (`' . implode('`,`', $fields) . '`) VALUES (' . implode(',', $marks) . ')';
        $pdo->prepare($sql)->execute($vals);
        echo json_encode(['ok' => true, 'name' => $name, 'message' => $msg, 'time' => date('d/m/Y H:i')]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => 'Không gửi được lời chúc: ' . $e->getMessage()]);
    }
    exit;
}

/* ---------- Dữ liệu ---------- */
$brand      = '';      // chữ chìm trên ảnh (để trống '' nếu không muốn hiện)
$shortNames = true;        // true: tên lớn chỉ lấy tên gọi (Trung Hiếu -> Hiếu); false: lấy đủ tên

$groomFull = $wedding['groom_name'] ?? '';
$brideFull = $wedding['bride_name'] ?? '';
$groomBig  = $shortNames ? tp_short($groomFull) : $groomFull;
$brideBig  = $shortNames ? tp_short($brideFull) : $brideFull;

$ts       = !empty($wedding['wedding_date']) ? strtotime($wedding['wedding_date']) : time();
$wDay     = date('d', $ts);
$wMonth   = date('m', $ts);
$wYear2   = date('y', $ts);
$wYear    = (int)date('Y', $ts);
$wMonthN  = (int)date('n', $ts);
$wDayN    = (int)date('j', $ts);
$weekdays = ['Chủ Nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
$wWeekday = mb_strtoupper($weekdays[(int)date('w', $ts)]);
$wTime    = !empty($wedding['wedding_time']) ? substr($wedding['wedding_time'], 0, 5) : '';

$bgUrl    = tp_url($wedding['background_image'] ?? '', 'weddings');
$storyImg = tp_url($wedding['story_image'] ?? '', 'weddings');
$musicUrl = tp_url($wedding['music'] ?? '', 'music');
$guest    = trim((string)($_GET['guest'] ?? '')) ?: 'Khách mời';

/* Album */
$photos = [];
if ($pdo instanceof PDO) {
    $pc   = tp_cols($pdo, 'wedding_photos');
    $ord  = in_array('sort_order', $pc, true) ? 'sort_order, id' : 'id';
    foreach (tp_rows($pdo, "SELECT * FROM wedding_photos WHERE wedding_id = ? ORDER BY $ord", [$wid]) as $r) {
        $u = tp_url(tp_pick($r, ['image', 'photo', 'image_path', 'photo_path', 'file', 'filename', 'file_name', 'path', 'url']), 'weddings');
        if ($u) $photos[] = $u;
    }
}
if (!$photos) {
    foreach ([$bgUrl, $storyImg] as $u) if ($u) $photos[] = $u;
}
$heroPhotos = $photos;

/* Timeline */
$events = [];
if ($pdo instanceof PDO) {
    $ec  = tp_cols($pdo, 'wedding_events');
    $tc  = tp_first_col($ec, ['event_time', 'time', 'start_time']);
    $ord = in_array('sort_order', $ec, true) ? 'sort_order, id' : ($tc ? "$tc, id" : 'id');
    foreach (tp_rows($pdo, "SELECT * FROM wedding_events WHERE wedding_id = ? ORDER BY $ord", [$wid]) as $r) {
        $t = tp_pick($r, ['event_time', 'time', 'start_time']);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $t, $m)) $t = sprintf('%02d', $m[1]) . 'h' . $m[2];
        $title = tp_pick($r, ['title', 'event_name', 'name', 'label', 'event_title']);
        if ($t !== '' || $title !== '') $events[] = ['time' => $t, 'title' => $title];
    }
}

/* Bố mẹ hai bên */
$parents = ['groom' => [], 'bride' => []];
if ($pdo instanceof PDO) {
    $rows = tp_rows($pdo, 'SELECT * FROM wedding_parents WHERE wedding_id = ? ORDER BY id', [$wid]);
    $loc  = ['groom' => '', 'bride' => ''];
    foreach ($rows as $i => $r) {
        $side = tp_side($r);
        if ($side === '') $side = $i === 0 ? 'groom' : 'bride';
        $father = tp_pick($r, ['father_name', 'father', 'bo', 'dad']);
        $mother = tp_pick($r, ['mother_name', 'mother', 'me', 'mom']);
        if ($father !== '' || $mother !== '') {
            if ($father !== '') $parents[$side][] = (preg_match('/^(ông|ong)/iu', $father) ? '' : 'Ông. ') . $father;
            if ($mother !== '') $parents[$side][] = (preg_match('/^(bà|ba)\b/iu', $mother) ? '' : 'Bà. ') . $mother;
        } else {
            $n = tp_pick($r, ['name', 'full_name', 'parent_name']);
            if ($n !== '') {
                $rel = mb_strtolower(tp_pick($r, ['relation', 'role', 'relationship', 'title']));
                $pre = preg_match('/father|bố|bo|cha|ông/u', $rel) ? 'Ông. ' : (preg_match('/mother|mẹ|me|má|bà/u', $rel) ? 'Bà. ' : '');
                $parents[$side][] = (preg_match('/^(ông|bà)/iu', $n) ? '' : $pre) . $n;
            }
        }
        $l = tp_pick($r, ['address', 'location', 'hometown', 'city', 'place']);
        if ($l !== '') $loc[$side] = $l;
    }
    foreach ($loc as $s => $l) if ($l !== '') $parents[$s][] = $l;
}

/* Quà mừng / QR */
$gift = ['groom' => ['qr' => '', 'bank' => '', 'acc' => '', 'name' => ''],
         'bride' => ['qr' => '', 'bank' => '', 'acc' => '', 'name' => '']];
$giftMode    = $wedding['gift_mode'] ?? '';
$giftMessage = $wedding['gift_message'] ?? '';
if ($pdo instanceof PDO) {
    $rows = tp_rows($pdo, 'SELECT * FROM wedding_gifts WHERE wedding_id = ? ORDER BY id', [$wid]);
    foreach ($rows as $i => $r) {
        if ($giftMode === '' && !empty($r['gift_mode'])) $giftMode = $r['gift_mode'];
        if ($giftMessage === '' && !empty($r['gift_message'])) $giftMessage = $r['gift_message'];
        // kiểu 1 dòng có cả 2 bên: groom_qr, bride_qr...
        $wide = false;
        foreach (['groom', 'bride'] as $s) {
            $q = tp_pick($r, [$s . '_qr', $s . '_qr_image', $s . '_qr_code']);
            if ($q !== '') {
                $wide = true;
                $gift[$s]['qr']   = tp_url($q, 'gifts');
                $gift[$s]['bank'] = tp_pick($r, [$s . '_bank', $s . '_bank_name']);
                $gift[$s]['acc']  = tp_pick($r, [$s . '_account', $s . '_account_number']);
                $gift[$s]['name'] = tp_pick($r, [$s . '_account_name', $s . '_receiver']);
            }
        }
        if ($wide) continue;
        // kiểu mỗi bên 1 dòng
        $side = tp_side($r);
        if ($side === '') $side = $i === 0 ? 'groom' : 'bride';
        $gift[$side] = [
            'qr'   => tp_url(tp_pick($r, ['qr_image', 'qr', 'qr_code', 'qr_path', 'qr_file', 'image']), 'gifts'),
            'bank' => tp_pick($r, ['bank_name', 'bank']),
            'acc'  => tp_pick($r, ['account_number', 'account_no', 'bank_account', 'stk']),
            'name' => tp_pick($r, ['account_name', 'receiver_name', 'holder', 'recipient', 'name']),
        ];
    }
}

/* Lời chúc đã gửi */
$wishes = [];
if ($pdo instanceof PDO) {
    foreach (tp_rows($pdo, 'SELECT * FROM wedding_wishes WHERE wedding_id = ? ORDER BY id DESC LIMIT 50', [$wid]) as $r) {
        $wishes[] = [
            'name' => tp_pick($r, ['guest_name', 'name', 'sender_name', 'fullname', 'full_name']),
            'msg'  => tp_pick($r, ['message', 'content', 'wish', 'wish_message', 'wishes']),
            'time' => !empty($r['created_at']) ? date('d/m/Y H:i', strtotime($r['created_at'])) : '',
        ];
    }
}

/* Lịch */
$firstTs = mktime(0, 0, 0, $wMonthN, 1, $wYear);
$daysIn  = (int)date('t', $firstTs);
$offset  = (int)date('N', $firstTs) - 1;

/* Địa chỉ + bản đồ */
$address = trim((string)($wedding['address'] ?? ''));
$venue   = trim((string)($wedding['venue'] ?? ''));
$fullAddr = trim($venue . ($venue && $address ? ' - ' : '') . $address);
$mapQuery = $address !== '' ? $address : $venue;
$mapUrl   = $mapQuery !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($mapQuery) : '';

$heart = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.5-4.6-9.6-9.2C.9 8.4 2.8 5 6.1 5c2 0 3.4 1.1 4.1 2.3h3.6C14.5 6.1 15.9 5 17.9 5c3.3 0 5.2 3.4 3.7 6.8C19.5 16.4 12 21 12 21z"/></svg>';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Thiệp cưới <?= tp_h($groomFull) ?> &amp; <?= tp_h($brideFull) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Alegreya+Sans:wght@400;500;700;800&family=Allura&family=Cinzel:wght@500;700&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Great+Vibes&family=Montserrat:wght@500;600;700&family=Nunito:wght@400;600&family=Playfair+Display:wght@500;700;900&display=swap&subset=vietnamese" rel="stylesheet">
<link rel="stylesheet" href="/WeddingProject/templates/template17/style.css">
</head>
<body class="is-locked">

<div class="hearts" id="hearts" aria-hidden="true"></div>

<!-- ============ 1. MỞ THIỆP ============ -->
<div class="opening" id="opening" role="button" tabindex="0" aria-label="Chạm để mở thiệp"
     <?= $bgUrl ? 'style="--open-bg:url(\'' . tp_h($bgUrl) . '\')"' : '' ?>>
    <div class="opening-title"><span>Thiệp</span><span>mời</span></div>
    <div class="opening-bottom">
        <p class="opening-tap">Chạm để mở thiệp</p>
        <div class="opening-names">
            <span><?= tp_h(mb_strtoupper($groomFull)) ?></span>
            <span><?= tp_h(mb_strtoupper($brideFull)) ?></span>
        </div>
    </div>
</div>

<main class="wrap" id="main">

    <!-- ============ 2. HERO: ẢNH BACKGROUND TĨNH ============ -->
    <section class="hero ss">
        <?php if ($bgUrl): ?>
            <img class="ss-img is-on" src="<?= tp_h($bgUrl) ?>" alt="">
        <?php endif; ?>
        <div class="hero-names">
            <span class="hn-groom"><?= tp_h($groomBig) ?></span>
            <span class="hn-and">and</span>
            <span class="hn-bride"><?= tp_h($brideBig) ?></span>
        </div>
        <?php if ($brand): ?><div class="hero-brand"><?= tp_h($brand) ?></div><?php endif; ?>
    </section>

    <!-- ============ 3. LOVE STORY + NGÀY ============ -->
    <section class="story paper">
        <div class="story-top">
            <div class="story-text">
                <h2 class="script story-title">Love Story</h2>
                <p><?= nl2br(tp_h($wedding['story'] ?? '')) ?></p>
            </div>
            <div class="date-stack" aria-label="Ngày cưới">
                <span><?= $wDay ?></span><span><?= $wMonth ?></span><span><?= $wYear2 ?></span>
            </div>
        </div>
        <h3 class="invite-title">THƯ MỜI THAM DỰ LỄ CƯỚI</h3>
        <p class="script invite-guest"><?= tp_h($guest) ?></p>
        <div class="line-dot"></div>
    </section>
    <?php if ($storyImg): ?>
        <img class="story-photo" src="<?= tp_h($storyImg) ?>" alt="Ảnh câu chuyện" loading="lazy">
    <?php endif; ?>

    <!-- ============ 4. THÔNG TIN LỄ ============ -->
    <section class="info paper">
        <p class="info-invite">TRÂN TRỌNG KÍNH MỜI BẠN TỚI DỰ BUỔI TIỆC CƯỚI<br>CÙNG GIA ĐÌNH CHÚNG TÔI</p>

        <div class="info-names">
            <svg class="swirl" viewBox="0 0 300 120" aria-hidden="true"><path d="M2 30 C60 -10 150 0 190 40 C215 65 160 100 120 80 C100 70 110 40 150 38 C200 36 260 60 298 70" fill="none" stroke="currentColor" stroke-width="1.2"/></svg>
            <span class="in-groom"><?= tp_h($groomBig) ?></span>
            <span class="in-and">and</span>
            <span class="in-bride"><?= tp_h($brideBig) ?></span>
        </div>

        <p class="info-time"><?= tp_h($wTime) ?><?= $wTime ? ', ' : '' ?><?= tp_h($wWeekday) ?></p>
        <p class="info-date"><?= $wDay ?> &nbsp;/&nbsp; <?= $wMonth ?> &nbsp;/&nbsp; <?= $wYear2 ?></p>

        <?php if ($fullAddr): ?>
            <div class="info-addr">
                <strong>ĐỊA CHỈ</strong>
                <p><?= tp_h(mb_strtoupper($fullAddr)) ?></p>
            </div>
        <?php endif; ?>

        <hr class="info-rule">

        <?php if ($parents['groom'] || $parents['bride']): ?>
        <div class="family">
            <div class="fam">
                <h4>NHÀ TRAI</h4>
                <?php foreach ($parents['groom'] as $l): ?><p><?= tp_h($l) ?></p><?php endforeach; ?>
            </div>
            <div class="fam">
                <h4>NHÀ GÁI</h4>
                <?php foreach ($parents['bride'] as $l): ?><p><?= tp_h($l) ?></p><?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($mapUrl): ?>
            <a class="btn-map" href="<?= tp_h($mapUrl) ?>" target="_blank" rel="noopener">CHỈ ĐƯỜNG</a>
        <?php endif; ?>
    </section>

    <!-- ============ 5. LỊCH + ALBUM ĐỔI MỖI GIÂY ============ -->
    <section class="calendar">
        <div class="cal-head">
            <h3 class="script">Tháng <?= $wMonthN ?></h3>
            <span class="cal-line"></span>
        </div>
        <div class="cal-grid">
            <?php foreach (['MON', 'TUE', 'WED', 'THUR', 'FRI', 'SAT', 'SUN'] as $d): ?><b><?= $d ?></b><?php endforeach; ?>
            <?php for ($i = 0; $i < $offset; $i++): ?><i></i><?php endfor; ?>
            <?php for ($d = 1; $d <= $daysIn; $d++): ?>
                <span class="<?= $d === $wDayN ? 'is-day' : '' ?>"><em><?= $d ?></em></span>
            <?php endfor; ?>
        </div>
    </section>
    <section class="cal-photo ss" data-ss>
        <?php foreach ($photos as $i => $u): ?>
            <img class="ss-img<?= $i === 0 ? ' is-on' : '' ?>" src="<?= tp_h($u) ?>" alt="" loading="lazy">
        <?php endforeach; ?>
        <span class="script cal-photo-text">Destiny</span>
    </section>

    <!-- ============ 6. TIMELINE ============ -->
    <?php if ($events): ?>
    <section class="timeline paper">
        <div class="tl-head"><h3 class="script">Time Line</h3><span class="tl-headline"></span></div>
        <div class="tl" id="tl">
            <svg class="tl-path" id="tlPath" aria-hidden="true"><path d="" fill="none"/></svg>
            <?php foreach ($events as $i => $e): ?>
                <div class="tl-item <?= $i % 2 ? 'is-right' : 'is-left' ?>">
                    <div class="tl-text">
                        <strong><?= tp_h($e['time']) ?></strong>
                        <span><?= tp_h(mb_strtoupper($e['title'])) ?></span>
                    </div>
                    <i class="tl-dot"></i>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ 7. ALBUM CÔ DÂU CHÚ RỂ ============ -->
    <?php if ($photos): ?>
    <section class="album paper" id="album">
        <div class="album-view">
            <div class="album-track" id="albumTrack">
                <?php foreach ($photos as $i => $u): ?>
                    <figure class="album-slide<?= $i === 0 ? ' is-on' : '' ?>"><img src="<?= tp_h($u) ?>" alt="" loading="lazy"></figure>
                <?php endforeach; ?>
            </div>
            <?php if ($brand): ?><span class="album-brand"><?= tp_h($brand) ?></span><?php endif; ?>
            <button class="album-btn prev" id="albumPrev" type="button" aria-label="Ảnh trước">&#8249;</button>
            <button class="album-btn next" id="albumNext" type="button" aria-label="Ảnh sau">&#8250;</button>
        </div>
        <div class="album-thumbs" id="albumThumbs">
            <?php foreach ($photos as $i => $u): ?>
                <button type="button" class="<?= $i === 0 ? 'is-on' : '' ?>" data-i="<?= $i ?>"><img src="<?= tp_h($u) ?>" alt="" loading="lazy"></button>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- ============ 8. LỜI CẢM ƠN CỐ ĐỊNH ============ -->
    <section class="thanks paper">
        <div class="thanks-card">
            <h3 class="script">Trân trọng cảm ơn</h3>
            <span class="tdot"></span>
            <p>Cảm ơn bạn đã dành thời gian ghé thăm và gửi những lời chúc yêu thương đến chúng mình.</p>
            <p>Sự hiện diện và lời chúc của bạn là niềm vui và hạnh phúc lớn đối với chúng mình trong ngày đặc biệt này.</p>
            <p>Dù gần hay xa, chúng mình đều trân trọng từng tình cảm bạn dành cho ngày vui của hai đứa.<br>Thương mến và biết ơn bạn thật nhiều! <span class="mini-heart"><?= $heart ?></span></p>
            <span class="tdot"></span>
            <small>THÂN</small>
            <div class="script thanks-names"><?= tp_h($groomBig) ?> &amp; <?= tp_h($brideBig) ?></div>
        </div>
    </section>

    <!-- ============ 9. SỔ LƯU BÚT ============ -->
    <section class="guestbook">
        <h3>SỔ LƯU BÚT</h3>
        <div class="wish-form" id="wishForm">
            <input type="text" id="wishName" maxlength="100" placeholder="Nhập tên*" autocomplete="name">
            <textarea id="wishMessage" maxlength="1000" placeholder="Nhập lời chúc*"></textarea>
            <p class="wish-error" id="wishError" role="alert" hidden></p>
            <button type="button" id="sendWish">GỬI LỜI CHÚC</button>
        </div>
        <div class="wish-list" id="wishList">
            <?php foreach ($wishes as $w): ?>
                <article class="wish">
                    <strong><?= tp_h($w['name']) ?></strong>
                    <p><?= nl2br(tp_h($w['msg'])) ?></p>
                    <?php if ($w['time']): ?><time><?= tp_h($w['time']) ?></time><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ============ 10. HỘP QUÀ MỪNG ============ -->
    <section class="gift">
        <p class="gift-eyebrow">A LITTLE GIFT</p>
        <h3>Hộp quà mừng</h3>
        <p class="gift-sub">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>
        <button class="gift-card" id="giftOpen" type="button">
            <svg viewBox="0 0 64 64" class="gift-icon" aria-hidden="true">
                <path d="M32 24 L8 30 V50 L32 58 L56 50 V30 Z" fill="#dfe8d3" stroke="#8a1515" stroke-width="3" stroke-linejoin="round"/>
                <path d="M32 24 V58 M8 30 L32 38 L56 30" fill="none" stroke="#8a1515" stroke-width="3" stroke-linejoin="round"/>
                <path d="M32 24 C22 10 10 14 16 22 C20 26 28 24 32 24 C36 24 44 26 48 22 C54 14 42 10 32 24Z" fill="none" stroke="#8a1515" stroke-width="3" stroke-linejoin="round"/>
            </svg>
            <strong>Gửi quà mừng cưới</strong>
            <span>Nhấn để xem thông tin</span>
        </button>
        <p class="gift-foot">Thank you for being with us</p>
    </section>

    <div class="modal" id="giftModal" hidden>
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="giftTitle">
            <div class="modal-head">
                <h4 id="giftTitle">HỘP QUÀ MỪNG</h4>
                <button type="button" class="modal-close" id="giftClose" aria-label="Đóng">&times;</button>
            </div>
            <div class="modal-body">
                <?php if ($giftMode === 'message' && $giftMessage !== ''): ?>
                    <p class="gift-message"><?= nl2br(tp_h($giftMessage)) ?></p>
                <?php else: ?>
                    <?php foreach (['groom' => ['Chú Rể', $groomFull, 'chú rể'], 'bride' => ['Cô Dâu', $brideFull, 'cô dâu']] as $s => $meta): $g = $gift[$s]; ?>
                    <div class="gift-col">
                        <h5><?= tp_h($meta[0]) ?> - <?= tp_h($meta[1]) ?></h5>
                        <div class="qr-box">
                            <?php if ($g['qr']): ?>
                                <img src="<?= tp_h($g['qr']) ?>" alt="Mã QR <?= tp_h($meta[2]) ?>">
                            <?php else: ?>
                                <span>Chưa có mã QR <?= tp_h($meta[2]) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($g['bank'] || $g['acc'] || $g['name']): ?>
                            <p class="qr-info">
                                <?= tp_h($g['name']) ?><?= $g['name'] ? '<br>' : '' ?>
                                <?= tp_h($g['bank']) ?><?= $g['bank'] ? '<br>' : '' ?>
                                <?= tp_h($g['acc']) ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============ 11. CẢM ƠN CUỐI ============ -->
    <section class="final">
        <?php if ($storyImg): ?><img src="<?= tp_h($storyImg) ?>" alt="" loading="lazy"><?php endif; ?>
        <div class="final-cap">
            <span class="script">Thank you</span>
            <p>Cảm ơn đã dành tình cảm cho chúng mình! Sự hiện diện của bạn chính là món quà ý nghĩa nhất, và chúng mình vô cùng trân quý khi được cùng bạn chia sẻ niềm hạnh phúc trong ngày trọng đại này.</p>
        </div>
    </section>
</main>

<!-- ============ NHẠC ============ -->
<?php if ($musicUrl): ?>
<audio id="weddingMusic" src="<?= tp_h($musicUrl) ?>" loop preload="auto"></audio>
<button class="music-btn" id="musicBtn" type="button" aria-label="Bật/tắt nhạc" aria-pressed="false">
    <svg class="ic-on" viewBox="0 0 24 24"><path d="M3 10v4h4l5 4V6L7 10H3z" fill="currentColor"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18 6a8.5 8.5 0 0 1 0 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    <svg class="ic-off" viewBox="0 0 24 24"><path d="M3 10v4h4l5 4V6L7 10H3z" fill="currentColor"/><path d="M16 9l5 6M21 9l-5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
</button>
<?php endif; ?>

<script>window.TP = { heart: <?= json_encode($heart) ?> };</script>
<script src="/WeddingProject/templates/template17/script.js"></script>
</body>
</html>