<?php
/**
 * Tubİsg - Sistem İşlem ve Hareket Logları Ekranı
 * (Tekli Silme, Kullanıcı Bazlı Silme, Seçili Toplu Silme ve Tümünü Temizleme Seçenekleri İle)
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('logs_view');

$db = getDB();

// Log Silme İşlemleri POST Yönetimi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Tekli Log Silme
    if ($action === 'delete_single') {
        $logId = (int)($_POST['log_id'] ?? 0);
        if ($logId > 0) {
            $stmt = $db->prepare("DELETE FROM system_logs WHERE id = ?");
            $stmt->execute([$logId]);
            set_flash('success', "Log kaydı (#{$logId}) silindi.");
        }
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    // 2. Seçilen Logları Toplu Silme
    if ($action === 'delete_bulk') {
        $selectedIds = $_POST['selected_log_ids'] ?? [];
        if (is_array($selectedIds) && !empty($selectedIds)) {
            $cleanIds = array_map('intval', $selectedIds);
            $cleanIds = array_filter($cleanIds, fn($id) => $id > 0);
            if (!empty($cleanIds)) {
                $inClause = implode(',', array_fill(0, count($cleanIds), '?'));
                $stmt = $db->prepare("DELETE FROM system_logs WHERE id IN ($inClause)");
                $stmt->execute(array_values($cleanIds));
                set_flash('success', count($cleanIds) . ' adet işlem logu başarıyla silindi.');
            }
        } else {
            set_flash('warning', 'Silinecek log kaydı seçilmedi.');
        }
        header("Location: " . $_SERVER['HTTP_REFERER']);
        exit;
    }

    // 3. Seçili Kullanıcının Tüm Loglarını Silme
    if ($action === 'delete_by_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = $db->prepare("DELETE FROM system_logs WHERE user_id = ?");
            $stmt->execute([$userId]);
            set_flash('success', 'Seçilen kullanıcının tüm hareket logları temizlendi.');
        }
        header("Location: logs.php");
        exit;
    }

    // 4. Tarih Aralığına Göre Veya 30 Günden Eski Logları Silme
    if ($action === 'delete_by_date') {
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        
        if (!empty($startDate) && !empty($endDate)) {
            $stmt = $db->prepare("DELETE FROM system_logs WHERE created_at >= ? AND created_at <= ?");
            $stmt->execute([$startDate . " 00:00:00", $endDate . " 23:59:59"]);
            set_flash('success', "{$startDate} ile {$endDate} arasındaki loglar temizlendi.");
        } elseif (!empty($_POST['older_than_30_days'])) {
            $stmt = $db->query("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            set_flash('success', '30 günden eski geçmiş loglar temizlendi.');
        }
        header("Location: logs.php");
        exit;
    }

    // 5. Tüm Log Geçmişini Tamamen Temizleme
    if ($action === 'delete_all') {
        $db->exec("TRUNCATE TABLE system_logs");
        set_flash('success', 'Tüm sistem işlem logları başarıyla sıfırlandı.');
        header("Location: logs.php");
        exit;
    }
}

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

$sql = "SELECT l.*, u.name_surname, u.username FROM system_logs l LEFT JOIN users u ON l.user_id = u.id $whereSql ORDER BY l.id DESC LIMIT $per_page OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Filtre İçin Kullanıcı Listesi
$usersList = $db->query("SELECT id, username, name_surname FROM users ORDER BY name_surname ASC")->fetchAll();

$selectedUserName = '';
if ($user_id > 0) {
    foreach ($usersList as $u) {
        if ($u['id'] == $user_id) {
            $selectedUserName = $u['name_surname'] ? $u['name_surname'] . " (@{$u['username']})" : "@" . $u['username'];
            break;
        }
    }
}

$pageTitle = 'Sistem İşlem Logları';
include __DIR__ . '/includes/header.php';
?>

<!-- Üst Başlık & Log Temizleme Araçları -->
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge bg-secondary-subtle text-secondary font-weight-bold px-2.5 py-1 fs-8 rounded-pill">
        <i class="bi bi-shield-lock me-1"></i> Güvenlik & İşlem Kayıtları
      </span>
    </div>
    <h3 class="fw-extrabold m-0 text-dark">Sistem İşlem Logları</h3>
    <p class="text-muted fs-7 m-0 mt-0.5">Kullanıcı hareketleri, saha denetimi kayıtları ve yetki müdahaleleri.</p>
  </div>
  
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="badge bg-primary-subtle text-primary font-weight-bold p-2 px-3 rounded-pill fs-8">
      <i class="bi bi-clock-history me-1"></i> Toplam <?php echo $totalRecords; ?> İşlem Kaydı
    </span>

    <!-- Log Temizleme Araçları Açılır Menüsü -->
    <div class="dropdown">
      <button class="btn btn-outline-danger btn-sm font-weight-bold px-3 py-2 rounded-3 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-trash3 me-1"></i> Log Temizleme Araçları
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3 p-2 fs-7" style="min-width: 240px;">
        <?php if ($user_id > 0): ?>
          <li>
            <form method="POST" action="logs.php" class="confirm-delete-form" data-confirm-title="Kullanıcı Loglarını Sil" data-confirm-text="<?php echo htmlspecialchars($selectedUserName); ?> adlı kullanıcının tüm işlem logları silinecektir. Emin misiniz?">
              <input type="hidden" name="action" value="delete_by_user">
              <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
              <button type="submit" class="dropdown-item text-danger font-weight-bold rounded-2">
                <i class="bi bi-person-x me-2"></i> Bu Kullanıcının Loglarını Sil
              </button>
            </form>
          </li>
          <li><hr class="dropdown-divider"></li>
        <?php endif; ?>

        <li>
          <form method="POST" action="logs.php" class="confirm-delete-form" data-confirm-title="Eski Logları Sil" data-confirm-text="30 günden eski tüm geçmiş işlem kayıtları silinecektir. Emin misiniz?">
            <input type="hidden" name="action" value="delete_by_date">
            <input type="hidden" name="older_than_30_days" value="1">
            <button type="submit" class="dropdown-item text-dark rounded-2">
              <i class="bi bi-calendar-x me-2 text-warning"></i> 30 Günden Eski Logları Temizle
            </button>
          </form>
        </li>
        <li>
          <button type="button" class="dropdown-item text-dark rounded-2" data-bs-toggle="modal" data-bs-target="#dateRangeDeleteModal">
            <i class="bi bi-calendar-range me-2 text-info"></i> Tarih Aralığına Göre Sil...
          </button>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <form method="POST" action="logs.php" class="confirm-delete-form" data-confirm-title="TÜM LOGLARI SIFIRLA" data-confirm-text="DİKKAT! Sistemdeki TÜM işlem log geçmişi tamamen silinecektir. Bu işlem geri alınamaz!">
            <input type="hidden" name="action" value="delete_all">
            <button type="submit" class="dropdown-item text-danger font-weight-bold bg-danger-subtle rounded-2">
              <i class="bi bi-exclamation-octagon-fill me-2"></i> Tüm Log Geçmişini Temizle
            </button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</div>

<!-- Filtre Paneli -->
<div class="custom-card p-3 mb-4 bg-white border-0 shadow-sm rounded-4">
  <form method="GET" action="logs.php" class="row g-2 align-items-end">
    <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">

    <div class="col-12 col-sm-6 col-md-3">
      <label class="form-label fw-bold fs-8 text-muted mb-1"><i class="bi bi-person-fill me-1"></i> Kullanıcı Filtresi</label>
      <select name="user_id" class="form-select form-select-sm">
        <option value="0">-- Tüm Kullanıcılar --</option>
        <?php foreach ($usersList as $u): ?>
          <option value="<?php echo $u['id']; ?>" <?php echo $user_id == $u['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($u['name_surname']); ?> (@<?php echo htmlspecialchars($u['username']); ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <label class="form-label fw-bold fs-8 text-muted mb-1"><i class="bi bi-search me-1"></i> İşlem / Detay Arama</label>
      <input type="text" name="action_filter" class="form-control form-control-sm" placeholder="Örn: Denetim, Silindi, Giriş..." value="<?php echo htmlspecialchars($action_filter); ?>">
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label fw-bold fs-8 text-muted mb-1"><i class="bi bi-calendar-event me-1"></i> Başlangıç Tarihi</label>
      <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date); ?>">
    </div>

    <div class="col-6 col-md-2">
      <label class="form-label fw-bold fs-8 text-muted mb-1"><i class="bi bi-calendar-event me-1"></i> Bitiş Tarihi</label>
      <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date); ?>">
    </div>

    <div class="col-12 col-md-2 d-flex gap-1">
      <button type="submit" class="btn btn-dark btn-sm w-100 font-weight-bold">
        <i class="bi bi-filter me-1"></i> Filtrele
      </button>
      <?php if ($user_id > 0 || !empty($action_filter) || !empty($start_date) || !empty($end_date)): ?>
        <a href="logs.php" class="btn btn-sm btn-outline-secondary" title="Filtreleri Temizle">
          <i class="bi bi-x-lg"></i>
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Log Kayıtları Tablosu & Toplu Silme Formu -->
<form method="POST" action="logs.php" id="bulkDeleteForm">
  <input type="hidden" name="action" value="delete_bulk">

  <div class="custom-card p-0 overflow-hidden border-0 shadow-sm rounded-4 mb-4">
    
    <!-- Toplu İşlem Barları (Seçilince Açılır) -->
    <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between gap-3">
      <div class="d-flex align-items-center gap-3">
        <div class="form-check m-0">
          <input type="checkbox" id="selectAllLogs" class="form-check-input" title="Tümünü Seç / Kaldır">
          <label for="selectAllLogs" class="form-check-label fw-bold fs-8 text-dark cursor-pointer">Tümünü Seç</label>
        </div>
        
        <span class="text-muted fs-8">Toplam <?php echo $totalRecords; ?> kayıt gösteriliyor</span>
      </div>

      <div>
        <button type="button" id="btnSubmitBulkDelete" class="btn btn-danger btn-sm font-weight-bold px-3 d-none shadow-xs">
          <i class="bi bi-trash-fill me-1"></i> Seçilenleri Sil (<span id="selectedLogsCount">0</span>)
        </button>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle m-0" style="font-size: 0.85rem;">
        <thead class="table-dark">
          <tr>
            <th style="width: 40px;" class="ps-3 text-center"></th>
            <th style="width: 70px;" class="text-center">ID</th>
            <th style="width: 200px;">KULLANICI</th>
            <th style="width: 180px;">YAPILAN İŞLEM</th>
            <th>DETAY BİLGİSİ</th>
            <th style="width: 120px;">IP ADRESİ</th>
            <th style="width: 160px;">TARİH / ZAMAN</th>
            <th style="width: 60px;" class="text-end pe-3">SİL</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="8" class="text-center py-5 text-muted">
                <i class="bi bi-clock-history fs-1 d-block mb-2 text-secondary opacity-50"></i>
                Kriterlere uygun hareket kaydı bulunamadı.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <?php
              $actionName = $log['action'];
              $badgeBg = 'bg-secondary-subtle text-secondary';
              if (str_contains($actionName, 'Giriş')) $badgeBg = 'bg-success-subtle text-success';
              elseif (str_contains($actionName, 'Silindi')) $badgeBg = 'bg-danger-subtle text-danger';
              elseif (str_contains($actionName, 'Güncellendi')) $badgeBg = 'bg-primary-subtle text-primary';
              elseif (str_contains($actionName, 'Tamamlandı')) $badgeBg = 'bg-info-subtle text-info-emphasis';
              elseif (str_contains($actionName, 'Eklendi')) $badgeBg = 'bg-success-subtle text-success';
              ?>
              <tr>
                <td class="ps-3 text-center">
                  <input type="checkbox" name="selected_log_ids[]" value="<?php echo $log['id']; ?>" class="form-check-input log-select-checkbox">
                </td>
                <td class="text-center fw-bold text-muted fs-8">#<?php echo $log['id']; ?></td>
                <td>
                  <div class="fw-bold text-dark fs-7">
                    <?php echo htmlspecialchars($log['name_surname'] ?? $log['username'] ?? 'Ziyaretçi'); ?>
                  </div>
                  <?php if ($log['username']): ?>
                    <div class="text-muted fs-8">@<?php echo htmlspecialchars($log['username']); ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?php echo $badgeBg; ?> px-2.5 py-1.5 font-weight-bold fs-8 rounded-pill">
                    <?php echo htmlspecialchars($actionName); ?>
                  </span>
                </td>
                <td class="text-secondary fs-7" style="max-width: 320px;">
                  <?php echo htmlspecialchars($log['details'] ?? '-'); ?>
                </td>
                <td class="text-muted fs-8 font-monospace">
                  <i class="bi bi-laptop me-1"></i> <?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?>
                </td>
                <td class="text-muted fs-8 text-nowrap">
                  <i class="bi bi-clock me-1"></i> <?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?>
                </td>
                <td class="text-end pe-3">
                  <form method="POST" action="logs.php" class="d-inline confirm-delete-form" data-confirm-title="Log Kaydını Sil" data-confirm-text="Bu log kaydını (#<?php echo $log['id']; ?>) silmek istediğinize emin misiniz?">
                    <input type="hidden" name="action" value="delete_single">
                    <input type="hidden" name="log_id" value="<?php echo $log['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2 fs-8 rounded-2" title="Log Kaydını Sil">
                      <i class="bi bi-trash-fill"></i>
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Sayfalama (Pagination) Alt Barı -->
    <div class="p-3 bg-light border-top d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
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
</form>

<!-- TARİH ARALIĞINA GÖRE LOG SİLME MODAL -->
<div class="modal fade" id="dateRangeDeleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <form method="POST" action="logs.php" class="confirm-delete-form" data-confirm-title="Tarih Aralığındaki Logları Sil" data-confirm-text="Seçilen tarihler arasındaki tüm sistem işlem logları silinecektir. Emin misiniz?">
        <input type="hidden" name="action" value="delete_by_date">
        <div class="modal-header bg-dark text-white p-3 px-4">
          <h5 class="modal-title fw-extrabold text-white fs-6"><i class="bi bi-calendar-range me-2 text-warning"></i> Tarih Aralığına Göre Log Temizle</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label fw-bold text-dark fs-8">Başlangıç Tarihi *</label>
              <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col-6">
              <label class="form-label fw-bold text-dark fs-8">Bitiş Tarihi *</label>
              <input type="date" name="end_date" class="form-control" required>
            </div>
          </div>
          <p class="text-muted fs-8 mt-3 m-0"><i class="bi bi-info-circle me-1"></i> Bu tarih aralığında kaydedilmiş tüm kullanıcı hareket logları kalıcı olarak temizlenecektir.</p>
        </div>
        <div class="modal-footer bg-light p-3 px-4">
          <button type="button" class="btn btn-secondary font-weight-bold px-3" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-danger font-weight-bold px-4">Seçili Tarihleri Temizle</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const selectAll = document.getElementById('selectAllLogs');
  const checkboxes = document.querySelectorAll('.log-select-checkbox');
  const bulkBtn = document.getElementById('btnSubmitBulkDelete');
  const selectedCountSpan = document.getElementById('selectedLogsCount');
  const bulkForm = document.getElementById('bulkDeleteForm');

  function updateBulkState() {
    let checkedCount = 0;
    checkboxes.forEach(cb => {
      if (cb.checked) checkedCount++;
    });

    if (selectedCountSpan) selectedCountSpan.textContent = checkedCount;

    if (checkedCount > 0) {
      if (bulkBtn) bulkBtn.classList.remove('d-none');
    } else {
      if (bulkBtn) bulkBtn.classList.add('d-none');
    }
  }

  if (selectAll) {
    selectAll.addEventListener('change', function() {
      checkboxes.forEach(cb => {
        cb.checked = selectAll.checked;
      });
      updateBulkState();
    });
  }

  checkboxes.forEach(cb => {
    cb.addEventListener('change', function() {
      updateBulkState();
    });
  });

  if (bulkBtn) {
    bulkBtn.addEventListener('click', function() {
      const count = selectedCountSpan ? selectedCountSpan.textContent : '0';
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          title: 'Seçilen Logları Sil',
          text: `${count} adet seçili log kaydını silmek istediğinize emin misiniz?`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc2626',
          cancelButtonColor: '#64748b',
          confirmButtonText: 'Evet, Sil',
          cancelButtonText: 'Vazgeç',
          customClass: { popup: 'swal2-custom-popup' }
        }).then((result) => {
          if (result.isConfirmed) {
            bulkForm.submit();
          }
        });
      } else {
        if (confirm(`${count} adet log kaydını silmek istediğinize emin misiniz?`)) {
          bulkForm.submit();
        }
      }
    });
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
