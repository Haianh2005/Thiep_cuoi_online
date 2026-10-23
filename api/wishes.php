<?php

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../config/database.php';

try {

    /*
     * ================================
     * LẤY DANH SÁCH LỜI CHÚC
     * ================================
     */

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        $weddingId = isset($_GET['wedding_id'])
            ? (int) $_GET['wedding_id']
            : 0;

        if ($weddingId <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Thiếu wedding_id.'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }

        $stmt = $pdo->prepare("
            SELECT
                id,
                guest_name,
                message,
                created_at
            FROM wedding_wishes
            WHERE wedding_id = :wedding_id
            ORDER BY id DESC
        ");

        $stmt->execute([
            'wedding_id' => $weddingId
        ]);

        $wishes = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'wishes' => $wishes
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    /*
     * ================================
     * THÊM LỜI CHÚC
     * ================================
     */

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $weddingId = isset($data['wedding_id'])
            ? (int) $data['wedding_id']
            : 0;

        $guestName = isset($data['guest_name'])
            ? trim($data['guest_name'])
            : '';

        $message = isset($data['message'])
            ? trim($data['message'])
            : '';


        /*
         * Kiểm tra dữ liệu
         */

        if ($weddingId <= 0) {

            echo json_encode([
                'success' => false,
                'message' => 'Thiếu wedding_id.'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }


        if ($guestName === '') {

            echo json_encode([
                'success' => false,
                'message' => 'Vui lòng nhập tên.'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }


        if ($message === '') {

            echo json_encode([
                'success' => false,
                'message' => 'Vui lòng nhập lời chúc.'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }


        /*
         * Giới hạn độ dài
         */

        if (mb_strlen($guestName) > 255) {

            echo json_encode([
                'success' => false,
                'message' => 'Tên quá dài.'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }


        if (mb_strlen($message) > 2000) {

            echo json_encode([
                'success' => false,
                'message' => 'Lời chúc quá dài.'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }


        /*
         * Kiểm tra wedding có tồn tại
         */

        $checkWedding = $pdo->prepare("
            SELECT id
            FROM weddings
            WHERE id = :wedding_id
            LIMIT 1
        ");

        $checkWedding->execute([
            'wedding_id' => $weddingId
        ]);

        $wedding = $checkWedding->fetch();


        if (!$wedding) {

            echo json_encode([
                'success' => false,
                'message' => 'Không tìm thấy thiệp cưới.'
            ], JSON_UNESCAPED_UNICODE);

            exit;
        }


        /*
         * LƯU VÀO DATABASE
         */

        $stmt = $pdo->prepare("
            INSERT INTO wedding_wishes
            (
                wedding_id,
                guest_name,
                message
            )
            VALUES
            (
                :wedding_id,
                :guest_name,
                :message
            )
        ");

        $stmt->execute([
            'wedding_id' => $weddingId,
            'guest_name' => $guestName,
            'message' => $message
        ]);


        /*
         * Lấy lại lời chúc vừa tạo
         */

        $wishId = $pdo->lastInsertId();

        $getWish = $pdo->prepare("
            SELECT
                id,
                guest_name,
                message,
                created_at
            FROM wedding_wishes
            WHERE id = :id
            LIMIT 1
        ");

        $getWish->execute([
            'id' => $wishId
        ]);

        $wish = $getWish->fetch();


        echo json_encode([
            'success' => true,
            'message' => 'Đã gửi lời chúc thành công.',
            'wish' => $wish
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }


    /*
     * Phương thức không được hỗ trợ
     */

    echo json_encode([
        'success' => false,
        'message' => 'Phương thức không được hỗ trợ.'
    ], JSON_UNESCAPED_UNICODE);


} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Lỗi database.',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);

}