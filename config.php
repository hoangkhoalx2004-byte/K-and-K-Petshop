<?php
// config.php
// Chỉnh thông tin DB ở đây
define('DB_HOST', 'sql307.infinityfree.com');
define('DB_PORT', '3306'); // nếu khác 3306 đổi ở đây
define('DB_NAME', 'if0_40429909_kkpetshop');
define('DB_USER', 'if0_40429909');
define('DB_PASS', 'khoalx2004');

// PDO connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    // Không xuất lỗi chi tiết trên production
    die("DB connection failed: " . $e->getMessage());
}
