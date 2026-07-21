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
            'reports_export' => isset($permsInput['reports_export']),
            'users_manage'   => isset($permsInput['users_manage']),
        ];
        $permsJson = json_encode($permissions);

        if ($role_id > 0) {
            // Güncelleme
            $stmt = $db->prepare("UPDATE roles SET role_name = ?, description = ?, permissions = ? WHERE id = ?");
            $stmt->execute([$role_name, $description, $permsJson, $role_id]);
            set_flash('success', 'Rol yetkileri güncellendi.');
        } else {
            // Yeni Rol
            $stmt = $db->prepare("INSERT INTO roles (role_name, description, permissions) VALUES (?, ?, ?)");
            $stmt->execute([$role_name, $description, $permsJson]);
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

<div class="row g-4">
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
              <i class="bi bi-<?php echo ($isSuperAdmin || !empty($perms['reports_export'])) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
              PDF/Excel/Word Dışa Aktarma
            </li>
            <li class="mb-1">
              <i class="bi bi-<?php echo ($isSuperAdmin || !empty($perms['users_manage'])) ? 'check-circle-fill text-success' : 'x-circle-fill text-danger'; ?>"></i>
              Kullanıcı & Rol Yönetimi
            </li>
          </ul>
        </div>

        <div class="pt-3 border-top mt-3 text-end">
          <?php if ($isSuperAdmin): ?>
            <span class="badge bg-success-light text-success p-2">Tam Yetkili Sistem Rolü</span>
          <?php else: ?>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editRoleModal<?php echo $role['id']; ?>">
              <i class="bi bi-sliders"></i> Yetkileri Düzenle
            </button>
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
                <h5 class="modal-title fw-bold">Rol Yetkilerini Düzenle: <?php echo htmlspecialchars($role['role_name']); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label class="form-label fw-bold">Rol Adı</label>
                  <input type="text" name="role_name" class="form-control" value="<?php echo htmlspecialchars($role['role_name']); ?>" required>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-bold">Açıklama</label>
                  <input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars($role['description'] ?? ''); ?>">
                </div>
                <div class="mb-2 fw-bold text-dark">Modül Erişim Yetkileri</div>
                
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" name="perms[surveys_manage]" id="p1_<?php echo $role['id']; ?>" <?php echo !empty($perms['surveys_manage']) ? 'checked' : ''; ?>>
                  <label class="form-check-label fw-semibold" for="p1_<?php echo $role['id']; ?>">Anket & Soru Tanımlama Yetkisi</label>
                </div>
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" name="perms[units_manage]" id="p2_<?php echo $role['id']; ?>" <?php echo !empty($perms['units_manage']) ? 'checked' : ''; ?>>
                  <label class="form-check-label fw-semibold" for="p2_<?php echo $role['id']; ?>">Birim Tanımlama Yetkisi</label>
                </div>
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" name="perms[audit_conduct]" id="p3_<?php echo $role['id']; ?>" <?php echo !empty($perms['audit_conduct']) ? 'checked' : ''; ?>>
                  <label class="form-check-label fw-semibold" for="p3_<?php echo $role['id']; ?>">Sahada Denetim Başlatma & Doldurma</label>
                </div>
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" name="perms[audit_view]" id="p4_<?php echo $role['id']; ?>" <?php echo !empty($perms['audit_view']) ? 'checked' : ''; ?>>
                  <label class="form-check-label fw-semibold" for="p4_<?php echo $role['id']; ?>">Denetim Raporlarını Görüntüleme</label>
                </div>
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" name="perms[reports_export]" id="p5_<?php echo $role['id']; ?>" <?php echo !empty($perms['reports_export']) ? 'checked' : ''; ?>>
                  <label class="form-check-label fw-semibold" for="p5_<?php echo $role['id']; ?>">PDF / Excel / Word Rapor Alma</label>
                </div>
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" type="checkbox" name="perms[users_manage]" id="p6_<?php echo $role['id']; ?>" <?php echo !empty($perms['users_manage']) ? 'checked' : ''; ?>>
                  <label class="form-check-label fw-semibold" for="p6_<?php echo $role['id']; ?>">Kullanıcı ve Rol Yönetimi</label>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="submit" class="btn btn-success">Yetkileri Kaydet</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

<!-- Yeni Rol Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="roles.php">
        <input type="hidden" name="action" value="save_role">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-shield-plus text-success"></i> Yeni Rol Oluştur</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Rol Adı</label>
            <input type="text" name="role_name" class="form-control" placeholder="Örn: Kalite Kontrol Sorumlusu" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Rol Açıklaması</label>
            <input type="text" name="description" class="form-control" placeholder="Rolün görev ve sorumluluk alanı">
          </div>
          <div class="mb-2 fw-bold text-dark">Modül Yetkileri</div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="perms[audit_conduct]" id="np3" checked>
            <label class="form-check-label fw-semibold" for="np3">Sahada Denetim Başlatma & Doldurma</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="perms[audit_view]" id="np4" checked>
            <label class="form-check-label fw-semibold" for="np4">Denetim Raporlarını Görüntüleme</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="perms[reports_export]" id="np5" checked>
            <label class="form-check-label fw-semibold" for="np5">PDF / Excel / Word Rapor Alma</label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
          <button type="submit" class="btn btn-success">Oluştur</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
