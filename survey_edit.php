<?php
/**
 * Tubİsg - Anket Soruları & Seçenek Puan Editörü
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
            if (!empty($qText)) {
                $db->prepare("UPDATE survey_questions SET question_text = ? WHERE id = ? AND template_id = ?")->execute([$qText, $qId, $template_id]);
                
                if (isset($qData['options']) && is_array($qData['options'])) {
                    foreach ($qData['options'] as $optId => $optData) {
                        $optText = trim($optData['text'] ?? '');
                        $points = (int)($optData['points'] ?? 0);
                        if (!empty($optText)) {
                            $db->prepare("UPDATE question_options SET option_text = ?, points = ? WHERE id = ? AND question_id = ?")->execute([$optText, $points, $optId, $qId]);
                        }
                    }
                }
            }
        }
    }

    // 4. Yeni Dinamik Soruları Kaydetme
    if (isset($_POST['new_questions']) && is_array($_POST['new_questions'])) {
        foreach ($_POST['new_questions'] as $newQ) {
            $qText = trim($newQ['text'] ?? '');
            if (!empty($qText)) {
                $stmtQ = $db->prepare("INSERT INTO survey_questions (template_id, question_text) VALUES (?, ?)");
                $stmtQ->execute([$template_id, $qText]);
                $qId = $db->lastInsertId();

                if (isset($newQ['options']) && is_array($newQ['options'])) {
                    $stmtOpt = $db->prepare("INSERT INTO question_options (question_id, option_text, points) VALUES (?, ?, ?)");
                    foreach ($newQ['options'] as $opt) {
                        $optText = trim($opt['text'] ?? '');
                        $points = (int)($opt['points'] ?? 0);
                        if (!empty($optText)) {
                            $stmtOpt->execute([$qId, $optText, $points]);
                        }
                    }
                }
            }
        }
    }

    log_action('Anket Soruları Güncellendi', "Anket: {$template['title']} (ID: #{$template_id}) soruları ve puanlamaları güncellendi.");
    set_flash('success', 'Anket soruları ve puanlama haritası başarıyla güncellendi.');
    header("Location: survey_edit.php?id=" . $template_id);
    exit;
}

// Soruları ve Seçenekleri Çek
$questionsStmt = $db->prepare("SELECT * FROM survey_questions WHERE template_id = ? ORDER BY sort_order ASC, id ASC");
$questionsStmt->execute([$template_id]);
$questions = $questionsStmt->fetchAll();

foreach ($questions as &$q) {
    $optStmt = $db->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order ASC, id ASC");
    $optStmt->execute([$q['id']]);
    $q['options'] = $optStmt->fetchAll();
}
unset($q);

$pageTitle = 'Anket Soruları Editörü: ' . $template['title'];
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div>
    <a href="survey_templates.php" class="text-decoration-none text-muted fs-8 font-weight-bold d-block mb-1">
      <i class="bi bi-arrow-left"></i> Anket Profillerine Dön
    </a>
    <h3 class="fw-extrabold m-0"><?php echo htmlspecialchars($template['title']); ?></h3>
    <p class="text-muted fs-7 m-0">Soruları, seçenekleri ve pozitif/negatif puanlama sistemini yönetin</p>
  </div>
  <div class="d-flex gap-2">
    <button type="button" id="addQuestionBtn" class="btn btn-outline-success font-weight-bold">
      <i class="bi bi-plus-circle-fill"></i> Yeni Soru Ekle
    </button>
  </div>
</div>

<form method="POST" action="survey_edit.php?id=<?php echo $template_id; ?>" id="surveyEditForm">

  <div id="questionsContainer">
    <?php if (empty($questions)): ?>
      <div class="alert alert-warning text-center py-4">
        <i class="bi bi-exclamation-triangle-fill fs-3 d-block mb-2"></i>
        Bu anket profilinde henüz hiç soru bulunmuyor. Yukarıdaki <strong>"Yeni Soru Ekle"</strong> butonuna tıklayarak soru tanımlayabilirsiniz.
      </div>
    <?php else: ?>
      <?php $qNum = 1; foreach ($questions as $q): ?>
        <div class="custom-card question-builder-card mb-4" data-qindex="<?php echo $qNum; ?>">
          <div class="custom-card-header bg-light p-3 rounded-top">
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-primary rounded-circle" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;"><?php echo $qNum; ?></span>
              <h6 class="m-0 font-weight-bold">Soru #<?php echo $qNum; ?></h6>
            </div>
            <button type="submit" name="delete_question_id" value="<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-danger" data-confirm-btn="true" data-confirm-title="Soruyu Sil" data-confirm-text="Bu soruyu ve bağlı tüm cevap seçeneklerini silmek istediğinize emin misiniz?">
              <i class="bi bi-trash"></i> Soruyu Sil
            </button>
          </div>
          <div class="p-3">
            <div class="mb-3">
              <label class="form-label fw-bold">Soru Metni</label>
              <input type="text" name="questions[<?php echo $q['id']; ?>][text]" class="form-control" value="<?php echo htmlspecialchars($q['question_text']); ?>" required>
            </div>

            <div class="mb-2 d-flex align-items-center justify-content-between">
              <label class="form-label fw-bold m-0"><i class="bi bi-list-check"></i> Seçenekler & Puan Haritası</label>
            </div>

            <div class="options-list-container">
              <?php foreach ($q['options'] as $opt): ?>
                <div class="row g-2 mb-2 option-row align-items-center">
                  <div class="col-7">
                    <input type="text" name="questions[<?php echo $q['id']; ?>][options][<?php echo $opt['id']; ?>][text]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($opt['option_text']); ?>" required>
                  </div>
                  <div class="col-4">
                    <div class="input-group input-group-sm">
                      <span class="input-group-text bg-light fw-bold">Puan</span>
                      <input type="number" name="questions[<?php echo $q['id']; ?>][options][<?php echo $opt['id']; ?>][points]" class="form-control" value="<?php echo (int)$opt['points']; ?>" required>
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
    <span class="text-muted fs-8"><i class="bi bi-info-circle me-1"></i> Yapılan tüm soru ve puan değişikliklerini kaydetmek için tıklayın.</span>
    <button type="submit" class="btn btn-primary-custom px-4 py-2 font-weight-bold">
      <i class="bi bi-check-circle-fill"></i> Tüm Değişiklikleri Kaydet
    </button>
  </div>

</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
