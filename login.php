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

            header("Location: index.php");
            exit;
        } else {
            $error = 'Kullanıcı adı veya parola hatalı!';
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
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(16px);
      border-radius: var(--radius-lg);
      padding: 40px 32px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }
    .login-brand-icon {
      width: 64px;
      height: 64px;
      background: linear-gradient(135deg, #10b981, #059669);
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 2rem;
      margin: 0 auto 16px auto;
      box-shadow: 0 10px 20px rgba(5, 150, 105, 0.4);
    }
  </style>
</head>
<body>

<div class="login-card">
  <div class="text-center mb-4">
    <div class="login-brand-icon">
      <i class="bi bi-shield-check"></i>
    </div>
    <h3 class="fw-extrabold text-dark mb-1">Tub<span class="text-success">İsg</span></h3>
    <p class="text-muted fs-7">Saha İş Sağlığı ve Güvenliği Denetim Portalı</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 fs-7 mb-4">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <div><?php echo htmlspecialchars($error); ?></div>
    </div>
  <?php endif; ?>

  <form method="POST" action="login.php">
    <div class="mb-3">
      <label class="form-label fw-bold text-secondary fs-7">Kullanıcı Adı</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
        <input type="text" name="username" class="form-control bg-light border-start-0" placeholder="Örn: admin" required autofocus>
      </div>
    </div>

    <div class="mb-4">
      <label class="form-label fw-bold text-secondary fs-7">Parola</label>
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
        <input type="password" name="password" class="form-control bg-light border-start-0" placeholder="••••••••" required>
      </div>
    </div>

    <button type="submit" class="btn btn-primary-custom w-100 py-3 text-uppercase font-weight-bold letter-spacing-1">
      <i class="bi bi-box-arrow-in-right fs-5"></i>
      Giriş Yap
    </button>
  </form>

  <div class="mt-4 pt-3 border-top text-center text-muted fs-8">
    <small>Varsayılan Yönetici: <strong>admin</strong> / <strong>admin123</strong></small>
  </div>
</div>

</body>
</html>
