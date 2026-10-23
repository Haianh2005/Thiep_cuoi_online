<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$templateParam = trim((string)($_GET['template'] ?? ''));

if ($templateParam === '') {
    die('Thiếu mã mẫu thiệp.');
}

if (ctype_digit($templateParam)) {
    $stmt = $pdo->prepare("
        SELECT id, name, folder, description
        FROM templates
        WHERE id = :id AND status = 1
        LIMIT 1
    ");
    $stmt->execute(['id' => (int)$templateParam]);
} else {
    $stmt = $pdo->prepare("
        SELECT id, name, folder, description
        FROM templates
        WHERE folder = :folder AND status = 1
        LIMIT 1
    ");
    $stmt->execute(['folder' => $templateParam]);
}

$template = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$template) {
    die('Không tìm thấy mẫu thiệp.');
}

/*
|--------------------------------------------------------------------------
| TẠO / LẤY BẢN DEMO CỦA TEMPLATE
|--------------------------------------------------------------------------
| Bản demo chỉ dùng để khách xem mẫu.
| status = 3 = ẩn, nên không thể xuất hiện như thiệp public.
| Không dùng bản demo này để khách chỉnh sửa.
|--------------------------------------------------------------------------
*/
$demoSlug = '__demo_template_' . (int)$template['id'];

$demoStmt = $pdo->prepare("
    SELECT *
    FROM weddings
    WHERE slug = :slug
    LIMIT 1
");

$demoStmt->execute([
    'slug' => $demoSlug
]);

$wedding = $demoStmt->fetch(PDO::FETCH_ASSOC);

if (!$wedding) {
    $editToken = bin2hex(random_bytes(16));

    $insert = $pdo->prepare("
        INSERT INTO weddings (
            template_id,
            slug,
            edit_token,
            groom_name,
            bride_name,
            wedding_date,
            wedding_time,
            venue,
            address,
            story,
            background_image,
            music,
            story_image,
            status,
            created_at,
            updated_at
        ) VALUES (
            :template_id,
            :slug,
            :edit_token,
            :groom_name,
            :bride_name,
            :wedding_date,
            :wedding_time,
            :venue,
            :address,
            :story,
            NULL,
            NULL,
            NULL,
            3,
            NOW(),
            NOW()
        )
    ");

    $insert->execute([
        'template_id' => (int)$template['id'],
        'slug' => $demoSlug,
        'edit_token' => $editToken,
        'groom_name' => 'Trung Hiếu',
        'bride_name' => 'Minh Nguyệt',
        'wedding_date' => '2026-12-20',
        'wedding_time' => '17:30:00',
        'venue' => 'Areca Garden Restaurant',
        'address' => 'Hà Nội, Việt Nam',
        'story' => 'Đây là nội dung mẫu để khách xem trước giao diện của thiệp cưới.'
    ]);

    $demoStmt->execute([
        'slug' => $demoSlug
    ]);

    $wedding = $demoStmt->fetch(PDO::FETCH_ASSOC);
}

if (!$wedding) {
    die('Không thể tạo bản xem trước mẫu.');
}

$templatePath =
    __DIR__ . '/../templates/' .
    $template['folder'] . '/index.php';

if (!is_file($templatePath)) {
    die('Không tìm thấy file giao diện của mẫu thiệp.');
}

/*
|--------------------------------------------------------------------------
| CHẠY TEMPLATE THẬT
|--------------------------------------------------------------------------
| Template nhận chính biến $wedding giống như /w/index.php.
| Vì vậy khách xem đúng giao diện thật của mẫu.
|--------------------------------------------------------------------------
*/
ob_start();

require $templatePath;

$html = ob_get_clean();

/*
|--------------------------------------------------------------------------
| NÚT CHỈNH SỬA Ở CUỐI BẢN DEMO
|--------------------------------------------------------------------------
*/
$demoCta = '
<section class="template-demo-cta">
    <div class="template-demo-cta__inner">
        <div class="template-demo-cta__label">BẠN ƯNG MẪU NÀY?</div>
        <h2>✏️ Chỉnh sửa mẫu này</h2>
        <p>
            Tự nhập thông tin đám cưới, tải ảnh, thêm nhạc,
            QR ngân hàng và các nội dung của riêng bạn.
        </p>

        <form action="/WeddingProject/mau-thiep/start.php" method="post">
            <input type="hidden" name="template_id" value="' . (int)$template['id'] . '">
            <button type="submit">✏️ CHỈNH SỬA MẪU NÀY</button>
        </form>

        <a href="/WeddingProject/mau-thiep/" class="template-demo-back">
            ← Xem các mẫu khác
        </a>
    </div>
</section>

<style>
    .template-demo-cta {
        padding: 70px 20px 90px;
        text-align: center;
        background: #fff;
    }

    .template-demo-cta__inner {
        width: min(680px, 100%);
        margin: 0 auto;
        padding: 42px 26px;
        border: 1px solid rgba(0,0,0,.08);
        border-radius: 24px;
        box-shadow: 0 14px 50px rgba(0,0,0,.10);
    }

    .template-demo-cta__label {
        font-size: 12px;
        font-weight: 800;
        letter-spacing: 2px;
        color: #a02b2b;
        margin-bottom: 10px;
    }

    .template-demo-cta h2 {
        margin: 0;
        font-size: clamp(26px, 5vw, 40px);
    }

    .template-demo-cta p {
        max-width: 520px;
        margin: 14px auto 24px;
        line-height: 1.7;
        color: #777;
    }

    .template-demo-cta button {
        border: 0;
        border-radius: 999px;
        padding: 15px 28px;
        background: #9d1c28;
        color: #fff;
        font-weight: 800;
        font-size: 14px;
        cursor: pointer;
        box-shadow: 0 10px 25px rgba(157,28,40,.25);
    }

    .template-demo-cta button:hover {
        transform: translateY(-2px);
    }

    .template-demo-back {
        display: block;
        margin-top: 18px;
        color: #777;
        font-size: 13px;
    }
</style>
';

$html = str_replace('</body>', $demoCta . "\n</body>", $html);

echo $html;
