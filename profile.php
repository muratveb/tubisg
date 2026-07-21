<?php
/**
 * Tubİsg - Profil Bilgileri ve Parola Güncelleme Ekranı
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$db = getDB();
$user = get_current_user_data();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_surname = trim($_POST['name_surname'] ?? '');
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $new_password_confirm = trim($_POST['new_password_confirm'] ?? '');

    $error = false;

    if (empty($name_surname)) {
        set_flash('danger', 'Ad Soyad alanı boş bırakılamaz.');
        $error = true;
    }

    if (!$error && !empty($new_password)) {
        // Mevcut parolayı doğrula
        if (!password_verify($current_password, $user['password'])) {
            set_flash('danger', 'Mevcut parolanız hatalı. Şifre değiştirilemedi.');
            $error = true;
        } elseif ($new_password !== $new_password_confirm) {
            set_flash('danger', 'Yeni parolalar birbiriyle eşleşmiyor.');
            $error = true;
        } elseif (strlen($new_password) < 4) {
            set_flash('danger', 'Yeni parola en az 4 karakter olmalıdır.');
            $error = true;
        }
    }

    if (!$error) {
        if (!empty($new_password)) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET name_surname = ?, password = ? WHERE id = ?");
            $stmt->execute([$name_surname, $new_hash, $user['id']]);
        } else {
            $stmt = $db->prepare("UPDATE users SET name_surname = ? WHERE id = ?");
            $stmt->execute([$name_surname, $user['id']]);
        }

        $_SESSION['name_surname'] = $name_surname;
        set_flash('success', 'Profil bilgileriniz başarıyla güncellendi.');
        header("Location: profile.php");
        exit;
    }
}

$pageTitle = 'Profilim & Hesap Ayarları';
include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-6">
    <div class="custom-card">
      <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
        <div class="avatar-circle" style="width: 54px; height: 54px; font-size: 1.4rem;">
          <?php echo mb_substr($user['name_surname'], 0, 1, 'UTF-8'); ?>
        </div>
        <div>
          <h4 class="fw-extrabold text-dark m-0"><?php echo htmlspecialchars($user['name_surname']); ?></h4>
          <span class="badge bg-primary-light text-primary font-weight-bold fs-8"><?php echo htmlspecialchars($user['role_name']); ?></span>
        </div>
      </div>

      <form method="POST" action="profile.php">
        
        <!-- Değiştirilemeyen Sabit Bilgiler -->
        <div class="mb-3">
          <label class="form-label fw-bold text-muted fs-8 text-uppercase">Kullanıcı Adı (Değiştirilemez)</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-person-lock text-muted"></i></span>
            <input type="text" class="form-control bg-light" value="<?php echo htmlspecialchars($user['username']); ?>" disabled readonly>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold text-muted fs-8 text-uppercase">E-Posta Adresi (Değiştirilemez)</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="bi bi-envelope-lock text-muted"></i></span>
            <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($user['email'] ?? 'Belirtilmemiş'); ?>" disabled readonly>
          </div>
        </div>

        <hr class="my-4">

        <!-- Düzenlenebilir Bilgiler -->
        <div class="mb-4">
          <label class="form-label fw-bold text-dark fs-7">Ad Soyad</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person-lines-fill text-success"></i></span>
            <input type="text" name="name_surname" class="form-control" value="<?php echo htmlspecialchars($user['name_surname']); ?>" required>
          </div>
        </div>

        <div class="custom-card bg-light p-3 border mb-4">
          <h6 class="fw-bold text-dark mb-3"><i class="bi bi-key-fill text-warning"></i> Parola Değiştir (İsteğe Bağlı)</h6>
          
          <div class="mb-3">
            <label class="form-label fw-semibold fs-8">Mevcut Parola</label>
            <input type="password" name="current_password" class="form-control form-control-sm" placeholder="Şifrenizi değiştirmek istiyorsanız girin">
          </div>

          <div class="row g-2">
            <div class="col-12 col-sm-6">
              <label class="form-label fw-semibold fs-8">Yeni Parola</label>
              <input type="password" name="new_password" class="form-control form-control-sm" placeholder="En az 4 karakter">
            </div>
            <div class="col-12 col-sm-6">
              <label class="form-label fw-semibold fs-8">Yeni Parola (Tekrar)</label>
              <input type="password" name="new_password_confirm" class="form-control form-control-sm" placeholder="Yeni parolayı onaylayın">
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary-custom w-100 py-3 font-weight-bold">
          <i class="bi bi-check-circle-fill"></i> Profil Bilgilerini Güncelle
        </button>

      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
