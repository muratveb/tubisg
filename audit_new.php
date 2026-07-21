<?php
/**
 * Tubİsg - Modern Saha Denetimi Başlatma Ekranı (Görsel Sihirbaz)
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
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div>
    <h3 class="fw-extrabold m-0">Saha Denetim Sihirbazı</h3>
    <p class="text-muted fs-7 m-0">Lütfen denetim gerçekleştireceğiniz **Anket Profilini** ve **Birimi** seçin.</p>
  </div>
  <div class="d-flex gap-2">
    <span class="badge bg-success-light text-success font-weight-bold px-3 py-2 rounded-pill fs-8">
      <i class="bi bi-shield-check"></i> Canlı Saha Modu
    </span>
  </div>
</div>

<form method="GET" action="audit_fill.php" id="startAuditWizardForm">

  <!-- ADIM 1: Görsel Anket Profili Seçim Kartları -->
  <div class="custom-card mb-4">
    <div class="custom-card-header">
      <h5 class="custom-card-title m-0">
        <span class="badge bg-primary rounded-circle me-1" style="width:26px; height:26px; display:inline-flex; align-items:center; justify-content:center;">1</span>
        Anket Profilini Seçin
      </h5>
      <span class="text-muted fs-8">Sahada uygulanacak İSG kontrol şablonu</span>
    </div>

    <input type="hidden" name="template_id" id="selectedTemplateInput" value="<?php echo $selectedTemplateId > 0 ? $selectedTemplateId : ''; ?>" required>

    <div class="row g-3" id="templateCardsContainer">
      <?php foreach ($templates as $tpl): ?>
        <?php $isSelected = ($selectedTemplateId == $tpl['id']); ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="wizard-select-card template-card <?php echo $isSelected ? 'selected' : ''; ?>" data-id="<?php echo $tpl['id']; ?>">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="badge bg-primary-light text-primary font-weight-bold"><?php echo htmlspecialchars($tpl['category']); ?></span>
              <span class="badge bg-light text-dark border"><i class="bi bi-list-task"></i> <?php echo $tpl['question_count']; ?> Soru</span>
            </div>
            <h6 class="fw-extrabold text-dark mb-1 wizard-card-title"><?php echo htmlspecialchars($tpl['title']); ?></h6>
            <p class="text-muted fs-8 mb-0 wizard-card-desc"><?php echo htmlspecialchars($tpl['description'] ?? 'Özelleştirilmiş saha denetim soruları ve puanlama kurgusu.'); ?></p>
            
            <div class="wizard-card-check">
              <i class="bi bi-check-circle-fill"></i>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ADIM 2: Görsel Birim / Saha Seçim Kartları & Hızlı Ekleme -->
  <div class="custom-card mb-4">
    <div class="custom-card-header">
      <h5 class="custom-card-title m-0">
        <span class="badge bg-primary rounded-circle me-1" style="width:26px; height:26px; display:inline-flex; align-items:center; justify-content:center;">2</span>
        Birim / Saha Seçin
      </h5>
      
      <?php if (has_permission('units_manage')): ?>
        <button type="button" class="btn btn-sm btn-outline-success font-weight-bold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#quickUnitModal">
          <i class="bi bi-plus-circle-fill"></i> + Hızlı Birim Ekle
        </button>
      <?php endif; ?>
    </div>

    <input type="hidden" name="unit_id" id="selectedUnitInput" value="<?php echo $selectedUnitId > 0 ? $selectedUnitId : ''; ?>" required>

    <div class="row g-3" id="unitCardsContainer">
      <?php foreach ($units as $u): ?>
        <?php $isSelected = ($selectedUnitId == $u['id']); ?>
        <div class="col-12 col-sm-6 col-md-4 col-lg-3">
          <div class="wizard-select-card unit-card <?php echo $isSelected ? 'selected' : ''; ?>" data-id="<?php echo $u['id']; ?>">
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bi bi-building text-success fs-5"></i>
              <h6 class="fw-bold text-dark m-0 wizard-card-title"><?php echo htmlspecialchars($u['unit_name']); ?></h6>
            </div>
            <p class="text-muted fs-8 m-0 wizard-card-desc"><?php echo htmlspecialchars($u['description'] ?? 'Kayıtlı saha birimi.'); ?></p>
            
            <div class="wizard-card-check">
              <i class="bi bi-check-circle-fill"></i>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ADIM 3: Denetimi Başlat Alt Barı -->
  <div class="custom-card bg-white p-3 shadow-lg border-2 border-success d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-5">
    <div class="d-flex align-items-center gap-3">
      <div class="p-3 bg-success-light text-success rounded-circle">
        <i class="bi bi-play-circle-fill fs-3"></i>
      </div>
      <div>
        <div class="fs-8 text-muted text-uppercase font-weight-bold">SEÇİLEN DENETİM BİLGİSİ</div>
        <div class="fw-extrabold text-dark fs-6" id="wizardSelectionSummary">
          Lütfen yukarıdan Anket Profili ve Birim seçin
        </div>
      </div>
    </div>

    <button type="submit" id="startAuditSubmitBtn" class="btn btn-primary-custom py-3 px-4 font-weight-bold fs-6 disabled shadow-lg">
      <i class="bi bi-arrow-right-circle-fill fs-5"></i> Denetimi Başlat ve Doldur
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
          <h5 class="modal-title fw-bold"><i class="bi bi-building-add text-success"></i> Hızlı Birim Tanımla</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Birim / Departman Adı</label>
            <input type="text" name="unit_name" class="form-control" placeholder="Örn: Faturalama Birimi" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Açıklama (Opsiyonel)</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Birim konumu veya detaylı bilgi..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-success">Ekle ve Seç</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
