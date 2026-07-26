<?php
/**
 * Tubİsg - Saha Risk Denetimi & Adım Adım Matris Doldurma Ekranı (Step-by-Step Wizard & Global Seçenekli)
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

// Genel Tanımlı Cevap Seçeneklerini Çek
$globalOptions = $db->query("SELECT * FROM global_options WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll();

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
    $qOptions = $optStmt->fetchAll();
    
    // Eğer soruya özel seçenek tanımlanmamışsa genel seçenekleri atayalım
    if (!empty($qOptions)) {
        $q['options'] = $qOptions;
    } else {
        $q['options'] = $globalOptions;
    }
}
unset($q);

// Soruları Risk Gruplarına Göre Grupla
$groupedQuestions = [];
foreach ($questions as $q) {
    $groupName = !empty($q['group_name']) ? $q['group_name'] : 'Genel İSG Riskleri';
    $groupedQuestions[$groupName][] = $q;
}

// Autocomplete Verilerini Çek
$libRecommendations = $db->query("
    SELECT item_text FROM risk_libraries WHERE category = 'action_recommendation'
    UNION
    SELECT DISTINCT action_plan AS item_text FROM audit_answers WHERE action_plan IS NOT NULL AND action_plan != ''
    ORDER BY item_text ASC
")->fetchAll(PDO::FETCH_COLUMN);

$libResponsibles = $db->query("
    SELECT item_text FROM risk_libraries WHERE category = 'responsible_person'
    UNION
    SELECT DISTINCT responsible_person AS item_text FROM audit_answers WHERE responsible_person IS NOT NULL AND responsible_person != ''
    ORDER BY item_text ASC
")->fetchAll(PDO::FETCH_COLUMN);

$libStatuses = $db->query("
    SELECT DISTINCT current_status AS item_text FROM audit_answers WHERE current_status IS NOT NULL AND current_status != ''
    ORDER BY item_text ASC
")->fetchAll(PDO::FETCH_COLUMN);

// Denetim Formu Kaydedildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answersInput = $_POST['answers'] ?? [];
    $notes = trim($_POST['notes'] ?? '');

    $answeredCount = 0;
    $maxRiskScoreRecorded = 0;

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
        if ($riskScore > $maxRiskScoreRecorded) {
            $maxRiskScoreRecorded = $riskScore;
        }

        $actionPlan = trim($qInput['action_plan'] ?? '');
        $responsible = trim($qInput['responsible_person'] ?? '');
        $deadline = trim($qInput['deadline'] ?? '');

        if (!empty($selectedOptText)) {
            $answeredCount++;
        }

        $answersToSave[] = [
            'question_id'        => $qId,
            'option_id'          => $selectedOptId > 0 ? $selectedOptId : null,
            'answer_option'      => $selectedOptText,
            'points_awarded'     => 0,
            'current_status'     => $currentStatus,
            'probability'        => $probability,
            'severity'           => $severity,
            'risk_score'         => $riskScore,
            'action_plan'        => $actionPlan,
            'responsible_person' => $responsible,
            'deadline'           => $deadline
        ];

        // Otomatik Kütüphane Öğrenme
        if (!empty($actionPlan) && !in_array($actionPlan, $libRecommendations)) {
            $db->prepare("INSERT INTO risk_libraries (category, item_text) VALUES ('action_recommendation', ?)")->execute([$actionPlan]);
            log_action('Kütüphaneye Otomatik Önlem Eklendi', "Sahada yazıldı: {$actionPlan}");
        }
        if (!empty($responsible) && !in_array($responsible, $libResponsibles)) {
            $db->prepare("INSERT INTO risk_libraries (category, item_text) VALUES ('responsible_person', ?)")->execute([$responsible]);
            log_action('Kütüphaneye Otomatik Sorumlu Eklendi', "Sahada yazıldı: {$responsible}");
        }
    }

    // Denetim Kaydını Oluştur
    $stmtAudit = $db->prepare("
        INSERT INTO audits (template_id, unit_id, auditor_id, total_score, max_possible_score, percentage_score, status, notes) 
        VALUES (?, ?, ?, ?, 25, ?, 'Tamamlandı', ?)
    ");
    $stmtAudit->execute([$template_id, $unit_id, $user['id'], $maxRiskScoreRecorded, (float)$maxRiskScoreRecorded, $notes]);
    $auditId = $db->lastInsertId();

    // Risk Cevaplarını Kaydet
    $stmtAns = $db->prepare("
        INSERT INTO audit_answers 
        (audit_id, question_id, option_id, answer_option, points_awarded, current_status, probability, severity, risk_score, action_plan, responsible_person, deadline) 
        VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($answersToSave as $ans) {
        $stmtAns->execute([
            $auditId, 
            $ans['question_id'], 
            $ans['option_id'], 
            $ans['answer_option'], 
            $ans['current_status'], 
            $ans['probability'], 
            $ans['severity'], 
            $ans['risk_score'], 
            $ans['action_plan'], 
            $ans['responsible_person'], 
            $ans['deadline']
        ]);
    }

    log_action('Saha İSG Risk Denetimi Tamamlandı', "Birim: {$unit['unit_name']}, Anket: {$template['title']}, Max Risk: {$maxRiskScoreRecorded} (#DEN-" . sprintf('%04d', $auditId) . ")");

    set_flash('success', 'Adım adım İSG risk denetimi ve analizi kaydedildi.');
    header("Location: audit_detail.php?id=" . $auditId);
    exit;
}

$pageTitle = 'Saha Risk Denetimi Sihirbazı: ' . $unit['unit_name'];
include __DIR__ . '/includes/header.php';
?>

<!-- Autocomplete Datalists -->
<datalist id="recommendations_list">
  <?php foreach ($libRecommendations as $rec): ?>
    <option value="<?php echo htmlspecialchars($rec); ?>"></option>
  <?php endforeach; ?>
</datalist>

<datalist id="responsibles_list">
  <?php foreach ($libResponsibles as $resp): ?>
    <option value="<?php echo htmlspecialchars($resp); ?>"></option>
  <?php endforeach; ?>
</datalist>

<datalist id="statuses_list">
  <?php foreach ($libStatuses as $st): ?>
    <option value="<?php echo htmlspecialchars($st); ?>"></option>
  <?php endforeach; ?>
</datalist>

<!-- Üst Başlık & Birim Bilgisi -->
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
  <div>
    <span class="badge bg-primary-light text-primary font-weight-bold mb-1">
      <i class="bi bi-building"></i> BİRİM / SAHA: <?php echo htmlspecialchars($unit['unit_name']); ?>
    </span>
    <h3 class="fw-extrabold m-0"><?php echo htmlspecialchars($template['title']); ?></h3>
  </div>
  <div class="text-muted fs-8">
    <i class="bi bi-person-fill"></i> İSG Uzmanı: <strong><?php echo htmlspecialchars($user['name_surname']); ?></strong>
  </div>
</div>

<!-- ADIM ADIM DENETİM SİHİRBAZI STEPPER BAR (STEP WIZARD) -->
<div class="custom-card p-3 mb-4 shadow-sm">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="fw-bold text-dark m-0"><i class="bi bi-magic text-success"></i> Adım Adım Risk Grubu Sihirbazı</h6>
    <span class="badge bg-dark" id="stepCounterBadge">Adım 1 / <?php echo count($groupedQuestions); ?></span>
  </div>

  <div class="nav nav-pills custom-card-tabs flex-nowrap overflow-auto py-1" id="wizardTabs" role="tablist">
    <?php $stepIdx = 1; foreach ($groupedQuestions as $groupName => $gQuestions): ?>
      <button class="nav-link text-nowrap fw-bold px-3 py-2 me-2 <?php echo $stepIdx === 1 ? 'active bg-success text-white' : 'bg-light text-dark border'; ?>" 
              id="wizard-tab-<?php echo $stepIdx; ?>" 
              data-bs-toggle="pill" 
              data-bs-target="#wizard-step-<?php echo $stepIdx; ?>" 
              type="button" 
              role="tab"
              data-step="<?php echo $stepIdx; ?>">
        Adım <?php echo $stepIdx; ?>: <?php echo htmlspecialchars($groupName); ?> (<?php echo count($gQuestions); ?>)
      </button>
    <?php $stepIdx++; endforeach; ?>
  </div>
</div>

<form method="POST" action="audit_fill.php?template_id=<?php echo $template_id; ?>&unit_id=<?php echo $unit_id; ?>" id="auditFillForm">

  <div class="tab-content" id="wizardTabContent">
    <?php if (empty($groupedQuestions)): ?>
      <div class="alert alert-warning">Bu anket profilinde henüz tanımlanmış soru veya risk maddesi bulunmuyor.</div>
    <?php else: ?>
      <?php $stepIdx = 1; $qGlobalIndex = 1; foreach ($groupedQuestions as $groupName => $gQuestions): ?>
        
        <div class="tab-pane fade <?php echo $stepIdx === 1 ? 'show active' : ''; ?>" id="wizard-step-<?php echo $stepIdx; ?>" role="tabpanel">
          
          <!-- Risk Grubu Başlık Kartı -->
          <div class="card border-0 bg-dark text-white rounded-3 p-3 mb-3 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
              <h5 class="fw-bold m-0 text-white"><i class="bi bi-shield-exclamation text-warning me-2"></i> <?php echo htmlspecialchars($groupName); ?></h5>
              <span class="badge bg-secondary rounded-pill">Adım <?php echo $stepIdx; ?> / <?php echo count($groupedQuestions); ?></span>
            </div>
          </div>

          <?php foreach ($gQuestions as $q): ?>
            <div class="custom-card question-card mb-4 border-2" id="q_card_<?php echo $q['id']; ?>">
              
              <!-- Soru / Tehlike Header -->
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

              <!-- Cevap Seçenek Butonları (Genel Tanımlı Şıklardan Dinamik) -->
              <div class="row g-2 mb-3">
                <?php foreach ($q['options'] as $opt): ?>
                  <?php
                  $optText = $opt['option_text'];
                  $optId = $opt['id'] ?? 0;
                  $isDanger = (strpos($optText, 'Hayır') !== false || $opt['trigger_action'] == 1);
                  $isWarning = strpos($optText, 'Kısmen') !== false;
                  $btnColorClass = $isDanger ? 'btn-outline-danger' : ($isWarning ? 'btn-outline-warning text-dark' : 'btn-outline-success');
                  ?>
                  <div class="col-6 col-md-3">
                    <label class="btn <?php echo $btnColorClass; ?> w-100 font-weight-bold py-2 answer-btn-label">
                      <input type="radio" name="answers[<?php echo $q['id']; ?>][answer_option]" value="<?php echo htmlspecialchars($optText); ?>" data-optid="<?php echo $optId; ?>" data-trigger="<?php echo $opt['trigger_action']; ?>" class="d-none answer-radio" data-qid="<?php echo $q['id']; ?>">
                      <i class="bi <?php echo $isDanger ? 'bi-x-circle-fill' : ($isWarning ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill'); ?> me-1"></i>
                      <?php echo htmlspecialchars($optText); ?>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Riskli / Kısmen Veya Tetikleyici Aktif Olduğunda Açılan 5x5 Risk Matrisi & Önlem Kartı -->
              <div class="risk-matrix-panel d-none p-3 rounded-3 bg-light border border-warning" id="risk_panel_<?php echo $q['id']; ?>">
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                  <h6 class="fw-bold text-danger m-0 fs-7">
                    <i class="bi bi-clipboard2-pulse-fill"></i> İSG Uzmanı Risk Değerlendirme & Önlem Kartı
                  </h6>
                  <span class="badge bg-danger" id="risk_badge_<?php echo $q['id']; ?>">RİSK SKORU: 1 (Kabul Edilebilir)</span>
                </div>

                <!-- Mevcut Durum Açıklaması -->
                <div class="mb-3">
                  <label class="form-label fw-bold fs-8 text-dark">Mevcut Durum / Tespit Edilen Eksiklik</label>
                  <input type="text" name="answers[<?php echo $q['id']; ?>][current_status]" list="statuses_list" class="form-control form-control-sm" placeholder="Örn: Lavabolar tavanda su akıntısı mevcut...">
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

                <!-- Alınacak Önlemler -->
                <div class="mb-3">
                  <label class="form-label fw-bold fs-8 text-dark"><i class="bi bi-lightbulb-fill text-warning"></i> Alınacak Önlemler / İyileştirmeler</label>
                  <input type="text" name="answers[<?php echo $q['id']; ?>][action_plan]" list="recommendations_list" class="form-control form-control-sm" placeholder="Örn: Lavabo (WC) tavanlarında gerekli yalıtımın sağlanması...">
                </div>

                <div class="row g-2">
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold fs-8 text-muted">Sorumlu Birim / Kişi</label>
                    <input type="text" name="answers[<?php echo $q['id']; ?>][responsible_person]" list="responsibles_list" class="form-control form-control-sm" placeholder="Örn: Tekn. Hiz. Yön.">
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold fs-8 text-muted">Termin / Süre</label>
                    <input type="text" name="answers[<?php echo $q['id']; ?>][deadline]" class="form-control form-control-sm" placeholder="Örn: 6 Ay, Sürekli">
                  </div>
                </div>
              </div>

            </div>
          <?php $qGlobalIndex++; endforeach; ?>

          <!-- Adım İlerleme Butonları Barı -->
          <div class="d-flex align-items-center justify-content-between mt-4 pt-3 border-top">
            <?php if ($stepIdx > 1): ?>
              <button type="button" class="btn btn-outline-secondary font-weight-bold prev-step-btn" data-prev="<?php echo $stepIdx - 1; ?>">
                <i class="bi bi-arrow-left"></i> Önceki Adım
              </button>
            <?php else: ?>
              <div></div>
            <?php endif; ?>

            <?php if ($stepIdx < count($groupedQuestions)): ?>
              <button type="button" class="btn btn-success font-weight-bold px-4 next-step-btn" data-next="<?php echo $stepIdx + 1; ?>">
                Sonraki Adım <i class="bi bi-arrow-right"></i>
              </button>
            <?php else: ?>
              <button type="submit" class="btn btn-primary-custom px-4 py-2 font-weight-bold shadow">
                <i class="bi bi-check-circle-fill fs-5 me-1"></i> Tüm Adımları Tamamla ve Kaydet
              </button>
            <?php endif; ?>
          </div>

        </div>

      <?php $stepIdx++; endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Saha Notları (Son Adımda Veya Sabit) -->
  <div class="custom-card my-4">
    <div class="custom-card-header">
      <h6 class="custom-card-title m-0">
        <i class="bi bi-pencil-square text-warning"></i> Genel Saha Denetim Notları (Opsiyonel)
      </h6>
    </div>
    <textarea name="notes" class="form-control" rows="3" placeholder="Saha denetimi esnasında tespit edilen özel hususlar veya genel görüşler..."></textarea>
  </div>

  <!-- Sabit Alt Kaydet Barı -->
  <div class="custom-card p-3 d-flex align-items-center justify-content-between sticky-bottom bg-white shadow-lg border-top border-2 border-primary mb-5" style="z-index:90;">
    <span class="text-muted fs-8"><i class="bi bi-info-circle me-1"></i> Tüm soruları yanıtladıktan sonra kaydet butonuna basabilirsiniz.</span>
    <button type="submit" class="btn btn-primary-custom px-4 py-2 font-weight-bold shadow-lg">
      <i class="bi bi-check-circle-fill"></i> Saha Denetimini Tamamla ve Kaydet
    </button>
  </div>

</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
  
  // 1. Adım İlerleme (Step Wizard Navigation) Mantığı
  const totalSteps = <?php echo count($groupedQuestions); ?>;

  document.querySelectorAll('.next-step-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const nextStep = this.dataset.next;
      const nextTab = document.getElementById('wizard-tab-' + nextStep);
      if (nextTab) {
        new bootstrap.Tab(nextTab).show();
        updateStepBadge(nextStep);
        window.scrollTo({ top: 120, behavior: 'smooth' });
      }
    });
  });

  document.querySelectorAll('.prev-step-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      const prevStep = this.dataset.prev;
      const prevTab = document.getElementById('wizard-tab-' + prevStep);
      if (prevTab) {
        new bootstrap.Tab(prevTab).show();
        updateStepBadge(prevStep);
        window.scrollTo({ top: 120, behavior: 'smooth' });
      }
    });
  });

  document.querySelectorAll('#wizardTabs button').forEach(tabBtn => {
    tabBtn.addEventListener('shown.bs.tab', function(e) {
      const step = this.dataset.step;
      updateStepBadge(step);
    });
  });

  function updateStepBadge(step) {
    document.getElementById('stepCounterBadge').textContent = `Adım ${step} / ${totalSteps}`;
  }

  // 2. Cevap Butonlarına Tıklama ve Tetikleyici Mantığı
  document.querySelectorAll('.answer-radio').forEach(radio => {
    radio.addEventListener('change', function() {
      const qId = this.dataset.qid;
      const val = this.value;
      const trigger = parseInt(this.dataset.trigger || '0');
      const label = this.closest('.answer-btn-label');
      const qCard = document.getElementById('q_card_' + qId);
      const riskPanel = document.getElementById('risk_panel_' + qId);

      qCard.querySelectorAll('.answer-btn-label').forEach(lbl => {
        lbl.classList.remove('active', 'btn-success', 'btn-danger', 'btn-warning', 'btn-secondary');
      });

      if (trigger === 1 || val.includes('Hayır') || val.includes('Kısmen')) {
        label.classList.add('active', val.includes('Kısmen') ? 'btn-warning' : 'btn-danger');
        if (riskPanel) riskPanel.classList.remove('d-none');
      } else {
        label.classList.add('active', 'btn-success');
        if (riskPanel) riskPanel.classList.add('d-none');
      }

      calculateRiskScore(qId);
    });
  });

  // 3. Risk Skoru Hesaplama ($O \times Ş$)
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

});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
