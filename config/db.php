<?php
/**
 * Tubİsg - Veritabanı Bağlantısı ve Otomatik Migration (MAMP / MySQL)
 */

define('DB_NAME', 'tubisg_db');
define('DB_USER', 'root');

$pdo = null;

// MAMP ve Standart MySQL Olası Bağlantı Yolları
$connectionAttempts = [
    [
        'dsn' => "mysql:unix_socket=/Applications/MAMP/tmp/mysql/mysql.sock;charset=utf8mb4",
        'user' => 'root',
        'pass' => 'root'
    ],
    [
        'dsn' => "mysql:host=127.0.0.1;port=8889;charset=utf8mb4",
        'user' => 'root',
        'pass' => 'root'
    ],
    [
        'dsn' => "mysql:host=127.0.0.1;port=3306;charset=utf8mb4",
        'user' => 'root',
        'pass' => 'root'
    ],
    [
        'dsn' => "mysql:host=127.0.0.1;port=3306;charset=utf8mb4",
        'user' => 'root',
        'pass' => ''
    ],
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
            break;
        }
    } catch (PDOException $e) {
        $lastError = $e->getMessage();
    }
}

if (!$pdo) {
    die('<div style="font-family:sans-serif; padding:40px; text-align:center; background:#fff0f0; border-radius:12px; margin:50px auto; max-width:600px; color:#c53030;">'
        . '<h2>⚠️ Veritabanı Bağlantı Hatası</h2>'
        . '<p>MAMP / MySQL sunucusuna bağlanılamadı. Lütfen MAMP uygulamasında MySQL Server\'ın açık olduğundan emin olun.</p>'
        . '<p><small>Hata Detayı: ' . htmlspecialchars($lastError) . '</small></p>'
        . '</div>');
}

try {
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

    $checkTable = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($checkTable->rowCount() === 0) {
        $sqlFile = __DIR__ . '/../database.sql';
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            $pdo->exec($sqlContent);
        }
    }

    // Migration Check for survey_questions table columns
    $cols = $pdo->query("SHOW COLUMNS FROM survey_questions")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('current_status', $cols)) {
        $pdo->exec("ALTER TABLE survey_questions ADD COLUMN current_status TEXT NULL AFTER affected_people");
    }
    if (!in_array('default_probability', $cols)) {
        $pdo->exec("ALTER TABLE survey_questions ADD COLUMN default_probability INT DEFAULT 2 AFTER current_status");
    }
    if (!in_array('default_severity', $cols)) {
        $pdo->exec("ALTER TABLE survey_questions ADD COLUMN default_severity INT DEFAULT 3 AFTER default_probability");
    }
    if (!in_array('default_action_plan', $cols)) {
        $pdo->exec("ALTER TABLE survey_questions ADD COLUMN default_action_plan TEXT NULL AFTER default_severity");
    }
    if (!in_array('default_responsible', $cols)) {
        $pdo->exec("ALTER TABLE survey_questions ADD COLUMN default_responsible VARCHAR(255) NULL AFTER default_action_plan");
    }
    if (!in_array('default_deadline', $cols)) {
        $pdo->exec("ALTER TABLE survey_questions ADD COLUMN default_deadline VARCHAR(100) NULL AFTER default_responsible");
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
