<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once 'config.php';

// chỉ cho admin gọi
if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 2 đơn có tổng tiền cao nhất trong 10 đơn gần nhất
    $sql = "
        SELECT *
        FROM (
            SELECT MaDonHang, TenNguoiMua, TongTien, NgayDat, TrangThai
            FROM DonHang
            ORDER BY MaDonHang DESC
            LIMIT 10
        ) t
        ORDER BY TongTien DESC
        LIMIT 2
    ";
    $st = $pdo->query($sql);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}