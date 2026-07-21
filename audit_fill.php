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

    set_flash('success', 'Saha denetimi başarıyla tamamlandı ve kaydedildi.');
    header("Location: audit_detail.php?id=" . $auditId);
    exit;
}

$pageTitle = 'Saha Denetimi: ' . $unit['unit_name'];
include __DIR__ . '/includes/header.php';
?>

<!-- Denetim Başlığı Kartı -->
<div class="custom-card mb-3 p-3 bg-white">
  <div class="d-flex align-items-center justify-content-between">
    <div>
      <span class="badge bg-primary-light text-primary font-weight-bold mb-1">
        <i class="bi bi-building"></i> <?php echo htmlspecialchars($unit['unit_name']); ?>
      </span>
      <h4 class="fw-extrabold m-0 text-dark fs-5"><?php echo htmlspecialchars($template['title']); ?></h4>
    </div>
    <div class="text-end text-muted fs-8">
      <div><i class="bi bi-person"></i> <?php echo htmlspecialchars($user['name_surname']); ?></div>
      <div><i class="bi bi-calendar3"></i> <?php echo date('d.m.Y'); ?></div>
    </div>
  </div>
</div>

<!-- Canlı Skor ve Yüzde Çubuğu -->
<div class="sticky-audit-scorebar">
  <div>
    <div class="fs-8 text-light opacity-75">TOPLAM SKOR</div>
    <div class="score-number fs-6">
      <span id="liveTotalScore">0</span> / <span id="liveMaxScore">0</span> Puan
    </div>
  </div>

  <div class="score-progress-bar mx-2">
    <div id="scoreProgressFill" class="score-progress-fill"></div>
  </div>

  <div class="score-badge-box">
    <div class="fs-8 text-light opacity-75">UYGUNLUK</div>
    <div id="livePercentage" class="fw-extrabold fs-6 text-warning">%0</div>
  </div>
</div>

<form method="POST" action="audit_fill.php?template_id=<?php echo $template_id; ?>&unit_id=<?php echo $unit_id; ?>" id="auditFillForm">
  
  <div class="questions-wrapper">
    <?php $qNo = 1; foreach ($questions as $q): ?>
      <div class="question-card">
        <div class="question-title">
          <div class="question-number"><?php echo $qNo; ?></div>
          <div class="fw-bold"><?php echo htmlspecialchars($q['question_text']); ?></div>
        </div>

        <div class="option-checkbox-group">
          <?php foreach ($q['options'] as $opt): ?>
            <?php
            $pts = (int)$opt['points'];
            $pointClass = $pts > 0 ? 'positive' : ($pts < 0 ? 'negative' : 'neutral');
            $pointSign = $pts > 0 ? '+' : '';
            ?>
            <!-- div olarak güncellendi: Çift tıklama / çakışma önlendi -->
            <div class="option-item-card" data-points="<?php echo $pts; ?>">
              <div class="option-left">
                <div class="custom-checkbox">
                  <i class="bi bi-check-lg check-icon d-none"></i>
                </div>
                <input type="checkbox" name="answers[<?php echo $q['id']; ?>][]" value="<?php echo $opt['id']; ?>" class="option-checkbox d-none">
                <span class="option-text"><?php echo htmlspecialchars($opt['option_text']); ?></span>
              </div>
              <div class="point-badge <?php echo $pointClass; ?>">
                <?php echo $pointSign . $pts; ?> Puan
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php $qNo++; endforeach; ?>
  </div>

  <!-- Ek Saha Notları -->
  <div class="custom-card mb-4">
    <label class="form-label fw-bold text-dark fs-7 mb-2">
      <i class="bi bi-journal-text text-primary"></i> Saha Notları ve Uyarılar (Opsiyonel)
    </label>
    <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Saha denetiminde tespit edilen ek aksaklıklar, fotoğraf notları veya düzeltici faaliyet önerileri..."></textarea>
  </div>

  <!-- Kaydet Butonu -->
  <div class="mb-5">
    <button type="submit" class="btn btn-primary-custom w-100 py-3 font-weight-bold shadow-lg">
      <i class="bi bi-check-circle-fill"></i> Denetimi Tamamla ve Kaydet
    </button>
  </div>
</form>

<?php include __DIR__ . '/includes/footer.php'; ?>
