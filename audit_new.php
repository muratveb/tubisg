<?php
/**
 * Tubİsg - Modern Saha Denetimi Başlatma Ekranı (Kompakt Sihirbaz)
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('audit_conduct');

$db = getDB();

// Aktif Anket Profillerini ve Soruların Sayısını Çek
$templates = $db->query("
    SELECT st.*, COUNT(sq.id) as question_count
    FROM survey_templates st
    LEFT JOIN survey_questions sq ON st.id = sq.template_id
    WHERE st.is_active = 1
    GROUP BY st.id
    ORDER BY st.title ASC
")->fetchAll();

// Birimleri Çek
$units = $db->query("SELECT * FROM units ORDER BY unit_name ASC")->fetchAll();

$selectedTemplateId = (int)($_GET['template_id'] ?? 0);
$selectedUnitId = (int)($_GET['unit_id'] ?? 0);

$pageTitle = 'Yeni Saha Denetimi Başlat';
include __DIR__ . '/includes/header.php';
?>

<!-- Üst Başlık & Rehber -->
<div class="d-flex align-items-center justify-content-between gap-3 mb-3">
  <div>
    <h4 class="fw-extrabold text-dark m-0">Saha Denetim Sihirbazı</h4>
    <p class="text-muted fs-8 m-0">Lütfen denetim yapacağınız <strong>Anket Profilini</strong> ve <strong>Birimi</strong> seçin.</p>
  </div>
  <span class="badge bg-success-light text-success font-weight-bold px-3 py-2 rounded-pill fs-8">
    <i class="bi bi-shield-check"></i> Canlı Saha Modu
  </span>
</div>

<form method="GET" action="audit_fill.php" id="startAuditWizardForm">

  <div class="row g-3 mb-3">
    
    <!-- ADIM 1: Anket Profili Seçim Kartları (Kompakt Sütun) -->
    <div class="col-12 col-lg-6">
      <div class="custom-card h-100 mb-0 p-3">
        <div class="custom-card-header mb-3 pb-2">
          <h6 class="custom-card-title m-0 fs-7">
            <span class="badge bg-primary rounded-circle" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center;">1</span>
            Anket Profilini Seçin
          </h6>
          <span class="text-muted fs-8">İSG kontrol şablonu</span>
        </div>

        <input type="hidden" name="template_id" id="selectedTemplateInput" value="<?php echo $selectedTemplateId > 0 ? $selectedTemplateId : ''; ?>" required>

        <div class="row g-2" id="templateCardsContainer">
          <?php foreach ($templates as $tpl): ?>
            <?php $isSelected = ($selectedTemplateId == $tpl['id']); ?>
            <div class="col-12">
              <div class="wizard-select-card template-card p-2 px-3 <?php echo $isSelected ? 'selected' : ''; ?>" data-id="<?php echo $tpl['id']; ?>">
                <div class="d-flex align-items-center justify-content-between mb-1">
                  <span class="badge bg-primary-light text-primary font-weight-bold fs-8" style="font-size:0.7rem;"><?php echo htmlspecialchars($tpl['category']); ?></span>
                  <span class="badge bg-light text-dark border fs-8" style="font-size:0.7rem;"><i class="bi bi-list-task"></i> <?php echo $tpl['question_count']; ?> Soru</span>
                </div>
                <h6 class="fw-bold text-dark m-0 wizard-card-title fs-7"><?php echo htmlspecialchars($tpl['title']); ?></h6>
                <p class="text-muted fs-8 m-0 text-truncate wizard-card-desc" style="font-size:0.75rem;"><?php echo htmlspecialchars($tpl['description'] ?? 'Saha risk denetim anket profili.'); ?></p>
                
                <div class="wizard-card-check">
                  <i class="bi bi-check-circle-fill"></i>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ADIM 2: Birim / Saha Seçim Kartları (Kompakt Sütun) -->
    <div class="col-12 col-lg-6">
      <div class="custom-card h-100 mb-0 p-3">
        <div class="custom-card-header mb-3 pb-2">
          <h6 class="custom-card-title m-0 fs-7">
            <span class="badge bg-primary rounded-circle" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center;">2</span>
            Birim / Saha Seçin
          </h6>
          
          <?php if (has_permission('units_manage')): ?>
            <button type="button" class="btn btn-xs btn-outline-success font-weight-bold rounded-pill px-2 py-1 fs-8" data-bs-toggle="modal" data-bs-target="#quickUnitModal">
              <i class="bi bi-plus-circle-fill"></i> + Hızlı Birim Ekle
            </button>
          <?php endif; ?>
        </div>

        <!-- Hızlı Arama Kutusu -->
        <div class="mb-2">
          <input type="text" id="unitSearchInput" class="form-control form-control-sm fs-8" placeholder="🔍 Birim / Saha Ara...">
        </div>

        <input type="hidden" name="unit_id" id="selectedUnitInput" value="<?php echo $selectedUnitId > 0 ? $selectedUnitId : ''; ?>" required>

        <div class="row g-2" id="unitCardsContainer" style="max-height: 380px; overflow-y: auto;">
          <?php foreach ($units as $u): ?>
            <?php $isSelected = ($selectedUnitId == $u['id']); ?>
            <div class="col-12 col-sm-6 unit-card-wrapper" data-name="<?php echo mb_strtolower($u['unit_name'], 'UTF-8'); ?>">
              <div class="wizard-select-card unit-card p-2 px-3 <?php echo $isSelected ? 'selected' : ''; ?>" data-id="<?php echo $u['id']; ?>">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <i class="bi bi-building text-success fs-6"></i>
                  <h6 class="fw-bold text-dark m-0 wizard-card-title fs-7 text-truncate"><?php echo htmlspecialchars($u['unit_name']); ?></h6>
                </div>
                <p class="text-muted fs-8 m-0 wizard-card-desc text-truncate" style="font-size:0.75rem;"><?php echo htmlspecialchars($u['description'] ?? 'Kayıtlı birim.'); ?></p>
                
                <div class="wizard-card-check">
                  <i class="bi bi-check-circle-fill"></i>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- ADIM 3: Denetimi Başlat Alt İşlem Barı -->
  <div class="custom-card bg-white p-3 shadow-md border-2 border-success d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
      <div class="p-2 px-3 bg-success-light text-success rounded-circle">
        <i class="bi bi-play-circle-fill fs-4"></i>
      </div>
      <div>
        <div class="fs-8 text-muted text-uppercase font-weight-bold" style="font-size:0.7rem;">SEÇİLEN DENETİM BİLGİSİ</div>
        <div class="fw-extrabold text-dark fs-7" id="wizardSelectionSummary">
          Lütfen yukarıdan Anket Profili ve Birim seçin
        </div>
      </div>
    </div>

    <button type="submit" id="startAuditSubmitBtn" class="btn btn-primary-custom py-2 px-4 font-weight-bold fs-7 disabled shadow-sm text-nowrap">
      <i class="bi bi-arrow-right-circle-fill fs-6"></i> Denetimi Başlat ve Doldur
    </button>
  </div>

</form>

<!-- Hızlı Birim Tanımlama Modalı -->
<?php if (has_permission('units_manage')): ?>
<div class="modal fade" id="quickUnitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="quickUnitForm">
        <div class="modal-header">
          <h5 class="modal-title fw-bold fs-6"><i class="bi bi-building-add text-success"></i> Hızlı Birim Tanımla</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold fs-7">Birim / Departman Adı</label>
            <input type="text" name="unit_name" class="form-control" placeholder="Örn: Faturalama Birimi" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold fs-7">Açıklama (Opsiyonel)</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Birim konumu veya detaylı bilgi..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-success btn-sm">Ekle ve Seç</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
// Birim Arama İnteraktif Filtreleme
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('unitSearchInput');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const q = this.value.toLowerCase().trim();
      const wrappers = document.querySelectorAll('.unit-card-wrapper');
      wrappers.forEach(w => {
        const name = w.dataset.name || '';
        if (name.includes(q)) {
          w.style.display = '';
        } else {
          w.style.display = 'none';
        }
      });
    });
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
