<?php
/**
 * Tubİsg - Rapor Dışa Aktarma Motoru (Birim Bazlı Risk Analiz Formu Excel, Word & PDF Export Engine)
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

// Cevapları ve Risk Matrisi Bilgilerini Çek
$answersStmt = $db->prepare("
    SELECT ans.*, sq.question_text, sq.hazard_source, sq.hazard_name, sq.affected_risk, sq.affected_people, 
           sq.default_responsible, sq.default_deadline, rg.group_name, qo.option_text
    FROM audit_answers ans
    JOIN survey_questions sq ON ans.question_id = sq.id
    LEFT JOIN risk_groups rg ON sq.risk_group_id = rg.id
    LEFT JOIN question_options qo ON ans.option_id = qo.id
    WHERE ans.audit_id = ?
    ORDER BY COALESCE(rg.sort_order, 99) ASC, sq.sort_order ASC, sq.id ASC
");
$answersStmt->execute([$audit_id]);
$answers = $answersStmt->fetchAll();

// Risk Gruplarına Göre Grupla
$groupedAnswersList = [];
foreach ($answers as $ans) {
    $gName = !empty($ans['group_name']) ? $ans['group_name'] : 'Genel Riskler';
    $groupedAnswersList[$gName][] = $ans;
}

$fileName = 'TubISG_Birim_Risk_Analizi_#DEN-' . sprintf('%04d', $audit['id']) . '_' . date('Ymd');

// ==========================================
// 1. EXCEL EXPORT (.xls / UTF-8 HTML Table)
// ==========================================
if ($format === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '.xls"');
    header('Cache-Control: max-age=0');

    echo "\xEF\xBB\xBF";
    ?>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
      table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }
      th, td { border: 1px solid #94a3b8; padding: 6px; text-align: left; vertical-align: middle; }
      th { background-color: #0f172a; color: white; font-weight: bold; text-align: center; }
      .vhead-th { writing-mode: vertical-rl; mso-direction-alt: bottom-to-top; white-space: nowrap; height: 110px; vertical-align: middle; text-align: center; font-size: 10px; padding: 6px; }
      .rg-vcell { writing-mode: vertical-rl; mso-direction-alt: bottom-to-top; white-space: nowrap; text-align: center; vertical-align: middle; font-weight: bold; background-color: #f1f5f9; padding: 10px; }
      .header-title { background-color: #059669; color: white; font-size: 14px; font-weight: bold; text-align: center; }
      .risk-high { background-color: #fee2e2; color: #991b1b; font-weight: bold; text-align: center; }
      .risk-medium { background-color: #fef3c7; color: #92400e; font-weight: bold; text-align: center; }
      .risk-low { background-color: #dcfce7; color: #166534; font-weight: bold; text-align: center; }
    </style>

    <table>
      <tr class="header-title">
        <td colspan="12">İŞ YERİ SAĞLIK VE GÜVENLİK BİRİMİ - BİRİM BAZLI RİSK ANALİZ VE DENETİM FORMU</td>
      </tr>
      <tr>
        <td colspan="4"><strong>RİSK ANALİZİ YAPILAN YER:</strong> <?php echo htmlspecialchars($audit['unit_name']); ?></td>
        <td colspan="4"><strong>TARİH:</strong> <?php echo date('d.m.Y H:i', strtotime($audit['audit_date'])); ?></td>
        <td colspan="4"><strong>DENETÇİ:</strong> <?php echo htmlspecialchars($audit['auditor_name']); ?></td>
      </tr>
      <tr>
        <td colspan="12" style="background-color: #f8fafc; font-size: 10px;">
          Şiddet (Ş): 1 (Çok hafif), 2 (Hafif), 3 (Ciddi), 4 (Çok Ciddi), 5 (Felaket) | Olasılık (O): 1 (Çok küçük), 2 (Küçük), 3 (Orta), 4 (Yüksek), 5 (Çok yüksek) | Risk Skoru (R = O x Ş)
        </td>
      </tr>
    </table>

    <br/>

    <table>
      <thead>
        <tr>
          <th class="vhead-th">RİSK GRUPLARI</th>
          <th>TEHLİKE KAYNAĞI</th>
          <th>TEHLİKE</th>
          <th>ETKİLENME (YAŞANABİLECEK RİSKLER)</th>
          <th>ETKİLENENLER</th>
          <th>MEVCUT DURUM / CEVAP</th>
          <th class="vhead-th">OLASILIK (O)</th>
          <th class="vhead-th">ŞİDDET (Ş)</th>
          <th class="vhead-th">RİSK DERECESİ (R)</th>
          <th>ALINACAK ÖNLEMLER / İYİLEŞTİRMELER</th>
          <th>SORUMLU</th>
          <th>BAŞLAMA / SÜRE</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($groupedAnswersList as $groupName => $gAnswers): ?>
          <?php $gCount = count($gAnswers); ?>
          <?php foreach ($gAnswers as $idx => $ans): ?>
            <?php
            $rScore = (int)$ans['risk_score'];
            $prob = (int)$ans['probability'];
            $sev = (int)$ans['severity'];
            if ($rScore == 0 && $prob > 0 && $sev > 0) $rScore = $prob * $sev;

            $ansOption = !empty($ans['answer_option']) ? $ans['answer_option'] : ($ans['option_text'] ?? 'Evet (Uygun)');
            $isEvet = (strpos($ansOption, 'Evet') !== false);
            $isMuaf = (strpos($ansOption, 'Denetim Dışı') !== false || strpos($ansOption, 'Muaf') !== false);

            if ($isEvet) {
                $rClass = 'risk-low';
                $statusDisplay = '';
                $actionDisplay = !empty($ans['action_plan']) && strpos($ans['action_plan'], 'girilecek') === false ? htmlspecialchars($ans['action_plan']) : 'Gerekli Önlemler Alınmış';
                $probDisplay = 1;
                $sevDisplay = 1;
                $rScoreDisplay = 1;
            } elseif ($isMuaf) {
                $rClass = 'risk-low';
                $statusDisplay = '';
                $actionDisplay = 'Muaf';
                $probDisplay = '-';
                $sevDisplay = '-';
                $rScoreDisplay = '-';
            } else {
                $rClass = 'risk-low';
                if ($rScore >= 16) $rClass = 'risk-high';
                elseif ($rScore >= 6) $rClass = 'risk-medium';

                $statusDisplay = !empty($ans['current_status']) && strpos($ans['current_status'], 'girilecek') === false ? htmlspecialchars($ans['current_status']) : 'Tespit Edilen Eksiklik Var';
                $actionDisplay = !empty($ans['action_plan']) && strpos($ans['action_plan'], 'girilecek') === false ? htmlspecialchars($ans['action_plan']) : 'İyileştirme Yapılmalı';
                $probDisplay = $prob > 0 ? $prob : '-';
                $sevDisplay = $sev > 0 ? $sev : '-';
                $rScoreDisplay = $rScore > 0 ? $rScore : '-';
            }

            $responsibleDisplay = !empty($ans['responsible_person']) ? $ans['responsible_person'] : (!empty($ans['default_responsible']) ? $ans['default_responsible'] : 'İşveren');
            $deadlineDisplay = !empty($ans['deadline']) ? $ans['deadline'] : (!empty($ans['default_deadline']) ? $ans['default_deadline'] : 'Sürekli');
            ?>
            <tr>
              <?php if ($idx === 0): ?>
                <td rowspan="<?php echo $gCount; ?>" class="rg-vcell"><?php echo htmlspecialchars($groupName); ?></td>
              <?php endif; ?>
              <td><?php echo htmlspecialchars($ans['hazard_source'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($ans['hazard_name'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($ans['affected_risk'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($ans['affected_people'] ?? '-'); ?></td>
              <td>
                <strong><?php echo htmlspecialchars($ans['question_text']); ?></strong><br>
                Cevap: [<?php echo htmlspecialchars($ansOption); ?>]
                <?php if (!$isEvet && !$isMuaf && !empty($statusDisplay)): ?>
                  <br><em><?php echo $statusDisplay; ?></em>
                <?php endif; ?>
              </td>
              <td style="text-align:center;"><?php echo $probDisplay; ?></td>
              <td style="text-align:center;"><?php echo $sevDisplay; ?></td>
              <td class="<?php echo $rClass; ?>"><?php echo $rScoreDisplay; ?></td>
              <td><?php echo $actionDisplay; ?></td>
              <td><?php echo htmlspecialchars($responsibleDisplay); ?></td>
              <td><?php echo htmlspecialchars($deadlineDisplay); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if (!empty($audit['notes'])): ?>
      <br/>
      <table>
        <tr>
          <th colspan="12">Saha Notları ve Gözlemler</th>
        </tr>
        <tr>
          <td colspan="12"><?php echo htmlspecialchars($audit['notes']); ?></td>
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
      <title>Birim Bazlı Risk Analiz Formu</title>
      <style>
        body { font-family: Arial, sans-serif; line-height: 1.4; color: #333; font-size: 11px; }
        h2 { color: #059669; text-align: center; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .info-table td { border: 1px solid #0f172a; padding: 6px; }
        table.matrix-table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        table.matrix-table th, table.matrix-table td { border: 1px solid #cbd5e1; padding: 6px; text-align: left; }
        table.matrix-table th { background: #0f172a; color: #fff; text-align: center; font-size: 10px; }
        .vhead-th { writing-mode: vertical-rl; white-space: nowrap; height: 110px; vertical-align: middle; text-align: center; font-size: 10px; padding: 6px; }
        .rg-vcell { writing-mode: vertical-rl; white-space: nowrap; text-align: center; vertical-align: middle; font-weight: bold; background-color: #f1f5f9; padding: 10px; }
      </style>
    </head>
    <body>
      <h2>🛡️ İŞ YERİ SAĞLIK VE GÜVENLİK BİRİMİ - BİRİM BAZLI RİSK ANALİZ FORMU</h2>
      
      <table class="info-table">
        <tr>
          <td><strong>Form No:</strong> #DEN-<?php echo sprintf('%04d', $audit['id']); ?></td>
          <td><strong>Birim / Saha:</strong> <?php echo htmlspecialchars($audit['unit_name']); ?></td>
          <td><strong>Tarih:</strong> <?php echo date('d.m.Y H:i', strtotime($audit['audit_date'])); ?></td>
          <td><strong>Denetçi:</strong> <?php echo htmlspecialchars($audit['auditor_name']); ?></td>
        </tr>
      </table>

      <table class="matrix-table">
        <thead>
          <tr>
            <th class="vhead-th">RİSK GRUP</th>
            <th>TEHLİKE KAYNAĞI</th>
            <th>TEHLİKE</th>
            <th>ETKİLENME (RİSKLER)</th>
            <th>ETKİLENENLER</th>
            <th>MEVCUT DURUM</th>
            <th class="vhead-th">OLASILIK (O)</th>
            <th class="vhead-th">ŞİDDET (Ş)</th>
            <th class="vhead-th">RİSK DERECESİ (R)</th>
            <th>ALINACAK ÖNLEMLER</th>
            <th>SORUMLU</th>
            <th>TERMİN</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($groupedAnswersList as $groupName => $gAnswers): ?>
            <?php $gCount = count($gAnswers); ?>
            <?php foreach ($gAnswers as $idx => $ans): ?>
              <?php
              $rScore = (int)$ans['risk_score'];
              $prob = (int)$ans['probability'];
              $sev = (int)$ans['severity'];
              if ($rScore == 0 && $prob > 0 && $sev > 0) $rScore = $prob * $sev;
              
              $ansOption = !empty($ans['answer_option']) ? $ans['answer_option'] : ($ans['option_text'] ?? 'Evet (Uygun)');
              $isEvet = (strpos($ansOption, 'Evet') !== false);
              $isMuaf = (strpos($ansOption, 'Denetim Dışı') !== false || strpos($ansOption, 'Muaf') !== false);

              if ($isEvet) {
                  $statusDisplay = '';
                  $actionDisplay = !empty($ans['action_plan']) && strpos($ans['action_plan'], 'girilecek') === false ? htmlspecialchars($ans['action_plan']) : 'Gerekli Önlemler Alınmış';
                  $probDisplay = 1;
                  $sevDisplay = 1;
                  $rScoreDisplay = 1;
              } elseif ($isMuaf) {
                  $statusDisplay = '';
                  $actionDisplay = 'Muaf';
                  $probDisplay = '-';
                  $sevDisplay = '-';
                  $rScoreDisplay = '-';
              } else {
                  $statusDisplay = !empty($ans['current_status']) && strpos($ans['current_status'], 'girilecek') === false ? htmlspecialchars($ans['current_status']) : 'Tespit Edilen Eksiklik Var';
                  $actionDisplay = !empty($ans['action_plan']) && strpos($ans['action_plan'], 'girilecek') === false ? htmlspecialchars($ans['action_plan']) : 'İyileştirme Yapılmalı';
                  $probDisplay = $prob > 0 ? $prob : '-';
                  $sevDisplay = $sev > 0 ? $sev : '-';
                  $rScoreDisplay = $rScore > 0 ? $rScore : '-';
              }

              $responsibleDisplay = !empty($ans['responsible_person']) ? $ans['responsible_person'] : (!empty($ans['default_responsible']) ? $ans['default_responsible'] : 'İşveren');
              $deadlineDisplay = !empty($ans['deadline']) ? $ans['deadline'] : (!empty($ans['default_deadline']) ? $ans['default_deadline'] : 'Sürekli');
              ?>
              <tr>
                <?php if ($idx === 0): ?>
                  <td rowspan="<?php echo $gCount; ?>" class="rg-vcell"><?php echo htmlspecialchars($groupName); ?></td>
                <?php endif; ?>
                <td><?php echo htmlspecialchars($ans['hazard_source'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($ans['hazard_name'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($ans['affected_risk'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($ans['affected_people'] ?? '-'); ?></td>
                <td>
                  <?php echo htmlspecialchars($ans['question_text']); ?><br>
                  <strong>Cevap: [<?php echo htmlspecialchars($ansOption); ?>]</strong>
                  <?php if (!$isEvet && !$isMuaf && !empty($statusDisplay)): ?>
                    <br><em><?php echo $statusDisplay; ?></em>
                  <?php endif; ?>
                </td>
                <td style="text-align:center;"><?php echo $probDisplay; ?></td>
                <td style="text-align:center;"><?php echo $sevDisplay; ?></td>
                <td style="text-align:center; font-weight:bold;"><?php echo $rScoreDisplay; ?></td>
                <td><?php echo $actionDisplay; ?></td>
                <td><?php echo htmlspecialchars($responsibleDisplay); ?></td>
                <td><?php echo htmlspecialchars($deadlineDisplay); ?></td>
              </tr>
            <?php endforeach; ?>
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
// 3. PDF EXPORT
// ==========================================
header("Location: audit_detail.php?id=" . $audit_id . "&download_pdf=1");
exit;
