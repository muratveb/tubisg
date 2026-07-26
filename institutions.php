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

$totalCount = count($institutions);
$totalAudits = array_sum(array_column($institutions, 'audit_count'));

$pageTitle = 'Kurum Tanımları';
include __DIR__ . '/includes/header.php';
?>

<style>
.inst-card-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(220, 38, 38, 0.1);
  color: #dc2626;
  font-size: 1.25rem;
}
.btn-action-edit {
  background-color: #e0f2fe;
  color: #0369a1;
  border: none;
  transition: all 0.2s ease;
}
.btn-action-edit:hover {
  background-color: #0284c7;
  color: #ffffff;
  transform: translateY(-1px);
}
.btn-action-delete {
  background-color: #fee2e2;
  color: #b91c1c;
  border: none;
  transition: all 0.2s ease;
}
.btn-action-delete:hover {
  background-color: #dc2626;
  color: #ffffff;
  transform: translateY(-1px);
}
</style>

<!-- Üst Başlık Banner & İstatistik Kartları -->
<div class="custom-card p-4 mb-4 border-0 shadow-sm rounded-4 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3">
      <div class="p-3 bg-danger bg-opacity-25 rounded-3 text-white border border-danger border-opacity-25 fs-3">
        <i class="bi bi-hospital"></i>
      </div>
      <div>
        <h3 class="fw-extrabold m-0 text-white">Kurum Tanımları</h3>
        <p class="text-white-50 fs-7 m-0">Saha İSG denetimi yürütülen resmi kurumlar ve yerleşkeler listesi.</p>
      </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-3">
      <div class="bg-white bg-opacity-10 px-3 py-2 rounded-3 text-center">
        <span class="d-block fs-8 text-white-50 font-weight-bold">TOPLAM KURUM</span>
        <span class="fw-extrabold fs-6 text-white"><?php echo $totalCount; ?></span>
      </div>
      <div class="bg-white bg-opacity-10 px-3 py-2 rounded-3 text-center">
        <span class="d-block fs-8 text-white-50 font-weight-bold">GERÇEKLEŞEN DENETİM</span>
        <span class="fw-extrabold fs-6 text-warning"><?php echo $totalAudits; ?></span>
      </div>
      <button type="button" class="btn btn-danger font-weight-bold px-4 py-2 shadow-sm rounded-3 text-nowrap" data-bs-toggle="modal" data-bs-target="#addInstitutionModal">
        <i class="bi bi-plus-circle-fill me-1"></i> Yeni Kurum Ekle
      </button>
    </div>
  </div>
</div>

<!-- Modern Kurumlar Tablosu -->
<div class="custom-card p-0 overflow-hidden border-0 shadow-sm rounded-4 mb-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0" style="font-size: 0.85rem;">
      <thead class="bg-light text-secondary text-uppercase fs-8" style="letter-spacing: 0.5px;">
        <tr>
          <th style="width: 60px;" class="ps-4">ID</th>
          <th>KURUM DETAYLARI</th>
          <th style="width: 140px;">KOD</th>
          <th style="width: 160px;">DENETİM SAYISI</th>
          <th style="width: 120px;">DURUM</th>
          <th style="width: 180px;" class="text-end pe-4">İŞLEMLER</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php if (empty($institutions)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-hospital fs-1 d-block mb-2 text-secondary opacity-50"></i>
              Henüz tanımlı bir kurum bulunmuyor. Yukarıdaki <strong>Yeni Kurum Ekle</strong> butonundan ekleyebilirsiniz.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($institutions as $inst): ?>
            <tr>
              <td class="ps-4 fw-bold text-muted">#<?php echo $inst['id']; ?></td>
              <td>
                <div class="d-flex align-items-center gap-3">
                  <div class="inst-card-icon">
                    <i class="bi bi-building"></i>
                  </div>
                  <div>
                    <div class="fw-extrabold text-dark fs-7"><?php echo htmlspecialchars($inst['institution_name']); ?></div>
                    <div class="text-muted fs-8 mt-1">
                      <?php echo htmlspecialchars($inst['description'] ?? 'Açıklama belirtilmemiş'); ?>
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge bg-secondary-subtle text-dark border font-weight-bold px-2 py-1 fs-8">
                  <?php echo htmlspecialchars($inst['code'] ?? 'KODSUZ'); ?>
                </span>
              </td>
              <td>
                <span class="badge bg-info-subtle text-info font-weight-bold px-3 py-2 rounded-pill fs-8">
                  <i class="bi bi-clipboard-data-fill me-1"></i> <?php echo $inst['audit_count']; ?> Denetim
                </span>
              </td>
              <td>
                <?php if ($inst['is_active']): ?>
                  <span class="badge bg-success-subtle text-success font-weight-bold px-3 py-1 rounded-pill fs-8">
                    <i class="bi bi-check-circle-fill me-1"></i> Aktif
                  </span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary font-weight-bold px-3 py-1 rounded-pill fs-8">
                    Pasif
                  </span>
                <?php endif; ?>
              </td>
              <td class="text-end pe-4">
                <div class="d-inline-flex align-items-center gap-2">
                  <button type="button" class="btn btn-sm btn-action-edit font-weight-bold px-3 py-1 rounded-3" 
                          onclick="editInstitution(<?php echo htmlspecialchars(json_encode($inst)); ?>)"
                          title="Kurum Bilgilerini Düzenle">
                    <i class="bi bi-pencil-square me-1"></i> Düzenle
                  </button>
                  <form method="POST" action="institutions.php" class="d-inline confirm-delete-form" data-confirm-title="Kurum Sil" data-confirm-text="Bu kurumu silmek istediğinize emin misiniz?">
                    <input type="hidden" name="action" value="delete_institution">
                    <input type="hidden" name="institution_id" value="<?php echo $inst['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-action-delete font-weight-bold px-2 py-1 rounded-3" title="Kurumu Sil">
                      <i class="bi bi-trash3-fill"></i> Sil
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- YENİ KURUM EKLE MODAL (Modernized UI) -->
<div class="modal fade" id="addInstitutionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <form method="POST" action="institutions.php">
        <input type="hidden" name="action" value="add_institution">
        <div class="modal-header bg-gradient bg-dark text-white p-3 px-4">
          <h5 class="modal-title fw-extrabold text-white fs-6"><i class="bi bi-hospital me-2 text-danger"></i> Yeni Kurum Tanımla</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Kurum Tam Adı *</label>
            <input type="text" name="institution_name" class="form-control form-control-lg fs-7" placeholder="Örn: Dicle Üniversitesi Hastaneleri" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Kurum Kodu / Kısaltması</label>
            <input type="text" name="code" class="form-control" placeholder="Örn: DUH">
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Açıklama / Detay</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Kurum veya yerleşke hakkında açıklayıcı detaylar..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light p-3 px-4">
          <button type="button" class="btn btn-secondary font-weight-bold px-3" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-danger font-weight-bold px-4">Kaydet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- KURUM DÜZENLE MODAL (Modernized UI) -->
<div class="modal fade" id="editInstitutionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <form method="POST" action="institutions.php">
        <input type="hidden" name="action" value="edit_institution">
        <input type="hidden" name="institution_id" id="edit_inst_id">
        <div class="modal-header bg-gradient bg-dark text-white p-3 px-4">
          <h5 class="modal-title fw-extrabold text-white fs-6"><i class="bi bi-pencil-square me-2 text-primary"></i> Kurum Bilgilerini Düzenle</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Kurum Tam Adı *</label>
            <input type="text" name="institution_name" id="edit_inst_name" class="form-control form-control-lg fs-7" required>
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
        <div class="modal-footer bg-light p-3 px-4">
          <button type="button" class="btn btn-secondary font-weight-bold px-3" data-bs-dismiss="modal">Vazgeç</button>
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
