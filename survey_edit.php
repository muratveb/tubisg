<?php
/**
 * Tubİsg - Anket Soruları & Seçenek Puan Editörü
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('surveys_manage');

$db = getDB();
$template_id = (int)($_GET['id'] ?? 0);

if ($template_id <= 0) {
    header("Location: survey_templates.php");
    exit;
}

// Anket profili bilgilerini çek
$stmt = $db->prepare("SELECT * FROM survey_templates WHERE id = ?");
$stmt->execute([$template_id]);
$template = $stmt->fetch();

if (!$template) {
    set_flash('danger', 'Anket profili bulunamadı.');
    header("Location: survey_templates.php");
    exit;
}

// Form Post İşlemleri (Soru & Seçenek Ekleme / Güncelleme / Silme)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Yeni Eklenen Sorular ve Seçenekleri Kaydet
    if (isset($_POST['new_questions']) && is_array($_POST['new_questions'])) {
        foreach ($_POST['new_questions'] as $qData) {
            $qText = trim($qData['text'] ?? '');
            if (!empty($qText)) {
                $stmtQ = $db->prepare("INSERT INTO survey_questions (template_id, question_text) VALUES (?, ?)");
                $stmtQ->execute([$template_id, $qText]);
                $questionId = $db->lastInsertId();

                if (isset($qData['options']) && is_array($qData['options'])) {
                    foreach ($qData['options'] as $oData) {
                        $optText = trim($oData['text'] ?? '');
                        $points = (int)($oData['points'] ?? 0);
                        if (!empty($optText)) {
                            $stmtO = $db->prepare("INSERT INTO question_options (question_id, option_text, points) VALUES (?, ?, ?)");
                            $stmtO->execute([$questionId, $optText, $points]);
                        }
                    }
                }
            }
        }
    }

    // 2. Mevcut Soruları Güncelle
    if (isset($_POST['questions']) && is_array($_POST['questions'])) {
        foreach ($_POST['questions'] as $qId => $qData) {
            $qId = (int)$qId;
            $qText = trim($qData['text'] ?? '');
            if ($qId > 0 && !empty($qText)) {
                $stmtQ = $db->prepare("UPDATE survey_questions SET question_text = ? WHERE id = ? AND template_id = ?");
                $stmtQ->execute([$qText, $qId, $template_id]);

                if (isset($qData['options']) && is_array($qData['options'])) {
                    foreach ($qData['options'] as $oId => $oData) {
                        $oId = (int)$oId;
                        $optText = trim($oData['text'] ?? '');
                        $points = (int)($oData['points'] ?? 0);
                        if ($oId > 0 && !empty($optText)) {
                            $stmtO = $db->prepare("UPDATE question_options SET option_text = ?, points = ? WHERE id = ? AND question_id = ?");
                            $stmtO->execute([$optText, $points, $oId, $qId]);
                        }
                    }
                }
            }
        }
    }

    // 3. Tekil Soru Silme İsteği
    if (isset($_POST['delete_question_id'])) {
        $delQId = (int)$_POST['delete_question_id'];
        if ($delQId > 0) {
            $stmtDel = $db->prepare("DELETE FROM survey_questions WHERE id = ? AND template_id = ?");
            $stmtDel->execute([$delQId, $template_id]);
        }
    }

    // 4. Tekil Seçenek Silme İsteği
    if (isset($_POST['delete_option_id'])) {
        $delOId = (int)$_POST['delete_option_id'];
        if ($delOId > 0) {
            $stmtDelO = $db->prepare("DELETE FROM question_options WHERE id = ?");
            $stmtDelO->execute([$delOId]);
        }
    }

    set_flash('success', 'Anket soruları ve puanlamaları başarıyla güncellendi.');
    header("Location: survey_edit.php?id=" . $template_id);
    exit;
}

// Soruları ve Seçeneklerini Çek
$questionsStmt = $db->prepare("SELECT * FROM survey_questions WHERE template_id = ? ORDER BY sort_order ASC, id ASC");
$questionsStmt->execute([$template_id]);
$questions = $questionsStmt->fetchAll();

foreach ($questions as &$q) {
    $optStmt = $db->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order ASC, id ASC");
    $optStmt->execute([$q['id']]);
    $q['options'] = $optStmt->fetchAll();
}
unset($q);

$pageTitle = 'Soru ve Puan Tanımlama: ' . $template['title'];
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <a href="survey_templates.php" class="btn btn-sm btn-outline-secondary mb-2">
      <i class="bi bi-arrow-left"></i> Anket Profillerine Dön
    </a>
    <h3 class="fw-extrabold m-0"><?php echo htmlspecialchars($template['title']); ?></h3>
    <p class="text-muted fs-7 m-0">Soruları ekleyin ve her seçeneğe pozitif (+5, +10) veya negatif (-5, -10) puan tanımlayın.</p>
  </div>
  <button type="button" id="addQuestionBtn" class="btn btn-primary-custom">
    <i class="bi bi-plus-circle-fill"></i> Yeni Soru Ekle
  </button>
</div>

<form method="POST" action="survey_edit.php?id=<?php echo $template_id; ?>" id="surveyEditForm">
  <div id="questionsContainer">
    <?php if (empty($questions)): ?>
      <div class="custom-card text-center py-5" id="emptyNotice">
        <i class="bi bi-question-circle fs-1 text-muted d-block mb-3"></i>
        <h5>Bu anket profilinde henüz soru bulunmuyor.</h5>
        <p class="text-muted">Yukarıdaki <strong>"Yeni Soru Ekle"</strong> butonuna tıklayarak soru ve seçenek puanlarını tanımlamaya başlayabilirsiniz.</p>
      </div>
    <?php else: ?>
      <?php $qNum = 1; foreach ($questions as $q): ?>
        <div class="custom-card question-builder-card mb-4" data-qindex="<?php echo $qNum; ?>">
          <div class="custom-card-header bg-light p-3 rounded-top">
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-primary rounded-circle" style="width:28px; height:28px; display:flex; align-items:center; justify-content:center;"><?php echo $qNum; ?></span>
              <h6 class="m-0 font-weight-bold">Soru #<?php echo $qNum; ?></h6>
            </div>
            <button type="submit" name="delete_question_id" value="<?php echo $q['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bu soruyu silmek istediğinize emin misiniz?');">
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
                    <button type="submit" name="delete_option_id" value="<?php echo $opt['id']; ?>" class="btn btn-sm btn-link text-danger p-0" onclick="return confirm('Bu seçeneği silmek istediğinize emin misiniz?');">
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

  <div class="sticky-bottom bg-white p-3 border-top rounded-top shadow-lg d-flex justify-content-between align-items-center mt-4">
    <span class="text-muted fs-7"><i class="bi bi-info-circle"></i> Değişiklikleri kaydetmek için aşağıdaki butona basın.</span>
    <button type="submit" class="btn btn-success px-4 py-2 font-weight-bold">
      <i class="bi bi-check-circle-fill"></i> Tüm Değişiklikleri Kaydet
    </button>
  </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
