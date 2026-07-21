<?php
/**
 * Tubİsg - Rapor Dışa Aktarma Motoru (PDF, Excel, Word Export Engine)
 */
require_once __DIR__ . '/includes/auth.php';
require_permission('reports_export');

$db = getDB();
$audit_id = (int)($_GET['id'] ?? 0);
$format = strtolower(trim($_GET['format'] ?? 'pdf'));

if ($audit_id <= 0) {
    die("Geçersiz denetim ID.");
}

// Denetim Detayını Çek
$stmt = $db->prepare("
    SELECT a.*, u.unit_name, u.description as unit_desc, st.title as survey_title, st.category as survey_cat, usr.name_surname as auditor_name
    FROM audits a
    JOIN units u ON a.unit_id = u.id
    JOIN survey_templates st ON a.template_id = st.id
    JOIN users usr ON a.auditor_id = usr.id
    WHERE a.id = ?
");
$stmt->execute([$audit_id]);
$audit = $stmt->fetch();

if (!$audit) {
    die("Denetim kaydı bulunamadı.");
}

// Cevapları Çek
$answersStmt = $db->prepare("
    SELECT ans.*, sq.question_text, qo.option_text, qo.points
    FROM audit_answers ans
    JOIN survey_questions sq ON ans.question_id = sq.id
    JOIN question_options qo ON ans.option_id = qo.id
    WHERE ans.audit_id = ?
    ORDER BY sq.sort_order ASC, sq.id ASC
");
$answersStmt->execute([$audit_id]);
$answers = $answersStmt->fetchAll();

$fileName = 'TubISG_Denetim_' . sprintf('%04d', $audit['id']) . '_' . date('Ymd');

// ==========================================
// 1. EXCEL EXPORT (.xls / UTF-8 CSV)
// ==========================================
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '.xls"');
    header('Cache-Control: max-age=0');

    // UTF-8 BOM ekle (Excel'de Türkçe karakter sorunu olmaması için)
    echo "\xEF\xBB\xBF";
    ?>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
      table { border-collapse: collapse; width: 100%; font-family: sans-serif; }
      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background-color: #059669; color: white; }
      .header-bg { background-color: #0f172a; color: white; font-weight: bold; }
    </style>

    <table>
      <tr class="header-bg">
        <td colspan="4">Tubİsg - Saha İş Sağlığı ve Güvenliği Denetim Raporu</td>
      </tr>
      <tr>
        <td><strong>Denetim No:</strong></td>
        <td>#DEN-<?php echo sprintf('%04d', $audit['id']); ?></td>
        <td><strong>Tarih:</strong></td>
        <td><?php echo date('d.m.Y H:i', strtotime($audit['audit_date'])); ?></td>
      </tr>
      <tr>
        <td><strong>Birim / Saha:</strong></td>
        <td><?php echo htmlspecialchars($audit['unit_name']); ?></td>
        <td><strong>Denetçi:</strong></td>
        <td><?php echo htmlspecialchars($audit['auditor_name']); ?></td>
      </tr>
      <tr>
        <td><strong>Anket Profili:</strong></td>
        <td><?php echo htmlspecialchars($audit['survey_title']); ?></td>
        <td><strong>Genel Skor:</strong></td>
        <td>%<?php echo number_format($audit['percentage_score'], 1); ?> (<?php echo $audit['total_score']; ?> / <?php echo $audit['max_possible_score']; ?> Puan)</td>
      </tr>
    </table>

    <br/>

    <table>
      <thead>
        <tr>
          <th>Soru</th>
          <th>Seçilen Cevap / Şık</th>
          <th>Kazanılan Puan</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($answers as $ans): ?>
          <tr>
            <td><?php echo htmlspecialchars($ans['question_text']); ?></td>
            <td><?php echo htmlspecialchars($ans['option_text']); ?></td>
            <td><?php echo (int)$ans['points_awarded']; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if (!empty($audit['notes'])): ?>
      <br/>
      <table>
        <tr>
          <th colspan="2">Saha Notları</th>
        </tr>
        <tr>
          <td colspan="2"><?php echo htmlspecialchars($audit['notes']); ?></td>
        </tr>
      </table>
    <?php endif; ?>
    <?php
    exit;
}

// ==========================================
// 2. WORD EXPORT (.doc)
// ==========================================
if ($format === 'word') {
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '.doc"');
    header('Cache-Control: max-age=0');

    echo "\xEF\xBB\xBF";
    ?>
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
      <meta charset='utf-8'>
      <title>Denetim Raporu</title>
      <style>
        body { font-family: Arial, sans-serif; line-height: 1.5; color: #333; }
        h2 { color: #059669; }
        .score-box { background: #f8fafc; border: 2px solid #059669; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th, td { border: 1px solid #cbd5e1; padding: 10px; text-align: left; }
        th { background: #0f172a; color: #fff; }
      </style>
    </head>
    <body>
      <h2>🛡️ Tubİsg Saha İSG Denetim Karnesi</h2>
      
      <div class="score-box">
        <p><strong>Rapor No:</strong> #DEN-<?php echo sprintf('%04d', $audit['id']); ?></p>
        <p><strong>Birim / Departman:</strong> <?php echo htmlspecialchars($audit['unit_name']); ?></p>
        <p><strong>Anket Profili:</strong> <?php echo htmlspecialchars($audit['survey_title']); ?></p>
        <p><strong>Denetimi Yapan:</strong> <?php echo htmlspecialchars($audit['auditor_name']); ?></p>
        <p><strong>Tarih:</strong> <?php echo date('d.m.Y H:i', strtotime($audit['audit_date'])); ?></p>
        <p><strong>İSG Uygunluk Skoru:</strong> %<?php echo number_format($audit['percentage_score'], 1); ?> (Toplanan Puan: <?php echo $audit['total_score']; ?> / <?php echo $audit['max_possible_score']; ?>)</p>
      </div>

      <h3>Denetim Cevapları ve Puanlandırma</h3>
      <table>
        <thead>
          <tr>
            <th>Denetim Sorusu</th>
            <th>Tespit Edilen / Seçilen Şık</th>
            <th>Puan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($answers as $ans): ?>
            <tr>
              <td><?php echo htmlspecialchars($ans['question_text']); ?></td>
              <td><?php echo htmlspecialchars($ans['option_text']); ?></td>
              <td><?php echo (int)$ans['points_awarded']; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <?php if (!empty($audit['notes'])): ?>
        <h3>Saha Gözlem Notları</h3>
        <p><?php echo nl2br(htmlspecialchars($audit['notes'])); ?></p>
      <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

// ==========================================
// 3. PDF EXPORT (Yazdırılabilir PDF HTML Görünümü)
// ==========================================
header("Location: audit_detail.php?id=" . $audit_id . "&print=1");
exit;
