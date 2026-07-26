<?php
/**
 * Tubİsg - Saha Denetim Detayı & Risk Analizi Karnesi
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('audit_view');

$db = getDB();
$audit_id = (int)($_GET['id'] ?? 0);

if ($audit_id <= 0) {
    header("Location: audits_list.php");
    exit;
}

// Denetim Silme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_audit') {
    require_permission('audit_delete');
    $stmt = $db->prepare("DELETE FROM audits WHERE id = ?");
    $stmt->execute([$audit_id]);
    log_action('Denetim Raporu Silindi', "Denetim ID #DEN-" . sprintf('%04d', $audit_id) . " detay sayfasından silindi.");
    set_flash('success', "Denetim kaydı (#DEN-" . sprintf('%04d', $audit_id) . ") başarıyla silindi.");
    header("Location: audits_list.php");
    exit;
}

// Denetim Detayını Çek
$stmt = $db->prepare("
    SELECT a.*, inst.institution_name, inst.code as inst_code, u.unit_name, u.description as unit_desc, st.title as survey_title, st.category as survey_cat, usr.name_surname as auditor_name, usr.email as auditor_email
    FROM audits a
    LEFT JOIN institutions inst ON a.institution_id = inst.id
    JOIN units u ON a.unit_id = u.id
    JOIN survey_templates st ON a.template_id = st.id
    JOIN users usr ON a.auditor_id = usr.id
    WHERE a.id = ?
");
$stmt->execute([$audit_id]);
$audit = $stmt->fetch();

if (!$audit) {
    set_flash('danger', 'Denetim kaydı bulunamadı.');
    header("Location: audits_list.php");
    exit;
}

// Seçilen Cevapları ve Risk Matrisi Detaylarını Çek
$answersStmt = $db->prepare("
    SELECT ans.*, sq.question_text, sq.hazard_source, sq.hazard_name, sq.affected_risk, sq.affected_people, 
           sq.default_responsible, sq.default_deadline, rg.group_name, qo.option_text
    FROM audit_answers ans
    JOIN survey_questions sq ON ans.question_id = sq.id
    LEFT JOIN risk_groups rg ON sq.risk_group_id = rg.id
    LEFT JOIN question_options qo ON ans.option_id = qo.id
    WHERE ans.audit_id = ?
    ORDER BY COALESCE(rg.sort_order, 99) ASC, sq.sort_order ASC, sq.id ASC
");
$answersStmt->execute([$audit_id]);
$answers = $answersStmt->fetchAll();

// Risk Gruplarına Göre Grupla (Dikey Birleştirilmiş Hücre İçin)
$groupedAnswersList = [];
foreach ($answers as $ans) {
    $gName = !empty($ans['group_name']) ? $ans['group_name'] : 'Genel Riskler';
    $groupedAnswersList[$gName][] = $ans;
}

$pct = (float)$audit['percentage_score'];
$badgeClass = $pct >= 80 ? 'bg-success text-white' : ($pct >= 50 ? 'bg-warning text-dark' : 'bg-danger text-white');
$riskLevel = $pct >= 80 ? 'DÜŞÜK RİSK / UYGUN' : ($pct >= 50 ? 'ORTA RİSK / DİKKAT' : 'YÜKSEK RİSK / TEHLİKE');

$institutionTitle = !empty($audit['institution_name']) ? mb_strtoupper($audit['institution_name'], 'UTF-8') : 'İŞ YERİ SAĞLIK VE GÜVENLİK BİRİMİ';

$pageTitle = 'İSG Risk Analiz Karnesi #DEN-' . sprintf('%04d', $audit['id']);
include __DIR__ . '/includes/header.php';
?>

<style>
.vhead-th {
  writing-mode: vertical-rl;
  transform: rotate(180deg);
  white-space: nowrap;
  padding: 4px 2px !important;
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.3px;
  height: 140px;
  vertical-align: middle !important;
  text-align: center !important;
  line-height: 1.2;
}
.rg-vcell {
  writing-mode: vertical-rl;
  transform: rotate(180deg);
  text-align: center !important;
  vertical-align: middle !important;
  white-space: nowrap;
  font-weight: 800;
  padding: 12px 6px !important;
  background-color: #f1f5f9 !important;
  font-size: 0.80rem;
  letter-spacing: 0.5px;
}
.vcell {
  writing-mode: vertical-rl;
  transform: rotate(180deg);
  text-align: center !important;
  vertical-align: middle !important;
  white-space: nowrap;
  font-weight: 700;
  padding: 8px 4px !important;
  font-size: 0.78rem;
  letter-spacing: 0.3px;
}
@media print {
  .vhead-th {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    height: 130px;
  }
  .rg-vcell, .vcell {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
  }
}
</style>

<!-- html2pdf.js CDN (Direkt PDF İndirme Motoru) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<!-- Üst İşlem Çubuğu / Export Butonları (Yazdırmada Gizlenir) -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 no-print">
  <div>
    <a href="audits_list.php" class="btn btn-sm btn-outline-secondary mb-1">
      <i class="bi bi-arrow-left"></i> Raporlar Listesine Dön
    </a>
    <h3 class="fw-extrabold m-0">Saha İSG Risk Analiz Formu #DEN-<?php echo sprintf('%04d', $audit['id']); ?></h3>
  </div>

  <div class="d-flex flex-wrap gap-2">
    <?php if (has_permission('reports_export')): ?>
      <button onclick="window.print();" class="btn btn-outline-dark fw-bold">
        <i class="bi bi-printer"></i> Yazdır
      </button>
      <button onclick="downloadAuditPDF(event);" class="btn btn-danger font-weight-bold">
        <i class="bi bi-file-earmark-pdf-fill"></i> PDF
      </button>
      <a href="export.php?id=<?php echo $audit['id']; ?>&format=excel" class="btn btn-success font-weight-bold">
        <i class="bi bi-file-earmark-excel-fill"></i> Excel
      </a>
      <a href="export.php?id=<?php echo $audit['id']; ?>&format=word" class="btn btn-primary font-weight-bold">
        <i class="bi bi-file-earmark-word-fill"></i> Word
      </a>
    <?php endif; ?>

    <?php if (has_permission('audit_delete')): ?>
      <form method="POST" action="audit_detail.php?id=<?php echo $audit['id']; ?>" class="d-inline confirm-delete-form" data-confirm-title="Denetim Raporunu Sil" data-confirm-text="Bu denetim kaydını (#DEN-<?php echo sprintf('%04d', $audit['id']); ?>) ve tüm detaylarını silmek istediğinize emin misiniz?">
        <input type="hidden" name="action" value="delete_audit">
        <button type="submit" class="btn btn-outline-danger font-weight-bold" title="Denetim Kaydını Sil">
          <i class="bi bi-trash-fill"></i> Denetimi Sil
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- RESMİ İSG FORMATINDA YAZDIRILABİLİR BİRİM BAZLI RİSK ANALİZ ALANI -->
<div id="auditScorecardPrintArea" style="background: #ffffff; padding: 15px; border-radius: 12px;">

  <!-- Başlık & Kurumsal Form Header -->
  <div class="custom-card mb-4 p-3 border-2 border-primary" style="background: #f8fafc;">
    <div class="text-center border-bottom pb-3 mb-3">
      <h4 class="fw-extrabold text-dark m-0 fs-5" style="letter-spacing: -0.3px;">
        <?php echo htmlspecialchars($institutionTitle); ?><br>
        <span class="text-primary text-uppercase">BİRİM BAZLI RİSK ANALİZ VE DENETİM FORMU</span>
      </h4>
    </div>

    <!-- Form Üst Metrik ve Açıklama Çizelgesi -->
    <div class="row g-2 text-dark fs-8 font-weight-bold mb-3 border-bottom pb-2">
      <div class="col-12 col-md-6">
        <strong>RİSK ANALİZİ YAPILAN YER:</strong> <span class="text-primary fs-7"><?php echo htmlspecialchars($audit['unit_name']); ?></span>
      </div>
      <div class="col-12 col-md-3">
        <strong>TARİH:</strong> <span><?php echo date('d.m.Y - H:i', strtotime($audit['audit_date'])); ?></span>
      </div>
      <div class="col-12 col-md-3 text-md-end">
        <strong>DENETÇİ:</strong> <span><?php echo htmlspecialchars($audit['auditor_name']); ?></span>
      </div>
    </div>

    <!-- Olasılık & Şiddet Risk Derecesi Tanımlama Skalası (Resmi İSG Lejantı) -->
    <div class="p-2 rounded bg-white border fs-8">
      <div class="mb-1 text-muted">
        <strong>Ş (Şiddet):</strong> 1: Çok hafif, 2: Hafif, 3: Ciddi, 4: Çok Ciddi, 5: Felaket &nbsp;|&nbsp; 
        <strong>O (Olasılık):</strong> 1: Çok küçük, 2: Küçük, 3: Orta, 4: Yüksek, 5: Çok yüksek &nbsp;|&nbsp; 
        <strong>R (Risk Derecesi):</strong> R = O × Ş
      </div>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge bg-success">1 ≤ R ≤ 5: Kabul edilebilir Risk</span>
        <span class="badge bg-info text-dark">6 ≤ R ≤ 9: Önemli Risk</span>
        <span class="badge bg-warning text-dark">10 ≤ R ≤ 15: Dikkate Değer Risk</span>
        <span class="badge bg-danger">16 ≤ R ≤ 25: Kabul Edilemez Risk</span>
      </div>
    </div>
  </div>

  <!-- BİRİM BAZLI RİSK ANALİZİ RESMİ MATRİS TABLOSU -->
  <div class="custom-card mb-4 p-0 overflow-hidden border">
    <div class="table-responsive">
      <table class="table table-bordered table-striped align-middle m-0" style="font-size: 0.78rem;">
        <thead class="table-dark text-center align-middle" style="font-size: 0.72rem; letter-spacing: 0.3px;">
          <tr>
            <th class="vhead-th" style="width: 58px;">RİSK GRUPLARI</th>
            <th style="width: 105px;">TEHLİKE KAYNAĞI</th>
            <th style="width: 105px;">TEHLİKE</th>
            <th style="width: 115px;">ETKİLENME (YAŞANABİLECEK RİSKLER)</th>
            <th style="width: 100px;">ETKİLENENLER</th>
            <th style="width: 140px;">MEVCUT DURUM / CEVAP</th>
            <th class="vhead-th" style="width: 42px;">OLASILIK (O)</th>
            <th class="vhead-th" style="width: 42px;">ŞİDDET (Ş)</th>
            <th class="vhead-th" style="width: 55px;">RİSK DERECESİ (R)</th>
            <th style="width: 145px;">ALINACAK ÖNLEMLER / İYİLEŞTİRMELER</th>
            <th class="vhead-th" style="width: 48px;">SORUMLU</th>
            <th class="vhead-th" style="width: 52px;">SÜRE / TERMİN</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($groupedAnswersList)): ?>
            <tr>
              <td colspan="12" class="text-center py-4 text-muted">Bu denetimde henüz risk analizi kaydı bulunmuyor.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($groupedAnswersList as $groupName => $gAnswers): ?>
              <?php $gCount = count($gAnswers); ?>
              <?php foreach ($gAnswers as $idx => $ans): ?>
                <?php
                $rScore = (int)$ans['risk_score'];
                $prob = (int)$ans['probability'];
                $sev = (int)$ans['severity'];

                if ($rScore == 0 && $prob > 0 && $sev > 0) {
                    $rScore = $prob * $sev;
                }

                $ansOption = !empty($ans['answer_option']) ? $ans['answer_option'] : ($ans['option_text'] ?? 'Evet (Uygun)');
                $isEvet = (strpos($ansOption, 'Evet') !== false);
                $isMuaf = (strpos($ansOption, 'Denetim Dışı') !== false || strpos($ansOption, 'Muaf') !== false);

                if ($isEvet) {
                    $rClass = 'bg-success text-white';
                    $statusDisplay = ''; // Evet için ekstra alt yazı gösterilmez
                    $actionDisplay = !empty($ans['action_plan']) && strpos($ans['action_plan'], 'girilecek') === false ? htmlspecialchars($ans['action_plan']) : 'Gerekli Önlemler Alınmış';
                    $probDisplay = 1;
                    $sevDisplay = 1;
                    $rScoreDisplay = 1;
                } elseif ($isMuaf) {
                    $rClass = 'bg-secondary text-white';
                    $statusDisplay = ''; // Muaf için ekstra alt yazı gösterilmez
                    $actionDisplay = 'Muaf';
                    $probDisplay = '-';
                    $sevDisplay = '-';
                    $rScoreDisplay = '-';
                } else {
                    $rClass = 'bg-success text-white';
                    if ($rScore >= 16) $rClass = 'bg-danger text-white fw-bold';
                    elseif ($rScore >= 10) $rClass = 'bg-warning text-dark fw-bold';
                    elseif ($rScore >= 6) $rClass = 'bg-info text-dark fw-bold';

                    $statusDisplay = !empty($ans['current_status']) && strpos($ans['current_status'], 'girilecek') === false ? htmlspecialchars($ans['current_status']) : 'Tespit Edilen Eksiklik Var';
                    $actionDisplay = !empty($ans['action_plan']) && strpos($ans['action_plan'], 'girilecek') === false ? htmlspecialchars($ans['action_plan']) : 'İyileştirme Yapılmalı';
                    $probDisplay = $prob > 0 ? $prob : '-';
                    $sevDisplay = $sev > 0 ? $sev : '-';
                    $rScoreDisplay = $rScore > 0 ? $rScore : '-';
                }

                // Her Koşulda Sorumlu ve Süre Gösterilir
                $responsibleDisplay = !empty($ans['responsible_person']) ? $ans['responsible_person'] : (!empty($ans['default_responsible']) ? $ans['default_responsible'] : 'İşveren');
                $deadlineDisplay = !empty($ans['deadline']) ? $ans['deadline'] : (!empty($ans['default_deadline']) ? $ans['default_deadline'] : 'Sürekli');
                ?>
                <tr>
                  <?php if ($idx === 0): ?>
                    <td rowspan="<?php echo $gCount; ?>" class="fw-extrabold text-primary text-center align-middle rg-vcell">
                      <?php echo htmlspecialchars($groupName); ?>
                    </td>
                  <?php endif; ?>
                  <td><?php echo htmlspecialchars($ans['hazard_source'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($ans['hazard_name'] ?? '-'); ?></td>
                  <td><?php echo htmlspecialchars($ans['affected_risk'] ?? '-'); ?></td>
                  <td class="text-muted"><?php echo htmlspecialchars($ans['affected_people'] ?? '-'); ?></td>
                  <td>
                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($ans['question_text']); ?></div>
                    <div class="mt-1">
                      <span class="badge <?php echo $isEvet ? 'bg-success' : ($isMuaf ? 'bg-secondary' : 'bg-danger'); ?> text-white me-1"><?php echo htmlspecialchars($ansOption); ?></span>
                    </div>
                    <?php if (!$isEvet && !$isMuaf && !empty($statusDisplay)): ?>
                      <div class="text-muted fs-8 mt-1"><em><?php echo $statusDisplay; ?></em></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-center fw-bold"><?php echo $probDisplay; ?></td>
                  <td class="text-center fw-bold"><?php echo $sevDisplay; ?></td>
                  <td class="text-center">
                    <?php if (is_numeric($rScoreDisplay)): ?>
                      <span class="badge <?php echo $rClass; ?> px-2 py-1 fs-8"><?php echo $rScoreDisplay; ?></span>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td><?php echo $actionDisplay; ?></td>
                  <td class="vcell text-secondary fw-bold"><?php echo htmlspecialchars($responsibleDisplay); ?></td>
                  <td class="vcell text-dark font-weight-bold"><?php echo htmlspecialchars($deadlineDisplay); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Saha Notları -->
  <?php if (!empty($audit['notes'])): ?>
  <div class="custom-card mb-4 p-3 border">
    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-card-text text-warning"></i> Denetçi Saha Notları & Gözlemler</h5>
    <p class="m-0 text-dark fs-7" style="white-space: pre-line; line-height: 1.5;"><?php echo htmlspecialchars($audit['notes']); ?></p>
  </div>
  <?php endif; ?>

</div> <!-- /#auditScorecardPrintArea -->

<script>
function downloadAuditPDF(evt) {
  const element = document.getElementById('auditScorecardPrintArea');
  const opt = {
    margin:       [6, 6, 6, 6],
    filename:     'TubISG_Birim_Risk_Analiz_Formu_#DEN-<?php echo sprintf('%04d', $audit['id']); ?>.pdf',
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 2, useCORS: true, logging: false, scrollY: 0 },
    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
  };
  
  let targetBtn = evt ? (evt.target.tagName === 'BUTTON' ? evt.target : evt.target.closest('button')) : null;
  let originalHTML = '';
  if (targetBtn) {
    originalHTML = targetBtn.innerHTML;
    targetBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Hazırlanıyor...';
    targetBtn.disabled = true;
  }

  html2pdf().set(opt).from(element).save().then(() => {
    if (targetBtn) {
      targetBtn.innerHTML = originalHTML;
      targetBtn.disabled = false;
    }
  }).catch(err => {
    console.error(err);
    if (targetBtn) {
      targetBtn.innerHTML = originalHTML;
      targetBtn.disabled = false;
    }
    alert('PDF oluşturulurken hata oluştu. Lütfen "Yazdır" butonundan PDF olarak kaydetmeyi deneyin.');
  });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
