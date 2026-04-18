<?php
// ============================================================
// reset_password_process.php — Validates token & updates password
// Called via POST from reset_password.html
// ============================================================

session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /lost-knowledge/forgot_password.html');
    exit;
}

$token    = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['password_confirm'] ?? '';

// Validate inputs
$errors = [];

if (empty($token)) {
    header('Location: /lost-knowledge/forgot_password.html?error=' . urlencode('Invalid reset link. Please request a new one.'));
    exit;
}

if (empty($password))                        $errors[] = 'Password is required.';
elseif (strlen($password) < 8)               $errors[] = 'Password must be at least 8 characters.';
elseif (!preg_match('/[A-Z]/', $password))   $errors[] = 'Password needs at least one uppercase letter.';
elseif (!preg_match('/[0-9]/', $password))   $errors[] = 'Password needs at least one number.';

if ($password !== $confirm)                  $errors[] = 'Passwords do not match.';

if (!empty($errors)) {
    $msg = urlencode(implode(' ', $errors));
    header("Location: /lost-knowledge/reset_password.html?token={$token}&error={$msg}");
    exit;
}

try {
    $pdo = get_pdo();

    // Look up the token
    $stmt = $pdo->prepare(
        'SELECT pr.id AS reset_id, pr.user_id, pr.expires_at, pr.used, u.email
         FROM password_resets pr
         JOIN users u ON pr.user_id = u.id
         WHERE pr.token = ?
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        header('Location: /lost-knowledge/forgot_password.html?error=' . urlencode('Invalid or expired reset link. Please request a new one.'));
        exit;
    }

    if ($reset['used']) {
        header('Location: /lost-knowledge/forgot_password.html?error=' . urlencode('This reset link has already been used. Request a new one.'));
        exit;
    }

    if (strtotime($reset['expires_at']) < time()) {
        header('Location: /lost-knowledge/forgot_password.html?error=' . urlencode('This reset link has expired. Please request a new one.'));
        exit;
    }

    // Hash new password
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Update user password
    $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
        ->execute([$hash, $reset['user_id']]);

    // Mark token as used
    $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = ?')
        ->execute([$reset['reset_id']]);

    // Redirect to login with success message
    $_SESSION['flash_success'] = 'Password updated successfully! Please sign in with your new password.';
    header('Location: /lost-knowledge/login.html?success=' . urlencode('Password updated successfully! Please sign in with your new password.'));
    exit;

} catch (Exception $e) {
    error_log('[LK] Reset password error: ' . $e->getMessage());
    header('Location: /lost-knowledge/reset_password.html?token=' . $token . '&error=' . urlencode('Something went wrong. Please try again.'));
    exit;
}
