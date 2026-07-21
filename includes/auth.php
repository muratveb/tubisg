<?php
/**
 * Tubİsg - Oturum Yönetimi, Sistem Logları & Rol Tabanlı Yetkilendirme (RBAC) Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

/**
 * Kullanıcı oturum açmış mı?
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Oturum açma zorunluluğu getiren güvenlik kontrolü
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Mevcut giriş yapmış kullanıcının detaylarını döndürür
 */
function get_current_user_data() {
    if (!is_logged_in()) return null;

    static $currentUserData = null;
    if ($currentUserData !== null) return $currentUserData;

    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.*, r.role_name, r.permissions 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ? AND u.is_active = 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // Kullanıcı silinmiş veya pasif yapılmışsa oturumu sonlandır
        logout_user();
        header("Location: login.php?error=inactive");
        exit;
    }

    $currentUserData = $user;
    return $user;
}

/**
 * Rol tabanlı yetki kontrol fonksiyonu
 * @param string $permission_key (Örn: 'surveys_manage', 'units_manage', 'audit_conduct', 'audit_delete', 'logs_view')
 * @return bool
 */
function has_permission($permission_key) {
    $user = get_current_user_data();
    if (!$user) return false;

    // Süper Yönetici (Role ID 1) her zaman tüm yetkilere sahiptir
    if ((int)$user['role_id'] === 1) {
        return true;
    }

    $permissions = json_decode($user['permissions'], true);
    if (!is_array($permissions)) return false;

    return isset($permissions[$permission_key]) && $permissions[$permission_key] === true;
}

/**
 * Yetki yoksa erişimi engelleyen fonksiyon
 */
function require_permission($permission_key) {
    require_login();
    if (!has_permission($permission_key)) {
        echo '<div style="font-family:sans-serif; padding:30px; text-align:center; background:#fff0f0; border-radius:12px; margin:50px auto; max-width:500px; color:#9b2c2c;">'
            . '<h2>🚫 Erişim Engellendi</h2>'
            . '<p>Bu işlemi yapmak için gerekli yetkiniz bulunmamaktadır.</p>'
            . '<a href="index.php" style="display:inline-block; padding:10px 20px; background:#e53e3e; color:#fff; text-decoration:none; border-radius:8px; margin-top:15px;">Ana Sayfaya Dön</a>'
            . '</div>';
        exit;
    }
}

/**
 * Sistem İşlem Logu Kaydeder
 */
function log_action($action, $details = '') {
    $db = getDB();
    if (!$db) return;

    $user_id = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'Sistem / Ziyaretçi';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    try {
        $stmt = $db->prepare("INSERT INTO system_logs (user_id, username, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $username, $action, $details, $ip]);
    } catch (Exception $e) {
        // Log hatasını yut, ana akış aksamasın
    }
}

/**
 * Kullanıcı oturumunu kapatır
 */
function logout_user() {
    if (is_logged_in()) {
        log_action('Çıkış Yapma', 'Kullanıcı sistemden çıkış yaptı.');
    }
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * CSRF Token Oluşturucu
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF Token Doğrulayıcı
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Bildirim / Flash mesaj atama
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Flash mesajı okuyup temizleme
 */
function display_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        
        $type = htmlspecialchars($flash['type']);
        $msg = htmlspecialchars($flash['message']);
        
        $icons = [
            'success' => '✅',
            'danger'  => '❌',
            'warning' => '⚠️',
            'info'    => 'ℹ️'
        ];
        $icon = $icons[$type] ?? 'ℹ️';

        echo "<div class='alert alert-{$type} alert-dismissible fade show shadow-sm border-0 mb-4' role='alert'>
            <strong>{$icon}</strong> {$msg}
            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    }
}
