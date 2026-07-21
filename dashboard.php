<?php
/**
 * Tubİsg - Dashboard / Kontrol Paneli (Giriş Yapmış Kullanıcılar İçin)
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Kontrol Paneli & Saha Özeti';
$db = getDB();

// 1. İstatistik Sayıları
$totalAuditsCount = $db->query("SELECT COUNT(*) FROM audits")->fetchColumn();
$activeSurveysCount = $db->query("SELECT COUNT(*) FROM survey_templates WHERE is_active = 1")->fetchColumn();
$totalUnitsCount = $db->query("SELECT COUNT(*) FROM units")->fetchColumn();
$avgScore = $db->query("SELECT AVG(percentage_score) FROM audits WHERE status = 'Tamamlandı'")->fetchColumn();
$avgScoreFormatted = $avgScore !== null ? number_format((float)$avgScore, 1) : '0';

// 2. Son 5 Denetim
$recentAuditsStmt = $db->query("
    SELECT a.*, u.unit_name, st.title as survey_title, usr.name_surname as auditor_name
    FROM audits a
    JOIN units u ON a.unit_id = u.id
    JOIN survey_templates st ON a.template_id = st.id
    JOIN users usr ON a.auditor_id = usr.id
    ORDER BY a.audit_date DESC
    LIMIT 5
");
$recentAudits = $recentAuditsStmt->fetchAll();

// 3. Birim Bazlı Ortalama Skor Özeti
$unitScoresStmt = $db->query("
    SELECT u.unit_name, COUNT(a.id) as audit_count, AVG(a.percentage_score) as avg_unit_score
    FROM units u
    LEFT JOIN audits a ON u.id = a.unit_id AND a.status = 'Tamamlandı'
    GROUP BY u.id
    ORDER BY avg_unit_score DESC
");
$unitScores = $unitScoresStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Mobil / Tablet Hızlı Başlatma Kartı -->
<div class="custom-card border-0 text-white mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #059669 100%);">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <span class="badge bg-warning text-dark font-weight-bold mb-2">SAHA DENETİMİ</span>
      <h3 class="fw-extrabold mb-1">Hoş Geldiniz, <?php echo htmlspecialchars($user['name_surname']); ?>! 👋</h3>
      <p class="m-0 text-light opacity-90 fs-7">Saha İSG kontrollerini telefon veya tabletinizden anında başlatabilirsiniz.</p>
    </div>
    <?php if (has_permission('audit_conduct')): ?>
    <div>
      <a href="audit_new.php" class="btn btn-light text-success fw-bold py-3 px-4 rounded-pill shadow-lg d-inline-flex align-items-center gap-2">
        <i class="bi bi-play-circle-fill fs-4"></i>
        <span>Yeni Denetim Başlat</span>
      </a>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Metrik / Stat Kartları -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="custom-card p-3 h-100 border-start border-4 border-success">
      <div class="text-muted fs-8 text-uppercase font-weight-bold mb-1">Toplam Denetim</div>
      <div class="d-flex align-items-center justify-content-between">
        <span class="fs-2 fw-extrabold text-dark"><?php echo $totalAuditsCount; ?></span>
        <div class="p-2 bg-success-light text-success rounded-circle"><i class="bi bi-clipboard-check fs-4"></i></div>
      </div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="custom-card p-3 h-100 border-start border-4 border-info">
      <div class="text-muted fs-8 text-uppercase font-weight-bold mb-1">Genel Uygunluk</div>
      <div class="d-flex align-items-center justify-content-between">
        <span class="fs-2 fw-extrabold text-dark">%<?php echo $avgScoreFormatted; ?></span>
        <div class="p-2 bg-info-light text-info rounded-circle"><i class="bi bi-graph-up-arrow fs-4"></i></div>
      </div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="custom-card p-3 h-100 border-start border-4 border-primary">
      <div class="text-muted fs-8 text-uppercase font-weight-bold mb-1">Aktif Anketler</div>
      <div class="d-flex align-items-center justify-content-between">
        <span class="fs-2 fw-extrabold text-dark"><?php echo $activeSurveysCount; ?></span>
        <div class="p-2 bg-primary-light text-primary rounded-circle"><i class="bi bi-journal-text fs-4"></i></div>
      </div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="custom-card p-3 h-100 border-start border-4 border-warning">
      <div class="text-muted fs-8 text-uppercase font-weight-bold mb-1">Kayıtlı Birimler</div>
      <div class="d-flex align-items-center justify-content-between">
        <span class="fs-2 fw-extrabold text-dark"><?php echo $totalUnitsCount; ?></span>
        <div class="p-2 bg-warning-light text-warning rounded-circle"><i class="bi bi-building fs-4"></i></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Son Yapılan Saha Denetimleri -->
  <div class="col-12 col-lg-8">
    <div class="custom-card h-100">
      <div class="custom-card-header">
        <h5 class="custom-card-title m-0">
          <i class="bi bi-clock-history text-success"></i> Son Saha Denetimleri
        </h5>
        <?php if (has_permission('audit_view')): ?>
          <a href="audits_list.php" class="btn btn-sm btn-outline-secondary rounded-pill">Tümünü Gör</a>
        <?php endif; ?>
      </div>

      <?php if (empty($recentAudits)): ?>
        <div class="text-center py-4 text-muted">
          <i class="bi bi-inbox fs-1 d-block mb-2 text-light opacity-50"></i>
          Henüz yapılmış bir denetim bulunmuyor.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle m-0">
            <thead class="table-light fs-8 text-uppercase text-muted">
              <tr>
                <th>Birim</th>
                <th>Anket</th>
                <th>Tarih</th>
                <th>Skor</th>
                <th>İşlem</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentAudits as $audit): ?>
                <?php
                $pct = (float)$audit['percentage_score'];
                $badgeClass = $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning text-dark' : 'bg-danger');
                ?>
                <tr>
                  <td class="fw-bold"><?php echo htmlspecialchars($audit['unit_name']); ?></td>
                  <td class="text-muted fs-7"><?php echo htmlspecialchars($audit['survey_title']); ?></td>
                  <td class="fs-8 text-muted"><?php echo date('d.m.Y H:i', strtotime($audit['audit_date'])); ?></td>
                  <td>
                    <span class="badge <?php echo $badgeClass; ?> p-2 rounded-pill fs-8">
                      %<?php echo number_format($pct, 0); ?> Uygun
                    </span>
                  </td>
                  <td>
                    <a href="audit_detail.php?id=<?php echo $audit['id']; ?>" class="btn btn-sm btn-light rounded-circle shadow-sm" title="Detay">
                      <i class="bi bi-chevron-right"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Birim Bazlı İSG Uygunluk Durumları -->
  <div class="col-12 col-lg-4">
    <div class="custom-card h-100">
      <div class="custom-card-header">
        <h5 class="custom-card-title m-0">
          <i class="bi bi-building-check text-primary"></i> Birim Skorları
        </h5>
      </div>
      <div class="d-flex flex-column gap-3">
        <?php foreach ($unitScores as $uScore): ?>
          <?php
          $uAvg = $uScore['avg_unit_score'] !== null ? round((float)$uScore['avg_unit_score']) : 0;
          $uColor = $uAvg >= 80 ? '#10b981' : ($uAvg >= 50 ? '#f59e0b' : '#ef4444');
          ?>
          <div>
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="fw-bold fs-7"><?php echo htmlspecialchars($uScore['unit_name']); ?></span>
              <span class="fs-8 text-muted"><?php echo $uScore['audit_count']; ?> denetim / %<?php echo $uAvg; ?></span>
            </div>
            <div class="progress" style="height: 8px; border-radius: 4px; background:#e2e8f0;">
              <div class="progress-bar" role="progressbar" style="width: <?php echo $uAvg; ?>%; background: <?php echo $uColor; ?>;" aria-valuenow="<?php echo $uAvg; ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
