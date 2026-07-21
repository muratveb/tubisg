<?php
/**
 * Tubİsg - Birim Tanımlama ve Yönetim Modülü
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('units_manage');

$db = getDB();

// 1. AJAX Hızlı Birim Ekleme Endpoint'i (Denetim esnasında modal'dan gelen istek)
if (isset($_GET['action']) && $_GET['action'] === 'quick_add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $unit_name = trim($_POST['unit_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($unit_name)) {
        echo json_encode(['success' => false, 'message' => 'Birim adı boş bırakılamaz.']);
        exit;
    }

    $stmt = $db->prepare("INSERT INTO units (unit_name, description) VALUES (?, ?)");
    if ($stmt->execute([$unit_name, $description])) {
        $newId = $db->lastInsertId();
        log_action('Hızlı Birim Eklendi', "Saha sihirbazından birim eklendi: {$unit_name} (ID: #{$newId})");
        echo json_encode([
            'success' => true,
            'unit' => [
                'id' => $newId,
                'unit_name' => htmlspecialchars($unit_name),
                'description' => htmlspecialchars($description)
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Birim eklenirken hata oluştu.']);
    }
    exit;
}

// 2. Normal Form Post İşlemleri (Ekleme / Düzenleme / Silme)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $unit_name = trim($_POST['unit_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!empty($unit_name)) {
            $stmt = $db->prepare("INSERT INTO units (unit_name, description) VALUES (?, ?)");
            $stmt->execute([$unit_name, $description]);
            $newId = $db->lastInsertId();
            log_action('Birim Eklendi', "Yeni birim: {$unit_name} (ID: #{$newId})");
            set_flash('success', 'Birim başarıyla eklendi.');
        } else {
            set_flash('danger', 'Birim adı boş olamaz.');
        }
        header("Location: units.php");
        exit;
    }

    if ($_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $unit_name = trim($_POST['unit_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!empty($unit_name) && $id > 0) {
            $stmt = $db->prepare("UPDATE units SET unit_name = ?, description = ? WHERE id = ?");
            $stmt->execute([$unit_name, $description, $id]);
            log_action('Birim Güncellendi', "Birim: {$unit_name} (ID: #{$id}) güncellendi.");
            set_flash('success', 'Birim bilgileri güncellendi.');
        }
        header("Location: units.php");
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $targetStmt = $db->prepare("SELECT * FROM units WHERE id = ?");
            $targetStmt->execute([$id]);
            $u = $targetStmt->fetch();

            if ($u) {
                // FK 1451 hatasını önlemek için önce ilişkili denetimleri sil
                $db->prepare("DELETE FROM audits WHERE unit_id = ?")->execute([$id]);

                $stmt = $db->prepare("DELETE FROM units WHERE id = ?");
                $stmt->execute([$id]);

                log_action('Birim Silindi', "Birim: {$u['unit_name']} (ID: #{$id}) ve ilişkili tüm denetim verileri silindi.");
                set_flash('success', "Birim ({$u['unit_name']}) başarıyla silindi.");
            }
        }
        header("Location: units.php");
        exit;
    }
}

// 3. Birimleri Listele
$units = $db->query("SELECT u.*, COUNT(a.id) as audit_count FROM units u LEFT JOIN audits a ON u.id = a.unit_id GROUP BY u.id ORDER BY u.unit_name ASC")->fetchAll();

$pageTitle = 'Birim Tanımları';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h3 class="fw-extrabold m-0">Birim Tanımları</h3>
    <p class="text-muted fs-7 m-0">Saha denetimi yapılacak departman ve alanların listesi</p>
  </div>
  <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addUnitModal">
    <i class="bi bi-plus-lg"></i> Yeni Birim Ekle
  </button>
</div>

<div class="custom-card">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0">
      <thead class="table-light fs-8 text-uppercase text-muted">
        <tr>
          <th>Birim Adı</th>
          <th>Açıklama</th>
          <th>Yapılan Denetim Sayısı</th>
          <th class="text-end">İşlem</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($units)): ?>
          <tr>
            <td colspan="4" class="text-center py-4 text-muted">Henüz hiç birim tanımlanmamış.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($units as $u): ?>
            <tr>
              <td class="fw-bold text-dark">
                <i class="bi bi-building text-success me-2"></i> <?php echo htmlspecialchars($u['unit_name']); ?>
              </td>
              <td class="text-muted fs-7"><?php echo htmlspecialchars($u['description'] ?? '-'); ?></td>
              <td>
                <span class="badge bg-light text-dark border font-weight-bold">
                  <?php echo $u['audit_count']; ?> Denetim
                </span>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-light text-primary me-1" data-bs-toggle="modal" data-bs-target="#editUnitModal<?php echo $u['id']; ?>" title="Düzenle">
                  <i class="bi bi-pencil-fill"></i>
                </button>
                <form method="POST" action="units.php" class="d-inline confirm-delete-form" data-confirm-title="Birimi Sil" data-confirm-text="Bu birimi (<?php echo htmlspecialchars($u['unit_name']); ?>) ve bağlı tüm denetim verilerini silmek istediğinize emin misiniz?">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-light text-danger" title="Sil">
                    <i class="bi bi-trash-fill"></i>
                  </button>
                </form>
              </td>
            </tr>

            <!-- Birim Düzenleme Modal (Modern Kart) -->
            <div class="modal fade" id="editUnitModal<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <form method="POST" action="units.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                    <div class="modal-header">
                      <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-success"></i> Birim Düzenle</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                      <div class="mb-3">
                        <label class="form-label fw-bold">Birim Adı</label>
                        <input type="text" name="unit_name" class="form-control" value="<?php echo htmlspecialchars($u['unit_name']); ?>" required>
                      </div>
                      <div class="mb-3">
                        <label class="form-label fw-bold">Açıklama</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($u['description'] ?? ''); ?></textarea>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                      <button type="submit" class="btn btn-success font-weight-bold"><i class="bi bi-check-lg"></i> Kaydet</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Yeni Birim Ekle Modal -->
<div class="modal fade" id="addUnitModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="units.php">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-building-add text-success"></i> Yeni Birim Tanımla</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Birim / Departman Adı</label>
            <input type="text" name="unit_name" class="form-control" placeholder="Örn: Faturalama Birimi" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Açıklama (Opsiyonel)</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Birim konumu veya detaylı bilgi..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-success font-weight-bold"><i class="bi bi-plus-circle-fill"></i> Oluştur</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
