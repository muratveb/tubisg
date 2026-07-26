<?php
/**
 * Tubİsg - Tamamlanan Saha Denetimleri Listesi & İSG Risk Matris Filtreleme
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
$institution_id = (int)($_GET['institution_id'] ?? 0);
$unit_id = (int)($_GET['unit_id'] ?? 0);
$template_id = (int)($_GET['template_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

// Sayfalama (Pagination) Parametreleri
$per_page = (int)($_GET['per_page'] ?? 10);
if (!in_array($per_page, [10, 25, 50, 100])) {
    $per_page = 10;
}
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

// SQL Sorgusu Oluşturma
$whereSql = " WHERE 1=1";
$params = [];

if ($institution_id > 0) {
    $whereSql .= " AND a.institution_id = ?";
    $params[] = $institution_id;
}

if ($unit_id > 0) {
    $whereSql .= " AND a.unit_id = ?";
    $params[] = $unit_id;
}

if ($template_id > 0) {
    $whereSql .= " AND a.template_id = ?";
    $params[] = $template_id;
}

if (!empty($search)) {
    $whereSql .= " AND (u.unit_name LIKE ? OR st.title LIKE ? OR usr.name_surname LIKE ? OR inst.institution_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Toplam Kayıt Sayısı
$countSql = "
    SELECT COUNT(*) as total
    FROM audits a
    LEFT JOIN institutions inst ON a.institution_id = inst.id
    JOIN units u ON a.unit_id = u.id
    JOIN survey_templates st ON a.template_id = st.id
    JOIN users usr ON a.auditor_id = usr.id
    $whereSql
";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetch()['total'];

$totalPages = ceil($totalRecords / $per_page);
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $per_page;
if ($offset < 0) $offset = 0;

// Ana Veri Sorgusu (Max Risk Skoru İle)
$sql = "
    SELECT a.*, inst.institution_name, inst.code as inst_code, u.unit_name, st.title as survey_title, usr.name_surname as auditor_name,
           (SELECT MAX(risk_score) FROM audit_answers WHERE audit_id = a.id) as max_audit_risk
    FROM audits a
    LEFT JOIN institutions inst ON a.institution_id = inst.id
    JOIN units u ON a.unit_id = u.id
    JOIN survey_templates st ON a.template_id = st.id
    JOIN users usr ON a.auditor_id = usr.id
    $whereSql
    ORDER BY a.id DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$audits = $stmt->fetchAll();

// Filtre Seçenekleri İçin Verileri Çek
$allInstitutions = $db->query("SELECT * FROM institutions ORDER BY institution_name ASC")->fetchAll();
$units = $db->query("SELECT * FROM units ORDER BY unit_name ASC")->fetchAll();
$templates = $db->query("SELECT * FROM survey_templates ORDER BY title ASC")->fetchAll();

$selectedInstName = '';
if ($institution_id > 0) {
    foreach ($allInstitutions as $i) {
        if ($i['id'] == $institution_id) {
            $selectedInstName = $i['institution_name'];
            break;
        }
    }
}

$pageTitle = 'Saha Denetim Raporları';
include __DIR__ . '/includes/header.php';
?>

<!-- Üst Başlık Barı -->
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
  <div>
    <h3 class="fw-extrabold m-0 text-dark">Saha Denetim Raporları</h3>
    <p class="text-muted fs-7 m-0 mt-1">Tamamlanan İSG saha denetimleri, birim risk analizi sonuçları ve karneleri.</p>
  </div>

  <?php if (has_permission('audit_conduct')): ?>
  <div>
    <a href="audit_new.php" class="btn btn-success font-weight-bold px-4 py-2.5 shadow-sm rounded-3">
      <i class="bi bi-plus-circle-fill me-1"></i> Yeni Saha Denetimi Başlat
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- Filtrelenen Kurum Rozet Bildirimi -->
<?php if (!empty($selectedInstName)): ?>
<div class="alert alert-danger d-flex align-items-center justify-content-between p-3 rounded-4 mb-4 shadow-xs">
  <div class="d-flex align-items-center gap-2">
    <i class="bi bi-hospital fs-4 text-danger"></i>
    <div>
      <strong class="text-danger">FİLTRELENEN KURUM:</strong>
      <span class="fw-extrabold text-dark ms-1"><?php echo htmlspecialchars($selectedInstName); ?></span>
      <span class="badge bg-danger ms-2"><?php echo $totalRecords; ?> Kayıt Bulundu</span>
    </div>
  </div>
  <a href="audits_list.php" class="btn btn-sm btn-outline-danger font-weight-bold rounded-pill px-3">
    <i class="bi bi-x-circle-fill me-1"></i> Filtreyi Temizle
  </a>
</div>
<?php endif; ?>

<!-- Detaylı Filtreleme Kartı -->
<div class="custom-card p-3 mb-4 bg-white border-0 shadow-sm rounded-4">
  <form method="GET" action="audits_list.php" class="row g-2 align-items-center">
    
    <!-- Kurum Filtresi -->
    <div class="col-12 col-md-3">
      <label class="form-label fw-bold fs-8 text-muted mb-1"><i class="bi bi-hospital text-danger me-1"></i> Kurum</label>
      <select name="institution_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="0">Tüm Kurumlar (Tümü)</option>
        <?php foreach ($allInstitutions as $inst): ?>
          <option value="<?php echo $inst['id']; ?>" <?php echo $institution_id == $inst['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($inst['institution_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Birim / Saha Filtresi -->
    <div class="col-12 col-md-3">
      <label class="form-label fw-bold fs-8 text-muted mb-1"><i class="bi bi-building me-1"></i> Birim / Saha</label>
      <select name="unit_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="0">Tüm Birimler (Tümü)</option>
        <?php foreach ($units as $u): ?>
          <option value="<?php echo $u['id']; ?>" <?php echo $unit_id == $u['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($u['unit_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Anket Şablon Filtresi -->
    <div class="col-12 col-md-3">
      <label class="form-label fw-bold fs-8 text-muted mb-1"><i class="bi bi-journal-text me-1"></i> Anket Profili</label>
      <select name="template_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="0">Tüm Anket Profilleri</option>
        <?php foreach ($templates as $t): ?>
          <option value="<?php echo $t['id']; ?>" <?php echo $template_id == $t['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($t['title']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Arama Metni -->
    <div class="col-12 col-md-3">
      <label class="form-label fw-bold fs-8 text-muted mb-1"><i class="bi bi-search me-1"></i> Genel Arama</label>
      <div class="input-group input-group-sm">
        <input type="text" name="search" class="form-control" placeholder="Denetçi, birim ara..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
      </div>
    </div>

  </form>
</div>

<!-- Denetim Raporları Tablosu -->
<div class="custom-card p-0 overflow-hidden border-0 shadow-sm rounded-4 mb-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0" style="font-size: 0.85rem;">
      <thead class="table-dark">
        <tr>
          <th style="width: 80px;" class="ps-3 text-center">RAPOR NO</th>
          <th>DENETLENEN KURUM & BİRİM</th>
          <th>ANKET PROFİLİ</th>
          <th>DENETÇİ UZMAN</th>
          <th style="width: 140px;" class="text-center">EN YÜKSEK RİSK</th>
          <th style="width: 130px;" class="text-center">DENETİM TARİHİ</th>
          <th style="width: 140px;" class="text-end pe-3">İŞLEMLER</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($audits)): ?>
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">
              <i class="bi bi-clipboard-x fs-1 d-block mb-2 opacity-50"></i>
              Arama kriterlerinize uygun tamamlanmış denetim kaydı bulunamadı.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($audits as $a): ?>
            <?php
            $maxRisk = (int)($a['max_audit_risk'] ?? 1);
            if ($maxRisk >= 16) {
                $riskBadge = '<span class="badge bg-danger px-3 py-1.5 fs-8 font-weight-bold">R: ' . $maxRisk . ' - YÜKSEK RİSK</span>';
            } elseif ($maxRisk >= 10) {
                $riskBadge = '<span class="badge bg-warning text-dark px-3 py-1.5 fs-8 font-weight-bold">R: ' . $maxRisk . ' - DİKKATE DEĞER</span>';
            } elseif ($maxRisk >= 6) {
                $riskBadge = '<span class="badge bg-info text-dark px-3 py-1.5 fs-8 font-weight-bold">R: ' . $maxRisk . ' - ÖNEMLİ RİSK</span>';
            } else {
                $riskBadge = '<span class="badge bg-success px-3 py-1.5 fs-8 font-weight-bold">R: ' . $maxRisk . ' - DÜŞÜK RİSK</span>';
            }
            ?>
            <tr>
              <td class="ps-3 text-center fw-bold">
                <a href="audit_detail.php?id=<?php echo $a['id']; ?>" class="text-decoration-none text-dark fw-extrabold">
                  #DEN-<?php echo sprintf('%04d', $a['id']); ?>
                </a>
              </td>
              <td>
                <div class="fw-extrabold text-dark fs-7">
                  <?php if (!empty($a['institution_name'])): ?>
                    <span class="badge bg-danger-subtle text-danger font-weight-bold me-1 fs-8">
                      <i class="bi bi-hospital me-1"></i><?php echo htmlspecialchars($a['institution_name']); ?>
                    </span><br>
                  <?php endif; ?>
                  <i class="bi bi-building text-primary me-1"></i><?php echo htmlspecialchars($a['unit_name']); ?>
                </div>
              </td>
              <td>
                <span class="badge bg-light text-dark border font-weight-bold fs-8">
                  <?php echo htmlspecialchars($a['survey_title']); ?>
                </span>
              </td>
              <td class="text-muted">
                <i class="bi bi-person-circle text-secondary me-1"></i> <?php echo htmlspecialchars($a['auditor_name']); ?>
              </td>
              <td class="text-center">
                <?php echo $riskBadge; ?>
              </td>
              <td class="text-center text-muted fs-8 font-weight-bold">
                <?php echo date('d.m.Y - H:i', strtotime($a['audit_date'])); ?>
              </td>
              <td class="text-end pe-3">
                <div class="d-inline-flex align-items-center gap-1">
                  <a href="audit_detail.php?id=<?php echo $a['id']; ?>" class="btn btn-sm btn-primary font-weight-bold px-2.5 py-1 rounded-2" title="Rapor Detayını İncele">
                    <i class="bi bi-eye-fill me-1"></i> İncele
                  </a>

                  <?php if (has_permission('audit_delete')): ?>
                    <form method="POST" action="audits_list.php" class="d-inline confirm-delete-form" data-confirm-title="Denetim Raporunu Sil" data-confirm-text="Bu denetim kaydını (#DEN-<?php echo sprintf('%04d', $a['id']); ?>) silmek istediğinize emin misiniz?">
                      <input type="hidden" name="action" value="delete_audit">
                      <input type="hidden" name="audit_id" value="<?php echo $a['id']; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger font-weight-bold px-2 py-1 rounded-2" title="Denetimi Sil">
                        <i class="bi bi-trash-fill"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Sayfalama (Pagination) Barı -->
  <?php if ($totalPages > 1): ?>
  <div class="p-3 bg-light border-top d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
    <div class="text-muted fs-8 font-weight-bold">
      Toplam <?php echo $totalRecords; ?> kayıttan <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $totalRecords); ?> arası gösteriliyor.
    </div>

    <nav>
      <ul class="pagination pagination-sm m-0">
        <?php if ($page > 1): ?>
          <li class="page-item"><a class="page-item-link page-link" href="audits_list.php?page=<?php echo $page - 1; ?>&institution_id=<?php echo $institution_id; ?>&unit_id=<?php echo $unit_id; ?>&template_id=<?php echo $template_id; ?>&search=<?php echo urlencode($search); ?>">&laquo; Önceki</a></li>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
            <a class="page-link" href="audits_list.php?page=<?php echo $i; ?>&institution_id=<?php echo $institution_id; ?>&unit_id=<?php echo $unit_id; ?>&template_id=<?php echo $template_id; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
          </li>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
          <li class="page-item"><a class="page-link" href="audits_list.php?page=<?php echo $page + 1; ?>&institution_id=<?php echo $institution_id; ?>&unit_id=<?php echo $unit_id; ?>&template_id=<?php echo $template_id; ?>&search=<?php echo urlencode($search); ?>">Sonraki &raquo;</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
