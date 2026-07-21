<?php
/**
 * Tubİsg - Saha Denetimi Başlatma Ekranı (Adım 1: Profil ve Birim Seçimi)
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('audit_conduct');

$db = getDB();

// Aktif Anket Profilleri ve Birimleri Çek
$templates = $db->query("SELECT * FROM survey_templates WHERE is_active = 1 ORDER BY title ASC")->fetchAll();
$units = $db->query("SELECT * FROM units ORDER BY unit_name ASC")->fetchAll();

$selectedTemplateId = (int)($_GET['template_id'] ?? 0);
$selectedUnitId = (int)($_GET['unit_id'] ?? 0);

$pageTitle = 'Yeni Saha Denetimi Başlat';
include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-8 col-lg-6">
    <div class="custom-card">
      <div class="text-center mb-4">
        <div class="d-inline-flex p-3 bg-success-light text-success rounded-circle mb-2">
          <i class="bi bi-clipboard2-plus-fill fs-1"></i>
        </div>
        <h3 class="fw-extrabold m-0">Saha Denetimi Başlat</h3>
        <p class="text-muted fs-7">Lütfen denetim yapacağınız <strong>Anket Profilini</strong> ve <strong>Birim/Departmanı</strong> seçin.</p>
      </div>

      <form method="GET" action="audit_fill.php" id="startAuditForm">
        <!-- 1. Anket Profili Seçimi -->
        <div class="mb-4">
          <label class="form-label fw-bold text-dark fs-7">
            <i class="bi bi-journal-check text-success"></i> 1. Anket Profili Seçin
          </label>
          <select name="template_id" class="form-select form-select-lg rounded-md" required>
            <option value="" disabled <?php echo $selectedTemplateId === 0 ? 'selected' : ''; ?>>-- Anket Profili Seçin --</option>
            <?php foreach ($templates as $tpl): ?>
              <option value="<?php echo $tpl['id']; ?>" <?php echo $selectedTemplateId == $tpl['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($tpl['title']); ?> (<?php echo htmlspecialchars($tpl['category']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- 2. Birim Seçimi & Hızlı Ekle Kısayolu -->
        <div class="mb-4">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <label class="form-label fw-bold text-dark fs-7 m-0">
              <i class="bi bi-building text-primary"></i> 2. Birim / Saha Seçin
            </label>

            <?php if (has_permission('units_manage')): ?>
              <button type="button" class="btn btn-sm btn-outline-success border-0 fw-bold" data-bs-toggle="modal" data-bs-target="#quickUnitModal">
                <i class="bi bi-plus-circle"></i> + Hızlı Birim Ekle
              </button>
            <?php endif; ?>
          </div>

          <select name="unit_id" id="unit_id" class="form-select form-select-lg rounded-md" required>
            <option value="" disabled <?php echo $selectedUnitId === 0 ? 'selected' : ''; ?>>-- Denetim Yapılacak Birimi Seçin --</option>
            <?php foreach ($units as $u): ?>
              <option value="<?php echo $u['id']; ?>" <?php echo $selectedUnitId == $u['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($u['unit_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit" class="btn btn-primary-custom w-100 py-3 text-uppercase font-weight-bold letter-spacing-1">
          <i class="bi bi-play-circle-fill fs-4"></i>
          Denetimi Başlat ve Doldur
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Hızlı Birim Tanımlama Modalı (Denetçi anında birim ekleyebilsin) -->
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
