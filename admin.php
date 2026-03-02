<?php
// admin_products.php
header('Content-Type: text/html; charset=utf-8');

session_start();
require_once 'config.php';

// --- Auth guard thật sự ---
if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}
// --- Helpers ---
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function handleUpload($fieldName = 'hinh') {
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) return null;
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES[$fieldName]['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed)) return null;
    // limit size (optional): 5MB
    if ($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) return null;
    $ext = pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION);
    $name = uniqid('p_') . '.' . $ext;
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $dest = $uploadDir . '/' . $name;
    if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $dest)) {
        return 'uploads/' . $name;
    }
    return null;
}

// --- Actions: add / edit / delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $ten = $_POST['TenSanPham'] ?? '';
    $gia = $_POST['Gia'] ?? 0;
    $soluong = $_POST['SoLuong'] ?? 0;
    $mota = $_POST['MoTa'] ?? '';
    $madm = $_POST['MaDanhMuc'] ?: null;
    $manpp = $_POST['MaNhaPhanPhoi'] ?: null;
    $makm = $_POST['MaKhuyenMai'] ?: null;
    $loai = $_POST['LoaiThuCung'] ?: null;

    $img = handleUpload('HinhAnh');
    $sql = "INSERT INTO SanPham (TenSanPham, Gia, SoLuong, MoTa, HinhAnh, MaKhuyenMai, LoaiThuCung, MaDanhMuc, MaNhaPhanPhoi)
      VALUES (:ten, :gia, :soluong, :mota, :hinh, :makm, :loai, :madm, :manpp)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ten'=>$ten, ':gia'=>$gia, ':soluong'=>$soluong, ':mota'=>$mota,
        ':hinh'=>$img, ':makm'=>$makm, ':loai'=>$loai, ':madm'=>$madm, ':manpp'=>$manpp
    ]);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // EDIT
    if (isset($_POST['action']) && $_POST['action'] === 'edit' && !empty($_POST['MaSanPham'])) {
        $id = intval($_POST['MaSanPham']);
        $ten = $_POST['TenSanPham'] ?? '';
        $gia = $_POST['Gia'] ?? 0;
        $soluong = $_POST['SoLuong'] ?? 0;
        $mota = $_POST['MoTa'] ?? '';
        $madm = $_POST['MaDanhMuc'] ?: null;
    $manpp = $_POST['MaNhaPhanPhoi'] ?: null;
    $loai = $_POST['LoaiThuCung'] ?: null;
        $makm = $_POST['MaKhuyenMai'] ?: null;

        // nếu upload ảnh mới -> xóa ảnh cũ trên disk
        $newImg = handleUpload('HinhAnh_edit');
        if ($newImg) {
            // get old
            $q = $pdo->prepare("SELECT HinhAnh FROM SanPham WHERE MaSanPham = ?");
            $q->execute([$id]);
            $row = $q->fetch();
            if ($row && $row['HinhAnh']) {
                $old = __DIR__ . '/' . $row['HinhAnh'];
                if (file_exists($old)) @unlink($old);
            }
            $sql = "UPDATE SanPham SET TenSanPham=?, Gia=?, SoLuong=?, MoTa=?, HinhAnh=?, MaKhuyenMai=?, LoaiThuCung=?, MaDanhMuc=?, MaNhaPhanPhoi=? WHERE MaSanPham=?";
            $pdo->prepare($sql)->execute([$ten,$gia,$soluong,$mota,$newImg,$makm,$loai,$madm,$manpp,$id]);
        } else {
            $sql = "UPDATE SanPham SET TenSanPham=?, Gia=?, SoLuong=?, MoTa=?, MaKhuyenMai=?, LoaiThuCung=?, MaDanhMuc=?, MaNhaPhanPhoi=? WHERE MaSanPham=?";
            $pdo->prepare($sql)->execute([$ten,$gia,$soluong,$mota,$makm,$loai,$madm,$manpp,$id]);
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    // DELETE ALL PRODUCTS
if (isset($_POST['action']) && $_POST['action'] === 'delete_all') {

    // lấy tất cả ảnh để xóa file
    $imgs = $pdo->query("SELECT HinhAnh FROM SanPham WHERE HinhAnh IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($imgs as $img) {
        $path = __DIR__ . '/' . $img;
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    // xóa toàn bộ sản phẩm
    $pdo->exec("DELETE FROM SanPham");

    // reset AUTO_INCREMENT
    $pdo->exec("ALTER TABLE SanPham AUTO_INCREMENT = 1");

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


    // DELETE
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['MaSanPham'])) {
        $id = intval($_POST['MaSanPham']);
        // xóa file ảnh
        $q = $pdo->prepare("SELECT HinhAnh FROM SanPham WHERE MaSanPham = ?");
        $q->execute([$id]);
        $row = $q->fetch();
        if ($row && $row['HinhAnh']) {
            $old = __DIR__ . '/' . $row['HinhAnh'];
            if (file_exists($old)) @unlink($old);
        }
        $pdo->prepare("DELETE FROM SanPham WHERE MaSanPham = ?")->execute([$id]);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// --- Load data for page ---
$products = $pdo->query("SELECT * FROM SanPham ORDER BY MaSanPham DESC")->fetchAll();
$categories = $pdo->query("SELECT MaDanhMuc, TenDanhMuc FROM DanhMucSanPham ORDER BY TenDanhMuc")->fetchAll();
$vendors = $pdo->query("SELECT MaNhaPhanPhoi, TenNhaPhanPhoi FROM NhaPhanPhoi ORDER BY TenNhaPhanPhoi")->fetchAll();
$promos = $pdo->query("SELECT MaKhuyenMai, TenKhuyenMai, PhanTramGiam FROM KhuyenMai ORDER BY TenKhuyenMai")->fetchAll();
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin - Quản lý sản phẩm</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f5f6fa; }
    .sidebar { width:260px; position:fixed; top:0; left:0; height:100vh; background:#1e1e2d; color:#fff; padding-top:20px; }
    .content { margin-left:260px; padding:28px; }
    .product-img { width:64px; height:64px; object-fit:cover; border-radius:6px; }
  </style>
</head>
<body>
<div class="sidebar">
    <div class="text-center mb-3"><h4>K&K Admin</h4></div>

    <!-- Có thể bỏ Dashboard nếu chưa dùng -->
    <!-- <a class="d-block text-decoration-none text-muted px-3 py-2" href="#">📊 Dashboard</a> -->

    <a class="d-block text-decoration-none px-3 py-2" href="admin_categories.php">📂 Quản lý danh mục</a>
    <a class="d-block text-decoration-none px-3 py-2" href="admin_promos.php">🏷️ Quản lý khuyến mãi</a>
    <a class="d-block text-decoration-none px-3 py-2" href="admin.php">📦 Sản phẩm</a>
    <a class="d-block text-decoration-none px-3 py-2" href="admin_vendors.php">🚚 Nhà phân phối</a>
    <a class="d-block text-decoration-none px-3 py-2" href="admin_orders.php">📝 Đơn hàng</a>
    <a class="d-block text-decoration-none px-3 py-2" href="admin_reviews.php">⭐ Đánh giá</a>

  </div>

  <div class="content">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">Quản lý sản phẩm</h2>
      <a href="index.php" class="btn btn-outline-secondary">
        ← Về trang khách hàng
      </a>
    </div>

    <!-- form thêm -->


    <!-- form thêm -->
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Thêm sản phẩm</h5>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="add">
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Tên sản phẩm</label>
              <input name="TenSanPham" class="form-control" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Giá</label>
              <input name="Gia" type="number" step="0.01" class="form-control" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Số lượng</label>
              <input name="SoLuong" type="number" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Danh mục</label>
              <select name="MaDanhMuc" class="form-select">
                <option value="">-- Chọn danh mục --</option>
                <?php foreach($categories as $c): ?>
                  <option value="<?=h($c['MaDanhMuc'])?>"><?=h($c['TenDanhMuc'])?></option>
                <?php endforeach;?>
              </select>
            </div>

            <div class="col-md-4 mt-2">
              <label class="form-label">Loại thú cưng</label>
              <select name="LoaiThuCung" class="form-select">
                <option value="">-- Không --</option>
                <option value="cho">Phụ kiện cho chó</option>
                <option value="meo">Phụ kiện cho mèo</option>
                <option value="chung">Dùng chung</option>
              </select>
            </div>

            <div class="col-md-4 mt-2">
              <label class="form-label">Nhà phân phối</label>
              <select name="MaNhaPhanPhoi" class="form-select">
                <option value="">-- Chọn NPP --</option>
                <?php foreach($vendors as $v): ?>
                  <option value="<?=h($v['MaNhaPhanPhoi'])?>"><?=h($v['TenNhaPhanPhoi'])?></option>
                <?php endforeach;?>
              </select>
            </div>

            <div class="col-md-4 mt-2">
              <label class="form-label">Khuyến mãi</label>
              <select name="MaKhuyenMai" class="form-select">
                <option value="">-- Không --</option>
                <?php foreach($promos as $p): ?>
                  <option value="<?=h($p['MaKhuyenMai'])?>"><?=h($p['TenKhuyenMai'])?> (<?=h($p['PhanTramGiam'])?>%)</option>
                <?php endforeach;?>
              </select>
            </div>

            <div class="col-md-4 mt-2">
              <label class="form-label">Ảnh</label>
              <input name="HinhAnh" type="file" accept="image/*" class="form-control">
            </div>

            <div class="col-12 mt-2">
              <label class="form-label">Mô tả</label>
              <textarea name="MoTa" class="form-control" rows="3"></textarea>
            </div>

            <div class="col-12 mt-3">
              <button class="btn btn-primary">Thêm sản phẩm</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- bảng -->
    <div class="card">
      <div class="card-body">
        <h5 class="card-title">Danh sách sản phẩm (<?=count($products)?>)</h5>
          <form method="post" class="mb-3"
      onsubmit="return confirm('⚠️ CẢNH BÁO!\nHành động này sẽ XÓA TOÀN BỘ sản phẩm và KHÔNG THỂ KHÔI PHỤC.\nBạn chắc chắn chứ?');">
  <input type="hidden" name="action" value="delete_all">
  <button class="btn btn-danger">
    🗑️ Xóa tất cả sản phẩm
  </button>
</form>


        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-dark">
              <tr>
                <th>#</th><th>Ảnh</th><th>Tên</th><th>Giá</th><th>Tồn</th><th>Danh mục</th><th>Hành động</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($products as $p): ?>
                <tr data-id="<?=h($p['MaSanPham'])?>"
                  data-ten="<?=h($p['TenSanPham'])?>"
                  data-gia="<?=h($p['Gia'])?>"
                  data-sol="<?=h($p['SoLuong'])?>"
                  data-mota="<?=h($p['MoTa'])?>"
                  data-madm="<?=h($p['MaDanhMuc'])?>"
                  data-manpp="<?=h($p['MaNhaPhanPhoi'])?>"
                  data-makm="<?=h($p['MaKhuyenMai'])?>"
                  data-loai="<?=h($p['LoaiThuCung'] ?? '')?>"
                  data-hinh="<?=h($p['HinhAnh'])?>"
                >
                  <td><?=h($p['MaSanPham'])?></td>
                  <td>
                    <?php if ($p['HinhAnh']): ?>
                      <img src="<?=h($p['HinhAnh'])?>" class="product-img" alt="">
                    <?php else: ?>
                      <div style="width:64px;height:64px;background:#eee;border-radius:6px;"></div>
                    <?php endif;?>
                  </td>
                  <td><?=h($p['TenSanPham'])?></td>
                  <td><?=number_format($p['Gia'],0,',','.')?>đ</td>
                  <td><?=h($p['SoLuong'])?></td>
                  <td>
                    <?php
                      // load category name quickly (could be optimized)
                      $cname = '';
                      foreach($categories as $c) if ($c['MaDanhMuc']==$p['MaDanhMuc']) $cname = $c['TenDanhMuc'];
                      echo h($cname);
                    ?>
                  </td>
                  <td style="min-width:200px;">
                    <button class="btn btn-sm btn-primary btn-edit">Sửa</button>
                    <form method="post" style="display:inline" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="MaSanPham" value="<?=h($p['MaSanPham'])?>">
                      <button class="btn btn-sm btn-danger">Xóa</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($products)): ?>
                <tr><td colspan="7" class="text-center text-muted">Chưa có sản phẩm.</td></tr>
              <?php endif;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

  <!-- Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="MaSanPham" id="edit_MaSanPham">
          <div class="modal-header">
            <h5 class="modal-title">Sửa sản phẩm</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">Tên sản phẩm</label>
                <input name="TenSanPham" id="edit_TenSanPham" class="form-control" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Giá</label>
                <input name="Gia" id="edit_Gia" type="number" step="0.01" class="form-control" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Số lượng</label>
                <input name="SoLuong" id="edit_SoLuong" type="number" class="form-control" required>
              </div>

              <div class="col-md-4 mt-2">
                <label class="form-label">Danh mục</label>
                <select name="MaDanhMuc" id="edit_MaDanhMuc" class="form-select">
                  <option value="">-- Không --</option>
                  <?php foreach($categories as $c): ?>
                    <option value="<?=h($c['MaDanhMuc'])?>"><?=h($c['TenDanhMuc'])?></option>
                  <?php endforeach;?>
                </select>
              </div>

              <div class="col-md-4 mt-2">
                <label class="form-label">Loại thú cưng</label>
                <select name="LoaiThuCung" id="edit_LoaiThuCung" class="form-select">
                  <option value="">-- Không --</option>
                  <option value="cho">Phụ kiện cho chó</option>
                  <option value="meo">Phụ kiện cho mèo</option>
                  <option value="chung">Dùng chung</option>
                </select>
              </div>

              <div class="col-md-4 mt-2">
                <label class="form-label">Nhà phân phối</label>
                <select name="MaNhaPhanPhoi" id="edit_MaNhaPhanPhoi" class="form-select">
                  <option value="">-- Không --</option>
                  <?php foreach($vendors as $v): ?>
                    <option value="<?=h($v['MaNhaPhanPhoi'])?>"><?=h($v['TenNhaPhanPhoi'])?></option>
                  <?php endforeach;?>
                </select>
              </div>

              <div class="col-md-4 mt-2">
                <label class="form-label">Khuyến mãi</label>
                <select name="MaKhuyenMai" id="edit_MaKhuyenMai" class="form-select">
                  <option value="">-- Không --</option>
                  <?php foreach($promos as $p): ?>
                    <option value="<?=h($p['MaKhuyenMai'])?>"><?=h($p['TenKhuyenMai'])?> (<?=h($p['PhanTramGiam'])?>%)</option>
                  <?php endforeach;?>
                </select>
              </div>

              <div class="col-md-6 mt-2">
                <label class="form-label">Ảnh hiện tại</label>
                <div id="currentImageWrap"></div>
              </div>
              <div class="col-md-6 mt-2">
                <label class="form-label">Đổi ảnh (nếu cần)</label>
                <input type="file" name="HinhAnh_edit" class="form-control">
              </div>

              <div class="col-12 mt-2">
                <label class="form-label">Mô tả</label>
                <textarea name="MoTa" id="edit_MoTa" class="form-control" rows="4"></textarea>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
            <button class="btn btn-primary">Lưu thay đổi</button>
          </div>
        </form>
      </div>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// khi bấm Sửa: lấy data-* từ tr và fill modal
document.querySelectorAll('.btn-edit').forEach(btn=>{
  btn.addEventListener('click', function(){
    const tr = this.closest('tr');
    const id = tr.dataset.id;
    document.getElementById('edit_MaSanPham').value = id;
    document.getElementById('edit_TenSanPham').value = tr.dataset.ten || '';
    document.getElementById('edit_Gia').value = tr.dataset.gia || '';
    document.getElementById('edit_SoLuong').value = tr.dataset.sol || '';
    document.getElementById('edit_MoTa').value = tr.dataset.mota || '';
    document.getElementById('edit_MaDanhMuc').value = tr.dataset.madm || '';
    document.getElementById('edit_MaNhaPhanPhoi').value = tr.dataset.manpp || '';
    document.getElementById('edit_MaKhuyenMai').value = tr.dataset.makm || '';
    document.getElementById('edit_LoaiThuCung').value = tr.dataset.loai || '';

    const hinh = tr.dataset.hinh || '';
    const wrap = document.getElementById('currentImageWrap');
    wrap.innerHTML = '';
    if (hinh) {
      const img = document.createElement('img');
      img.src = hinh;
      img.style.maxWidth = '120px';
      img.style.borderRadius = '6px';
      wrap.appendChild(img);
    } else {
      wrap.textContent = 'Chưa có ảnh';
    }

    const modal = new bootstrap.Modal(document.getElementById('editModal'));
    modal.show();
  });
});
</script>
</body>
</html>
