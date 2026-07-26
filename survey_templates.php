<?php
/**
 * Tubİsg - Anket Profilleri / Şablonları Listesi ve Tanımlama
 * Ultra-Modern Visual SaaS Dashboard UI
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('surveys_manage');

$db = getDB();
$user = get_current_user_data();

// Ekleme / Güncelleme / Silme İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'Genel');

        if (!empty($title)) {
            $stmt = $db->prepare("INSERT INTO survey_templates (title, description, category, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $description, $category, $user['id']]);
            $newId = $db->lastInsertId();
            log_action('Anket Profili Eklendi', "Yeni anket profili: {$title} (ID: #{$newId})");
            set_flash('success', 'Anket profili oluşturuldu. Şimdi soruları ve risk maddelerini ekleyebilirsiniz.');
            header("Location: survey_edit.php?id=" . $newId);
            exit;
        }
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'Genel');

        if ($id > 0 && !empty($title)) {
            $stmt = $db->prepare("UPDATE survey_templates SET title = ?, description = ?, category = ? WHERE id = ?");
            $stmt->execute([$title, $description, $category, $id]);
            log_action('Anket Profili Güncellendi', "Anket profili ID #{$id}: {$title}");
            set_flash('success', 'Anket profili bilgileri güncellendi.');
        }
        header("Location: survey_templates.php");
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
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
    SELECT st.*, 
           COUNT(sq.id) as question_count,
           (SELECT COUNT(*) FROM audits a WHERE a.template_id = st.id) as audit_count,
           u.name_surname as creator_name
    FROM survey_templates st
    LEFT JOIN survey_questions sq ON st.id = sq.template_id
    LEFT JOIN users u ON st.created_by = u.id
    GROUP BY st.id
    ORDER BY st.created_at DESC
")->fetchAll();

$totalTemplates = count($templates);
$totalQuestionsCount = array_sum(array_column($templates, 'question_count'));
$totalAuditsCount = array_sum(array_column($templates, 'audit_count'));

$pageTitle = 'Anket Profilleri Şablon Tanımlama';
include __DIR__ . '/includes/header.php';
?>

<style>
.tpl-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}
.tpl-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 24px -4px rgba(0, 0, 0, 0.1);
  border-color: #cbd5e1;
}
.tpl-avatar {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
  color: #059669;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.35rem;
  flex-shrink: 0;
  box-shadow: 0 4px 10px rgba(5, 150, 105, 0.15);
  transition: transform 0.2s ease;
}
.tpl-card:hover .tpl-avatar {
  transform: scale(1.08);
}
.btn-soft-primary {
  background-color: #f0f9ff;
  color: #0284c7;
  border: 1px solid #e0f2fe;
  font-weight: 700;
  transition: all 0.2s ease;
}
.btn-soft-primary:hover {
  background-color: #0284c7;
  color: #ffffff;
  border-color: #0284c7;
  transform: translateY(-1px);
}
.btn-soft-danger {
  background-color: #fef2f2;
  color: #dc2626;
  border: 1px solid #fee2e2;
  font-weight: 700;
  transition: all 0.2s ease;
}
.btn-soft-danger:hover {
  background-color: #dc2626;
  color: #ffffff;
  border-color: #dc2626;
  transform: translateY(-1px);
}
</style>

<!-- Üst İşlem Çubuğu & Başlık -->
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
  <div>
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="badge bg-primary-subtle text-primary font-weight-bold px-2.5 py-1 fs-8 rounded-pill">
        <i class="bi bi-journal-text me-1"></i> İSG Şablon Portföyü
      </span>
    </div>
    <h3 class="fw-extrabold m-0 text-dark">Anket Profilleri</h3>
    <p class="text-muted fs-7 m-0 mt-0.5">Hastane, Fabrika veya Şantiye için özelleştirilmiş anket ve İSG risk değerlendirme şablonları.</p>
  </div>
  <div>
    <button type="button" class="btn btn-primary-custom font-weight-bold px-4 py-2.5 shadow-sm rounded-3 text-nowrap" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
      <i class="bi bi-plus-lg me-1.5"></i> Yeni Anket Profili Oluştur
    </button>
  </div>
</div>

<!-- Modern Dark Glass Hero Banner & Metrikler -->
<div class="custom-card p-4 mb-4 border-0 shadow-lg rounded-4 text-white" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);">
  <div class="row g-3 align-items-center">
    <div class="col-12 col-md-5">
      <div class="d-flex align-items-center gap-3">
        <div class="p-3 bg-primary bg-opacity-20 rounded-3 text-white border border-primary border-opacity-25 fs-2">
          <i class="bi bi-journal-check"></i>
        </div>
        <div>
          <h5 class="fw-extrabold m-0 text-white fs-6">Anket Profilleri Yönetim Merkezi</h5>
          <p class="text-white-50 fs-8 m-0 mt-1">Her profil altında risk grupları, sorular ve otomatik iyileştirme tavsiyeleri bulunur.</p>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-7">
      <div class="row g-2">
        <div class="col-4">
          <div class="bg-white bg-opacity-10 p-3 rounded-3 text-center border border-white border-opacity-10">
            <span class="d-block fs-8 text-white-50 font-weight-bold">TOPLAM PROFİL</span>
            <span class="fw-extrabold fs-4 text-white"><?php echo $totalTemplates; ?></span>
          </div>
        </div>
        <div class="col-4">
          <div class="bg-white bg-opacity-10 p-3 rounded-3 text-center border border-white border-opacity-10">
            <span class="d-block fs-8 text-white-50 font-weight-bold">RİSK MADDESİ</span>
            <span class="fw-extrabold fs-4 text-success"><?php echo $totalQuestionsCount; ?></span>
          </div>
        </div>
        <div class="col-4">
          <div class="bg-white bg-opacity-10 p-3 rounded-3 text-center border border-white border-opacity-10">
            <span class="d-block fs-8 text-white-50 font-weight-bold">DENETİMLER</span>
            <span class="fw-extrabold fs-4 text-warning"><?php echo $totalAuditsCount; ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Canlı Arama Çubuğu -->
<div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
  <div class="input-group input-group-sm shadow-2xs" style="max-width: 360px;">
    <span class="input-group-text bg-white border-end-0 text-muted px-3"><i class="bi bi-search"></i></span>
    <input type="text" id="tplSearchInput" class="form-control border-start-0 py-2 fs-7" placeholder="Anket profili veya kategoriye göre ara...">
  </div>
  <span class="text-muted fs-8 font-weight-bold">Toplam <strong id="visibleTplCount" class="text-dark"><?php echo $totalTemplates; ?></strong> anket profili listeleniyor</span>
</div>

<!-- VISUAL ANKET PROFİLLERİ KART GRID LİSTESİ -->
<div class="row g-3" id="templatesGrid">
  <?php if (empty($templates)): ?>
    <div class="col-12">
      <div class="custom-card p-5 text-center bg-white border-0 shadow-sm rounded-4">
        <i class="bi bi-journal-x fs-1 text-muted d-block mb-3 opacity-40"></i>
        <h5 class="fw-extrabold text-dark m-0">Henüz Anket Profili Tanımlanmadı</h5>
        <p class="text-muted fs-7 mt-1">Saha denetimlerinde kullanılmak üzere sağ üstteki <strong>Yeni Anket Profili Oluştur</strong> butonundan başlayabilirsiniz.</p>
      </div>
    </div>
  <?php else: ?>
    <?php foreach ($templates as $tpl): ?>
      <div class="col-12 col-md-6 col-xl-4 tpl-card-wrapper" data-search="<?php echo mb_strtolower($tpl['title'] . ' ' . $tpl['category'] . ' ' . $tpl['description'], 'UTF-8'); ?>">
        <div class="tpl-card p-4 h-100 d-flex flex-column justify-content-between">
          
          <div>
            <!-- Kart Üst Başlık & Kategori Rozeti -->
            <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
              <div class="d-flex align-items-center gap-3">
                <div class="tpl-avatar">
                  <i class="bi bi-journal-check"></i>
                </div>
                <div>
                  <h6 class="fw-extrabold text-dark m-0 fs-6 leading-tight"><?php echo htmlspecialchars($tpl['title']); ?></h6>
                  <span class="badge bg-info-subtle text-info-emphasis font-weight-bold px-2.5 py-1 fs-8 rounded-pill mt-1">
                    <i class="bi bi-tag-fill me-1"></i> <?php echo htmlspecialchars($tpl['category']); ?>
                  </span>
                </div>
              </div>

              <?php if ($tpl['is_active']): ?>
                <span class="badge bg-success-subtle text-success font-weight-bold px-2.5 py-1 rounded-pill fs-8 flex-shrink-0">
                  <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Aktif
                </span>
              <?php endif; ?>
            </div>

            <!-- Açıklama Metni -->
            <p class="text-muted fs-7 mb-3 text-break" style="min-height: 40px; line-height: 1.4;">
              <?php echo htmlspecialchars($tpl['description'] ?? 'Anket profili açıklaması girilmemiş.'); ?>
            </p>

            <!-- Risk Maddesi ve Denetim İstatistik Rozetleri -->
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
              <span class="badge bg-light text-dark border px-3 py-1.5 rounded-pill fs-8 font-weight-bold">
                <i class="bi bi-list-check text-primary me-1"></i> <?php echo $tpl['question_count']; ?> Risk Maddesi
              </span>
              <span class="badge bg-light text-dark border px-3 py-1.5 rounded-pill fs-8 font-weight-bold">
                <i class="bi bi-clipboard2-check text-success me-1"></i> <?php echo $tpl['audit_count']; ?> Denetim
              </span>
            </div>
          </div>

          <!-- Alt İşlem Butonları -->
          <div class="pt-3 border-top d-flex align-items-center justify-content-between gap-2 mt-2">
            
            <a href="survey_edit.php?id=<?php echo $tpl['id']; ?>" class="btn btn-sm btn-primary-custom font-weight-bold px-3 py-2 rounded-3 shadow-xs fs-8" title="Soruları ve Risk Maddelerini Yönet">
              <i class="bi bi-sliders me-1"></i> Soruları Yönet
            </a>

            <div class="d-flex align-items-center gap-1.5">
              <button type="button" class="btn btn-sm btn-soft-primary px-2.5 py-1.5 rounded-3 fs-8" 
                      onclick="editTemplate(<?php echo htmlspecialchars(json_encode($tpl)); ?>)"
                      title="Profil Bilgilerini Düzenle">
                <i class="bi bi-pencil-square"></i>
              </button>
              
              <form method="POST" action="survey_templates.php" class="d-inline confirm-delete-form" data-confirm-title="Anket Profilini Sil" data-confirm-text="Bu anket profilini (<?php echo htmlspecialchars($tpl['title']); ?>) ve bağlı tüm sorularını silmek istediğinize emin misiniz?">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $tpl['id']; ?>">
                <button type="submit" class="btn btn-sm btn-soft-danger px-2.5 py-1.5 rounded-3 fs-8" title="Anket Profilini Sil">
                  <i class="bi bi-trash3-fill"></i>
                </button>
              </form>
            </div>
          </div>

        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- YENİ ANKET PROFİLİ EKLE MODAL -->
<div class="modal fade" id="addTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <form method="POST" action="survey_templates.php">
        <input type="hidden" name="action" value="add">
        <div class="modal-header bg-dark text-white p-3 px-4">
          <h5 class="modal-title fw-extrabold text-white fs-6"><i class="bi bi-journal-plus text-success me-2"></i> Yeni Anket Profili Tanımla</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Anket Profili Başlığı *</label>
            <input type="text" name="title" class="form-control form-control-lg fs-7" placeholder="Örn: Hastane İSG Saha Denetimi" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Kategori / Sektör</label>
            <input type="text" name="category" class="form-control" placeholder="Örn: Sağlık Tesisleri, Şantiye, Depo" value="Sağlık" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Açıklama / Amac</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Şablonun kullanıldığı saha türü ve genel kapsama alanı..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light p-3 px-4">
          <button type="button" class="btn btn-secondary font-weight-bold px-3" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-success font-weight-bold px-4">Oluştur ve Sorulara Geç</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ANKET PROFİLİ BİLGİLERİNİ DÜZENLE MODAL -->
<div class="modal fade" id="editTemplateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
      <form method="POST" action="survey_templates.php">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="edit_tpl_id">
        <div class="modal-header bg-dark text-white p-3 px-4">
          <h5 class="modal-title fw-extrabold text-white fs-6"><i class="bi bi-pencil-square text-primary me-2"></i> Anket Profil Bilgilerini Düzenle</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Anket Profili Başlığı *</label>
            <input type="text" name="title" id="edit_tpl_title" class="form-control form-control-lg fs-7" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Kategori / Sektör</label>
            <input type="text" name="category" id="edit_tpl_category" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold text-dark fs-8">Açıklama / Amac</label>
            <textarea name="description" id="edit_tpl_description" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light p-3 px-4">
          <button type="button" class="btn btn-secondary font-weight-bold px-3" data-bs-dismiss="modal">Vazgeç</button>
          <button type="submit" class="btn btn-primary font-weight-bold px-4">Güncelle</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function editTemplate(tpl) {
  document.getElementById('edit_tpl_id').value = tpl.id;
  document.getElementById('edit_tpl_title').value = tpl.title || '';
  document.getElementById('edit_tpl_category').value = tpl.category || '';
  document.getElementById('edit_tpl_description').value = tpl.description || '';
  
  const modal = new bootstrap.Modal(document.getElementById('editTemplateModal'));
  modal.show();
}

// Canlı Arama Filtrelemesi
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('tplSearchInput');
  const countDisplay = document.getElementById('visibleTplCount');
  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const q = this.value.toLowerCase().trim();
      const wrappers = document.querySelectorAll('.tpl-card-wrapper');
      let visible = 0;
      wrappers.forEach(w => {
        const text = w.dataset.search || '';
        if (text.includes(q)) {
          w.style.display = '';
          visible++;
        } else {
          w.style.display = 'none';
        }
      });
      if (countDisplay) countDisplay.textContent = visible;
    });
  }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
