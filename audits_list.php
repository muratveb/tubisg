<?php
/**
 * Tubİsg - Tamamlanan Saha Denetimleri Listesi & Filtreleme
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('audit_view');

$db = getDB();

// Denetim Silme İşlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_audit') {
    require_permission('audit_delete');
    $audit_id = (int)($_POST['audit_id'] ?? 0);
    if ($audit_id > 0) {
        $stmt = $db->prepare("DELETE FROM audits WHERE id = ?");
        $stmt->execute([$audit_id]);
        log_action('Denetim Raporu Silindi', "Denetim ID #DEN-" . sprintf('%04d', $audit_id) . " silindi.");
        set_flash('success', "Denetim kaydı (#DEN-" . sprintf('%04d', $audit_id) . ") başarıyla silindi.");
    }
    header("Location: audits_list.php");
    exit;
}

// Filtre Değişkenleri
$unit_id = (int)($_GET['unit_id'] ?? 0);
$template_id = (int)($_GET['template_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

// SQL Sorgusu Oluşturma
$sql = "
    SELECT a.*, u.unit_name, st.title as survey_title, usr.name_surname as auditor_name
    FROM audits a
    JOIN units u ON a.unit_id = u.id
    JOIN survey_templates st ON a.template_id = st.id
    JOIN users usr ON a.auditor_id = usr.id
    WHERE 1=1
";
$params = [];

if ($unit_id > 0) {
    $sql .= " AND a.unit_id = ?";
    $params[] = $unit_id;
}

if ($template_id > 0) {
    $sql .= " AND a.template_id = ?";
    $params[] = $template_id;
}

if (!empty($search)) {
    $sql .= " AND (u.unit_name LIKE ? OR st.title LIKE ? OR usr.name_surname LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY a.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$audits = $stmt->fetchAll();

// Filtre Seçenekleri İçin Verileri Çek
$units = $db->query("SELECT * FROM units ORDER BY unit_name ASC")->fetchAll();
$templates = $db->query("SELECT * FROM survey_templates ORDER BY title ASC")->fetchAll();

$pageTitle = 'Saha Denetim Raporları';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div>
    <h3 class="fw-extrabold m-0">Denetim Raporları</h3>
    <p class="text-muted fs-7 m-0">Tamamlanan saha denetimleri, karne skorları ve dışa aktarma seçenekleri</p>
  </div>
  <?php if (has_permission('audit_conduct')): ?>
    <a href="audit_new.php" class="btn btn-primary-custom">
      <i class="bi bi-plus-circle-fill"></i> Yeni Saha Denetimi
    </a>
  <?php endif; ?>
</div>

<!-- Filtreleme Paneli -->
<div class="custom-card mb-4">
  <form method="GET" action="audits_list.php" class="row g-3 align-items-end">
    <div class="col-12 col-md-4">
      <label class="form-label fw-bold fs-8 text-muted">Arama (Birim, Anket, Denetçi)</label>
      <input type="text" name="search" class="form-control" placeholder="Örn: Faturalama, Hastane..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    
    <div class="col-12 col-sm-6 col-md-3">
      <label class="form-label fw-bold fs-8 text-muted">Birim Filtresi</label>
      <select name="unit_id" class="form-select">
        <option value="0">-- Tüm Birimler --</option>
        <?php foreach ($units as $u): ?>
          <option value="<?php echo $u['id']; ?>" <?php echo $unit_id == $u['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($u['unit_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <label class="form-label fw-bold fs-8 text-muted">Anket Profili</label>
      <select name="template_id" class="form-select">
        <option value="0">-- Tüm Profiller --</option>
        <?php foreach ($templates as $t): ?>
          <option value="<?php echo $t['id']; ?>" <?php echo $template_id == $t['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($t['title']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-dark w-100 font-weight-bold">
        <i class="bi bi-filter"></i> Filtrele
      </button>
      <?php if ($unit_id > 0 || $template_id > 0 || !empty($search)): ?>
        <a href="audits_list.php" class="btn btn-outline-secondary" title="Filtreleri Temizle">
          <i class="bi bi-x-lg"></i>
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Denetimler Tablosu -->
<div class="custom-card">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0">
      <thead class="table-light fs-8 text-uppercase text-muted">
        <tr>
          <th>Denetim No</th>
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

                <?php if (has_permission('audit_delete')): ?>
                  <form method="POST" action="audits_list.php" class="d-inline" onsubmit="return confirm('Bu denetim kaydını tamamen silmek istediğinize emin misiniz?');">
                    <input type="hidden" name="action" value="delete_audit">
                    <input type="hidden" name="audit_id" value="<?php echo $audit['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Denetimi Sil">
                      <i class="bi bi-trash-fill"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
