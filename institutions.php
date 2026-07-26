<?php
/**
 * Tubİsg - Kurum Tanımları Yönetimi (institutions.php)
 * Ultra-Modern SaaS Dashboard UI (Grid Cards & Direct Audit Filter Link)
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

<style>
.inst-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.inst-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px -4px rgba(0, 0, 0, 0.1);
  border-color: #cbd5e1;
}
.inst-avatar {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #fee2e2 0%, #fecdd3 100%);
  color: #dc2626;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  flex-shrink: 0;
  box-shadow: 0 4px 10px rgba(220, 38, 38, 0.15);
  transition: transform 0.2s ease;
}
.inst-card:hover .inst-avatar {
  transform: scale(1.08);
}
.hover-title-link:hover .hover-title {
  color: #dc2626 !important;
  text-decoration: underline;
}
.audit-pill-link {
  transition: all 0.2s ease;
}
.audit-pill-link:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 10px rgba(2, 132, 199, 0.2);
}
.btn-soft-primary {
  background-color: #f0f9ff;
  color: #0284c7;
  border: 1px solid #e0f2fe;
  font-weight: 700;
  transition: all 0.2s ease;
}
.btn-soft-primary:hover {
  background-color: #0284c7;
  color: #ffffff;
  border-color: #0284c7;
  transform: translateY(-1px);
}
.btn-soft-danger {
  background-color: #fef2f2;
  color: #dc2626;
  border: 1px solid #fee2e2;
  font-weight: 700;
  transition: all 0.2s ease;
}
.btn-soft-danger:hover {
  background-color: #dc2626;
  color: #ffffff;
  border-color: #dc2626;
  transform: translateY(-1px);
}
</style>

<!-- Üst İşlem Çubuğu & Başlık -->
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge bg-danger-subtle text-danger font-weight-bold px-2.5 py-1 fs-8 rounded-pill">
        <i class="bi bi-hospital me-1"></i> İSG Kurum Portföyü
      </span>
    </div>
    <h3 class="fw-extrabold m-0 text-dark">Kurum Tanımları</h3>
    <p class="text-muted fs-7 m-0 mt-0.5">Saha risk denetimi yürütülen resmi kurumlar ve bağlı birimleri yönetin.</p>
  </div>
  <div>
    <button type="button" class="btn btn-danger font-weight-bold px-4 py-2.5 shadow-sm rounded-3 text-nowrap" data-bs-toggle="modal" data-bs-target="#addInstitutionModal">
      <i class="bi bi-plus-lg me-1.5"></i> Yeni Kurum Ekle
    </button>
  </div>
</div>

<!-- Modern Dark Glass Hero Banner & Metrikler -->
<div class="custom-card p-4 mb-4 border-0 shadow-lg rounded-4 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
  <div class="row g-3 align-items-center">
    <div class="col-12 col-md-5">
      <div class="d-flex align-items-center gap-3">
        <div class="p-3 bg-danger bg-opacity-20 rounded-3 text-white border border-danger border-opacity-25 fs-2">
          <i class="bi bi-building-fill-gear"></i>
        </div>
        <div>
          <h5 class="fw-extrabold m-0 text-white fs-6">Kurum Yönetim Merkezi</h5>
          <p class="text-white-50 fs-8 m-0 mt-1">Denetimlerde kurumlara bağlı birimler ve risk analizleri raporlanır.</p>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-7">
      <div class="row g-2">
        <div class="col-4">
          <div class="bg-white bg-opacity-10 p-3 rounded-3 text-center border border-white border-opacity-10">
            <span class="d-block fs-8 text-white-50 font-weight-bold">TOPLAM KURUM</span>
            <span class="fw-extrabold fs-4 text-white"><?php echo $totalCount; ?></span>
          </div>
        </div>
        <div class="col-4">
          <div class="bg-white bg-opacity-10 p-3 rounded-3 text-center border border-white border-opacity-10">
            <span class="d-block fs-8 text-white-50 font-weight-bold">AKTİF DURUM</span>
            <span class="fw-extrabold fs-4 text-success"><?php echo $activeCount; ?></span>
          </div>
        </div>
        <div class="col-4">
          <div class="bg-white bg-opacity-10 p-3 rounded-3 text-center border border-white border-opacity-10">
            <span class="d-block fs-8 text-white-50 font-weight-bold">DENETİMLER</span>
            <span class="fw-extrabold fs-4 text-warning"><?php echo $totalAudits; ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Canlı Arama ve Görünüm Çubuğu -->
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
  <div class="input-group input-group-sm shadow-2xs" style="max-width: 360px;">
    <span class="input-group-text bg-white border-end-0 text-muted px-3"><i class="bi bi-search"></i></span>
    <input type="text" id="instSearchInput" class="form-control border-start-0 py-2 fs-7" placeholder="Kurum adı veya koda göre ara...">
  </div>
  <span class="text-muted fs-8 font-weight-bold">Toplam <strong id="visibleCountDisplay" class="text-dark"><?php echo $totalCount; ?></strong> kurum listeleniyor</span>
</div>

<!-- MODERN KURUMLAR KART GRID LİSTESİ -->
<div class="row g-3" id="institutionsGrid">
  <?php if (empty($institutions)): ?>
    <div class="col-12">
      <div class="custom-card p-5 text-center bg-white border-0 shadow-sm rounded-4">
        <i class="bi bi-hospital fs-1 text-muted d-block mb-3 opacity-40"></i>
        <h5 class="fw-extrabold text-dark m-0">Henüz Tanımlı Kurum Bulunmuyor</h5>
        <p class="text-muted fs-7 mt-1">Saha denetimleri için sağ üstteki <strong>Yeni Kurum Ekle</strong> butonuna tıklayarak ilk kurumu tanımlayabilirsiniz.</p>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($institutions as $inst): ?>
      <div class="col-12 col-md-6 col-xl-4 inst-card-wrapper" data-search="<?php echo mb_strtolower($inst['institution_name'] . ' ' . $inst['code'] . ' ' . $inst['description'], 'UTF-8'); ?>">
        <div class="inst-card p-4 h-100 d-flex flex-column justify-content-between">
          
          <div>
            <!-- Üst Kurum Bilgisi & Rozetler (Tıklanabilir Link İle Raporlar Sayfasına Yönlendirir) -->
            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
              <a href="audits_list.php?institution_id=<?php echo $inst['id']; ?>" class="d-flex align-items-center gap-3 text-decoration-none hover-title-link" title="<?php echo htmlspecialchars($inst['institution_name']); ?> Denetim Raporlarını İncele">
                <div class="inst-avatar">
                  <i class="bi bi-hospital"></i>
                </div>
                <div>
                  <h6 class="fw-extrabold text-dark m-0 fs-6 leading-tight hover-title"><?php echo htmlspecialchars($inst['institution_name']); ?></h6>
                  <span class="badge bg-light text-secondary border font-weight-bold px-2 py-0.5 fs-8 mt-1">
                    <?php echo htmlspecialchars($inst['code'] ?? 'KODSUZ'); ?>
                  </span>
                </div>
              </a>

              <?php if ($inst['is_active']): ?>
                <span class="badge bg-success-subtle text-success font-weight-bold px-2.5 py-1 rounded-pill fs-8 flex-shrink-0">
                  <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Aktif
                </span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary font-weight-bold px-2.5 py-1 rounded-pill fs-8 flex-shrink-0">
                  Pasif
                </span>
              <?php endif; ?>
            </div>

            <!-- Açıklama Metni -->
            <p class="text-muted fs-7 mb-3 text-break" style="min-height: 40px; line-height: 1.4;">
              <?php echo htmlspecialchars($inst['description'] ?? 'Kurum açıklaması girilmemiş.'); ?>
            </p>
          </div>

          <!-- Alt Metrik (Kuruma Ait Denetim Raporlarına Bağlantı) & Aksiyon Butonları -->
          <div class="pt-3 border-top d-flex align-items-center justify-content-between gap-2 mt-2">
            
            <a href="audits_list.php?institution_id=<?php echo $inst['id']; ?>" 
               class="badge bg-info-subtle text-info-emphasis font-weight-bold px-3 py-2 rounded-pill fs-8 text-decoration-none audit-pill-link" 
               title="<?php echo htmlspecialchars($inst['institution_name']); ?> Saha Denetim Raporlarını Aç">
              <i class="bi bi-clipboard2-check-fill me-1"></i> <?php echo $inst['audit_count']; ?> Denetim <i class="bi bi-arrow-right-short ms-0.5"></i>
            </a>

            <div class="d-flex align-items-center gap-1.5">
              <button type="button" class="btn btn-sm btn-soft-primary px-3 py-1.5 rounded-3 fs-8" 
                      onclick="editInstitution(<?php echo htmlspecialchars(json_encode($inst)); ?>)"
                      title="Kurum Bilgilerini Düzenle">
                <i class="bi bi-pencil-square me-1"></i> Düzenle
              </button>
              
              <form method="POST" action="institutions.php" class="d-inline confirm-delete-form" data-confirm-title="Kurumu Sil" data-confirm-text="Bu kurumu (<?php echo htmlspecialchars($inst['institution_name']); ?>) silmek istediğinize emin misiniz?">
                <input type="hidden" name="action" value="delete_institution">
                <input type="hidden" name="institution_id" value="<?php echo $inst['id']; ?>">
                <button type="submit" class="btn btn-sm btn-soft-danger px-2.5 py-1.5 rounded-3 fs-8" title="Kurumu Sil">
                  <i class="bi bi-trash3-fill"></i>
                </button>
              </form>
            </div>
          </div>

        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- YENİ KURUM EKLE MODAL (Modernized UI) -->
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

<!-- KURUM DÜZENLE MODAL (Modernized UI) -->
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
  const countDisplay = document.getElementById('visibleCountDisplay');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const q = this.value.toLowerCase().trim();
      const wrappers = document.querySelectorAll('.inst-card-wrapper');
      let visible = 0;
      wrappers.forEach(w => {
        const text = w.dataset.search || '';
        if (text.includes(q)) {
          w.style.display = '';
          visible++;
        } else {
          w.style.display = 'none';
        }
      });
      if (countDisplay) countDisplay.textContent = visible;
    });
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
