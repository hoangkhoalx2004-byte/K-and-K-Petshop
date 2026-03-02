<?php
require 'config.php';

$stmt = $pdo->prepare("SELECT * FROM NguoiDung WHERE Email = ?");
$stmt->execute(['admin@example.com']);
$exists = $stmt->fetch();

if ($exists) {
    echo "Admin already exists!";
    exit;
}

$sql = "INSERT INTO NguoiDung (HoTen, Email, MatKhau, VaiTro) VALUES (?, ?, ?, ?)";
$pdo->prepare($sql)->execute([
    'Administrator',
    'admin@example.com',
    password_hash('123456', PASSWORD_DEFAULT),
    'Admin'
]);

echo "Admin created successfully!";
