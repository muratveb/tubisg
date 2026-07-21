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
            set_flash('success', 'Anket profili oluşturuldu. Şimdi soruları ekleyebilirsiniz.');
            header("Location: survey_edit.php?id=" . $newId);
            exit;
        }
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM survey_templates WHERE id = ?");
            $stmt->execute([$id]);
            set_flash('success', 'Anket profili ve soruları silindi.');
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

<div class="row g-3">
  <?php if (empty($templates)): ?>
    <div class="col-12">
      <div class="custom-card text-center py-5">
        <i class="bi bi-journal-x fs-1 text-muted d-block mb-3"></i>
        <h5>Henüz hiç anket profili tanımlanmamış.</h5>
        <p class="text-muted">Yukarıdaki butonla ilk İSG anket profilinizi oluşturarak soru ve puan tanımlamaya başlayın.</p>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($templates as $tpl): ?>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="custom-card h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex align-items-center justify-content-between mb-2">
              <span class="badge bg-primary-light text-primary font-weight-bold"><?php echo htmlspecialchars($tpl['category']); ?></span>
              <span class="badge bg-light text-dark border"><?php echo $tpl['question_count']; ?> Soru</span>
            </div>
            <h5 class="fw-bold text-dark mb-2"><?php echo htmlspecialchars($tpl['title']); ?></h5>
            <p class="text-muted fs-7 mb-3 opacity-90"><?php echo htmlspecialchars($tpl['description'] ?? 'Açıklama girilmemiş.'); ?></p>
          </div>

          <div class="pt-3 border-top d-flex align-items-center justify-content-between">
            <div class="fs-8 text-muted">
              <i class="bi bi-person"></i> <?php echo htmlspecialchars($tpl['creator_name'] ?? 'Yönetici'); ?>
            </div>
            <div class="d-flex gap-2">
              <a href="survey_edit.php?id=<?php echo $tpl['id']; ?>" class="btn btn-sm btn-outline-success font-weight-bold">
                <i class="bi bi-pencil-square"></i> Soruları Düzenle
              </a>
              <form method="POST" action="survey_templates.php" style="display:inline;" onsubmit="return confirm('Bu anket profilini silmek istediğinize emin misiniz?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $tpl['id']; ?>">
                <button type="submit" class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Yeni Anket Profili Modal -->
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
            <label class="form-label fw-bold">Anket Profili Adı</label>
            <input type="text" name="title" class="form-control" placeholder="Örn: Hastane İSG Saha Denetim Anketi" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Kategori</label>
            <input type="text" name="category" class="form-control" placeholder="Örn: Sağlık Tesisleri, Şantiye, Depo" value="Genel" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Profil Açıklaması</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Bu anket profilinin kullanım alanı ve denetçi yönergeleri..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-success">Oluştur ve Sorulara Geç</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
