<?php if (isset($_GET['bulk_sent'])): ?>
  <div class="alert alert-success">
    ✅ Đã gửi thông báo cho admin. Admin sẽ liên hệ bạn sớm nhất!
  </div>
<?php endif; ?>

<?php
header('Content-Type: text/html; charset=utf-8');
// index.php - Trang khách hàng

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

// Lấy danh sách sản phẩm + % giảm
$sql = "SELECT sp.*, km.PhanTramGiam  
        FROM SanPham sp
        LEFT JOIN KhuyenMai km ON sp.MaKhuyenMai = km.MaKhuyenMai
        ORDER BY sp.MaSanPham DESC";
$products = $pdo->query($sql)->fetchAll();

// Hàm escape
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Lấy info user nếu đã đăng nhập
$userName = $_SESSION['user_name'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

// Lấy danh sách sản phẩm nổi bật (lấy 5 sản phẩm mới nhất làm featured)
$featuredProducts = array_slice($products, 0, 5);

// Lấy map danh mục (MaDanhMuc => TenDanhMuc)
$catsStmt = $pdo->query("SELECT MaDanhMuc, TenDanhMuc FROM DanhMucSanPham");
$categoriesMap = [];
foreach ($catsStmt->fetchAll() as $c) {
  $categoriesMap[$c['MaDanhMuc']] = $c['TenDanhMuc'];
}

// Phân loại sản phẩm: phụ kiện chó, phụ kiện mèo, dùng chung
$dogProducts = [];
$catProducts = [];
$sharedProducts = [];
foreach ($products as $p) {
  $catName = null;
  if (!empty($p['MaDanhMuc']) && isset($categoriesMap[$p['MaDanhMuc']])) {
    $catName = $categoriesMap[$p['MaDanhMuc']];
  }

  $isDog = false;
  $isCat = false;
  // Prefer explicit LoaiThuCung if set (cho|meo|chung)
  $loaiField = mb_strtolower((string)($p['LoaiThuCung'] ?? ''), 'UTF-8');
  if ($loaiField !== '') {
    if (mb_stripos($loaiField, 'cho') !== false || mb_stripos($loaiField, 'chó') !== false || mb_stripos($loaiField, 'dog') !== false) { $isDog = true; }
    elseif (mb_stripos($loaiField, 'meo') !== false || mb_stripos($loaiField, 'mèo') !== false || mb_stripos($loaiField, 'cat') !== false) { $isCat = true; }
    else { /* leave as shared */ }
  } else {
    // Fallback to category name detection
    if ($catName) {
      $lc = mb_strtolower($catName, 'UTF-8');
      if (mb_stripos($lc, 'cho') !== false || mb_stripos($lc, 'chó') !== false || mb_stripos($lc, 'dog') !== false) $isDog = true;
      if (mb_stripos($lc, 'meo') !== false || mb_stripos($lc, 'mèo') !== false || mb_stripos($lc, 'cat') !== false) $isCat = true;
    }

    // Older fallback: check vendor field string if it contains keywords (rare)
    $ptype = mb_strtolower((string)($p['MaNhaPhanPhoi'] ?? ''), 'UTF-8');
    if ($ptype !== '') {
      if (mb_stripos($ptype, 'cho') !== false || mb_stripos($ptype, 'chó') !== false || mb_stripos($ptype, 'dog') !== false) { $isDog = true; $isCat = false; }
      if (mb_stripos($ptype, 'meo') !== false || mb_stripos($ptype, 'mèo') !== false || mb_stripos($ptype, 'cat') !== false) { $isCat = true; $isDog = false; }
      if (mb_stripos($ptype, 'chung') !== false) { $isDog = false; $isCat = false; }
    }
  }

  if ($isDog && !$isCat) {
    $dogProducts[] = $p;
  } elseif ($isCat && !$isDog) {
    $catProducts[] = $p;
  } else {
    $sharedProducts[] = $p;
  }
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>K&K PetShop - Cửa hàng thú cưng</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    body { background:#f5f6fa; font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial; }
   
      /* Bao ngoài để có nền xanh full trang */
.kk-marquee-wrap {
  background: #66CCFF;
  padding: 4px 0;
}

/* Dòng chạy chỉ nằm trong container, không bị dư */
.kk-marquee {
  color: #FF9900;
  font-weight: 700;
  font-size: 20px;
  white-space: nowrap;
  overflow: hidden;
  animation: kkSlide 15s linear infinite;
}

@keyframes kkSlide {
  0% { transform: translateX(100%); }
  100% { transform: translateX(-100%); }
}

/* CATEGORY BAR */
/* Thanh danh mục màu cam như ảnh mẫu */
.kk-category-bar {
    background: #66CCFF;
    padding: 8px 0;
}

/* Khối bên trong giới hạn chiều rộng để chữ không bị banh quá xa */
.kk-category-inner {
    max-width: 900px;          /* chỉnh 800–1000 tuỳ bạn muốn sát hay rộng */
    margin: 0 auto;
    display: flex;
    justify-content: space-between;  /* trải đều giống ảnh mẫu */
    align-items: center;
}

/* Item */
.kk-category-bar .cat-item {
    color: #FF9900;
    font-weight: 700;
    text-decoration: none;
    font-size: 20px;
    padding: 4px 8px;
    white-space: nowrap;
    transition: 0.2s ease;
}

.kk-category-bar .cat-item:hover {
  text-decoration: none;
  opacity: 0.9;
  transform: translateY(-1px);
}



   

    .navbar {
      background:#ffffff;
      border-bottom:1px solid #e5e7eb;
    }
    .navbar-brand span {
      font-weight:800;
      color:#f97316;
      letter-spacing:0.03em;
    }
    .navbar-brand {
      font-weight:600;
    }
    .logo-img {
      height: 70px;
      width: auto;
      object-fit: contain;
    }
    /* Header giống Pet Yêu: logo + search + icon bên phải */
.navbar {
  background:#ffffff;
  border-bottom:1px solid #e5e7eb;
  padding:8px 0;
}

.kk-header-main {
  display:flex;
  align-items:center;
  gap:16px;
}

/* Ô search bo tròn xanh nhạt */
.kk-search {
  flex:1;
  display:flex;
  align-items:center;
  background:#66CCFF;              /* xanh nhạt */
  border-radius:999px;
  padding:4px;
}

.kk-search-input {
  flex:1;
  border:none;
  background:transparent;
  padding:8px 16px;
  outline:none;
  font-size:14px;
}

.kk-search-btn {
  border:none;
  cursor:pointer;
  width:40px;
  height:40px;
  border-radius:50%;
  background:#f8ac1b;              /* cam */
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:18px;
  color:#ffffff;
}

/* Các mục bên phải: Đăng nhập – Giỏ hàng – Kiểm tra – SĐT */
.kk-actions {
  display:flex;
  align-items:center;
  gap:20px;
  margin-left:8px;
  font-size:13px;
  color:#134f26;
}



.kk-action-item {
  display:flex;
  flex-direction:column;
  align-items:center;
  text-decoration:none;
  color:inherit;
}

.kk-action-icon {
  font-size:18px;
  margin-bottom:2px;
}

/* Responsive chút cho màn nhỏ */
@media (max-width: 768px) {
  .kk-header-main {
    flex-wrap:wrap;
  }
  .kk-actions {
    flex-wrap:wrap;
    justify-content:flex-end;
  }
}


    .nav-link {
      font-weight:500;
      color:#374151 !important;
    }
    .nav-link.active, .nav-link:hover {
      color:#f97316 !important;
    }

    /* HERO mới: mềm hơn + sang hơn */
    .hero {
  background: linear-gradient(
      135deg,
      #b5e7ff 0%,
      #9edcff 30%,
      #87d2ff 60%,
      #b5e7ff 100%
  );
  padding: 40px 0 32px;
}
.hero,
.featured-card,
.hero-title,
.hero-sub {
    font-family: 'Poppins', sans-serif;
}



.hero .container {
  max-width: 1120px;
}

/* Bên trái */
.hero-title {
  font-size: 36px;
  font-weight: 700;
  color: #FF9900; /* xanh đậm hiện đại */
}
.hero-sub {
  font-size: 15px;
  color: #FF9900;
}

.hero ul {
  padding-left: 18px;
  margin-bottom: 20px;
}


.hero ul li {
  margin-bottom: 6px;
  font-size: 15px;
  color: #FF9900;
}

/* Badge trên cùng */
.badge-hero {
  background:#f97316;
  color:#fff;
  padding:4px 14px;
  border-radius:999px;
  font-size:11px;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:0.12em;
}

/* CARD bên phải – làm nổi hơn */
.featured-card {
    background: #f2fbff;  /* trắng pha xanh pastel */
    border-radius: 22px;
    border: 1px solid rgba(0, 140, 255, 0.15);
    box-shadow: 0 10px 25px rgba(0, 80, 160, 0.12);
}

.featured-card h3 {
  font-weight: 600;
  font-size: 22px;
  color: #FF9900; /* xanh đậm sạch, không quá nặng */
}
.featured-card p {
  color: #FF9900;
}
.featured-card .price {
  font-size: 18px;
  font-weight: 700;
  color: #FF9900;
}


/* Chữ trong card phải */
.featured-label {
  font-size:12px;
  color:#FF9900;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:0.12em;
}

.featured-card h3 {
  font-size:22px;
  font-weight:800;
  margin-bottom:6px;
}

.featured-card .price {
  font-size:18px;
}

/* Nút “Mua ngay sản phẩm này” to + tròn hơn */
.btn-primary {
  border-radius: 999px;
  padding: 12px 24px;
  background: linear-gradient(90deg, #FF9900, #005eff);
  border: none;
  box-shadow: 0px 6px 20px rgba(0, 91, 255, 0.35);
}
/* Style chung */
.btn-kk {
    font-family: 'Poppins', sans-serif;
    font-weight: 600;
    font-size: 15px;
    padding: 12px 28px;
    border-radius: 999px;
    transition: 0.25s ease;
    text-decoration:none !important;
}

/* Nút chính */
.btn-kk-primary {
    background: linear-gradient(90deg, #007bff, #005eff);
    color: #FF9900;
    border: none;
    box-shadow: 0 6px 18px rgba(0, 123, 255, 0.35);
}
.btn-kk-primary:hover {
    background: linear-gradient(90deg, #005eff, #004dcc);
    box-shadow: 0 8px 22px rgba(0, 91, 255, 0.45);
    transform: translateY(-2px);
}

/* Nút phụ */
.btn-kk-outline {
    background: linear-gradient(90deg, #007bff, #005eff);
    color: #FF9900;
    border: none;
    box-shadow: 0 6px 18px rgba(0, 123, 255, 0.35);
}
.btn-kk-outline:hover {
    background: linear-gradient(90deg, #005eff, #004dcc);
    box-shadow: 0 8px 22px rgba(0, 91, 255, 0.45);
    transform: translateY(-2px);
}



/* Vùng "Tất cả sản phẩm" (full-bleed) */
#products {
  /* Blue gradient to match the hero / category bar and span full viewport */
 background: linear-gradient(
      135deg,
      #b5e7ff 0%,
      #9edcff 30%,
      #87d2ff 60%,
      #b5e7ff 100%
  );
  padding: 48px 0 60px;
  border-radius: 0; /* remove rounded corners so gradient is full-bleed */
}

/* Tiêu đề + info số sản phẩm */
.section-title {
  font-family: 'Poppins', sans-serif;
  font-size: 22px;
  font-weight: 700;
  color: #111827;
}

#products .text-muted {
  font-size: 13px;
}

/* CARD sản phẩm bên dưới */
.product-card {
  border-radius: 22px;
  background: #ffffff;
  box-shadow: 0 16px 40px rgba(15, 23, 42, 0.10);
  overflow: hidden;
  transition: transform .18s ease, box-shadow .18s ease;
  display: flex;
  flex-direction: column;
}


.product-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 22px 60px rgba(15, 23, 42, 0.18);
}

/* Hình sản phẩm */
.product-img {
  display: block;
  width: 100%;
  height: auto;                /* 🔥 cho ảnh tự cao theo tỉ lệ gốc */
  margin: 0;
  background: #f3f4f6;
  border-top-left-radius: 22px;
  border-top-right-radius: 22px;

  /* ❌ NHỚ BỎ các dòng này nếu còn:
     height: 240px;
     aspect-ratio: ...;
     object-fit: cover;
     object-fit: contain;
  */
}

/* Tên sp */
.product-card .p-3 {
  font-family: 'Poppins', sans-serif;
}

.product-card .p-3 > div:first-child {
  min-height: 48px;
  font-size: 15px;
  font-weight: 600;
  color: #111827;
}

/* Giá */
.price {
  color: #ff4b4b;
  font-weight: 700;
  font-size: 15px;
  display: block;
  text-align: center;
}


.old-price {
  text-decoration: line-through;
  color: #9ca3af;
  font-size: 13px;
  margin-left: 6px;
}

/* Badge giảm giá */
.discount-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  background: #ff4b4b;
  color: #fff;
  font-size: 12px;
  padding: 4px 8px;
  border-radius: 999px;
  font-weight: 600;
  box-shadow: 0 8px 20px rgba(220,38,38,0.45);
}

/* "Còn lại: x sp" */
.product-card .text-muted {
  font-size: 12px;
}

/* Nút Mua ngay trong card – dùng chung style đẹp ở trên */
.product-card .btn-primary {
  border-radius: 999px;
  padding: 10px 0;
  font-weight: 600;
  font-family: 'Poppins', sans-serif;
  background: linear-gradient(90deg, #ff9f1c, #007bff);
  border: none;
  box-shadow: 0 6px 18px rgba(0, 123, 255, 0.30);
}

.product-card .btn-primary:hover {
  background: linear-gradient(90deg, #ff8500, #005eff);
  box-shadow: 0 8px 24px rgba(0, 91, 255, 0.40);
  transform: translateY(-1px);
}
.price-wrap {
    text-align: center;      /* nếu muốn căn trái => đổi thành left */
}

.price {
    display: inline-block;
    color: #ff0000;
    font-weight: 700;
    font-size: 15px;
}

.old-price {
    display: inline-block;
    margin-left: 6px;
    font-size: 14px;
    color: #9ca3af;
    text-decoration: line-through;
}



    
    footer {
      margin-top:40px;
      padding:24px 0;
      background:#111827;
      color:#9ca3af;
      font-size:14px;
    }

    /* Carousel custom height */
    #featuredCarousel .carousel-item {
      padding:8px 0;
    }

    .featured-card {
      border-radius:18px;
      background:#ffffff;
      box-shadow:0 10px 30px rgba(15,23,42,0.12);
      overflow:hidden;
    }
    .featured-img {
      height:260px;
      width:100%;
      object-fit:cover;
    }
    .featured-label {
      font-size:13px;
      color:#f97316;
      font-weight:600;
      text-transform:uppercase;
      letter-spacing:0.08em;
    }

    /* Popup promo modal */
    .promo-modal .modal-content {
      border-radius:18px;
      border:none;
      overflow:hidden;
    }
    .promo-header {
      background:linear-gradient(135deg,#0369a1,#60a5fa);
      color:#fff;
      padding:16px 20px;
    }
    .promo-body {
      padding:20px;
    }
    .promo-tag {
      display:inline-block;
      background:#e0f2fe;
      color:#075985;
      font-size:12px;
      font-weight:600;
      padding:4px 8px;
      border-radius:999px;
      margin-bottom:8px;
      text-transform:uppercase;
      letter-spacing:0.08em;
    }
    .promo-header h5 {
      font-size:22px;
      font-weight:800;
      margin:0;
      color:#fff;
      font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      letter-spacing:0.01em;
    }
    .promo-header div {
      font-size:13px;
      opacity:0.95;
      color:rgba(255,255,255,0.95);
    }
    .promo-body h5 {
      font-size:18px;
      color:#083344;
      font-weight:700;
      margin-bottom:8px;
    }
    .promo-body p, .promo-body ul, .promo-body li {
      color:#334155;
      font-size:15px;
      line-height:1.5;
    }
    .promo-body strong {
      color:#0369a1;
    }
  </style>
</head>
<body>
  <!--Topbar -->
  <div class="kk-marquee-wrap">
  <div class="container">
    <div class="kk-marquee">
      ✨ XIN KÍNH CHÀO QUÝ KHÁCH ĐẾN VỚI K&K Pet Shop ✨
    </div>
  </div>
</div>


<!-- Navbar -->
<nav class="navbar">
  <div class="container kk-header-main">

    <!-- Logo -->
    <a href="index.php" class="navbar-brand d-flex align-items-center">
      <img src="logo.png" alt="K&K PetShop" class="logo-img me-2">
    </a>

    <!-- Ô tìm kiếm -->
    <form class="kk-search" action="search.php" method="get">
      <input type="text"
             name="q"
             class="kk-search-input"
             placeholder="Bạn muốn tìm gì ...">
      <button type="submit" class="kk-search-btn">
        🔍
      </button>
    </form>

    <!-- Các icon bên phải -->
    <div class="kk-actions">
      <?php if (!empty($userRole) && $userRole === 'Admin'): ?>
        <a href="admin.php" class="kk-action-item">
          <span class="kk-action-icon">🛠️</span>
          <span>Admin</span>
        </a>
   <?php elseif (!empty($userName)): ?>
  <a href="profile.php" class="kk-action-item">
    <span class="kk-action-icon">👤</span>
    <span><?=h($userName)?></span>
  </a>


      <?php else: ?>
        <a href="login.php?redirect=profile.php" class="kk-action-item">

          <span class="kk-action-icon">👤</span>
          <span>Đăng nhập</span>
        </a>
      <?php endif; ?>

      <?php if (!empty($userName) || (!empty($userRole) && $userRole === 'Admin')): ?>
        <a href="logout.php" class="kk-action-item">
          <span class="kk-action-icon">🚪</span>
          <span>Đăng xuất</span>
        </a>
      <?php endif; ?>
      <a href="cart.php" class="kk-action-item">
        <span class="kk-action-icon">🛒</span>
        <span>Giỏ hàng</span>
      </a>
      <a href="view_orders.php" class="kk-action-item">
        <span class="kk-action-icon">📦</span>
        <span>Kiểm tra đơn</span>
      </a>
      <a href="tel:0921803567" class="kk-action-item">
        <span class="kk-action-icon">📞</span>
        <span>0921803567</span>
      </a>
    </div>

  </div>
</nav>
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="container mt-3">
    <?php foreach ($_SESSION['flash'] as $f): ?>
      <div class="alert alert-<?=htmlspecialchars($f['type'])?>"><?=(htmlspecialchars($f['msg']))?></div>
    <?php endforeach; unset($_SESSION['flash']); ?>
  </div>
<?php endif; ?>
<!-- Category Bar -->
<div class="kk-category-bar">
  <div class="kk-category-inner">
      <a href="#" class="cat-item">Thức ăn</a>
      <a href="#" class="cat-item">Phụ kiện</a>
      <a href="#" class="cat-item">Đồ chơi</a>
      <a href="#" class="cat-item">Ưu đãi</a>
  </div>
</div>



<!-- Hero + Featured carousel -->
<section class="hero">
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-5">
        <span class="badge-hero mb-2">Pet care everyday</span>
        <h1 class="hero-title mb-3">Siêu thị thú cưng K&amp;K Pet Shop</h1>
        <p class="hero-sub mb-3">
          Thức ăn, phụ kiện, đồ chơi cho chó mèo. Hàng chính hãng, giao nhanh trong ngày, nhiều ưu đãi mỗi tuần.
        </p>
        <ul class="hero-sub mb-3">
          <li>✨ Giảm đến 20% cho khách hàng mới</li>
          <li>🚚 Miễn phí vận chuyển nội thành với đơn từ 500.000đ</li>
          <li>💬 Tư vấn dinh dưỡng miễn phí cho thú cưng</li>
        </ul>
        <a href="#" class="btn-kk btn-kk-primary">Mua sắm ngay</a>
        <a href="#" class="btn-kk btn-kk-outline">Xem ưu đãi thức ăn</a>

      </div>

      <div class="col-lg-7">
        <?php if (!empty($featuredProducts)): ?>
        <div id="featuredCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4500">
          <div class="carousel-indicators">
            <?php foreach ($featuredProducts as $idx => $fp): ?>
              <button type="button" data-bs-target="#featuredCarousel" data-bs-slide-to="<?=$idx?>" class="<?=$idx === 0 ? 'active':''?>" aria-current="<?=$idx===0?'true':'false'?>"></button>
            <?php endforeach; ?>
          </div>
          <div class="carousel-inner">
            <?php foreach ($featuredProducts as $idx => $fp):
              $gia = (float)$fp['Gia'];
              $giam = $fp['PhanTramGiam'] !== null ? (float)$fp['PhanTramGiam'] : 0;
              $giaSauGiam = $gia;
              if ($giam > 0) $giaSauGiam = $gia * (1 - $giam/100);
            ?>
            <div class="carousel-item <?=$idx===0?'active':''?>">
              <div class="row featured-card">
                <div class="col-md-5 p-0">
                  <?php if ($fp['HinhAnh']): ?>
                    <img src="<?=h($fp['HinhAnh'])?>" class="featured-img" alt="<?=h($fp['TenSanPham'])?>">
                  <?php else: ?>
                    <div class="featured-img d-flex align-items-center justify-content-center">
                      <span class="text-muted">Không có ảnh</span>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="col-md-7 p-4 d-flex flex-column justify-content-center">
                  <div class="featured-label mb-1">Sản phẩm nổi bật</div>
                  <h3 class="mb-2" style="font-weight:700; font-size:22px;"><?=h($fp['TenSanPham'])?></h3>
                  <p class="mb-2 text-muted" style="font-size:14px;"><?=h($fp['MoTa'] ?: 'Sản phẩm phù hợp cho thú cưng của bạn.')?></p>
                  <div class="price-wrap mb-2">
                   <span class="price">
                       <?= number_format($giaSauGiam, 0, ',', '.') ?>đ
                   </span>

                   <?php if ($giam > 0): ?>
                       <span class="old-price">
                           <?= number_format($gia, 0, ',', '.') ?>đ
                       </span>
                   <?php endif; ?>
               </div>
                
                  <div class="mb-3">
                    <span class="stock-badge">Còn lại: <?=h($fp['SoLuong'])?> sp</span>
                  </div>
                  <button class="btn btn-primary btn-lg btn-buy-now" data-id="<?=h($fp['MaSanPham'])?>" data-name="<?=h($fp['TenSanPham'])?>" data-stock="<?=h($fp['SoLuong'])?>" <?=$fp['SoLuong'] <= 0 ? 'disabled' : ''?>>
                    <?=$fp['SoLuong'] > 0 ? 'Mua ngay sản phẩm này' : 'Hết hàng'?>
                  </button>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php else: ?>
          <div class="text-center text-muted py-5">
            Chưa có sản phẩm nào để hiển thị. Vui lòng thêm sản phẩm trong trang Admin.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Products grid -->
<div id="products">
  <div class="container mt-4">

  <!-- Phụ kiện cho chó -->
  <div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="section-title mb-0">Phụ kiện cho chó</h4>
    </div>
    <div class="row g-3">
      <?php if (empty($dogProducts)): ?>
        <div class="col-12 text-muted">Không có phụ kiện cho chó.</div>
      <?php else: ?>
        <?php foreach($dogProducts as $p):
          $gia = (float)$p['Gia'];
          $giam = $p['PhanTramGiam'] !== null ? (float)$p['PhanTramGiam'] : 0;
          $giaSauGiam = $gia; if ($giam > 0) $giaSauGiam = $gia * (1 - $giam/100);
        ?>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="product-card position-relative">
              <?php if ($giam > 0): ?><div class="discount-badge">-<?=h($giam)?>%</div><?php endif; ?>
              <?php if ($p['HinhAnh']): ?><img src="<?=h($p['HinhAnh'])?>" class="product-img" alt="<?=h($p['TenSanPham'])?>"><?php else: ?><div class="product-img d-flex align-items-center justify-content-center"><span class="text-muted">Không có ảnh</span></div><?php endif; ?>
              <div class="p-3 d-flex flex-column flex-grow-1">
                <div class="mb-1" style="min-height:48px; font-weight:600; font-size:14px;"><?=h($p['TenSanPham'])?></div>
                <div class="mb-2"><span class="price"><?=number_format($giaSauGiam,0,',','.')?>đ</span><?php if ($giam > 0): ?><span class="old-price"><?=number_format($gia,0,',','.')?>đ</span><?php endif; ?></div>
                <div class="d-flex gap-2 mt-auto"><button class="btn btn-primary w-100 btn-sm btn-buy-now" data-id="<?=h($p['MaSanPham'])?>" data-name="<?=h($p['TenSanPham'])?>" data-stock="<?=h($p['SoLuong'])?>" <?= $p['SoLuong'] <= 0 ? 'disabled' : '' ?>><?= $p['SoLuong'] > 0 ? 'Mua ngay' : 'Hết hàng' ?></button><button type="button" class="btn btn-primary btn-sm flex-grow-1 btn-view-detail" data-id="<?=h($p['MaSanPham'])?>" data-name="<?=h($p['TenSanPham'])?>" data-desc="<?=h($p['MoTa'] ?? 'Chưa có mô tả')?>">Xem chi tiết</button></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Phụ kiện cho mèo -->
  <div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="section-title mb-0">Phụ kiện cho mèo</h4>
    </div>
    <div class="row g-3">
      <?php if (empty($catProducts)): ?>
        <div class="col-12 text-muted">Không có phụ kiện cho mèo.</div>
      <?php else: ?>
        <?php foreach($catProducts as $p):
          $gia = (float)$p['Gia'];
          $giam = $p['PhanTramGiam'] !== null ? (float)$p['PhanTramGiam'] : 0;
          $giaSauGiam = $gia; if ($giam > 0) $giaSauGiam = $gia * (1 - $giam/100);
        ?>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="product-card position-relative">
              <?php if ($giam > 0): ?><div class="discount-badge">-<?=h($giam)?>%</div><?php endif; ?>
              <?php if ($p['HinhAnh']): ?><img src="<?=h($p['HinhAnh'])?>" class="product-img" alt="<?=h($p['TenSanPham'])?>"><?php else: ?><div class="product-img d-flex align-items-center justify-content-center"><span class="text-muted">Không có ảnh</span></div><?php endif; ?>
              <div class="p-3 d-flex flex-column flex-grow-1">
                <div class="mb-1" style="min-height:48px; font-weight:600; font-size:14px;"><?=h($p['TenSanPham'])?></div>
                <div class="mb-2"><span class="price"><?=number_format($giaSauGiam,0,',','.')?>đ</span><?php if ($giam > 0): ?><span class="old-price"><?=number_format($gia,0,',','.')?>đ</span><?php endif; ?></div>
                <div class="d-flex gap-2 mt-auto"><button class="btn btn-primary w-100 btn-sm btn-buy-now" data-id="<?=h($p['MaSanPham'])?>" data-name="<?=h($p['TenSanPham'])?>" data-stock="<?=h($p['SoLuong'])?>" <?= $p['SoLuong'] <= 0 ? 'disabled' : '' ?>><?= $p['SoLuong'] > 0 ? 'Mua ngay' : 'Hết hàng' ?></button><button type="button" class="btn btn-primary btn-sm flex-grow-1 btn-view-detail" data-id="<?=h($p['MaSanPham'])?>" data-name="<?=h($p['TenSanPham'])?>" data-desc="<?=h($p['MoTa'] ?? 'Chưa có mô tả')?>">Xem chi tiết</button></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Sản phẩm dùng chung -->
  <div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="section-title mb-0">Phụ kiện dùng chung cho chó &amp; mèo</h4>
    </div>
    <div class="row g-3">
      <?php if (empty($sharedProducts)): ?>
        <div class="col-12 text-muted">Không có phụ kiện dùng chung.</div>
      <?php else: ?>
        <?php foreach($sharedProducts as $p):
          $gia = (float)$p['Gia'];
          $giam = $p['PhanTramGiam'] !== null ? (float)$p['PhanTramGiam'] : 0;
          $giaSauGiam = $gia; if ($giam > 0) $giaSauGiam = $gia * (1 - $giam/100);
        ?>
          <div class="col-6 col-md-4 col-lg-3">
            <div class="product-card position-relative">
              <?php if ($giam > 0): ?><div class="discount-badge">-<?=h($giam)?>%</div><?php endif; ?>
              <?php if ($p['HinhAnh']): ?><img src="<?=h($p['HinhAnh'])?>" class="product-img" alt="<?=h($p['TenSanPham'])?>"><?php else: ?><div class="product-img d-flex align-items-center justify-content-center"><span class="text-muted">Không có ảnh</span></div><?php endif; ?>
              <div class="p-3 d-flex flex-column flex-grow-1">
                <div class="mb-1" style="min-height:48px; font-weight:600; font-size:14px;"><?=h($p['TenSanPham'])?></div>
                <div class="mb-2"><span class="price"><?=number_format($giaSauGiam,0,',','.')?>đ</span><?php if ($giam > 0): ?><span class="old-price"><?=number_format($gia,0,',','.')?>đ</span><?php endif; ?></div>
                <div class="d-flex gap-2 mt-auto"><button class="btn btn-primary w-100 btn-sm btn-buy-now" data-id="<?=h($p['MaSanPham'])?>" data-name="<?=h($p['TenSanPham'])?>" data-stock="<?=h($p['SoLuong'])?>" <?= $p['SoLuong'] <= 0 ? 'disabled' : '' ?>><?= $p['SoLuong'] > 0 ? 'Mua ngay' : 'Hết hàng' ?></button><button type="button" class="btn btn-primary btn-sm flex-grow-1 btn-view-detail" data-id="<?=h($p['MaSanPham'])?>" data-name="<?=h($p['TenSanPham'])?>" data-desc="<?=h($p['MoTa'] ?? 'Chưa có mô tả')?>">Xem chi tiết</button></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>
</div>

<!-- Product Detail Modal -->
<div class="modal fade" id="productDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailProductName">Chi tiết sản phẩm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="detailProductDesc" style="line-height:1.6; white-space:pre-wrap;">Mô tả...</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
      </div>
    </div>
  </div>
</div>

<!-- Purchase Modal -->
<div class="modal fade" id="purchaseModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="purchaseProductName">Mua sản phẩm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="purchaseForm">
          <input type="hidden" id="purchaseProductId" name="MaSanPham">
          <div class="mb-3">
            <label for="quantity" class="form-label">Chọn số lượng:</label>
            <select class="form-select" id="quantity" name="quantity" required>
              <option value="">-- Chọn số lượng --</option>
            </select>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" formaction="cart.php" formmethod="post">Thêm vào giỏ hàng</button>
           
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<footer class="mt-5">
  <div class="container">
    <div class="row">
      <div class="col-md-6">
        <strong>K&K PetShop</strong><br>
        Địa chỉ: C7D/30M2 Phạm Hùng, Bình Hưng, Bình Chánh, TP HCM<br>
        Hotline: 0900 000 000
      </div>
      <div class="col-md-6 text-md-end mt-3 mt-md-0">
        &copy; <?=date('Y')?> K&K PetShop - Đồ án quản lý sản phẩm thú cưng.
      </div>
    </div>
  </div>
</footer>

<!-- Popup khuyến mãi (Modal giữa màn hình) -->
<div class="modal fade promo-modal" id="promoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="promo-header d-flex justify-content-between align-items-center">
        <div>
          <div style="font-size:14px;opacity:.9;">K&K PetShop ưu đãi đặc biệt</div>
          <h5 class="mb-0" style="font-weight:800;">Tuần Lễ Vàng Cho Boss Yêu 🐶🐱</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="promo-body">
        <div class="row align-items-center g-3">
          <div class="col-md-7">
            <span class="promo-tag">Khuyến mãi hot</span>
            <h5 class="mb-2" style="font-weight:700;">Giảm ngay 15% cho đơn hàng đầu tiên</h5>
            <p class="mb-2" style="font-size:14px; color:#4b5563;">
              Đăng ký tài khoản và đặt hàng hôm nay để nhận ưu đãi giảm 15% cho đơn đầu tiên,
              áp dụng cho tất cả thức ăn &amp; phụ kiện thú cưng. Số lượng mã có hạn.
            </p>
            <ul style="font-size:13px; color:#4b5563;">
              <li>Nhập mã: <strong>KKPET15</strong> ở bước thanh toán</li>
              <li>Áp dụng cho đơn từ <strong>300.000đ</strong></li>
              <li>Miễn phí ship nội thành cho đơn từ 500.000đ</li>
            </ul>
          </div>
          <div class="col-md-5 text-center">
            <img src="https://images.pexels.com/photos/5731865/pexels-photo-5731865.jpeg?auto=compress&cs=tinysrgb&w=600"
                 alt="Khuyến mãi thú cưng" class="img-fluid rounded-4 mb-2">
            <div style="font-size:12px; color:#6b7280;">Ưu đãi chỉ áp dụng trong tuần này.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Hiển thị popup khuyến mãi khi người dùng truy cập
document.addEventListener('DOMContentLoaded', function () {
  var promoModalEl = document.getElementById('promoModal');
  if (!promoModalEl) return;

  var promoModal = new bootstrap.Modal(promoModalEl);

  // Luôn luôn hiện, bỏ localStorage
  setTimeout(function () {
    promoModal.show();
  }, 600);
});

// Xử lý nút "Xem chi tiết" để hiển thị modal sản phẩm
document.querySelectorAll('.btn-view-detail').forEach(btn => {
  btn.addEventListener('click', function() {
    const name = this.dataset.name;
    const desc = this.dataset.desc;
    document.getElementById('detailProductName').textContent = name;
    document.getElementById('detailProductDesc').textContent = desc;
    new bootstrap.Modal(document.getElementById('productDetailModal')).show();
  });
});

// Xử lý nút "Mua ngay" để mở modal chọn số lượng
document.querySelectorAll('.btn-buy-now').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    const id = this.dataset.id;
    const name = this.dataset.name;
    const stock = parseInt(this.dataset.stock || '0', 10);
    if (!id) return;

    document.getElementById('purchaseProductId').value = id;
    document.getElementById('purchaseProductName').textContent = name || 'Mua sản phẩm';

    const qty = document.getElementById('quantity');
    if (qty) {
      // Clear old options, keep placeholder
      qty.innerHTML = '<option value="">-- Chọn số lượng --</option>';
      
      // Limit to min(stock, 20)
      const maxQty = Math.min(stock > 0 ? stock : 1, 20);
      
      // Generate options 1 to maxQty
      for (let i = 1; i <= maxQty; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = i;
        if (i === 1) opt.selected = true; // Select 1 by default
        qty.appendChild(opt);
      }
      
      if (stock <= 0) {
        qty.setAttribute('disabled', 'disabled');
      } else {
        qty.removeAttribute('disabled');
      }
    }

    new bootstrap.Modal(document.getElementById('purchaseModal')).show();
  });
});

// Giới hạn input số lượng (clamp theo min/max)
const _qtyEl = document.getElementById('quantity');
if (_qtyEl) {
  _qtyEl.addEventListener('change', function() {
    // Select không cần clamp, giá trị đã validate
  });
}


</script>
</body>
</html>
