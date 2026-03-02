<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';

if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ===== HANDLE ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['MaDanhGia'] ?? 0);

    if ($id > 0) {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE DanhGia SET TrangThai='Hiển thị' WHERE MaDanhGia=?")
                ->execute([$id]);
        }

        if ($action === 'hide') {
            $pdo->prepare("UPDATE DanhGia SET TrangThai='Ẩn' WHERE MaDanhGia=?")
                ->execute([$id]);
        }

        if ($action === 'delete') {
            $pdo->prepare("DELETE FROM DanhGia WHERE MaDanhGia=?")
                ->execute([$id]);
        }
    }
    header('Location: admin_reviews.php');
    exit;
}

// ===== LOAD REVIEWS =====
$sql = "
SELECT dg.*, sp.TenSanPham, nd.HoTen
FROM DanhGia dg
JOIN SanPham sp ON dg.MaSanPham = sp.MaSanPham
JOIN NguoiDung nd ON dg.MaNguoiDung = nd.MaNguoiDung
ORDER BY dg.NgayDanhGia DESC
";
$reviews = $pdo->query($sql)->fetchAll();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Admin - Đánh giá sản phẩm</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f5f6fa}
.sidebar{width:260px;position:fixed;top:0;left:0;height:100vh;background:#1e1e2d;color:#fff;padding-top:20px}
.content{margin-left:260px;padding:28px}
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
<h2>Quản lý đánh giá</h2>

<table class="table table-hover">
<thead class="table-dark">
<tr>
<th>#</th>
<th>Sản phẩm</th>
<th>Khách hàng</th>
<th>Sao</th>
<th>Nội dung</th>
<th>Trạng thái</th>
<th>Hành động</th>
</tr>
</thead>
<tbody>
<?php foreach ($reviews as $r): ?>
<tr>
<td><?=h($r['MaDanhGia'])?></td>
<td><?=h($r['TenSanPham'])?></td>
<td><?=h($r['HoTen'])?></td>
<td><?=str_repeat('⭐', (int)$r['SoSao'])?></td>
<td><?=h($r['NoiDung'])?></td>
<td>
<?php
$st = $r['TrangThai'];
$badge = 'bg-secondary';
if ($st === 'Hiển thị') $badge = 'bg-success';
elseif ($st === 'Chờ duyệt') $badge = 'bg-warning';
elseif ($st === 'Ẩn') $badge = 'bg-danger';
?>
<span class="badge <?=$badge?>"><?=h($st)?></span>
</td>
<td>
<form method="post" style="display:inline">
<input type="hidden" name="MaDanhGia" value="<?=h($r['MaDanhGia'])?>">
<?php if ($r['TrangThai'] !== 'Hiển thị'): ?>
<button name="action" value="approve" class="btn btn-sm btn-success">Duyệt</button>
<?php endif; ?>
<?php if ($r['TrangThai'] === 'Hiển thị'): ?>
<button name="action" value="hide" class="btn btn-sm btn-warning">Ẩn</button>
<?php endif; ?>
<button name="action" value="delete" class="btn btn-sm btn-danger"
        onclick="return confirm('Xóa đánh giá này?')">Xóa</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (empty($reviews)): ?>
<tr><td colspan="7" class="text-center text-muted">Chưa có đánh giá</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

</body>
</html>
