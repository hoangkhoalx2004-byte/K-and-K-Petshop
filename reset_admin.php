<?php
// reset_admin.php
require 'config.php';

$email = 'admin@example.com';
$rawPassword = '123456'; // mật khẩu bạn muốn dùng để đăng nhập
$hash = password_hash($rawPassword, PASSWORD_DEFAULT);

// Nếu đã có admin@example.com thì update, chưa có thì insert
$sqlCheck = "SELECT * FROM NguoiDung WHERE Email = :email LIMIT 1";
$stmt = $pdo->prepare($sqlCheck);
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

if ($user) {
    $sql = "UPDATE NguoiDung 
            SET MatKhau = :matkhau, VaiTro = 'Admin' 
            WHERE Email = :email";
    $pdo->prepare($sql)->execute([
        ':matkhau' => $hash,
        ':email'   => $email
    ]);
    echo "Đã cập nhật mật khẩu admin. Đăng nhập với mật khẩu: {$rawPassword}";
} else {
    $sql = "INSERT INTO NguoiDung (HoTen, SoDienThoai, Email, MatKhau, DiaChi, VaiTro)
            VALUES (:hoten, :sdt, :email, :matkhau, :diachi, 'Admin')";
    $pdo->prepare($sql)->execute([
        ':hoten'   => 'Quản trị viên',
        ':sdt'     => '0900000000',
        ':email'   => $email,
        ':matkhau' => $hash,
        ':diachi'  => 'TP. HCM'
    ]);
    echo "Đã tạo tài khoản admin. Đăng nhập với mật khẩu: {$rawPassword}";
}
