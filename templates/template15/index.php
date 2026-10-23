<?php
/*
|--------------------------------------------------------------------------
| TEMPLATE 08 – WEDDING ĐỎ TRẮNG
|--------------------------------------------------------------------------
| w/index.php đã tìm wedding theo slug và truyền vào $wedding (+ $pdo).
| Template chỉ ĐỌC dữ liệu và hiển thị. Không lưu gì.
| Cột tuỳ chọn (groom_photo, bride_photo, lunar_date): có thì dùng, chưa có thì tự ẩn.
|--------------------------------------------------------------------------
*/

if (!isset($wedding) || !$wedding) {
    die("Không tìm thấy thông tin đám cưới.");
}

/* ---------- helper ---------- */
if (!function_exists('t8e')) {
    function t8e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
}
/* Đường dẫn tuyệt đối/http giữ nguyên, còn lại thêm /WeddingProject/ (không lặp tiền tố). */
if (!function_exists('t8url')) {
    function t8url($p) {
        $p = trim((string)($p ?? ''));
        if ($p === '') return '';
        if (preg_match('#^(https?:)?//#i', $p) || $p[0] === '/') return $p;
        return '/WeddingProject/' . ltrim($p, '/');
    }
}
/* "Nguyễn Văn Anh" -> "Văn Anh" (như ảnh mẫu); tên 1-2 chữ giữ nguyên */
if (!function_exists('t8name')) {
    function t8name($n) {
        $p = preg_split('/\s+/u', trim((string)$n));
        if (count($p) >= 3) array_shift($p);
        return implode(' ', $p);
    }
}
if (!function_exists('t8initial')) {
    function t8initial($n) {
        $p = preg_split('/\s+/u', trim((string)$n));
        $l = end($p);
        return ($l === false || $l === '') ? '?' : mb_strtoupper(mb_substr($l, 0, 1, 'UTF-8'), 'UTF-8');
    }
}
/* "Ông A" giữ nguyên, "A" -> "ÔNG A" (in hoa như mẫu) */
if (!function_exists('t8parent')) {
    function t8parent($name, $prefix) {
        $name = trim((string)$name);
        if ($name === '') return '';
        if (preg_match('/^(ông|bà)\b/iu', $name)) return mb_strtoupper($name, 'UTF-8');
        return mb_strtoupper($prefix . ' ' . $name, 'UTF-8');
    }
}

/* Thư mục template tự nhận theo tên folder -> đổi tên folder vẫn chạy */
$base = '/WeddingProject/templates/' . basename(__DIR__) . '/';

/* Số ảnh hiện sẵn trong khung album so le (ảnh 7). Ảnh còn lại hiện dạng +N, bấm vào để xem tiếp. */
$collageShow = 5;

/* ---------- thông tin cơ bản ---------- */
$groomName = $wedding['groom_name'] ?? '';
$brideName = $wedding['bride_name'] ?? '';
$venue     = $wedding['venue'] ?? '';
$address   = $wedding['address'] ?? '';
$groomPhoto = t8url($wedding['groom_photo'] ?? '');
$bridePhoto = t8url($wedding['bride_photo'] ?? '');
$lunarDate = trim((string)($wedding['lunar_date'] ?? ''));
$music     = t8url($wedding['music'] ?? '');
$storyImage = t8url($wedding['story_image'] ?? '');       /* ảnh 2 và ảnh 6 */
$bgImage    = t8url($wedding['background_image'] ?? '');  /* ảnh 1, nền sương mờ, màn kết */
$shortGroom = t8name($groomName);
$shortBride = t8name($brideName);

/* ---------- ngày giờ ---------- */
$weekdayNames = ['Chủ nhật','Thứ hai','Thứ ba','Thứ tư','Thứ năm','Thứ sáu','Thứ bảy'];
$dateObject = null;
if (!empty($wedding['wedding_date'])) {
    try { $dateObject = new DateTime($wedding['wedding_date']); } catch (Exception $e) { $dateObject = null; }
}
$timeHi = '';
$timeSql = '00:00:00';
if (!empty($wedding['wedding_time']) && strtotime($wedding['wedding_time']) !== false) {
    $ts = strtotime($wedding['wedding_time']);
    $timeHi = date('H:i', $ts);
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
    $dotDate = $dateObject->format('d.m.Y');
    $targetIso = $dateObject->format('Y-m-d') . 'T' . $timeSql;
    $weddingDay = (int)$dateObject->format('j');
    $first = new DateTime($dateObject->format('Y-m-01'));
    $offset = ((int)$first->format('w') + 6) % 7;      /* tuần bắt đầu từ Thứ 2 như ảnh mẫu */
    $dim = (int)$first->format('t');
    for ($i = 0; $i < $offset; $i++) $calCells[] = null;
    for ($d = 1; $d <= $dim; $d++) $calCells[] = $d;
}

/* ---------- album ---------- */
$photoStmt = $pdo->prepare("SELECT image_path FROM wedding_photos WHERE wedding_id = :wedding_id ORDER BY sort_order ASC, id ASC");
$photoStmt->execute(['wedding_id' => $wedding['id']]);
$album = [];
foreach ($photoStmt->fetchAll() as $row) {
    $u = t8url($row['image_path'] ?? '');
    if ($u !== '') $album[] = $u;
}
$shuffled = $album;
shuffle($shuffled);                                    /* thứ tự ngẫu nhiên cho slideshow */
$archPhoto = !empty($album) ? $album[array_rand($album)] : ''; /* ảnh vòm (ảnh 5): 1 ảnh bất kỳ */
$collageVisible = array_slice($album, 0, $collageShow);
$collageExtra = max(0, count($album) - $collageShow);
/* so le: cột trái nhận ảnh 1 và 3, cột phải nhận ảnh 2, 4, 5 */
$colLeft = [];
$colRight = [];
foreach ($collageVisible as $i => $src) {
    if ($i === 0 || $i === 2) $colLeft[$i] = $src; else $colRight[$i] = $src;
}
$t8Tile = function ($i, $src) use ($collageVisible, $collageExtra, $collageShow) {
    $isMore = ($collageExtra > 0 && $i === count($collageVisible) - 1);
    $open = $isMore ? $collageShow : $i;   /* ô +N mở từ ảnh đầu tiên chưa hiện */
    echo '<button type="button" class="t8-tile t8-reveal" data-i="' . (int)$open . '" aria-label="Xem ảnh ' . ($open + 1) . '" style="--d:' . (0.08 * $i) . 's">';
    echo '<img src="' . t8e($src) . '" alt="Ảnh cưới ' . ($i + 1) . '" loading="lazy">';
    if ($isMore) echo '<span class="t8-more">+' . (int)$collageExtra . '</span>';
    echo '</button>';
};

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

/* ---------- SVG dùng lại ---------- */
$bowSvg = '<svg viewBox="0 0 80 60" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M40 26C30 5 7 6 8 21c1 13 22 12 32 5z"/><path d="M40 26C50 5 73 6 72 21c-1 13-22 12-32 5z"/><path d="M14 18c5-4 12-3 20 6M66 18c-5-4-12-3-20 6"/><circle cx="40" cy="26" r="4.5"/><path d="M37 30L27 55l9-5 4 8M43 30l10 25-9-5-4 8"/></svg>';
$heartSvg = '<svg viewBox="0 0 32 30" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round" aria-hidden="true"><path d="M16 27C4 18 1 11 5 6c3-4 9-3 11 2 2-5 8-6 11-2 4 5 1 12-11 21z"/></svg>';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t8e($groomName) ?> &amp; <?= t8e($brideName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Cormorant+Garamond:wght@300;400;500;600&family=Great+Vibes&family=Montserrat:wght@400;500;600&display=swap&subset=vietnamese" rel="stylesheet">
    <link rel="stylesheet" href="/WeddingProject/templates/template15/style.css">
    <noscript><style>.template08-page .t8-reveal{opacity:1!important;filter:none!important;transform:none!important}</style></noscript>
</head>
<body class="t8-body t8-locked">

<div class="template08-page"
     data-target="<?= t8e($targetIso) ?>"
     data-wedding-id="<?= (int)$wedding['id'] ?>"
     data-wish-api="/WeddingProject/api/wishes.php"
     data-slides="<?= t8e(json_encode($shuffled, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>"
     data-album="<?= t8e(json_encode($album, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>">

    <!-- ============ 1. MỞ THIỆP (ảnh nền = background_image) ============ -->
    <section id="t8Opening" class="t8-opening" role="button" tabindex="0" aria-label="Mở thiệp"
        <?= $bgImage !== '' ? 'style="background-image:url(\'' . t8e($bgImage) . '\')"' : '' ?>>
        <div class="t8-opening-shade"></div>
        <h1 class="t8-opening-title"><span>Thiệp</span><span>mời</span></h1>
        <div class="t8-opening-hint">Chạm để mở thiệp</div>
        <div class="t8-opening-name t8-left"><?= t8e($groomName) ?></div>
        <div class="t8-opening-name t8-right"><?= t8e($brideName) ?></div>
    </section>

    <main id="t8Main" class="t8-main">

        <!-- ============ 2. HERO (ảnh = story_image, vòm trắng bo tròn) ============ -->
        <section class="t8-hero"<?= $storyImage !== '' ? ' style="background-image:url(\'' . t8e($storyImage) . '\')"' : '' ?>>
            <?php if ($dotDate !== ''): ?>
                <div class="t8-savedate"><span>Save the date</span><b><?= t8e($dotDate) ?></b></div>
            <?php endif; ?>
            <div class="t8-hero-strip"></div>
            <div class="t8-hero-dome"></div>
            <div class="t8-hero-text">
                <span class="t8-bow"><?= $bowSvg ?></span>
                <h2>WEDDING</h2>
                <p class="t8-hero-names"><span><?= t8e($shortGroom) ?></span><i>-</i><span><?= t8e($shortBride) ?></span></p>
            </div>
        </section>

        <!-- ============ 3. THƯ MỜI + HAI GIA ĐÌNH + ẢNH CHÚ RỂ / CÔ DÂU ============ -->
        <section class="t8-invite">
            <h2 class="t8-inv-title t8-reveal">THƯ MỜI TIỆC CƯỚI</h2>
            <hr class="t8-rule t8-reveal">
            <?php if ($weekday !== ''): ?>
                <p class="t8-inv-day t8-reveal" style="--d:.1s"><?= t8e(mb_strtoupper($weekday, 'UTF-8')) ?><?= $timeHi !== '' ? ' - ' . t8e($timeHi) : '' ?></p>
                <p class="t8-inv-date t8-reveal" style="--d:.15s"><?= t8e($dotDate) ?></p>
            <?php endif; ?>

            <div class="t8-fams t8-reveal" style="--d:.2s">
                <?php foreach ([['groom', 'NHÀ TRAI'], ['bride', 'NHÀ GÁI']] as [$sd, $cap]): ?>
                    <div class="t8-fam">
                        <h3><?= $cap ?></h3>
                        <p>
                            <?php if ($parents[$sd]['father'] !== ''): ?><b><?= t8e(t8parent($parents[$sd]['father'], 'Ông')) ?></b><?php endif; ?>
                            <?php if ($parents[$sd]['mother'] !== ''): ?><b><?= t8e(t8parent($parents[$sd]['mother'], 'Bà')) ?></b><?php endif; ?>
                        </p>
                        <?php if ($parents[$sd]['address'] !== ''): ?><em><?= t8e($parents[$sd]['address']) ?></em><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="t8-duo t8-reveal" style="--d:.1s">
                <figure>
                    <?php if ($groomPhoto !== ''): ?><img src="<?= t8e($groomPhoto) ?>" alt="Ảnh chú rể" loading="lazy">
                    <?php else: ?><span><?= t8e(t8initial($groomName)) ?></span><?php endif; ?>
                </figure>
                <figure>
                    <?php if ($bridePhoto !== ''): ?><img src="<?= t8e($bridePhoto) ?>" alt="Ảnh cô dâu" loading="lazy">
                    <?php else: ?><span><?= t8e(t8initial($brideName)) ?></span><?php endif; ?>
                </figure>
            </div>
            <div class="t8-gb t8-reveal"><span>GROOM</span><span>BRIDE</span></div>
            <p class="t8-script-names t8-reveal">
                <span><?= t8e($shortGroom) ?></span>
                <em class="t8-bow-sm"><?= $bowSvg ?></em>
                <span><?= t8e($shortBride) ?></span>
            </p>
        </section>

        <!-- ============ 4. L.O.V.E + slideshow album + ngày cưới ============ -->
        <section class="t8-love">
            <?php if (!empty($album)): ?>
                <p class="t8-love-title t8-reveal">L.O.V.E</p>
                <div class="t8-slides t8-reveal" id="t8Slides" style="--d:.1s">
                    <img class="is-on" src="<?= t8e($shuffled[0]) ?>" alt="Ảnh cưới">
                    <img src="" alt="" aria-hidden="true">
                </div>
            <?php endif; ?>
            <p class="t8-married t8-reveal">We got married</p>
            <?php if ($dateObject): ?>
                <h2 class="t8-ceremony t8-reveal">LỄ THÀNH HÔN</h2>
                <p class="t8-weekday t8-reveal"><?= t8e(mb_strtoupper($weekday, 'UTF-8')) ?></p>
                <div class="t8-daterow t8-reveal">
                    <span>THÁNG <?= t8e($month) ?></span>
                    <b><?= t8e($day) ?></b>
                    <span>NĂM <?= t8e($year) ?></span>
                </div>
                <?php if ($timeHi !== ''): ?><p class="t8-time t8-reveal"><?= t8e($timeHi) ?></p><?php endif; ?>
                <?php if ($lunarDate !== ''): ?><p class="t8-lunar t8-reveal"><?= t8e($lunarDate) ?></p><?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if ($dateObject): ?>
        <!-- ============ 5. VÒM ẢNH + LỊCH (đánh dấu ngày cưới) ============ -->
        <section class="t8-arch-sec">
            <div class="t8-arch t8-reveal">
                <svg class="t8-arc-text" viewBox="0 0 400 200" aria-hidden="true">
                    <defs><path id="t8Arc" d="M 12 200 A 188 188 0 0 1 388 200"/></defs>
                    <text><textPath href="#t8Arc" startOffset="50%" text-anchor="middle">WELCOME TO OUR WEDDING</textPath></text>
                </svg>
                <div class="t8-arch-photo"<?= $archPhoto !== '' ? ' style="background-image:url(\'' . t8e($archPhoto) . '\')"' : '' ?>></div>
                <div class="t8-cal-circle">
                    <div class="t8-cal-head"><span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span><span class="t8-cn">CN</span></div>
                    <div class="t8-cal-grid">
                        <?php foreach ($calCells as $c): ?>
                            <?php if ($c === null): ?><span></span>
                            <?php elseif ($c === $weddingDay): ?><span class="t8-wd"><?= $heartSvg ?><i><?= $c ?></i></span>
                            <?php else: ?><span><?= $c ?></span><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ 6. ĐỊA CHỈ + ĐẾM NGƯỢC (ảnh = story_image) ============ -->
        <section class="t8-addr">
            <div class="t8-addr-grid">
                <div class="t8-addr-photo t8-reveal"<?= $storyImage !== '' ? ' style="background-image:url(\'' . t8e($storyImage) . '\')"' : '' ?>></div>
                <div class="t8-addr-info t8-reveal" style="--d:.15s">
                    <h2>ADDRESS</h2>
                    <?php if ($venue !== ''): ?><h3><?= t8e(mb_strtoupper($venue, 'UTF-8')) ?></h3><?php endif; ?>
                    <?php if ($address !== ''): ?><p><?= t8e($address) ?></p><?php endif; ?>
                    <?php if ($mapEmbed !== ''): ?>
                        <div class="t8-map"><iframe src="<?= t8e($mapEmbed) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen title="Bản đồ"></iframe></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="t8-count t8-reveal" id="t8Countdown">
                <div><b data-cd="d">0</b><small>ngày</small></div>
                <div><b data-cd="h">0</b><small>giờ</small></div>
                <div><b data-cd="m">0</b><small>phút</small></div>
                <div><b data-cd="s">0</b><small>giây</small></div>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($collageVisible)): ?>
        <!-- ============ 7. ALBUM SO LE trên nền background_image sương mờ ============ -->
        <section class="t8-collage">
            <div class="t8-collage-bg"<?= $bgImage !== '' ? ' style="background-image:url(\'' . t8e($bgImage) . '\')"' : '' ?>></div>
            <div class="t8-mist t8-mist-a"></div>
            <div class="t8-mist t8-mist-b"></div>
            <p class="t8-col-title">INVITATION</p>
            <span class="t8-side t8-side-l">I love you forever</span>
            <span class="t8-side t8-side-r">Nice to meet you</span>
            <div class="t8-frame t8-mosaic<?= count($collageVisible) === 1 ? ' t8-solo' : '' ?>" id="t8Mosaic">
                <div class="t8-mcol t8-mcol-l">
                    <?php foreach ($colLeft as $i => $src) $t8Tile($i, $src); ?>
                </div>
                <?php if (!empty($colRight)): ?>
                <div class="t8-mcol t8-mcol-r">
                    <?php foreach ($colRight as $i => $src) $t8Tile($i, $src); ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ============ 7B. SỔ LƯU BÚT (nằm trên hộp quà) ============ -->
        <section class="t8-wish" id="t8Wish">
            <div class="t8-wish-card-form t8-reveal">
                <h2 class="t8-wish-title">SỔ LƯU BÚT</h2>
                <div class="t8-wish-form">
                    <input type="text" id="t8WishName" class="t8-wish-input" placeholder="Nhập tên*" maxlength="100" autocomplete="name">
                    <textarea id="t8WishMessage" class="t8-wish-input t8-wish-textarea" placeholder="Nhập lời chúc*" rows="3" maxlength="500"></textarea>
                    <div class="t8-wish-actions">
                        <button type="button" id="t8SendWish" class="t8-wish-send">GỬI LỜI CHÚC</button>
                    </div>
                    <p class="t8-wish-status" id="t8WishStatus" style="display:none">Cảm ơn bạn đã gửi lời chúc ♥</p>
                </div>
                <!-- Hoa trang trí (tuỳ chọn): đặt PNG nền trong suốt vào templates/template08/assets/flower.png. Không có ảnh thì tự ẩn. -->
                <img class="t8-wish-flower" src="<?= t8e($base) ?>assets/flower.png" alt="" aria-hidden="true" onerror="this.style.display='none'">
            </div>
            <div class="t8-wish-list" id="t8WishList"></div>
        </section>

        <!-- ============ 8. HỘP QUÀ ============ -->
        <section class="t8-gift">
            <p class="t8-gift-label t8-reveal">A LITTLE GIFT</p>
            <h2 class="t8-gift-title t8-reveal" style="--d:.1s">Hộp quà mừng</h2>
            <p class="t8-gift-sub t8-reveal" style="--d:.15s">Cảm ơn bạn đã gửi lời chúc phúc đến chúng mình.</p>
            <button type="button" class="t8-gift-card t8-reveal" id="t8OpenGift" style="--d:.2s">
                <svg viewBox="0 0 90 84" fill="none" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round" aria-hidden="true">
                    <path d="M14 36l31 12 31-12v30L45 78 14 66z" fill="#f4dcd5" stroke="#8c1c1c"/>
                    <path d="M45 48v30M30 42v28" stroke="#8c1c1c"/>
                    <path d="M10 28l35 13 35-13-35-12z" fill="#f9e9e4" stroke="#8c1c1c"/>
                    <path d="M45 41V22" stroke="#8c1c1c"/>
                    <path d="M45 24C38 8 22 8 26 18c2 5 12 6 19 6zM45 24C52 8 68 8 64 18c-2 5-12 6-19 6z" fill="#fff" stroke="#8c1c1c"/>
                    <path d="M45 24l-5 12 5-3 5 3z" fill="#e9c1b8" stroke="#8c1c1c"/>
                </svg>
                <strong>Gửi quà mừng cưới</strong>
                <span>Nhấn để xem thông tin</span>
            </button>
            <p class="t8-thanks t8-reveal">Thank you for being with us</p>
        </section>

        <!-- ============ 9. KẾT THÚC: bằng nửa màn hình điện thoại ============ -->
        <section class="t8-ending<?= $bgImage === '' ? ' t8-ending-plain' : '' ?>">
            <div class="t8-ending-bg"<?= $bgImage !== '' ? ' style="background-image:url(\'' . t8e($bgImage) . '\')"' : '' ?>></div>
            <div class="t8-ending-text t8-reveal">
                <p>Cảm ơn bạn đã ghé thăm tấm thiệp nhỏ này.</p>
                <p>Sự hiện diện và lời chúc của bạn<br>là món quà ý nghĩa nhất với chúng mình.</p>
                <p>With love,<br><?= t8e($shortGroom) ?> &amp; <?= t8e($shortBride) ?></p>
            </div>
        </section>
    </main>

    <!-- ============ MODAL HỘP QUÀ MỪNG ============ -->
    <div class="t8-modal" id="t8GiftModal" aria-hidden="true">
        <div class="t8-modal-backdrop" data-close></div>
        <div class="t8-modal-panel" role="dialog" aria-modal="true" aria-label="Hộp quà mừng">
            <div class="t8-modal-head">
                <h3>HỘP QUÀ MỪNG</h3>
                <button type="button" class="t8-modal-close" data-close aria-label="Đóng">&times;</button>
            </div>
            <div class="t8-modal-body">
                <?php if ($giftMode === 'message'): ?>
                    <p class="t8-modal-msg"><?= nl2br(t8e($giftMessage)) ?></p>
                <?php else: ?>
                <div class="t8-qr-grid">
                    <?php foreach ([['Chú Rể', $groomGift, $groomName, 'chú rể'], ['Cô Dâu', $brideGift, $brideName, 'cô dâu']] as [$cap, $g, $fb, $who]):
                        $qr = $g ? t8url($g['qr_image'] ?? '') : '';
                        $pname = $g && !empty($g['person_name']) ? $g['person_name'] : t8name($fb);
                    ?>
                        <div class="t8-qr-col">
                            <h4><?= $cap ?> - <?= t8e($pname) ?></h4>
                            <div class="t8-qr-card">
                                <?php if ($qr !== ''): ?><img src="<?= t8e($qr) ?>" alt="QR mừng cưới <?= $who ?>">
                                <?php else: ?><span>Chưa có mã QR <?= $who ?></span><?php endif; ?>
                            </div>
                            <?php if ($g): ?>
                            <div class="t8-bank">
                                <?php if (!empty($g['bank_name'])): ?><p><?= t8e($g['bank_name']) ?></p><?php endif; ?>
                                <?php if (!empty($g['account_number'])): ?><p><?= t8e($g['account_number']) ?></p><?php endif; ?>
                                <?php if (!empty($g['account_name'])): ?><p><b><?= t8e($g['account_name']) ?></b></p><?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($collageVisible)): ?>
    <!-- ============ XEM ẢNH ALBUM (lightbox) ============ -->
    <div class="t8-lb" id="t8Lightbox" aria-hidden="true">
        <button type="button" class="t8-lb-close" data-lb-close aria-label="Đóng">&times;</button>
        <button type="button" class="t8-lb-nav t8-lb-prev" aria-label="Ảnh trước">&#8249;</button>
        <div class="t8-lb-stage" id="t8LbStage"><img id="t8LbImg" src="" alt=""></div>
        <button type="button" class="t8-lb-nav t8-lb-next" aria-label="Ảnh sau">&#8250;</button>
        <div class="t8-lb-count" id="t8LbCount"></div>
    </div>
    <?php endif; ?>

    <?php if ($music !== ''): ?>
        <button type="button" class="t8-music" id="t8Music" aria-label="Bật/tắt nhạc">♪</button>
        <audio id="t8Audio" loop preload="none"><source src="<?= t8e($music) ?>" type="audio/mpeg"></audio>
    <?php endif; ?>
</div>

<script src="/WeddingProject/templates/template15/script.js"></script>
</body>
</html>