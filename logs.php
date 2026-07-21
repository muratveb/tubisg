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

$sql = "SELECT l.*, u.name_surname FROM system_logs l LEFT JOIN users u ON l.user_id = u.id WHERE 1=1";
$params = [];

if ($user_id > 0) {
    $sql .= " AND l.user_id = ?";
    $params[] = $user_id;
}

if (!empty($action_filter)) {
    $sql .= " AND (l.action LIKE ? OR l.details LIKE ?)";
    $params[] = "%$action_filter%";
    $params[] = "%$action_filter%";
}

if (!empty($start_date)) {
    $sql .= " AND l.created_at >= ?";
    $params[] = $start_date . " 00:00:00";
}

if (!empty($end_date)) {
    $sql .= " AND l.created_at <= ?";
    $params[] = $end_date . " 23:59:59";
}

$sql .= " ORDER BY l.id DESC LIMIT 500";

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
    <p class="text-muted fs-7 m-0">Kullanıcıların ne zaman, nereden bağlandığını ve hangi işlemleri yaptığını anlık izleyin.</p>
  </div>
  <span class="badge bg-primary-light text-primary font-weight-bold p-2 px-3 rounded-pill">
    <i class="bi bi-clock-history"></i> Toplam <?php echo count($logs); ?> Kayıt Gösteriliyor
  </span>
</div>

<!-- Log Filtreleme Kartı -->
<div class="custom-card mb-4">
  <form method="GET" action="logs.php" class="row g-3 align-items-end">
    
    <div class="col-12 col-sm-6 col-md-3">
      <label class="form-label fw-bold fs-8 text-muted">Kullanıcı Filtresi</label>
      <select name="user_id" class="form-select">
        <option value="0">-- Tüm Kullanıcılar --</option>
        <?php foreach ($usersList as $u): ?>
          <option value="<?php echo $u['id']; ?>" <?php echo $user_id == $u['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($u['name_surname']); ?> (<?php echo htmlspecialchars($u['username']); ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <label class="form-label fw-bold fs-8 text-muted">İşlem / Detay Arama</label>
      <input type="text" name="action_filter" class="form-control" placeholder="Örn: Denetim, Silindi, Giriş..." value="<?php echo htmlspecialchars($action_filter); ?>">
    </div>

    <div class="col-12 col-sm-6 col-md-2">
      <label class="form-label fw-bold fs-8 text-muted">Başlangıç Tarihi</label>
      <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
    </div>

    <div class="col-12 col-sm-6 col-md-2">
      <label class="form-label fw-bold fs-8 text-muted">Bitiş Tarihi</label>
      <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
    </div>

    <div class="col-12 col-md-2 d-flex gap-2">
      <button type="submit" class="btn btn-dark w-100 font-weight-bold">
        <i class="bi bi-search"></i> Filtrele
      </button>
      <?php if ($user_id > 0 || !empty($action_filter) || !empty($start_date) || !empty($end_date)): ?>
        <a href="logs.php" class="btn btn-outline-secondary" title="Temizle">
          <i class="bi bi-x-lg"></i>
        </a>
      <?php endif; ?>
    </div>

  </form>
</div>

<!-- Log Veri Tablosu -->
<div class="custom-card">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0">
      <thead class="table-light fs-8 text-uppercase text-muted">
        <tr>
          <th>Tarih & Saat</th>
          <th>Kullanıcı</th>
          <th>İşlem Türü</th>
          <th>İşlem Detayı</th>
          <th>IP Adresi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr>
            <td colspan="5" class="text-center py-4 text-muted">Filtrelere uygun işlem logu bulunamadı.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($logs as $log): ?>
            <?php
            $act = strtolower($log['action']);
            $badgeColor = 'bg-secondary';
            if (str_contains($act, 'sil')) $badgeColor = 'bg-danger';
            elseif (str_contains($act, 'giriş') || str_contains($act, 'eklendi') || str_contains($act, 'tamamlandı')) $badgeColor = 'bg-success';
            elseif (str_contains($act, 'güncelle') || str_contains($act, 'düzenle')) $badgeColor = 'bg-primary';
            elseif (str_contains($act, 'çıkış')) $badgeColor = 'bg-warning text-dark';
            ?>
            <tr>
              <td class="text-nowrap fs-8 fw-bold text-muted">
                <i class="bi bi-clock text-secondary me-1"></i>
                <?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?>
              </td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="avatar-circle" style="width: 28px; height: 28px; font-size: 0.75rem;">
                    <?php echo mb_substr($log['name_surname'] ?? $log['username'] ?? 'S', 0, 1, 'UTF-8'); ?>
                  </div>
                  <div>
                    <div class="fw-bold text-dark fs-7"><?php echo htmlspecialchars($log['name_surname'] ?? $log['username']); ?></div>
                    <div class="text-muted fs-8" style="font-size:0.65rem;">@<?php echo htmlspecialchars($log['username']); ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge <?php echo $badgeColor; ?> p-2 fs-8 rounded-pill font-weight-bold">
                  <?php echo htmlspecialchars($log['action']); ?>
                </span>
              </td>
              <td class="fs-7 text-dark"><?php echo htmlspecialchars($log['details'] ?? '-'); ?></td>
              <td class="fs-8 text-muted font-monospace"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
