<?php
/**
 * Tubİsg - Veritabanı Bağlantısı ve Otomatik Migration (MAMP / MySQL)
 */

define('DB_NAME', 'tubisg_db');
define('DB_USER', 'root');

$pdo = null;

// MAMP ve Standart MySQL Olası Bağlantı Yolları
$connectionAttempts = [
    // 1. MAMP macOS Varsayılan Unix Socket
    [
        'dsn' => "mysql:unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock;charset=utf8mb4",
        'user' => 'root',
        'pass' => 'root'
    ],
    // 2. MAMP Portu 8889
    [
        'dsn' => "mysql:host=127.0.0.1;port=8889;charset=utf8mb4",
        'user' => 'root',
        'pass' => 'root'
    ],
    // 3. Standart MySQL Portu 3306 (Parola 'root')
    [
        'dsn' => "mysql:host=127.0.0.1;port=3306;charset=utf8mb4",
        'user' => 'root',
        'pass' => 'root'
    ],
    // 4. Standart MySQL Portu 3306 (Parola boş '')
    [
        'dsn' => "mysql:host=127.0.0.1;port=3306;charset=utf8mb4",
        'user' => 'root',
        'pass' => ''
    ],
    // 5. Localhost Sockets
    [
        'dsn' => "mysql:host=localhost;charset=utf8mb4",
        'user' => 'root',
        'pass' => 'root'
    ],
    [
        'dsn' => "mysql:host=localhost;charset=utf8mb4",
        'user' => 'root',
        'pass' => ''
    ]
];

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$lastError = '';

foreach ($connectionAttempts as $attempt) {
    try {
        $pdo = new PDO($attempt['dsn'], $attempt['user'], $attempt['pass'], $pdoOptions);
        if ($pdo) {
            break; // Bağlantı başarılı!
        }
    } catch (PDOException $e) {
        $lastError = $e->getMessage();
    }
}

if (!$pdo) {
    die('<div style="font-family:sans-serif; padding:40px; text-align:center; background:#fff0f0; border-radius:12px; margin:50px auto; max-width:600px; box-shadow:0 10px 30px rgba(0,0,0,0.1); color:#c53030;">'
        . '<h2>⚠️ Veritabanı Bağlantı Hatası</h2>'
        . '<p>MAMP / MySQL sunucusuna bağlanılamadı. Lütfen MAMP uygulamasında MySQL Server\'ın açık olduğundan emin olun.</p>'
        . '<p><small>Hata Detayı: ' . htmlspecialchars($lastError) . '</small></p>'
        . '</div>');
}

try {
    // 2. Veritabanını kontrol et / oluştur
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    // 3. Tabloların varlığını kontrol et, yoksa database.sql ile otomatik kur
    $checkTable = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($checkTable->rowCount() === 0) {
        $sqlFile = __DIR__ . '/../database.sql';
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            $pdo->exec($sqlContent);
        }
    }

    // 4. system_logs Tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `system_logs` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `user_id` INT NULL,
          `username` VARCHAR(50) NULL,
          `action` VARCHAR(100) NOT NULL,
          `details` TEXT NULL,
          `ip_address` VARCHAR(45) NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 5. risk_groups Tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `risk_groups` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `group_name` VARCHAR(100) NOT NULL,
          `description` VARCHAR(255) NULL,
          `sort_order` INT DEFAULT 0,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Risk Grupları Boşsa Varsayılan Verileri Ekle
    $countGroups = $pdo->query("SELECT COUNT(*) FROM `risk_groups`")->fetchColumn();
    if ((int)$countGroups === 0) {
        $pdo->exec("
            INSERT INTO `risk_groups` (`id`, `group_name`, `description`, `sort_order`) VALUES
            (1, 'Biyolojik Riskler', 'Enfeksiyon, pis su bulaşması, bulaşıcı biyolojik etkenler', 1),
            (2, 'Ergonomik Riskler', 'Ekranlı araçlar, ayakta kalma, ağır kaldırma, kas-iskelet yükü', 2),
            (3, 'Fiziksel Riskler', 'Gürültü, aydınlatma, havalandırma, kaygan zemin, yüksekten düşme', 3),
            (4, 'Kimyasal Riskler', 'Tıbbi atıklar, dezenfektanlar, tehlikeli kimyasal maruziyeti', 4),
            (5, 'Psikososyal Riskler', 'Vardiyalı çalışma, iş stresi, aşırı iş yükü', 5),
            (6, 'Genel Saha & Hijyen Riskleri', 'Yangın tesisatı, acil çıkışlar, ilk yardım ve genel temizlik', 6);
        ");
    }

    // 6. survey_questions Tablosuna Eksik Sütunları Ekle (Migration)
    $qCols = $pdo->query("SHOW COLUMNS FROM `survey_questions`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('risk_group_id', $qCols)) {
        $pdo->exec("ALTER TABLE `survey_questions` ADD COLUMN `risk_group_id` INT NULL AFTER `template_id`");
    }
    if (!in_array('hazard_source', $qCols)) {
        $pdo->exec("ALTER TABLE `survey_questions` ADD COLUMN `hazard_source` VARCHAR(255) NULL AFTER `question_text`");
    }
    if (!in_array('hazard_name', $qCols)) {
        $pdo->exec("ALTER TABLE `survey_questions` ADD COLUMN `hazard_name` VARCHAR(255) NULL AFTER `hazard_source`");
    }
    if (!in_array('affected_risk', $qCols)) {
        $pdo->exec("ALTER TABLE `survey_questions` ADD COLUMN `affected_risk` TEXT NULL AFTER `hazard_name`");
    }
    if (!in_array('affected_people', $qCols)) {
        $pdo->exec("ALTER TABLE `survey_questions` ADD COLUMN `affected_people` VARCHAR(255) NULL AFTER `affected_risk`");
    }

    // 7. audit_answers Tablosuna Risk Analiz Sütunlarını Ekle (Migration)
    $aCols = $pdo->query("SHOW COLUMNS FROM `audit_answers`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('answer_option', $aCols)) {
        $pdo->exec("ALTER TABLE `audit_answers` ADD COLUMN `answer_option` VARCHAR(50) NULL AFTER `option_id`");
    }
    if (!in_array('current_status', $aCols)) {
        $pdo->exec("ALTER TABLE `audit_answers` ADD COLUMN `current_status` TEXT NULL AFTER `points_awarded`");
    }
    if (!in_array('probability', $aCols)) {
        $pdo->exec("ALTER TABLE `audit_answers` ADD COLUMN `probability` INT DEFAULT 1 AFTER `current_status`");
    }
    if (!in_array('severity', $aCols)) {
        $pdo->exec("ALTER TABLE `audit_answers` ADD COLUMN `severity` INT DEFAULT 1 AFTER `probability`");
    }
    if (!in_array('risk_score', $aCols)) {
        $pdo->exec("ALTER TABLE `audit_answers` ADD COLUMN `risk_score` INT DEFAULT 1 AFTER `severity`");
    }
    if (!in_array('action_plan', $aCols)) {
        $pdo->exec("ALTER TABLE `audit_answers` ADD COLUMN `action_plan` TEXT NULL AFTER `risk_score`");
    }
    if (!in_array('responsible_person', $aCols)) {
        $pdo->exec("ALTER TABLE `audit_answers` ADD COLUMN `responsible_person` VARCHAR(255) NULL AFTER `action_plan`");
    }
    if (!in_array('deadline', $aCols)) {
        $pdo->exec("ALTER TABLE `audit_answers` ADD COLUMN `deadline` VARCHAR(100) NULL AFTER `responsible_person`");
    }

} catch (PDOException $e) {
    die('<div style="font-family:sans-serif; padding:40px; text-align:center; background:#fff0f0; border-radius:12px; margin:50px auto; max-width:600px; color:#c53030;">'
        . '<h2>⚠️ Veritabanı Kurulum Hatası</h2>'
        . '<p>' . htmlspecialchars($e->getMessage()) . '</p>'
        . '</div>');
}

/**
 * Global PDO erişim yardımcısı
 * @return PDO
 */
function getDB() {
    global $pdo;
    return $pdo;
}
