<?php
// admin_categories.php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';

// Chặn nếu không phải admin
if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ================== XỬ LÝ THÊM / SỬA / XÓA ==================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // THÊM
    if (!empty($_POST['action']) && $_POST['action'] === 'add') {
        $ten  = trim($_POST['TenDanhMuc'] ?? '');
        $mota = trim($_POST['MoTa'] ?? '');

        if ($ten !== '') {
            $sql = "INSERT INTO DanhMucSanPham (TenDanhMuc, MoTa) VALUES (:ten, :mota)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':ten' => $ten, ':mota' => $mota]);
        }
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }

    // SỬA
    if (!empty($_POST['action']) && $_POST['action'] === 'edit' && !empty($_POST['MaDanhMuc'])) {
        $id   = (int)$_POST['MaDanhMuc'];
        $ten  = trim($_POST['TenDanhMuc'] ?? '');
        $mota = trim($_POST['MoTa'] ?? '');

        if ($ten !== '') {
            $sql = "UPDATE DanhMucSanPham SET TenDanhMuc = :ten, MoTa = :mota WHERE MaDanhMuc = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':ten'=>$ten, ':mota'=>$mota, ':id'=>$id]);
        }
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }

    // XÓA
    if (!empty($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['MaDanhMuc'])) {
        $id = (int)$_POST['MaDanhMuc'];

        // Nếu sản phẩm còn trỏ tới danh mục này thì nên set MaDanhMuc = NULL
        $pdo->prepare("UPDATE SanPham SET MaDanhMuc = NULL WHERE MaDanhMuc = ?")->execute([$id]);

        $pdo->prepare("DELETE FROM DanhMucSanPham WHERE MaDanhMuc = ?")->execute([$id]);
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }
}

// ================== LẤY DỮ LIỆU HIỂN THỊ ==================

// Danh sách danh mục
$categories = $pdo->query("
    SELECT * FROM DanhMucSanPham
    ORDER BY TenDanhMuc
")->fetchAll(PDO::FETCH_ASSOC);

// Gom danh sách sản phẩm theo từng danh mục
$productsByCat = [];
$stmtProdCat = $pdo->query("
    SELECT MaDanhMuc, TenSanPham
    FROM SanPham
    WHERE MaDanhMuc IS NOT NULL
    ORDER BY TenSanPham
");

foreach ($stmtProdCat as $row) {
    $madm = $row['MaDanhMuc'];
    if (!isset($productsByCat[$madm])) {
        $productsByCat[$madm] = [];
    }
    $productsByCat[$madm][] = $row['TenSanPham'];
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Admin - Quản lý danh mục</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f5f6fa; }
    .sidebar {
      width:260px; position:fixed; top:0; left:0;
      height:100vh; background:#1e1e2d; color:#fff; padding-top:20px;
    }
    .content { margin-left:260px; padding:28px; }
    a { text-decoration:none; }
  </style>
</head>
<body>

<!-- Sidebar chung với các trang admin khác -->
<div class="sidebar">
  <div class="text-center mb-3"><h4>K&K Admin</h4></div>
  <a class="d-block px-3 py-2 text-decoration-none" href="admin_categories.php">📂 Quản lý danh mục</a>
  <a class="d-block px-3 py-2 text-decoration-none" href="admin_promos.php">🏷️ Quản lý khuyến mãi</a>
  <a class="d-block px-3 py-2 text-decoration-none" href="admin.php">📦 Sản phẩm</a>
  <a class="d-block text-decoration-none px-3 py-2" href="admin_vendors.php">🚚 Nhà phân phối</a>
  <a class="d-block px-3 py-2 text-decoration-none" href="admin_orders.php">📝 Đơn hàng</a>
    <a class="d-block text-decoration-none px-3 py-2" href="admin_reviews.php">⭐ Đánh giá</a>

</div>

<div class="content">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Quản lý danh mục sản phẩm</h2>
    <a href="admin.php" class="btn btn-outline-secondary">← Về trang sản phẩm</a>
  </div>

  <!-- Form thêm danh mục -->
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="card-title">Thêm danh mục</h5>
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Tên danh mục</label>
            <input type="text" name="TenDanhMuc" class="form-control" required>
          </div>
          <div class="col-md-8">
            <label class="form-label">Mô tả</label>
            <input type="text" name="MoTa" class="form-control">
          </div>
          <div class="col-12 mt-3">
            <button class="btn btn-primary">Thêm danh mục</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Bảng danh mục -->
  <div class="card">
    <div class="card-body">
      <h5 class="card-title">Danh sách danh mục (<?=count($categories)?>)</h5>

      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Tên danh mục</th>
              <th>Mô tả</th>
              <th>Sản phẩm trong danh mục</th>
              <th>Hành động</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($categories as $dm): ?>
            <tr>
              <form method="post">
                <td><?=h($dm['MaDanhMuc'])?></td>

                <td style="min-width:180px;">
                  <input type="text" name="TenDanhMuc"
                         class="form-control form-control-sm"
                         value="<?=h($dm['TenDanhMuc'])?>">
                </td>

                <td style="min-width:220px;">
                  <input type="text" name="MoTa"
                         class="form-control form-control-sm"
                         value="<?=h($dm['MoTa'])?>">
                </td>

                <!-- CỘT MỚI: danh sách sản phẩm thuộc danh mục -->
                <td style="min-width:230px;">
                  <?php if (!empty($productsByCat[$dm['MaDanhMuc']])): ?>
                    <ul class="mb-0 ps-3">
                      <?php foreach ($productsByCat[$dm['MaDanhMuc']] as $tenSp): ?>
                        <li style="font-size:13px;"><?=h($tenSp)?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php else: ?>
                    <span class="text-muted" style="font-size:13px;">
                      Chưa có sản phẩm trong danh mục này
                    </span>
                  <?php endif; ?>
                </td>

                <td style="min-width:170px;">
                  <input type="hidden" name="MaDanhMuc" value="<?=h($dm['MaDanhMuc'])?>">
                  <button class="btn btn-sm btn-primary" name="action" value="edit">Lưu</button>
                  <button class="btn btn-sm btn-danger" name="action" value="delete"
                          onclick="return confirm('Xóa danh mục này? Sản phẩm sẽ bị bỏ liên kết danh mục.');">
                    Xóa
                  </button>
                </td>
              </form>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($categories)): ?>
            <tr><td colspan="5" class="text-center text-muted">Chưa có danh mục.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
</body>
</html>
