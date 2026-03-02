
<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'config.php';

$error = '';

// Lấy redirect an toàn (chống rỗng + chống link ngoài)
$redirect = '';
if (isset($_POST['redirect'])) $redirect = trim($_POST['redirect']);
elseif (isset($_GET['redirect'])) $redirect = trim($_GET['redirect']);

if ($redirect === '') $redirect = 'index.php';
if (strpos($redirect, '://') !== false) $redirect = 'index.php';
if (substr($redirect, 0, 2) === '//') $redirect = 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM NguoiDung WHERE Email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['MatKhau'])) {
            $_SESSION['user_id'] = $user['MaNguoiDung'];
            $_SESSION['user_name'] = $user['HoTen'];
            $_SESSION['user_role'] = $user['VaiTro'];

            if ($user['VaiTro'] === 'Admin') {
                $_SESSION['is_admin'] = true;
                header('Location: admin.php');
                exit;
            } else {
                header('Location: ' . $redirect);
                exit;
            }
        } else {
            $error = 'Email hoặc mật khẩu không chính xác.';
        }
    }
}



function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>Đăng nhập - K&K PetShop</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f5f6fa; }
    .box {
      max-width:420px;
      margin:60px auto;
      background:#fff;
      padding:24px;
      border-radius:12px;
      box-shadow:0 8px 25px rgba(0,0,0,0.08);
    }
  </style>
</head>
<body>
<div class="box">
  <h4 class="mb-3 text-center">Đăng nhập</h4>
  <?php if ($error): ?>
    <div class="alert alert-danger py-2"><?=h($error)?></div>
  <?php endif; ?>

  <form method="post">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect'] ?? '') ?>">

    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required value="<?=h($_POST['email'] ?? '')?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Mật khẩu</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button class="btn btn-primary w-100 mb-2">Đăng nhập</button>
    <div class="text-center">
      Chưa có tài khoản? <a href="register.php">Đăng ký</a>
    </div>
  </form>
</div>
</body>
</html>
