<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';

if (empty($_SESSION['user_id'])) {
  header('Location: login.php?redirect=view_orders.php');
  exit;
}

$userId = (int)$_SESSION['user_id'];

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ================== XÓA ĐƠN (chỉ đơn Đã hủy) ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && !empty($_POST['orders'])) {
  $ids = array_map('intval', $_POST['orders']);
  if (!empty($ids)) {
    $ph = implode(',', array_fill(0, count($ids), '?'));

    // Chỉ lấy những đơn thuộc user + trạng thái Đã hủy
    $sql = "SELECT MaDonHang
            FROM DonHang
            WHERE MaNguoiDung = ?
              AND TrangThai = 'Đã hủy'
              AND MaDonHang IN ($ph)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$userId], $ids));
    $canDelete = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($canDelete)) {
      $pdo->beginTransaction();
      try {
        $ph2 = implode(',', array_fill(0, count($canDelete), '?'));

        $st1 = $pdo->prepare("DELETE FROM ChiTietDonHang WHERE MaDonHang IN ($ph2)");
        $st1->execute($canDelete);

        $st2 = $pdo->prepare("DELETE FROM DonHang WHERE MaDonHang IN ($ph2)");
        $st2->execute($canDelete);

        $pdo->commit();
      } catch (Exception $e) {
        $pdo->rollBack();
      }
    }
  }
  header('Location: view_orders.php');
  exit;
}

// ================== LẤY DANH SÁCH ĐƠN ==================
$stmt = $pdo->prepare("
  SELECT *
  FROM DonHang
  WHERE MaNguoiDung = :mnd
  ORDER BY MaDonHang DESC
");
$stmt->execute([':mnd' => $userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================== LẤY CHI TIẾT SẢN PHẨM CHO CÁC ĐƠN ==================
$orderItems = []; // [MaDonHang => [items...]]
$orderIds = [];

if (!empty($orders)) {
  $orderIds = array_map('intval', array_column($orders, 'MaDonHang'));
  $ph = implode(',', array_fill(0, count($orderIds), '?'));

  $st = $pdo->prepare("
    SELECT ct.MaDonHang, ct.MaSanPham, ct.SoLuong, sp.TenSanPham
    FROM ChiTietDonHang ct
    JOIN SanPham sp ON sp.MaSanPham = ct.MaSanPham
    WHERE ct.MaDonHang IN ($ph)
    ORDER BY ct.MaDonHang DESC
  ");
  $st->execute($orderIds);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $r) {
    $orderItems[(int)$r['MaDonHang']][] = $r;
  }
}

// ================== LẤY TẤT CẢ ĐÁNH GIÁ CỦA USER (tối ưu) ==================
$ratedMap = []; // [MaSanPham => true]
$st = $pdo->prepare("SELECT MaSanPham FROM DanhGia WHERE MaNguoiDung=?");
$st->execute([$userId]);
while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
  $ratedMap[(int)$row['MaSanPham']] = true;
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đơn hàng của tôi - K&K PetShop</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container" style="max-width:950px; margin-top:40px;">
  <h4 class="mb-3">Đơn hàng của tôi</h4>
  <div class="d-flex gap-2 mb-3">
    <a href="index.php" class="btn btn-sm btn-outline-secondary">&laquo; Về trang chủ</a>
    <a href="profile.php" class="btn btn-sm btn-outline-primary">👤 Tài khoản</a>
  </div>

  <?php if (empty($orders)): ?>
    <div class="alert alert-info">Bạn chưa có đơn hàng nào.</div>
  <?php else: ?>
    <form method="post">
      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead class="table-dark">
            <tr>
              <th style="width:40px;">
                <input type="checkbox" id="checkAll">
              </th>
              <th>Mã đơn</th>
              <th>Ngày đặt</th>
              <th class="text-end">Tổng tiền</th>
              <th>Trạng thái</th>
              <th class="text-end" style="width:170px;">Chi tiết</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($orders as $o): ?>
              <?php
                $status = $o['TrangThai'];
                $badge  = 'bg-secondary';
                if ($status === 'Đang xử lý') $badge = 'bg-warning';
                elseif ($status === 'Đã nhận') $badge = 'bg-success';
                elseif ($status === 'Đã hủy') $badge = 'bg-danger';
              ?>
              <tr>
                <td style="width:40px;">
                  <?php if ($o['TrangThai'] === 'Đã hủy'): ?>
                    <input type="checkbox" name="orders[]" value="<?=h($o['MaDonHang'])?>">
                  <?php endif; ?>
                </td>
            <td>#<?=h($o['MaDonHangUser'])?></td>

                <td><?=h($o['NgayDat'])?></td>
                <td class="text-end"><?=number_format($o['TongTien'],0,',','.')?>đ</td>
                <td><span class="badge <?=$badge?>"><?=h($status)?></span></td>
                <td class="text-end" style="white-space:nowrap;">
                  <button type="button" class="btn btn-sm btn-outline-primary"
                          data-bs-toggle="modal"
                          data-bs-target="#modalOrder<?=h($o['MaDonHang'])?>">
                    Xem sp
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-2">
        <button type="submit" name="delete" class="btn btn-danger btn-sm"
                onclick="return confirm('Xóa các đơn đã chọn? (Chỉ xóa được đơn trạng thái Đã hủy)');">
          Xóa đơn đã chọn
        </button>
        <div class="form-text">
          * Chỉ những đơn có trạng thái <strong>Đã hủy</strong> mới có thể xóa.
        </div>
      </div>
    </form>
  <?php endif; ?>
</div>

<!-- MODAL: Sản phẩm theo từng đơn + nút đánh giá -->
<?php foreach ($orders as $o): 
  $oid = (int)$o['MaDonHang'];
  $items = $orderItems[$oid] ?? [];
  $daNhan = ($o['TrangThai'] === 'Đã nhận');
?>
<div class="modal fade" id="modalOrder<?=h($oid)?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
       <h5 class="modal-title">Đơn #<?=h($o['MaDonHangUser'])?> - Danh sách sản phẩm</h5>

        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <?php if (empty($items)): ?>
          <div class="alert alert-info">Không có sản phẩm trong đơn.</div>
        <?php else: ?>
          <table class="table table-striped">
            <thead>
              <tr>
                <th>Sản phẩm</th>
                <th style="width:90px;">SL</th>
                <th style="width:200px;">Đánh giá</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $sp): 
                $msp = (int)$sp['MaSanPham'];
                $rated = !empty($ratedMap[$msp]);
              ?>
                <tr>
                  <td><?=h($sp['TenSanPham'])?></td>
                  <td><?=h($sp['SoLuong'])?></td>
                  <td>
                    <?php if ($daNhan): ?>
                      <?php if (!$rated): ?>
                        <a class="btn btn-sm btn-outline-primary"
                           href="review.php?sp=<?=h($msp)?>">
                          ⭐ Đánh giá
                        </a>
                      <?php else: ?>
                        <span class="text-success">✔ Đã đánh giá</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted">Chỉ đánh giá khi đơn “Đã nhận”</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script>
// tick chọn tất cả (chỉ các checkbox đơn đã hủy hiện ra)
document.getElementById('checkAll')?.addEventListener('change', function(){
  const checked = this.checked;
  document.querySelectorAll('input[name="orders[]"]').forEach(cb => cb.checked = checked);
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
