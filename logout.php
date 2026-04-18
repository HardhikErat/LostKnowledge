<?php
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
if (isset($_COOKIE['lk_remember'])) {
    setcookie('lk_remember', '', ['expires' => time()-3600, 'path'=>'/', 'httponly'=>true, 'samesite'=>'Lax']);
}
header('Location: /lost-knowledge/index.html');
exit;
