<?php
// admin_orders.php - Quản lý đơn hàng
header('Content-Type: text/html; charset=utf-8');

session_start();
require_once 'config.php';

// Auth guard
if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ================== HANDLE POST ACTIONS ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $orderId = (int)($_POST['order_id'] ?? 0);

    // --------- DELETE ONE ORDER ----------
    if ($action === 'delete' && $orderId > 0) {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("DELETE FROM ChiTietDonHang WHERE MaDonHang = :id");
            $stmt->execute([':id' => $orderId]);

            $stmt = $pdo->prepare("DELETE FROM DonHang WHERE MaDonHang = :id");
            $stmt->execute([':id' => $orderId]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
        }
        header('Location: admin_orders.php');
        exit;
    }

    // --------- UPDATE STATUS ONE ORDER ----------
    if ($action === 'update_status' && $orderId > 0) {
        $newStatus = $_POST['status'] ?? '';
        $validStatuses = ['Đang xử lý', 'Đã nhận', 'Đã hủy']; // ✅ FIX: bỏ "ss"

        if (in_array($newStatus, $validStatuses, true)) {
            // Lấy trạng thái hiện tại
            $stmt = $pdo->prepare("SELECT TrangThai FROM DonHang WHERE MaDonHang = :id");
            $stmt->execute([':id' => $orderId]);
            $currentStatus = $stmt->fetchColumn();

            // Nếu chuyển sang Đã hủy và trước đó chưa hủy => hoàn kho
            if ($newStatus === 'Đã hủy' && $currentStatus !== 'Đã hủy') {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("SELECT MaSanPham, SoLuong FROM ChiTietDonHang WHERE MaDonHang = :id");
                    $stmt->execute([':id' => $orderId]);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $stmtUpd = $pdo->prepare("UPDATE SanPham SET SoLuong = SoLuong + :sl WHERE MaSanPham = :id");
                    foreach ($items as $item) {
                        $stmtUpd->execute([
                            ':sl' => (int)$item['SoLuong'],
                            ':id' => (int)$item['MaSanPham']
                        ]);
                    }

                    $stmt = $pdo->prepare("UPDATE DonHang SET TrangThai = :status WHERE MaDonHang = :id");
                    $stmt->execute([':status' => $newStatus, ':id' => $orderId]);

                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollBack();
                }
            } else {
                // Update bình thường
                $stmt = $pdo->prepare("UPDATE DonHang SET TrangThai = :status WHERE MaDonHang = :id");
                $stmt->execute([':status' => $newStatus, ':id' => $orderId]);
            }
        }

        header('Location: admin_orders.php?view=' . $orderId);
        exit;
    }

    // --------- BULK DELETE ----------
    if ($action === 'bulk_delete') {
        $orderIds = $_POST['order_ids'] ?? [];
        $orderIds = array_map('intval', $orderIds);
        $orderIds = array_filter($orderIds);

        if (!empty($orderIds)) {
            $pdo->beginTransaction();
            try {
                $stmtDetails = $pdo->prepare("DELETE FROM ChiTietDonHang WHERE MaDonHang = :id");
                $stmtOrder   = $pdo->prepare("DELETE FROM DonHang WHERE MaDonHang = :id");

                foreach ($orderIds as $id) {
                    $stmtDetails->execute([':id' => $id]);
                    $stmtOrder->execute([':id' => $id]);
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }

        header('Location: admin_orders.php');
        exit;
    }

    // --------- BULK UPDATE STATUS ----------
    if ($action === 'bulk_update_status') {
        $orderIds  = $_POST['order_ids'] ?? [];
        $newStatus = $_POST['bulk_status'] ?? '';
        $validStatuses = ['Đang xử lý', 'Đã nhận', 'Đã hủy'];

        $orderIds = array_map('intval', $orderIds);
        $orderIds = array_filter($orderIds);

        if (!empty($orderIds) && in_array($newStatus, $validStatuses, true)) {
            try {
                foreach ($orderIds as $id) {
                    // kiểm tra trạng thái hiện tại
                    $stmt = $pdo->prepare("SELECT TrangThai FROM DonHang WHERE MaDonHang = :id");
                    $stmt->execute([':id' => $id]);
                    $currentStatus = $stmt->fetchColumn();

                    // nếu chuyển sang Đã hủy và chưa hủy -> hoàn kho
                    if ($newStatus === 'Đã hủy' && $currentStatus !== 'Đã hủy') {
                        $pdo->beginTransaction();
                        try {
                            $stmtItems = $pdo->prepare("SELECT MaSanPham, SoLuong FROM ChiTietDonHang WHERE MaDonHang = :id");
                            $stmtItems->execute([':id' => $id]);
                            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                            $stmtUpd = $pdo->prepare("UPDATE SanPham SET SoLuong = SoLuong + :sl WHERE MaSanPham = :id");
                            foreach ($items as $item) {
                                $stmtUpd->execute([
                                    ':sl' => (int)$item['SoLuong'],
                                    ':id' => (int)$item['MaSanPham']
                                ]);
                            }

                            $stmtStatus = $pdo->prepare("UPDATE DonHang SET TrangThai = :status WHERE MaDonHang = :id");
                            $stmtStatus->execute([':status' => $newStatus, ':id' => $id]);

                            $pdo->commit();
                        } catch (Exception $e) {
                            $pdo->rollBack();
                        }
                    } else {
                        $stmtStatus = $pdo->prepare("UPDATE DonHang SET TrangThai = :status WHERE MaDonHang = :id");
                        $stmtStatus->execute([':status' => $newStatus, ':id' => $id]);
                    }
                }
            } catch (Exception $e) {
                // bỏ qua
            }
        }

        header('Location: admin_orders.php');
        exit;
    }
}

// ================== FETCH 10 ORDERS (đúng yêu cầu) ==================
$sql = "SELECT * FROM DonHang ORDER BY MaDonHang DESC LIMIT 10";
$orders = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// ================== TOP 2 TỔNG TIỀN CAO NHẤT TRONG 10 ĐƠN ==================
$top2 = $pdo->query("
    SELECT *
    FROM (
        SELECT MaDonHang, TenNguoiMua, TongTien, NgayDat, TrangThai
        FROM DonHang
        ORDER BY MaDonHang DESC
        LIMIT 10
    ) t
    ORDER BY TongTien DESC
    LIMIT 2
")->fetchAll(PDO::FETCH_ASSOC);

// ================== FETCH SELECTED ORDER ==================
$selectedOrder       = null;
$selectedOrderItems  = [];

if (!empty($_GET['view'])) {
    $viewId = (int)$_GET['view'];

    $stmt = $pdo->prepare("SELECT * FROM DonHang WHERE MaDonHang = :id");
    $stmt->execute([':id' => $viewId]);
    $selectedOrder = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedOrder) {
        $stmt = $pdo->prepare("
            SELECT ctdh.*, sp.TenSanPham
            FROM ChiTietDonHang ctdh
            JOIN SanPham sp ON ctdh.MaSanPham = sp.MaSanPham
            WHERE ctdh.MaDonHang = :id
        ");
        $stmt->execute([':id' => $viewId]);
        $selectedOrderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Quản lý đơn hàng - Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f5f6fa; }
    .sidebar { width: 260px; position: fixed; top: 0; left: 0; height: 100vh; background: #1e1e2d; color: #fff; padding-top: 20px; }
    .content { margin-left: 260px; padding: 28px; }
    .sidebar a { color:#cfd1ff; opacity:.9; }
    .sidebar a:hover { opacity:1; color:#fff; }
    .order-card {
      background: #fff; border-radius: 8px; padding: 15px;
      margin-bottom: 10px; cursor: pointer;
      border-left: 4px solid #ffc107; transition: 0.2s;
    }
    .order-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .order-card.confirmed { border-left-color: #28a745; }
    .order-card.canceled { border-left-color: #dc3545; }
    .detail-panel {
      background: #fff; padding: 20px; border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .top2-card { background:#fff6d8; border:1px solid #ffe08a; border-radius:10px; padding:14px; }
  </style>
</head>
<body>
  <div class="sidebar">
    <div class="text-center mb-3"><h4>K&K Admin</h4></div>
    <a class="d-block text-decoration-none px-3 py-2" href="admin_categories.php">📂 Quản lý danh mục</a>
    <a class="d-block text-decoration-none px-3 py-2" href="admin_promos.php">🏷️ Quản lý khuyến mãi</a>
    <a class="d-block text-decoration-none px-3 py-2" href="admin.php">📦 Sản phẩm</a>
    <a class="d-block text-decoration-none px-3 py-2" href="admin_vendors.php">🚚 Nhà phân phối</a>
    <a class="d-block text-decoration-none px-3 py-2" href="admin_orders.php">📝 Đơn hàng</a>
    <a class="d-block text-decoration-none px-3 py-2" href="admin_reviews.php">⭐ Đánh giá</a>
  </div>

  <div class="content">
    <h2>Quản lý đơn hàng</h2>

    <!-- TOP 2 -->
    <?php if (!empty($top2)): ?>
      <div class="top2-card mb-3">
        <div class="fw-bold mb-2">Top đơn hàng có tổng tiền cao nhất </div>
        <div class="row g-2">
          <?php foreach ($top2 as $o): ?>
            <div class="col-md-6">
              <div class="p-2 bg-white rounded border">
                <div class="fw-bold">#<?= h($o['MaDonHang']) ?></div>
                <div>Khách: <?= h($o['TenNguoiMua'] ?? '') ?></div>
                <div>
                  Tổng:
                  <span style="color:#d32f2f; font-weight:bold;">
                    ₫<?= number_format((float)$o['TongTien'], 0, ',', '.') ?>
                  </span>
                </div>
                <div style="font-size:13px; color:#666;">
                  Ngày đặt: <?= h($o['NgayDat'] ?? '') ?> <br>
                  Trạng thái: <?= h($o['TrangThai'] ?? '') ?>
                </div>
                <a class="btn btn-sm btn-outline-dark mt-2" href="?view=<?= (int)$o['MaDonHang'] ?>">Xem chi tiết</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
      <div class="alert alert-info">Chưa có đơn hàng nào.</div>
    <?php else: ?>
      <div class="row">
        <!-- Danh sách đơn -->
        <div class="col-md-6">
          <h5>Danh sách đơn hàng (10 đơn gần nhất)</h5>

          <form method="POST" id="bulk-actions-form">
            <input type="hidden" name="action" id="bulk-action-input">

            <div class="d-flex gap-2 mb-2">
              <select name="bulk_status" class="form-select">
                <option value="">-- Chọn trạng thái --</option>
                <option value="Đang xử lý">Đang xử lý</option>
                <option value="Đã nhận">Đã nhận</option>
                <option value="Đã hủy">Đã hủy</option>
              </select>

              <button type="button" class="btn btn-primary" onclick="submitBulkAction('bulk_update_status')">Áp dụng</button>
              <button type="button" class="btn btn-danger" onclick="submitBulkAction('bulk_delete')">Xóa</button>
            </div>

            <?php foreach ($orders as $order): ?>
              <?php
                $status = $order['TrangThai'] ?? '';
                $badge  = 'bg-secondary';
                if ($status === 'Đang xử lý') $badge = 'bg-warning';
                elseif ($status === 'Đã nhận') $badge = 'bg-success';
                elseif ($status === 'Đã hủy') $badge = 'bg-danger';

                $cardClass = '';
                if ($status === 'Đã nhận') $cardClass = 'confirmed';
                if ($status === 'Đã hủy')  $cardClass = 'canceled';
              ?>
              <div class="d-flex align-items-center">
                <input type="checkbox" name="order_ids[]" value="<?= (int)$order['MaDonHang'] ?>" class="form-check-input me-2">

                <a href="?view=<?= (int)$order['MaDonHang'] ?>" style="text-decoration:none; flex-grow: 1;">
                  <div class="order-card <?= h($cardClass) ?>">
                    <div style="font-weight:bold; font-size:16px;">#<?= h($order['MaDonHang']) ?></div>
                    <div>Khách: <?= h($order['TenNguoiMua'] ?? '') ?></div>
                    <div>
                      Tổng:
                      <span style="color:#d32f2f; font-weight:bold;">
                        ₫<?= number_format((float)($order['TongTien'] ?? 0), 0, ',', '.') ?>
                      </span>
                    </div>
                    <div style="font-size:13px; color:#666;">
                      Ngày đặt: <?= h($order['NgayDat'] ?? '') ?><br>
                      Trạng thái: <span class="badge <?=$badge?>"><?=h($status)?></span>
                    </div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </form>

          <script>
            function submitBulkAction(action) {
              const form = document.getElementById('bulk-actions-form');
              const checked = form.querySelectorAll('input[name="order_ids[]"]:checked').length;

              if (checked === 0) {
                alert('Bạn chưa chọn đơn hàng nào.');
                return;
              }

              if (action === 'bulk_update_status') {
                const st = form.querySelector('select[name="bulk_status"]').value;
                if (!st) {
                  alert('Vui lòng chọn trạng thái cần áp dụng.');
                  return;
                }
                if (!confirm('Cập nhật trạng thái cho các đơn đã chọn?')) return;
              }

              if (action === 'bulk_delete') {
                if (!confirm('Bạn có chắc muốn XÓA các đơn đã chọn?')) return;
              }

              document.getElementById('bulk-action-input').value = action;
              form.submit();
            }
          </script>
        </div>

        <!-- Chi tiết đơn -->
        <?php if ($selectedOrder): ?>
          <div class="col-md-6">
            <div class="detail-panel">
              <h5>Chi tiết đơn hàng #<?= h($selectedOrder['MaDonHang']) ?></h5>
              <hr>

              <!-- Thông tin khách hàng -->
              <h6 class="mt-3">👤 Thông tin khách hàng</h6>
              <table class="table table-sm" style="background:none; border:none;">
                <tr>
                  <td><strong>Tên:</strong></td>
                  <td><?= h($selectedOrder['TenNguoiMua'] ?? '') ?></td>
                </tr>
                <tr>
                  <td><strong>SĐT:</strong></td>
                  <td><?= h($selectedOrder['SoDienThoai'] ?? '') ?></td>
                </tr>
                <tr>
                  <td><strong>Địa chỉ:</strong></td>
                  <td><?= h($selectedOrder['DiaChi'] ?? '') ?></td>
                </tr>
              </table>
              <hr>

              <!-- Danh sách sản phẩm -->
              <h6>📦 Sản phẩm</h6>
              <table class="table table-sm">
                <thead style="background:#f5f5f5;">
                  <tr>
                    <th>Sản phẩm</th>
                    <th class="text-center">SL</th>
                    <th class="text-end">Đơn giá</th>
                    <th class="text-end">Thành tiền</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $totalAmount = 0; ?>
                  <?php foreach ($selectedOrderItems as $item): ?>
                    <?php
                      $unitPrice = ($item['DonGiaSauGiam'] !== null)
                        ? (float)$item['DonGiaSauGiam']
                        : (float)$item['DonGia'];
                      $subTotal  = $unitPrice * (int)$item['SoLuong'];
                      $totalAmount += $subTotal;
                    ?>
                    <tr>
                      <td><?= h($item['TenSanPham']) ?></td>
                      <td class="text-center"><?= h($item['SoLuong']) ?></td>
                      <td class="text-end"><?= number_format($unitPrice, 0, ',', '.') ?>₫</td>
                      <td class="text-end"><?= number_format($subTotal, 0, ',', '.') ?>₫</td>
                    </tr>
                  <?php endforeach; ?>

                  <tr style="font-weight:bold; background:#f5f5f5;">
                    <td colspan="2">Tổng cộng</td>
                    <td class="text-end" colspan="2">
                      <?= number_format($totalAmount, 0, ',', '.') ?>₫
                    </td>
                  </tr>

                  <tr>
                    <td colspan="2"><strong>Phương thức thanh toán</strong></td>
                    <td colspan="2" class="text-end">
                      <?= h($selectedOrder['PhuongThucThanhToan'] ?? 'COD') ?>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="2"><strong>Trạng thái thanh toán</strong></td>
                    <td colspan="2" class="text-end">
                      <?= h($selectedOrder['TrangThaiThanhToan'] ?? 'Chưa thanh toán') ?>
                    </td>
                  </tr>
                </tbody>
              </table>
              <hr>

              <!-- Trạng thái + nút hành động -->
              <p>
                <strong>Trạng thái hiện tại:</strong>
                <?php
                  $status = $selectedOrder['TrangThai'] ?? '';
                  $badge  = 'bg-secondary';
                  if ($status === 'Đang xử lý') $badge = 'bg-warning';
                  elseif ($status === 'Đã nhận') $badge = 'bg-success';
                  elseif ($status === 'Đã hủy') $badge = 'bg-danger';
                ?>
                <span class="badge <?=$badge?>"><?=h($status)?></span>
              </p>

              <div class="d-flex flex-column gap-2">
                <form method="POST" class="d-flex gap-2">
                  <input type="hidden" name="action" value="update_status">
                  <input type="hidden" name="order_id" value="<?= (int)$selectedOrder['MaDonHang'] ?>">

                  <select name="status" class="form-select">
                    <option value="Đang xử lý" <?= ($selectedOrder['TrangThai'] ?? '') === 'Đang xử lý' ? 'selected' : '' ?>>Đang xử lý</option>
                    <option value="Đã nhận" <?= ($selectedOrder['TrangThai'] ?? '') === 'Đã nhận' ? 'selected' : '' ?>>Đã nhận</option>
                    <option value="Đã hủy" <?= ($selectedOrder['TrangThai'] ?? '') === 'Đã hủy' ? 'selected' : '' ?>>Đã hủy</option>
                  </select>

                  <button type="submit" class="btn btn-primary">Cập nhật</button>
                </form>

                <hr>

                <form method="POST" onsubmit="return confirm('Bạn có chắc chắn muốn XÓA vĩnh viễn đơn hàng này không? Hành động này không thể hoàn tác.');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="order_id" value="<?= (int)$selectedOrder['MaDonHang'] ?>">
                  <button type="submit" class="btn btn-danger w-100">Xóa đơn hàng</button>
                </form>
              </div>

            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>