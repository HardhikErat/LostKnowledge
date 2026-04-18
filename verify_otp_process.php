<?php
// ============================================================
// verify_otp_process.php — Validates OTP & redirects to reset
// ============================================================

session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /lost-knowledge/forgot_password.html');
    exit;
}

$token = trim($_POST['token'] ?? '');
$otp   = trim($_POST['otp'] ?? '');

if (empty($token) || empty($otp)) {
    header('Location: /lost-knowledge/forgot_password.html?tab=phone&error=' . urlencode('Invalid request. Please try again.'));
    exit;
}

if (!preg_match('/^\d{6}$/', $otp)) {
    header('Location: /lost-knowledge/verify_otp.html?token=' . $token . '&error=' . urlencode('OTP must be exactly 6 digits.'));
    exit;
}

try {
    $pdo = get_pdo();

    // Look up the token + OTP
    $stmt = $pdo->prepare(
        'SELECT id, user_id, otp, expires_at, used
         FROM password_resets
         WHERE token = ? LIMIT 1'
    );
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        header('Location: /lost-knowledge/forgot_password.html?tab=phone&error=' . urlencode('Invalid OTP session. Please request a new one.'));
        exit;
    }

    if ($reset['used']) {
        header('Location: /lost-knowledge/forgot_password.html?tab=phone&error=' . urlencode('This OTP has already been used. Request a new one.'));
        exit;
    }

    if (strtotime($reset['expires_at']) < time()) {
        header('Location: /lost-knowledge/forgot_password.html?tab=phone&error=' . urlencode('This OTP has expired. Please request a new one.'));
        exit;
    }

    if ($reset['otp'] !== $otp) {
        header('Location: /lost-knowledge/verify_otp.html?token=' . $token . '&error=' . urlencode('Incorrect OTP. Please check and try again.'));
        exit;
    }

    // OTP is valid! Redirect to the reset password page with the token
    // Clear session OTP debug data
    unset($_SESSION['otp_debug'], $_SESSION['otp_token'], $_SESSION['otp_phone']);

    header('Location: /lost-knowledge/reset_password.html?token=' . $token . '&via=otp');
    exit;

} catch (Exception $e) {
    error_log('[LK] OTP verification error: ' . $e->getMessage());
    header('Location: /lost-knowledge/verify_otp.html?token=' . $token . '&error=' . urlencode('Something went wrong. Please try again.'));
    exit;
}
