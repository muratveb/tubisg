<?php
/**
 * Tubİsg - Genel Cevap Seçenekleri Yönetim Modülü
 * (Evet, Hayır, Kısmen, Denetim Dışı vb. Genel Şıkların & Tetikleyicilerinin Tanımlandığı Ekran)
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('surveys_manage');

$db = getDB();

// Form İşlemleri (Ekleme / Düzenleme / Silme / Aktiflik)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'add') {
        $option_text = trim($_POST['option_text'] ?? '');
        $trigger_action = isset($_POST['trigger_action']) && $_POST['trigger_action'] == 1 ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if (!empty($option_text)) {
            $stmt = $db->prepare("INSERT INTO global_options (option_text, trigger_action, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$option_text, $trigger_action, $sort_order]);
            $newId = $db->lastInsertId();

            log_action('Genel Cevap Seçeneği Eklendi', "Seçenek: {$option_text}, Tetikleyici: {$trigger_action} (ID: #{$newId})");
            set_flash('success', 'Genel cevap seçeneği başarıyla eklendi.');
        } else {
            set_flash('danger', 'Cevap seçeneği metni boş bırakılamaz.');
        }
        header("Location: global_options.php");
        exit;
    }

    if ($_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $option_text = trim($_POST['option_text'] ?? '');
        $trigger_action = isset($_POST['trigger_action']) && $_POST['trigger_action'] == 1 ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($id > 0 && !empty($option_text)) {
            $stmt = $db->prepare("UPDATE global_options SET option_text = ?, trigger_action = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$option_text, $trigger_action, $sort_order, $id]);

            log_action('Genel Cevap Seçeneği Güncellendi', "ID: #{$id}, Seçenek: {$option_text}, Tetikleyici: {$trigger_action}");
            set_flash('success', 'Genel cevap seçeneği güncellendi.');
        }
        header("Location: global_options.php");
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM global_options WHERE id = ?");
            $stmt->execute([$id]);

            log_action('Genel Cevap Seçeneği Silindi', "ID: #{$id}");
            set_flash('success', 'Genel cevap seçeneği silindi.');
        }
        header("Location: global_options.php");
        exit;
    }
}

// Seçenekleri Çek
$options = $db->query("SELECT * FROM global_options ORDER BY sort_order ASC, id ASC")->fetchAll();

$pageTitle = 'Genel Cevap Seçenekleri Tanımlama';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h3 class="fw-extrabold m-0">Genel Cevap Seçenekleri</h3>
    <p class="text-muted fs-7 m-0">Saha denetimlerinde gösterilecek standart şıkların (Evet, Hayır, Kısmen vb.) ve önlem tetikleyicilerinin yönetimi</p>
  </div>
  <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addOptionModal">
    <i class="bi bi-plus-lg"></i> Yeni Cevap Seçeneği Ekle
  </button>
</div>

<div class="custom-card">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0">
      <thead class="table-light fs-8 text-uppercase text-muted">
        <tr>
          <th style="width:70px;">Sıra</th>
          <th>Cevap Seçeneği Metni</th>
          <th>İSG Önlem Kartı Tetiklesin mi?</th>
          <th class="text-end">İşlem</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($options)): ?>
          <tr>
            <td colspan="4" class="text-center py-4 text-muted">Henüz hiç genel cevap seçeneği tanımlanmamış.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($options as $opt): ?>
            <tr>
              <td class="fw-bold text-muted"><?php echo (int)$opt['sort_order']; ?></td>
              <td class="fw-bold text-dark fs-7">
                <span class="badge bg-light text-dark border me-2"><i class="bi bi-ui-checks"></i></span>
                <?php echo htmlspecialchars($opt['option_text']); ?>
              </td>
              <td>
                <?php if ($opt['trigger_action'] == 1): ?>
                  <span class="badge bg-danger p-2"><i class="bi bi-exclamation-circle-fill me-1"></i> Evet (Mevcut Durum & Önlem Kartı Açılır)</span>
                <?php else: ?>
                  <span class="badge bg-success p-2"><i class="bi bi-check-circle-fill me-1"></i> Hayır (Normal Kabul Olunur)</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-light text-primary me-1" data-bs-toggle="modal" data-bs-target="#editOptionModal<?php echo $opt['id']; ?>" title="Düzenle">
                  <i class="bi bi-pencil-fill"></i>
                </button>
                <form method="POST" action="global_options.php" class="d-inline confirm-delete-form" data-confirm-title="Cevap Seçeneğini Sil" data-confirm-text="Bu cevap seçeneğini silmek istediğinize emin misiniz?">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo $opt['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-light text-danger" title="Sil">
                    <i class="bi bi-trash-fill"></i>
                  </button>
                </form>
              </td>
            </tr>

            <!-- Düzenleme Modal -->
            <div class="modal fade" id="editOptionModal<?php echo $opt['id']; ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <form method="POST" action="global_options.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $opt['id']; ?>">
                    <div class="modal-header">
                      <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-success"></i> Cevap Seçeneğini Düzenle</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                      <div class="mb-3">
                        <label class="form-label fw-bold">Cevap Şıkkı Metni</label>
                        <input type="text" name="option_text" class="form-control" value="<?php echo htmlspecialchars($opt['option_text']); ?>" required>
                      </div>
                      <div class="mb-3">
                        <div class="form-check form-switch pt-1">
                          <input class="form-check-input" type="checkbox" name="trigger_action" value="1" id="trig_opt_<?php echo $opt['id']; ?>" <?php echo $opt['trigger_action'] == 1 ? 'checked' : ''; ?>>
                          <label class="form-check-label fw-bold text-danger" for="trig_opt_<?php echo $opt['id']; ?>">
                            Bu Seçenek İşaretlendiğinde Mevcut Durum & İSG Önlem Kartı Açsın
                          </label>
                        </div>
                      </div>
                      <div class="mb-3">
                        <label class="form-label fw-bold">Sıralama Önceliği</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo (int)$opt['sort_order']; ?>">
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

<!-- Yeni Cevap Seçeneği Ekle Modal -->
<div class="modal fade" id="addOptionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="global_options.php">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill text-success"></i> Yeni Genel Cevap Seçeneği Ekle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Cevap Şıkkı Metni</label>
            <input type="text" name="option_text" class="form-control" placeholder="Örn: Gözlenemedi / Diğer" required>
          </div>
          <div class="mb-3">
            <div class="form-check form-switch pt-1">
              <input class="form-check-input" type="checkbox" name="trigger_action" value="1" id="trig_add_new" checked>
              <label class="form-check-label fw-bold text-danger" for="trig_add_new">
                Bu Seçenek İşaretlendiğinde Mevcut Durum & İSG Önlem Kartı Açsın
              </label>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Sıralama Önceliği</label>
            <input type="number" name="sort_order" class="form-control" value="0">
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
