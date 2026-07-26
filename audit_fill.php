<?php
/**
 * Tubİsg - Saha Risk Denetimi Doldurma Ekranı (audit_fill.php)
 * Kurum, Anket ve Birim Seçimi İle 12 Sütunlu İSG Risk Analiz Belgesine Göre Denetim Doldurma
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('audit_conduct');

$db = getDB();
$user = get_current_user_data();

$institution_id = (int)($_GET['institution_id'] ?? ($_POST['institution_id'] ?? 0));
$template_id = (int)($_GET['template_id'] ?? ($_POST['template_id'] ?? 0));
$unit_id = (int)($_GET['unit_id'] ?? ($_POST['unit_id'] ?? 0));

if ($template_id <= 0 || $unit_id <= 0) {
    set_flash('danger', 'Geçersiz denetim parametreleri.');
    header("Location: audit_new.php");
    exit;
}

// Kurum, Şablon ve Birim Bilgilerini Çek
$institution = null;
if ($institution_id > 0) {
    $stmtInst = $db->prepare("SELECT * FROM institutions WHERE id = ?");
    $stmtInst->execute([$institution_id]);
    $institution = $stmtInst->fetch();
}

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

// Varsayılan Standart Şıklar
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

        if ($selectedOptId <= 0 && !empty($selectedOptText) && isset($questionOptions[$qId])) {
            foreach ($questionOptions[$qId] as $opt) {
                if ($opt['option_text'] === $selectedOptText) {
                    $selectedOptId = (int)$opt['id'];
                    break;
                }
            }
        }
        
        $isEvet = (strpos($selectedOptText, 'Evet') !== false);
        $isMuaf = (strpos($selectedOptText, 'Denetim Dışı') !== false || strpos($selectedOptText, 'Muaf') !== false);

        if ($isEvet) {
            $currentStatus = 'Evet (Uygun)';
            $probability = 1;
            $severity = 1;
            $riskScore = 1;
            $actionPlan = !empty($qInput['action_plan']) ? trim($qInput['action_plan']) : (!empty($q['default_action_plan']) ? trim($q['default_action_plan']) : 'Gerekli Önlemler Alınmış (Uygun)');
        } elseif ($isMuaf) {
            $currentStatus = 'Denetim Dışı / Muaf';
            $probability = 1;
            $severity = 1;
            $riskScore = 1;
            $actionPlan = 'Muaf';
        } else {
            // Hayır veya Kısmen Seçildiğinde
            $currentStatus = trim($qInput['current_status'] ?? '');
            if (empty($currentStatus)) {
                $currentStatus = 'Tespit Edilen Eksiklik Var';
            }

            $probability = (int)($qInput['probability'] ?? ($q['default_probability'] ?? 2));
            if ($probability < 1) $probability = 1;
            if ($probability > 5) $probability = 5;

            $severity = (int)($qInput['severity'] ?? ($q['default_severity'] ?? 3));
            if ($severity < 1) $severity = 1;
            if ($severity > 5) $severity = 5;

            $riskScore = $probability * $severity;

            $actionPlan = trim($qInput['action_plan'] ?? '');
            if (empty($actionPlan) && !empty($q['default_action_plan'])) {
                $actionPlan = $q['default_action_plan'];
            }
        }

        if ($riskScore > $maxRiskScoreRecorded) {
            $maxRiskScoreRecorded = $riskScore;
        }

        // HER DURUMDA SORUMLU VE SÜRE ANKETE DAHİL EDİLİR
        $responsible = trim($qInput['responsible_person'] ?? '');
        if (empty($responsible) && !empty($q['default_responsible'])) {
            $responsible = $q['default_responsible'];
        }
        if (empty($responsible)) {
            $responsible = 'İşveren / İSG Birimi';
        }

        $deadline = trim($qInput['deadline'] ?? '');
        if (empty($deadline) && !empty($q['default_deadline'])) {
            $deadline = $q['default_deadline'];
        }
        if (empty($deadline)) {
            $deadline = 'Sürekli';
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
        if (!empty($actionPlan) && !in_array($actionPlan, $libRecommendations) && $actionPlan !== 'Muaf' && $actionPlan !== 'Gerekli Önlemler Alınmış (Uygun)') {
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
        INSERT INTO audits (institution_id, template_id, unit_id, auditor_id, total_score, max_possible_score, percentage_score, status, notes) 
        VALUES (?, ?, ?, ?, ?, 25, ?, 'Tamamlandı', ?)
    ");
    $stmtAudit->execute([
        $institution_id > 0 ? $institution_id : null,
        $template_id, 
        $unit_id, 
        $user['id'], 
        $maxRiskScoreRecorded, 
        (float)$maxRiskScoreRecorded, 
        $notes
    ]);
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

    log_action('Saha İSG Risk Denetimi Tamamlandı', "Kurum: " . ($institution['institution_name'] ?? 'Kurum') . ", Birim: {$unit['unit_name']}, Anket: {$template['title']} (#DEN-" . sprintf('%04d', $auditId) . ")");

    set_flash('success', 'Adım adım İSG risk denetimi ve analizi başarıyla kaydedildi.');
    header("Location: audit_detail.php?id=" . $auditId);
    exit;
}

$userNameDisplay = $user['full_name'] ?? $user['name'] ?? $user['username'] ?? 'İSG Uzmanı';
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

<style>
.answer-btn-card {
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 14px;
  background: #ffffff;
  transition: all 0.2s ease;
  font-weight: 700;
  font-size: 0.85rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.03);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  cursor: pointer;
  width: 100%;
}
.answer-btn-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.answer-btn-card.active-evet {
  background: #dcfce7 !important;
  color: #15803d !important;
  border-color: #22c55e !important;
  box-shadow: 0 4px 12px rgba(34, 197, 94, 0.2) !important;
}
.answer-btn-card.active-hayir {
  background: #fee2e2 !important;
  color: #b91c1c !important;
  border-color: #ef4444 !important;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2) !important;
}
.answer-btn-card.active-kismen {
  background: #fef3c7 !important;
  color: #b45309 !important;
  border-color: #f59e0b !important;
  box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2) !important;
}
.answer-btn-card.active-muaf {
  background: #f1f5f9 !important;
  color: #475569 !important;
  border-color: #94a3b8 !important;
}
.risk-meta-grid {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 12px 14px;
}
.risk-meta-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}
.risk-meta-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.95rem;
  flex-shrink: 0;
}
</style>

<!-- Sayfa Üst Barı -->
<div class="custom-card p-3 mb-4 bg-white border-0 shadow-sm rounded-4">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <div class="d-flex flex-wrap gap-2 mb-1">
        <?php if ($institution): ?>
          <span class="badge bg-danger-light text-danger font-weight-bold fs-8">
            <i class="bi bi-hospital me-1"></i> KURUM: <?php echo htmlspecialchars($institution['institution_name']); ?>
          </span>
        <?php endif; ?>
        <span class="badge bg-primary-light text-primary font-weight-bold fs-8">
          <i class="bi bi-building me-1"></i> BİRİM / SAHA: <?php echo htmlspecialchars($unit['unit_name']); ?>
        </span>
      </div>
      <h3 class="fw-extrabold m-0 text-dark"><?php echo htmlspecialchars($unit['unit_name']); ?></h3>
      <span class="text-muted fs-8">Anket Profili: <strong><?php echo htmlspecialchars($template['title']); ?></strong></span>
    </div>
    <div class="text-md-end">
      <span class="badge bg-light text-dark border p-2 px-3 rounded-pill fs-8">
        <i class="bi bi-person-fill text-success"></i> İSG Uzmanı: <strong><?php echo htmlspecialchars($userNameDisplay); ?></strong>
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

<form method="POST" action="audit_fill.php?institution_id=<?php echo $institution_id; ?>&template_id=<?php echo $template_id; ?>&unit_id=<?php echo $unit_id; ?>" id="auditFillForm">

  <div class="tab-content" id="wizardTabContent">
    <?php if (empty($groupedQuestions)): ?>
      <div class="alert alert-warning">Bu anket profilinde henüz tanımlanmış soru veya risk maddesi bulunmuyor.</div>
    <?php else: ?>
      <?php 
      $totalGroupsCount = count($groupedQuestions);
      $stepIdx = 1; 
      $qGlobalIndex = 1; 
      foreach ($groupedQuestions as $groupName => $gQuestions): 
        $groupQuestionsCount = count($gQuestions);
      ?>
        
        <div class="tab-pane fade <?php echo $stepIdx === 1 ? 'show active' : ''; ?>" id="wizard-step-<?php echo $stepIdx; ?>" role="tabpanel" data-group-index="<?php echo $stepIdx; ?>" data-total-questions="<?php echo $groupQuestionsCount; ?>">
          
          <!-- Risk Grubu Başlık Kartı -->
          <div class="card border-0 bg-dark text-white rounded-3 p-3 mb-3 shadow-sm">
            <div class="d-flex align-items-center justify-content-between">
              <h5 class="fw-bold m-0 text-white"><i class="bi bi-shield-exclamation text-warning me-2"></i> <?php echo htmlspecialchars($groupName); ?></h5>
              <span class="badge bg-secondary rounded-pill">Grup <?php echo $stepIdx; ?> / <?php echo $totalGroupsCount; ?> (<?php echo $groupQuestionsCount; ?> Soru)</span>
            </div>
          </div>

          <?php $groupQIndex = 1; foreach ($gQuestions as $q): ?>
            <?php
            $defP = (int)($q['default_probability'] ?? 2);
            $defS = (int)($q['default_severity'] ?? 3);
            $isFirstQuestionInGroup = ($groupQIndex === 1);
            ?>
            <!-- Soru Kartı -->
            <div class="custom-card question-card mb-4 border-2 step-question-card p-4 rounded-4 shadow-sm <?php echo $isFirstQuestionInGroup ? '' : 'd-none'; ?>" 
                 id="q_card_<?php echo $q['id']; ?>" 
                 data-group-step="<?php echo $stepIdx; ?>" 
                 data-q-seq="<?php echo $groupQIndex; ?>" 
                 data-q-total="<?php echo $groupQuestionsCount; ?>">
              
              <input type="hidden" name="answers[<?php echo $q['id']; ?>][option_id]" id="opt_id_input_<?php echo $q['id']; ?>" value="0">

              <!-- Soru Üst Başlık & Yanıt Durumu -->
              <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                <div class="d-flex align-items-start gap-3">
                  <div class="bg-dark text-white rounded-circle d-flex align-items-center justify-content-center fw-extrabold shadow-sm flex-shrink-0" style="width:34px; height:34px; font-size:0.9rem;">
                    <?php echo $qGlobalIndex; ?>
                  </div>
                  <div>
                    <h5 class="fw-extrabold text-dark m-0 fs-6 leading-snug"><?php echo htmlspecialchars($q['question_text']); ?></h5>
                  </div>
                </div>
                
                <span class="badge bg-success-subtle text-success border border-success-subtle font-weight-bold px-3 py-1.5 rounded-pill fs-8 d-none answered-badge flex-shrink-0" id="answered_badge_<?php echo $q['id']; ?>">
                  <i class="bi bi-check-circle-fill me-1"></i> Yanıtlandı
                </span>
              </div>

              <!-- MODERN TEHLİKE, ETKİLENME & RİSK BİLGİ GRID PANELDİR -->
              <div class="risk-meta-grid mb-4">
                <div class="row g-3">
                  <?php if (!empty($q['hazard_source'])): ?>
                    <div class="col-12 col-md-6">
                      <div class="risk-meta-item">
                        <div class="risk-meta-icon bg-danger-subtle text-danger">
                          <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <div>
                          <div class="text-uppercase text-muted font-weight-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">TEHLİKE KAYNAĞI</div>
                          <div class="fw-bold text-dark fs-8 mt-0.5"><?php echo htmlspecialchars($q['hazard_source']); ?></div>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($q['hazard_name'])): ?>
                    <div class="col-12 col-md-6">
                      <div class="risk-meta-item">
                        <div class="risk-meta-icon bg-warning-subtle text-dark">
                          <i class="bi bi-shield-exclamation"></i>
                        </div>
                        <div>
                          <div class="text-uppercase text-muted font-weight-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">TEHLİKE</div>
                          <div class="fw-bold text-dark fs-8 mt-0.5"><?php echo htmlspecialchars($q['hazard_name']); ?></div>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($q['affected_risk'])): ?>
                    <div class="col-12 col-md-6">
                      <div class="risk-meta-item">
                        <div class="risk-meta-icon bg-info-subtle text-info">
                          <i class="bi bi-activity"></i>
                        </div>
                        <div>
                          <div class="text-uppercase text-muted font-weight-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">ETKİLENME (YAŞANABİLECEK RİSKLER)</div>
                          <div class="fw-bold text-dark fs-8 mt-0.5"><?php echo htmlspecialchars($q['affected_risk']); ?></div>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($q['affected_people'])): ?>
                    <div class="col-12 col-md-6">
                      <div class="risk-meta-item">
                        <div class="risk-meta-icon bg-secondary-subtle text-secondary">
                          <i class="bi bi-people-fill"></i>
                        </div>
                        <div>
                          <div class="text-uppercase text-muted font-weight-bold" style="font-size: 0.65rem; letter-spacing: 0.5px;">ETKİLENEN GRUPLAR</div>
                          <div class="fw-bold text-dark fs-8 mt-0.5"><?php echo htmlspecialchars($q['affected_people']); ?></div>
                        </div>
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Cevap Seçenek Buton Kartları -->
              <div class="row g-2 mb-4">
                <?php foreach ($q['options'] as $opt): ?>
                  <?php
                  $optText = $opt['option_text'];
                  $optId = $opt['id'] ?? 0;
                  $isDanger = (strpos($optText, 'Hayır') !== false || $opt['trigger_action'] == 1);
                  $isWarning = strpos($optText, 'Kısmen') !== false;
                  ?>
                  <div class="col-6 col-md-3">
                    <label class="answer-btn-card answer-btn-label">
                      <input type="radio" name="answers[<?php echo $q['id']; ?>][answer_option]" value="<?php echo htmlspecialchars($optText); ?>" data-optid="<?php echo $optId; ?>" data-trigger="<?php echo $opt['trigger_action']; ?>" class="d-none answer-radio" data-qid="<?php echo $q['id']; ?>">
                      <i class="bi <?php echo $isDanger ? 'bi-x-circle-fill text-danger' : ($isWarning ? 'bi-exclamation-triangle-fill text-warning' : 'bi-check-circle-fill text-success'); ?> fs-6"></i>
                      <span><?php echo htmlspecialchars($optText); ?></span>
                    </label>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Riskli / Kısmen Veya Tetikleyici Aktif Olduğunda Açılan 5x5 Risk Matrisi & Önlem Kartı -->
              <div class="risk-matrix-panel d-none p-3 rounded-3 bg-light border border-warning mb-3" id="risk_panel_<?php echo $q['id']; ?>">
                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                  <h6 class="fw-bold text-danger m-0 fs-7">
                    <i class="bi bi-clipboard2-pulse-fill"></i> İSG Uzmanı Risk Değerlendirme & Önlem Kartı
                  </h6>
                  <span class="badge bg-danger" id="risk_badge_<?php echo $q['id']; ?>">RİSK SKORU: <?php echo $defP * $defS; ?></span>
                </div>

                <!-- Mevcut Durum Açıklaması -->
                <div class="mb-3">
                  <label class="form-label fw-bold fs-8 text-dark">Mevcut Durum / Tespit Edilen Eksiklik (Saha Tespiti)</label>
                  <input type="text" name="answers[<?php echo $q['id']; ?>][current_status]" class="form-control form-control-sm" placeholder="Örn: Lavabolar tavanda su akıntısı mevcut..." value="">
                </div>

                <!-- Olasılık (O) ve Şiddet (Ş) Seçimi -->
                <div class="row g-3 mb-3">
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold fs-8 text-muted">Olasılık (O)</label>
                    <select name="answers[<?php echo $q['id']; ?>][probability]" class="form-select form-select-sm risk-calc-select" data-qid="<?php echo $q['id']; ?>" id="prob_<?php echo $q['id']; ?>">
                      <option value="1" <?php echo $defP == 1 ? 'selected' : ''; ?>>1 - Çok Küçük (Çok nadir)</option>
                      <option value="2" <?php echo $defP == 2 ? 'selected' : ''; ?>>2 - Küçük (Nadir)</option>
                      <option value="3" <?php echo $defP == 3 ? 'selected' : ''; ?>>3 - Orta (Olabilir)</option>
                      <option value="4" <?php echo $defP == 4 ? 'selected' : ''; ?>>4 - Yüksek (Sık sık)</option>
                      <option value="5" <?php echo $defP == 5 ? 'selected' : ''; ?>>5 - Çok Yüksek (Her an)</option>
                    </select>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label fw-bold fs-8 text-muted">Şiddet (Ş)</label>
                    <select name="answers[<?php echo $q['id']; ?>][severity]" class="form-select form-select-sm risk-calc-select" data-qid="<?php echo $q['id']; ?>" id="sev_<?php echo $q['id']; ?>">
                      <option value="1" <?php echo $defS == 1 ? 'selected' : ''; ?>>1 - Çok Hafif (İlk yardım gerektirmez)</option>
                      <option value="2" <?php echo $defS == 2 ? 'selected' : ''; ?>>2 - Hafif (İlk yardım gerekir)</option>
                      <option value="3" <?php echo $defS == 3 ? 'selected' : ''; ?>>3 - Ciddi (Hastane tedavisi gerekir)</option>
                      <option value="4" <?php echo $defS == 4 ? 'selected' : ''; ?>>4 - Çok Ciddi (Ağır yaralanma / kalıcı hasar)</option>
                      <option value="5" <?php echo $defS == 5 ? 'selected' : ''; ?>>5 - Felaket (Ölümcül / Çoklu kayıp)</option>
                    </select>
                  </div>
                </div>

                <!-- Önlem Önerisi -->
                <div class="mb-3">
                  <label class="form-label fw-bold fs-8 text-dark">Alınacak Önlemler / İyileştirmeler</label>
                  <input type="text" name="answers[<?php echo $q['id']; ?>][action_plan]" list="recommendations_list" class="form-control form-control-sm" placeholder="Kütüphaneden veya manuel yazın..." value="<?php echo htmlspecialchars($q['default_action_plan'] ?? ''); ?>">
                </div>

              </div>

              <!-- Sorumlu ve Süre/Termin Alanı (HER DURUMDA AKTİF & GÖRÜNÜR) -->
              <div class="p-3 bg-white rounded-3 border mb-4 shadow-2xs">
                <div class="row g-3">
                  <div class="col-12 col-md-7">
                    <label class="form-label fw-bold fs-8 text-dark mb-1"><i class="bi bi-person-badge text-primary me-1"></i> Sorumlu Birim / Kişi</label>
                    <input type="text" name="answers[<?php echo $q['id']; ?>][responsible_person]" list="responsibles_list" class="form-control form-control-sm" placeholder="Örn: Tekn. Hiz. Yön. / İşveren" value="<?php echo htmlspecialchars($q['default_responsible'] ?? 'İşveren'); ?>">
                  </div>
                  <div class="col-12 col-md-5">
                    <label class="form-label fw-bold fs-8 text-dark mb-1"><i class="bi bi-clock-history text-success me-1"></i> Süre / Termin</label>
                    <input type="text" name="answers[<?php echo $q['id']; ?>][deadline]" class="form-control form-control-sm" placeholder="Örn: 6 Ay, Sürekli" value="<?php echo htmlspecialchars($q['default_deadline'] ?? 'Sürekli'); ?>">
                  </div>
                </div>
              </div>

              <!-- SONRAKİ SORU / İLERLEME BUTONU -->
              <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                <span class="text-muted fs-8 font-weight-bold">Soru <?php echo $groupQIndex; ?> / <?php echo $groupQuestionsCount; ?></span>
                <?php if ($groupQIndex < $groupQuestionsCount): ?>
                  <button type="button" class="btn btn-sm btn-success font-weight-bold px-4 py-2 rounded-3 next-q-btn" data-group-step="<?php echo $stepIdx; ?>" data-next-seq="<?php echo $groupQIndex + 1; ?>">
                    Sonraki Soru <i class="bi bi-arrow-down-circle-fill ms-1"></i>
                  </button>
                <?php else: ?>
                  <?php if ($stepIdx < $totalGroupsCount): ?>
                    <button type="button" class="btn btn-sm btn-primary font-weight-bold px-4 py-2 rounded-3 next-group-btn" data-next-step="<?php echo $stepIdx + 1; ?>">
                      Sonraki Risk Grubuna Geç (Adım <?php echo $stepIdx + 1; ?>) <i class="bi bi-arrow-right-circle-fill ms-1"></i>
                    </button>
                  <?php else: ?>
                    <span class="badge bg-success p-2 px-3 fs-8"><i class="bi bi-check-all"></i> Tüm Sorular Tamamlandı</span>
                  <?php endif; ?>
                <?php endif; ?>
              </div>

            </div>
          <?php $groupQIndex++; $qGlobalIndex++; endforeach; ?>

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
  <div class="custom-card p-3 d-flex align-items-center justify-content-between bg-white shadow-lg border-top border-2 border-success mb-4 rounded-4" style="position: sticky; bottom: 45px; z-index:90;">
    <span class="text-muted fs-8"><i class="bi bi-info-circle me-1"></i> Tüm soruları yanıtladıktan sonra kaydet butonuna basabilirsiniz.</span>
    <button type="submit" class="btn btn-success px-4 py-2 font-weight-bold shadow rounded-3">
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

      // Yanıtlandı rozetini aç
      const answeredBadge = document.getElementById('answered_badge_' + qId);
      if (answeredBadge) answeredBadge.classList.remove('d-none');

      // Label aktiflik sınıflarını temizle
      const qCard = document.getElementById('q_card_' + qId);
      const labels = qCard.querySelectorAll('.answer-btn-card');
      labels.forEach(lbl => {
        lbl.classList.remove('active-evet', 'active-hayir', 'active-kismen', 'active-muaf');
      });

      // Seçili butona active efekti ekle
      const parentLabel = this.closest('.answer-btn-card');
      if (parentLabel) {
        if (val.includes('Hayır')) {
          parentLabel.classList.add('active-hayir');
        } else if (val.includes('Kısmen')) {
          parentLabel.classList.add('active-kismen');
        } else if (val.includes('Muaf') || val.includes('Denetim Dışı')) {
          parentLabel.classList.add('active-muaf');
        } else {
          parentLabel.classList.add('active-evet');
        }
      }

      // Risk Değerlendirme & İyileştirme Kartını Göster / Gizle (Sadece Hayır / Kısmen Seçilirse)
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

  // 2. Sıralı Soru Akışı (Sonraki Soru Butonu Tıklandığında Bir Alt Soruyu Aç)
  const nextQBtns = document.querySelectorAll('.next-q-btn');
  nextQBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const groupStep = this.dataset.groupStep;
      const nextSeq = this.dataset.nextSeq;

      // Sıradaki soru kartını bul ve d-none sınıfını kaldır
      const nextQCard = document.querySelector(`.step-question-card[data-group-step="${groupStep}"][data-q-seq="${nextSeq}"]`);
      if (nextQCard) {
        nextQCard.classList.remove('d-none');
        nextQCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
    });
  });

  // 3. Sonraki Risk Grubuna Geçiş Butonu
  const nextGroupBtns = document.querySelectorAll('.next-group-btn');
  nextGroupBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const nextStep = this.dataset.nextStep;
      const targetTabBtn = document.getElementById('wizard-tab-' + nextStep);
      if (targetTabBtn) {
        const bsTab = new bootstrap.Tab(targetTabBtn);
        bsTab.show();
        window.scrollTo({ top: 150, behavior: 'smooth' });
      }
    });
  });

  // 4. Canlı Risk Skoru Hesaplama ($R = O \times Ş$)
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

  // 5. Tab Değişiminde Adım Rozetini Güncelleme
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
