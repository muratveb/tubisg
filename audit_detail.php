<?php
/**
 * Tubİsg - Saha Denetim Detayı & Karnesi
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('audit_view');

$db = getDB();
$audit_id = (int)($_GET['id'] ?? 0);

if ($audit_id <= 0) {
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

// Seçilen Cevapları ve Soruları Çek
$answersStmt = $db->prepare("
    SELECT ans.*, sq.question_text, qo.option_text, qo.points
    FROM audit_answers ans
    JOIN survey_questions sq ON ans.question_id = sq.id
    JOIN question_options qo ON ans.option_id = qo.id
    WHERE ans.audit_id = ?
    ORDER BY sq.sort_order ASC, sq.id ASC
");
$answersStmt->execute([$audit_id]);
$answers = $answersStmt->fetchAll();

// Sorulara Göre Grupla
$groupedAnswers = [];
foreach ($answers as $ans) {
    $qText = $ans['question_text'];
    if (!isset($groupedAnswers[$qText])) {
        $groupedAnswers[$qText] = [];
    }
    $groupedAnswers[$qText][] = $ans;
}

$pct = (float)$audit['percentage_score'];
$badgeClass = $pct >= 80 ? 'bg-success text-white' : ($pct >= 50 ? 'bg-warning text-dark' : 'bg-danger text-white');
$riskLevel = $pct >= 80 ? 'DÜŞÜK RİSK / UYGUN' : ($pct >= 50 ? 'ORTA RİSK / DİKKAT' : 'YÜKSEK RİSK / TEHLİKE');

$pageTitle = 'Denetim Karnesi #DEN-' . sprintf('%04d', $audit['id']);
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
    <h3 class="fw-extrabold m-0">Saha Denetim Karnesi #DEN-<?php echo sprintf('%04d', $audit['id']); ?></h3>
  </div>

  <?php if (has_permission('reports_export')): ?>
    <div class="d-flex flex-wrap gap-2">
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
    </div>
  <?php endif; ?>
</div>

<!-- PDF İÇİN YAZDIRILABİLİR KARNE ALANI (PİKSEL MÜKEMMEL PDF HİZALAMA) -->
<div id="auditScorecardPrintArea" style="background: #ffffff; padding: 10px; border-radius: 12px;">

  <!-- Denetim Karne Başlığı Kartı -->
  <div class="custom-card mb-4" style="border-top: 6px solid <?php echo $pct >= 80 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444'); ?>; padding: 20px;">
    <div class="row align-items-center g-3">
      
      <!-- Sol Bilgi Alanı -->
      <div class="col-12 col-md-7">
        <span class="badge bg-primary-light text-primary font-weight-bold mb-2">
          <i class="bi bi-building"></i> BİRİM: <?php echo htmlspecialchars($audit['unit_name']); ?>
        </span>
        <h3 class="fw-extrabold text-dark mb-3" style="font-size: 1.4rem; line-height: 1.3;"><?php echo htmlspecialchars($audit['survey_title']); ?></h3>
        
        <div class="row text-muted fs-7 g-2">
          <div class="col-4">
            <div style="font-size: 0.75rem; color: #64748b;">Denetçi</div>
            <div style="color: #0f172a; font-weight: 700; font-size: 0.85rem;"><?php echo htmlspecialchars($audit['auditor_name']); ?></div>
          </div>
          <div class="col-4">
            <div style="font-size: 0.75rem; color: #64748b;">Tarih & Saat</div>
            <div style="color: #0f172a; font-weight: 700; font-size: 0.85rem;"><?php echo date('d.m.Y - H:i', strtotime($audit['audit_date'])); ?></div>
          </div>
          <div class="col-4">
            <div style="font-size: 0.75rem; color: #64748b;">Kategori</div>
            <div style="color: #0f172a; font-weight: 700; font-size: 0.85rem;"><?php echo htmlspecialchars($audit['survey_cat']); ?></div>
          </div>
        </div>
      </div>

      <!-- Sağ Skor Kutusu (PDF Çıktısında Hizalama Kaymasını Önleyen Sabit Kutu) -->
      <div class="col-12 col-md-5">
        <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 16px; text-align: center; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
          <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; letter-spacing: 0.5px;">GENEL İSG UYGUNLUK KARNESİ</div>
          <div style="font-size: 2.2rem; font-weight: 800; color: #0f172a; line-height: 1.1; margin-bottom: 6px;">%<?php echo number_format($pct, 1); ?></div>
          <div style="margin-bottom: 8px;">
            <span class="badge <?php echo $badgeClass; ?>" style="font-size: 0.75rem; font-weight: 800; padding: 6px 12px; border-radius: 20px; display: inline-block;">
              <?php echo $riskLevel; ?>
            </span>
          </div>
          <div style="font-size: 0.75rem; color: #64748b;">
            Kazanılan Puan: <strong style="color: #059669; font-weight: 800;"><?php echo $audit['total_score']; ?></strong> / Maks: <strong style="color: #0f172a; font-weight: 800;"><?php echo $audit['max_possible_score']; ?></strong>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Soru ve Seçilen Cevaplar Karnesi -->
  <div class="custom-card mb-4" style="padding: 20px;">
    <div class="custom-card-header mb-3 pb-2 border-bottom">
      <h5 class="custom-card-title m-0" style="font-size: 1.1rem;">
        <i class="bi bi-list-check text-success"></i> Denetim Detayı ve Verilen Cevaplar
      </h5>
    </div>

    <?php if (empty($groupedAnswers)): ?>
      <div class="text-muted text-center py-4">Bu denetimde kaydedilmiş bir cevap seçeneği bulunmuyor.</div>
    <?php else: ?>
      <?php $qCount = 1; foreach ($groupedAnswers as $qText => $optList): ?>
        <div class="mb-4 pb-3 border-bottom last-border-0">
          <h6 class="fw-bold text-dark mb-3" style="font-size: 0.95rem;">
            <span class="badge bg-secondary rounded-circle me-2"><?php echo $qCount; ?></span>
            <?php echo htmlspecialchars($qText); ?>
          </h6>

          <div class="ps-2 ps-md-3">
            <?php foreach ($optList as $ans): ?>
              <?php
              $p = (int)$ans['points_awarded'];
              $pClass = $p > 0 ? 'bg-success-light text-success' : ($p < 0 ? 'bg-danger-light text-danger' : 'bg-light text-muted');
              $pSign = $p > 0 ? '+' : '';
              ?>
              <div class="d-flex align-items-center justify-content-between p-2 px-3 mb-2 rounded bg-light border-start border-3 border-primary">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-check-square-fill text-success fs-6"></i>
                  <span class="fw-bold text-dark fs-7"><?php echo htmlspecialchars($ans['option_text']); ?></span>
                </div>
                <span class="badge <?php echo $pClass; ?> p-2 font-weight-bold" style="font-size: 0.75rem;">
                  <?php echo $pSign . $p; ?> Puan
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php $qCount++; endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Saha Notları ve Gözlemler -->
  <?php if (!empty($audit['notes'])): ?>
  <div class="custom-card mb-4" style="padding: 20px;">
    <div class="custom-card-header mb-2 pb-2 border-bottom">
      <h5 class="custom-card-title m-0" style="font-size: 1.1rem;">
        <i class="bi bi-card-text text-warning"></i> Denetçi Saha Notları & Gözlemler
      </h5>
    </div>
    <p class="m-0 text-dark fs-7" style="white-space: pre-line; line-height: 1.6;"><?php echo htmlspecialchars($audit['notes']); ?></p>
  </div>
  <?php endif; ?>

</div> <!-- /#auditScorecardPrintArea -->

<script>
function downloadAuditPDF(evt) {
  const element = document.getElementById('auditScorecardPrintArea');
  const opt = {
    margin:       [8, 8, 8, 8],
    filename:     'TubISG_Denetim_Karnesi_#DEN-<?php echo sprintf('%04d', $audit['id']); ?>.pdf',
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 2, useCORS: true, logging: false, scrollY: 0 },
    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
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

// Eğer URL'de download_pdf=1 geldiyse otomatik indir
<?php if (isset($_GET['download_pdf']) && $_GET['download_pdf'] == 1): ?>
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() { downloadAuditPDF(); }, 500);
});
<?php endif; ?>

// Eğer URL'de print=1 geldiyse otomatik yazdır penceresi aç
<?php if (isset($_GET['print']) && $_GET['print'] == 1): ?>
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() { window.print(); }, 500);
});
<?php endif; ?>
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
