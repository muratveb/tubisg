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

    // 6. risk_libraries Tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `risk_libraries` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `category` ENUM('hazard_source', 'hazard_name', 'affected_people', 'responsible_person', 'action_recommendation') NOT NULL,
          `item_text` TEXT NOT NULL,
          `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Kütüphaneler Boşsa Varsayılan Verileri Ekle
    $countLib = $pdo->query("SELECT COUNT(*) FROM `risk_libraries`")->fetchColumn();
    if ((int)$countLib === 0) {
        $pdo->exec("
            INSERT INTO `risk_libraries` (`category`, `item_text`) VALUES
            ('hazard_source', 'Lavabo, Wc tavanı'),
            ('hazard_source', 'Ekranlı Araçlar (Bilgisayar vb.)'),
            ('hazard_source', 'Çalışma alanı'),
            ('hazard_source', 'Tıbbi atık alanı ve jeneratör dairesi'),
            ('hazard_name', 'Enfeksiyon'),
            ('hazard_name', 'Uzun süre sabit oturma'),
            ('hazard_name', 'Ayakta kalma'),
            ('hazard_name', 'Kaygan zemin ve kimyasal maruziyeti'),
            ('affected_people', 'Çalışanlar(Doktor, Hemşire, Sağ. Tek. hasta bakıcı, temizlik çalışanı vd.)Hasta ve hasta yakını'),
            ('responsible_person', 'Tekn. Hiz. Yön.'),
            ('responsible_person', 'Mali Hiz. Yön.'),
            ('responsible_person', 'Çalışan'),
            ('responsible_person', 'İSG Birimi'),
            ('action_recommendation', 'Lavabo(wc) tavanlarda gerekli yalıtımın sağlanması'),
            ('action_recommendation', 'Çalışma araları verilmeli, boyun egzersizleri yapılmalı, ortopedik Mouse pedleri kullanılmalı'),
            ('action_recommendation', 'Kısa mola ve dinlenmeler yapılmalı, Uzun süre ayakta kalınmamalı, egzersiz ve ara dinlenmeler verilmeli');
        ");
    }

    // 7. question_options Tablosuna trigger_action Sütununu Ekle
    $optCols = $pdo->query("SHOW COLUMNS FROM `question_options`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('trigger_action', $optCols)) {
        $pdo->exec("ALTER TABLE `question_options` ADD COLUMN `trigger_action` TINYINT(1) DEFAULT 0 AFTER `points`");
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
