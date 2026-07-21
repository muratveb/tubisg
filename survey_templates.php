<?php
/**
 * Tubİsg - Anket Profilleri / Şablonları Listesi ve Tanımlama
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('surveys_manage');

$db = getDB();
$user = get_current_user_data();

// Ekleme / Durum Değiştirme / Silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'Genel');

        if (!empty($title)) {
            $stmt = $db->prepare("INSERT INTO survey_templates (title, description, category, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $description, $category, $user['id']]);
            $newId = $db->lastInsertId();
            log_action('Anket Profili Eklendi', "Yeni anket profili: {$title} (ID: #{$newId})");
            set_flash('success', 'Anket profili oluşturuldu. Şimdi soruları ekleyebilirsiniz.');
            header("Location: survey_edit.php?id=" . $newId);
            exit;
        }
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $targetStmt = $db->prepare("SELECT * FROM survey_templates WHERE id = ?");
            $targetStmt->execute([$id]);
            $tpl = $targetStmt->fetch();

            if ($tpl) {
                // FK 1451 hatasını önlemek için bu şablona ait denetimleri sil
                $db->prepare("DELETE FROM audits WHERE template_id = ?")->execute([$id]);

                // Şablonu sil (soru ve seçenekler ON DELETE CASCADE ile silinir)
                $stmt = $db->prepare("DELETE FROM survey_templates WHERE id = ?");
                $stmt->execute([$id]);

                log_action('Anket Profili Silindi', "Anket profili: {$tpl['title']} (ID: #{$id}) ve ilişkili tüm denetimleri silindi.");
                set_flash('success', "Anket profili ({$tpl['title']}) başarıyla silindi.");
            }
        }
        header("Location: survey_templates.php");
        exit;
    }
}

// Anket şablonlarını çek
$templates = $db->query("
    SELECT st.*, COUNT(sq.id) as question_count, u.name_surname as creator_name
    FROM survey_templates st
    LEFT JOIN survey_questions sq ON st.id = sq.template_id
    LEFT JOIN users u ON st.created_by = u.id
    GROUP BY st.id
    ORDER BY st.created_at DESC
")->fetchAll();

$pageTitle = 'Anket Profilleri Şablon Tanımlama';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h3 class="fw-extrabold m-0">Anket Profilleri</h3>
    <p class="text-muted fs-7 m-0">Hastane, Fabrika veya Şantiye için özelleştirilmiş anket ve puanlama şablonları</p>
  </div>
  <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
    <i class="bi bi-journal-plus"></i> Yeni Anket Profili Oluştur
  </button>
</div>

<div class="row g-4">
  <?php if (empty($templates)): ?>
    <div class="col-12">
      <div class="custom-card text-center py-5 text-muted">
        <i class="bi bi-journal-x display-4 d-block mb-3"></i>
        Henüz hiç anket profili tanımlanmamış. "Yeni Anket Profili Oluştur" butonuna basarak başlayabilirsiniz.
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($templates as $tpl): ?>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="custom-card h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="badge bg-primary-light text-primary font-weight-bold"><?php echo htmlspecialchars($tpl['category']); ?></span>
              <span class="badge bg-light text-dark border"><i class="bi bi-list-task"></i> <?php echo $tpl['question_count']; ?> Soru</span>
            </div>
            
            <h5 class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($tpl['title']); ?></h5>
            <p class="text-muted fs-7 mb-3"><?php echo htmlspecialchars($tpl['description'] ?? 'Açıklama girilmemiş.'); ?></p>
          </div>

          <div class="border-top pt-3 mt-3 d-flex align-items-center justify-content-between">
            <div class="fs-8 text-muted">
              <i class="bi bi-person me-1"></i> <?php echo htmlspecialchars($tpl['creator_name'] ?? 'Sistem'); ?>
            </div>
            <div class="d-flex gap-1">
              <a href="survey_edit.php?id=<?php echo $tpl['id']; ?>" class="btn btn-sm btn-outline-primary font-weight-bold" title="Soruları Düzenle">
                <i class="bi bi-pencil-fill"></i> Soruları Yönet
              </a>
              <form method="POST" action="survey_templates.php" class="d-inline confirm-delete-form" data-confirm-title="Anket Profilini Sil" data-confirm-text="Bu anket profilini (<?php echo htmlspecialchars($tpl['title']); ?>) ve bağlı tüm sorularını silmek istediğinize emin misiniz?">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $tpl['id']; ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Anketi Sil">
                  <i class="bi bi-trash-fill"></i>
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Yeni Anket Profili Ekle Modal -->
<div class="modal fade" id="addTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="survey_templates.php">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-journal-plus text-success"></i> Yeni Anket Profili Tanımla</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Anket Profili Başlığı</label>
            <input type="text" name="title" class="form-control" placeholder="Örn: Hastane İSG Saha Denetimi" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Kategori</label>
            <input type="text" name="category" class="form-control" placeholder="Örn: Sağlık Tesisleri, Şantiye, Depo" value="Genel" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Açıklama (Opsiyonel)</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Bu anket profilinin kullanım amacı ve kapsamı..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-success">Oluştur ve Soruları Ekle</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
