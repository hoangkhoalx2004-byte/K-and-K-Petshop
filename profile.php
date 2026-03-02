<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';

if (empty($_SESSION['user_id'])) {
  header("Location: login.php?redirect=profile.php");
  exit;
}

$uid = (int)$_SESSION['user_id'];
$msg = '';

// Lấy thông tin user
$stmt = $pdo->prepare("SELECT * FROM NguoiDung WHERE MaNguoiDung=? LIMIT 1");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  // user không tồn tại nữa thì logout
  header("Location: logout.php");
  exit;
}

// Update thông tin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $hoTen  = trim($_POST['HoTen'] ?? '');
  $sdt    = trim($_POST['SoDienThoai'] ?? '');
  $diaChi = trim($_POST['DiaChi'] ?? '');

  if ($hoTen === '' || $sdt === '' || $diaChi === '') {
    $msg = '❌ Vui lòng nhập đầy đủ thông tin.';
  } else {
    $st = $pdo->prepare("UPDATE NguoiDung SET HoTen=?, SoDienThoai=?, DiaChi=? WHERE MaNguoiDung=?");
    $st->execute([$hoTen, $sdt, $diaChi, $uid]);

    $_SESSION['user_name'] = $hoTen; // cập nhật lại tên trên navbar

    // load lại user sau khi update
    $stmt->execute([$uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $msg = '✅ Cập nhật thành công.';
  }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Tài khoản của tôi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width:650px">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-3">👤 Tài khoản của tôi</h4>

      <?php if ($msg): ?>
        <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label">Họ tên</label>
          <input class="form-control" name="HoTen" required value="<?= htmlspecialchars($user['HoTen']) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Email</label>
          <input class="form-control" value="<?= htmlspecialchars($user['Email']) ?>" readonly>
        </div>

        <div class="mb-3">
          <label class="form-label">Số điện thoại</label>
          <input class="form-control" name="SoDienThoai" required value="<?= htmlspecialchars($user['SoDienThoai']) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Địa chỉ</label>
          <input class="form-control" name="DiaChi" required value="<?= htmlspecialchars($user['DiaChi']) ?>">
        </div>

        <button class="btn btn-primary">Lưu</button>
        <a class="btn btn-secondary" href="view_orders.php">Đơn hàng của tôi</a>
      </form>
    </div>
  </div>
</div>
</body>
</html>
