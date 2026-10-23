<?php
// tools/crawler_mongmer.php
// Phân hệ cào dữ liệu mẫu thiệp từ mongmer.com theo Phần IV của tài liệu đặc tả

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function fetchPage(string $url): string {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 30
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    return is_string($html) ? $html : '';
}

echo "=== KHỞI ĐỘNG BỘ CÀO MẪU THIỆP MONGMER.COM ===\n";
$targetUrl = 'https://mongmer.com/mau-thiep';
$html = fetchPage($targetUrl);

if (empty($html)) {
    die("Lỗi: Không tải được nội dung từ $targetUrl\n");
}

libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
$xpath = new DOMXPath($dom);

$cards = $xpath->query("//div[contains(@class, 'card') or contains(@class, 'template-item') or contains(@class, 'group') or contains(@class, 'col')]");
$total = 0;

echo "Đã phân tích DOM, đang duyệt danh sách mẫu thiệp...\n";

foreach ($cards as $card) {
    $titleNode = $xpath->query(".//h3 | .//h4 | .//div[contains(@class, 'title')]", $card)->item(0);
    $imgNode   = $xpath->query(".//img", $card)->item(0);

    $name   = $titleNode ? trim($titleNode->textContent) : '';
    $imgUrl = $imgNode   ? $imgNode->getAttribute('src') : '';

    if (!empty($name) && !empty($imgUrl)) {
        // Chuẩn hóa URL ảnh nếu là đường dẫn tương đối
        if (str_starts_with($imgUrl, '//')) {
            $imgUrl = 'https:' . $imgUrl;
        } elseif (str_starts_with($imgUrl, '/')) {
            $imgUrl = 'https://mongmer.com' . $imgUrl;
        }

        // Tải ảnh thumbnail về thư mục uploads
        $imgContent = fetchPage($imgUrl);
        $fileName   = 'crawled_' . md5($name) . '.webp';
        $destPath   = __DIR__ . '/../uploads/thumbnails/' . $fileName;

        if (!is_dir(dirname($destPath))) {
            mkdir(dirname($destPath), 0755, true);
        }

        if (!empty($imgContent)) {
            file_put_contents($destPath, $imgContent);
        }

        // Sinh mã thư mục template kế tiếp
        $folderName = 'template_' . substr(md5($name), 0, 8);

        // Kiểm tra xem mẫu đã tồn tại trong DB chưa
        $checkStmt = $pdo->prepare("SELECT id FROM templates WHERE name = :name LIMIT 1");
        $checkStmt->execute(['name' => $name]);
        if (!$checkStmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO templates (name, folder, thumbnail, description, status, created_at)
                VALUES (:name, :folder, :thumb, :desc, 1, NOW())
            ");
            $stmt->execute([
                'name'   => $name,
                'folder' => $folderName,
                'thumb'  => '/WeddingProject/uploads/thumbnails/' . $fileName,
                'desc'   => "Mẫu thiệp phong cách: " . $name
            ]);
            $total++;
            echo "[+] Đã thêm mẫu: $name -> thư mục: $folderName\n";
        }
    }
}

echo "=== HOÀN TẤT: CÀO THÀNH CÔNG $total MẪU THIỆP MỚI ===\n";
