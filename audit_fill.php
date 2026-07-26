<?php
/**
 * Tubİsg - Saha Risk Denetimi Doldurma Ekranı (audit_fill.php)
 * 12 Sütunlu İSG Risk Analiz Belgenize Göre Risk Skoru ($R = O \times Ş$) & İyileştirme Kartlı Yapı
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('audit_conduct');

$db = getDB();
$user = get_current_user_data();

$template_id = (int)($_GET['template_id'] ?? 0);
$unit_id = (int)($_GET['unit_id'] ?? 0);

if ($template_id <= 0 || $unit_id <= 0) {
    set_flash('danger', 'Geçersiz denetim parametreleri.');
    header("Location: audit_new.php");
    exit;
}

// Şablon ve Birim Bilgilerini Çek
$stmtTpl = $db->prepare("SELECT * FROM survey_templates WHERE id = ? AND is_active = 1");
$stmtTpl->execute([$template_id]);
$template = $stmtTpl->fetch();

$stmtUnit = $db->prepare("SELECT * FROM units WHERE id = ?");
$stmtUnit->execute([$unit_id]);
$unit = $stmtUnit->fetch();

if (!$template || !$unit) {
    set_flash('danger', 'Seçilen anket profili veya birim aktif değil.');
    header("Location: audit_new.php");
    exit;
}

// Risk Grupları Sırasıyla Soruları Çek
$questionsStmt = $db->prepare("
    SELECT sq.*, rg.group_name, COALESCE(rg.sort_order, 99) as group_sort
    FROM survey_questions sq
    LEFT JOIN risk_groups rg ON sq.risk_group_id = rg.id
    WHERE sq.template_id = ? 
    ORDER BY group_sort ASC, sq.sort_order ASC, sq.id ASC
");
$questionsStmt->execute([$template_id]);
$questions = $questionsStmt->fetchAll();

// Her Soru İçin Cevap Şıklarını Çek
$questionIds = array_column($questions, 'id');
$questionOptions = [];

if (!empty($questionIds)) {
    $inClause = implode(',', array_fill(0, count($questionIds), '?'));
    $optionsStmt = $db->prepare("SELECT * FROM question_options WHERE question_id IN ($inClause) ORDER BY id ASC");
    $optionsStmt->execute($questionIds);
    $allOptions = $optionsStmt->fetchAll();

    foreach ($allOptions as $opt) {
        $questionOptions[$opt['question_id']][] = $opt;
    }
}

// Varsayılan Standart Şıklar (Eğer özel şık tanımlı değilse)
$defaultStandardOptions = [
    ['id' => 0, 'option_text' => 'Evet (Uygun)', 'points' => 0, 'trigger_action' => 0],
    ['id' => 0, 'option_text' => 'Hayır (Uygun Değil)', 'points' => 0, 'trigger_action' => 1],
    ['id' => 0, 'option_text' => 'Kısmen (Kısmen Uygun)', 'points' => 0, 'trigger_action' => 1],
    ['id' => 0, 'option_text' => 'Denetim Dışı / Muaf', 'points' => 0, 'trigger_action' => 0]
];

// Soruları Risk Gruplarına Göre Grupla
$groupedQuestions = [];
foreach ($questions as $q) {
    $gName = $q['group_name'] ? $q['group_name'] : 'Genel Saha & Risk Tespiti';
    $q['options'] = !empty($questionOptions[$q['id']]) ? $questionOptions[$q['id']] : $defaultStandardOptions;
    $groupedQuestions[$gName][] = $q;
}

// Kütüphane Verileri (Otomatik Tamamlama İçin)
$libResponsibles = $db->query("SELECT item_text FROM risk_libraries WHERE category = 'responsible_person' ORDER BY item_text ASC")->fetchAll(PDO::FETCH_COLUMN);
$libRecommendations = $db->query("SELECT item_text FROM risk_libraries WHERE category = 'action_recommendation' ORDER BY item_text ASC")->fetchAll(PDO::FETCH_COLUMN);

// Form Post Edildiğinde (Denetim Tamamlama)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answersInput = $_POST['answers'] ?? [];
    $notes = trim($_POST['notes'] ?? '');
    
    $totalQuestions = count($questions);
    $answeredCount = 0;
    $maxRiskScoreRecorded = 0;

    $answersToSave = [];

    foreach ($questions as $q) {
        $qId = $q['id'];
        $qInput = $answersInput[$qId] ?? [];
        
        $selectedOptText = trim($qInput['answer_option'] ?? '');
        $selectedOptId = (int)($qInput['option_id'] ?? 0);

        // Eğer option_id gelmediyse şık metninden eşleştir
        if ($selectedOptId <= 0 && !empty($selectedOptText) && isset($questionOptions[$qId])) {
            foreach ($questionOptions[$qId] as $opt) {
                if ($opt['option_text'] === $selectedOptText) {
                    $selectedOptId = (int)$opt['id'];
                    break;
                }
            }
        }
        
        $currentStatus = trim($qInput['current_status'] ?? '');
        if (empty($currentStatus) && !empty($q['current_status'])) {
            $currentStatus = $q['current_status'];
        }

        $probability = (int)($qInput['probability'] ?? ($q['default_probability'] ?? 1));
        if ($probability < 1) $probability = 1;
        if ($probability > 5) $probability = 5;

        $severity = (int)($qInput['severity'] ?? ($q['default_severity'] ?? 1));
        if ($severity < 1) $severity = 1;
        if ($severity > 5) $severity = 5;

        $riskScore = $probability * $severity;
        if ($riskScore > $maxRiskScoreRecorded) {
            $maxRiskScoreRecorded = $riskScore;
        }

        $actionPlan = trim($qInput['action_plan'] ?? '');
        if (empty($actionPlan) && !empty($q['default_action_plan'])) {
            $actionPlan = $q['default_action_plan'];
        }

        $responsible = trim($qInput['responsible_person'] ?? '');
        if (empty($responsible) && !empty($q['default_responsible'])) {
            $responsible = $q['default_responsible'];
        }

        $deadline = trim($qInput['deadline'] ?? '');
        if (empty($deadline) && !empty($q['default_deadline'])) {
            $deadline = $q['default_deadline'];
        }

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

    // Risk Cevaplarını Kaydet (option_id Güvenli Kullanım)
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

    set_flash('success', 'Adım adım İSG risk denetimi ve analizi başarıyla kaydedildi.');
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

<!-- Sayfa Üst Barı -->
<div class="custom-card p-3 mb-4 bg-white border-0 shadow-sm rounded-4">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <span class="badge bg-primary-light text-primary font-weight-bold fs-8 mb-1">
        <i class="bi bi-building me-1"></i> BİRİM / SAHA: <?php echo htmlspecialchars($unit['unit_name']); ?>
      </span>
      <h3 class="fw-extrabold m-0 text-dark"><?php echo htmlspecialchars($unit['unit_name']); ?></h3>
      <span class="text-muted fs-8">Anket Profili: <strong><?php echo htmlspecialchars($template['title']); ?></strong></span>
    </div>
    <div class="text-md-end">
      <span class="badge bg-light text-dark border p-2 px-3 rounded-pill fs-8">
        <i class="bi bi-person-fill text-success"></i> İSG Uzmanı: <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
      </span>
    </div>
  </div>
</div>

<!-- Risk Grubu Adım Barı (Stepper Tabs) -->
<div class="custom-card p-3 mb-4 bg-white border-0 shadow-sm rounded-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="fw-bold text-dark m-0"><i class="bi bi-magic text-warning me-1"></i> Adım Adım Risk Grubu Sihirbazı</h6>
    <span class="badge bg-dark text-white fs-8" id="currentWizardStepBadge">Adım 1 / <?php echo count($groupedQuestions); ?></span>
  </div>
  
  <div class="nav nav-pills flex-nowrap overflow-auto pb-2" id="wizardPillsTab" role="tablist">
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
            <?php
            $defP = (int)($q['default_probability'] ?? 2);
            $defS = (int)($q['default_severity'] ?? 3);
            ?>
            <div class="custom-card question-card mb-4 border-2" id="q_card_<?php echo $q['id']; ?>">
              
              <input type="hidden" name="answers[<?php echo $q['id']; ?>][option_id]" id="opt_id_input_<?php echo $q['id']; ?>" value="0">

              <!-- Soru / Tehlike Header -->
              <div class="question-title d-flex align-items-start justify-content-between gap-2 border-bottom pb-2 mb-3">
                <div class="d-flex align-items-start gap-2">
                  <span class="question-number"><?php echo $qGlobalIndex; ?></span>
                  <div>
                    <h6 class="fw-bold text-dark m-0 fs-6"><?php echo htmlspecialchars($q['question_text']); ?></h6>
                    <div class="text-muted fs-8 mt-1">
                      <?php if ($q['hazard_source']): ?><strong>Tehlike Kaynağı:</strong> <?php echo htmlspecialchars($q['hazard_source']); ?> &nbsp;|&nbsp; <?php endif; ?>
                      <?php if ($q['hazard_name']): ?><strong>Tehlike:</strong> <?php echo htmlspecialchars($q['hazard_name']); ?><?php endif; ?>
                      <?php if ($q['affected_risk']): ?> &nbsp;|&nbsp; <strong>Etkilenme:</strong> <?php echo htmlspecialchars($q['affected_risk']); ?><?php endif; ?>
                    </div>
                  </div>
                </div>
                <?php if ($q['affected_people']): ?>
                  <span class="badge bg-light text-secondary border fs-8 text-nowrap"><i class="bi bi-people"></i> <?php echo htmlspecialchars($q['affected_people']); ?></span>
                <?php endif; ?>
              </div>

              <!-- Cevap Seçenek Butonları -->
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
                  <span class="badge bg-danger" id="risk_badge_<?php echo $q['id']; ?>">RİSK SKORU: <?php echo $defP * $defS; ?></span>
                </div>

                <!-- Mevcut Durum Açıklaması -->
                <div class="mb-3">
                  <label class="form-label fw-bold fs-8 text-dark">Mevcut Durum / Tespit Edilen Eksiklik</label>
                  <input type="text" name="answers[<?php echo $q['id']; ?>][current_status]" class="form-control form-control-sm" placeholder="Örn: Lavabolar tavanda su akıntısı mevcut..." value="<?php echo htmlspecialchars($q['current_status'] ?? ''); ?>">
                </div>

                <!-- Olasılık ($O$) ve Şiddet ($Ş$) Seçimi -->
                <div class="row g-3 mb-3">
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold fs-8 text-muted">Olasılık ($O$)</label>
                    <select name="answers[<?php echo $q['id']; ?>][probability]" class="form-select form-select-sm risk-calc-select" data-qid="<?php echo $q['id']; ?>" id="prob_<?php echo $q['id']; ?>">
                      <option value="1" <?php echo $defP == 1 ? 'selected' : ''; ?>>1 - Çok Küçük (Çok nadir)</option>
                      <option value="2" <?php echo $defP == 2 ? 'selected' : ''; ?>>2 - Küçük (Nadir)</option>
                      <option value="3" <?php echo $defP == 3 ? 'selected' : ''; ?>>3 - Orta (Olabilir)</option>
                      <option value="4" <?php echo $defP == 4 ? 'selected' : ''; ?>>4 - Yüksek (Sık sık)</option>
                      <option value="5" <?php echo $defP == 5 ? 'selected' : ''; ?>>5 - Çok Yüksek (Her an)</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold fs-8 text-muted">Şiddet ($Ş$)</label>
                    <select name="answers[<?php echo $q['id']; ?>][severity]" class="form-select form-select-sm risk-calc-select" data-qid="<?php echo $q['id']; ?>" id="sev_<?php echo $q['id']; ?>">
                      <option value="1" <?php echo $defS == 1 ? 'selected' : ''; ?>>1 - Çok Hafif (İlk yardım gerektirmez)</option>
                      <option value="2" <?php echo $defS == 2 ? 'selected' : ''; ?>>2 - Hafif (İlk yardım gerekir)</option>
                      <option value="3" <?php echo $defS == 3 ? 'selected' : ''; ?>>3 - Ciddi (Hastane tedavisi gerekir)</option>
                      <option value="4" <?php echo $defS == 4 ? 'selected' : ''; ?>>4 - Çok Ciddi (Ağır yaralanma / kalıcı hasar)</option>
                      <option value="5" <?php echo $defS == 5 ? 'selected' : ''; ?>>5 - Felaket (Ölümcül / Çoklu kayıp)</option>
                    </select>
                  </div>
                </div>

                <!-- Önlem Önerisi, Sorumlu ve Süre -->
                <div class="row g-3">
                  <div class="col-12 col-md-5">
                    <label class="form-label fw-bold fs-8 text-dark">Alınacak Önlemler / İyileştirmeler</label>
                    <input type="text" name="answers[<?php echo $q['id']; ?>][action_plan]" list="recommendations_list" class="form-control form-control-sm" placeholder="Kütüphaneden veya manuel yazın..." value="<?php echo htmlspecialchars($q['default_action_plan'] ?? ''); ?>">
                  </div>
                  <div class="col-12 col-md-4">
                    <label class="form-label fw-bold fs-8 text-dark">Sorumlu Birim / Kişi</label>
                    <input type="text" name="answers[<?php echo $q['id']; ?>][responsible_person]" list="responsibles_list" class="form-control form-control-sm" placeholder="Kütüphaneden veya manuel yazın..." value="<?php echo htmlspecialchars($q['default_responsible'] ?? ''); ?>">
                  </div>
                  <div class="col-12 col-md-3">
                    <label class="form-label fw-bold fs-8 text-dark">Termin / Süre</label>
                    <input type="text" name="answers[<?php echo $q['id']; ?>][deadline]" class="form-control form-control-sm" placeholder="Örn: 6 ay" value="<?php echo htmlspecialchars($q['default_deadline'] ?? ''); ?>">
                  </div>
                </div>

              </div>

            </div>
          <?php $qGlobalIndex++; endforeach; ?>

        </div>
      <?php $stepIdx++; endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Genel Notlar Kartı -->
  <div class="custom-card p-3 mb-4 bg-white border-0 shadow-sm rounded-4">
    <label class="form-label fw-bold text-dark fs-7"><i class="bi bi-journal-text text-primary me-1"></i> Saha Denetimi Genel Notları / Ek Gözlemler</label>
    <textarea name="notes" class="form-control form-control-sm" rows="3" placeholder="Saha genelinde tespit edilen ek hususlar, hava durumu veya genel izlenimler..."></textarea>
  </div>

  <!-- Alt İşlem Barı (Sabit ve Şık) -->
  <div class="custom-card p-3 d-flex align-items-center justify-content-between sticky-bottom bg-white shadow-lg border-top border-2 border-success mb-5" style="z-index:90;">
    <span class="text-muted fs-8"><i class="bi bi-info-circle me-1"></i> Tüm soruları yanıtladıktan sonra kaydet butonuna basabilirsiniz.</span>
    <button type="submit" class="btn btn-success px-4 py-2 font-weight-bold shadow">
      <i class="bi bi-check-circle-fill"></i> Saha Denetimini Tamamla ve Kaydet
    </button>
  </div>

</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
  
  // 1. Cevap Şıkkı Buton Tıklama ve Risk Kartı Açma
  const answerRadios = document.querySelectorAll('.answer-radio');
  answerRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      const qId = this.dataset.qid;
      const optId = this.dataset.optid || 0;
      const isTrigger = parseInt(this.dataset.trigger) === 1;
      const val = this.value;

      // Hidden input'a option_id yaz
      const optIdInput = document.getElementById('opt_id_input_' + qId);
      if (optIdInput) optIdInput.value = optId;

      // Label aktiflik sınıflarını temizle
      const qCard = document.getElementById('q_card_' + qId);
      const labels = qCard.querySelectorAll('.answer-btn-label');
      labels.forEach(lbl => {
        lbl.classList.remove('active', 'bg-danger', 'text-white', 'bg-warning', 'bg-success');
      });

      // Seçili butona active efekti ekle
      const parentLabel = this.closest('.answer-btn-label');
      if (parentLabel) {
        parentLabel.classList.add('active');
        if (val.includes('Hayır')) {
          parentLabel.classList.add('bg-danger', 'text-white');
        } else if (val.includes('Kısmen')) {
          parentLabel.classList.add('bg-warning', 'text-dark');
        } else {
          parentLabel.classList.add('bg-success', 'text-white');
        }
      }

      // Risk Değerlendirme & İyileştirme Kartını Göster / Gizle
      const riskPanel = document.getElementById('risk_panel_' + qId);
      if (riskPanel) {
        if (val.includes('Hayır') || val.includes('Kısmen') || isTrigger) {
          riskPanel.classList.remove('d-none');
        } else {
          riskPanel.classList.add('d-none');
        }
      }
    });
  });

  // 2. Canlı Risk Skoru Hesaplama ($R = O \times Ş$)
  const calcSelects = document.querySelectorAll('.risk-calc-select');
  calcSelects.forEach(select => {
    select.addEventListener('change', function() {
      const qId = this.dataset.qid;
      const pVal = parseInt(document.getElementById('prob_' + qId).value) || 1;
      const sVal = parseInt(document.getElementById('sev_' + qId).value) || 1;
      const score = pVal * sVal;

      const badge = document.getElementById('risk_badge_' + qId);
      if (badge) {
        badge.textContent = `RİSK SKORU: ${score}`;
        if (score >= 16) {
          badge.className = 'badge bg-danger';
        } else if (score >= 10) {
          badge.className = 'badge bg-warning text-dark';
        } else {
          badge.className = 'badge bg-info text-dark';
        }
      }
    });
  });

  // 3. Tab Değişiminde Adım Rozetini Güncelleme
  const pillBtns = document.querySelectorAll('#wizardPillsTab button');
  pillBtns.forEach(btn => {
    btn.addEventListener('shown.bs.tab', function(e) {
      const step = this.dataset.step;
      const total = pillBtns.length;
      const badge = document.getElementById('currentWizardStepBadge');
      if (badge) {
        badge.textContent = `Adım ${step} / ${total}`;
      }
    });
  });

});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
