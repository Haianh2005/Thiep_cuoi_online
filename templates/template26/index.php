<?php
/* ===== TEMPLATE04 – Green Garden =====
   Được include từ w/index.php → dùng biến $wedding. Tự lấy dữ liệu phụ từ MySQL. */
$BASE = '/WeddingProject';
$TPL  = $BASE . '/templates/template04';

// ---- PDO ----
$db = $pdo ?? ($db ?? ($conn ?? null));
if (!($db instanceof PDO)) {
    $cfg = dirname(__DIR__, 2) . '/config/database.php';
    if (is_file($cfg)) { require_once $cfg; $db = $pdo ?? ($db ?? ($conn ?? null)); }
}
$wid = (int)($wedding['id'] ?? 0);

function q4(?PDO $db, string $sql, array $p = []): array {
    if (!$db) return [];
    try { $s = $db->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { return []; }
}
function e4($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function pick4(array $a, array $keys, $def = '') {
    foreach ($keys as $k) if (isset($a[$k]) && trim((string)$a[$k]) !== '') return $a[$k];
    return $def;
}
function img4($v, $folder = 'weddings') {
    global $BASE;
    $v = trim((string)$v);
    if ($v === '') return '';
    if (preg_match('~^(https?:)?//~', $v) || $v[0] === '/') return $v;
    if (strpos($v, 'uploads/') === 0) return $BASE . '/' . $v;
    return $BASE . '/uploads/' . $folder . '/' . $v;
}

$groom = $wedding['groom_name'] ?? '';
$bride = $wedding['bride_name'] ?? '';
$ts    = !empty($wedding['wedding_date']) ? strtotime($wedding['wedding_date']) : null;
$time  = !empty($wedding['wedding_time']) ? substr($wedding['wedding_time'], 0, 5) : '';
$dateDot = $ts ? date('d.m.Y', $ts) : '';
$wdays = ['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
$weekday = $ts ? $wdays[(int)date('w', $ts)] : '';
$bg    = img4($wedding['background_image'] ?? '');
$story = img4($wedding['story_image'] ?? '');
$gPhoto = img4(pick4($wedding, ['groom_photo']), 'weddings') ?: $bg;
$bPhoto = img4(pick4($wedding, ['bride_photo']), 'weddings') ?: $bg;
$music  = !empty($wedding['music']) ? img4($wedding['music'], 'music') : '';
$venue  = $wedding['venue'] ?? '';
$addr   = $wedding['address'] ?? '';
$mapQ   = urlencode($addr !== '' ? $addr : $venue);

// ---- Dữ liệu phụ ----
$parents = (q4($db, "SELECT * FROM wedding_parents WHERE wedding_id=? LIMIT 1", [$wid])[0] ?? []);
$events  = q4($db, "SELECT * FROM wedding_events WHERE wedding_id=?", [$wid]);
$photos  = q4($db, "SELECT * FROM wedding_photos WHERE wedding_id=?", [$wid]);
$wishes  = q4($db, "SELECT * FROM wedding_wishes WHERE wedding_id=? ORDER BY id DESC LIMIT 200", [$wid]);
$giftRows = q4($db, "SELECT * FROM wedding_gifts WHERE wedding_id=?", [$wid]);
$gift     = $giftRows[0] ?? [];

usort($events, fn($a, $b) => strcmp((string)pick4($a, ['event_time','time']), (string)pick4($b, ['event_time','time'])));

// Lấy đường dẫn ảnh từ một dòng của wedding_photos
function photo4(array $p): string {
    $f = pick4($p, ['image','photo','filename','file','path','url','image_url','image_path','photo_url','img','src']);
    if ($f !== '') return (string)$f;
    // Nếu không khớp tên cột nào: lấy giá trị đầu tiên trông giống file ảnh
    foreach ($p as $k => $v) {
        if (in_array($k, ['id','wedding_id','sort_order','created_at','updated_at'], true)) continue;
        $v = trim((string)$v);
        if ($v !== '' && preg_match('~\.(jpe?g|png|gif|webp|avif)(\?.*)?$~i', $v)) return $v;
    }
    return '';
}

$albumImgs = [];
foreach ($photos as $p) {
    $f = photo4($p);
    if ($f !== '') $albumImgs[] = img4($f);
}
// Không có ảnh album thì để trống, KHÔNG dùng ảnh nền

// ---- Quà mừng: nhận diện chú rể / cô dâu theo tên cột hoặc theo giá trị cột loại ----
function role4(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    if (preg_match('~bride|co_?dau|c[oô]\s*d[aâ]u|wife~u', $s)) return 'bride';
    if (preg_match('~groom|chu_?re|ch[uú]\s*r[eể]|husband~u', $s)) return 'groom';
    return '';
}
function qr4(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    $root = dirname(__DIR__, 2);
    $name = basename($v);
    // nếu file không nằm trong uploads/gifts mà nằm ở uploads/weddings thì dùng thư mục đó
    if (!preg_match('~^(https?:)?//~', $v) && $v[0] !== '/' && strpos($v, 'uploads/') !== 0) {
        if (!is_file($root . '/uploads/gifts/' . $v) && is_file($root . '/uploads/weddings/' . $v)) return img4($v, 'weddings');
    }
    return img4($v, 'gifts');
}

$gMode = 'bank'; $gMsg = '';
$qr = ['groom' => '', 'bride' => ''];
$generic = [];
$bankBy = ['groom' => [], 'bride' => [], '' => []];
$roleCols = ['type','role','owner','side','person','for','target','receiver','who'];
foreach ($giftRows as $row) {
    if ($gMode === 'bank' && !empty($row['gift_mode'])) $gMode = $row['gift_mode'];
    if ($gMsg === '' && !empty($row['gift_message'])) $gMsg = $row['gift_message'];
    $rowRole = '';
    foreach ($roleCols as $rc) if (!empty($row[$rc]) && ($r = role4((string)$row[$rc]))) { $rowRole = $r; break; }
    $bk = [
        'Người nhận'   => pick4($row, ['account_name','receiver_name','holder']),
        'Ngân hàng'    => pick4($row, ['bank_name','bank']),
        'Số tài khoản' => pick4($row, ['account_number','bank_account','account_no']),
    ];
    foreach ($row as $k => $v) {
        $v = trim((string)$v);
        if ($v === '' || !preg_match('~qr|image|img|photo~i', (string)$k)) continue;
        if (!preg_match('~qr~i', (string)$k) && !$rowRole) continue;
        $r = role4((string)$k) ?: $rowRole;
        if ($r && !$qr[$r]) $qr[$r] = $v;
        elseif (!$r) $generic[] = $v;
    }
    if (array_filter($bk)) $bankBy[$rowRole][] = $bk;
}
// QR chung chưa rõ của ai: điền lần lượt vào ô còn trống (chú rể trước, cô dâu sau)
foreach ($generic as $v) {
    if (!$qr['groom']) $qr['groom'] = $v;
    elseif (!$qr['bride'] && $v !== $qr['groom']) $qr['bride'] = $v;
}
$gQrG = qr4($qr['groom']);
$gQrB = qr4($qr['bride']);
// danh sách thông tin ngân hàng để hiển thị (có nhãn nếu có nhiều người nhận)
$bankBlocks = [];
foreach (['groom' => 'Chú rể', 'bride' => 'Cô dâu', '' => ''] as $rk => $lab) {
    foreach ($bankBy[$rk] as $bk) $bankBlocks[] = ['label' => $lab, 'rows' => $bk];
}
if (count($bankBlocks) < 2) foreach ($bankBlocks as &$bb) $bb['label'] = '';
unset($bb);

// ---- Lịch tháng ----
$calHtml = '';
if ($ts) {
    $y = (int)date('Y', $ts); $m = (int)date('n', $ts); $d = (int)date('j', $ts);
    $first = (int)date('N', mktime(0,0,0,$m,1,$y)); // 1=T2
    $days  = (int)date('t', mktime(0,0,0,$m,1,$y));
    $cells = array_fill(0, $first - 1, '');
    for ($i = 1; $i <= $days; $i++) $cells[] = $i;
    while (count($cells) % 7) $cells[] = '';
    foreach ($cells as $i => $c) {
        if ($i % 7 === 0) $calHtml .= '<tr>';
        $calHtml .= $c === $d ? '<td class="on"><span>' . $c . '</span></td>' : '<td>' . $c . '</td>';
        if ($i % 7 === 6) $calHtml .= '</tr>';
    }
}
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Thiệp cưới <?= e4($groom) ?> &amp; <?= e4($bride) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Dancing+Script:wght@500;700&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
<?php
$cssFile = __DIR__ . '/style.css'; $jsFile = __DIR__ . '/script.js';
if (is_file($cssFile)) { echo '<style>' . file_get_contents($cssFile) . '</style>'; }
else { echo '<link rel="stylesheet" href="' . $TPL . '/style.css">'; }
?>
</head>
<body>
<!-- template04 album-gallery v3 -->
<?php if (isset($_GET['preview'])): ?><!-- gift cols: <?= e4(implode(', ', array_keys($gift))) ?> | rows: <?= count($giftRows) ?> | qrG: <?= e4($gQrG) ?> | qrB: <?= e4($gQrB) ?> --><?php endif; ?>
<div class="card">

<!-- 1. MỞ ĐẦU -->
<section class="hero" style="background-image:url('<?= e4($bg) ?>')">
  <div class="leaf leaf-tr"></div>
  <div class="hero-text">
    <h1><?= e4($groom) ?> &amp; <?= e4($bride) ?></h1>
    <p class="num"><?= e4($dateDot) ?></p>
  </div>
</section>

<!-- 2. CHUYỆN TÌNH CHÚNG MÌNH -->
<section class="story paper reveal">
  <p class="story-cap">We've been writing<br>our love story for years...<br>and the next chapter begins in</p>
  <?php if (!empty($wedding['story'])): ?>
    <p class="story-text"><?= nl2br(e4($wedding['story'])) ?></p>
  <?php endif; ?>
  <?php if ($story): ?><div class="story-photo"><img src="<?= e4($story) ?>" alt=""></div><?php endif; ?>
</section>

<!-- 3. CHÚ RỂ – CÔ DÂU + THÔNG TIN -->
<section class="couple paper reveal" style="--bgimg:url('<?= e4($bg) ?>')">
  <h2 class="script-title">Two hearts <b>ONE SOUL</b></h2>
  <div class="polaroids">
    <figure class="pol pol-l"><img src="<?= e4($gPhoto) ?>" alt=""><figcaption><span>Chú rể</span><b><?= e4($groom) ?></b></figcaption></figure>
    <figure class="pol pol-r"><img src="<?= e4($bPhoto) ?>" alt=""><figcaption><span>Cô dâu</span><b><?= e4($bride) ?></b></figcaption></figure>
  </div>

  <div class="families">
    <div>
      <h4>NHÀ GÁI</h4>
      <p>Ông: <?= e4(pick4($parents, ['bride_father','father_bride','bride_father_name'])) ?></p>
      <p>Bà: <?= e4(pick4($parents, ['bride_mother','mother_bride','bride_mother_name'])) ?></p>
    </div>
    <div>
      <h4>NHÀ TRAI</h4>
      <p>Ông: <?= e4(pick4($parents, ['groom_father','father_groom','groom_father_name'])) ?></p>
      <p>Bà: <?= e4(pick4($parents, ['groom_mother','mother_groom','groom_mother_name'])) ?></p>
    </div>
  </div>

  <p class="invite">KÍNH MỜI THAM DỰ LỄ CƯỚI CỦA CON<br>VUI CÙNG GIA ĐÌNH CHÚNG TÔI</p>
  <p class="names"><?= e4($bride) ?><br><i>&amp;</i><br><?= e4($groom) ?></p>
  <p class="time-line"><span class="num"><?= e4($time) ?></span>, <?= e4(mb_strtoupper($weekday)) ?></p>
  <?php if ($ts): ?>
  <div class="bigdate">
    <span class="num">THÁNG <?= date('m', $ts) ?></span>
    <strong class="num"><?= date('d', $ts) ?></strong>
    <span class="num">NĂM <?= date('Y', $ts) ?></span>
  </div>
  <?php endif; ?>
  <p class="place-label">Tổ chức tại</p>
  <p class="place"><b><?= e4(mb_strtoupper($venue)) ?></b><br><?= e4($addr) ?></p>
  <?php if ($mapQ): ?><a class="btn-green" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&query=<?= $mapQ ?>">CHỈ ĐƯỜNG</a><?php endif; ?>
</section>

<!-- 4. LỊCH + TIMELINE -->
<section class="calendar paper reveal">
  <div class="cal-sheet">
    <h3 class="script">Tháng <?= $ts ? (int)date('n', $ts) : '' ?></h3>
    <table class="cal num">
      <thead><tr><th>MON</th><th>TUE</th><th>WED</th><th>THU</th><th>FRI</th><th>SAT</th><th>SUN</th></tr></thead>
      <tbody><?= $calHtml ?></tbody>
    </table>
  </div>
</section>

<section class="timeline reveal" style="background-image:linear-gradient(rgba(40,55,40,.55),rgba(40,55,40,.65)),url('<?= e4($story ?: $bg) ?>')">
  <h3>TOGETHER IS A <i>Beautiful plan</i></h3>
  <?php
    $tl = [];
    foreach ($events as $ev) {
        $tl[] = [pick4($ev, ['event_time','time']), pick4($ev, ['title','event_name','name']), pick4($ev, ['description','note','place'])];
    }
    if (!$tl && $time) $tl[] = [$time, 'Lễ cưới', $venue];
  ?>
  <div class="tl-row">
    <?php foreach ($tl as $t): ?>
      <div class="tl-item"><span class="dot"></span>
        <b class="num"><?= e4(substr((string)$t[0], 0, 5)) ?></b>
        <em><?= e4($t[1]) ?></em><small><?= e4($t[2]) ?></small></div>
    <?php endforeach; ?>
  </div>
</section>

<!-- 5. ĐẾM NGƯỢC + ALBUM -->
<section class="count paper reveal" data-date="<?= $ts ? e4(date('Y-m-d', $ts) . 'T' . ($time ?: '00:00') . ':00') : '' ?>">
  <h2 class="script-title">Countdown</h2>
  <div class="cd">
    <div><b class="num" id="cdD">00</b><span>Ngày</span></div>
    <div><b class="num" id="cdH">00</b><span>Giờ</span></div>
    <div><b class="num" id="cdM">00</b><span>Phút</span></div>
    <div><b class="num" id="cdS">00</b><span>Giây</span></div>
  </div>
</section>

<!-- 5b. ALBUM / GALLERY -->
<?php if ($albumImgs): ?>
<section class="gx paper reveal">
  <p class="gx-label">GALLERY</p>
  <h2 class="gx-title">Khoảnh khắc của chúng mình</h2>
  <div class="gx-album" id="album" data-imgs='<?= e4(json_encode($albumImgs)) ?>'>
    <div class="gx-main"><img id="alMain" src="<?= e4($albumImgs[0]) ?>" alt=""></div>
    <div class="gx-thumbs">
      <?php foreach ($albumImgs as $i => $src): ?>
        <img src="<?= e4($src) ?>" data-i="<?= $i ?>" class="gx-th<?= $i === 0 ? ' on' : '' ?>" alt="">
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- 6. SỔ LƯU BÚT -->
<section class="guestbook paper reveal">
  <h2 class="gb-title">SỔ LƯU BÚT</h2>
  <div class="wish-form">
    <input type="text" id="wishName" placeholder="Nhập tên của bạn..." maxlength="60">
    <textarea id="wishMessage" rows="4" placeholder="Nhập lời chúc của bạn..." maxlength="500"></textarea>
    <button type="button" id="sendWish" data-wid="<?= $wid ?>" data-api="<?= $BASE ?>/api/wishes.php">GỬI LỜI CHÚC</button>
  </div>
  <div class="wish-list" id="wishList">
    <?php foreach ($wishes as $w): ?>
      <div class="wish">
        <div class="wish-head"><b><?= e4(pick4($w, ['name','guest_name'])) ?></b>
          <span class="num"><?= e4(pick4($w, ['created_at'])) ? date('H:i:s d/m/Y', strtotime(pick4($w, ['created_at']))) : '' ?></span></div>
        <p><?= nl2br(e4(pick4($w, ['message','content','wish']))) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- 7. HỘP QUÀ + FOOTER -->
<section class="giftbox paper reveal">
  <h2 class="script-title">Hộp quà mừng</h2>
  <p class="sub">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>
  <button type="button" class="gift-btn" id="openGift">
    <svg viewBox="0 0 64 64" width="64" height="64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linejoin="round"><path d="M10 26h44v10H10zM14 36h36v20H14zM32 26v30M32 26c-8-12-18-6-12-1M32 26c8-12 18-6 12-1"/></svg>
    <span class="gl">Gửi quà mừng cưới</span><small>Nhấn để xem thông tin</small>
  </button>
  <p class="thanks">Thank you for being with us</p>
</section>

<footer class="foot"<?= $story ? " style=\"background-image:linear-gradient(rgba(38,56,38,.78),rgba(38,56,38,.9)),url('" . e4($story) . "')\"" : '' ?>>
  <p>Cảm ơn bạn đã ghé thăm tấm thiệp nhỏ này.</p>
  <p>Sự hiện diện và lời chúc của bạn<br>là món quà ý nghĩa nhất với chúng mình.</p>
  <p>With love,<br><?= e4($groom) ?> &amp; <?= e4($bride) ?></p>
</footer>

</div>

<!-- Modal quà -->
<div class="modal" id="giftModal" hidden>
  <div class="modal-box">
    <div class="modal-head"><h3>HỘP QUÀ MỪNG</h3><button type="button" id="closeGift" aria-label="Đóng">×</button></div>
    <?php if ($gMode === 'message'): ?>
      <p class="gift-msg"><?= nl2br(e4($gMsg ?: 'Sự hiện diện của bạn là món quà lớn nhất.')) ?></p>
    <?php else: ?>
      <div class="qr-grid">
        <div><p>Chú Rể - <?= e4($groom) ?></p><div class="qr"><?= $gQrG ? '<img src="' . e4($gQrG) . '" alt="QR chú rể">' : 'Chưa có mã QR chú rể' ?></div></div>
        <div><p>Cô Dâu - <?= e4($bride) ?></p><div class="qr"><?= $gQrB ? '<img src="' . e4($gQrB) . '" alt="QR cô dâu">' : 'Chưa có mã QR cô dâu' ?></div></div>
      </div>
      <div class="bank">
        <?php foreach ($bankBlocks as $bb): ?>
          <div class="bank-blk">
            <?php if ($bb['label']): ?><h5><?= e4($bb['label']) ?></h5><?php endif; ?>
            <?php foreach ($bb['rows'] as $k => $v) if ($v): ?><p><span><?= e4($k) ?>:</span> <b class="num"><?= e4($v) ?></b></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Nhạc -->
<?php if ($music): ?><audio id="weddingMusic" loop preload="auto" src="<?= e4($music) ?>"></audio><?php endif; ?>
<button type="button" id="musicBtn" class="music-btn" aria-label="Bật/tắt nhạc"<?= $music ? '' : ' hidden' ?>>♪</button>

<?php
if (is_file($jsFile)) { echo '<script>' . str_replace('</script', '<\\/script', file_get_contents($jsFile)) . '</script>'; }
else { echo '<script src="' . $TPL . '/script.js"></script>'; }
?>
</body>
</html>