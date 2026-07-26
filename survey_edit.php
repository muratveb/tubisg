<?php
/**
 * Tubİsg - 9 Adımlı Seçimli Risk Maddesi & Anket Profili Sihirbazı (survey_edit.php)
 * Kağıt Form Belgenizdeki 12 Sütunlu Yapı İle Birebir Senkronize
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('surveys_manage');

$db = getDB();
$user = get_current_user_data();

$template_id = (int)($_GET['id'] ?? 0);
if ($template_id <= 0) {
    header("Location: survey_templates.php");
    exit;
}

// Şablon Bilgisini Çek
$stmt = $db->prepare("SELECT * FROM survey_templates WHERE id = ?");
$stmt->execute([$template_id]);
$template = $stmt->fetch();

if (!$template) {
    set_flash('danger', 'Anket profili bulunamadı.');
    header("Location: survey_templates.php");
    exit;
}

// Risk Gruplarını Çek
$riskGroups = $db->query("SELECT * FROM risk_groups ORDER BY sort_order ASC, group_name ASC")->fetchAll();

// Kütüphane Verilerini Çek
$libSources = $db->query("SELECT item_text FROM risk_libraries WHERE category = 'hazard_source' ORDER BY item_text ASC")->fetchAll(PDO::FETCH_COLUMN);
$libHazards = $db->query("SELECT item_text FROM risk_libraries WHERE category = 'hazard_name' ORDER BY item_text ASC")->fetchAll(PDO::FETCH_COLUMN);
$libAffected = $db->query("SELECT item_text FROM risk_libraries WHERE category = 'affected_people' ORDER BY item_text ASC")->fetchAll(PDO::FETCH_COLUMN);
$libResponsibles = $db->query("SELECT item_text FROM risk_libraries WHERE category = 'responsible_person' ORDER BY item_text ASC")->fetchAll(PDO::FETCH_COLUMN);
$libRecommendations = $db->query("SELECT item_text FROM risk_libraries WHERE category = 'action_recommendation' ORDER BY item_text ASC")->fetchAll(PDO::FETCH_COLUMN);

// Form Post İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 0. Kütüphaneye Hızlı Öğe Ekleme (Modal Formu)
    if (isset($_POST['action']) && $_POST['action'] === 'add_library_item') {
        $cat = trim($_POST['category'] ?? '');
        $itemText = trim($_POST['item_text'] ?? '');
        if (!empty($cat) && !empty($itemText)) {
            $db->prepare("INSERT INTO risk_libraries (category, item_text) VALUES (?, ?)")->execute([$cat, $itemText]);
            log_action('İSG Kütüphanesine Öğe Eklendi', "Anket Editöründen Eklendi - Kategori: {$cat}, Metin: {$itemText}");
            set_flash('success', "Kütüphaneye yeni öge ({$itemText}) başarıyla eklendi.");
        }
        header("Location: survey_edit.php?id=" . $template_id);
        exit;
    }

    // 1. Soru / Risk Satırı Silme İşlemi
    if (isset($_POST['delete_question_id'])) {
        $qId = (int)$_POST['delete_question_id'];
        $db->prepare("DELETE FROM survey_questions WHERE id = ? AND template_id = ?")->execute([$qId, $template_id]);
        log_action('Risk Satırı Silindi', "Anket ID: #{$template_id}, Satır ID: #{$qId}");
        set_flash('success', 'Risk satırı silindi.');
        header("Location: survey_edit.php?id=" . $template_id);
        exit;
    }

    // 2. Mevcut Risk Satırlarını Güncelleme
    if (isset($_POST['questions']) && is_array($_POST['questions'])) {
        foreach ($_POST['questions'] as $qId => $qData) {
            $riskGroupId = (int)($qData['risk_group_id'] ?? 0);
            $hazardSource = trim($qData['hazard_source'] ?? '');
            $hazardName = trim($qData['hazard_name'] ?? '');
            $affectedRisk = trim($qData['affected_risk'] ?? '');
            $affectedPeople = trim($qData['affected_people'] ?? '');
            $currentStatus = trim($qData['current_status'] ?? '');
            $prob = (int)($qData['default_probability'] ?? 2);
            $sev = (int)($qData['default_severity'] ?? 3);
            $actionPlan = trim($qData['default_action_plan'] ?? '');
            $responsible = trim($qData['default_responsible'] ?? '');
            $deadline = trim($qData['default_deadline'] ?? '');
            $qText = trim($qData['question_text'] ?? '');
            if (empty($qText)) {
                $qText = !empty($hazardName) ? $hazardName : 'Saha Risk Denetim Maddesi';
            }

            $stmtUpd = $db->prepare("
                UPDATE survey_questions 
                SET risk_group_id = ?, hazard_source = ?, hazard_name = ?, affected_risk = ?, affected_people = ?,
                    current_status = ?, default_probability = ?, default_severity = ?, default_action_plan = ?,
                    default_responsible = ?, default_deadline = ?, question_text = ?
                WHERE id = ? AND template_id = ?
            ");
            $stmtUpd->execute([
                $riskGroupId > 0 ? $riskGroupId : null,
                $hazardSource,
                $hazardName,
                $affectedRisk,
                $affectedPeople,
                $currentStatus,
                $prob,
                $sev,
                $actionPlan,
                $responsible,
                $deadline,
                $qText,
                $qId,
                $template_id
            ]);
        }
    }

    // 3. 9 Adımlı Sihirbazdan Gelen Yeni Risk Satırları Ekleme
    if (isset($_POST['new_questions']) && is_array($_POST['new_questions'])) {
        foreach ($_POST['new_questions'] as $newQ) {
            $riskGroupId = (int)($newQ['risk_group_id'] ?? 0);
            $hazardSource = trim($newQ['hazard_source'] ?? '');
            $hazardName = trim($newQ['hazard_name'] ?? '');
            $affectedRisk = trim($newQ['affected_risk'] ?? '');
            $affectedPeople = trim($newQ['affected_people'] ?? '');
            $currentStatus = trim($newQ['current_status'] ?? '');
            $prob = (int)($newQ['default_probability'] ?? 2);
            $sev = (int)($newQ['default_severity'] ?? 3);
            $actionPlan = trim($newQ['default_action_plan'] ?? '');
            $responsible = trim($newQ['default_responsible'] ?? '');
            $deadline = trim($newQ['default_deadline'] ?? '');
            $qText = trim($newQ['question_text'] ?? '');
            if (empty($qText)) {
                $qText = !empty($hazardName) ? $hazardName : 'Saha Risk Denetim Maddesi';
            }

            if (!empty($hazardSource) || !empty($hazardName) || !empty($qText)) {
                $stmtQ = $db->prepare("
                    INSERT INTO survey_questions 
                    (template_id, risk_group_id, hazard_source, hazard_name, affected_risk, affected_people,
                     current_status, default_probability, default_severity, default_action_plan, default_responsible, default_deadline, question_text) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtQ->execute([
                    $template_id,
                    $riskGroupId > 0 ? $riskGroupId : null,
                    $hazardSource,
                    $hazardName,
                    $affectedRisk,
                    $affectedPeople,
                    $currentStatus,
                    $prob,
                    $sev,
                    $actionPlan,
                    $responsible,
                    $deadline,
                    $qText
                ]);
                $qId = $db->lastInsertId();

                // Standart İSG Cevap Şıkları
                $defaultOptions = [
                    ['text' => 'Evet (Uygun)', 'trigger' => 0],
                    ['text' => 'Hayır (Uygun Değil)', 'trigger' => 1],
                    ['text' => 'Kısmen (Kısmen Uygun)', 'trigger' => 1],
                    ['text' => 'Denetim Dışı / Muaf', 'trigger' => 0]
                ];
                $stmtOpt = $db->prepare("INSERT INTO question_options (question_id, option_text, points, trigger_action) VALUES (?, ?, 0, ?)");
                foreach ($defaultOptions as $defOpt) {
                    $stmtOpt->execute([$qId, $defOpt['text'], $defOpt['trigger']]);
                }
            }
        }
    }

    log_action('Birim Bazlı Risk Formu Güncellendi', "Anket Profili: {$template['title']} (ID: #{$template_id}) güncellendi.");
    set_flash('success', 'Birim bazlı İSG risk analiz form maddeleri başarıyla kaydedildi.');
    header("Location: survey_edit.php?id=" . $template_id);
    exit;
}

// Soruları / Risk Satırlarını Çek
$questionsStmt = $db->prepare("
    SELECT sq.*, rg.group_name
    FROM survey_questions sq
    LEFT JOIN risk_groups rg ON sq.risk_group_id = rg.id
    WHERE sq.template_id = ? 
    ORDER BY COALESCE(rg.sort_order, 99) ASC, sq.sort_order ASC, sq.id ASC
");
$questionsStmt->execute([$template_id]);
$questions = $questionsStmt->fetchAll();

$pageTitle = 'İSG Risk Analiz Form Editörü: ' . $template['title'];
include __DIR__ . '/includes/header.php';
?>

<script>
window.riskGroupsData = <?php echo json_encode($riskGroups); ?>;
window.libSourcesData = <?php echo json_encode($libSources); ?>;
window.libHazardsData = <?php echo json_encode($libHazards); ?>;
window.libAffectedData = <?php echo json_encode($libAffected); ?>;
window.libResponsiblesData = <?php echo json_encode($libResponsibles); ?>;
window.libRecommendationsData = <?php echo json_encode($libRecommendations); ?>;
</script>

<!-- Autocomplete Datalist Öğeleri -->
<datalist id="hazard_sources_list">
  <?php foreach ($libSources as $src): ?>
    <option value="<?php echo htmlspecialchars($src); ?>"></option>
  <?php endforeach; ?>
</datalist>

<datalist id="hazards_list">
  <?php foreach ($libHazards as $hz): ?>
    <option value="<?php echo htmlspecialchars($hz); ?>"></option>
  <?php endforeach; ?>
</datalist>

<datalist id="affected_list">
  <?php foreach ($libAffected as $aff): ?>
    <option value="<?php echo htmlspecialchars($aff); ?>"></option>
  <?php endforeach; ?>
</datalist>

<datalist id="responsibles_list">
  <?php foreach ($libResponsibles as $resp): ?>
    <option value="<?php echo htmlspecialchars($resp); ?>"></option>
  <?php endforeach; ?>
</datalist>

<datalist id="recommendations_list">
  <?php foreach ($libRecommendations as $rec): ?>
    <option value="<?php echo htmlspecialchars($rec); ?>"></option>
  <?php endforeach; ?>
</datalist>

<!-- Sayfa Üst Başlık & Butonlar -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div>
    <a href="survey_templates.php" class="text-decoration-none text-muted fs-8 font-weight-bold d-block mb-1">
      <i class="bi bi-arrow-left"></i> Anket Profillerine Dön
    </a>
    <h3 class="fw-extrabold m-0"><?php echo htmlspecialchars($template['title']); ?></h3>
    <p class="text-muted fs-7 m-0">Kağıt Belgenizdeki 12 Sütunlu İSG Birim Bazlı Risk Analiz Form Şablonu</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <button type="button" class="btn btn-outline-primary font-weight-bold" data-bs-toggle="modal" data-bs-target="#quickAddLibModal">
      <i class="bi bi-plus-circle-fill"></i> Kütüphaneye Öğe Ekle
    </button>
    <button type="button" class="btn btn-success font-weight-bold shadow-lg" data-bs-toggle="modal" data-bs-target="#wizardAddRiskItemModal">
      <i class="bi bi-magic me-1"></i> + Adım Adım Seçimli Risk Maddesi Ekle
    </button>
  </div>
</div>

<form method="POST" action="survey_edit.php?id=<?php echo $template_id; ?>" id="surveyEditForm">

  <div id="questionsContainer">
    <?php if (empty($questions)): ?>
      <div class="alert alert-warning text-center py-5 shadow-sm rounded-4">
        <i class="bi bi-magic fs-1 d-block mb-2 text-warning"></i>
        Bu anket profilinde henüz hiç risk maddesi bulunmuyor.<br>
        Yukarıdaki <strong>"+ Adım Adım Seçimli Risk Maddesi Ekle"</strong> butonuna tıklayarak rehberli sihirbaz ile kolayca ekleyebilirsiniz.
      </div>
    <?php else: ?>
      <?php $qNum = 1; foreach ($questions as $q): ?>
        <?php
        $p = (int)($q['default_probability'] ?? 2);
        $s = (int)($q['default_severity'] ?? 3);
        $r = $p * $s;

        $badgeBg = 'bg-success';
        $statusText = 'Kabul Edilebilir Risk';
        if ($r >= 16) { $badgeBg = 'bg-danger'; $statusText = 'Kabul Edilemez Risk'; }
        elseif ($r >= 10) { $badgeBg = 'bg-warning text-dark'; $statusText = 'Dikkate Değer Risk'; }
        elseif ($r >= 6) { $badgeBg = 'bg-info text-dark'; $statusText = 'Önemli Risk'; }
        ?>
        <div class="custom-card question-builder-card mb-4 border-2" data-qindex="<?php echo $qNum; ?>">
          
          <!-- Kart Üst Barı -->
          <div class="custom-card-header bg-dark text-white p-3 rounded-top d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-warning text-dark rounded-circle" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;"><?php echo $qNum; ?></span>
              <h6 class="m-0 font-weight-bold text-white">Risk Satırı #<?php echo $qNum; ?></h6>
              <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($q['group_name'] ?? 'Genel Riskler'); ?></span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge <?php echo $badgeBg; ?>" id="risk_calc_badge_<?php echo $q['id']; ?>">R = <?php echo $r; ?> (<?php echo $statusText; ?>)</span>
              <button type="submit" name="delete_question_id" value="<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-danger text-white" data-confirm-btn="true" data-confirm-title="Risk Satırını Sil" data-confirm-text="Bu risk analiz satırını silmek istediğinize emin misiniz?">
                <i class="bi bi-trash"></i> Sil
              </button>
            </div>
          </div>

          <div class="p-3">
            <!-- 1. SÜTUN: RİSK GRUBU SEÇİMİ -->
            <div class="row g-3 mb-3 bg-light p-2 rounded-3 border">
              <div class="col-12 col-md-4">
                <label class="form-label fw-bold fs-8 text-dark"><i class="bi bi-diagram-3-fill text-warning me-1"></i> 1. Risk Grubu</label>
                <select name="questions[<?php echo $q['id']; ?>][risk_group_id]" class="form-select form-select-sm fw-bold">
                  <option value="0">-- Risk Grubu Seçin --</option>
                  <?php foreach ($riskGroups as $rg): ?>
                    <option value="<?php echo $rg['id']; ?>" <?php echo $q['risk_group_id'] == $rg['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($rg['group_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- 2. SÜTUN: TEHLİKE KAYNAĞI -->
              <div class="col-12 col-md-4">
                <label class="form-label fw-bold fs-8 text-dark">2. Tehlike Kaynağı (Kütüphaneden)</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][hazard_source]" list="hazard_sources_list" class="form-control form-control-sm" placeholder="Örn: Lavabo, Wc tavanı" value="<?php echo htmlspecialchars($q['hazard_source'] ?? ''); ?>">
              </div>

              <!-- 3. SÜTUN: TEHLİKE -->
              <div class="col-12 col-md-4">
                <label class="form-label fw-bold fs-8 text-dark">3. Tehlike (Kütüphaneden)</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][hazard_name]" list="hazards_list" class="form-control form-control-sm" placeholder="Örn: Enfeksiyon, Kaygan zemin" value="<?php echo htmlspecialchars($q['hazard_name'] ?? ''); ?>">
              </div>
            </div>

            <!-- 4. ETKİLENME VE 5. ETKİLENENLER -->
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-8 text-muted">4. Etkilenme (Yaşanabilecek Riskler)</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][affected_risk]" class="form-control form-control-sm" placeholder="Örn: Pis su bulaşma, enfeksiyon maruziyeti" value="<?php echo htmlspecialchars($q['affected_risk'] ?? ''); ?>">
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-8 text-muted">5. Etkilenenler (Kütüphaneden)</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][affected_people]" list="affected_list" class="form-control form-control-sm" placeholder="Örn: Çalışanlar(Doktor, Hemşire), Hasta ve yakını" value="<?php echo htmlspecialchars($q['affected_people'] ?? ''); ?>">
              </div>
            </div>

            <!-- 6. MEVCUT DURUM VE KONTROL SORUSU -->
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-8 text-muted">6. Mevcut Durum / Saha Tespiti</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][current_status]" class="form-control form-control-sm" placeholder="Örn: Lavabolar tavanda su akıntısı mevcut" value="<?php echo htmlspecialchars($q['current_status'] ?? ''); ?>">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-8 text-muted">Saha Denetim Sorusu Metni</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][question_text]" class="form-control form-control-sm" placeholder="Örn: WC tavanında su sızıntısı var mı?" value="<?php echo htmlspecialchars($q['question_text'] ?? ''); ?>">
              </div>
            </div>

            <!-- 7. OLASILIK, 8. ŞİDDET VE 9. RİSK DERECESİ -->
            <div class="row g-3 mb-3 p-2 rounded-3 bg-light border border-info">
              <div class="col-12 col-md-4">
                <label class="form-label fw-bold fs-8 text-dark">7. Olasılık ($O: 1-5$)</label>
                <select name="questions[<?php echo $q['id']; ?>][default_probability]" class="form-select form-select-sm risk-calc-edit" data-qid="<?php echo $q['id']; ?>" id="prob_edit_<?php echo $q['id']; ?>">
                  <option value="1" <?php echo $p == 1 ? 'selected' : ''; ?>>1 - Çok Küçük</option>
                  <option value="2" <?php echo $p == 2 ? 'selected' : ''; ?>>2 - Küçük</option>
                  <option value="3" <?php echo $p == 3 ? 'selected' : ''; ?>>3 - Orta</option>
                  <option value="4" <?php echo $p == 4 ? 'selected' : ''; ?>>4 - Yüksek</option>
                  <option value="5" <?php echo $p == 5 ? 'selected' : ''; ?>>5 - Çok Yüksek</option>
                </select>
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label fw-bold fs-8 text-dark">8. Şiddet ($Ş: 1-5$)</label>
                <select name="questions[<?php echo $q['id']; ?>][default_severity]" class="form-select form-select-sm risk-calc-edit" data-qid="<?php echo $q['id']; ?>" id="sev_edit_<?php echo $q['id']; ?>">
                  <option value="1" <?php echo $s == 1 ? 'selected' : ''; ?>>1 - Çok Hafif</option>
                  <option value="2" <?php echo $s == 2 ? 'selected' : ''; ?>>2 - Hafif</option>
                  <option value="3" <?php echo $s == 3 ? 'selected' : ''; ?>>3 - Ciddi</option>
                  <option value="4" <?php echo $s == 4 ? 'selected' : ''; ?>>4 - Çok Ciddi</option>
                  <option value="5" <?php echo $s == 5 ? 'selected' : ''; ?>>5 - Felaket</option>
                </select>
              </div>

              <div class="col-12 col-md-4 d-flex align-items-center">
                <div class="w-100 text-center">
                  <div class="text-muted fs-8 fw-bold">9. Risk Derecesi ($R = O \times Ş$)</div>
                  <div class="fs-5 fw-extrabold text-primary" id="risk_val_<?php echo $q['id']; ?>"><?php echo $r; ?></div>
                </div>
              </div>
            </div>

            <!-- 10. ALINACAK ÖNLEMLER, 11. SORUMLU VE 12. SÜRE -->
            <div class="row g-3">
              <div class="col-12 col-md-5">
                <label class="form-label fw-bold fs-8 text-dark">10. Alınacak Önlemler / İyileştirmeler (Kütüphaneden)</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][default_action_plan]" list="recommendations_list" class="form-control form-control-sm" placeholder="Örn: Lavabo (WC) tavanlarında gerekli yalıtımın sağlanması" value="<?php echo htmlspecialchars($q['default_action_plan'] ?? ''); ?>">
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label fw-bold fs-8 text-dark">11. Sorumlu Birim (Kütüphaneden)</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][default_responsible]" list="responsibles_list" class="form-control form-control-sm" placeholder="Örn: Tekn. Hiz. Yön." value="<?php echo htmlspecialchars($q['default_responsible'] ?? ''); ?>">
              </div>

              <div class="col-12 col-md-3">
                <label class="form-label fw-bold fs-8 text-dark">12. Başlama / Süre</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][default_deadline]" class="form-control form-control-sm" placeholder="Örn: 6 Ay, Sürekli" value="<?php echo htmlspecialchars($q['default_deadline'] ?? ''); ?>">
              </div>
            </div>

          </div>
        </div>
      <?php $qNum++; endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Kaydet Butonu Barı -->
  <div class="custom-card p-3 d-flex align-items-center justify-content-between sticky-bottom bg-white shadow-lg border-top border-2 border-primary mb-5" style="z-index:90;">
    <span class="text-muted fs-8"><i class="bi bi-info-circle me-1"></i> Yapılan tüm 12 sütunlu risk analizi değişikliklerini kaydetmek için tıklayın.</span>
    <button type="submit" class="btn btn-primary-custom px-4 py-2 font-weight-bold shadow">
      <i class="bi bi-check-circle-fill"></i> Tüm Risk Matrisini Kaydet
    </button>
  </div>

</form>

<!-- 9 ADIMLI İNTERAKTİF RİSK MADDESİ OLUŞTURMA SİHİRBAZI MODAL (WIZARD MODAL) -->
<div class="modal fade" id="wizardAddRiskItemModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg rounded-4">
      
      <div class="modal-header bg-dark text-white p-3 rounded-top-4">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-magic text-warning fs-4"></i>
          <div>
            <h5 class="modal-title fw-extrabold text-white mb-0">Adım Adım Risk Maddesi Oluşturma Sihirbazı</h5>
            <span class="fs-8 text-light opacity-75">Resmi Kağıt Belgenizdeki 12 Sütuna Göre Adım Adım Seçim Yapın</span>
          </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body p-4">
        
        <!-- Üst Adım Barı (Stepper) -->
        <div class="d-flex align-items-center justify-content-between mb-4 border-bottom pb-3">
          <span class="badge bg-primary fs-7" id="itemWizardStepBadge">Adım 1 / 9: Risk Grubu</span>
          <div class="progress w-50" style="height: 8px;">
            <div class="progress-bar bg-success" id="itemWizardProgressBar" role="progressbar" style="width: 11%;"></div>
          </div>
        </div>

        <!-- 9 ADIM PANEL İÇERİKLERİ -->
        <div id="itemWizardStepsContainer">
          
          <!-- ADIM 1: RİSK GRUBU SEÇİMİ -->
          <div class="item-wizard-step" id="itemStep1">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-diagram-3-fill text-warning me-1"></i> 1. Adım: Risk Grubu Seçin</h6>
            <div class="row g-3">
              <?php foreach ($riskGroups as $rg): ?>
                <div class="col-12 col-md-6">
                  <div class="card p-3 border hover-shadow cursor-pointer wiz-chip-card wiz-rg-card" data-rgid="<?php echo $rg['id']; ?>" data-rgname="<?php echo htmlspecialchars($rg['group_name']); ?>">
                    <div class="d-flex align-items-center justify-content-between">
                      <span class="fw-bold text-dark"><i class="bi bi-shield-exclamation text-warning me-1"></i> <?php echo htmlspecialchars($rg['group_name']); ?></span>
                      <i class="bi bi-chevron-right text-muted"></i>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" id="wiz_risk_group_id" value="0">
            <input type="hidden" id="wiz_risk_group_name" value="">
          </div>

          <!-- ADIM 2: TEHLİKE KAYNAĞI SEÇİMİ -->
          <div class="item-wizard-step d-none" id="itemStep2">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-box-seam text-primary me-1"></i> 2. Adım: Tehlike Kaynağı Seçin veya Yazın</h6>
            <p class="text-muted fs-8 mb-3">Kütüphanedeki hazır tanımlara tıklayabilir veya kendi ifadenizi yazabilirsiniz:</p>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <?php foreach ($libSources as $src): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary wiz-chip-btn" onclick="setWizInput('wiz_hazard_source', '<?php echo addslashes(htmlspecialchars($src)); ?>', this)"><?php echo htmlspecialchars($src); ?></button>
              <?php endforeach; ?>
            </div>
            <input type="text" id="wiz_hazard_source" class="form-control" placeholder="Örn: Lavabo, Wc tavanı veya Ekranlı Araçlar">
          </div>

          <!-- ADIM 3: TEHLİKE SEÇİMİ -->
          <div class="item-wizard-step d-none" id="itemStep3">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-exclamation-triangle text-danger me-1"></i> 3. Adım: Tehlike Seçin veya Yazın</h6>
            <p class="text-muted fs-8 mb-3">Kütüphanedeki hazır tehlikelere tıklayabilir veya yazabilirsiniz:</p>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <?php foreach ($libHazards as $hz): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary wiz-chip-btn" onclick="setWizInput('wiz_hazard_name', '<?php echo addslashes(htmlspecialchars($hz)); ?>', this)"><?php echo htmlspecialchars($hz); ?></button>
              <?php endforeach; ?>
            </div>
            <input type="text" id="wiz_hazard_name" class="form-control" placeholder="Örn: Enfeksiyon veya Uzun süre sabit oturma">
          </div>

          <!-- ADIM 4: ETKİLENME (YAŞANABİLECEK RİSKLER) -->
          <div class="item-wizard-step d-none" id="itemStep4">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-activity text-warning me-1"></i> 4. Adım: Etkilenme (Yaşanabilecek Riskler)</h6>
            <p class="text-muted fs-8 mb-3">Bu tehlike sonucunda ne tür sağlık/güvenlik riski yaşanabilir?</p>
            <input type="text" id="wiz_affected_risk" class="form-control mb-3" placeholder="Örn: Pis su bulaşma, enfeksiyon maruziyeti veya Kas-iskelet hast.">
          </div>

          <!-- ADIM 5: ETKİLENENLER SEÇİMİ -->
          <div class="item-wizard-step d-none" id="itemStep5">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-people text-info me-1"></i> 5. Adım: Etkilenen Grupları Seçin veya Yazın</h6>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <?php foreach ($libAffected as $aff): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary wiz-chip-btn" onclick="setWizInput('wiz_affected_people', '<?php echo addslashes(htmlspecialchars($aff)); ?>', this)"><?php echo htmlspecialchars($aff); ?></button>
              <?php endforeach; ?>
            </div>
            <input type="text" id="wiz_affected_people" class="form-control" placeholder="Örn: Çalışanlar(Doktor, Hemşire, Sağ. Tek. vd.) Hasta ve hasta yakını">
          </div>

          <!-- ADIM 6: MEVCUT DURUM VE KONTROL SORUSU -->
          <div class="item-wizard-step d-none" id="itemStep6">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-journal-text text-secondary me-1"></i> 6. Adım: Mevcut Durum & Denetim Sorusu Metni</h6>
            <div class="mb-3">
              <label class="form-label fw-bold fs-8">Mevcut Durum / Saha Tespiti</label>
              <input type="text" id="wiz_current_status" class="form-control" placeholder="Örn: Lavabolar tavanda su akıntısı mevcut">
            </div>
            <div>
              <label class="form-label fw-bold fs-8">Saha Denetim Sorusu Metni</label>
              <input type="text" id="wiz_question_text" class="form-control" placeholder="Örn: WC tavanında su sızıntısı var mı?">
            </div>
          </div>

          <!-- ADIM 7: OLASILIK & ŞİDDET RİSK SKORU -->
          <div class="item-wizard-step d-none" id="itemStep7">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-bar-chart-fill text-success me-1"></i> 7. Adım: Olasılık ($O$) & Şiddet ($Ş$) Derecelendirmesi</h6>
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-8">Olasılık ($O: 1-5$)</label>
                <select id="wiz_probability" class="form-select onchange-wiz-calc">
                  <option value="1">1 - Çok Küçük (Çok nadir)</option>
                  <option value="2" selected>2 - Küçük (Nadir)</option>
                  <option value="3">3 - Orta (Olabilir)</option>
                  <option value="4">4 - Yüksek (Sık sık)</option>
                  <option value="5">5 - Çok Yüksek (Her an)</option>
                </select>
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-8">Şiddet ($Ş: 1-5$)</label>
                <select id="wiz_severity" class="form-select onchange-wiz-calc">
                  <option value="1">1 - Çok Hafif (İlk yardım gerektirmez)</option>
                  <option value="2">2 - Hafif (İlk yardım gerekir)</option>
                  <option value="3" selected>3 - Ciddi (Hastane tedavisi gerekir)</option>
                  <option value="4">4 - Çok Ciddi (Ağır yaralanma / kalıcı hasar)</option>
                  <option value="5">5 - Felaket (Ölümcül / Çoklu kayıp)</option>
                </select>
              </div>
            </div>
            <div class="p-3 bg-light rounded-3 text-center border">
              <div class="text-muted fs-8 fw-bold">Hesaplanan Risk Derecesi ($R = O \times Ş$)</div>
              <div class="fs-3 fw-extrabold text-primary" id="wiz_risk_result">R = 6 (Önemli Risk)</div>
            </div>
          </div>

          <!-- ADIM 8: ALINACAK ÖNLEMLER -->
          <div class="item-wizard-step d-none" id="itemStep8">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-lightbulb-fill text-warning me-1"></i> 8. Adım: Alınacak Önlemler / İyileştirmeler</h6>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <?php foreach ($libRecommendations as $rec): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary wiz-chip-btn" onclick="setWizInput('wiz_action_plan', '<?php echo addslashes(htmlspecialchars($rec)); ?>', this)"><?php echo htmlspecialchars($rec); ?></button>
              <?php endforeach; ?>
            </div>
            <input type="text" id="wiz_action_plan" class="form-control" placeholder="Örn: Lavabo (WC) tavanlarında gerekli yalıtımın sağlanması">
          </div>

          <!-- ADIM 9: SORUMLU VE SÜRE -->
          <div class="item-wizard-step d-none" id="itemStep9">
            <h6 class="fw-bold text-dark mb-2"><i class="bi bi-person-gear text-primary me-1"></i> 9. Adım: Sorumlu Birim & Süre/Termin</h6>
            <div class="mb-3">
              <label class="form-label fw-bold fs-8">Sorumlu Birim</label>
              <div class="d-flex flex-wrap gap-2 mb-2">
                <?php foreach ($libResponsibles as $resp): ?>
                  <button type="button" class="btn btn-sm btn-outline-secondary wiz-chip-btn" onclick="setWizInput('wiz_responsible', '<?php echo addslashes(htmlspecialchars($resp)); ?>', this)"><?php echo htmlspecialchars($resp); ?></button>
                <?php endforeach; ?>
              </div>
              <input type="text" id="wiz_responsible" class="form-control" placeholder="Örn: Tekn. Hiz. Yön.">
            </div>
            <div>
              <label class="form-label fw-bold fs-8">Termin / Süre</label>
              <input type="text" id="wiz_deadline" class="form-control" placeholder="Örn: 6 Ay, Sürekli">
            </div>
          </div>

        </div>

      </div>

      <!-- Alt Buton Barı -->
      <div class="modal-footer d-flex align-items-center justify-content-between p-3 bg-light rounded-bottom-4">
        <button type="button" class="btn btn-outline-secondary font-weight-bold d-none" id="wizPrevBtn">
          <i class="bi bi-arrow-left"></i> Önceki Adım
        </button>
        <div></div>
        <button type="button" class="btn btn-success font-weight-bold px-4" id="wizNextBtn">
          Sonraki Adım <i class="bi bi-arrow-right"></i>
        </button>
      </div>

    </div>
  </div>
</div>

<!-- Kütüphaneye Hızlı Öğe Ekleme Modal -->
<div class="modal fade" id="quickAddLibModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="survey_edit.php?id=<?php echo $template_id; ?>">
        <input type="hidden" name="action" value="add_library_item">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill text-primary"></i> İSG Kütüphanesine Yeni Öğe Ekle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Kategori</label>
            <select name="category" class="form-select" required>
              <option value="hazard_source">Tehlike Kaynakları</option>
              <option value="hazard_name">Tehlikeler</option>
              <option value="affected_people">Etkilenen Gruplar</option>
              <option value="responsible_person">Sorumlu Birimler</option>
              <option value="action_recommendation">Önlem & İyileştirme Bankası</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Tanımlanacak İfade / Metin</label>
            <textarea name="item_text" class="form-control" rows="3" placeholder="Örn: Lavabo, WC tavan sızıntısı" required></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-primary font-weight-bold"><i class="bi bi-plus-lg"></i> Kütüphaneye Ekle</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function setWizInput(inputId, val, btnEl) {
  document.getElementById(inputId).value = val;
  if (btnEl) {
    const parent = btnEl.parentElement;
    parent.querySelectorAll('.wiz-chip-btn').forEach(b => b.classList.remove('btn-success', 'text-white'));
    btnEl.classList.add('btn-success', 'text-white');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  
  // Risk Matris Editöründe Canlı $O \times Ş$ Hesaplama
  document.querySelectorAll('.risk-calc-edit').forEach(select => {
    select.addEventListener('change', function() {
      const qId = this.dataset.qid;
      const probSelect = document.getElementById('prob_edit_' + qId);
      const sevSelect = document.getElementById('sev_edit_' + qId);
      const valDiv = document.getElementById('risk_val_' + qId);
      const badgeSpan = document.getElementById('risk_calc_badge_' + qId);

      if (probSelect && sevSelect && valDiv && badgeSpan) {
        const p = parseInt(probSelect.value) || 1;
        const s = parseInt(sevSelect.value) || 1;
        const r = p * s;

        valDiv.textContent = r;

        let category = 'Kabul Edilebilir Risk';
        let badgeBg = 'bg-success';
        if (r >= 16) { category = 'Kabul Edilemez Risk'; badgeBg = 'bg-danger'; }
        else if (r >= 10) { category = 'Dikkate Değer Risk'; badgeBg = 'bg-warning text-dark'; }
        else if (r >= 6) { category = 'Önemli Risk'; badgeBg = 'bg-info text-dark'; }

        badgeSpan.className = 'badge ' + badgeBg;
        badgeSpan.textContent = `R = ${r} (${category})`;
      }
    });
  });

});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
