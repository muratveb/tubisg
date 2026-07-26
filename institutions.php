<?php
/**
 * Tubİsg - Kurum Tanımları Yönetimi (institutions.php)
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('units_manage');

$db = getDB();

// Kurum Ekleme / Düzenleme / Silme İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_institution') {
        $instName = trim($_POST['institution_name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($instName)) {
            set_flash('danger', 'Kurum adı zorunludur.');
        } else {
            $stmt = $db->prepare("INSERT INTO institutions (institution_name, code, description, address, phone) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$instName, $code, $desc, $address, $phone]);
            log_action('Kurum Eklendi', "Yeni Kurum: {$instName}");
            set_flash('success', "Kurum ({$instName}) başarıyla eklendi.");
        }
        header("Location: institutions.php");
        exit;
    }

    if ($action === 'edit_institution') {
        $instId = (int)($_POST['institution_id'] ?? 0);
        $instName = trim($_POST['institution_name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($instId > 0 && !empty($instName)) {
            $stmt = $db->prepare("UPDATE institutions SET institution_name = ?, code = ?, description = ?, address = ?, phone = ? WHERE id = ?");
            $stmt->execute([$instName, $code, $desc, $address, $phone, $instId]);
            log_action('Kurum Güncellendi', "Kurum ID #{$instId}: {$instName}");
            set_flash('success', 'Kurum bilgileri güncellendi.');
        }
        header("Location: institutions.php");
        exit;
    }

    if ($action === 'delete_institution') {
        $instId = (int)($_POST['institution_id'] ?? 0);
        if ($instId > 0) {
            $stmt = $db->prepare("DELETE FROM institutions WHERE id = ?");
            $stmt->execute([$instId]);
            log_action('Kurum Silindi', "Kurum ID #{$instId}");
            set_flash('success', 'Kurum başarıyla silindi.');
        }
        header("Location: institutions.php");
        exit;
    }
}

// Tüm Kurumları Çek
$institutions = $db->query("SELECT i.*, (SELECT COUNT(*) FROM audits a WHERE a.institution_id = i.id) as audit_count FROM institutions i ORDER BY i.institution_name ASC")->fetchAll();

$pageTitle = 'Kurum Tanımları';
include __DIR__ . '/includes/header.php';
?>

<!-- Üst Başlık & Buton -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div>
    <h3 class="fw-extrabold m-0"><i class="bi bi-hospital-fill text-danger me-2"></i> Kurum Tanımları</h3>
    <p class="text-muted fs-7 m-0">Saha risk denetimi yapılacak kurumları (Örn: Dicle Üniversitesi Hastaneleri, Tıp Fakültesi) yönetin.</p>
  </div>
  <div>
    <button type="button" class="btn btn-danger font-weight-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addInstitutionModal">
      <i class="bi bi-plus-lg me-1"></i> + Yeni Kurum Ekle
    </button>
  </div>
</div>

<!-- Kurumlar Listesi Tablosu -->
<div class="custom-card p-0 overflow-hidden border">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0" style="font-size: 0.85rem;">
      <thead class="table-dark">
        <tr>
          <th style="width: 50px;">#</th>
          <th>KURUM ADI</th>
          <th>KOD / KISALTMA</th>
          <th>AÇIKLAMA</th>
          <th>DENETİM SAYISI</th>
          <th>DURUM</th>
          <th style="width: 130px;" class="text-end">İŞLEMLER</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($institutions)): ?>
          <tr>
            <td colspan="7" class="text-center py-4 text-muted">Henüz kayıtlı bir kurum bulunmuyor.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($institutions as $inst): ?>
            <tr>
              <td class="fw-bold">#<?php echo $inst['id']; ?></td>
              <td class="fw-bold text-dark fs-7">
                <i class="bi bi-hospital text-danger me-1"></i> <?php echo htmlspecialchars($inst['institution_name']); ?>
              </td>
              <td>
                <span class="badge bg-light text-dark border fw-bold"><?php echo htmlspecialchars($inst['code'] ?? '-'); ?></span>
              </td>
              <td class="text-muted"><?php echo htmlspecialchars($inst['description'] ?? '-'); ?></td>
              <td>
                <span class="badge bg-info text-dark font-weight-bold"><?php echo $inst['audit_count']; ?> Denetim</span>
              </td>
              <td>
                <?php if ($inst['is_active']): ?>
                  <span class="badge bg-success">Aktif</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Pasif</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                        onclick="editInstitution(<?php echo htmlspecialchars(json_encode($inst)); ?>)">
                  <i class="bi bi-pencil-square"></i> Düzenle
                </button>
                <form method="POST" action="institutions.php" class="d-inline confirm-delete-form" data-confirm-title="Kurum Sil" data-confirm-text="Bu kurumu silmek istediğinize emin misiniz?">
                  <input type="hidden" name="action" value="delete_institution">
                  <input type="hidden" name="institution_id" value="<?php echo $inst['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- YENİ KURUM EKLE MODAL -->
<div class="modal fade" id="addInstitutionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <form method="POST" action="institutions.php">
        <input type="hidden" name="action" value="add_institution">
        <div class="modal-header bg-dark text-white p-3 rounded-top-4">
          <h5 class="modal-title fw-extrabold text-white"><i class="bi bi-hospital me-2 text-danger"></i> Yeni Kurum Ekle</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Kurum Tam Adı *</label>
            <input type="text" name="institution_name" class="form-control" placeholder="Örn: Dicle Üniversitesi Hastaneleri" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Kurum Kodu / Kısaltması</label>
            <input type="text" name="code" class="form-control" placeholder="Örn: DUH">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Açıklama / Detay</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Kurum hakkında ek detaylar..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light rounded-bottom-4">
          <button type="button" class="btn btn-secondary font-weight-bold" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-danger font-weight-bold px-4">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- KURUM DÜZENLE MODAL -->
<div class="modal fade" id="editInstitutionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <form method="POST" action="institutions.php">
        <input type="hidden" name="action" value="edit_institution">
        <input type="hidden" name="institution_id" id="edit_inst_id">
        <div class="modal-header bg-dark text-white p-3 rounded-top-4">
          <h5 class="modal-title fw-extrabold text-white"><i class="bi bi-pencil-square me-2 text-primary"></i> Kurum Düzenle</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Kurum Tam Adı *</label>
            <input type="text" name="institution_name" id="edit_inst_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Kurum Kodu / Kısaltması</label>
            <input type="text" name="code" id="edit_inst_code" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Açıklama / Detay</label>
            <textarea name="description" id="edit_inst_desc" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light rounded-bottom-4">
          <button type="button" class="btn btn-secondary font-weight-bold" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-primary font-weight-bold px-4">Güncelle</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editInstitution(inst) {
  document.getElementById('edit_inst_id').value = inst.id;
  document.getElementById('edit_inst_name').value = inst.institution_name || '';
  document.getElementById('edit_inst_code').value = inst.code || '';
  document.getElementById('edit_inst_desc').value = inst.description || '';
  
  const modal = new bootstrap.Modal(document.getElementById('editInstitutionModal'));
  modal.show();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
