<?php
// admin_promos.php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';

if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $ten  = trim($_POST['TenKhuyenMai'] ?? '');
        $ptg  = (float)($_POST['PhanTramGiam'] ?? 0);
        if ($ten !== '' && $ptg >= 0 && $ptg <= 100) {
            $sql = "INSERT INTO KhuyenMai (TenKhuyenMai, PhanTramGiam) VALUES (?, ?)";
            $pdo->prepare($sql)->execute([$ten, $ptg]);
        }
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'edit' && !empty($_POST['MaKhuyenMai'])) {
        $id   = (int)$_POST['MaKhuyenMai'];
        $ten  = trim($_POST['TenKhuyenMai'] ?? '');
        $ptg  = (float)($_POST['PhanTramGiam'] ?? 0);
        if ($ten !== '' && $ptg >= 0 && $ptg <= 100) {
            $sql = "UPDATE KhuyenMai SET TenKhuyenMai=?, PhanTramGiam=? WHERE MaKhuyenMai=?";
            $pdo->prepare($sql)->execute([$ten, $ptg, $id]);
        }
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'delete' && !empty($_POST['MaKhuyenMai'])) {
        $id = (int)$_POST['MaKhuyenMai'];
        $pdo->prepare("DELETE FROM KhuyenMai WHERE MaKhuyenMai=?")->execute([$id]);
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }
}

$promos = $pdo->query("SELECT * FROM KhuyenMai ORDER BY TenKhuyenMai")->fetchAll();
// Lấy danh sách sản phẩm đang nhận khuyến mãi, gom theo MaKhuyenMai
$productsByPromo = [];
$stmtProd = $pdo->query("
    SELECT MaKhuyenMai, TenSanPham
    FROM SanPham
    WHERE MaKhuyenMai IS NOT NULL
    ORDER BY TenSanPham
");

foreach ($stmtProd as $row) {
    $makm = $row['MaKhuyenMai'];
    if (!isset($productsByPromo[$makm])) {
        $productsByPromo[$makm] = [];
    }
    $productsByPromo[$makm][] = $row['TenSanPham'];
}

?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Admin - Quản lý khuyến mãi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f5f6fa; }
    .sidebar { width:260px; position:fixed; top:0; left:0; height:100vh; background:#1e1e2d; color:#fff; padding-top:20px; }
    .content { margin-left:260px; padding:28px; }
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
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">Quản lý khuyến mãi</h2>
      <a href="admin.php" class="btn btn-outline-secondary">← Về quản lý sản phẩm</a>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Thêm khuyến mãi</h5>
        <form method="post">
          <input type="hidden" name="action" value="add">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label">Tên khuyến mãi</label>
              <input name="TenKhuyenMai" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">% giảm (0–100)</label>
              <input name="PhanTramGiam" type="number" min="0" max="100" class="form-control" required>
            </div>
            <div class="col-12 mt-3">
              <button class="btn btn-primary">Thêm khuyến mãi</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Danh sách khuyến mãi (<?=count($promos)?>)</h5>
        <table class="table table-hover align-middle">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Tên khuyến mãi</th>
            <th>% giảm</th>
            <th>Sản phẩm đang áp dụng</th> <!-- THÊM CỘT -->
            <th>Hành động</th>
          </tr>
        </thead>
        
          <tbody>
            <?php foreach($promos as $km): ?>
              <tr>
                <form method="post">
                  <td><?=h($km['MaKhuyenMai'])?></td>
                  <td>
                    <input type="text" name="TenKhuyenMai"
                           class="form-control form-control-sm"
                           value="<?=h($km['TenKhuyenMai'])?>">
                  </td>
                  <td>
  <input type="number" name="PhanTramGiam" min="0" max="100"
         class="form-control form-control-sm"
         value="<?=h($km['PhanTramGiam'])?>">
</td>

<!-- CỘT MỚI: danh sách sản phẩm -->
<td style="min-width:220px;">
  <?php if (!empty($productsByPromo[$km['MaKhuyenMai']])): ?>
      <ul class="mb-0 ps-3">
        <?php foreach ($productsByPromo[$km['MaKhuyenMai']] as $tenSp): ?>
          <li style="font-size: 15px;"><?=h($tenSp)?></li>
        <?php endforeach; ?>
      </ul>
  <?php else: ?>
      <span class="text-muted" style="font-size: 15px;">
        Chưa có sản phẩm áp dụng
      </span>
  <?php endif; ?>
</td>

<td style="min-width:180px;">
  <input type="hidden" name="MaKhuyenMai" value="<?=h($km['MaKhuyenMai'])?>">
  <button class="btn btn-sm btn-primary" name="action" value="edit">Lưu</button>
  <button class="btn btn-sm btn-danger" name="action" value="delete"
          onclick="return confirm('Xóa chương trình khuyến mãi này?');">
    Xóa
  </button>
</td>

                </form>
              </tr>
            <?php endforeach;?>
            <?php if (empty($promos)): ?>
              <tr><td colspan="4" class="text-center text-muted">Chưa có khuyến mãi.</td></tr>
            <?php endif;?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
