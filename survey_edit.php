<?php
/**
 * Tubİsg - Anket Soruları, İSG Risk Matrisi & Tetikleyici Editörü
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

// İSG Kütüphanelerinden Autocomplete Verilerini Çek
$libSources = $db->query("SELECT item_text FROM risk_libraries WHERE category = 'hazard_source' ORDER BY item_text ASC")->fetchAll(PDO::FETCH_COLUMN);
$libHazards = $db->query("SELECT item_text FROM risk_libraries WHERE category = 'hazard_name' ORDER BY item_text ASC")->fetchAll(PDO::FETCH_COLUMN);
$libAffected = $db->query("SELECT item_text FROM risk_libraries WHERE category = 'affected_people' ORDER BY item_text ASC")->fetchAll(PDO::FETCH_COLUMN);

// Form Post İşlemleri (Güncelleme / Soru Ekleme / Silme)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Soru Silme İşlemi
    if (isset($_POST['delete_question_id'])) {
        $qId = (int)$_POST['delete_question_id'];
        $db->prepare("DELETE FROM survey_questions WHERE id = ? AND template_id = ?")->execute([$qId, $template_id]);
        log_action('Anket Sorusu Silindi', "Anket ID: #{$template_id}, Soru ID: #{$qId}");
        set_flash('success', 'Soru silindi.');
        header("Location: survey_edit.php?id=" . $template_id);
        exit;
    }

    // 2. Seçenek Silme İşlemi
    if (isset($_POST['delete_option_id'])) {
        $optId = (int)$_POST['delete_option_id'];
        $db->prepare("DELETE FROM question_options WHERE id = ?")->execute([$optId]);
        log_action('Soru Seçeneği Silindi', "Anket ID: #{$template_id}, Seçenek ID: #{$optId}");
        set_flash('success', 'Seçenek silindi.');
        header("Location: survey_edit.php?id=" . $template_id);
        exit;
    }

    // 3. Mevcut Soruları Güncelleme
    if (isset($_POST['questions']) && is_array($_POST['questions'])) {
        foreach ($_POST['questions'] as $qId => $qData) {
            $qText = trim($qData['text'] ?? '');
            $riskGroupId = (int)($qData['risk_group_id'] ?? 0);
            $hazardSource = trim($qData['hazard_source'] ?? '');
            $hazardName = trim($qData['hazard_name'] ?? '');
            $affectedRisk = trim($qData['affected_risk'] ?? '');
            $affectedPeople = trim($qData['affected_people'] ?? '');

            if (!empty($qText)) {
                $stmtUpd = $db->prepare("
                    UPDATE survey_questions 
                    SET question_text = ?, risk_group_id = ?, hazard_source = ?, hazard_name = ?, affected_risk = ?, affected_people = ?
                    WHERE id = ? AND template_id = ?
                ");
                $stmtUpd->execute([
                    $qText, 
                    $riskGroupId > 0 ? $riskGroupId : null, 
                    $hazardSource, 
                    $hazardName, 
                    $affectedRisk, 
                    $affectedPeople, 
                    $qId, 
                    $template_id
                ]);
                
                if (isset($qData['options']) && is_array($qData['options'])) {
                    foreach ($qData['options'] as $optId => $optData) {
                        $optText = trim($optData['text'] ?? '');
                        $points = (int)($optData['points'] ?? 0);
                        $trigger = isset($optData['trigger_action']) && $optData['trigger_action'] == 1 ? 1 : 0;
                        if (!empty($optText)) {
                            $db->prepare("UPDATE question_options SET option_text = ?, points = ?, trigger_action = ? WHERE id = ? AND question_id = ?")->execute([$optText, $points, $trigger, $optId, $qId]);
                        }
                    }
                }
            }
        }
    }

    // 4. Yeni Soru Ekleme
    if (isset($_POST['new_questions']) && is_array($_POST['new_questions'])) {
        foreach ($_POST['new_questions'] as $newQ) {
            $qText = trim($newQ['text'] ?? '');
            $riskGroupId = (int)($newQ['risk_group_id'] ?? 0);
            $hazardSource = trim($newQ['hazard_source'] ?? '');
            $hazardName = trim($newQ['hazard_name'] ?? '');
            $affectedRisk = trim($newQ['affected_risk'] ?? '');
            $affectedPeople = trim($newQ['affected_people'] ?? '');

            if (!empty($qText)) {
                $stmtQ = $db->prepare("
                    INSERT INTO survey_questions (template_id, risk_group_id, question_text, hazard_source, hazard_name, affected_risk, affected_people) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtQ->execute([
                    $template_id, 
                    $riskGroupId > 0 ? $riskGroupId : null, 
                    $qText, 
                    $hazardSource, 
                    $hazardName, 
                    $affectedRisk, 
                    $affectedPeople
                ]);
                $qId = $db->lastInsertId();

                if (isset($newQ['options']) && is_array($newQ['options'])) {
                    $stmtOpt = $db->prepare("INSERT INTO question_options (question_id, option_text, points, trigger_action) VALUES (?, ?, ?, ?)");
                    foreach ($newQ['options'] as $opt) {
                        $optText = trim($opt['text'] ?? '');
                        $points = (int)($opt['points'] ?? 0);
                        $trigger = isset($opt['trigger_action']) && $opt['trigger_action'] == 1 ? 1 : 0;
                        if (!empty($optText)) {
                            $stmtOpt->execute([$qId, $optText, $points, $trigger]);
                        }
                    }
                } else {
                    // Varsayılan 4 Standart İSG Seçeneği Ekle (Evet, Hayır, Kısmen, Denetim Dışı)
                    $defaultOptions = [
                        ['text' => 'Evet (Uygun)', 'points' => 10, 'trigger' => 0],
                        ['text' => 'Hayır (Uygun Değil)', 'points' => 0, 'trigger' => 1],
                        ['text' => 'Kısmen (Kısmen Uygun)', 'points' => 5, 'trigger' => 1],
                        ['text' => 'Denetim Dışı / Muaf', 'points' => 10, 'trigger' => 0]
                    ];
                    $stmtOpt = $db->prepare("INSERT INTO question_options (question_id, option_text, points, trigger_action) VALUES (?, ?, ?, ?)");
                    foreach ($defaultOptions as $defOpt) {
                        $stmtOpt->execute([$qId, $defOpt['text'], $defOpt['points'], $defOpt['trigger']]);
                    }
                }
            }
        }
    }

    log_action('Anket Soruları Güncellendi', "Anket: {$template['title']} (ID: #{$template_id}) soruları ve kütüphane bağlantıları güncellendi.");
    set_flash('success', 'Anket soruları, seçenek tetikleyicileri ve risk matrisi bilgileri güncellendi.');
    header("Location: survey_edit.php?id=" . $template_id);
    exit;
}

// Soruları ve Seçenekleri Çek
$questionsStmt = $db->prepare("
    SELECT sq.*, rg.group_name
    FROM survey_questions sq
    LEFT JOIN risk_groups rg ON sq.risk_group_id = rg.id
    WHERE sq.template_id = ? 
    ORDER BY sq.sort_order ASC, sq.id ASC
");
$questionsStmt->execute([$template_id]);
$questions = $questionsStmt->fetchAll();

foreach ($questions as &$q) {
    $optStmt = $db->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order ASC, id ASC");
    $optStmt->execute([$q['id']]);
    $q['options'] = $optStmt->fetchAll();
}
unset($q);

$pageTitle = 'Anket Soruları & Risk Editörü: ' . $template['title'];
include __DIR__ . '/includes/header.php';
?>

<!-- HTML5 Autocomplete Datalist Öğeleri -->
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

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div>
    <a href="survey_templates.php" class="text-decoration-none text-muted fs-8 font-weight-bold d-block mb-1">
      <i class="bi bi-arrow-left"></i> Anket Profillerine Dön
    </a>
    <h3 class="fw-extrabold m-0"><?php echo htmlspecialchars($template['title']); ?></h3>
    <p class="text-muted fs-7 m-0">Risk Grupları, Kütüphane Otomatik Tamamlamaları ve Seçenek Tetikleyicilerini yönetin</p>
  </div>
  <div class="d-flex gap-2">
    <a href="risk_libraries.php" target="_blank" class="btn btn-outline-primary font-weight-bold">
      <i class="bi bi-book-fill"></i> İSG Kütüphanesi
    </a>
    <button type="button" id="addQuestionBtn" class="btn btn-outline-success font-weight-bold">
      <i class="bi bi-plus-circle-fill"></i> Yeni Risk Sorusu Ekle
    </button>
  </div>
</div>

<form method="POST" action="survey_edit.php?id=<?php echo $template_id; ?>" id="surveyEditForm">

  <div id="questionsContainer">
    <?php if (empty($questions)): ?>
      <div class="alert alert-warning text-center py-4">
        <i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2"></i>
        Bu anket profilinde henüz hiç risk tanımı bulunmuyor. Yukarıdaki <strong>"Yeni Risk Sorusu Ekle"</strong> butonuna tıklayarak ekleyebilirsiniz.
      </div>
    <?php else: ?>
      <?php $qNum = 1; foreach ($questions as $q): ?>
        <div class="custom-card question-builder-card mb-4" data-qindex="<?php echo $qNum; ?>">
          <div class="custom-card-header bg-light p-3 rounded-top">
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-primary rounded-circle" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;"><?php echo $qNum; ?></span>
              <h6 class="m-0 font-weight-bold">Risk Sorusu #<?php echo $qNum; ?> <?php if ($q['group_name']): ?><span class="badge bg-warning text-dark ms-2"><?php echo htmlspecialchars($q['group_name']); ?></span><?php endif; ?></h6>
            </div>
            <button type="submit" name="delete_question_id" value="<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-danger" data-confirm-btn="true" data-confirm-title="Soruyu Sil" data-confirm-text="Bu soruyu ve bağlı tüm cevap seçeneklerini silmek istediğinize emin misiniz?">
              <i class="bi bi-trash"></i> Soruyu Sil
            </button>
          </div>

          <div class="p-3">
            <!-- Risk Grubu ve Tehlike Kaynağı Row -->
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-4">
                <label class="form-label fw-bold fs-8 text-muted"><i class="bi bi-exclamation-triangle"></i> Risk Grubu</label>
                <select name="questions[<?php echo $q['id']; ?>][risk_group_id]" class="form-select form-select-sm">
                  <option value="0">-- Risk Grubu Seçin --</option>
                  <?php foreach ($riskGroups as $rg): ?>
                    <option value="<?php echo $rg['id']; ?>" <?php echo $q['risk_group_id'] == $rg['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($rg['group_name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label fw-bold fs-8 text-muted">Tehlike Kaynağı (Otomatik Tamamlamalı)</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][hazard_source]" list="hazard_sources_list" class="form-control form-control-sm" placeholder="Örn: Lavabo/WC tavanı, Ekranlı Araçlar" value="<?php echo htmlspecialchars($q['hazard_source'] ?? ''); ?>">
              </div>

              <div class="col-12 col-md-4">
                <label class="form-label fw-bold fs-8 text-muted">Tehlike Metni (Otomatik Tamamlamalı)</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][hazard_name]" list="hazards_list" class="form-control form-control-sm" placeholder="Örn: Enfeksiyon, Uzun süre sabit oturma" value="<?php echo htmlspecialchars($q['hazard_name'] ?? ''); ?>">
              </div>
            </div>

            <!-- Etkilenme ve Etkilenenler Row -->
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-8 text-muted">Etkilenme (Yaşanabilecek Riskler)</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][affected_risk]" class="form-control form-control-sm" placeholder="Örn: Pis su bulaşma, Kas-iskelet hast." value="<?php echo htmlspecialchars($q['affected_risk'] ?? ''); ?>">
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-8 text-muted">Etkilenenler (Otomatik Tamamlamalı)</label>
                <input type="text" name="questions[<?php echo $q['id']; ?>][affected_people]" list="affected_list" class="form-control form-control-sm" placeholder="Örn: Çalışanlar (Doktor, Hemşire), Hasta ve yakınları" value="<?php echo htmlspecialchars($q['affected_people'] ?? ''); ?>">
              </div>
            </div>

            <!-- Soru Metni -->
            <div class="mb-3">
              <label class="form-label fw-bold">Kontrol / Denetim Sorusu Metni</label>
              <input type="text" name="questions[<?php echo $q['id']; ?>][text]" class="form-control" value="<?php echo htmlspecialchars($q['question_text']); ?>" placeholder="Örn: Lavabo tavanlarında su sızıntısı veya yalıtım eksikliği var mı?" required>
            </div>

            <!-- Cevap Seçenekleri & Tetikleyici Şartları -->
            <div class="mb-2 d-flex align-items-center justify-content-between">
              <label class="form-label fw-bold m-0 fs-8 text-muted"><i class="bi bi-list-check"></i> Cevap Seçenekleri, Puanlar & Önlem Kartı Tetikleyicisi</label>
            </div>

            <div class="options-list-container">
              <?php foreach ($q['options'] as $opt): ?>
                <div class="row g-2 mb-2 option-row align-items-center">
                  <div class="col-5">
                    <input type="text" name="questions[<?php echo $q['id']; ?>][options][<?php echo $opt['id']; ?>][text]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($opt['option_text']); ?>" required>
                  </div>
                  <div class="col-3">
                    <div class="input-group input-group-sm">
                      <span class="input-group-text bg-light fw-bold">Puan</span>
                      <input type="number" name="questions[<?php echo $q['id']; ?>][options][<?php echo $opt['id']; ?>][points]" class="form-control" value="<?php echo (int)$opt['points']; ?>" required>
                    </div>
                  </div>
                  <div class="col-3">
                    <div class="form-check form-switch pt-1">
                      <input class="form-check-input" type="checkbox" name="questions[<?php echo $q['id']; ?>][options][<?php echo $opt['id']; ?>][trigger_action]" value="1" id="trig_<?php echo $opt['id']; ?>" <?php echo $opt['trigger_action'] == 1 ? 'checked' : ''; ?>>
                      <label class="form-check-label fs-8 fw-bold text-danger" for="trig_<?php echo $opt['id']; ?>">
                        Önlem Kartı Açsın
                      </label>
                    </div>
                  </div>
                  <div class="col-1 text-end">
                    <button type="submit" name="delete_option_id" value="<?php echo $opt['id']; ?>" class="btn btn-sm btn-link text-danger p-0" data-confirm-btn="true" data-confirm-title="Seçeneği Sil" data-confirm-text="Bu cevap seçeneğini silmek istediğinize emin misiniz?">
                      <i class="bi bi-x-circle-fill fs-5"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php $qNum++; endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Kaydet Butonu Barı -->
  <div class="custom-card p-3 d-flex align-items-center justify-content-between sticky-bottom bg-white shadow-lg border-top border-2 border-primary mb-5" style="z-index:90;">
    <span class="text-muted fs-8"><i class="bi bi-info-circle me-1"></i> Yapılan tüm soru, risk, puan ve tetikleyici değişikliklerini kaydetmek için tıklayın.</span>
    <button type="submit" class="btn btn-primary-custom px-4 py-2 font-weight-bold">
      <i class="bi bi-check-circle-fill"></i> Tüm Değişiklikleri Kaydet
    </button>
  </div>

</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
