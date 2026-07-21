<?php
/**
 * Tubİsg - Gelişmiş Rol Tabanlı Yetkilendirme (RBAC) Paneli
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('users_manage');

$db = getDB();

// Form Post İşlemleri (Rol Ekleme / Güncelleme)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_role') {
        $role_id = (int)($_POST['role_id'] ?? 0);
        $role_name = trim($_POST['role_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        $permsInput = $_POST['perms'] ?? [];
        $permissions = [
            'surveys_manage' => isset($permsInput['surveys_manage']),
            'units_manage'   => isset($permsInput['units_manage']),
            'audit_conduct'  => isset($permsInput['audit_conduct']),
            'audit_view'     => isset($permsInput['audit_view']),
            'audit_delete'   => isset($permsInput['audit_delete']),
            'reports_export' => isset($permsInput['reports_export']),
            'users_manage'   => isset($permsInput['users_manage']),
            'logs_view'      => isset($permsInput['logs_view']),
        ];
        $permsJson = json_encode($permissions);

        if ($role_id > 0) {
            // Güncelleme
            $stmt = $db->prepare("UPDATE roles SET role_name = ?, description = ?, permissions = ? WHERE id = ?");
            $stmt->execute([$role_name, $description, $permsJson, $role_id]);
            log_action('Rol Yetkileri Güncellendi', "Rol: {$role_name} (ID: #{$role_id}) yetkileri güncellendi.");
            set_flash('success', 'Rol yetkileri güncellendi.');
        } else {
            // Yeni Rol
            $stmt = $db->prepare("INSERT INTO roles (role_name, description, permissions) VALUES (?, ?, ?)");
            $stmt->execute([$role_name, $description, $permsJson]);
            log_action('Yeni Rol Tanımlandı', "Yeni rol eklendi: {$role_name}");
            set_flash('success', 'Yeni rol tanımlandı.');
        }
        header("Location: roles.php");
        exit;
    }
}

$roles = $db->query("SELECT r.*, COUNT(u.id) as user_count FROM roles r LEFT JOIN users u ON r.id = u.role_id GROUP BY r.id ORDER BY r.id ASC")->fetchAll();

$pageTitle = 'Rol & Yetki Yönetimi (RBAC)';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h3 class="fw-extrabold m-0">Rol & Yetki Yönetimi</h3>
    <p class="text-muted fs-7 m-0">Kullanıcıların sistemde neleri yapıp yapamayacağını ince ayrıntısıyla yönetin.</p>
  </div>
  <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addRoleModal">
    <i class="bi bi-shield-plus"></i> Yeni Rol Tanımla
  </button>
</div>

<div class="row g-4 mb-5">
  <?php foreach ($roles as $role): ?>
    <?php
    $perms = json_decode($role['permissions'], true) ?? [];
    $isSuperAdmin = (int)$role['id'] === 1;
    ?>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="custom-card h-100 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="badge bg-dark text-white font-weight-bold">Rol ID: #<?php echo $role['id']; ?></span>
            <span class="badge bg-light text-muted border"><?php echo $role['user_count']; ?> Kullanıcı</span>
          </div>
          <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($role['role_name']); ?></h5>
          <p class="text-muted fs-7 mb-3"><?php echo htmlspecialchars($role['description'] ?? ''); ?></p>

          <h6 class="fs-8 text-uppercase text-muted font-weight-bold mb-2">İzin Detayları:</h6>
          <ul class="list-unstyled fs-7 mb-0">
            <li class="mb-1">
              <i class="bi bi-<?php echo ($isSuperAdmin || !empty($perms['surveys_manage'])) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
              Anket & Soru Yönetimi
            </li>
            <li class="mb-1">
              <i class="bi bi-<?php echo ($isSuperAdmin || !empty($perms['units_manage'])) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
              Birim Tanımlama & Yönetimi
            </li>
            <li class="mb-1">
              <i class="bi bi-<?php echo ($isSuperAdmin || !empty($perms['audit_conduct'])) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
              Saha Denetimi Doldurma
            </li>
            <li class="mb-1">
              <i class="bi bi-<?php echo ($isSuperAdmin || !empty($perms['audit_view'])) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
              Denetim Raporlarını İzleme
            </li>
            <li class="mb-1">
              <i class="bi bi-<?php echo ($isSuperAdmin || !empty($perms['audit_delete'])) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
              Denetim Raporu Silme Yetkisi
            </li>
            <li class="mb-1">
              <i class="bi bi-<?php echo ($isSuperAdmin || !empty($perms['reports_export'])) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
              PDF/Excel/Word Dışa Aktarma
            </li>
            <li class="mb-1">
              <i class="bi bi-<?php echo ($isSuperAdmin || !empty($perms['users_manage'])) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
              Kullanıcı & Rol Yönetimi
            </li>
            <li class="mb-1">
              <i class="bi bi-<?php echo ($isSuperAdmin || !empty($perms['logs_view'])) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
              Sistem Loglarını Görüntüleme
            </li>
          </ul>
        </div>

        <div class="mt-4 pt-3 border-top text-end">
          <?php if (!$isSuperAdmin): ?>
            <button class="btn btn-sm btn-outline-primary fw-bold w-100" data-bs-toggle="modal" data-bs-target="#editRoleModal<?php echo $role['id']; ?>">
              <i class="bi bi-pencil"></i> Yetkileri Düzenle
            </button>
          <?php else: ?>
            <span class="badge bg-light text-muted border w-100 p-2"><i class="bi bi-lock-fill text-danger"></i> Tam Yetkili Rol (Düzenlenemez)</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Rol Düzenleme Modal -->
    <?php if (!$isSuperAdmin): ?>
    <div class="modal fade" id="editRoleModal<?php echo $role['id']; ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST" action="roles.php">
            <input type="hidden" name="action" value="save_role">
            <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
            <div class="modal-header">
              <h5 class="modal-title fw-bold">Rol Düzenle: <?php echo htmlspecialchars($role['role_name']); ?></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-start">
              <div class="mb-3">
                <label class="form-label fw-bold">Rol Adı</label>
                <input type="text" name="role_name" class="form-control" value="<?php echo htmlspecialchars($role['role_name']); ?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label fw-bold">Açıklama</label>
                <input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars($role['description'] ?? ''); ?>">
              </div>

              <label class="form-label fw-bold text-uppercase fs-8 text-muted mb-2">İzin ve Erişim Ayarları:</label>
              
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="perms[surveys_manage]" id="perm1_<?php echo $role['id']; ?>" <?php echo !empty($perms['surveys_manage']) ? 'checked' : ''; ?>>
                <label class="form-check-label fw-bold" for="perm1_<?php echo $role['id']; ?>">Anket Profil ve Soru Yönetimi</label>
              </div>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="perms[units_manage]" id="perm2_<?php echo $role['id']; ?>" <?php echo !empty($perms['units_manage']) ? 'checked' : ''; ?>>
                <label class="form-check-label fw-bold" for="perm2_<?php echo $role['id']; ?>">Birim / Saha Tanımlama ve Yönetimi</label>
              </div>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="perms[audit_conduct]" id="perm3_<?php echo $role['id']; ?>" <?php echo !empty($perms['audit_conduct']) ? 'checked' : ''; ?>>
                <label class="form-check-label fw-bold" for="perm3_<?php echo $role['id']; ?>">Saha Denetimi Yapma (Anket Doldurma)</label>
              </div>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="perms[audit_view]" id="perm4_<?php echo $role['id']; ?>" <?php echo !empty($perms['audit_view']) ? 'checked' : ''; ?>>
                <label class="form-check-label fw-bold" for="perm4_<?php echo $role['id']; ?>">Denetim Raporlarını Görüntüleme</label>
              </div>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="perms[audit_delete]" id="perm4b_<?php echo $role['id']; ?>" <?php echo !empty($perms['audit_delete']) ? 'checked' : ''; ?>>
                <label class="form-check-label fw-bold text-danger" for="perm4b_<?php echo $role['id']; ?>">Denetim Raporu Silme Yetkisi</label>
              </div>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="perms[reports_export]" id="perm5_<?php echo $role['id']; ?>" <?php echo !empty($perms['reports_export']) ? 'checked' : ''; ?>>
                <label class="form-check-label fw-bold" for="perm5_<?php echo $role['id']; ?>">PDF / Excel / Word Rapor Dışa Aktarma</label>
              </div>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="perms[users_manage]" id="perm6_<?php echo $role['id']; ?>" <?php echo !empty($perms['users_manage']) ? 'checked' : ''; ?>>
                <label class="form-check-label fw-bold text-primary" for="perm6_<?php echo $role['id']; ?>">Kullanıcı ve Rol Yönetimi</label>
              </div>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="perms[logs_view]" id="perm7_<?php echo $role['id']; ?>" <?php echo !empty($perms['logs_view']) ? 'checked' : ''; ?>>
                <label class="form-check-label fw-bold text-info" for="perm7_<?php echo $role['id']; ?>">Sistem İşlem Loglarını Görüntüleme</label>
              </div>

            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
              <button type="submit" class="btn btn-success">Değişiklikleri Kaydet</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>

  <?php endforeach; ?>
</div>

<!-- Yeni Rol Ekle Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="roles.php">
        <input type="hidden" name="action" value="save_role">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-shield-plus text-success"></i> Yeni Rol Tanımla</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-start">
          <div class="mb-3">
            <label class="form-label fw-bold">Rol Adı</label>
            <input type="text" name="role_name" class="form-control" placeholder="Örn: Bölge Müdürü" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Açıklama</label>
            <input type="text" name="description" class="form-control" placeholder="Rolün kısa tanımı">
          </div>

          <label class="form-label fw-bold text-uppercase fs-8 text-muted mb-2">İzinler:</label>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="perms[surveys_manage]" id="new_perm1">
            <label class="form-check-label" for="new_perm1">Anket & Soru Yönetimi</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="perms[units_manage]" id="new_perm2">
            <label class="form-check-label" for="new_perm2">Birim / Saha Tanımlama</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="perms[audit_conduct]" id="new_perm3" checked>
            <label class="form-check-label" for="new_perm3">Denetim Yapma</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="perms[audit_view]" id="new_perm4" checked>
            <label class="form-check-label" for="new_perm4">Raporları İzleme</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="perms[audit_delete]" id="new_perm4b">
            <label class="form-check-label text-danger" for="new_perm4b">Denetim Raporu Silme Yetkisi</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="perms[reports_export]" id="new_perm5" checked>
            <label class="form-check-label" for="new_perm5">PDF / Excel / Word Rapor Alımı</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="perms[users_manage]" id="new_perm6">
            <label class="form-check-label text-primary" for="new_perm6">Kullanıcı Yönetimi</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="perms[logs_view]" id="new_perm7">
            <label class="form-check-label text-info" for="new_perm7">Sistem İşlem Logları</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-success">Rolü Oluştur</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
