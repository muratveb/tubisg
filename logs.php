<?php
/**
 * Tubİsg - Sistem İşlem ve Hareket Logları Ekranı
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('logs_view');

$db = getDB();

// Filtre Değişkenleri
$user_id = (int)($_GET['user_id'] ?? 0);
$action_filter = trim($_GET['action_filter'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

// Sayfalama (Pagination) Parametreleri
$per_page = (int)($_GET['per_page'] ?? 15);
if (!in_array($per_page, [15, 30, 50, 100])) {
    $per_page = 15;
}
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;

$whereSql = " WHERE 1=1";
$params = [];

if ($user_id > 0) {
    $whereSql .= " AND l.user_id = ?";
    $params[] = $user_id;
}

if (!empty($action_filter)) {
    $whereSql .= " AND (l.action LIKE ? OR l.details LIKE ?)";
    $params[] = "%$action_filter%";
    $params[] = "%$action_filter%";
}

if (!empty($start_date)) {
    $whereSql .= " AND l.created_at >= ?";
    $params[] = $start_date . " 00:00:00";
}

if (!empty($end_date)) {
    $whereSql .= " AND l.created_at <= ?";
    $params[] = $end_date . " 23:59:59";
}

// Toplam Kayıt Sayısı
$countSql = "SELECT COUNT(*) as total FROM system_logs l $whereSql";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetch()['total'];

$totalPages = ceil($totalRecords / $per_page);
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $per_page;
if ($offset < 0) $offset = 0;

$sql = "SELECT l.*, u.name_surname FROM system_logs l LEFT JOIN users u ON l.user_id = u.id $whereSql ORDER BY l.id DESC LIMIT $per_page OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Filtre İçin Kullanıcı Listesi
$usersList = $db->query("SELECT id, username, name_surname FROM users ORDER BY name_surname ASC")->fetchAll();

$pageTitle = 'Sistem İşlem Logları';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h3 class="fw-extrabold m-0">Sistem İşlem Logları</h3>
    <p class="text-muted fs-7 m-0">Sistemdeki tüm kullanıcı hareketleri, denetim tamamlama ve yetki müdahale kayıtları</p>
  </div>
  <span class="badge bg-primary-light text-primary font-weight-bold p-2 px-3">
    <i class="bi bi-clock-history"></i> Toplam <?php echo $totalRecords; ?> İşlem Kaydı
  </span>
</div>

<!-- Filtre Paneli -->
<div class="custom-card mb-4">
  <form method="GET" action="logs.php" class="row g-3 align-items-end">
    <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">

    <div class="col-12 col-sm-6 col-md-3">
      <label class="form-label fw-bold fs-8 text-muted">Kullanıcı Filtresi</label>
      <select name="user_id" class="form-select">
        <option value="0">-- Tüm Kullanıcılar --</option>
        <?php foreach ($usersList as $u): ?>
          <option value="<?php echo $u['id']; ?>" <?php echo $user_id == $u['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($u['name_surname']); ?> (@<?php echo htmlspecialchars($u['username']); ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <label class="form-label fw-bold fs-8 text-muted">İşlem / Detay Arama</label>
      <input type="text" name="action_filter" class="form-control" placeholder="Örn: Denetim, Silindi, Giriş..." value="<?php echo htmlspecialchars($action_filter); ?>">
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label fw-bold fs-8 text-muted">Başlangıç Tarihi</label>
      <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label fw-bold fs-8 text-muted">Bitiş Tarihi</label>
      <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
    </div>

    <div class="col-12 col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-dark w-100 font-weight-bold">
        <i class="bi bi-filter"></i> Filtrele
      </button>
      <?php if ($user_id > 0 || !empty($action_filter) || !empty($start_date) || !empty($end_date)): ?>
        <a href="logs.php" class="btn btn-outline-secondary" title="Temizle">
          <i class="bi bi-x-lg"></i>
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Log Kayıtları Tablosu -->
<div class="custom-card">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0">
      <thead class="table-light fs-8 text-uppercase text-muted">
        <tr>
          <th style="width: 70px;">ID</th>
          <th>Kullanıcı</th>
          <th>Yapılan İşlem / Eylem</th>
          <th>Detay Bilgisi</th>
          <th>IP Adresi</th>
          <th>Tarih / Zaman</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr>
            <td colspan="6" class="text-center py-4 text-muted">Kriterlere uygun hareket kaydı bulunamadı.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($logs as $log): ?>
            <?php
            $action = $log['action'];
            $badgeBg = 'bg-secondary';
            if (str_contains($action, 'Giriş')) $badgeBg = 'bg-success';
            elseif (str_contains($action, 'Silindi')) $badgeBg = 'bg-danger';
            elseif (str_contains($action, 'Güncellendi')) $badgeBg = 'bg-primary';
            elseif (str_contains($action, 'Tamamlandı')) $badgeBg = 'bg-info text-dark';
            elseif (str_contains($action, 'Eklendi')) $badgeBg = 'bg-success';
            ?>
            <tr>
              <td class="fw-bold text-muted fs-8">#<?php echo $log['id']; ?></td>
              <td>
                <div class="fw-bold text-dark fs-7">
                  <?php echo htmlspecialchars($log['name_surname'] ?? $log['username'] ?? 'Ziyaretçi'); ?>
                </div>
                <?php if ($log['username']): ?>
                  <div class="text-muted fs-8">@<?php echo htmlspecialchars($log['username']); ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge <?php echo $badgeBg; ?> p-2 font-weight-bold">
                  <?php echo htmlspecialchars($action); ?>
                </span>
              </td>
              <td class="text-secondary fs-7" style="max-width: 350px;">
                <?php echo htmlspecialchars($log['details'] ?? '-'); ?>
              </td>
              <td class="text-muted fs-8 font-monospace">
                <i class="bi bi-laptop me-1"></i> <?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?>
              </td>
              <td class="text-muted fs-8 text-nowrap">
                <i class="bi bi-clock me-1"></i> <?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Sayfalama (Pagination) Alt Barı -->
  <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3 mt-3 pt-3 border-top">
    <div class="text-muted fs-8">
      Toplam <strong><?php echo $totalRecords; ?></strong> kayıttan <strong><?php echo $totalRecords > 0 ? $offset + 1 : 0; ?></strong> - <strong><?php echo min($offset + $per_page, $totalRecords); ?></strong> arası gösteriliyor.
    </div>

    <div class="d-flex align-items-center gap-2">
      <label class="fs-8 text-muted fw-bold">Sayfa Başına:</label>
      <select class="form-select form-select-sm" style="width: auto;" onchange="location = this.value;">
        <?php foreach ([15, 30, 50, 100] as $size): ?>
          <?php
          $urlParams = $_GET;
          $urlParams['per_page'] = $size;
          $urlParams['page'] = 1;
          $sizeUrl = 'logs.php?' . http_build_query($urlParams);
          ?>
          <option value="<?php echo htmlspecialchars($sizeUrl); ?>" <?php echo $per_page == $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
        <?php endforeach; ?>
      </select>

      <?php if ($totalPages > 1): ?>
        <nav>
          <ul class="pagination pagination-sm m-0">
            <!-- Önceki -->
            <?php
            $prevParams = $_GET;
            $prevParams['page'] = max(1, $page - 1);
            $prevUrl = 'logs.php?' . http_build_query($prevParams);
            ?>
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
              <a class="page-link" href="<?php echo htmlspecialchars($prevUrl); ?>">&laquo;</a>
            </li>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <?php
              $pageParams = $_GET;
              $pageParams['page'] = $i;
              $pUrl = 'logs.php?' . http_build_query($pageParams);
              ?>
              <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo htmlspecialchars($pUrl); ?>"><?php echo $i; ?></a>
              </li>
            <?php endfor; ?>

            <!-- Sonraki -->
            <?php
            $nextParams = $_GET;
            $nextParams['page'] = min($totalPages, $page + 1);
            $nextUrl = 'logs.php?' . http_build_query($nextParams);
            ?>
            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
              <a class="page-link" href="<?php echo htmlspecialchars($nextUrl); ?>">&raquo;</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
