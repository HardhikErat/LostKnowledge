<?php
// ============================================================
// login_process.php — Backend authentication handler
// Called via POST from login.html
// Separation of Concerns: ALL logic here, NO HTML output
// ============================================================

session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /lost-knowledge/login.html');
    exit;
}

$email    = trim(strtolower($_POST['email']    ?? ''));
$password = $_POST['password'] ?? '';
$remember = !empty($_POST['remember_me']);

if (empty($email) || empty($password)) {
    header('Location: /lost-knowledge/login.html?error=required');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /lost-knowledge/login.html?error=invalid');
    exit;
}

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare('SELECT id, username, email, password, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        header('Location: /lost-knowledge/login.html?error=invalid');
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['logged_in'] = true;

    if ($remember) {
        setcookie('lk_remember', $user['id'] . ':' . bin2hex(random_bytes(32)), [
            'expires'  => time() + (86400 * 30),
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    $_SESSION['flash_success'] = 'Welcome back, ' . htmlspecialchars($user['username']) . '!';

    if ($user['role'] === 'admin') {
        header('Location: /lost-knowledge/admin/admin_dashboard.php');
    } else {
        header('Location: /lost-knowledge/dashboard.php');
    }
    exit;

} catch (Exception $e) {
    error_log('[LK] Login error: ' . $e->getMessage());
    header('Location: /lost-knowledge/login.html?error=invalid');
    exit;
}
