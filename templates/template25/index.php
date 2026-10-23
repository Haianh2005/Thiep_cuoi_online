<?php
/* Template24 – "Đỏ Rượu Hỷ Sự"
 * Được require từ w/index.php, có sẵn $wedding (và thường là $pdo).
 * Cột nào trong bảng phụ khác tên, sửa ở các mảng pick([...]) bên dưới. */
if (!defined('T24_BASE')) define('T24_BASE', '/WeddingProject/');
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('pick')) { function pick($r, $keys, $d = ''){ foreach ($keys as $k) if (!empty($r[$k])) return $r[$k]; return $d; } }
if (!function_exists('img24')) { function img24($p, $dir = 'weddings'){
    $p = trim((string)$p);
    if ($p === '') return '';
    if (preg_match('~^(https?:)?//~i', $p)) return $p;
    $p = ltrim(str_replace('\\', '/', $p), '/');
    if (strpos($p, 'WeddingProject/') === 0) return '/' . $p;
    if (strpos($p, 'uploads/') === 0) return '/WeddingProject/' . $p;
    if (strpos($p, '/') !== false) return '/WeddingProject/' . $p;
    return '/WeddingProject/uploads/' . $dir . '/' . $p;
} }
if (!function_exists('rows24')) { function rows24($db, $table, $id){
    if (!$db) return [];
    try { $s = $db->prepare("SELECT * FROM $table WHERE wedding_id = ? ORDER BY id"); $s->execute([$id]); return $s->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $t) { return []; }
} }
/* PDO dự phòng */
if (!isset($pdo) || !($pdo instanceof PDO)) {
    foreach (['db', 'conn', 'dbh'] as $n) { if (isset($$n) && $$n instanceof PDO) { $pdo = $$n; break; } }
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $cfg = __DIR__ . '/../../config/database.php';
    if (is_file($cfg)) require_once $cfg;
}
$db = (isset($pdo) && $pdo instanceof PDO) ? $pdo : null;
$wid = (int)$wedding['id'];

/* ---- Sổ lưu bút: nhận lời chúc gửi thẳng về trang này (giống template23) ---- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'wish') {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    $wName = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
    $wMsg  = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 1000);
    if ($wName === '' || $wMsg === '') { echo json_encode(['ok' => false, 'error' => 'Vui lòng nhập tên và lời chúc.']); exit; }
    try {
        if (!$db) throw new Exception('Không kết nối được cơ sở dữ liệu.');
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM `wedding_wishes`') as $r) $cols[] = $r['Field'];
        $nc = $mc = null;
        foreach (['guest_name','name','sender_name','fullname','full_name'] as $c) if (in_array($c, $cols, true)) { $nc = $c; break; }
        foreach (['message','content','wish','wish_message','wishes'] as $c) if (in_array($c, $cols, true)) { $mc = $c; break; }
        if (!$nc || !$mc) throw new Exception('Bảng wedding_wishes thiếu cột tên/lời chúc.');
        $f = ['wedding_id', $nc, $mc]; $m = ['?', '?', '?'];
        if (in_array('created_at', $cols, true)) { $f[] = 'created_at'; $m[] = 'NOW()'; }
        $db->prepare('INSERT INTO wedding_wishes (`' . implode('`,`', $f) . '`) VALUES (' . implode(',', $m) . ')')
           ->execute([$wid, $wName, $wMsg]);
        echo json_encode(['ok' => true, 'name' => $wName, 'message' => $wMsg, 'time' => date('H:i:s d/m/Y')]);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => 'Không gửi được lời chúc: ' . $e->getMessage()]);
    }
    exit;
}

$groom = $wedding['groom_name']; $bride = $wedding['bride_name'];
$ts    = strtotime($wedding['wedding_date']);
$time  = $wedding['wedding_time'] ? substr($wedding['wedding_time'], 0, 5) : '';
$thu   = ['Chủ Nhật','Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy'][date('w', $ts)];
$place = trim(($wedding['venue'] ?? '') . ($wedding['address'] ? ', ' . $wedding['address'] : ''));

/* ---- Bố mẹ hai bên (giống cách template23 đọc wedding_parents) ---- */
$P = ['groom' => ['father' => '', 'mother' => ''], 'bride' => ['father' => '', 'mother' => '']];
foreach (rows24($db, 'wedding_parents', $wid) as $i => $r) {
    $sd = '';
    foreach (['side','type','owner','party','person_type'] as $k) if (isset($r[$k])) $sd .= ' ' . mb_strtolower((string)$r[$k], 'UTF-8');
    if (preg_match('/groom|trai|chu_re|chure|male/', $sd) && !preg_match('/female/', $sd)) $side = 'groom';
    elseif (preg_match('/bride|gai|co_dau|codau|female/', $sd)) $side = 'bride';
    else $side = $i === 0 ? 'groom' : 'bride';
    $f = pick($r, ['father_name','father','groom_father','bride_father']);
    $m = pick($r, ['mother_name','mother','groom_mother','bride_mother']);
    if ($f === '' && $m === '') {
        $n = pick($r, ['parent_name','name','full_name']);
        $role = mb_strtolower(pick($r, ['parent_role','role','relation','relationship']), 'UTF-8');
        if ($n !== '') { if (preg_match('/mother|mẹ|me|má|bà/u', $role)) $m = $n; else $f = $n; }
    }
    if ($f !== '') $P[$side]['father'] = $f;
    if ($m !== '') $P[$side]['mother'] = $m;
}
/* bảng có đủ 4 cột trong 1 dòng (groom_father, bride_mother...) */
$one = rows24($db, 'wedding_parents', $wid)[0] ?? [];
foreach (['groom' => 'groom', 'bride' => 'bride'] as $sd => $px) {
    if (!$P[$sd]['father']) $P[$sd]['father'] = pick($one, [$px . '_father', 'father_' . $px]);
    if (!$P[$sd]['mother']) $P[$sd]['mother'] = pick($one, [$px . '_mother', 'mother_' . $px]);
}
$strip = fn($v) => trim(preg_replace('/^(ông|bà|ong|ba)\b[\s.:]*/iu', '', trim((string)$v)));
$gFather = $strip($P['groom']['father']); $gMother = $strip($P['groom']['mother']);
$bFather = $strip($P['bride']['father']); $bMother = $strip($P['bride']['mother']);

$events = rows24($db, 'wedding_events', $wid);
usort($events, fn($a, $b) => ((int)($a['sort_order'] ?? 0)) <=> ((int)($b['sort_order'] ?? 0)) ?: strcmp(pick($a, ['event_time','time','start_time']), pick($b, ['event_time','time','start_time'])));

/* ---- Album (wedding_photos) ---- */
$photos = [];
$pcols = [];
if ($db) { try { foreach ($db->query('SHOW COLUMNS FROM `wedding_photos`') as $r) $pcols[] = $r['Field']; } catch (Throwable $t) {} }
$ord = in_array('sort_order', $pcols, true) ? 'sort_order ASC, id ASC' : 'id ASC';
if ($db) {
    try {
        $st = $db->prepare("SELECT * FROM wedding_photos WHERE wedding_id = ? ORDER BY $ord");
        $st->execute([$wid]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $u = img24(pick($r, ['image_path','image','photo','photo_path','file','filename','path','url','file_path']));
            if ($u !== '') $photos[] = $u;
        }
    } catch (Throwable $t) {}
}
$gifts  = rows24($db, 'wedding_gifts', $wid);
$wishes = array_reverse(rows24($db, 'wedding_wishes', $wid));

$bg    = img24($wedding['background_image'] ?? '');
$story = img24($wedding['story_image'] ?? '');
$gp    = img24($wedding['groom_photo'] ?? '');
$bp    = img24($wedding['bride_photo'] ?? '');
$music = !empty($wedding['music']) ? T24_BASE . 'uploads/music/' . basename($wedding['music']) : '';
$assets = T24_BASE . 'templates/template24/';

$icons = [
 '<svg viewBox="0 0 40 40"><circle cx="20" cy="8" r="4"/><path d="M12 36V18q0-4 8-4t8 4v18M16 36V24M24 36V24"/></svg>',
 '<svg viewBox="0 0 40 40"><circle cx="20" cy="8" r="4"/><path d="M12 36V18q0-4 8-4t8 4v18M16 36V24M24 36V24"/><path d="M31 6q3-4 6 0t-6 7q-9-3-6-7t6 0z"/></svg>',
 '<svg viewBox="0 0 40 40"><rect x="8" y="22" width="24" height="14" rx="2"/><circle cx="20" cy="15" r="5"/><path d="M17 9l3-4 3 4"/></svg>',
 '<svg viewBox="0 0 40 40"><path d="M6 30h28M10 30V20h20v10M14 20v-6h12v6M17 14V9h6v5"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Thiệp cưới <?= e($groom) ?> &amp; <?= e($bride) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Allura&family=Noto+Serif+SC:wght@700&family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Quicksand:wght@400;500;600&family=Cormorant+Garamond:wght@400;500;600;700&family=Montserrat:wght@400;500&family=Dancing+Script:wght@500;600&display=swap" rel="stylesheet">
 <link rel="stylesheet" href="/WeddingProject/templates/template25/style.css">
</head>
<body>

<canvas class="sparkles" id="sparkles" aria-hidden="true"></canvas>

<!-- 1. BÌA THIỆP -->
<section class="cover" id="cover">
  <div class="cover-in">
    <div class="cover-xi">囍</div>
    <p class="cover-top">THIỆP MỜI ĐÁM CƯỚI</p>
    <h1 class="cover-name"><?= e($groom) ?></h1>
    <span class="cover-amp">&amp;</span>
    <h1 class="cover-name"><?= e($bride) ?></h1>
    <p class="cover-date"><?= date('d · m · Y', $ts) ?></p>
    <button class="cover-open" id="openBtn" type="button">Chạm để mở thiệp</button>
  </div>
</section>

<?php if ($music): ?>
<audio id="weddingMusic" loop src="<?= e($music) ?>"></audio>
<button class="music-btn" id="musicBtn" type="button" aria-label="Bật/tắt nhạc">♪</button>
<?php endif; ?>

<main class="page" id="page">


<!-- 2. HERO -->
<section class="hero">
  <p class="hero-names"><?= e($groom) ?> &amp; <?= e($bride) ?></p>
  <div class="xi">囍</div>
  <div class="arch-wrap">
    <svg class="arch-text" viewBox="0 0 360 200" aria-hidden="true">
      <defs><path id="archPath" d="M 22 200 A 158 158 0 0 1 338 200"/></defs>
      <text><textPath href="#archPath" startOffset="50%" text-anchor="middle">WELCOME TO OUR WEDDING</textPath></text>
    </svg>
    <div class="arch" style="<?= $bg ? "background-image:url('" . e($bg) . "')" : '' ?>">
      <span class="arch-date"><?= date('d.m.Y', $ts) ?></span>
    </div>
  </div>
  <p class="script-line">Điều đẹp nhất luôn tìm đến đúng lúc.</p>
</section>

<!-- 3. ẢNH CÔ DÂU CHÚ RỂ + CHUYỆN TÌNH -->
<section class="duo">
  <div class="duo-photos">
    <figure><div class="frame" style="<?= $gp ? "background-image:url('" . e($gp) . "')" : '' ?>"></div><figcaption><?= e($groom) ?></figcaption></figure>
    <span class="duo-xi">囍</span>
    <figure><div class="frame" style="<?= $bp ? "background-image:url('" . e($bp) . "')" : '' ?>"></div><figcaption><?= e($bride) ?></figcaption></figure>
  </div>
  <?php if (!empty($wedding['story'])): ?>
  <p class="story"><?= nl2br(e($wedding['story'])) ?></p>
  <?php endif; ?>
</section>

<!-- 4. GIA ĐÌNH -->
<section class="family">
  <h2 class="save-date">Save The Date</h2>
  <div class="parents">
    <div><h3>Nhà Trai</h3><?php if ($gFather): ?><p>Ông: <?= e($gFather) ?></p><?php endif; ?><?php if ($gMother): ?><p>Bà: <?= e($gMother) ?></p><?php endif; ?></div>
    <div><h3>Nhà Gái</h3><?php if ($bFather): ?><p>Ông: <?= e($bFather) ?></p><?php endif; ?><?php if ($bMother): ?><p>Bà: <?= e($bMother) ?></p><?php endif; ?></div>
  </div>
  <p class="announce">Trân trọng thông báo</p>
  <p class="ceremony">LỄ THÀNH HÔN</p>
  <div class="names-script">
    <span class="n1"><?= e($groom) ?></span>
    <span class="and">And</span>
    <span class="n2"><?= e($bride) ?></span>
  </div>
  <?php if ($story): ?><img class="family-img" src="<?= e($story) ?>" alt="Ảnh cưới" loading="lazy"><?php endif; ?>
</section>

<!-- 5. THỜI GIAN – ĐỊA ĐIỂM – LỊCH – ĐẾM NGƯỢC -->
<section class="when">
  <div class="when-head">
    <span class="when-xi">囍</span>
    <div class="when-text">
      <h2>LỄ VU QUY</h2>
      <p><?= $time ? e($time) . ' ' : '' ?><?= e($thu) ?></p>
      <p><?= date('d.m.Y', $ts) ?></p>
      <p class="when-place"><?= e($place) ?></p>
    </div>
  </div>
  <div class="cal">
    <div class="cal-bar"><?= date('n.Y', $ts) ?></div>
    <div class="cal-grid">
      <?php
      foreach (['T2','T3','T4','T5','T6','T7','CN'] as $i => $d) echo '<b class="' . ($i > 4 ? 'we' : '') . '">' . $d . '</b>';
      $first = strtotime(date('Y-m-01', $ts));
      for ($i = 1; $i < (int)date('N', $first); $i++) echo '<i></i>';
      $dim = (int)date('t', $ts); $day = (int)date('j', $ts);
      for ($d = 1; $d <= $dim; $d++) {
          $dow = (int)date('N', strtotime(date('Y-m-', $ts) . sprintf('%02d', $d)));
          echo '<span class="' . ($d === $day ? 'hit' : '') . ($dow >= 6 ? ' we' : '') . '">' . $d . '</span>';
      }
      ?>
    </div>
    <div class="cal-year"><?= date('Y', $ts) ?></div>
  </div>
  <div class="count" id="count" data-target="<?= date('Y-m-d', $ts) ?>T<?= $time ?: '00:00' ?>:00">
    <div><b id="cdD">0</b><small>ngày</small></div>
    <div><b id="cdH">0</b><small>giờ</small></div>
    <div><b id="cdM">0</b><small>phút</small></div>
    <div><b id="cdS">0</b><small>giây</small></div>
  </div>
  <?php if ($place): ?>
  <iframe class="map" loading="lazy" src="https://www.google.com/maps?q=<?= urlencode($wedding['address'] ?: $wedding['venue']) ?>&output=embed" title="Bản đồ"></iframe>
  <?php endif; ?>
</section>

<!-- 6. TIMELINE -->
<?php if ($events): ?>
<section class="timeline">
  <p class="tl-sub">WEDDING TIMELINE</p>
  <h2>Lịch trình ngày cưới</h2>
  <div class="tl-scroll"><div class="tl-track">
    <?php foreach ($events as $i => $ev): ?>
    <div class="tl-item">
      <time><?= e(substr(pick($ev, ['event_time','time','start_time']), 0, 5)) ?></time>
      <span class="tl-dot"></span>
      <div class="tl-icon"><?= $icons[$i % 4] ?></div>
      <span class="tl-title"><?= e(pick($ev, ['title','name','event_name'])) ?></span>
    </div>
    <?php endforeach; ?>
  </div></div>
</section>
<?php endif; ?>

<!-- 7. ALBUM -->
<?php if ($photos): $n = count($photos); $more = $n - 5; ?>
<section class="album">
  <p class="alb-top">INVITATION</p>
  <span class="alb-side l">I love you forever</span>
  <span class="alb-side r">Nice to meet you</span>
  <div class="alb-board">
    <div class="alb-grid">
      <?php foreach (array_slice($photos, 0, 5) as $i => $src): ?>
      <button type="button" class="ph p<?= $i + 1 ?>" data-i="<?= $i ?>" style="background-image:url('<?= e($src) ?>')">
        <?php if ($i === 4 && $more > 0): ?><span class="more">+<?= $more ?></span><?php endif; ?>
      </button>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<div class="lightbox" id="lb" hidden>
  <button class="lb-x" id="lbX" type="button" aria-label="Đóng">×</button>
  <button class="lb-n prev" id="lbP" type="button" aria-label="Ảnh trước">‹</button>
  <img id="lbImg" alt="">
  <button class="lb-n next" id="lbN" type="button" aria-label="Ảnh sau">›</button>
</div>
<?php endif; ?>

<!-- 8. SỔ LƯU BÚT -->
<section class="guest">
  <div class="gb-card">
    <h2>SỔ LƯU BÚT</h2>
    <input id="wishName" type="text" placeholder="Nhập tên*" maxlength="60">
    <textarea id="wishMessage" rows="3" placeholder="Nhập lời chúc*" maxlength="500"></textarea>
    <button id="sendWish" type="button">GỬI LỜI CHÚC</button>
    <p class="wish-status" id="wishStatus" role="status" aria-live="polite"></p>
  </div>
  <div class="wish-list" id="wishList">
    <?php foreach ($wishes as $w): ?>
    <article class="wish">
      <header><strong><?= e(pick($w, ['guest_name','name','sender_name','fullname','full_name'])) ?></strong>
        <time><?= e(date('H:i:s d/m/Y', strtotime(pick($w, ['created_at'], 'now')))) ?></time></header>
      <p><?= nl2br(e(pick($w, ['message','content','wish','wish_message','wishes']))) ?></p>
    </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- 9. HỘP QUÀ + FOOTER -->
<section class="gift">
  <p class="g-sub">A LITTLE GIFT</p>
  <h2>Hộp quà mừng</h2>
  <p class="g-note">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>
  <button class="g-box" id="giftOpen" type="button">
    <svg viewBox="0 0 64 64"><rect x="10" y="26" width="44" height="28"/><rect x="6" y="20" width="52" height="8"/><path d="M32 20v34M32 20c-8-12-18-6-12 0M32 20c8-12 18-6 12 0"/></svg>
    <span>Gửi quà mừng cưới</span><small>Nhấn để xem thông tin</small>
  </button>
  <p class="g-thanks">Thank you for being with us</p>
</section>

<footer class="foot">
  <?php if ($story): ?><div class="foot-img" style="background-image:url('<?= e($story) ?>')"></div><?php endif; ?>
  <div class="foot-text">
    <p>Cảm ơn bạn đã ghé thăm tấm thiệp nhỏ này.</p>
    <p>Sự hiện diện và lời chúc của bạn<br>là món quà ý nghĩa nhất với chúng mình.</p>
    <p class="wl">With love,<br><?= e($groom) ?> &amp; <?= e($bride) ?></p>
  </div>
</footer>
</main>

<!-- MODAL QUÀ -->
<div class="modal" id="giftModal" hidden>
  <div class="modal-box">
    <button class="modal-x" id="giftClose" type="button" aria-label="Đóng">×</button>
    <h3>HỘP QUÀ MỪNG</h3>
    <?php
    $gm = $gifts[0]['gift_mode'] ?? 'bank';
    if ($gm === 'message'): ?>
      <p class="gift-msg"><?= nl2br(e($gifts[0]['gift_message'] ?? '')) ?></p>
    <?php else: ?>
    <div class="qr-row">
      <?php foreach (array_slice($gifts, 0, 2) as $i => $g):
        $qr = img24(pick($g, ['qr_image','qr','qr_code']), 'gifts');
        $who = pick($g, ['account_name','receiver_name','name']); ?>
      <div class="qr">
        <p><?= $i === 0 ? 'Chú Rể' : 'Cô Dâu' ?> - <?= e($who) ?></p>
        <?php if ($qr): ?><img src="<?= e($qr) ?>" alt="QR"><a class="qr-save" href="<?= e($qr) ?>" download>Lưu QR</a><?php endif; ?>
        <small><?= e(pick($g, ['bank_name','bank'])) ?> <?= e(pick($g, ['account_number','bank_account','account_no'])) ?></small>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
  window.T24 = { photos: <?= json_encode($photos) ?> };
</script>
<script src="/WeddingProject/templates/template25/script.js"></script>
</body>
</html>