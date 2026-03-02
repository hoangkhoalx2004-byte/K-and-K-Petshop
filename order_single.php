<?php
session_start();
require_once "config.php"; // $pdh (PDO)

$maSP = (int)($_POST['MaSanPham'] ?? 0);
$sl   = (int)($_POST['SoLuong'] ?? 0);

$hoTen = trim($_POST['HoTen'] ?? '');
$sdt   = trim($_POST['SoDienThoai'] ?? '');
$diaChi= trim($_POST['DiaChi'] ?? '');
$ghiChu= trim($_POST['GhiChu'] ?? '');

$maNguoiDung = isset($_SESSION['user']['MaNguoiDung']) ? (int)$_SESSION['user']['MaNguoiDung'] : null;

// Validate cơ bản
if ($maSP <= 0) {
  header("Location: index.php?err=invalid_product");
  exit;
}
if ($sl < 20) {
  header("Location: index.php?err=bulk_min_20");
  exit;
}
if ($hoTen === '' || $sdt === '' || $diaChi === '') {
  header("Location: index.php?err=missing_info");
  exit;
}

// (Tuỳ bạn) validate số điện thoại đơn giản
if (!preg_match('/^[0-9+\s\-]{8,20}$/', $sdt)) {
  header("Location: index.php?err=invalid_phone");
  exit;
}

try {
  // check sản phẩm tồn tại
  $st = $pdh->prepare("SELECT MaSanPham FROM SanPham WHERE MaSanPham=?");
  $st->execute([$maSP]);
  if (!$st->fetch()) {
    header("Location: index.php?err=notfound");
    exit;
  }

  // Lưu yêu cầu
  $st = $pdh->prepare("
    INSERT INTO YeuCau_SoLuongLon (MaNguoiDung, MaSanPham, SoLuong, HoTen, SoDienThoai, DiaChi, GhiChu)
    VALUES (:uid, :sp, :sl, :ten, :sdt, :dc, :gc)
  ");
  $st->execute([
    ':uid' => $maNguoiDung,
    ':sp'  => $maSP,
    ':sl'  => $sl,
    ':ten' => $hoTen,
    ':sdt' => $sdt,
    ':dc'  => $diaChi,
    ':gc'  => $ghiChu
  ]);

  // Thông báo cho admin: cách đơn giản nhất là lưu DB (admin vào trang quản trị xem)
  // Nếu bạn có hệ thống "thông báo admin" riêng thì insert thêm ở đây.

  header("Location: index.php?bulk_sent=1");
  exit;

} catch (Exception $e) {
  header("Location: index.php?err=" . urlencode("Lỗi gửi yêu cầu, thử lại!"));
  exit;
}
