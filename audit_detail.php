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
    SELECT a.*, u.unit_name, u.description as unit_desc, st.title as survey_title, st.category as survey_cat, usr.name_surname as auditor_name, usr.email as auditor_email
    FROM audits a
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
           rg.group_name, qo.option_text
    FROM audit_answers ans
    JOIN survey_questions sq ON ans.question_id = sq.id
    LEFT JOIN risk_groups rg ON sq.risk_group_id = rg.id
    LEFT JOIN question_options qo ON ans.option_id = qo.id
    WHERE ans.audit_id = ?
    ORDER BY COALESCE(rg.sort_order, 99) ASC, sq.sort_order ASC, sq.id ASC
");
$answersStmt->execute([$audit_id]);
$answers = $answersStmt->fetchAll();

$pct = (float)$audit['percentage_score'];
$badgeClass = $pct >= 80 ? 'bg-success text-white' : ($pct >= 50 ? 'bg-warning text-dark' : 'bg-danger text-white');
$riskLevel = $pct >= 80 ? 'DÜŞÜK RİSK / UYGUN' : ($pct >= 50 ? 'ORTA RİSK / DİKKAT' : 'YÜKSEK RİSK / TEHLİKE');

$pageTitle = 'İSG Risk Analiz Karnesi #DEN-' . sprintf('%04d', $audit['id']);
include __DIR__ . '/includes/header.php';
?>

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
        İŞ YERİ SAĞLIK VE GÜVENLİK BİRİMİ<br>
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
        <strong>R (Risk Derecesi):</strong> $Ş \times O$
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
            <th style="width: 90px;">RİSK GRUPLARI</th>
            <th style="width: 100px;">TEHLİKE KAYNAĞI</th>
            <th style="width: 100px;">TEHLİKE</th>
            <th style="width: 110px;">ETKİLENME (YAŞANABİLECEK RİSKLER)</th>
            <th style="width: 100px;">ETKİLENENLER</th>
            <th style="width: 130px;">MEVCUT DURUM / CEVAP</th>
            <th style="width: 35px;">O</th>
            <th style="width: 35px;">Ş</th>
            <th style="width: 45px;">R.D.</th>
            <th style="width: 140px;">ALINACAK ÖNLEMLER / İYİLEŞTİRMELER</th>
            <th style="width: 90px;">SORUMLU</th>
            <th style="width: 75px;">SÜRE / TERMİN</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($answers)): ?>
            <tr>
              <td colspan="12" class="text-center py-4 text-muted">Bu denetimde henüz risk analizi kaydı bulunmuyor.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($answers as $ans): ?>
              <?php
              $rScore = (int)$ans['risk_score'];
              $prob = (int)$ans['probability'];
              $sev = (int)$ans['severity'];

              if ($rScore == 0 && $prob > 0 && $sev > 0) {
                  $rScore = $prob * $sev;
              }

              $rClass = 'bg-success text-white';
              if ($rScore >= 16) $rClass = 'bg-danger text-white fw-bold';
              elseif ($rScore >= 10) $rClass = 'bg-warning text-dark fw-bold';
              elseif ($rScore >= 6) $rClass = 'bg-info text-dark fw-bold';

              $ansOption = !empty($ans['answer_option']) ? $ans['answer_option'] : ($ans['option_text'] ?? '-');
              ?>
              <tr>
                <td class="fw-bold text-primary"><?php echo htmlspecialchars($ans['group_name'] ?? 'Genel'); ?></td>
                <td><?php echo htmlspecialchars($ans['hazard_source'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($ans['hazard_name'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($ans['affected_risk'] ?? '-'); ?></td>
                <td class="text-muted"><?php echo htmlspecialchars($ans['affected_people'] ?? '-'); ?></td>
                <td>
                  <div class="fw-bold text-dark"><?php echo htmlspecialchars($ans['question_text']); ?></div>
                  <div class="mt-1">
                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ansOption); ?></span>
                  </div>
                  <?php if (!empty($ans['current_status'])): ?>
                    <div class="text-muted fs-8 mt-1"><em><?php echo htmlspecialchars($ans['current_status']); ?></em></div>
                  <?php endif; ?>
                </td>
                <td class="text-center fw-bold"><?php echo $prob > 0 ? $prob : '-'; ?></td>
                <td class="text-center fw-bold"><?php echo $sev > 0 ? $sev : '-'; ?></td>
                <td class="text-center">
                  <span class="badge <?php echo $rClass; ?> px-2 py-1 fs-8">
                    <?php echo $rScore > 0 ? $rScore : '-'; ?>
                  </span>
                </td>
                <td>
                  <?php echo !empty($ans['action_plan']) ? htmlspecialchars($ans['action_plan']) : '<span class="text-muted">-</span>'; ?>
                </td>
                <td class="fw-bold text-secondary"><?php echo htmlspecialchars($ans['responsible_person'] ?? '-'); ?></td>
                <td class="text-muted"><?php echo htmlspecialchars($ans['deadline'] ?? '-'); ?></td>
              </tr>
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

  html2pdf().set(opt).from(element).save().then(function() {
    if (targetBtn) {
      targetBtn.innerHTML = originalHTML;
      targetBtn.disabled = false;
    }
  });
}

// Otomatik İndirme / Yazdırma Trigger'ları
<?php if (isset($_GET['download_pdf']) && $_GET['download_pdf'] == 1): ?>
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() { downloadAuditPDF(); }, 500);
});
<?php endif; ?>

<?php if (isset($_GET['print']) && $_GET['print'] == 1): ?>
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() { window.print(); }, 500);
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
