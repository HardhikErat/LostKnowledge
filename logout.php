<?php
session_start();
$userId = $_SESSION['user_id'] ?? null;

// Clear session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

// Clear cookie and database token
if (isset($_COOKIE['lk_remember'])) {
    $parts = explode(':', $_COOKIE['lk_remember']);
    if (count($parts) === 2) {
        try {
            require_once __DIR__ . '/config/db.php';
            get_pdo()->prepare('DELETE FROM user_tokens WHERE user_id = ? AND token = ?')
                     ->execute([$parts[0], $parts[1]]);
        } catch (Exception $e) {}
    }
    setcookie('lk_remember', '', ['expires' => time()-3600, 'path'=>'/', 'httponly'=>true, 'samesite'=>'Lax']);
}

header('Location: /lost-knowledge/index.html');
exit;
