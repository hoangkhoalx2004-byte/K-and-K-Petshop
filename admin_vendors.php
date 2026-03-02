<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';

// auth admin
if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ================= ADD / EDIT / DELETE =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ADD
    if ($_POST['action'] === 'add') {
        $ten = trim($_POST['TenNhaPhanPhoi'] ?? '');
        $dt  = trim($_POST['SoDienThoai'] ?? '');
        $dc  = trim($_POST['DiaChi'] ?? '');

        if ($ten !== '') {
            $stmt = $pdo->prepare(
                "INSERT INTO NhaPhanPhoi (TenNhaPhanPhoi, SoDienThoai, DiaChi)
                 VALUES (?, ?, ?)"
            );
            $stmt->execute([$ten, $dt, $dc]);
        }
        header('Location: admin_vendors.php');
        exit;
    }

    // EDIT
    if ($_POST['action'] === 'edit' && !empty($_POST['MaNhaPhanPhoi'])) {
        $id  = (int)$_POST['MaNhaPhanPhoi'];
        $ten = trim($_POST['TenNhaPhanPhoi'] ?? '');
        $dt  = trim($_POST['SoDienThoai'] ?? '');
        $dc  = trim($_POST['DiaChi'] ?? '');

        if ($id > 0 && $ten !== '') {
            $stmt = $pdo->prepare(
                "UPDATE NhaPhanPhoi
                 SET TenNhaPhanPhoi=?, SoDienThoai=?, DiaChi=?
                 WHERE MaNhaPhanPhoi=?"
            );
            $stmt->execute([$ten, $dt, $dc, $id]);
        }
        header('Location: admin_vendors.php');
        exit;
    }

    // DELETE
    if ($_POST['action'] === 'delete' && !empty($_POST['MaNhaPhanPhoi'])) {
        $id = (int)$_POST['MaNhaPhanPhoi'];

        // chặn xóa nếu đang được dùng
        $check = $pdo->prepare("SELECT COUNT(*) FROM SanPham WHERE MaNhaPhanPhoi=?");
        $check->execute([$id]);

        if ($check->fetchColumn() == 0) {
            $pdo->prepare("DELETE FROM NhaPhanPhoi WHERE MaNhaPhanPhoi=?")->execute([$id]);
        }

        header('Location: admin_vendors.php');
        exit;
    }
}

// ================= LOAD DATA =================
$vendors = $pdo->query(
    "SELECT * FROM NhaPhanPhoi ORDER BY TenNhaPhanPhoi"
)->fetchAll();

// vendor đang sửa (nếu có ?edit=)
$editVendor = null;
if (!empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $st = $pdo->prepare("SELECT * FROM NhaPhanPhoi WHERE MaNhaPhanPhoi=?");
    $st->execute([$editId]);
    $editVendor = $st->fetch(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin - Nhà phân phối</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f6fa}
.sidebar{width:260px;position:fixed;top:0;left:0;height:100vh;background:#1e1e2d;color:#fff;padding-top:20px}
.sidebar a:hover{opacity:1}
.content{margin-left:260px;padding:28px}
</style>
</head>
<body>

<!-- SIDEBAR -->
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

<h2 class="mb-3">Quản lý nhà phân phối</h2>

<!-- ADD / EDIT FORM -->
<div class="card mb-4">
<div class="card-body">
<h5><?= $editVendor ? 'Cập nhật nhà phân phối' : 'Thêm nhà phân phối' ?></h5>

<form method="post">
<input type="hidden" name="action" value="<?= $editVendor ? 'edit' : 'add' ?>">
<?php if ($editVendor): ?>
  <input type="hidden" name="MaNhaPhanPhoi" value="<?= (int)$editVendor['MaNhaPhanPhoi'] ?>">
<?php endif; ?>

<div class="row g-2">
  <div class="col-md-4">
    <input name="TenNhaPhanPhoi" class="form-control" placeholder="Tên NPP" required
           value="<?= h($editVendor['TenNhaPhanPhoi'] ?? '') ?>">
  </div>
  <div class="col-md-3">
    <input name="SoDienThoai" class="form-control" placeholder="Điện thoại"
           value="<?= h($editVendor['SoDienThoai'] ?? '') ?>">
  </div>
  <div class="col-md-5">
    <input name="DiaChi" class="form-control" placeholder="Địa chỉ"
           value="<?= h($editVendor['DiaChi'] ?? '') ?>">
  </div>
  <div class="col-12">
    <button class="btn btn-primary mt-2">
      <?= $editVendor ? 'Cập nhật' : 'Thêm' ?>
    </button>
    <?php if ($editVendor): ?>
      <a href="admin_vendors.php" class="btn btn-secondary mt-2">Hủy</a>
    <?php endif; ?>
  </div>
</div>
</form>
</div>
</div>

<!-- LIST -->
<div class="card">
<div class="card-body">
<table class="table table-hover">
<thead class="table-dark">
<tr>
<th>#</th><th>Tên</th><th>Điện thoại</th><th>Địa chỉ</th><th>Hành động</th>
</tr>
</thead>
<tbody>
<?php foreach($vendors as $v): ?>
<tr>
<td><?=h($v['MaNhaPhanPhoi'])?></td>
<td><?=h($v['TenNhaPhanPhoi'])?></td>
<td><?=h($v['SoDienThoai'])?></td>
<td><?=h($v['DiaChi'])?></td>
<td>
<a href="admin_vendors.php?edit=<?= (int)$v['MaNhaPhanPhoi'] ?>" class="btn btn-sm btn-warning">Sửa</a>

<form method="post" style="display:inline" onsubmit="return confirm('Xóa nhà phân phối này?');">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="MaNhaPhanPhoi" value="<?=h($v['MaNhaPhanPhoi'])?>">
<button class="btn btn-sm btn-danger">Xóa</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if(empty($vendors)): ?>
<tr><td colspan="5" class="text-center text-muted">Chưa có nhà phân phối</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>
</body>
</html>
