<?php
/**
 * templates/template20/index.php  – "Đỏ Rượu Vang"
 * Được require từ /w/index.php (đã có $wedding, thường có cả $pdo).
 * Lời chúc được xử lý ngay trong file này (POST action=wish) – không cần api/wishes.php.
 */
if (!defined('TP_BASE')) define('TP_BASE', '/WeddingProject');
$BASE = TP_BASE;
$TPL  = TP_BASE . '/templates/' . basename(__DIR__);

/* ---------- PDO ---------- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    foreach (['db', 'conn', 'dbh'] as $n) { if (isset($$n) && $$n instanceof PDO) { $pdo = $$n; break; } }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $f = __DIR__ . '/../../config/database.php';
    if (is_file($f)) require_once $f;
}

/* ---------- Helper ---------- */
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
        try { $cols = []; foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $r) $cols[] = $r['Field']; }
        catch (Throwable $e) { $cols = []; }
        return $cache[$table] = $cols;
    }
    function tp_pick($row, $cands) {
        foreach ($cands as $c) if (isset($row[$c]) && trim((string)$row[$c]) !== '') return trim((string)$row[$c]);
        return '';
    }
    function tp_first_col($cols, $cands) { foreach ($cands as $c) if (in_array($c, $cols, true)) return $c; return null; }
    function tp_rows($pdo, $sql, $params) {
        try { $st = $pdo->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $e) { return []; }
    }
    function tp_side($row) {
        $s = '';
        foreach (['side', 'type', 'owner', 'party', 'person', 'gift_type', 'for'] as $k) if (isset($row[$k])) $s .= ' ' . mb_strtolower((string)$row[$k]);
        if (preg_match('/groom|trai|nam|chu_re|chure|male/', $s) && !preg_match('/female/', $s)) return 'groom';
        if (preg_match('/bride|gai|nu|co_dau|codau|female/', $s)) return 'bride';
        return '';
    }
}

/* ---------- ID thiệp (có dự phòng theo slug) ---------- */
$wid = (int)($wedding['id'] ?? $wedding['wedding_id'] ?? 0);
if (!$wid && ($pdo instanceof PDO) && !empty($_GET['slug'])) {
    $q = tp_rows($pdo, 'SELECT * FROM weddings WHERE slug = ? LIMIT 1', [$_GET['slug']]);
    if ($q) { $wedding = $q[0]; $wid = (int)$q[0]['id']; }
}

/* ---------- Gửi lời chúc ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'wish') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
    $msg  = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 1000);
    if ($name === '' || $msg === '') { echo json_encode(['ok' => false, 'error' => 'Vui lòng nhập tên và lời chúc.']); exit; }
    if (!$wid) { echo json_encode(['ok' => false, 'error' => 'Không xác định được thiệp (thiếu id).']); exit; }
    try {
        $cols    = tp_cols($pdo, 'wedding_wishes');
        $nameCol = tp_first_col($cols, ['guest_name', 'name', 'sender_name', 'fullname', 'full_name']);
        $msgCol  = tp_first_col($cols, ['message', 'content', 'wish', 'wish_message', 'wishes']);
        if (!$nameCol || !$msgCol) throw new Exception('Bảng wedding_wishes thiếu cột tên/lời chúc.');
        $fields = ['wedding_id', $nameCol, $msgCol]; $marks = ['?', '?', '?']; $vals = [$wid, $name, $msg];
        if (in_array('created_at', $cols, true)) { $fields[] = 'created_at'; $marks[] = 'NOW()'; }
        $pdo->prepare('INSERT INTO wedding_wishes (`' . implode('`,`', $fields) . '`) VALUES (' . implode(',', $marks) . ')')->execute($vals);
        echo json_encode(['ok' => true, 'name' => $name, 'message' => $msg, 'time' => date('H:i d/m/Y')]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => 'Không gửi được lời chúc: ' . $e->getMessage()]);
    }
    exit;
}

/* ---------- Dữ liệu cơ bản ---------- */
$groom = $wedding['groom_name'] ?? '';
$bride = $wedding['bride_name'] ?? '';
$ts    = !empty($wedding['wedding_date']) ? strtotime($wedding['wedding_date']) : false;
$time  = !empty($wedding['wedding_time']) ? substr($wedding['wedding_time'], 0, 5) : '';
$dowN  = ['CHỦ NHẬT','THỨ HAI','THỨ BA','THỨ TƯ','THỨ NĂM','THỨ SÁU','THỨ BẢY'];
$dow   = $ts ? $dowN[(int)date('w', $ts)] : '';
$dateDot   = $ts ? date('d.m.Y', $ts) : '';
$targetISO = $ts ? date('Y-m-d', $ts) . 'T' . ($time ?: '00:00') . ':00' : '';
$lunar = $wedding['lunar_date'] ?? '';

$bg     = tp_url($wedding['background_image'] ?? '', 'weddings');
$story  = tp_url($wedding['story_image'] ?? '', 'weddings');
$gPhoto = tp_url($wedding['groom_photo'] ?? '', 'weddings');
$bPhoto = tp_url($wedding['bride_photo'] ?? '', 'weddings');
$music  = tp_url($wedding['music'] ?? '', 'music');
$fmtBd  = function ($d) { $t = $d ? strtotime($d) : false; return $t ? date('d/m/Y', $t) : ''; };
$gBirth = $fmtBd($wedding['groom_birthday'] ?? '');
$bBirth = $fmtBd($wedding['bride_birthday'] ?? '');

/* ---------- Bố mẹ hai bên ---------- */
$parents = ['groom' => [], 'bride' => []];
if ($pdo instanceof PDO) {
    $loc = ['groom' => '', 'bride' => ''];
    foreach (tp_rows($pdo, 'SELECT * FROM wedding_parents WHERE wedding_id = ? ORDER BY id', [$wid]) as $i => $r) {
        $side = tp_side($r) ?: ($i === 0 ? 'groom' : 'bride');
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
    foreach ($loc as $sd => $l) if ($l !== '') $parents[$sd][] = $l;
}

/* ---------- Timeline ---------- */
$events = [];
if ($pdo instanceof PDO) {
    $ec  = tp_cols($pdo, 'wedding_events');
    $tc  = tp_first_col($ec, ['event_time', 'time', 'start_time']);
    $ord = in_array('sort_order', $ec, true) ? 'sort_order, id' : ($tc ? "$tc, id" : 'id');
    foreach (tp_rows($pdo, "SELECT * FROM wedding_events WHERE wedding_id = ? ORDER BY $ord", [$wid]) as $r) {
        $t = tp_pick($r, ['event_time', 'time', 'start_time']);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $t, $m)) $t = sprintf('%02d', $m[1]) . ':' . $m[2];
        $ti = tp_pick($r, ['title', 'event_name', 'name', 'label', 'event_title']);
        if ($t !== '' || $ti !== '') $events[] = ['time' => $t, 'title' => $ti];
    }
}

/* ---------- Quà mừng / QR ---------- */
$gift = ['groom' => ['qr' => '', 'bank' => '', 'acc' => '', 'name' => ''], 'bride' => ['qr' => '', 'bank' => '', 'acc' => '', 'name' => '']];
$giftMode = $wedding['gift_mode'] ?? ''; $giftMsg = $wedding['gift_message'] ?? '';
if ($pdo instanceof PDO) {
    foreach (tp_rows($pdo, 'SELECT * FROM wedding_gifts WHERE wedding_id = ? ORDER BY id', [$wid]) as $i => $r) {
        if ($giftMode === '' && !empty($r['gift_mode'])) $giftMode = $r['gift_mode'];
        if ($giftMsg === '' && !empty($r['gift_message'])) $giftMsg = $r['gift_message'];
        $wide = false;
        foreach (['groom', 'bride'] as $sd) {
            $q = tp_pick($r, [$sd . '_qr', $sd . '_qr_image', $sd . '_qr_code']);
            if ($q !== '') {
                $wide = true;
                $gift[$sd] = ['qr' => tp_url($q, 'gifts'),
                    'bank' => tp_pick($r, [$sd . '_bank', $sd . '_bank_name']),
                    'acc'  => tp_pick($r, [$sd . '_account', $sd . '_account_number']),
                    'name' => tp_pick($r, [$sd . '_account_name', $sd . '_receiver'])];
            }
        }
        if ($wide) continue;
        $side = tp_side($r) ?: ($i === 0 ? 'groom' : 'bride');
        $gift[$side] = [
            'qr'   => tp_url(tp_pick($r, ['qr_image', 'qr', 'qr_code', 'qr_path', 'qr_file', 'image']), 'gifts'),
            'bank' => tp_pick($r, ['bank_name', 'bank']),
            'acc'  => tp_pick($r, ['account_number', 'account_no', 'bank_account', 'stk']),
            'name' => tp_pick($r, ['account_name', 'receiver_name', 'holder', 'recipient', 'name']),
        ];
    }
}
if ($giftMode === '') $giftMode = 'bank';

/* ---------- Lời chúc đã gửi ---------- */
$wishes = [];
if ($pdo instanceof PDO) {
    foreach (tp_rows($pdo, 'SELECT * FROM wedding_wishes WHERE wedding_id = ? ORDER BY id DESC LIMIT 100', [$wid]) as $r) {
        $wishes[] = [
            'name' => tp_pick($r, ['guest_name', 'name', 'sender_name', 'fullname', 'full_name']),
            'msg'  => tp_pick($r, ['message', 'content', 'wish', 'wish_message', 'wishes']),
            'time' => !empty($r['created_at']) ? date('H:i d/m/Y', strtotime($r['created_at'])) : '',
        ];
    }
}

/* ---------- Album ---------- */
$photos = [];
if ($pdo instanceof PDO) {
    $pc  = tp_cols($pdo, 'wedding_photos');
    $ord = in_array('sort_order', $pc, true) ? 'sort_order, id' : 'id';
    foreach (tp_rows($pdo, "SELECT * FROM wedding_photos WHERE wedding_id = ? ORDER BY $ord", [$wid]) as $r) {
        $u = tp_url(tp_pick($r, ['image', 'photo', 'image_path', 'photo_path', 'file', 'filename', 'file_name', 'path', 'url']), 'weddings');
        if ($u) $photos[] = $u;
    }
}
$photoTotal = count($photos);
$slots = array_slice($photos, 0, 5);
$more  = max(0, $photoTotal - 5);

/* ---------- Lịch tháng ---------- */
$calHtml = '';
if ($ts) {
    $y = (int)date('Y', $ts); $m = (int)date('n', $ts); $d = (int)date('j', $ts);
    $first = (int)date('w', mktime(0, 0, 0, $m, 1, $y));
    $days  = (int)date('t', mktime(0, 0, 0, $m, 1, $y));
    $calHtml .= '<div class="cal-head">';
    foreach (['CN','T2','T3','T4','T5','T6','T7'] as $h) $calHtml .= '<span>' . $h . '</span>';
    $calHtml .= '</div><div class="cal-grid">';
    for ($i = 0; $i < $first; $i++) $calHtml .= '<span></span>';
    for ($i = 1; $i <= $days; $i++) $calHtml .= '<span' . ($i === $d ? ' class="on"' : '') . '>' . ($i === $d ? '<b>' . $i . '</b>' : $i) . '</span>';
    $calHtml .= '</div>';
}

$venue = $wedding['venue'] ?? '';
$addr  = $wedding['address'] ?? '';
$mapQ  = $addr !== '' ? $addr : $venue;
$title = trim($bride . ' & ' . $groom);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Thiệp cưới <?= tp_h($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Allura&family=Montserrat:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,700;0,800;1,400;1,500&family=Rouge+Script&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/WeddingProject/templates/template20/style.css">
</head>
<body>

<!-- icon dùng chung -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-car" viewBox="0 0 32 32"><path d="M4 21v-5l3-6h18l3 6v5zM4 16h24" /><circle cx="10" cy="22" r="2.6"/><circle cx="22" cy="22" r="2.6"/></symbol>
  <symbol id="i-ring" viewBox="0 0 32 32"><circle cx="16" cy="21" r="6.5"/><path d="M12.5 8.5L16 5l3.5 3.5L16 13z"/></symbol>
  <symbol id="i-heart" viewBox="0 0 32 32"><path d="M16 26S5 19 5 12a5.5 5.5 0 0 1 11-1 5.5 5.5 0 0 1 11 1c0 7-11 14-11 14z"/></symbol>
  <symbol id="i-glass" viewBox="0 0 32 32"><path d="M11 5h10l-1.2 9a3.8 3.8 0 0 1-7.6 0zM16 18v8M11 26h10"/></symbol>
</svg>

<main class="t4" data-target="<?= tp_h($targetISO) ?>">

  <!-- 1. HERO -->
  <section class="hero" style="<?= $bg ? "background-image:url('" . tp_h($bg) . "')" : '' ?>">
    <div class="hero-text">
      <p class="hero-script">We get married!</p>
      <h1 class="hero-names">
        <span><?= tp_h(mb_strtoupper($bride)) ?></span>
        <i>&amp;</i>
        <span><?= tp_h(mb_strtoupper($groom)) ?></span>
      </h1>
      <p class="hero-date"><?= tp_h($dateDot) ?></p>
      <p class="hero-sub">We will become husband and wife in</p>
    </div>
  </section>

  <div class="countdown" id="countdown">
    <div><b id="cdD">0</b><small>ngày</small></div>
    <div><b id="cdH">0</b><small>giờ</small></div>
    <div><b id="cdM">0</b><small>phút</small></div>
    <div><b id="cdS">0</b><small>giây</small></div>
  </div>

  <!-- 2. SAVE THE DATE -->
  <section class="std">
    <h2 class="std-title"><span>SAVE</span><em>the</em><span class="r">DATE</span></h2>
  </section>

  <!-- 3. GIA ĐÌNH + THƯ MỜI -->
  <section class="invite">
    <div class="family">
      <?php foreach (['bride' => 'Nhà gái', 'groom' => 'Nhà trai'] as $k => $label): ?>
        <div class="fam">
          <h3><?= $label ?></h3>
          <?php foreach ($parents[$k] as $line): ?><p><?= tp_h($line) ?></p><?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <p class="invite-line">Thân mời đến dự lễ thành hôn của<br>chúng mình!</p>

    <h2 class="invite-names">
      <span><?= tp_h($bride) ?></span><i>&amp;</i><span><?= tp_h($groom) ?></span>
    </h2>

    <p class="held">Được tổ chức vào lúc</p>
    <p class="big-time"><?= tp_h($time) ?><?= $time && $dow ? ' - ' : '' ?><?= tp_h($dow) ?></p>
    <p class="big-date"><?= tp_h($dateDot) ?></p>
    <?php if ($lunar): ?><p class="lunar">Nhằm ngày <?= tp_h($lunar) ?></p><?php endif; ?>

    <?php if ($calHtml): ?>
    <div class="calendar">
      <svg class="clip" viewBox="0 0 40 90" aria-hidden="true"><path d="M26 30V68a8 8 0 0 1-16 0V20a12 12 0 0 1 24 0V64" fill="none" stroke="#8d949b" stroke-width="3.2" stroke-linecap="round"/></svg>
      <div class="sheet">
        <div class="holes"></div>
        <p class="cal-month">Tháng <?= (int)date('n', $ts) ?> · <?= date('Y', $ts) ?></p>
        <?= $calHtml ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="venue">
      <p class="venue-label">Địa điểm :</p>
      <?php if ($venue): ?><p class="venue-name"><?= tp_h($venue) ?></p><?php endif; ?>
      <?php if ($addr): ?><p class="venue-addr"><?= tp_h($addr) ?></p><?php endif; ?>
    </div>
  </section>

  <!-- 4. MAP -->
  <?php if ($mapQ): ?>
  <section class="map">
    <iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Bản đồ"
      src="https://www.google.com/maps?q=<?= rawurlencode($mapQ) ?>&amp;output=embed"></iframe>
  </section>
  <?php endif; ?>

  <!-- 5. CÔ DÂU – CHÚ RỂ (so le) -->
  <section class="couple">
    <div class="pair bride">
      <div class="ph"><?php if ($bPhoto): ?><img src="<?= tp_h($bPhoto) ?>" alt="Cô dâu <?= tp_h($bride) ?>" loading="lazy"><?php endif; ?></div>
      <div class="who">
        <span class="role">Cô dâu</span>
        <strong><?= tp_h($bride) ?></strong>
        <?php if ($bBirth): ?><small><?= tp_h($bBirth) ?></small><?php endif; ?>
      </div>
    </div>
    <div class="pair groom">
      <div class="who">
        <span class="role">Chú rể</span>
        <strong><?= tp_h($groom) ?></strong>
        <?php if ($gBirth): ?><small><?= tp_h($gBirth) ?></small><?php endif; ?>
      </div>
      <div class="ph"><?php if ($gPhoto): ?><img src="<?= tp_h($gPhoto) ?>" alt="Chú rể <?= tp_h($groom) ?>" loading="lazy"><?php endif; ?></div>
    </div>
  </section>

  <!-- ALBUM (collage kính mờ) -->
  <?php if ($slots): ?>
  <section class="album" id="album">
    <div class="alb-bg" style="background-image:url('<?= tp_h($slots[0]) ?>')"></div>
    <div class="alb-stage">
      <div class="alb-frame"></div>
      <p class="alb-title">INVITATION</p>
      <p class="alb-side l">I love you forever</p>
      <p class="alb-side r">Nice to meet you</p>
      <?php foreach ($slots as $i => $u): ?>
        <button type="button" class="alb-ph p<?= $i + 1 ?>" data-i="<?= $i ?>" aria-label="Xem ảnh <?= $i + 1 ?>">
          <img src="<?= tp_h($u) ?>" alt="" loading="lazy">
          <?php if ($i === 4 && $more > 0): ?><span class="alb-more">+<?= $more ?></span><?php endif; ?>
        </button>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- 6. TIMELINE (nền = story_image, mờ theo tone ảnh) -->
  <?php if ($events): ?>
  <section class="timeline" id="timeline">
    <?php if ($story): ?><div class="tl-bg" style="background-image:url('<?= tp_h($story) ?>')" data-tone-src="<?= tp_h($story) ?>"></div><?php endif; ?>
    <h2>TIMELINE</h2>
    <div class="tl-track">
      <?php $ic = ['i-car','i-ring','i-heart','i-glass'];
      foreach ($events as $i => $ev): ?>
        <div class="tl-item">
          <svg class="tl-ic"><use href="#<?= $ic[$i % 4] ?>"/></svg>
          <span class="dot"></span>
          <b><?= tp_h($ev['time']) ?></b>
          <span><?= tp_h($ev['title']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- 7. SỔ LƯU BÚT -->
  <section class="guestbook" id="guestbook">
    <div class="wish-card">
      <h2>SỔ LƯU BÚT</h2>
      <input id="wishName" type="text" placeholder="Nhập tên*" maxlength="60" autocomplete="name">
      <textarea id="wishMessage" placeholder="Nhập lời chúc*" maxlength="500"></textarea>
      <div class="wish-actions"><button id="sendWish" type="button">GỬI LỜI CHÚC</button></div>
      <p class="wish-note" id="wishNote" hidden></p>
    </div>
    <div class="wish-list" id="wishList" data-wedding="<?= $wid ?>" >
      <?php foreach ($wishes as $w): ?>
        <article class="wish"><header><b><?= tp_h($w['name']) ?></b><time><?= tp_h($w['time']) ?></time></header><p><?= nl2br(tp_h($w['msg'])) ?></p></article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 8. HỘP QUÀ -->
  <section class="gift">
    <p class="gift-eyebrow">A little gift</p>
    <h2>Hộp quà mừng</h2>
    <p class="gift-sub">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>
    <button class="gift-card" id="openGift" type="button">
      <svg viewBox="0 0 64 64" aria-hidden="true"><g fill="none" stroke="#7a1f2b" stroke-width="2.6" stroke-linejoin="round" stroke-linecap="round"><path d="M12 28h40v26H12zM9 20h46v8H9zM32 20v34"/><path d="M32 20c-4-9-14-11-14-5s8 5 14 5zM32 20c4-9 14-11 14-5s-8 5-14 5z"/></g></svg>
      <span class="g1">Gửi quà mừng cưới</span>
      <span class="g2">Nhấn để xem thông tin</span>
    </button>
    <p class="thanks">Thank you for being with us</p>
  </section>

  <!-- 9. FOOTER (nửa chiều cao màn hình) -->
  <footer class="foot" <?= $story ? 'style="background-image:url(\'' . tp_h($story) . '\')"' : '' ?>>
    <div class="foot-text">
      <p>Cảm ơn bạn đã ghé thăm tấm thiệp nhỏ này.</p>
      <p>Sự hiện diện và lời chúc của bạn<br>là món quà ý nghĩa nhất với chúng mình.</p>
      <p class="wl">With love,<br>chú rể &amp; cô dâu</p>
    </div>
  </footer>
</main>

<!-- Thanh nổi -->
<div class="dock">
  <a class="dock-pill" href="#guestbook">Gửi lời chúc...</a>
  <button class="dock-btn" id="dockGift" type="button" aria-label="Quà mừng">
    <svg viewBox="0 0 24 24"><path d="M4 11h16v9H4zM3 7h18v4H3zM12 7v13M12 7C10 3 6 4 7 6s5 1 5 1zM12 7c2-4 6-3 5-1s-5 1-5 1z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
  </button>
  <?php if ($music): ?>
  <button class="dock-btn music" id="musicBtn" type="button" aria-label="Bật/tắt nhạc">
    <svg viewBox="0 0 24 24"><path d="M9 18V6l10-2v12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="7" cy="18" r="2.4" fill="currentColor"/><circle cx="17" cy="16" r="2.4" fill="currentColor"/></svg>
  </button>
  <audio id="weddingMusic" loop preload="none" src="<?= tp_h($music) ?>"></audio>
  <?php endif; ?>
</div>

<!-- Lightbox album -->
<?php if ($photos): ?>
<div class="lb" id="lightbox" hidden>
  <button class="lb-x" id="lbClose" type="button" aria-label="Đóng">×</button>
  <button class="lb-nav prev" id="lbPrev" type="button" aria-label="Ảnh trước">‹</button>
  <img id="lbImg" alt="">
  <button class="lb-nav next" id="lbNext" type="button" aria-label="Ảnh sau">›</button>
  <p class="lb-count" id="lbCount"></p>
</div>
<script>window.T20 = { photos: <?= json_encode($photos, JSON_UNESCAPED_SLASHES) ?> };</script>
<?php endif; ?>

<!-- Modal quà -->
<div class="modal" id="giftModal" hidden>
  <div class="modal-box" role="dialog" aria-modal="true" aria-label="Hộp quà mừng">
    <button class="modal-x" id="closeGift" type="button" aria-label="Đóng">×</button>
    <h3>HỘP QUÀ MỪNG</h3>
    <?php if ($giftMode === 'message'): ?>
      <p class="gift-msg"><?= nl2br(tp_h($giftMsg ?: 'Sự hiện diện của bạn là niềm vui của chúng mình.')) ?></p>
    <?php else: ?>
      <div class="qr-grid">
        <?php foreach (['groom' => 'Chú Rể', 'bride' => 'Cô Dâu'] as $k => $label): $g = $gift[$k]; ?>
          <div class="qr">
            <h4><?= $label ?><?= $g['name'] ? ' - ' . tp_h($g['name']) : '' ?></h4>
            <div class="qr-box">
              <?php if ($g['qr']): ?><img src="<?= tp_h($g['qr']) ?>" alt="QR <?= $label ?>"><?php else: ?><span>Chưa có mã QR <?= mb_strtolower($label) ?></span><?php endif; ?>
            </div>
            <?php if ($g['bank'] || $g['acc']): ?><p class="bank"><?= tp_h($g['bank']) ?><br><b><?= tp_h($g['acc']) ?></b></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="/WeddingProject/templates/template20/script.js"></script>
</body>
</html>