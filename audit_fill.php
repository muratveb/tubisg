<?php
/**
 * Tubİsg - Saha Denetimi Doldurma Ekranı (Mobil & Tablet Öncelikli)
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('audit_conduct');

$db = getDB();
$user = get_current_user_data();

$template_id = (int)($_GET['template_id'] ?? 0);
$unit_id = (int)($_GET['unit_id'] ?? 0);

if ($template_id <= 0 || $unit_id <= 0) {
    set_flash('warning', 'Lütfen denetim başlatmak için anket profili ve birim seçin.');
    header("Location: audit_new.php");
    exit;
}

// Anket ve Birim Detaylarını Çek
$stmtTpl = $db->prepare("SELECT * FROM survey_templates WHERE id = ?");
$stmtTpl->execute([$template_id]);
$template = $stmtTpl->fetch();

$stmtUnit = $db->prepare("SELECT * FROM units WHERE id = ?");
$stmtUnit->execute([$unit_id]);
$unit = $stmtUnit->fetch();

if (!$template || !$unit) {
    set_flash('danger', 'Geçersiz anket veya birim seçimi.');
    header("Location: audit_new.php");
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

// Denetim Formu Kaydedildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answersInput = $_POST['answers'] ?? []; // format: [question_id => [option_id1, option_id2]]
    $notes = trim($_POST['notes'] ?? '');

    $totalScore = 0;
    $maxPossibleScore = 0;

    $selectedOptionsData = [];

    foreach ($questions as $q) {
        $qMax = 0;
        foreach ($q['options'] as $opt) {
            if ((int)$opt['points'] > $qMax) {
                $qMax = (int)$opt['points'];
            }
        }
        $maxPossibleScore += $qMax;

        if (isset($answersInput[$q['id']]) && is_array($answersInput[$q['id']])) {
            foreach ($answersInput[$q['id']] as $selectedOptId) {
                $selectedOptId = (int)$selectedOptId;
                foreach ($q['options'] as $opt) {
                    if ((int)$opt['id'] === $selectedOptId) {
                        $pts = (int)$opt['points'];
                        $totalScore += $pts;
                        $selectedOptionsData[] = [
                            'question_id' => $q['id'],
                            'option_id'   => $opt['id'],
                            'points'      => $pts
                        ];
                        break;
                    }
                }
            }
        }
    }

    $percentageScore = 0.00;
    if ($maxPossibleScore > 0) {
        $percentageScore = round(($totalScore / $maxPossibleScore) * 100, 2);
        if ($percentageScore < 0) $percentageScore = 0.00;
    }

    // Denetim Kaydını Oluştur
    $stmtAudit = $db->prepare("
        INSERT INTO audits (template_id, unit_id, auditor_id, total_score, max_possible_score, percentage_score, status, notes) 
        VALUES (?, ?, ?, ?, ?, ?, 'Tamamlandı', ?)
    ");
    $stmtAudit->execute([$template_id, $unit_id, $user['id'], $totalScore, $maxPossibleScore, $percentageScore, $notes]);
    $auditId = $db->lastInsertId();

    // Cevapları Kaydet
    $stmtAns = $db->prepare("INSERT INTO audit_answers (audit_id, question_id, option_id, points_awarded) VALUES (?, ?, ?, ?)");
    foreach ($selectedOptionsData as $ans) {
        $stmtAns->execute([$auditId, $ans['question_id'], $ans['option_id'], $ans['points']]);
    }

    log_action('Saha Denetimi Tamamlandı', "Birim: {$unit['unit_name']}, Anket: {$template['title']}, Skor: %{$percentageScore} (#DEN-" . sprintf('%04d', $auditId) . ")");

    set_flash('success', 'Saha denetimi başarıyla tamamlandı ve kaydedildi.');
    header("Location: audit_detail.php?id=" . $auditId);
    exit;
}

$pageTitle = 'Saha Denetimi: ' . $unit['unit_name'];
include __DIR__ . '/includes/header.php';
?>

<!-- Üst Başlık ve Sabit Skor Rozeti Barı -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
  <div>
    <span class="badge bg-primary-light text-primary font-weight-bold mb-1">
      <i class="bi bi-building"></i> BİRİM: <?php echo htmlspecialchars($unit['unit_name']); ?>
    </span>
    <h3 class="fw-extrabold m-0"><?php echo htmlspecialchars($template['title']); ?></h3>
  </div>
  <div class="text-muted fs-8">
    <i class="bi bi-person-fill"></i> Denetçi: <strong><?php echo htmlspecialchars($user['name_surname']); ?></strong>
  </div>
</div>

<!-- Canlı Hesaplayıcı Skor Barı (Yapışkan / Sticky) -->
<div class="sticky-audit-scorebar">
  <div class="d-flex align-items-center gap-3 flex-grow-1">
    <div class="text-center">
      <div class="score-number" id="liveTotalScore">0</div>
      <div class="fs-8 opacity-75" style="font-size: 0.65rem;">Puan / <span id="liveMaxScore">0</span></div>
    </div>
    
    <div class="score-progress-bar">
      <div class="score-progress-fill" id="scoreProgressFill"></div>
    </div>

    <div class="text-end">
      <div class="fw-extrabold" id="livePercentage">%0</div>
      <span id="scoreStatusBadge" class="badge bg-secondary">HESAPLANIYOR</span>
    </div>
  </div>
</div>

<form method="POST" action="audit_fill.php?template_id=<?php echo $template_id; ?>&unit_id=<?php echo $unit_id; ?>" id="auditFillForm">

  <div class="mb-4">
    <?php if (empty($questions)): ?>
      <div class="alert alert-warning">Bu anket profilinde henüz tanımlanmış soru bulunmuyor.</div>
    <?php else: ?>
      <?php $qIndex = 1; foreach ($questions as $q): ?>
        <div class="question-card">
          <div class="question-title">
            <span class="question-number"><?php echo $qIndex; ?></span>
            <div><?php echo htmlspecialchars($q['question_text']); ?></div>
          </div>

          <div class="option-checkbox-group">
            <?php foreach ($q['options'] as $opt): ?>
              <?php
              $pts = (int)$opt['points'];
              $ptsBadgeClass = $pts > 0 ? 'positive' : ($pts < 0 ? 'negative' : 'neutral');
              $ptsSign = $pts > 0 ? '+' : '';
              ?>
              <div class="option-item-card" data-points="<?php echo $pts; ?>">
                <div class="option-left">
                  <div class="custom-checkbox">
                    <i class="bi bi-check text-white d-none check-icon"></i>
                  </div>
                  <input type="checkbox" name="answers[<?php echo $q['id']; ?>][]" value="<?php echo $opt['id']; ?>" class="d-none option-checkbox">
                  <span class="option-text"><?php echo htmlspecialchars($opt['option_text']); ?></span>
                </div>
                <span class="point-badge <?php echo $ptsBadgeClass; ?>">
                  <?php echo $ptsSign . $pts; ?> Puan
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php $qIndex++; endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Saha Notları ve Gözlem Kutusu -->
  <div class="custom-card mb-4">
    <div class="custom-card-header">
      <h6 class="custom-card-title m-0">
        <i class="bi bi-pencil-square text-warning"></i> Denetçi Saha Notları & Gözlemleri (Opsiyonel)
      </h6>
    </div>
    <textarea name="notes" class="form-control" rows="3" placeholder="Saha denetimi esnasında tespit edilen özel hususlar, eksiklikler veya uyarılar..."></textarea>
  </div>

  <!-- Kaydet Butonu -->
  <div class="d-grid gap-2 mb-5">
    <button type="submit" class="btn btn-primary-custom py-3 fs-6 font-weight-bold shadow-lg">
      <i class="bi bi-check-circle-fill fs-5"></i> Saha Denetimini Tamamla ve Kaydet
    </button>
  </div>

</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
