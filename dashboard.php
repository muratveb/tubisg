<?php
/**
 * Tubİsg - Dashboard / Kontrol Paneli (Giriş Yapmış Kullanıcılar İçin)
 */
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Kontrol Paneli & Saha Özeti';
$db = getDB();
$user = get_current_user_data();

// 1. İstatistik Sayıları
$totalAuditsCount = $db->query("SELECT COUNT(*) FROM audits")->fetchColumn();
$activeSurveysCount = $db->query("SELECT COUNT(*) FROM survey_templates WHERE is_active = 1")->fetchColumn();
$totalUnitsCount = $db->query("SELECT COUNT(*) FROM units")->fetchColumn();
$totalRiskItemsCount = $db->query("SELECT COUNT(*) FROM audit_answers WHERE risk_score >= 6")->fetchColumn();

// 2. Son 5 Denetim (Max Risk Skoru İle)
$recentAuditsStmt = $db->query("
    SELECT a.*, u.unit_name, st.title as survey_title, usr.name_surname as auditor_name,
           (SELECT MAX(risk_score) FROM audit_answers WHERE audit_id = a.id) as max_audit_risk
    FROM audits a
    JOIN units u ON a.unit_id = u.id
    JOIN survey_templates st ON a.template_id = st.id
    JOIN users usr ON a.auditor_id = usr.id
    ORDER BY a.audit_date DESC
    LIMIT 5
");
$recentAudits = $recentAuditsStmt->fetchAll();

// 3. Birim Bazlı Denetim Özeti
$unitScoresStmt = $db->query("
    SELECT u.unit_name, COUNT(a.id) as audit_count, MAX(a.total_score) as max_unit_risk
    FROM units u
    LEFT JOIN audits a ON u.id = a.unit_id AND a.status = 'Tamamlandı'
    GROUP BY u.id
    ORDER BY audit_count DESC
");
$unitScores = $unitScoresStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Mobil & Masaüstü Hızlı Denetim Başlatma Karşılama Kartı -->
<div class="custom-card border-0 text-white mb-4 p-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #059669 100%); border-radius: 20px; box-shadow: 0 16px 32px rgba(5, 150, 105, 0.2);">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <span class="badge bg-warning text-dark font-weight-bold mb-2 px-3 py-1 rounded-pill" style="font-size:0.7rem;">
        <i class="bi bi-shield-check me-1"></i> CANLI SAHA RİSK PORTALI
      </span>
      <h3 class="fw-extrabold mb-1 text-white">Hoş Geldiniz, <?php echo htmlspecialchars($user['name_surname']); ?>! 👋</h3>
      <p class="m-0 text-light opacity-90 fs-7">Saha İSG risk denetimlerini dokunmatik telefon ve tabletinizden anında başlatıp doldurabilirsiniz.</p>
    </div>
    <?php if (has_permission('audit_conduct')): ?>
    <div class="flex-shrink-0">
      <a href="audit_new.php" class="btn btn-light text-success fw-bold py-3 px-4 rounded-pill shadow-lg d-inline-flex align-items-center gap-2">
        <i class="bi bi-play-circle-fill fs-4 text-success"></i>
        <span class="fs-6">Yeni Denetim Başlat</span>
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
    <div class="custom-card p-3 h-100 border-start border-4 border-danger">
      <div class="text-muted fs-8 text-uppercase font-weight-bold mb-1">Tespit Edilen Riskler</div>
      <div class="d-flex align-items-center justify-content-between">
        <span class="fs-2 fw-extrabold text-dark"><?php echo $totalRiskItemsCount; ?></span>
        <div class="p-2 bg-danger-light text-danger rounded-circle"><i class="bi bi-exclamation-triangle fs-4"></i></div>
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
          <a href="audits_list.php" class="btn btn-sm btn-outline-secondary rounded-pill font-weight-bold">Tümünü Gör</a>
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
                <th>İSG Risk Seviyesi</th>
                <th class="text-end">İşlem</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentAudits as $audit): ?>
                <?php
                $mRisk = (int)($audit['max_audit_risk'] ?? $audit['total_score']);
                $badgeClass = 'bg-success text-white';
                $statusText = 'Kabul Edilebilir Risk';

                if ($mRisk >= 16) {
                    $badgeClass = 'bg-danger text-white';
                    $statusText = 'Kabul Edilemez Risk';
                } elseif ($mRisk >= 10) {
                    $badgeClass = 'bg-warning text-dark';
                    $statusText = 'Dikkate Değer Risk';
                } elseif ($mRisk >= 6) {
                    $badgeClass = 'bg-info text-dark';
                    $statusText = 'Önemli Risk';
                }
                ?>
                <tr>
                  <td class="fw-bold text-dark"><?php echo htmlspecialchars($audit['unit_name']); ?></td>
                  <td class="text-muted fs-7"><?php echo htmlspecialchars($audit['survey_title']); ?></td>
                  <td class="fs-8 text-muted"><?php echo date('d.m.Y H:i', strtotime($audit['audit_date'])); ?></td>
                  <td>
                    <span class="badge <?php echo $badgeClass; ?> p-2 rounded-pill fs-8">
                      <?php echo $statusText; ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <a href="audit_detail.php?id=<?php echo $audit['id']; ?>" class="btn btn-sm btn-light text-primary rounded-circle shadow-sm" title="Risk Formunu Göster">
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

  <!-- Birim Bazlı İSG Durumları -->
  <div class="col-12 col-lg-4">
    <div class="custom-card h-100">
      <div class="custom-card-header">
        <h5 class="custom-card-title m-0">
          <i class="bi bi-building-check text-primary"></i> Birim Denetim Özeti
        </h5>
      </div>
      <div class="d-flex flex-column gap-3">
        <?php if (empty($unitScores)): ?>
          <div class="text-muted fs-8 text-center py-3">Henüz birim verisi yok.</div>
        <?php else: ?>
          <?php foreach ($unitScores as $uScore): ?>
            <div>
              <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="fw-bold fs-7 text-dark"><?php echo htmlspecialchars($uScore['unit_name']); ?></span>
                <span class="fs-8 badge bg-light text-dark border"><?php echo $uScore['audit_count']; ?> denetim</span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
