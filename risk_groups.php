<?php
/**
 * Tubİsg - Risk Grupları Yönetim Modülü (Ergonomik, Biyolojik, Fiziksel vb.)
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('surveys_manage');

$db = getDB();

// Form İşlemleri (Ekleme / Düzenleme / Silme)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $group_name = trim($_POST['group_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if (!empty($group_name)) {
            $stmt = $db->prepare("INSERT INTO risk_groups (group_name, description, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$group_name, $description, $sort_order]);
            $newId = $db->lastInsertId();
            log_action('Risk Grubu Eklendi', "Yeni risk grubu: {$group_name} (ID: #{$newId})");
            set_flash('success', 'Risk grubu başarıyla eklendi.');
        } else {
            set_flash('danger', 'Risk grubu adı boş bırakılamaz.');
        }
        header("Location: risk_groups.php");
        exit;
    }

    if ($_POST['action'] === 'edit') {
        $id = (int)$_POST['id'];
        $group_name = trim($_POST['group_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if (!empty($group_name) && $id > 0) {
            $stmt = $db->prepare("UPDATE risk_groups SET group_name = ?, description = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$group_name, $description, $sort_order, $id]);
            log_action('Risk Grubu Güncellendi', "Risk grubu: {$group_name} (ID: #{$id}) güncellendi.");
            set_flash('success', 'Risk grubu bilgileri güncellendi.');
        }
        header("Location: risk_groups.php");
        exit;
    }

    if ($_POST['action'] === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $targetStmt = $db->prepare("SELECT * FROM risk_groups WHERE id = ?");
            $targetStmt->execute([$id]);
            $g = $targetStmt->fetch();

            if ($g) {
                // Sorulardaki risk_group_id referansını sıfırla
                $db->prepare("UPDATE survey_questions SET risk_group_id = NULL WHERE risk_group_id = ?")->execute([$id]);

                $stmt = $db->prepare("DELETE FROM risk_groups WHERE id = ?");
                $stmt->execute([$id]);

                log_action('Risk Grubu Silindi', "Risk grubu: {$g['group_name']} (ID: #{$id}) silindi.");
                set_flash('success', "Risk grubu ({$g['group_name']}) başarıyla silindi.");
            }
        }
        header("Location: risk_groups.php");
        exit;
    }
}

// Risk Gruplarını ve Sorularının Sayısını Çek
$groups = $db->query("
    SELECT rg.*, COUNT(sq.id) as question_count
    FROM risk_groups rg
    LEFT JOIN survey_questions sq ON rg.id = sq.risk_group_id
    GROUP BY rg.id
    ORDER BY rg.sort_order ASC, rg.group_name ASC
")->fetchAll();

$pageTitle = 'İSG Risk Grupları Tanımları';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h3 class="fw-extrabold m-0">İSG Risk Grupları</h3>
    <p class="text-muted fs-7 m-0">Ergonomik, Biyolojik, Fiziksel ve Kimyasal risk kategorileri yönetimi</p>
  </div>
  <button type="button" class="btn btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addRiskGroupModal">
    <i class="bi bi-plus-lg"></i> Yeni Risk Grubu Ekle
  </button>
</div>

<div class="custom-card">
  <div class="table-responsive">
    <table class="table table-hover align-middle m-0">
      <thead class="table-light fs-8 text-uppercase text-muted">
        <tr>
          <th style="width:70px;">Sıra</th>
          <th>Risk Grubu Adı</th>
          <th>Açıklama</th>
          <th>Tarihli Soru Sayısı</th>
          <th class="text-end">İşlem</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($groups)): ?>
          <tr>
            <td colspan="5" class="text-center py-4 text-muted">Henüz hiç risk grubu tanımlanmamış.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($groups as $g): ?>
            <tr>
              <td class="fw-bold text-muted"><?php echo (int)$g['sort_order']; ?></td>
              <td class="fw-bold text-dark">
                <span class="badge bg-primary-light text-primary p-2 me-1">
                  <i class="bi bi-exclamation-triangle-fill"></i>
                </span>
                <?php echo htmlspecialchars($g['group_name']); ?>
              </td>
              <td class="text-muted fs-7"><?php echo htmlspecialchars($g['description'] ?? '-'); ?></td>
              <td>
                <span class="badge bg-light text-dark border font-weight-bold">
                  <?php echo $g['question_count']; ?> Soru
                </span>
              </td>
              <td class="text-end">
                <button class="btn btn-sm btn-light text-primary me-1" data-bs-toggle="modal" data-bs-target="#editRiskGroupModal<?php echo $g['id']; ?>" title="Düzenle">
                  <i class="bi bi-pencil-fill"></i>
                </button>
                <form method="POST" action="risk_groups.php" class="d-inline confirm-delete-form" data-confirm-title="Risk Grubunu Sil" data-confirm-text="Bu risk grubunu (<?php echo htmlspecialchars($g['group_name']); ?>) silmek istediğinize emin misiniz? Sorular grubundan çıkarılacaktır.">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-light text-danger" title="Sil">
                    <i class="bi bi-trash-fill"></i>
                  </button>
                </form>
              </td>
            </tr>

            <!-- Risk Grubu Düzenleme Modal -->
            <div class="modal fade" id="editRiskGroupModal<?php echo $g['id']; ?>" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <form method="POST" action="risk_groups.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $g['id']; ?>">
                    <div class="modal-header">
                      <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-success"></i> Risk Grubu Düzenle</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                      <div class="mb-3">
                        <label class="form-label fw-bold">Risk Grubu Adı</label>
                        <input type="text" name="group_name" class="form-control" value="<?php echo htmlspecialchars($g['group_name']); ?>" required>
                      </div>
                      <div class="mb-3">
                        <label class="form-label fw-bold">Açıklama</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($g['description'] ?? ''); ?></textarea>
                      </div>
                      <div class="mb-3">
                        <label class="form-label fw-bold">Sıralama Önceliği</label>
                        <input type="number" name="sort_order" class="form-control" value="<?php echo (int)$g['sort_order']; ?>">
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

<!-- Yeni Risk Grubu Ekle Modal -->
<div class="modal fade" id="addRiskGroupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="risk_groups.php">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill text-success"></i> Yeni İSG Risk Grubu Ekle</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Risk Grubu Adı</label>
            <input type="text" name="group_name" class="form-control" placeholder="Örn: Ergonomik Riskler" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Açıklama (Opsiyonel)</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Örn: Ekranlı araçlar, ayakta kalma, ağır kaldırma vb."></textarea>
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
