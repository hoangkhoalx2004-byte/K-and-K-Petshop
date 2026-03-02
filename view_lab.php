<?php
header('Content-Type: text/html; charset=utf-8');

$LABS_DIR = __DIR__ . "/labs";
$LABS_URL = "labs";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$lab = $_GET['lab'] ?? '';
$lab = trim($lab);

// chặn path traversal: không cho ../
if ($lab === '' || str_contains($lab, '..') || str_contains($lab, '/') || str_contains($lab, '\\')) {
  http_response_code(400);
  echo "Lab không hợp lệ.";
  exit;
}

$path = $LABS_DIR . "/" . $lab;
if (!is_dir($path)) {
  http_response_code(404);
  echo "Không tìm thấy folder lab.";
  exit;
}

$items = scandir($path);
$files = [];
$folders = [];

foreach ($items as $it) {
  if ($it === "." || $it === "..") continue;
  $full = $path . "/" . $it;

  // ẩn file nguy hiểm/không cần
  if ($it === ".htaccess") continue;

  if (is_dir($full)) $folders[] = $it;
  else $files[] = $it;
}

sort($folders, SORT_NATURAL | SORT_FLAG_CASE);
sort($files, SORT_NATURAL | SORT_FLAG_CASE);

function fileIcon($name){
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  return match($ext){
    'php' => '🟣',
    'html','htm' => '🟠',
    'css' => '🔵',
    'js' => '🟡',
    'png','jpg','jpeg','gif','webp' => '🖼️',
    'pdf' => '📄',
    'zip','rar' => '🗜️',
    default => '📄',
  };
}
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Xem nội dung <?=h($lab)?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{ background:#0b1020; color:#fff; }
    .wrap{ max-width:980px; margin:26px auto; padding:0 14px; }
    .cardx{ background:#111a33; border:1px solid rgba(255,255,255,.08); border-radius:16px; }
    a{ text-decoration:none; }
    .item{
      background: rgba(255,255,255,.04);
      border:1px solid rgba(255,255,255,.08);
      border-radius:14px;
      padding:12px 14px;
      display:flex; justify-content:space-between; align-items:center;
      margin-bottom:10px;
    }
    .item:hover{ background: rgba(255,255,255,.07); }
    .muted{ color: rgba(255,255,255,.7); font-size:13px; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-1">📁 <?=h($lab)?></h3>
      <div class="muted">Bấm vào file để mở (tab mới).</div>
    </div>
    <a class="btn btn-outline-light" href="baocao.php">⬅ Quay về báo cáo</a>
  </div>

  <div class="cardx p-3">
    <?php if (empty($folders) && empty($files)): ?>
      <div class="alert alert-warning mb-0">Folder này đang trống.</div>
    <?php endif; ?>

    <?php if (!empty($folders)): ?>
      <div class="mb-2 fw-bold">Thư mục con</div>
      <?php foreach ($folders as $fo): ?>
        <?php $url = $LABS_URL . "/" . rawurlencode($lab) . "/" . rawurlencode($fo) . "/"; ?>
        <a href="<?=h($url)?>" target="_blank">
<div class="item">
            <div>📁 <?=h($fo)?></div>
            <span class="badge text-bg-light">Mở</span>
          </div>
        </a>
      <?php endforeach; ?>
      <hr class="border-secondary">
    <?php endif; ?>

    <?php if (!empty($files)): ?>
      <div class="mb-2 fw-bold">File</div>
      <?php foreach ($files as $fi): ?>
        <?php
          $icon = fileIcon($fi);
          $url = $LABS_URL . "/" . rawurlencode($lab) . "/" . rawurlencode($fi);
        ?>
        <a href="<?=h($url)?>" target="_blank">
          <div class="item">
            <div><?= $icon ?> <?=h($fi)?></div>
            <span class="badge text-bg-primary">Mở</span>
          </div>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
