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
$activeCount = count(array_filter($institutions, fn($i) => $i['is_active'] == 1));
$totalAudits = array_sum(array_column($institutions, 'audit_count'));

$pageTitle = 'Kurum Tanımları';
include __DIR__ . '/includes/header.php';
?>

<!-- Üst Başlık & Ekleme Butonu -->
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
  <div>
    <h3 class="fw-extrabold m-0 text-dark d-flex align-items-center gap-2">
      <i class="bi bi-hospital-fill text-danger"></i> Kurum Tanımları
    </h3>
    <p class="text-muted fs-7 m-0 mt-1">Saha risk denetimi yürütülen resmi kurumları (Örn: Dicle Üniversitesi Hastaneleri) yönetin.</p>
  </div>
  <div>
    <button type="button" class="btn btn-danger font-weight-bold px-4 py-2.5 shadow-sm rounded-3 text-nowrap" data-bs-toggle="modal" data-bs-target="#addInstitutionModal">
      <i class="bi bi-plus-circle-fill me-1.5"></i> + Yeni Kurum Ekle
    </button>
  </div>
</div>

<!-- 3 İstatistik Özeti Kartları -->
<div class="row g-3 mb-4">
  <div class="col-12 col-md-4">
    <div class="custom-card p-3 mb-0 border-0 shadow-sm rounded-4 bg-white d-flex align-items-center gap-3">
      <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3 fs-4">
        <i class="bi bi-hospital"></i>
      </div>
      <div>
        <span class="d-block text-uppercase text-muted font-weight-bold fs-8" style="letter-spacing: 0.5px;">TOPLAM KURUM</span>
        <span class="fw-extrabold fs-4 text-dark"><?php echo $totalCount; ?></span>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-4">
    <div class="custom-card p-3 mb-0 border-0 shadow-sm rounded-4 bg-white d-flex align-items-center gap-3">
      <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 fs-4">
        <i class="bi bi-check-circle-fill"></i>
      </div>
      <div>
        <span class="d-block text-uppercase text-muted font-weight-bold fs-8" style="letter-spacing: 0.5px;">AKTİF KURUMLAR</span>
        <span class="fw-extrabold fs-4 text-success"><?php echo $activeCount; ?></span>
      </div>
    </div>
  </div>

  <div class="col-12 col-md-4">
    <div class="custom-card p-3 mb-0 border-0 shadow-sm rounded-4 bg-white d-flex align-items-center gap-3">
      <div class="p-3 bg-warning bg-opacity-10 text-warning-emphasis rounded-3 fs-4">
        <i class="bi bi-clipboard2-check-fill text-warning"></i>
      </div>
      <div>
        <span class="d-block text-uppercase text-muted font-weight-bold fs-8" style="letter-spacing: 0.5px;">GERÇEKLEŞEN DENETİM</span>
        <span class="fw-extrabold fs-4 text-dark"><?php echo $totalAudits; ?></span>
      </div>
    </div>
  </div>
</div>

<!-- Arama Barı ve Kurum Tablosu Kartı -->
<div class="custom-card p-0 overflow-hidden border-0 shadow-sm rounded-4 mb-4">
  <!-- Canlı Filtre Arama Kutusu -->
  <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between gap-3">
    <div class="input-group input-group-sm style-search" style="max-width: 320px;">
      <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
      <input type="text" id="instSearchInput" class="form-control border-start-0 ps-0" placeholder="Kurum adı veya kod ara...">
    </div>
    <span class="text-muted fs-8 font-weight-bold">Toplam <?php echo $totalCount; ?> kayıt gösteriliyor</span>
  </div>

  <div class="table-responsive">
    <table class="table table-hover align-middle m-0" style="font-size: 0.85rem;">
      <thead class="table-dark">
        <tr>
          <th style="width: 60px;" class="ps-3 text-center">ID</th>
          <th>KURUM DETAYLARI</th>
          <th style="width: 130px;">KOD / KISALTMA</th>
          <th style="width: 150px;" class="text-center">DENETİM SAYISI</th>
          <th style="width: 110px;" class="text-center">DURUM</th>
          <th style="width: 170px;" class="text-end pe-3">İŞLEMLER</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($institutions)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-hospital fs-1 d-block mb-2 text-secondary opacity-50"></i>
              Henüz tanımlı bir kurum bulunmuyor. Sağ üstteki <strong>+ Yeni Kurum Ekle</strong> butonundan ekleyebilirsiniz.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($institutions as $inst): ?>
            <tr class="inst-row" data-name="<?php echo mb_strtolower($inst['institution_name'] . ' ' . $inst['code'], 'UTF-8'); ?>">
              <td class="ps-3 text-center fw-bold text-muted">#<?php echo $inst['id']; ?></td>
              <td>
                <div class="d-flex align-items-center gap-3">
                  <div class="p-2.5 bg-danger bg-opacity-10 text-danger rounded-3 fs-5 flex-shrink-0">
                    <i class="bi bi-building"></i>
                  </div>
                  <div>
                    <div class="fw-extrabold text-dark fs-7"><?php echo htmlspecialchars($inst['institution_name']); ?></div>
                    <div class="text-muted fs-8 mt-0.5">
                      <?php echo htmlspecialchars($inst['description'] ?? 'Açıklama girilmemiş'); ?>
                    </div>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge bg-light text-dark border font-weight-bold px-2.5 py-1.5 fs-8">
                  <?php echo htmlspecialchars($inst['code'] ?? 'KODSUZ'); ?>
                </span>
              </td>
              <td class="text-center">
                <span class="badge bg-info-subtle text-info-emphasis font-weight-bold px-3 py-1.5 rounded-pill fs-8">
                  <i class="bi bi-clipboard-data-fill me-1"></i> <?php echo $inst['audit_count']; ?> Denetim
                </span>
              </td>
              <td class="text-center">
                <?php if ($inst['is_active']): ?>
                  <span class="badge bg-success-subtle text-success font-weight-bold px-2.5 py-1.5 rounded-pill fs-8">
                    <i class="bi bi-check-circle-fill me-1"></i> Aktif
                  </span>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary font-weight-bold px-2.5 py-1.5 rounded-pill fs-8">
                    Pasif
                  </span>
                <?php endif; ?>
              </td>
              <td class="text-end pe-3">
                <div class="d-inline-flex align-items-center gap-1">
                  <button type="button" class="btn btn-sm btn-outline-primary font-weight-bold px-2.5 py-1 rounded-2" 
                          onclick="editInstitution(<?php echo htmlspecialchars(json_encode($inst)); ?>)"
                          title="Düzenle">
                    <i class="bi bi-pencil-square me-1"></i> Düzenle
                  </button>
                  <form method="POST" action="institutions.php" class="d-inline confirm-delete-form" data-confirm-title="Kurum Sil" data-confirm-text="Bu kurumu silmek istediğinize emin misiniz?">
                    <input type="hidden" name="action" value="delete_institution">
                    <input type="hidden" name="institution_id" value="<?php echo $inst['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger font-weight-bold px-2.5 py-1 rounded-2" title="Sil">
                      <i class="bi bi-trash-fill"></i> Sil
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

<!-- YENİ KURUM EKLE MODAL -->
<div class="modal fade" id="addInstitutionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <form method="POST" action="institutions.php">
        <input type="hidden" name="action" value="add_institution">
        <div class="modal-header bg-dark text-white p-3 px-4">
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

<!-- KURUM DÜZENLE MODAL -->
<div class="modal fade" id="editInstitutionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <form method="POST" action="institutions.php">
        <input type="hidden" name="action" value="edit_institution">
        <input type="hidden" name="institution_id" id="edit_inst_id">
        <div class="modal-header bg-dark text-white p-3 px-4">
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

// Canlı Arama Filtrelemesi
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('instSearchInput');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const q = this.value.toLowerCase().trim();
      const rows = document.querySelectorAll('.inst-row');
      rows.forEach(row => {
        const name = row.dataset.name || '';
        if (name.includes(q)) {
          row.style.display = '';
        } else {
          row.style.display = 'none';
        }
      });
    });
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
