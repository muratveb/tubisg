<?php
/**
 * Tubİsg - Veritabanı Bağlantısı (MAMP / Standart MySQL Otomatik Port ve Socket Algılayıcı)
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
