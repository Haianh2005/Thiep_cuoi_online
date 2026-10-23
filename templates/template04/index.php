<?php
/**
 * Template 04 – "Ely Paper"
 * Được require từ /w/index.php, nên đã có sẵn biến $wedding (và thường là $pdo).
 */

if (!defined('TP_BASE')) define('TP_BASE', '/WeddingProject');
$docRoot = rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$tplUrl  = str_replace($docRoot, '', str_replace('\\', '/', __DIR__));
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

/* ---------- Helper (giống file mẫu chạy đúng) ---------- */
if (!function_exists('tp_h')) {
    function tp_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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

    function tp_rows($pdo, $sql, $params) {
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            return $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return []; }
    }

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
$groom = $wedding['groom_name'] ?? '';
$bride = $wedding['bride_name'] ?? '';

$ts      = !empty($wedding['wedding_date']) ? strtotime($wedding['wedding_date']) : time();
$wDay    = date('d', $ts);
$wYear   = (int)date('Y', $ts);
$wMonthN = (int)date('n', $ts);
$wDayN   = (int)date('j', $ts);
$weekdays = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'];
$wWeekday = mb_strtoupper($weekdays[(int)date('w', $ts)]);
$wTime   = !empty($wedding['wedding_time']) ? substr($wedding['wedding_time'], 0, 5) : '';
$lunar   = tp_pick($wedding, ['lunar_date', 'am_lich']);

$bgUrl    = tp_url($wedding['background_image'] ?? '', 'weddings');
$storyImg = tp_url($wedding['story_image'] ?? '', 'weddings');
$groomImg = tp_url($wedding['groom_photo'] ?? '', 'weddings') ?: ($storyImg ?: $bgUrl);
$brideImg = tp_url($wedding['bride_photo'] ?? '', 'weddings') ?: ($storyImg ?: $bgUrl);
$musicUrl = tp_url($wedding['music'] ?? '', 'music');
$story    = $wedding['story'] ?? '';

/* Album */
$photos = [];
if ($pdo instanceof PDO) {
    $pc  = tp_cols($pdo, 'wedding_photos');
    $ord = in_array('sort_order', $pc, true) ? 'sort_order, id' : 'id';
    foreach (tp_rows($pdo, "SELECT * FROM wedding_photos WHERE wedding_id = ? ORDER BY $ord", [$wid]) as $r) {
        $u = tp_url(tp_pick($r, ['image', 'photo', 'image_path', 'photo_path', 'file', 'filename', 'file_name', 'path', 'url']), 'weddings');
        if ($u) $photos[] = $u;
    }
}
if (!$photos) {
    foreach ([$bgUrl, $storyImg] as $u) if ($u) $photos[] = $u;
}

/* Timeline */
$events = [];
if ($pdo instanceof PDO) {
    $ec  = tp_cols($pdo, 'wedding_events');
    $tc  = tp_first_col($ec, ['event_time', 'time', 'start_time']);
    $ord = in_array('sort_order', $ec, true) ? 'sort_order, id' : ($tc ? "$tc, id" : 'id');
    foreach (tp_rows($pdo, "SELECT * FROM wedding_events WHERE wedding_id = ? ORDER BY $ord", [$wid]) as $r) {
        $t = tp_pick($r, ['event_time', 'time', 'start_time']);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $t, $m)) $t = sprintf('%02d', $m[1]) . ':' . $m[2];
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
$address  = trim((string)($wedding['address'] ?? ''));
$venue    = trim((string)($wedding['venue'] ?? ''));
$mapQuery = $address !== '' ? $address : $venue;
$mapUrl   = $mapQuery !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($mapQuery) : '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Thiệp cưới <?= tp_h($groom) ?> &amp; <?= tp_h($bride) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Allura&family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Playfair+Display:wght@500;600&display=swap&subset=vietnamese" rel="stylesheet">
<link rel="stylesheet" href="<?= tp_h($tplUrl) ?>/style.css?v=<?= @filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>
<main class="card">

<!-- 1. OPENING -->
<section class="hero" <?= $bgUrl ? 'style="background-image:url(\'' . tp_h($bgUrl) . '\')"' : '' ?>>
  <div class="hero-text">
    <p class="script hero-script">We are getting married!</p>
    <h1 class="hero-names"><span><?= tp_h(mb_strtoupper($groom)) ?></span><i>&amp;</i><span><?= tp_h(mb_strtoupper($bride)) ?></span></h1>
    <p class="hero-date"><?= date('d.m.Y', $ts) ?></p>
  </div>
</section>

<!-- 2. CẶP ĐÔI -->
<section class="paper intro">
  <h2 class="caps">Thư mời tham dự lễ cưới</h2>
  <p class="script big">Khách mời</p>
  <hr class="rule">
  <p class="lead">Trân trọng kính mời đến dự buổi lễ cưới của<br><?= tp_h($groom) ?> &amp; <?= tp_h($bride) ?></p>

  <div class="duo">
    <div class="duo-row left">
      <div class="duo-label"><p class="script">Chú rể</p><p class="caps name"><?= tp_h(mb_strtoupper($groom)) ?></p></div>
      <?php if ($groomImg): ?><img src="<?= tp_h($groomImg) ?>" alt="Chú rể <?= tp_h($groom) ?>" loading="lazy"><?php endif; ?>
    </div>
    <div class="duo-row right">
      <?php if ($brideImg): ?><img src="<?= tp_h($brideImg) ?>" alt="Cô dâu <?= tp_h($bride) ?>" loading="lazy"><?php endif; ?>
      <div class="duo-label"><p class="script">Cô dâu</p><p class="caps name"><?= tp_h(mb_strtoupper($bride)) ?></p></div>
    </div>
  </div>
  <?php if ($story !== ''): ?><p class="story"><?= nl2br(tp_h($story)) ?></p><?php endif; ?>
</section>

<!-- 3. GIA ĐÌNH + SỰ KIỆN -->
<?php if ($storyImg): ?><div class="band" style="background-image:url('<?= tp_h($storyImg) ?>')"></div><?php endif; ?>
<section class="paper family">
  <?php if ($parents['groom'] || $parents['bride']): ?>
  <div class="fam">
    <div class="fam-col"><h3 class="caps">NHÀ TRAI</h3>
      <?php foreach ($parents['groom'] as $l): ?><p><?= tp_h($l) ?></p><?php endforeach; ?>
    </div>
    <div class="fam-col"><h3 class="caps">NHÀ GÁI</h3>
      <?php foreach ($parents['bride'] as $l): ?><p><?= tp_h($l) ?></p><?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="event-frame">
    <p class="caps small">Lễ thành hôn được tổ chức<?= $wTime ? '<br>vào lúc ' . tp_h(str_replace(':', ' giờ ', $wTime)) . ' phút' : '' ?></p>
    <p class="caps weekday"><?= tp_h($wWeekday) ?></p>
    <div class="datebar"><span>THÁNG <?= $wMonthN ?></span><b><?= $wDay ?></b><span>NĂM <?= $wYear ?></span></div>
    <?php if ($lunar): ?><p class="italic">( <?= tp_h($lunar) ?> )</p><?php endif; ?>
    <?php if ($venue): ?><p class="caps venue"><?= tp_h($venue) ?></p><?php endif; ?>
    <?php if ($address): ?><p class="addr"><?= tp_h($address) ?></p><?php endif; ?>
    <?php if ($mapUrl): ?><a class="btn-pill" target="_blank" rel="noopener" href="<?= tp_h($mapUrl) ?>">CHỈ ĐƯỜNG</a><?php endif; ?>
  </div>
</section>

<!-- 4. LỊCH + TIMELINE -->
<section class="calendar" <?= $bgUrl ? 'style="background-image:url(\'' . tp_h($bgUrl) . '\')"' : '' ?>>
  <div class="cal-shade">
    <p class="script cal-month">Tháng <?= $wMonthN ?></p>
    <div class="cal-grid">
      <?php foreach (['MON', 'TUE', 'WED', 'THUR', 'FRI', 'SAT', 'SUN'] as $d): ?><span class="dow"><?= $d ?></span><?php endforeach; ?>
      <?php for ($i = 0; $i < $offset; $i++): ?><span></span><?php endfor; ?>
      <?php for ($d = 1; $d <= $daysIn; $d++): ?>
        <?php if ($d === $wDayN): ?><span class="day mark"><em><?= $d ?></em></span><?php else: ?><span class="day"><?= $d ?></span><?php endif; ?>
      <?php endfor; ?>
    </div>
  </div>
</section>
<?php if ($events): ?>
<section class="paper timeline">
  <h2 class="script big">Timeline</h2>
  <ol class="tl">
    <?php foreach ($events as $i => $ev): ?>
    <li class="<?= $i % 2 ? 'r' : 'l' ?>">
      <div class="tl-item"><b><?= tp_h($ev['time']) ?></b><span><?= tp_h($ev['title']) ?></span></div>
      <i class="dot"></i>
    </li>
    <?php endforeach; ?>
  </ol>
</section>
<?php endif; ?>

<!-- 5. ALBUM -->
<?php if ($photos): ?>
<section class="album" aria-label="Album cưới">
  <div class="stage" id="stage">
    <?php foreach ($photos as $i => $u): ?><img class="slide" src="<?= tp_h($u) ?>" alt="Ảnh cưới <?= $i + 1 ?>" draggable="false"><?php endforeach; ?>
    <button class="nav prev" id="albPrev" type="button" aria-label="Ảnh trước">&#8249;</button>
    <button class="nav next" id="albNext" type="button" aria-label="Ảnh sau">&#8250;</button>
  </div>
  <div class="thumbs" id="thumbs">
    <?php foreach ($photos as $i => $u): ?><img src="<?= tp_h($u) ?>" alt="" loading="lazy"><?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- 6. SỔ LƯU BÚT -->
<section class="paper wish">
  <p class="lead">Hãy xác nhận sự có mặt của bạn trước để chúng mình chuẩn bị đón tiếp một cách chu đáo nhất.<br>Trân trọng!</p>
  <div class="wish-form">
    <input id="wishName" type="text" maxlength="100" placeholder="Tên của bạn" autocomplete="name">
    <textarea id="wishMessage" rows="4" maxlength="1000" placeholder="Gửi lời chúc tới cô dâu chú rể"></textarea>
    <select id="wishAttend">
      <option value="">Bạn sẽ tham gia chứ?</option>
      <option value="Sẽ tham dự">Tôi sẽ tham dự</option>
      <option value="Rất tiếc không thể tham dự">Rất tiếc, tôi không thể tham dự</option>
    </select>
    <button id="sendWish" type="button" class="btn-pill wide">GỬI LỜI CHÚC VÀ XÁC NHẬN</button>
    <p id="wishStatus" class="status" role="status" aria-live="polite"></p>
  </div>
  <div id="wishList" class="wish-list">
    <?php if (!$wishes): ?><p class="empty">Hãy là người đầu tiên gửi lời chúc.</p><?php endif; ?>
    <?php foreach ($wishes as $w): ?>
      <article class="wish-item">
        <b><?= tp_h($w['name']) ?></b>
        <p><?= nl2br(tp_h($w['msg'])) ?></p>
        <?php if ($w['time']): ?><small><?= tp_h($w['time']) ?></small><?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- 7. HỘP QUÀ -->
<section class="gift">
  <p class="small-cap">A LITTLE GIFT</p>
  <h2 class="gift-title">Hộp quà mừng</h2>
  <p class="lead">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>
  <button type="button" class="gift-card" id="openGift">
    <svg viewBox="0 0 48 48" width="46" height="46" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="6" y="18" width="36" height="24"/><rect x="4" y="12" width="40" height="6"/><path d="M24 12v30M24 12c-4-8-12-6-10-2s10 2 10 2zm0 0c4-8 12-6 10-2s-10 2-10 2z"/></svg>
    <strong>Gửi quà mừng cưới</strong><span>Nhấn để xem thông tin</span>
  </button>
  <p class="script thanks">Thank you for being with us</p>
</section>
</main>

<!-- MODAL QUÀ -->
<div class="modal" id="giftModal" hidden>
  <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="giftHead">
    <button class="close" id="closeGift" type="button" aria-label="Đóng">&times;</button>
    <h2 class="modal-title" id="giftHead">Hộp mừng cưới</h2>
    <p class="lead">Vợ chồng mình xin cảm ơn tất cả tình cảm của mọi người ạ ♥</p>
    <?php if ($giftMode === 'message' && $giftMessage !== ''): ?>
      <p class="gift-msg"><?= nl2br(tp_h($giftMessage)) ?></p>
    <?php else: ?>
    <div class="qr-grid">
      <?php foreach (['groom' => ['MỪNG CƯỚI ĐẾN CHÚ RỂ', 'chú rể'], 'bride' => ['MỪNG CƯỚI ĐẾN CÔ DÂU', 'cô dâu']] as $s => $meta): $g = $gift[$s]; ?>
      <div class="qr-col"><h3><?= tp_h($meta[0]) ?></h3>
        <div class="qr-box">
          <?php if ($g['qr']): ?><img src="<?= tp_h($g['qr']) ?>" alt="Mã QR <?= tp_h($meta[1]) ?>"><?php else: ?><span>Chưa có QR</span><?php endif; ?>
        </div>
        <?php if ($g['bank'] || $g['acc'] || $g['name']): ?>
        <p class="bank"><b><?= tp_h($g['name']) ?></b><?= $g['name'] ? '<br>' : '' ?><?= tp_h($g['bank']) ?> <?= tp_h($g['acc']) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($musicUrl): ?>
<audio id="weddingMusic" loop preload="none" src="<?= tp_h($musicUrl) ?>"></audio>
<button class="music" id="musicBtn" type="button" aria-label="Bật/tắt nhạc" aria-pressed="false">&#9834;</button>
<?php endif; ?>

<script src="<?= tp_h($tplUrl) ?>/script.js?v=<?= @filemtime(__DIR__ . '/script.js') ?>"></script>
</body>
</html>