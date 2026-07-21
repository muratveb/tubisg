<?php
/**
 * Tubİsg - Modern Login Screen
 */
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $db = getDB();
        $stmt = $db->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.username = ? AND u.is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Başarılı giriş
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name_surname'] = $user['name_surname'];

            log_action('Oturum Açma', 'Kullanıcı sisteme başarıyla giriş yaptı.');

            header("Location: index.php");
            exit;
        } else {
            $error = 'Kullanıcı adı veya parola hatalı!';
            log_action('Başarısız Giriş Denemesi', "Kullanıcı adı: {$username}");
        }
    } else {
        $error = 'Lütfen tüm alanları doldurun.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Giriş Yap | Tubİsg Saha Denetim Sistemi</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  
  <style>
    body {
      background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .login-card {
      background: rgba(255, 255, 255, 0.98);
      border-radius: var(--radius-lg);
      box-shadow: 0 20px 40px rgba(0,0,0,0.3);
      width: 100%;
      max-width: 420px;
      padding: 32px 28px;
    }
  </style>
</head>
<body>

<div class="login-card text-center">
  <div class="mb-4">
    <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle mb-3" style="width:60px; height:60px; font-size:1.8rem; box-shadow: 0 6px 16px rgba(16, 185, 129, 0.4);">
      <i class="bi bi-shield-check"></i>
    </div>
    <h3 class="fw-extrabold text-dark m-0">Tub<span class="text-success">İsg</span> Portal</h3>
    <p class="text-muted fs-8">Saha İş Sağlığı ve Güvenliği Platformu</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger p-2 fs-7 mb-3 text-start">
      <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="login.php" class="text-start">
    <div class="mb-3">
      <label class="form-label font-weight-bold fs-7">Kullanıcı Adı</label>
      <div class="input-group">
        <span class="input-group-text bg-light"><i class="bi bi-person-fill text-muted"></i></span>
        <input type="text" name="username" class="form-control" placeholder="Örn: admin" required autofocus>
      </div>
    </div>

    <div class="mb-4">
      <label class="form-label font-weight-bold fs-7">Parola</label>
      <div class="input-group">
        <span class="input-group-text bg-light"><i class="bi bi-lock-fill text-muted"></i></span>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
    </div>

    <button type="submit" class="btn btn-primary-custom w-100 py-3 font-weight-bold fs-6">
      <i class="bi bi-box-arrow-in-right fs-5"></i> Giriş Yap
    </button>
  </form>

  <div class="mt-4 pt-3 border-top text-center text-muted fs-8">
    © <?php echo date('Y'); ?> Tubİsg - Tüm Hakları Saklıdır
  </div>
</div>

</body>
</html>
