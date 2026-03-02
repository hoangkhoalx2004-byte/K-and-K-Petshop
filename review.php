<?php
session_start();
require_once 'config.php';

if (empty($_SESSION['user_id'])) {
  header("Location: login.php"); exit;
}

$uid = (int)$_SESSION['user_id'];
$maSP = (int)($_GET['sp'] ?? 0);
if ($maSP <= 0) die("Sản phẩm không hợp lệ");

// 1. Kiểm tra đã mua + đơn đã nhận chưa
$st = $pdo->prepare("
  SELECT 1
  FROM DonHang dh
  JOIN ChiTietDonHang ct ON dh.MaDonHang = ct.MaDonHang
  WHERE dh.MaNguoiDung = ?
    AND ct.MaSanPham = ?
    AND dh.TrangThai = 'Đã nhận'
  LIMIT 1
");
$st->execute([$uid, $maSP]);
if (!$st->fetch()) {
  die("Bạn chưa nhận sản phẩm này nên không thể đánh giá.");
}

// 2. Kiểm tra đã đánh giá chưa
$st = $pdo->prepare("
  SELECT 1 FROM DanhGia
  WHERE MaNguoiDung = ? AND MaSanPham = ?
  LIMIT 1
");
$st->execute([$uid, $maSP]);
if ($st->fetch()) {
  die("Bạn đã đánh giá sản phẩm này rồi.");
}

// 3. Lưu đánh giá
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $diem = (int)($_POST['DiemDanhGia'] ?? 0);
  $noiDung = trim($_POST['NoiDung'] ?? '');

  if ($diem < 1 || $diem > 5) {
    $err = "Vui lòng chọn số sao từ 1 đến 5.";
  } else {
    $st = $pdo->prepare("
      INSERT INTO DanhGia (MaNguoiDung, MaSanPham, DiemDanhGia, NoiDung)
      VALUES (?, ?, ?, ?)
    ");
    $st->execute([$uid, $maSP, $diem, $noiDung]);
    header("Location: view_orders.php?reviewed=1");
    exit;
  }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đánh giá sản phẩm</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width:600px">
  <div class="card shadow-sm">
    <div class="card-body">
      <h4 class="mb-3">⭐ Đánh giá sản phẩm</h4>

      <?php if (!empty($err)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label">Số sao</label>
          <select name="DiemDanhGia" class="form-select" required>
            <option value="">-- Chọn --</option>
            <?php for ($i=5;$i>=1;$i--): ?>
              <option value="<?= $i ?>"><?= $i ?> ⭐</option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Bình luận</label>
          <textarea name="NoiDung" class="form-control" rows="4"
            placeholder="Cảm nhận của bạn về sản phẩm..."></textarea>
        </div>

        <button class="btn btn-primary">Gửi đánh giá</button>
        <a href="view_orders.php" class="btn btn-secondary">Quay lại</a>
      </form>
    </div>
  </div>
</div>
</body>
</html>
