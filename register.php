<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['hoten'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['sdt'] ?? '');
    $address = trim($_POST['diachi'] ?? '');
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if ($name === '' || $email === '' || $pass === '' || $pass2 === '') {
        $error = 'Vui lòng nhập đầy đủ các trường bắt buộc.';
    } elseif ($pass !== $pass2) {
        $error = 'Mật khẩu nhập lại không khớp.';
    } else {
        // kiểm tra trùng email
        $stmt = $pdo->prepare("SELECT MaNguoiDung FROM NguoiDung WHERE Email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $error = 'Email đã được sử dụng.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $sql = "INSERT INTO NguoiDung (HoTen, SoDienThoai, Email, MatKhau, DiaChi, VaiTro)
                    VALUES (:hoten, :sdt, :email, :matkhau, :diachi, 'Khách hàng')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':hoten' => $name,
                ':sdt' => $phone,
                ':email' => $email,
                ':matkhau' => $hash,
                ':diachi' => $address
            ]);
            $success = 'Đăng ký thành công! Bạn có thể đăng nhập ngay.';
        }
    }
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đăng ký tài khoản - K&K PetShop</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f5f6fa; }
    .box {
      max-width:480px;
      margin:60px auto;
      background:#fff;
      padding:24px;
      border-radius:12px;
      box-shadow:0 8px 25px rgba(0,0,0,0.08);
    }
  </style>
</head>
<body>
<div class="box">
  <h4 class="mb-3 text-center">Đăng ký tài khoản</h4>

  <?php if ($error): ?>
    <div class="alert alert-danger py-2"><?=h($error)?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success py-2"><?=h($success)?></div>
  <?php endif; ?>

  <form method="post">
    <div class="mb-2">
      <label class="form-label">Họ tên *</label>
      <input type="text" name="hoten" class="form-control" required value="<?=h($_POST['hoten'] ?? '')?>">
    </div>
    <div class="mb-2">
      <label class="form-label">Email *</label>
      <input type="email" name="email" class="form-control" required value="<?=h($_POST['email'] ?? '')?>">
    </div>
    <div class="mb-2">
      <label class="form-label">Số điện thoại</label>
      <input type="text" name="sdt" class="form-control" value="<?=h($_POST['sdt'] ?? '')?>">
    </div>
    <div class="mb-2">
      <label class="form-label">Địa chỉ</label>
      <input type="text" name="diachi" class="form-control" value="<?=h($_POST['diachi'] ?? '')?>">
    </div>
    <div class="mb-2">
      <label class="form-label">Mật khẩu *</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Nhập lại mật khẩu *</label>
      <input type="password" name="password2" class="form-control" required>
    </div>
    <button class="btn btn-primary w-100 mb-2">Đăng ký</button>
    <div class="text-center">
      Đã có tài khoản? <a href="login.php">Đăng nhập</a>
    </div>
  </form>
</div>
</body>
</html>
