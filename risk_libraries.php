<?php
/**
 * Tubİsg - İSG Tanımlama Kütüphaneleri Modülü
 * (Tehlike Kaynakları, Tehlikeler, Etkilenenler, Sorumlular, Önlem Bankası)
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('surveys_manage');

$db = getDB();

// Aktif Sekme (Kategori)
$activeCat = trim($_GET['cat'] ?? 'hazard_source');
$validCats = [
    'hazard_source'         => 'Tehlike Kaynakları',
    'hazard_name'           => 'Tehlikeler',
    'affected_people'       => 'Etkilenen Gruplar',
    'responsible_person'    => 'Sorumlu Birimler',
    'action_recommendation' => 'Önlem & İyileştirme Bankası'
];

if (!array_key_exists($activeCat, $validCats)) {
    $activeCat = 'hazard_source';
}

// Form İşlemleri (Ekleme / Düzenleme / Silme)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'add') {
        $category = trim($_POST['category'] ?? '');
        $item_text = trim($_POST['item_text'] ?? '');

        if (!empty($category) && !empty($item_text)) {
            $stmt = $db->prepare("INSERT INTO risk_libraries (category, item_text) VALUES (?, ?)");
            $stmt->execute([$category, $item_text]);
            $newId = $db->lastInsertId();
            log_action('İSG Kütüphane Öğesi Eklendi', "Kategori: {$category}, Metin: {$item_text} (ID: #{$newId})");
            set_flash('success', 'Kütüphane öğesi başarıyla eklendi.');
        } else {
            set_flash('danger', 'Lütfen tüm alanları doldurun.');
        }
        header("Location: risk_libraries.php?cat=" . urlencode($category));
        exit;
    }

    if ($_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $category = trim($_POST['category'] ?? '');
        $item_text = trim($_POST['item_text'] ?? '');

        if ($id > 0 && !empty($category) && !empty($item_text)) {
            $stmt = $db->prepare("UPDATE risk_libraries SET item_text = ? WHERE id = ?");
            $stmt->execute([$item_text, $id]);
            log_action('İSG Kütüphane Öğesi Güncellendi', "ID: #{$id}, Metin: {$item_text}");
            set_flash('success', 'Kütüphane öğesi güncellendi.');
        }
        header("Location: risk_libraries.php?cat=" . urlencode($category));
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        $category = trim($_POST['category'] ?? '');
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM risk_libraries WHERE id = ?");
            $stmt->execute([$id]);
            log_action('İSG Kütüphane Öğesi Silindi', "ID: #{$id}");
            set_flash('success', 'Kütüphane öğesi silindi.');
        }
        header("Location: risk_libraries.php?cat=" . urlencode($category));
        exit;
    }
}

// Aktif Kategorideki Öğeleri Çek
$stmt = $db->prepare("SELECT * FROM risk_libraries WHERE category = ? ORDER BY id DESC");
$stmt->execute([$activeCat]);
$items = $stmt->fetchAll();

$pageTitle = 'İSG Tanımlama Kütüphaneleri';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h3 class="fw-extrabold m-0">İSG Tanımlama Kütüphaneleri</h3>
    <p class="text-muted fs-7 m-0">Tehlike kaynakları, etkilenenler, sorumlular ve standart önlem bankası yönetimi</p>
  </div>
  <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addItemModal">
    <i class="bi bi-plus-lg"></i> Yeni Öğesi Ekle
  </button>
</div>

<!-- Kategori Sekmeleri -->
<ul class="nav nav-pills custom-card-tabs p-2 bg-white rounded-3 shadow-sm border mb-4">
  <?php foreach ($validCats as $catKey => $catTitle): ?>
    <li class="nav-item">
      <a class="nav-link fw-bold px-3 py-2 <?php echo $activeCat === $catKey ? 'active bg-success text-white' : 'text-secondary'; ?>" href="risk_libraries.php?cat=<?php echo $catKey; ?>">
        <?php echo $catTitle; ?>
      </a>
    </li>
  <?php endforeach; ?>
</ul>

<!-- Öğeler Tablosu -->
<div class="custom-card">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0">
      <thead class="table-light fs-8 text-uppercase text-muted">
        <tr>
          <th style="width:70px;">ID</th>
          <th>Kütüphane Tanımı / Metni</th>
          <th>Ekleme Tarihi</th>
          <th class="text-end">İşlem</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr>
            <td colspan="4" class="text-center py-4 text-muted">Bu kategoride henüz tanımlanmış öğe bulunmuyor.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <tr>
              <td class="fw-bold text-muted">#<?php echo $item['id']; ?></td>
              <td class="fw-bold text-dark fs-7">
                <?php echo htmlspecialchars($item['item_text']); ?>
              </td>
              <td class="text-muted fs-8"><?php echo date('d.m.Y H:i', strtotime($item['created_at'])); ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-light text-primary me-1" data-bs-toggle="modal" data-bs-target="#editItemModal<?php echo $item['id']; ?>" title="Düzenle">
                  <i class="bi bi-pencil-fill"></i>
                </button>
                <form method="POST" action="risk_libraries.php" class="d-inline confirm-delete-form" data-confirm-title="Öğeyi Sil" data-confirm-text="Bu kütüphane öğesini silmek istediğinize emin misiniz?">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="category" value="<?php echo $activeCat; ?>">
                  <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-light text-danger" title="Sil">
                    <i class="bi bi-trash-fill"></i>
                  </button>
                </form>
              </td>
            </tr>

            <!-- Öğeyi Düzenleme Modal -->
            <div class="modal fade" id="editItemModal<?php echo $item['id']; ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <form method="POST" action="risk_libraries.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="category" value="<?php echo $activeCat; ?>">
                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                    <div class="modal-header">
                      <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-success"></i> Kütüphane Öğesini Düzenle</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                      <div class="mb-3">
                        <label class="form-label fw-bold"><?php echo $validCats[$activeCat]; ?> Tanımı</label>
                        <textarea name="item_text" class="form-control" rows="3" required><?php echo htmlspecialchars($item['item_text']); ?></textarea>
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

<!-- Yeni Öğe Ekle Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="risk_libraries.php">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill text-success"></i> Yeni <?php echo $validCats[$activeCat]; ?> Ekle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Kategori</label>
            <select name="category" class="form-select" required>
              <?php foreach ($validCats as $k => $v): ?>
                <option value="<?php echo $k; ?>" <?php echo $activeCat === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Tanımlanacak Metin / İfade</label>
            <textarea name="item_text" class="form-control" rows="3" placeholder="Örn: Lavabo (WC) tavanlarında gerekli su yalıtımının yapılması..." required></textarea>
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
