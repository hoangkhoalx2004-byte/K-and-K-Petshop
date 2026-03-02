<?php
// checkout.php - create an order from session cart
session_start();
require_once 'config.php';

// Flash helper
function add_flash($msg, $type = 'info') {
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) $_SESSION['flash'] = [];
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

if (empty($_SESSION['user_id'])) {
    add_flash('Vui lòng đăng nhập để thanh toán.', 'warning');
header('Location: login.php?redirect=cart.php'); exit;

}

// =================== LẤY THÔNG TIN KHÁCH HÀNG ===================
$ten    = trim($_POST['TenNguoiMua'] ?? '');   // name="TenNguoiMua"
$diachi = trim($_POST['DiaChi'] ?? '');        // name="DiaChi"
$sdt    = trim($_POST['SoDienThoai'] ?? '');   // name="SoDienThoai"
$pttt = trim($_POST['PhuongThucThanhToan'] ?? 'COD');
if ($pttt === '') $pttt = 'COD';

// set trạng thái thanh toán mặc định theo phương thức
$tttt = ($pttt === 'COD') ? 'Chưa thanh toán' : 'Chờ xác nhận';



// Nếu thiếu thông tin thì lấy từ hồ sơ NguoiDung
if (empty($ten) || empty($diachi) || empty($sdt)) {
    $uid = (int)$_SESSION['user_id'];
    $st = $pdo->prepare("SELECT HoTen, SoDienThoai, DiaChi FROM NguoiDung WHERE MaNguoiDung=? LIMIT 1");
    $st->execute([$uid]);
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if (empty($ten))    $ten    = trim($u['HoTen'] ?? '');
    if (empty($sdt))    $sdt    = trim($u['SoDienThoai'] ?? '');
    if (empty($diachi)) $diachi = trim($u['DiaChi'] ?? '');
}

// Sau khi fallback mà vẫn thiếu thì báo lỗi
if (empty($ten) || empty($diachi) || empty($sdt)) {
    add_flash('Vui lòng cập nhật đủ Họ tên / SĐT / Địa chỉ trong Tài khoản trước khi thanh toán.', 'warning');
    header('Location: profile.php'); exit;
}

// =============

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    add_flash('Giỏ hàng trống.', 'info');
    header('Location: cart.php'); exit;
}

// Lấy thông tin sản phẩm và kiểm tra tồn kho
$ids = array_keys($cart);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("
    SELECT MaSanPham, TenSanPham, Gia, PhanTramGiam, SoLuong
    FROM SanPham sp
    LEFT JOIN KhuyenMai km ON sp.MaKhuyenMai = km.MaKhuyenMai
    WHERE MaSanPham IN ($placeholders)
");
$stmt->execute($ids);
$rows = $stmt->fetchAll();
$map = [];
foreach ($rows as $r) $map[$r['MaSanPham']] = $r;

$total = 0;
foreach ($cart as $id => $q) {
    if (!isset($map[$id])) {
        add_flash('Một sản phẩm trong giỏ không tồn tại.', 'danger');
        header('Location: cart.php'); exit;
    }
    if ($q > (int)$map[$id]['SoLuong']) {
        add_flash('Số lượng trong giỏ vượt tồn kho cho sản phẩm: ' . $map[$id]['TenSanPham'], 'warning');
        header('Location: cart.php'); exit;
    }
    $gia  = (float)$map[$id]['Gia'];
    $giam = $map[$id]['PhanTramGiam'] !== null ? (float)$map[$id]['PhanTramGiam'] : 0;
    $giaSau = $giam > 0 ? $gia * (1 - $giam/100) : $gia;
    $total += $giaSau * $q;
}

// =================== TẠO ĐƠN HÀNG ===================
$pdo->beginTransaction();
// Tạo mã đơn theo user: 1,2,3...
$stNo = $pdo->prepare("
  SELECT COALESCE(MAX(MaDonHangUser),0) + 1
  FROM DonHang
  WHERE MaNguoiDung = ?
  FOR UPDATE
");
$stNo->execute([$_SESSION['user_id']]);
$nextNo = (int)$stNo->fetchColumn();

try {
    // lưu thêm TenNguoiMua, DiaChi, SoDienThoai
   $stmt = $pdo->prepare("
  INSERT INTO DonHang (
    MaNguoiDung, TongTien, TrangThai,
    TenNguoiMua, DiaChi, SoDienThoai, MaDonHangUser,
    PhuongThucThanhToan, TrangThaiThanhToan
  )
  VALUES (
    :mnd, :tongtien, 'Đang xử lý',
    :ten, :diachi, :sdt, :no,
    :pttt, :tttt
  )
");
$stmt->execute([
  ':mnd'      => (int)$_SESSION['user_id'],
  ':tongtien' => $total,
  ':ten'      => $ten,
  ':diachi'   => $diachi,
  ':sdt'      => $sdt,
  ':no'       => $nextNo,
  ':pttt'     => $pttt,
  ':tttt'     => $tttt
]);

    $maDonHang = $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("
        INSERT INTO ChiTietDonHang (MaDonHang, MaSanPham, SoLuong, DonGia, DonGiaSauGiam)
        VALUES (:mhd, :msp, :sl, :dongia, :dongiasg)
    ");
    $stmtUpd = $pdo->prepare("
        UPDATE SanPham SET SoLuong = SoLuong - :sl
        WHERE MaSanPham = :id AND SoLuong >= :sl
    ");

    foreach ($cart as $id => $q) {
        $r = $map[$id];
        $gia  = (float)$r['Gia'];
        $giam = $r['PhanTramGiam'] !== null ? (float)$r['PhanTramGiam'] : 0;
        $giaSau = $giam > 0 ? $gia * (1 - $giam/100) : $gia;

        $stmtItem->execute([
            ':mhd'      => $maDonHang,
            ':msp'      => $id,
            ':sl'       => $q,
            ':dongia'   => $gia,
            ':dongiasg' => $giaSau,
        ]);

        $stmtUpd->execute([':sl' => $q, ':id' => $id]);
        if ($stmtUpd->rowCount() === 0) {
            throw new Exception('Không thể cập nhật tồn kho cho sản phẩm: ' . $r['TenSanPham']);
        }
    }

    $pdo->commit();
    unset($_SESSION['cart']);
    add_flash('Thanh toán thành công. Mã đơn hàng: #' . $maDonHang, 'success');
    header('Location: view_orders.php'); exit;

} catch (Exception $e) {
    $pdo->rollBack();
    add_flash('Lỗi khi tạo đơn: ' . $e->getMessage(), 'danger');
    header('Location: cart.php'); exit;
}
?>
