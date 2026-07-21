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
$badgeClass = $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning text-dark' : 'bg-danger');
$riskLevel = $pct >= 80 ? 'DÜŞÜK RİSK / UYGUN' : ($pct >= 50 ? 'ORTA RİSK / DİKKAT' : 'YÜKSEK RİSK / TEHLİKE');

$pageTitle = 'Denetim Karnesi #DEN-' . sprintf('%04d', $audit['id']);
include __DIR__ . '/includes/header.php';
?>

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
      <a href="export.php?id=<?php echo $audit['id']; ?>&format=pdf" class="btn btn-danger font-weight-bold" target="_blank">
        <i class="bi bi-file-earmark-pdf"></i> PDF
      </a>
      <a href="export.php?id=<?php echo $audit['id']; ?>&format=excel" class="btn btn-success font-weight-bold">
        <i class="bi bi-file-earmark-excel"></i> Excel
      </a>
      <a href="export.php?id=<?php echo $audit['id']; ?>&format=word" class="btn btn-primary font-weight-bold">
        <i class="bi bi-file-earmark-word"></i> Word
      </a>
    </div>
  <?php endif; ?>
</div>

<!-- Denetim Karne Başlığı Kartı -->
<div class="custom-card mb-4" style="border-top: 6px solid <?php echo $pct >= 80 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444'); ?>;">
  <div class="row align-items-center g-3">
    <div class="col-12 col-md-8">
      <span class="badge bg-primary-light text-primary font-weight-bold mb-2">
        <i class="bi bi-building"></i> BIRIM: <?php echo htmlspecialchars($audit['unit_name']); ?>
      </span>
      <h3 class="fw-extrabold text-dark mb-2"><?php echo htmlspecialchars($audit['survey_title']); ?></h3>
      
      <div class="row text-muted fs-7 g-2 mt-2">
        <div class="col-6 col-sm-4">
          <strong>Denetçi:</strong><br><?php echo htmlspecialchars($audit['auditor_name']); ?>
        </div>
        <div class="col-6 col-sm-4">
          <strong>Tarih & Saat:</strong><br><?php echo date('d.m.Y - H:i', strtotime($audit['audit_date'])); ?>
        </div>
        <div class="col-6 col-sm-4">
          <strong>Kategori:</strong><br><?php echo htmlspecialchars($audit['survey_cat']); ?>
        </div>
      </div>
    </div>

    <!-- Skor Skor Rozeti -->
    <div class="col-12 col-md-4 text-md-end">
      <div class="p-3 rounded-lg bg-light d-inline-block text-center shadow-sm w-100 w-md-auto">
        <div class="text-muted fs-8 font-weight-bold text-uppercase mb-1">Genel İSG Uygunluk Karnesi</div>
        <div class="display-5 fw-extrabold text-dark mb-1">%<?php echo number_format($pct, 1); ?></div>
        <div class="badge <?php echo $badgeClass; ?> p-2 px-3 fs-7 rounded-pill text-uppercase font-weight-bold">
          <?php echo $riskLevel; ?>
        </div>
        <div class="fs-8 text-muted mt-2">
          Kazanılan Puan: <strong><?php echo $audit['total_score']; ?></strong> / Maks: <strong><?php echo $audit['max_possible_score']; ?></strong>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Soru ve Seçilen Cevaplar Karnesi -->
<div class="custom-card mb-4">
  <div class="custom-card-header">
    <h5 class="custom-card-title m-0">
      <i class="bi bi-list-check text-success"></i> Denetim Detayı ve Verilen Cevaplar
    </h5>
  </div>

  <?php if (empty($groupedAnswers)): ?>
    <div class="text-muted text-center py-4">Bu denetimde kaydedilmiş bir cevap seçeneği bulunmuyor.</div>
  <?php else: ?>
    <?php $qCount = 1; foreach ($groupedAnswers as $qText => $optList): ?>
      <div class="mb-4 pb-3 border-bottom last-border-0">
        <h6 class="fw-bold text-dark mb-3">
          <span class="badge bg-secondary rounded-circle me-2"><?php echo $qCount; ?></span>
          <?php echo htmlspecialchars($qText); ?>
        </h6>

        <div class="ps-4">
          <?php foreach ($optList as $ans): ?>
            <?php
            $p = (int)$ans['points_awarded'];
            $pClass = $p > 0 ? 'bg-success-light text-success' : ($p < 0 ? 'bg-danger-light text-danger' : 'bg-light text-muted');
            $pSign = $p > 0 ? '+' : '';
            ?>
            <div class="d-flex align-items-center justify-content-between p-2 mb-2 rounded bg-light border-start border-3 border-primary">
              <div class="d-flex align-items-center gap-2">
                <i class="bi bi-check-square-fill text-success fs-5"></i>
                <span class="fw-bold text-dark fs-7"><?php echo htmlspecialchars($ans['option_text']); ?></span>
              </div>
              <span class="badge <?php echo $pClass; ?> p-2 font-weight-bold">
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
<div class="custom-card mb-4">
  <div class="custom-card-header">
    <h5 class="custom-card-title m-0">
      <i class="bi bi-card-text text-warning"></i> Denetçi Saha Notları & Gözlemler
    </h5>
  </div>
  <p class="m-0 text-dark fs-7" style="white-space: pre-line;"><?php echo htmlspecialchars($audit['notes']); ?></p>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
