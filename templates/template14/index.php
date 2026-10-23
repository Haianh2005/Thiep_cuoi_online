<?php
/*
|--------------------------------------------------------------------------
| TEMPLATE 09 – THỦY MẶC HỒNG VÀNG
|--------------------------------------------------------------------------
| w/index.php đã tìm wedding theo slug và truyền vào $wedding (+ $pdo).
| Template chỉ ĐỌC dữ liệu và hiển thị. Không lưu gì (riêng lời chúc gọi api/wishes.php).
| Cột tuỳ chọn (groom_photo, bride_photo, lunar_date): có thì dùng, chưa có thì tự ẩn.
| Asset tuỳ chọn trong assets/images/ (có file mới hiện, không có thì bỏ qua):
|   bg-watercolor.png  nền lớp dưới (đã có)      flower-hero.png  hoa dưới ảnh đầu
|   flower-side.png    hoa mép phải              bridge.png       tranh cây cầu
|   frame.png          khung viền (thay khung SVG vẽ sẵn)
|--------------------------------------------------------------------------
*/

if (!isset($wedding) || !$wedding) {
    die("Không tìm thấy thông tin đám cưới.");
}

/* ---------- helper ---------- */
if (!function_exists('t9e')) {
    function t9e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}
/* Đường dẫn tuyệt đối/http giữ nguyên, còn lại thêm /WeddingProject/ (không lặp tiền tố). */
if (!function_exists('t9url')) {
    function t9url($p) {
        $p = trim((string)($p ?? ''));
        if ($p === '') return '';
        if (preg_match('#^(https?:)?//#i', $p) || $p[0] === '/') return $p;
        return '/WeddingProject/' . ltrim($p, '/');
    }
}
/* "Đỗ Thanh Tùng" -> "Thanh Tùng" (như ảnh mẫu); tên 1-2 chữ giữ nguyên */
if (!function_exists('t9name')) {
    function t9name($n) {
        $p = preg_split('/\s+/u', trim((string)$n));
        if (count($p) >= 3) array_shift($p);
        return implode(' ', $p);
    }
}
if (!function_exists('t9parent')) {
    function t9parent($name, $prefix) {
        $name = trim((string)$name);
        if ($name === '') return '';
        if (preg_match('/^(ông|bà)\b/iu', $name)) return $name;
        return $prefix . ' ' . $name;
    }
}
if (!function_exists('t9time')) {
    function t9time($v) {
        if (empty($v) || strtotime($v) === false) return '';
        return date('H:i', strtotime($v));
    }
}

/* Thư mục template tự nhận theo tên folder -> đổi tên folder vẫn chạy */
$tplFolder = basename(__DIR__);
$base = '/WeddingProject/templates/' . $tplFolder . '/';

/* Asset tuỳ chọn: chỉ hiện khi file tồn tại */
$deco = [];
foreach (['flower-hero', 'flower-side', 'bridge'] as $k) {
    $f = __DIR__ . '/assets/images/' . $k . '.png';
    $deco[$k] = is_file($f) ? $base . 'assets/images/' . $k . '.png' : '';
}
$frameCss = is_file(__DIR__ . '/assets/images/frame.png')
    ? "--t9-frame:url('" . $base . "assets/images/frame.png');" : '';

/* ---------- thông tin cơ bản ---------- */
$groomName = $wedding['groom_name'] ?? '';
$brideName = $wedding['bride_name'] ?? '';
$venue     = $wedding['venue'] ?? '';
$address   = $wedding['address'] ?? '';
$lunarRaw  = trim((string)($wedding['lunar_date'] ?? ''));
$music     = t9url($wedding['music'] ?? '');
$storyImage = t9url($wedding['story_image'] ?? '');       /* ảnh đầu trang 2 + nền lời cảm ơn */
$bgImage    = t9url($wedding['background_image'] ?? '');  /* ảnh màn mở thiệp */
$shortGroom = t9name($groomName);
$shortBride = t9name($brideName);

$lunarText = '';
if ($lunarRaw !== '') {
    $lunarText = preg_match('/^(nhằm|tức)/iu', $lunarRaw) ? $lunarRaw : 'Tức ngày ' . $lunarRaw;
    $lunarText = '(' . mb_strtoupper($lunarText, 'UTF-8') . ')';
}

/* ---------- ngày giờ ---------- */
$weekdayNames = ['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
$dateObject = null;
if (!empty($wedding['wedding_date'])) {
    try { $dateObject = new DateTime($wedding['wedding_date']); } catch (Exception $e) { $dateObject = null; }
}
$timeHi = t9time($wedding['wedding_time'] ?? '');
$timeSql = $timeHi !== '' ? $timeHi . ':00' : '00:00:00';
$day = $month = $year = $weekday = $targetIso = $icsDate = '';
$calCells = [];
$weddingDay = 0;
if ($dateObject) {
    $day = $dateObject->format('d');
    $month = $dateObject->format('m');
    $year = $dateObject->format('Y');
    $weekday = mb_strtoupper($weekdayNames[(int)$dateObject->format('w')], 'UTF-8');
    $targetIso = $dateObject->format('Y-m-d') . 'T' . $timeSql;
    $weddingDay = (int)$dateObject->format('j');
    $first = new DateTime($dateObject->format('Y-m-01'));
    $offset = ((int)$first->format('w') + 6) % 7;      /* tuần bắt đầu từ Thứ 2 */
    $dim = (int)$first->format('t');
    for ($i = 0; $i < $offset; $i++) $calCells[] = null;
    for ($d = 1; $d <= $dim; $d++) $calCells[] = $d;
}

/* ---------- album ---------- */
$photoStmt = $pdo->prepare("SELECT image_path FROM wedding_photos WHERE wedding_id = :wedding_id ORDER BY sort_order ASC, id ASC");
$photoStmt->execute(['wedding_id' => $wedding['id']]);
$album = [];
foreach ($photoStmt->fetchAll() as $row) {
    $u = t9url($row['image_path'] ?? '');
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

/* "ĐÓN KHÁCH" / "KHAI TIỆC": lấy từ sự kiện có tên tương ứng, không có thì ẩn */
$findEvent = function ($regex) use ($events) {
    foreach ($events as $ev) {
        if (preg_match($regex, mb_strtolower((string)($ev['event_name'] ?? ''), 'UTF-8'))) return $ev;
    }
    return null;
};
$evGreet = $findEvent('/đón/u');
$evFeast = $findEvent('/khai tiệc|tiệc/u');
if ($evGreet && $evFeast && $evGreet === $evFeast) $evFeast = null;

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

$heartSvg = '<svg viewBox="0 0 32 30" aria-hidden="true"><path d="M16 27C4 18 1 11 5 6c3-4 9-3 11 2 2-5 8-6 11-2 4 5 1 12-11 21z" fill="currentColor"/></svg>';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t9e($groomName) ?> &amp; <?= t9e($brideName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,500;1,600&family=Playfair+Display:wght@400;500;600;700&family=Great+Vibes&family=Montserrat:wght@400;500;600&display=swap&subset=vietnamese" rel="stylesheet">
    <link rel="stylesheet" href="/WeddingProject/templates/template14/style.css">
    <noscript><style>.template09-page .t9-reveal{opacity:1!important;filter:none!important;transform:none!important}</style></noscript>
</head>
<body class="t9-body t9-locked">

<div class="template09-page"
     style="<?= t9e($frameCss) ?>"
     data-wedding-id="<?= (int)$wedding['id'] ?>"
     data-wish-api="/WeddingProject/api/wishes.php"
     data-target="<?= t9e($targetIso) ?>"
     data-short-groom="<?= t9e($shortGroom) ?>"
     data-short-bride="<?= t9e($shortBride) ?>"
     data-venue="<?= t9e($venue) ?>"
     data-address="<?= t9e($address) ?>">

    <!-- nền lớp dưới (ảnh watercolor) -->
    <div class="t9-bg" aria-hidden="true"></div>

    <!-- ============ 1. MỞ THIỆP ============ -->
    <section id="t9Opening" class="t9-opening" role="button" tabindex="0" aria-label="Mở thiệp"
        <?= $bgImage !== '' ? 'style="background-image:url(\'' . t9e($bgImage) . '\')"' : '' ?>>
        <div class="t9-opening-shade"></div>
        <h1 class="t9-opening-title"><span>Thiệp</span><span>mời</span></h1>
        <div class="t9-opening-hint">Chạm để mở thiệp</div>
        <div class="t9-opening-name t9-left"><?= t9e($groomName) ?></div>
        <div class="t9-opening-name t9-right"><?= t9e($brideName) ?></div>
    </section>

    <main id="t9Main" class="t9-main">

        <!-- ============ 2. HERO (ảnh = story_image) ============ -->
        <section class="t9-hero">
            <p class="t9-welcome t9-reveal"><i></i><span>WELCOME TO OUR WEDDING</span><i></i></p>
            <div class="t9-hero-names t9-reveal" style="--d:.15s">
                <span class="t9-hn"><?= t9e($shortGroom) ?></span>
                <span class="t9-hn-amp">&amp;</span>
                <span class="t9-hn"><?= t9e($shortBride) ?></span>
            </div>
            <?php if ($storyImage !== ''): ?>
                <div class="t9-hero-pic t9-reveal" style="--d:.25s">
                    <img src="<?= t9e($storyImage) ?>" alt="Ảnh cưới">
                    <?php if ($deco['flower-hero'] !== ''): ?><img class="t9-deco t9-deco-hero" src="<?= t9e($deco['flower-hero']) ?>" alt=""><?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- ============ 3. THÔNG TIN LỄ CƯỚI ============ -->
        <section class="t9-sec t9-info">
            <?php if ($deco['flower-side'] !== ''): ?><img class="t9-deco t9-deco-side" src="<?= t9e($deco['flower-side']) ?>" alt=""><?php endif; ?>
            <h2 class="t9-h t9-reveal">THÔNG TIN LỄ CƯỚI</h2>

            <div class="t9-families t9-reveal" style="--d:.1s">
                <?php foreach (['groom', 'bride'] as $sd): ?>
                    <div class="t9-family">
                        <span class="t9-family-label">Ông Bà</span>
                        <?php if ($parents[$sd]['father'] !== ''): ?><b><?= t9e(t9parent($parents[$sd]['father'], 'Ông')) ?></b><?php endif; ?>
                        <?php if ($parents[$sd]['mother'] !== ''): ?><b><?= t9e(t9parent($parents[$sd]['mother'], 'Bà')) ?></b><?php endif; ?>
                        <?php if ($parents[$sd]['address'] !== ''): ?><em><?= t9e($parents[$sd]['address']) ?></em><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="t9-announce t9-reveal">CHÚNG TÔI SẮP VỀ CHUNG MỘT NHÀ,<br>VÀ RẤT MONG CÓ BẠN Ở ĐÓ</p>

            <div class="t9-couple t9-reveal" style="--d:.1s">
                <div class="t9-big"><?= t9e($groomName) ?></div>
                <div class="t9-amp">&amp;</div>
                <div class="t9-big"><?= t9e($brideName) ?></div>
            </div>

            <?php if ($dateObject): ?>
            <div class="t9-ceremony t9-reveal">
                <p class="t9-cer-title">LỄ THÀNH HÔN</p>
                <?php if ($venue !== ''): ?><p class="t9-cer-venue"><?= t9e(mb_strtoupper($venue, 'UTF-8')) ?></p><?php endif; ?>
                <?php if ($timeHi !== ''): ?><p class="t9-cer-time">VÀO LÚC <?= t9e($timeHi) ?></p><?php endif; ?>
                <div class="t9-daterow">
                    <span><?= t9e($weekday) ?></span>
                    <b><?= t9e($day) ?></b>
                    <span>THÁNG <?= t9e($month) ?></span>
                </div>
                <p class="t9-year"><?= t9e($year) ?></p>
                <?php if ($lunarText !== ''): ?><p class="t9-lunar"><?= t9e($lunarText) ?></p><?php endif; ?>
            </div>
            <?php endif; ?>
        </section>

        <?php if (!empty($album)): ?>
        <!-- ============ 4. ALBUM ẢNH (coverflow 3D) ============ -->
        <section class="t9-sec t9-album">
            <h2 class="t9-h t9-reveal">ALBUM ẢNH</h2>
            <div class="t9-cf t9-reveal" id="t9Cf" style="--d:.1s">
                <?php foreach ($album as $i => $src): ?>
                    <div class="t9-cf-slide" data-pos="<?= $i === 0 ? '0' : '3' ?>">
                        <img <?= $i < 3 ? 'src="' . t9e($src) . '"' : 'data-src="' . t9e($src) . '"' ?> alt="Ảnh cưới <?= $i + 1 ?>" draggable="false">
                    </div>
                <?php endforeach; ?>
                <?php if (count($album) > 1): ?>
                    <button type="button" class="t9-cf-btn t9-cf-prev" aria-label="Ảnh trước">&#8249;</button>
                    <button type="button" class="t9-cf-btn t9-cf-next" aria-label="Ảnh sau">&#8250;</button>
                <?php endif; ?>
            </div>
            <?php if (count($album) > 1): ?>
                <?php if (count($album) <= 9): ?>
                    <div class="t9-dots" id="t9Dots"><?php foreach ($album as $i => $_): ?><i data-i="<?= $i ?>"></i><?php endforeach; ?></div>
                <?php endif; ?>
                <input class="t9-range" id="t9Range" type="range" min="0" max="<?= count($album) - 1 ?>" value="0" step="1" aria-label="Chọn ảnh">
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($dateObject): ?>
        <!-- ============ 5. THÔNG TIN TIỆC CƯỚI + ĐẾM NGƯỢC + LỊCH ============ -->
        <section class="t9-sec t9-party">
            <h2 class="t9-h t9-reveal">THÔNG TIN TIỆC CƯỚI</h2>
            <p class="t9-party-sub t9-reveal" style="--d:.1s">TIỆC CƯỚI SẼ DIỄN RA VÀO LÚC:</p>
            <?php if ($timeHi !== ''): ?><p class="t9-party-time t9-reveal"><?= t9e($timeHi) ?></p><?php endif; ?>
            <div class="t9-daterow t9-reveal">
                <span><?= t9e($weekday) ?></span>
                <b><?= t9e($day) ?></b>
                <span>THÁNG <?= t9e($month) ?></span>
            </div>
            <p class="t9-year t9-reveal"><?= t9e($year) ?></p>
            <?php if ($lunarText !== ''): ?><p class="t9-lunar t9-reveal"><?= t9e($lunarText) ?></p><?php endif; ?>

            <?php if ($evGreet || $evFeast): ?>
            <div class="t9-mini t9-reveal">
                <?php if ($evGreet && t9time($evGreet['event_time'] ?? '') !== ''): ?>
                    <div><small>ĐÓN KHÁCH</small><b><?= t9e(t9time($evGreet['event_time'])) ?></b></div>
                <?php endif; ?>
                <?php if ($evFeast && t9time($evFeast['event_time'] ?? '') !== ''): ?>
                    <div><small>KHAI TIỆC</small><b><?= t9e(t9time($evFeast['event_time'])) ?></b></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <p class="t9-cd-title t9-reveal">CÙNG ĐẾM NGƯỢC</p>
            <p class="t9-cd-line t9-reveal" id="t9Countdown">
                <b data-cd="d">0</b> ngày <b data-cd="h">0</b> giờ <b data-cd="m">0</b> phút <b data-cd="s">0</b> giây
            </p>

            <div class="t9-frame t9-calendar t9-reveal">
                <p class="t9-cal-title">Tháng <?= t9e($month) ?> · <?= t9e($year) ?></p>
                <div class="t9-cal-head"><span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span><span>CN</span></div>
                <div class="t9-cal-grid">
                    <?php foreach ($calCells as $c): ?>
                        <?php if ($c === null): ?><span></span>
                        <?php elseif ($c === $weddingDay): ?><span class="t9-wd"><?= $heartSvg ?><i><?= $c ?></i></span>
                        <?php else: ?><span><?= $c ?></span><?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($deco['bridge'] !== ''): ?><img class="t9-bridge t9-reveal" src="<?= t9e($deco['bridge']) ?>" alt=""><?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($mapEmbed !== ''): ?>
        <!-- ============ 6. ĐỊA ĐIỂM ============ -->
        <section class="t9-sec t9-location">
            <h2 class="t9-h t9-h-sm t9-reveal">TIỆC CƯỚI SẼ TỔ CHỨC TẠI</h2>
            <p class="t9-loc-info t9-reveal" style="--d:.1s">
                <?php if ($venue !== ''): ?><b><?= t9e($venue) ?></b><br><?php endif; ?>
                <?php if ($address !== ''): ?><?= t9e($address) ?><?php endif; ?>
            </p>
            <div class="t9-map t9-reveal" style="--d:.2s">
                <iframe src="<?= t9e($mapEmbed) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Bản đồ"></iframe>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($events)): ?>
        <!-- ============ 7. LỊCH TRÌNH NGÀY CƯỚI ============ -->
        <section class="t9-sec t9-timeline-sec">
            <?php if ($deco['flower-side'] !== ''): ?><img class="t9-deco t9-deco-side t9-deco-side2" src="<?= t9e($deco['flower-side']) ?>" alt=""><?php endif; ?>
            <h2 class="t9-h t9-reveal">LỊCH TRÌNH NGÀY CƯỚI</h2>
            <div class="t9-tl">
                <?php foreach ($events as $ev): ?>
                    <div class="t9-tl-item t9-reveal">
                        <span class="t9-tl-t"><?= t9e(t9time($ev['event_time'] ?? '')) ?></span>
                        <i></i>
                        <span class="t9-tl-n"><?= t9e($ev['event_name'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ============ 8. SỔ LƯU BÚT ============ -->
        <section class="t9-sec t9-guest" id="t9Guestbook">
            <div class="t9-frame t9-gb-frame t9-reveal">
                <h2 class="t9-gb-title">SỔ LƯU BÚT</h2>
                <input type="text" id="t9WishName" placeholder="Nhập tên của bạn..." maxlength="50" autocomplete="name">
                <textarea id="t9WishMessage" placeholder="Nhập lời chúc của bạn..." maxlength="500"></textarea>
                <div class="t9-gb-actions"><button type="button" id="t9SendWish">GỬI LỜI CHÚC</button></div>
            </div>
            <div class="t9-wish-list" id="t9WishList"></div>
        </section>

        <!-- ============ 9. HỘP QUÀ ============ -->
        <section class="t9-gift">
            <p class="t9-gift-label t9-reveal">A LITTLE GIFT</p>
            <h2 class="t9-gift-title t9-reveal" style="--d:.1s">Hộp quà mừng</h2>
            <p class="t9-gift-sub t9-reveal" style="--d:.15s">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>
            <button type="button" class="t9-gift-card t9-reveal" id="t9OpenGift" style="--d:.2s">
                <svg viewBox="0 0 90 84" fill="none" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
                    <path d="M14 36l31 12 31-12v30L45 78 14 66z" fill="#fce4e8" stroke="#d4a359"/>
                    <path d="M45 48v30M30 42v28" stroke="#d4a359"/>
                    <path d="M10 28l35 13 35-13-35-12z" fill="#fff5f7" stroke="#d4a359"/>
                    <path d="M45 41V22" stroke="#d4a359"/>
                    <path d="M45 24C38 8 22 8 26 18c2 5 12 6 19 6zM45 24C52 8 68 8 64 18c-2 5-12 6-19 6z" fill="#fff" stroke="#d4a359"/>
                    <path d="M45 24l-5 12 5-3 5 3z" fill="#e8c897" stroke="#d4a359"/>
                </svg>
                <strong>Gửi quà mừng cưới</strong>
                <span>Nhấn để xem thông tin</span>
            </button>
            <p class="t9-thanks t9-reveal">Thank you for being with us</p>
        </section>

        <!-- ============ 10. KẾT THÚC ============ -->
        <section class="t9-ending<?= $storyImage === '' ? ' t9-ending-plain' : '' ?>">
            <div class="t9-ending-bg"<?= $storyImage !== '' ? ' style="background-image:url(\'' . t9e($storyImage) . '\')"' : '' ?>></div>
            <div class="t9-ending-text t9-reveal">
                <p>Cảm ơn bạn đã ghé thăm tấm thiệp nhỏ này.</p>
                <p>Sự hiện diện và lời chúc của bạn<br>là món quà ý nghĩa nhất với chúng mình.</p>
                <p>With love,<br><?= t9e($shortGroom) ?> &amp; <?= t9e($shortBride) ?></p>
            </div>
        </section>
    </main>

    <!-- ============ MODAL HỘP QUÀ MỪNG ============ -->
    <div class="t9-modal" id="t9GiftModal" aria-hidden="true">
        <div class="t9-modal-backdrop" data-close></div>
        <div class="t9-modal-panel" role="dialog" aria-modal="true" aria-label="Hộp quà mừng">
            <div class="t9-modal-head">
                <h3>HỘP QUÀ MỪNG</h3>
                <button type="button" class="t9-modal-close" data-close aria-label="Đóng">&times;</button>
            </div>
            <div class="t9-modal-body">
                <?php if ($giftMode === 'message'): ?>
                    <p class="t9-modal-msg"><?= nl2br(t9e($giftMessage)) ?></p>
                <?php else: ?>
                <div class="t9-qr-grid">
                    <?php foreach ([['Chú Rể', $groomGift, $groomName, 'chú rể'], ['Cô Dâu', $brideGift, $brideName, 'cô dâu']] as [$cap, $g, $fb, $who]):
                        $qr = $g ? t9url($g['qr_image'] ?? '') : '';
                        $pname = $g && !empty($g['person_name']) ? $g['person_name'] : t9name($fb);
                    ?>
                        <div class="t9-qr-col">
                            <h4><?= $cap ?> - <?= t9e($pname) ?></h4>
                            <div class="t9-qr-card">
                                <?php if ($qr !== ''): ?><img src="<?= t9e($qr) ?>" alt="QR mừng cưới <?= $who ?>">
                                <?php else: ?><span>Chưa có mã QR <?= $who ?></span><?php endif; ?>
                            </div>
                            <?php if ($g): ?>
                            <div class="t9-bank">
                                <?php if (!empty($g['bank_name'])): ?><p><?= t9e($g['bank_name']) ?></p><?php endif; ?>
                                <?php if (!empty($g['account_number'])): ?><p><?= t9e($g['account_number']) ?></p><?php endif; ?>
                                <?php if (!empty($g['account_name'])): ?><p><b><?= t9e($g['account_name']) ?></b></p><?php endif; ?>
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
        <button type="button" class="t9-music" id="t9Music" aria-label="Bật/tắt nhạc">♪</button>
        <audio id="t9Audio" loop preload="none"><source src="<?= t9e($music) ?>" type="audio/mpeg"></audio>
    <?php endif; ?>
</div>

<script src="/WeddingProject/templates/template14/script.js"></script>
</body>
</html>