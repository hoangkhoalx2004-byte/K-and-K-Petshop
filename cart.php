<?php
// cart.php - simple cart handler + display
session_start();
require_once 'config.php';

// Helper escape
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Flash helper
function add_flash($msg, $type = 'info') {
  if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) $_SESSION['flash'] = [];
  $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

// Detect AJAX request via explicit param or header
function is_ajax() {
  if (!empty($_POST['ajax']) || !empty($_GET['ajax'])) return true;
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') return true;
  return false;
}

// Handle POST actions: add (default), update, remove
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? 'add';
  $maSP = (int)($_POST['MaSanPham'] ?? 0);
  $qty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
  if ($qty < 1) $qty = 1;

  // Lấy sản phẩm
  $stmt = $pdo->prepare("SELECT MaSanPham, TenSanPham, Gia, SoLuong FROM SanPham WHERE MaSanPham = :id");
  $stmt->execute([':id' => $maSP]);
  $product = $stmt->fetch();
  if (!$product) {
    if (is_ajax()) {
      header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'Sản phẩm không tồn tại']); exit;
    }
    add_flash('Sản phẩm không tồn tại.', 'danger');
    header('Location: cart.php'); exit;
  }

  // Prepare session cart
  if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) $_SESSION['cart'] = [];

  if ($action === 'add') {
    // Kiểm tra tồn kho
    if ($qty > (int)$product['SoLuong']) {
      if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'Số lượng vượt tồn kho']); exit; }
      add_flash('Số lượng yêu cầu vượt quá tồn kho.', 'warning'); header('Location: cart.php'); exit;
    }
    if (isset($_SESSION['cart'][$maSP])) {
      $newQty = $_SESSION['cart'][$maSP] + $qty;
      $_SESSION['cart'][$maSP] = min($newQty, (int)$product['SoLuong']);
    } else {
      $_SESSION['cart'][$maSP] = $qty;
    }
    if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>'Đã thêm vào giỏ hàng']); exit; }
    add_flash('Đã thêm vào giỏ hàng.', 'success'); header('Location: cart.php'); exit;
  }

  if ($action === 'update') {
    // Update quantity
    if ($qty > (int)$product['SoLuong']) {
      if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'Số lượng vượt tồn kho, hãy liên hệ admin hoặc đặt số lượng lớn']); exit; }
      add_flash('Số lượng yêu cầu vượt quá tồn kho.', 'warning'); header('Location: cart.php'); exit;
    }
    if ($qty === 0) {
      unset($_SESSION['cart'][$maSP]);
    } else {
      $_SESSION['cart'][$maSP] = $qty;
    }
    if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>'Cập nhật giỏ hàng thành công']); exit; }
    add_flash('Cập nhật giỏ hàng thành công.', 'success'); header('Location: cart.php'); exit;
  }

  if ($action === 'remove') {
    unset($_SESSION['cart'][$maSP]);
    if (is_ajax()) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>'Đã xóa sản phẩm khỏi giỏ hàng']); exit; }
    add_flash('Đã xóa sản phẩm khỏi giỏ hàng.', 'info'); header('Location: cart.php'); exit;
  }
}

// Hiển thị giỏ hàng
$cart = $_SESSION['cart'] ?? [];
$items = [];
$total = 0;
if (!empty($cart)) {
  $ids = array_keys($cart);
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $pdo->prepare("SELECT MaSanPham, TenSanPham, Gia, PhanTramGiam, SoLuong FROM SanPham sp LEFT JOIN KhuyenMai km ON sp.MaKhuyenMai = km.MaKhuyenMai WHERE MaSanPham IN ($placeholders)");
  $stmt->execute($ids);
  $rows = $stmt->fetchAll();
  $map = [];
  foreach ($rows as $r) $map[$r['MaSanPham']] = $r;

  foreach ($cart as $id => $q) {
    if (!isset($map[$id])) continue;
    $r = $map[$id];
    $gia = (float)$r['Gia'];
    $giam = $r['PhanTramGiam'] !== null ? (float)$r['PhanTramGiam'] : 0;
    $giaSau = $gia; if ($giam > 0) $giaSau = $gia * (1 - $giam/100);
    // Clamp quantity to current stock
    if ($q > (int)$r['SoLuong']) { $_SESSION['cart'][$id] = (int)$r['SoLuong']; $q = (int)$r['SoLuong']; }
    $sub = $giaSau * $q;
    $total += $sub;
    $items[] = [ 'id' => $id, 'name' => $r['TenSanPham'], 'qty' => $q, 'price' => $giaSau, 'sub' => $sub, 'stock' => (int)$r['SoLuong'] ];
  }
}
// Auto-fill thông tin thanh toán từ NguoiDung
$shipName = '';
$shipPhone = '';
$shipAddress = '';

if (!empty($_SESSION['user_id'])) {
  $uid = (int)$_SESSION['user_id'];
  $st = $pdo->prepare("SELECT HoTen, SoDienThoai, DiaChi FROM NguoiDung WHERE MaNguoiDung=? LIMIT 1");
  $st->execute([$uid]);
  if ($u = $st->fetch(PDO::FETCH_ASSOC)) {
    $shipName = $u['HoTen'] ?? '';
    $shipPhone = $u['SoDienThoai'] ?? '';
    $shipAddress = $u['DiaChi'] ?? '';
  }
}

?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Giỏ hàng</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script>
    // Simple JS for AJAX update/remove
    async function cartAction(action, id, qty=1) {
      const fd = new FormData();
      fd.append('action', action);
      fd.append('MaSanPham', id);
      fd.append('quantity', qty);
      fd.append('ajax', '1');
      const res = await fetch('cart.php', { method: 'POST', body: fd });
      const json = await res.json();
      alert(json.msg || (json.ok? 'OK':'Lỗi'));
      if (json.ok) location.reload();
    }
  </script>
</head>
<body class="bg-light">
<div class="container" style="max-width:900px; margin-top:40px;">
  <h3>Giỏ hàng của bạn</h3>

  <?php if (!empty($_SESSION['flash'])): ?>
    <?php foreach ($_SESSION['flash'] as $f): ?>
      <div class="alert alert-<?=htmlspecialchars($f['type'])?>"><?=(htmlspecialchars($f['msg']))?></div>
    <?php endforeach; unset($_SESSION['flash']); ?>
  <?php endif; ?>

  <?php if (empty($items)): ?>
    <div class="alert alert-info">Giỏ hàng trống. Quay lại <a href="index.php">mua sắm</a>.</div>
  <?php else: ?>
    <table class="table table-striped">
      <thead>
        <tr><th>Sản phẩm</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?=h($it['name'])?></td>
            <td>
              <div class="d-flex" style="gap:8px; align-items:center;">
                <input type="number" value="<?=h($it['qty'])?>" min="1" max="<?=h($it['stock'])?>" id="qty-<?=h($it['id'])?>" style="width:80px;" />
                <button class="btn btn-sm btn-primary" onclick="cartAction('update', <?=json_encode($it['id'])?>, document.getElementById('qty-<?=h($it['id'])?>').value)">Cập nhật</button>
                <button class="btn btn-sm btn-danger" onclick="if(confirm('Xác nhận xóa?')) cartAction('remove', <?=json_encode($it['id'])?>)">Xóa</button>
              </div>
            </td>
            <td><?=number_format($it['price'],0,',','.')?>đ</td>
            <td><?=number_format($it['sub'],0,',','.')?>đ</td>
            <td style="width:120px;"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="text-end"><strong>Tổng: <?=number_format($total,0,',','.')?>đ</strong></div>
    <div class="mt-3 d-flex gap-2">
      <a href="index.php" class="btn btn-secondary">Tiếp tục mua sắm</a>
     <?php if (empty($_SESSION['user_id'])): ?>
  <a class="btn btn-primary" href="login.php?redirect=cart.php">Thanh toán</a>
<?php else: ?>
  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#checkoutModal">Thanh toán</button>
<?php endif; ?>

    </div>
  <?php endif; ?>
</div>

<!-- Checkout Modal -->
<div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Thông tin thanh toán</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form id="checkoutForm" method="POST" action="checkout.php">
        <div class="modal-body">
          <div class="mb-3">
            <label for="custName" class="form-label">Họ và tên:</label>
           <input type="text" class="form-control" id="ten" name="TenNguoiMua" required
       value="<?= h($_POST['TenNguoiMua'] ?? $shipName) ?>">

          </div>
          <div class="mb-3">
            <label for="custAddress" class="form-label">Địa chỉ:</label>
          <textarea class="form-control" id="diachi" name="DiaChi" rows="3" required><?= h($_POST['DiaChi'] ?? $shipAddress) ?></textarea>

          </div>
          <div class="mb-3">
            <label for="custPhone" class="form-label">Số điện thoại:</label>
         <input type="tel" class="form-control" id="sdt" name="SoDienThoai" required
       value="<?= h($_POST['SoDienThoai'] ?? $shipPhone) ?>">

          </div>
            <div class="mb-3">
  <label class="form-label">Phương thức thanh toán:</label>
  <select class="form-select" name="PhuongThucThanhToan" required>
    <option value="COD">COD - Thanh toán khi nhận hàng</option>
    <option value="Chuyển khoản">Chuyển khoản</option>
  </select>
</div>

        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
          <button type="submit" class="btn btn-primary">Đặt hàng</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
