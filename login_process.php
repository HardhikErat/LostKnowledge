<?php
// ============================================================
// login_process.php — Backend authentication handler
// Called via POST from login.html
// Separation of Concerns: ALL logic here, NO HTML output
// Supports login via email OR phone number
// ============================================================

session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /lost-knowledge/login.html');
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
$password   = $_POST['password'] ?? '';
$remember   = !empty($_POST['remember_me']);

if (empty($identifier) || empty($password)) {
    header('Location: /lost-knowledge/login.html?error=required');
    exit;
}

// Determine if input is email or phone number
$isPhone = false;
$isEmail = false;

// Strip any spaces/dashes from potential phone
$cleanIdentifier = preg_replace('/[\s\-\(\)\+]/', '', $identifier);

if (preg_match('/^[6-9]\d{9}$/', $cleanIdentifier)) {
    // Indian 10-digit phone number
    $isPhone = true;
    $identifier = $cleanIdentifier;
} elseif (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
    $isEmail = true;
    $identifier = strtolower($identifier);
} else {
    header('Location: /lost-knowledge/login.html?error=invalid');
    exit;
}

try {
    $pdo = get_pdo();

    if ($isEmail) {
        $stmt = $pdo->prepare('SELECT id, username, email, password, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$identifier]);
    } else {
        $stmt = $pdo->prepare('SELECT id, username, email, password, role FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$identifier]);
    }

    $user = $stmt->fetch();

    if (!$user || empty($user['password']) || !password_verify($password, $user['password'])) {
        header('Location: /lost-knowledge/login.html?error=invalid');
        exit;
    }

    session_regenerate_id(true);
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['logged_in']  = true;
    $_SESSION['login_type'] = 'session'; 

    if ($remember) {
        try {
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + (86400 * 30));
            
            // Clear old tokens for this user and store new one
            $pdo->prepare('DELETE FROM user_tokens WHERE user_id = ?')->execute([$user['id']]);
            $pdo->prepare('INSERT INTO user_tokens (user_id, token, expires) VALUES (?, ?, ?)')
                ->execute([$user['id'], $token, $expiry]);

            setcookie('lk_remember', $user['id'] . ':' . $token, [
                'expires'  => time() + (86400 * 30),
                'path'     => '/',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } catch (Exception $e) {
            error_log('[LK] Remember-me token error: ' . $e->getMessage());
            // Login still succeeds, just without persistent cookie
        }
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
