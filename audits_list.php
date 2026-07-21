<?php
/**
 * Tubİsg - Tamamlanan Saha Denetimleri Listesi & Filtreleme
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('audit_view');

$db = getDB();

$unitFilter = (int)($_GET['unit_id'] ?? 0);
$templateFilter = (int)($_GET['template_id'] ?? 0);

$query = "
    SELECT a.*, u.unit_name, st.title as survey_title, usr.name_surname as auditor_name
    FROM audits a
    JOIN units u ON a.unit_id = u.id
    JOIN survey_templates st ON a.template_id = st.id
    JOIN users usr ON a.auditor_id = usr.id
    WHERE 1=1
";
$params = [];

if ($unitFilter > 0) {
    $query .= " AND a.unit_id = ?";
    $params[] = $unitFilter;
}

if ($templateFilter > 0) {
    $query .= " AND a.template_id = ?";
    $params[] = $templateFilter;
}

$query .= " ORDER BY a.audit_date DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$audits = $stmt->fetchAll();

$units = $db->query("SELECT * FROM units ORDER BY unit_name ASC")->fetchAll();
$templates = $db->query("SELECT * FROM survey_templates ORDER BY title ASC")->fetchAll();

$pageTitle = 'Saha Denetim Raporları';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h3 class="fw-extrabold m-0">Saha Denetim Raporları</h3>
    <p class="text-muted fs-7 m-0">Tamamlanan saha denetimleri, skor karneleri ve rapor ihracatı</p>
  </div>
  <?php if (has_permission('audit_conduct')): ?>
    <a href="audit_new.php" class="btn btn-primary-custom">
      <i class="bi bi-plus-circle-fill"></i> Yeni Denetim
    </a>
  <?php endif; ?>
</div>

<!-- Filtreleme Kartı -->
<div class="custom-card mb-4 p-3 bg-light">
  <form method="GET" action="audits_list.php" class="row g-3 align-items-end">
    <div class="col-12 col-md-5">
      <label class="form-label fs-8 text-uppercase font-weight-bold text-muted">Birim Filtresi</label>
      <select name="unit_id" class="form-select">
        <option value="0">Tüm Birimler</option>
        <?php foreach ($units as $u): ?>
          <option value="<?php echo $u['id']; ?>" <?php echo $unitFilter == $u['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($u['unit_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-5">
      <label class="form-label fs-8 text-uppercase font-weight-bold text-muted">Anket Profili Filtresi</label>
      <select name="template_id" class="form-select">
        <option value="0">Tüm Anket Profilleri</option>
        <?php foreach ($templates as $tpl): ?>
          <option value="<?php echo $tpl['id']; ?>" <?php echo $templateFilter == $tpl['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($tpl['title']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-2">
      <button type="submit" class="btn btn-dark w-100 fw-bold">
        <i class="bi bi-filter"></i> Filtrele
      </button>
    </div>
  </form>
</div>

<div class="custom-card">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0">
      <thead class="table-light fs-8 text-uppercase text-muted">
        <tr>
          <th>Denetim ID</th>
          <th>Birim / Saha</th>
          <th>Anket Profili</th>
          <th>Denetçi</th>
          <th>Tarih</th>
          <th>Puan / Skor</th>
          <th>Uygunluk Seviyesi</th>
          <th class="text-end">İşlemler</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($audits)): ?>
          <tr>
            <td colspan="8" class="text-center py-4 text-muted">Filtrelere uygun denetim kaydı bulunamadı.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($audits as $audit): ?>
            <?php
            $pct = (float)$audit['percentage_score'];
            $badgeClass = $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning text-dark' : 'bg-danger');
            $statusText = $pct >= 80 ? 'Düşük Risk / Uygun' : ($pct >= 50 ? 'Orta Risk' : 'Yüksek Risk');
            ?>
            <tr>
              <td class="fw-bold text-muted">#DEN-<?php echo sprintf('%04d', $audit['id']); ?></td>
              <td class="fw-bold text-dark"><?php echo htmlspecialchars($audit['unit_name']); ?></td>
              <td class="text-muted fs-7"><?php echo htmlspecialchars($audit['survey_title']); ?></td>
              <td class="fs-7"><i class="bi bi-person text-muted"></i> <?php echo htmlspecialchars($audit['auditor_name']); ?></td>
              <td class="fs-8 text-muted"><?php echo date('d.m.Y H:i', strtotime($audit['audit_date'])); ?></td>
              <td class="fw-bold"><?php echo $audit['total_score']; ?> / <?php echo $audit['max_possible_score']; ?></td>
              <td>
                <span class="badge <?php echo $badgeClass; ?> p-2 rounded-pill fs-8">
                  %<?php echo number_format($pct, 0); ?> - <?php echo $statusText; ?>
                </span>
              </td>
              <td class="text-end">
                <a href="audit_detail.php?id=<?php echo $audit['id']; ?>" class="btn btn-sm btn-outline-primary me-1" title="Karneli İncele">
                  <i class="bi bi-eye-fill"></i> Detay
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
