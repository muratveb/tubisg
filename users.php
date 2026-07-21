<?php
/**
 * Tubİsg - Kullanıcı Hesapları ve Rol Atama Yönetimi
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('users_manage');

$db = getDB();

// Form İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $name_surname = trim($_POST['name_surname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 0);

        if (!empty($username) && !empty($password) && !empty($name_surname) && $role_id > 0) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, name_surname, email, role_id) VALUES (?, ?, ?, ?, ?)");
            try {
                $stmt->execute([$username, $hash, $name_surname, $email, $role_id]);
                set_flash('success', 'Kullanıcı hesabı oluşturuldu.');
            } catch (PDOException $e) {
                set_flash('danger', 'Bu kullanıcı adı zaten kullanılıyor.');
            }
        }
        header("Location: users.php");
        exit;
    }

    if ($_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $name_surname = trim($_POST['name_surname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role_id = (int)($_POST['role_id'] ?? 0);
        $new_password = trim($_POST['new_password'] ?? '');

        if ($id > 0 && !empty($name_surname) && $role_id > 0) {
            if (!empty($new_password)) {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET name_surname = ?, email = ?, role_id = ?, password = ? WHERE id = ?");
                $stmt->execute([$name_surname, $email, $role_id, $hash, $id]);
            } else {
                $stmt = $db->prepare("UPDATE users SET name_surname = ?, email = ?, role_id = ? WHERE id = ?");
                $stmt->execute([$name_surname, $email, $role_id, $id]);
            }
            set_flash('success', 'Kullanıcı bilgileri güncellendi.');
        }
        header("Location: users.php");
        exit;
    }

    if ($_POST['action'] === 'toggle_active') {
        $id = (int)$_POST['id'];
        if ($id > 1) { // Super Admin 1'in durumu değiştirilemez
            $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('info', 'Kullanıcı aktiflik durumu güncellendi.');
        }
        header("Location: users.php");
        exit;
    }
}

// Kullanıcıları ve Rollerini Çek
$users = $db->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.id ASC")->fetchAll();
$roles = $db->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

$pageTitle = 'Kullanıcı Hesapları Yönetimi';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h3 class="fw-extrabold m-0">Kullanıcı Hesapları</h3>
    <p class="text-muted fs-7 m-0">Saha denetçileri ve sistem kullanıcılarının rol atamaları</p>
  </div>
  <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addUserModal">
    <i class="bi bi-person-plus-fill"></i> Yeni Kullanıcı Ekle
  </button>
</div>

<div class="custom-card">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0">
      <thead class="table-light fs-8 text-uppercase text-muted">
        <tr>
          <th>Kullanıcı</th>
          <th>Kullanıcı Adı</th>
          <th>E-Posta</th>
          <th>Rol</th>
          <th>Durum</th>
          <th class="text-end">İşlem</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar-circle">
                  <?php echo mb_substr($u['name_surname'], 0, 1, 'UTF-8'); ?>
                </div>
                <div class="fw-bold text-dark"><?php echo htmlspecialchars($u['name_surname']); ?></div>
              </div>
            </td>
            <td class="font-weight-bold text-secondary"><?php echo htmlspecialchars($u['username']); ?></td>
            <td class="text-muted fs-7"><?php echo htmlspecialchars($u['email'] ?? '-'); ?></td>
            <td>
              <span class="badge bg-primary-light text-primary font-weight-bold p-2">
                <?php echo htmlspecialchars($u['role_name']); ?>
              </span>
            </td>
            <td>
              <?php if ($u['is_active']): ?>
                <span class="badge bg-success-light text-success p-2"><i class="bi bi-check-circle"></i> Aktif</span>
              <?php else: ?>
                <span class="badge bg-danger-light text-danger p-2"><i class="bi bi-x-circle"></i> Pasif</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <button class="btn btn-sm btn-light text-primary me-1" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $u['id']; ?>">
                <i class="bi bi-pencil-fill"></i>
              </button>
              <?php if ((int)$u['id'] !== 1): ?>
                <form method="POST" action="users.php" style="display:inline;">
                  <input type="hidden" name="action" value="toggle_active">
                  <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-light text-<?php echo $u['is_active'] ? 'warning' : 'success'; ?>">
                    <i class="bi bi-power"></i>
                  </button>
                </form>
              <?php endif; ?>
            </td>
          </tr>

          <!-- Kullanıcı Düzenleme Modal -->
          <div class="modal fade" id="editUserModal<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="POST" action="users.php">
                  <input type="hidden" name="action" value="edit">
                  <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                  <div class="modal-header">
                    <h5 class="modal-title fw-bold">Kullanıcı Düzenle: <?php echo htmlspecialchars($u['name_surname']); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body text-start">
                    <div class="mb-3">
                      <label class="form-label fw-bold">Ad Soyad</label>
                      <input type="text" name="name_surname" class="form-control" value="<?php echo htmlspecialchars($u['name_surname']); ?>" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-bold">E-Posta</label>
                      <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-bold">Rol</label>
                      <select name="role_id" class="form-select" required>
                        <?php foreach ($roles as $r): ?>
                          <option value="<?php echo $r['id']; ?>" <?php echo $r['id'] == $u['role_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['role_name']); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label fw-bold">Yeni Parola (Değiştirmek istemiyorsanız boş bırakın)</label>
                      <input type="password" name="new_password" class="form-control" placeholder="••••••••">
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">Kaydet</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Yeni Kullanıcı Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="users.php">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-success"></i> Yeni Kullanıcı Tanımla</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Ad Soyad</label>
            <input type="text" name="name_surname" class="form-control" placeholder="Örn: Mehmet Demir" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Kullanıcı Adı</label>
            <input type="text" name="username" class="form-control" placeholder="Örn: mdemir" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Parola</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">E-Posta</label>
            <input type="email" name="email" class="form-control" placeholder="mehmet@tubisg.com">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Atanacak Rol</label>
            <select name="role_id" class="form-select" required>
              <?php foreach ($roles as $r): ?>
                <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-success">Oluştur</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
