<?php
// ============================================================
// forgot_password_process.php — Generates reset token & sends email
// Supports both Email and Phone (OTP) based recovery
// ============================================================

session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /lost-knowledge/forgot_password.html');
    exit;
}

$method = trim($_POST['method'] ?? 'email');

// ── EMAIL-BASED RESET ─────────────────────────────────────────
if ($method === 'email') {
    $email = trim(strtolower($_POST['email'] ?? ''));

    if (empty($email)) {
        header('Location: /lost-knowledge/forgot_password.html?error=' . urlencode('Email address is required.'));
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: /lost-knowledge/forgot_password.html?error=' . urlencode('Enter a valid email address.'));
        exit;
    }

    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            header('Location: /lost-knowledge/forgot_password.html?success=' . urlencode('If that email is registered, you will receive a reset link shortly.'));
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
        $resetLink = "{$protocol}://{$host}/lost-knowledge/reset_password.html?token={$token}";

        // ── Send email using PHP mail() ──────────────────────
        $sent = send_reset_email_local($user['email'], $user['username'], $resetLink);

        if ($sent) {
            header('Location: /lost-knowledge/forgot_password.html?success=' . urlencode('Password reset link has been sent to your email. Check your inbox (and spam folder).'));
        } else {
            // Fallback: show the link directly for localhost development
            $_SESSION['reset_link_debug'] = $resetLink;
            header('Location: /lost-knowledge/forgot_password.html?success=' . urlencode('Reset link generated! Since this is a local server, click below to reset your password.') . '&show_link=1');
        }
        exit;

    } catch (Exception $e) {
        error_log('[LK] Forgot password error: ' . $e->getMessage());
        header('Location: /lost-knowledge/forgot_password.html?error=' . urlencode('Something went wrong. Please try again.'));
        exit;
    }
}

// ── PHONE (OTP) BASED RESET ──────────────────────────────────
if ($method === 'phone') {
    $phone = trim($_POST['phone'] ?? '');
    // Clean the phone number
    $cleanPhone = preg_replace('/[\s\-\(\)\+]/', '', $phone);

    if (empty($cleanPhone) || !preg_match('/^[6-9]\d{9}$/', $cleanPhone)) {
        header('Location: /lost-knowledge/forgot_password.html?tab=phone&error=' . urlencode('Enter a valid 10-digit Indian mobile number.'));
        exit;
    }

    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id, username, phone FROM users WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone," ",""),"-",""),"(",""),")",""),"+","") LIKE ? LIMIT 1');
        $stmt->execute(['%' . $cleanPhone]);
        $user = $stmt->fetch();

        if (!$user) {
            // Don't reveal whether the phone exists
            header('Location: /lost-knowledge/forgot_password.html?tab=phone&success=' . urlencode('If that phone number is registered, you will receive an OTP shortly.'));
            exit;
        }

        // Generate 6-digit OTP
        $otp = sprintf('%06d', random_int(100000, 999999));
        $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes

        // Create a token linked to this OTP
        $token = bin2hex(random_bytes(32));

        // Delete old unused tokens
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND used = 0')
            ->execute([$user['id']]);

        // Store OTP as token (OTP stored in a separate column or we store it in the token field with prefix)
        $pdo->prepare('INSERT INTO password_resets (user_id, token, otp, expires_at) VALUES (?, ?, ?, ?)')
            ->execute([$user['id'], $token, $otp, $expiresAt]);

        // In production, you'd send the OTP via SMS (Twilio, MSG91, etc.)
        // For localhost, we store it in session to display
        $_SESSION['otp_debug']  = $otp;
        $_SESSION['otp_token']  = $token;
        $_SESSION['otp_phone']  = $phone;

        header('Location: /lost-knowledge/verify_otp.html?token=' . $token);
        exit;

    } catch (Exception $e) {
        error_log('[LK] OTP generation error: ' . $e->getMessage());
        header('Location: /lost-knowledge/forgot_password.html?tab=phone&error=' . urlencode('Something went wrong. Please try again.'));
        exit;
    }
}

// Invalid method
header('Location: /lost-knowledge/forgot_password.html');
exit;

// ── Helper: Send email using PHP mail() ──────────────────────
function send_reset_email_local(string $to, string $username, string $link): bool
{
    $subject = 'Reset your Lost Knowledge password';

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Lost Knowledge <noreply@lostknowledge.local>\r\n";
    $headers .= "Reply-To: noreply@lostknowledge.local\r\n";

    $escapedLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

    $body = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif;background:#1A1208;color:#F5EFE6;">
  <div style="max-width:520px;margin:40px auto;background:#2A2015;border:1px solid #6B5228;border-radius:10px;overflow:hidden;">
    <div style="background:#211A0E;padding:28px 32px;border-bottom:2px solid #A0812A;text-align:center;">
      <h1 style="margin:0;font-size:24px;color:#D89748;font-family:Georgia,serif;">Lost Knowledge</h1>
      <p style="margin:6px 0 0;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8A7A66;">Archive of Vanishing Wisdom</p>
    </div>
    <div style="padding:32px;">
      <h2 style="margin:0 0 12px;font-size:20px;color:#F5EFE6;">Password Reset Request</h2>
      <p style="color:#C4B49A;line-height:1.7;font-size:15px;">
        Hello <strong style="color:#D89748;">{$username}</strong>, we received a request to reset your password.
      </p>
      <p style="color:#C4B49A;line-height:1.7;font-size:15px;">
        Click the button below to set a new password. This link expires in <strong>1 hour</strong>.
      </p>
      <div style="text-align:center;margin:28px 0;">
        <a href="{$escapedLink}"
           style="display:inline-block;background:#B8772A;color:#1A1208;font-weight:700;font-size:15px;
                  padding:14px 36px;border-radius:6px;text-decoration:none;letter-spacing:.02em;
                  border:1px solid #D89748;">
          Reset Password →
        </a>
      </div>
      <p style="color:#8A7A66;font-size:13px;line-height:1.6;">
        If you didn't request this, you can safely ignore this email.
      </p>
    </div>
    <div style="background:#211A0E;padding:16px 32px;border-top:1px solid #4A3820;text-align:center;">
      <p style="margin:0;color:#8A7A66;font-size:12px;">✦ Lost Knowledge — Preserving wisdom for tomorrow ✦</p>
    </div>
  </div>
</body>
</html>
HTML;

    // Try PHP mail() — works if sendmail/Mercury is configured in XAMPP
    $sent = @mail($to, $subject, $body, $headers);

    if (!$sent) {
        error_log("[LK] PHP mail() failed for: {$to} — showing direct link instead");
    }

    return $sent;
}
