<?php
// ============================================================
// forgot_password_process.php — Generates reset token & sends email
// Supports both Email and Phone (OTP) based recovery
// ============================================================

session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /forgot_password.html');
    exit;
}

$email = trim(strtolower($_POST['email'] ?? ''));

    if (empty($email)) {
        header('Location: /forgot_password.html?error=' . urlencode('Email address is required.'));
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: /forgot_password.html?error=' . urlencode('Enter a valid email address.'));
        exit;
    }

    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            header('Location: /forgot_password.html?success=' . urlencode('If that email is registered, you will receive a reset link shortly.'));
            exit;
        }

        // Generate secure token
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

        // Delete old unused tokens
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used = 0')
            ->execute([$user['id']]);

        // Store new token
        $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)')
            ->execute([$user['id'], $token, $expiresAt]);

        // Build reset link
        $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $resetLink = "{$protocol}://{$host}/reset_password.html?token={$token}";

        // ── Send email using Resend ──────────────────────
        require_once __DIR__ . '/config/resend.php';
        $sent = send_reset_email($user['email'], $resetLink);

        if ($sent) {
            header('Location: /forgot_password.html?success=' . urlencode('If that email is registered, you will receive a reset link shortly.'));
        } else {
            header('Location: /forgot_password.html?error=' . urlencode('Failed to send email. Please try again later.'));
        }
        exit;

    } catch (Exception $e) {
        error_log('[LK] Forgot password error: ' . $e->getMessage());
        header('Location: /forgot_password.html?error=' . urlencode('Something went wrong. Please try again.'));
        exit;
    }
