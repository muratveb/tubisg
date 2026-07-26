<?php
/**
 * Tubİsg - Dashboard / Kontrol Paneli (Giriş Yapmış Kullanıcılar İçin)
 * Ultra-Modern SaaS Executive Dashboard UI
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
$totalInstitutionsCount = $db->query("SELECT COUNT(*) FROM institutions")->fetchColumn();
$totalRiskItemsCount = $db->query("SELECT COUNT(*) FROM audit_answers WHERE risk_score >= 6")->fetchColumn();

// 2. Canlı İSG Risk Seviyesi Dağılımı (Olasılık x Şiddet)
$riskMatrixDistribution = $db->query("
    SELECT 
      SUM(CASE WHEN risk_score <= 5 THEN 1 ELSE 0 END) as low_risk_count,
      SUM(CASE WHEN risk_score >= 6 AND risk_score <= 9 THEN 1 ELSE 0 END) as med_risk_count,
      SUM(CASE WHEN risk_score >= 10 AND risk_score <= 15 THEN 1 ELSE 0 END) as high_risk_count,
      SUM(CASE WHEN risk_score >= 16 THEN 1 ELSE 0 END) as crit_risk_count
    FROM audit_answers
")->fetch();

$lowRisk = (int)($riskMatrixDistribution['low_risk_count'] ?? 0);
$medRisk = (int)($riskMatrixDistribution['med_risk_count'] ?? 0);
$highRisk = (int)($riskMatrixDistribution['high_risk_count'] ?? 0);
$critRisk = (int)($riskMatrixDistribution['crit_risk_count'] ?? 0);
$totalAnswersCount = $lowRisk + $medRisk + $highRisk + $critRisk;

// 3. Son 5 Denetim (Kurum Adı & Max Risk Skoru İle)
$recentAuditsStmt = $db->query("
    SELECT a.*, inst.institution_name, u.unit_name, st.title as survey_title, usr.name_surname as auditor_name,
           (SELECT MAX(risk_score) FROM audit_answers WHERE audit_id = a.id) as max_audit_risk
    FROM audits a
    LEFT JOIN institutions inst ON a.institution_id = inst.id
    JOIN units u ON a.unit_id = u.id
    JOIN survey_templates st ON a.template_id = st.id
    JOIN users usr ON a.auditor_id = usr.id
    ORDER BY a.audit_date DESC
    LIMIT 5
");
$recentAudits = $recentAuditsStmt->fetchAll();

// 4. Birim Bazlı Denetim Özeti
$unitScoresStmt = $db->query("
    SELECT u.unit_name, COUNT(a.id) as audit_count, MAX(a.total_score) as max_unit_risk
    FROM units u
    LEFT JOIN audits a ON u.id = a.unit_id AND a.status = 'Tamamlandı'
    GROUP BY u.id
    ORDER BY audit_count DESC
    LIMIT 6
");
$unitScores = $unitScoresStmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<style>
.dash-stat-card {
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  padding: 20px;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04);
}
.dash-stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px -4px rgba(0, 0, 0, 0.08);
  border-color: #cbd5e1;
}
.dash-icon-avatar {
  width: 52px;
  height: 52px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
</style>

<!-- Mobil & Masaüstü Hızlı Denetim Başlatma Karşılama Kartı -->
<div class="custom-card border-0 text-white mb-4 p-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #059669 100%); border-radius: 20px; box-shadow: 0 16px 32px rgba(5, 150, 105, 0.2);">
  <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="badge bg-warning text-dark font-weight-bold px-3 py-1 rounded-pill" style="font-size:0.7rem;">
          <i class="bi bi-shield-check me-1"></i> CANLI SAHA RİSK PORTALI
        </span>
        <span class="badge bg-white bg-opacity-20 text-white font-weight-bold px-3 py-1 rounded-pill" style="font-size:0.7rem;">
          <i class="bi bi-calendar3 me-1"></i> <?php echo date('d.m.Y'); ?>
        </span>
      </div>
      <h3 class="fw-extrabold mb-1 text-white">Hoş Geldiniz, <?php echo htmlspecialchars($user['name_surname']); ?>! 👋</h3>
      <p class="m-0 text-light opacity-90 fs-7">Saha İSG risk denetimlerini mobil cihazınızdan veya bilgisayarınızdan anında başlatıp takip edebilirsiniz.</p>
    </div>
    
    <div class="d-flex align-items-center gap-2 flex-wrap flex-shrink-0">
      <?php if (has_permission('audit_conduct')): ?>
        <a href="audit_new.php" class="btn btn-light text-success fw-bold py-2.5 px-4 rounded-pill shadow-lg d-inline-flex align-items-center gap-2">
          <i class="bi bi-plus-circle-fill fs-5 text-success"></i>
          <span class="fs-7">Yeni Denetim Başlat</span>
        </a>
      <?php endif; ?>
      <?php if (has_permission('audit_view')): ?>
        <a href="audits_list.php" class="btn btn-outline-light font-weight-bold py-2.5 px-3 rounded-pill fs-7">
          <i class="bi bi-file-earmark-pdf me-1"></i> Raporlar
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- 4 Metrik / Stat Kartları (Visual Soft Gradient Cards) -->
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-xl-3">
    <div class="dash-stat-card h-100 d-flex align-items-center justify-content-between gap-3" style="background: linear-gradient(135deg, #ffffff 0%, #f0fdf4 100%);">
      <div>
        <span class="d-block text-uppercase text-muted font-weight-bold fs-8" style="letter-spacing: 0.5px;">TOPLAM DENETİM</span>
        <div class="fw-extrabold fs-2 text-dark my-0.5"><?php echo $totalAuditsCount; ?></div>
        <span class="text-success fs-8 font-weight-bold"><i class="bi bi-check-circle-fill me-1"></i> Tamamlanan Sahalar</span>
      </div>
      <div class="dash-icon-avatar bg-success text-white">
        <i class="bi bi-clipboard2-check-fill"></i>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <div class="dash-stat-card h-100 d-flex align-items-center justify-content-between gap-3" style="background: linear-gradient(135deg, #ffffff 0%, #fef2f2 100%);">
      <div>
        <span class="d-block text-uppercase text-muted font-weight-bold fs-8" style="letter-spacing: 0.5px;">TESPİT EDİLEN RİSKLER</span>
        <div class="fw-extrabold fs-2 text-danger my-0.5"><?php echo $totalRiskItemsCount; ?></div>
        <span class="text-danger fs-8 font-weight-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Önlem Gerektiren</span>
      </div>
      <div class="dash-icon-avatar bg-danger text-white">
        <i class="bi bi-shield-slash-fill"></i>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <div class="dash-stat-card h-100 d-flex align-items-center justify-content-between gap-3" style="background: linear-gradient(135deg, #ffffff 0%, #f0f9ff 100%);">
      <div>
        <span class="d-block text-uppercase text-muted font-weight-bold fs-8" style="letter-spacing: 0.5px;">AKTİF ANKETLER</span>
        <div class="fw-extrabold fs-2 text-primary my-0.5"><?php echo $activeSurveysCount; ?></div>
        <span class="text-primary fs-8 font-weight-bold"><i class="bi bi-journal-text me-1"></i> Şablon Portföyü</span>
      </div>
      <div class="dash-icon-avatar bg-primary text-white">
        <i class="bi bi-journal-check"></i>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-xl-3">
    <div class="dash-stat-card h-100 d-flex align-items-center justify-content-between gap-3" style="background: linear-gradient(135deg, #ffffff 0%, #fffbeb 100%);">
      <div>
        <span class="d-block text-uppercase text-muted font-weight-bold fs-8" style="letter-spacing: 0.5px;">KAYITLI BİRİMLER</span>
        <div class="fw-extrabold fs-2 text-warning-emphasis my-0.5"><?php echo $totalUnitsCount; ?></div>
        <span class="text-muted fs-8 font-weight-bold"><i class="bi bi-hospital me-1"></i> <?php echo $totalInstitutionsCount; ?> Kuruma Bağlı</span>
      </div>
      <div class="dash-icon-avatar bg-warning text-dark">
        <i class="bi bi-building"></i>
      </div>
    </div>
  </div>
</div>

<!-- CANLI İSG RİSK MATRİSİ DAĞILIMI ÖZETİ (Visual Matrix Metric Widget - Clean Turkish Labels) -->
<?php if ($totalAnswersCount > 0): ?>
<div class="custom-card p-3 mb-4 bg-white border-0 shadow-sm rounded-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <h6 class="fw-extrabold text-dark m-0 fs-7">
      <i class="bi bi-bar-chart-steps text-primary me-1"></i> Saha İSG Risk Seviyesi Genel Dağılımı
    </h6>
    <span class="text-muted fs-8 font-weight-bold">Toplam <?php echo $totalAnswersCount; ?> Risk Değerlendirmesi</span>
  </div>

  <div class="progress mb-3" style="height: 12px; border-radius: 8px; overflow: hidden; background: #e2e8f0;">
    <?php
    $pLow = round(($lowRisk / $totalAnswersCount) * 100, 1);
    $pMed = round(($medRisk / $totalAnswersCount) * 100, 1);
    $pHigh = round(($highRisk / $totalAnswersCount) * 100, 1);
    $pCrit = round(($critRisk / $totalAnswersCount) * 100, 1);
    ?>
    <div class="progress-bar bg-success" style="width: <?php echo $pLow; ?>%" title="Kabul Edilebilir Risk: <?php echo $lowRisk; ?>"></div>
    <div class="progress-bar bg-info text-dark" style="width: <?php echo $pMed; ?>%" title="Önemli Risk: <?php echo $medRisk; ?>"></div>
    <div class="progress-bar bg-warning text-dark" style="width: <?php echo $pHigh; ?>%" title="Dikkate Değer Risk: <?php echo $highRisk; ?>"></div>
    <div class="progress-bar bg-danger" style="width: <?php echo $pCrit; ?>%" title="Kabul Edilemez Risk: <?php echo $critRisk; ?>"></div>
  </div>

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 fs-8">
    <span class="text-success font-weight-bold"><i class="bi bi-circle-fill me-1"></i> Kabul Edilebilir Risk: <strong><?php echo $lowRisk; ?></strong> (%<?php echo $pLow; ?>)</span>
    <span class="text-info-emphasis font-weight-bold"><i class="bi bi-circle-fill me-1 text-info"></i> Önemli Risk: <strong><?php echo $medRisk; ?></strong> (%<?php echo $pMed; ?>)</span>
    <span class="text-warning-emphasis font-weight-bold"><i class="bi bi-circle-fill me-1 text-warning"></i> Dikkate Değer Risk: <strong><?php echo $highRisk; ?></strong> (%<?php echo $pHigh; ?>)</span>
    <span class="text-danger font-weight-bold"><i class="bi bi-circle-fill me-1"></i> Kabul Edilemez Risk: <strong><?php echo $critRisk; ?></strong> (%<?php echo $pCrit; ?>)</span>
  </div>
</div>
<?php endif; ?>

<!-- İKİ SÜTUNLU ALT İÇERİK: SON DENETİMLER & BİRİM ÖZETİ -->
<div class="row g-4 mb-4">
  <!-- Son Yapılan Saha Denetimleri -->
  <div class="col-12 col-lg-8">
    <div class="custom-card p-0 overflow-hidden border-0 shadow-sm rounded-4 h-100">
      <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between">
        <h6 class="fw-extrabold text-dark m-0 fs-7">
          <i class="bi bi-clock-history text-success me-1"></i> Son Saha Denetimleri
        </h6>
        <?php if (has_permission('audit_view')): ?>
          <a href="audits_list.php" class="btn btn-sm btn-outline-secondary rounded-pill font-weight-bold fs-8">Tümünü Gör &rarr;</a>
        <?php endif; ?>
      </div>

      <?php if (empty($recentAudits)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
          Henüz yapılmış bir saha denetimi bulunmuyor.
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle m-0" style="font-size: 0.85rem;">
            <thead class="table-dark">
              <tr>
                <th class="ps-3">DENETLENEN KURUM & BİRİM</th>
                <th>ANKET PROFİLİ</th>
                <th>DENETİM TARİHİ</th>
                <th class="text-center">İSG RİSK SEVİYESİ</th>
                <th class="text-end pe-3">RAPOR</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentAudits as $audit): ?>
                <?php
                $mRisk = (int)($audit['max_audit_risk'] ?? $audit['total_score']);
                if ($mRisk >= 16) {
                    $badgeClass = 'bg-danger text-white';
                    $statusText = 'Kabul Edilemez Risk';
                } elseif ($mRisk >= 10) {
                    $badgeClass = 'bg-warning text-dark';
                    $statusText = 'Dikkate Değer Risk';
                } elseif ($mRisk >= 6) {
                    $badgeClass = 'bg-info text-dark';
                    $statusText = 'Önemli Risk';
                } else {
                    $badgeClass = 'bg-success text-white';
                    $statusText = 'Kabul Edilebilir Risk';
                }
                ?>
                <tr>
                  <td class="ps-3">
                    <?php if (!empty($audit['institution_name'])): ?>
                      <span class="badge bg-danger-subtle text-danger font-weight-bold fs-8 mb-0.5">
                        <i class="bi bi-hospital me-1"></i><?php echo htmlspecialchars($audit['institution_name']); ?>
                      </span><br>
                    <?php endif; ?>
                    <span class="fw-extrabold text-dark fs-7"><i class="bi bi-building text-primary me-1"></i><?php echo htmlspecialchars($audit['unit_name']); ?></span>
                  </td>
                  <td>
                    <span class="badge bg-light text-dark border font-weight-bold fs-8"><?php echo htmlspecialchars($audit['survey_title']); ?></span>
                  </td>
                  <td class="fs-8 text-muted font-weight-bold">
                    <?php echo date('d.m.Y - H:i', strtotime($audit['audit_date'])); ?>
                  </td>
                  <td class="text-center">
                    <span class="badge <?php echo $badgeClass; ?> px-2.5 py-1.5 rounded-pill fs-8 font-weight-bold">
                      <?php echo $statusText; ?>
                    </span>
                  </td>
                  <td class="text-end pe-3">
                    <a href="audit_detail.php?id=<?php echo $audit['id']; ?>" class="btn btn-sm btn-outline-primary rounded-circle shadow-xs" title="Risk Karnesini İncele">
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
    <div class="custom-card p-0 overflow-hidden border-0 shadow-sm rounded-4 h-100">
      <div class="p-3 bg-light border-bottom">
        <h6 class="fw-extrabold text-dark m-0 fs-7">
          <i class="bi bi-building-check text-primary me-1"></i> Birim Denetim Özeti
        </h6>
      </div>

      <div class="p-3">
        <div class="d-flex flex-column gap-2">
          <?php if (empty($unitScores)): ?>
            <div class="text-muted fs-8 text-center py-4">Henüz birim verisi bulunmuyor.</div>
          <?php else: ?>
            <?php foreach ($unitScores as $uScore): ?>
              <div class="p-2.5 rounded-3 bg-light border d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-building text-primary"></i>
                  <span class="fw-bold fs-8 text-dark"><?php echo htmlspecialchars($uScore['unit_name']); ?></span>
                </div>
                <span class="badge bg-info-subtle text-info-emphasis font-weight-bold px-2.5 py-1 rounded-pill fs-8">
                  <?php echo $uScore['audit_count']; ?> denetim
                </span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <?php if (has_permission('units_manage')): ?>
          <div class="mt-3 pt-2 text-center border-top">
            <a href="units.php" class="text-decoration-none fs-8 font-weight-bold text-primary">Tüm Birimleri Yönet &rarr;</a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
