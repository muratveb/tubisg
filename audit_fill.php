<?php
/**
 * Tubİsg - Saha Risk Denetimi & Matris Doldurma Ekranı
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

// Soruları ve Risk Gruplarını Çek
$questionsStmt = $db->prepare("
    SELECT sq.*, rg.group_name 
    FROM survey_questions sq 
    LEFT JOIN risk_groups rg ON sq.risk_group_id = rg.id 
    WHERE sq.template_id = ? 
    ORDER BY COALESCE(rg.sort_order, 99) ASC, sq.sort_order ASC, sq.id ASC
");
$questionsStmt->execute([$template_id]);
$questions = $questionsStmt->fetchAll();

foreach ($questions as &$q) {
    $optStmt = $db->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY sort_order ASC, id ASC");
    $optStmt->execute([$q['id']]);
    $q['options'] = $optStmt->fetchAll();
}
unset($q);

// Soruları Risk Gruplarına Göre Grupla
$groupedQuestions = [];
foreach ($questions as $q) {
    $groupName = !empty($q['group_name']) ? $q['group_name'] : 'Genel İSG Riskleri';
    $groupedQuestions[$groupName][] = $q;
}

// Denetim Formu Kaydedildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answersInput = $_POST['answers'] ?? []; // format: [question_id => [option_text, option_id, current_status, probability, severity, action_plan, responsible_person, deadline]]
    $notes = trim($_POST['notes'] ?? '');

    $totalScore = 0;
    $maxPossibleScore = count($questions) * 10;
    $answeredCount = 0;

    $answersToSave = [];

    foreach ($questions as $q) {
        $qId = $q['id'];
        $qInput = $answersInput[$qId] ?? [];
        
        $selectedOptText = trim($qInput['answer_option'] ?? '');
        $selectedOptId = (int)($qInput['option_id'] ?? 0);
        
        $currentStatus = trim($qInput['current_status'] ?? '');
        $probability = (int)($qInput['probability'] ?? 1);
        if ($probability < 1) $probability = 1;
        if ($probability > 5) $probability = 5;

        $severity = (int)($qInput['severity'] ?? 1);
        if ($severity < 1) $severity = 1;
        if ($severity > 5) $severity = 5;

        $riskScore = $probability * $severity;

        $actionPlan = trim($qInput['action_plan'] ?? '');
        $responsible = trim($qInput['responsible_person'] ?? '');
        $deadline = trim($qInput['deadline'] ?? '');

        // Puanlama Mantığı
        $ptsAwarded = 0;
        if ($selectedOptText === 'Evet') {
            $ptsAwarded = 10;
        } elseif ($selectedOptText === 'Kısmen') {
            $ptsAwarded = 5;
        } elseif ($selectedOptText === 'Hayır') {
            $ptsAwarded = 0;
        } elseif ($selectedOptText === 'Muaf') {
            $ptsAwarded = 10; // Muaf sorular puandan düşülür veya tam kabul edilir
        } else {
            // Standart seçeneklerden eşleşen varsa puanını al
            foreach ($q['options'] as $opt) {
                if ((int)$opt['id'] === $selectedOptId) {
                    $ptsAwarded = (int)$opt['points'];
                    break;
                }
            }
        }

        if (!empty($selectedOptText)) {
            $answeredCount++;
            $totalScore += $ptsAwarded;
        }

        $answersToSave[] = [
            'question_id'        => $qId,
            'option_id'          => $selectedOptId > 0 ? $selectedOptId : null,
            'answer_option'      => $selectedOptText,
            'points_awarded'     => $ptsAwarded,
            'current_status'     => $currentStatus,
            'probability'        => $probability,
            'severity'           => $severity,
            'risk_score'         => $riskScore,
            'action_plan'        => $actionPlan,
            'responsible_person' => $responsible,
            'deadline'           => $deadline
        ];
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

    // Risk Matrisi Cevaplarını Kaydet
    $stmtAns = $db->prepare("
        INSERT INTO audit_answers 
        (audit_id, question_id, option_id, answer_option, points_awarded, current_status, probability, severity, risk_score, action_plan, responsible_person, deadline) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($answersToSave as $ans) {
        $stmtAns->execute([
            $auditId, 
            $ans['question_id'], 
            $ans['option_id'], 
            $ans['answer_option'], 
            $ans['points_awarded'], 
            $ans['current_status'], 
            $ans['probability'], 
            $ans['severity'], 
            $ans['risk_score'], 
            $ans['action_plan'], 
            $ans['responsible_person'], 
            $ans['deadline']
        ]);
    }

    log_action('Saha Denetimi Tamamlandı', "Birim: {$unit['unit_name']}, Anket: {$template['title']}, Skor: %{$percentageScore} (#DEN-" . sprintf('%04d', $auditId) . ")");

    set_flash('success', 'Birim bazlı İSG risk denetimi başarıyla kaydedildi ve risk analizi raporu oluşturuldu.');
    header("Location: audit_detail.php?id=" . $auditId);
    exit;
}

$pageTitle = 'Saha Risk Denetimi: ' . $unit['unit_name'];
include __DIR__ . '/includes/header.php';
?>

<!-- Üst Başlık ve Sabit Skor Rozeti Barı -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
  <div>
    <span class="badge bg-primary-light text-primary font-weight-bold mb-1">
      <i class="bi bi-building"></i> BİRİM / SAHA: <?php echo htmlspecialchars($unit['unit_name']); ?>
    </span>
    <h3 class="fw-extrabold m-0"><?php echo htmlspecialchars($template['title']); ?></h3>
  </div>
  <div class="text-muted fs-8">
    <i class="bi bi-person-fill"></i> Denetçi: <strong><?php echo htmlspecialchars($user['name_surname']); ?></strong>
  </div>
</div>

<!-- Canlı Risk Skor Barı (Sticky) -->
<div class="sticky-audit-scorebar">
  <div class="d-flex align-items-center gap-3 flex-grow-1">
    <div class="text-center">
      <div class="score-number" id="liveTotalScore">0</div>
      <div class="fs-8 opacity-75" style="font-size: 0.65rem;">Toplam Puan</div>
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

  <?php if (empty($groupedQuestions)): ?>
    <div class="alert alert-warning">Bu anket profilinde henüz tanımlanmış soru veya risk maddesi bulunmuyor.</div>
  <?php else: ?>
    <?php $qGlobalIndex = 1; foreach ($groupedQuestions as $groupName => $gQuestions): ?>
      
      <!-- Risk Grubu Başlık Kartı -->
      <div class="card border-0 bg-dark text-white rounded-3 p-3 mb-3 shadow-sm">
        <div class="d-flex align-items-center justify-content-between">
          <h5 class="fw-bold m-0 text-white"><i class="bi bi-shield-exclamation text-warning me-2"></i> <?php echo htmlspecialchars($groupName); ?></h5>
          <span class="badge bg-secondary rounded-pill"><?php echo count($gQuestions); ?> Risk Maddesi</span>
        </div>
      </div>

      <?php foreach ($gQuestions as $q): ?>
        <div class="custom-card question-card mb-4 border-2" id="q_card_<?php echo $q['id']; ?>">
          
          <!-- Soru / Tehlike Detay Header -->
          <div class="question-title d-flex align-items-start justify-content-between gap-2 border-bottom pb-2 mb-3">
            <div class="d-flex align-items-start gap-2">
              <span class="question-number"><?php echo $qGlobalIndex; ?></span>
              <div>
                <h6 class="fw-bold text-dark m-0 fs-6"><?php echo htmlspecialchars($q['question_text']); ?></h6>
                <?php if ($q['hazard_source'] || $q['hazard_name']): ?>
                  <div class="text-muted fs-8 mt-1">
                    <?php if ($q['hazard_source']): ?><strong>Tehlike Kaynağı:</strong> <?php echo htmlspecialchars($q['hazard_source']); ?> &nbsp;|&nbsp; <?php endif; ?>
                    <?php if ($q['hazard_name']): ?><strong>Tehlike:</strong> <?php echo htmlspecialchars($q['hazard_name']); ?><?php endif; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($q['affected_people']): ?>
              <span class="badge bg-light text-secondary border fs-8 text-nowrap"><i class="bi bi-people"></i> <?php echo htmlspecialchars($q['affected_people']); ?></span>
            <?php endif; ?>
          </div>

          <!-- Cevap Seçenek Butonları (Evet, Hayır, Kısmen, Muaf) -->
          <div class="row g-2 mb-3">
            <div class="col-6 col-md-3">
              <label class="btn btn-outline-success w-100 font-weight-bold py-2 answer-btn-label">
                <input type="radio" name="answers[<?php echo $q['id']; ?>][answer_option]" value="Evet" class="d-none answer-radio" data-qid="<?php echo $q['id']; ?>">
                <i class="bi bi-check-circle-fill me-1"></i> Evet (Uygun)
              </label>
            </div>
            <div class="col-6 col-md-3">
              <label class="btn btn-outline-danger w-100 font-weight-bold py-2 answer-btn-label">
                <input type="radio" name="answers[<?php echo $q['id']; ?>][answer_option]" value="Hayır" class="d-none answer-radio" data-qid="<?php echo $q['id']; ?>">
                <i class="bi bi-x-circle-fill me-1"></i> Hayır (Riskli)
              </label>
            </div>
            <div class="col-6 col-md-3">
              <label class="btn btn-outline-warning text-dark w-100 font-weight-bold py-2 answer-btn-label">
                <input type="radio" name="answers[<?php echo $q['id']; ?>][answer_option]" value="Kısmen" class="d-none answer-radio" data-qid="<?php echo $q['id']; ?>">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> Kısmen
              </label>
            </div>
            <div class="col-6 col-md-3">
              <label class="btn btn-outline-secondary w-100 font-weight-bold py-2 answer-btn-label">
                <input type="radio" name="answers[<?php echo $q['id']; ?>][answer_option]" value="Muaf" class="d-none answer-radio" data-qid="<?php echo $q['id']; ?>">
                <i class="bi bi-dash-circle-fill me-1"></i> Muaf / Uyg.
              </label>
            </div>
          </div>

          <!-- Riskli / Kısmen Seçildiğinde Açılan 5x5 Risk Matrisi & Önlem Alanları -->
          <div class="risk-matrix-panel d-none p-3 rounded-3 bg-light border border-warning" id="risk_panel_<?php echo $q['id']; ?>">
            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
              <h6 class="fw-bold text-danger m-0 fs-7">
                <i class="bi bi-clipboard2-pulse-fill"></i> Risk Değerlendirme & Alınacak Önlem Kartı
              </h6>
              <span class="badge bg-danger" id="risk_badge_<?php echo $q['id']; ?>">RİSK SKORU: 1 (Kabul Edilebilir)</span>
            </div>

            <!-- Mevcut Durum Açıklaması -->
            <div class="mb-3">
              <label class="form-label fw-bold fs-8 text-dark">Mevcut Durum / Tespit Edilen Eksiklik</label>
              <textarea name="answers[<?php echo $q['id']; ?>][current_status]" class="form-control form-control-sm" rows="2" placeholder="Örn: Lavabolar tavanda su akıntısı mevcut veya baret takılmıyor..."></textarea>
            </div>

            <!-- Olasılık ($O$) ve Şiddet ($Ş$) Seçimi -->
            <div class="row g-3 mb-3">
              <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-8 text-muted">Olasılık ($O$)</label>
                <select name="answers[<?php echo $q['id']; ?>][probability]" class="form-select form-select-sm risk-calc-select" data-qid="<?php echo $q['id']; ?>" id="prob_<?php echo $q['id']; ?>">
                  <option value="1">1 - Çok Küçük (Çok nadir)</option>
                  <option value="2" selected>2 - Küçük (Nadir)</option>
                  <option value="3">3 - Orta (Olabilir)</option>
                  <option value="4">4 - Yüksek (Sık sık)</option>
                  <option value="5">5 - Çok Yüksek (Her an)</option>
                </select>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-8 text-muted">Şiddet ($Ş$)</label>
                <select name="answers[<?php echo $q['id']; ?>][severity]" class="form-select form-select-sm risk-calc-select" data-qid="<?php echo $q['id']; ?>" id="sev_<?php echo $q['id']; ?>">
                  <option value="1">1 - Çok Hafif (İlk yardım gerektirmez)</option>
                  <option value="2">2 - Hafif (İlk yardım gerekir)</option>
                  <option value="3" selected>3 - Ciddi (Hastane tedavisi gerekir)</option>
                  <option value="4">4 - Çok Ciddi (Ağır yaralanma / kalıcı hasar)</option>
                  <option value="5">5 - Felaket (Ölümcül / Çoklu kayıp)</option>
                </select>
              </div>
            </div>

            <!-- Alınacak Önlemler, Sorumlu ve Termin -->
            <div class="mb-3">
              <label class="form-label fw-bold fs-8 text-dark">Alınacak Önlemler / İyileştirmeler</label>
              <textarea name="answers[<?php echo $q['id']; ?>][action_plan]" class="form-control form-control-sm" rows="2" placeholder="Örn: Lavabo (WC) tavanlarında gerekli yalıtımın sağlanması..."></textarea>
            </div>

            <div class="row g-2">
              <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-8 text-muted">Sorumlu Birim / Kişi</label>
                <input type="text" name="answers[<?php echo $q['id']; ?>][responsible_person]" class="form-control form-control-sm" placeholder="Örn: Tekn. Hiz. Yön.">
              </div>
              <div class="col-12 col-md-6">
                <label class="form-label fw-bold fs-8 text-muted">Termin / Süre</label>
                <input type="text" name="answers[<?php echo $q['id']; ?>][deadline]" class="form-control form-control-sm" placeholder="Örn: 6 Ay, Sürekli">
              </div>
            </div>
          </div>

        </div>
      <?php $qGlobalIndex++; endforeach; ?>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Saha Notları -->
  <div class="custom-card mb-4">
    <div class="custom-card-header">
      <h6 class="custom-card-title m-0">
        <i class="bi bi-pencil-square text-warning"></i> Genel Saha Denetim Notları (Opsiyonel)
      </h6>
    </div>
    <textarea name="notes" class="form-control" rows="3" placeholder="Saha denetimi esnasında tespit edilen özel hususlar veya genel görüşler..."></textarea>
  </div>

  <!-- Kaydet Butonu -->
  <div class="d-grid gap-2 mb-5">
    <button type="submit" class="btn btn-primary-custom py-3 fs-6 font-weight-bold shadow-lg">
      <i class="bi bi-check-circle-fill fs-5"></i> Saha Risk Denetimini Tamamla ve Kaydet
    </button>
  </div>

</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
  
  // 1. Cevap Butonlarına Tıklama Mantığı
  document.querySelectorAll('.answer-radio').forEach(radio => {
    radio.addEventListener('change', function() {
      const qId = this.dataset.qid;
      const val = this.value;
      const label = this.closest('.answer-btn-label');
      const qCard = document.getElementById('q_card_' + qId);
      const riskPanel = document.getElementById('risk_panel_' + qId);

      // Gruptaki tüm butonların active sınıfını kaldır
      qCard.querySelectorAll('.answer-btn-label').forEach(lbl => {
        lbl.classList.remove('active', 'btn-success', 'btn-danger', 'btn-warning', 'btn-secondary');
        lbl.classList.add(lbl.dataset.defaultClass || 'btn-outline-secondary');
      });

      if (val === 'Evet') {
        label.classList.add('active', 'btn-success');
        if (riskPanel) riskPanel.classList.add('d-none');
      } else if (val === 'Hayır') {
        label.classList.add('active', 'btn-danger');
        if (riskPanel) riskPanel.classList.remove('d-none');
      } else if (val === 'Kısmen') {
        label.classList.add('active', 'btn-warning');
        if (riskPanel) riskPanel.classList.remove('d-none');
      } else if (val === 'Muaf') {
        label.classList.add('active', 'btn-secondary');
        if (riskPanel) riskPanel.classList.add('d-none');
      }

      calculateRiskScore(qId);
      updateTotalScore();
    });
  });

  // 2. Risk Skoru Hesaplama (Olasılık x Şiddet = Risk Derecesi)
  document.querySelectorAll('.risk-calc-select').forEach(select => {
    select.addEventListener('change', function() {
      const qId = this.dataset.qid;
      calculateRiskScore(qId);
    });
  });

  function calculateRiskScore(qId) {
    const probSelect = document.getElementById('prob_' + qId);
    const sevSelect = document.getElementById('sev_' + qId);
    const badge = document.getElementById('risk_badge_' + qId);
    if (!probSelect || !sevSelect || !badge) return;

    const prob = parseInt(probSelect.value) || 1;
    const sev = parseInt(sevSelect.value) || 1;
    const score = prob * sev;

    let category = 'Kabul Edilebilir Risk';
    let badgeBg = 'bg-success';

    if (score >= 16) {
      category = 'Kabul Edilemez Risk';
      badgeBg = 'bg-danger';
    } else if (score >= 10) {
      category = 'Dikkate Değer Risk';
      badgeBg = 'bg-warning text-dark';
    } else if (score >= 6) {
      category = 'Önemli Risk';
      badgeBg = 'bg-info text-dark';
    }

    badge.className = 'badge ' + badgeBg;
    badge.textContent = `RİSK DERECE SKORU: ${score} (${category})`;
  }

  function updateTotalScore() {
    let total = 0;
    let answered = 0;
    const radios = document.querySelectorAll('.answer-radio:checked');
    radios.forEach(r => {
      answered++;
      if (r.value === 'Evet' || r.value === 'Muaf') total += 10;
      else if (r.value === 'Kısmen') total += 5;
    });

    const totalQuestions = document.querySelectorAll('.question-card').length;
    const maxScore = totalQuestions * 10;
    const pct = maxScore > 0 ? Math.round((total / maxScore) * 100) : 0;

    document.getElementById('liveTotalScore').textContent = total;
    document.getElementById('livePercentage').textContent = '%' + pct;
    document.getElementById('scoreProgressFill').style.width = pct + '%';
  }

});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
