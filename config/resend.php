<?php
// ============================================================
// config/resend.php — Resend email API configuration
// Get your API key from https://resend.com/api-keys
// ============================================================

// ⚠️ REPLACE THIS with your actual Resend API key
define('RESEND_API_KEY', 're_KnPs3W1S_Ms6rszR66qXTVhunkfx7wZLM');

// The "from" address — must be verified in Resend dashboard
// For testing, Resend provides: onboarding@resend.dev
define('RESEND_FROM_EMAIL', 'Lost Knowledge <onboarding@resend.dev>');

/**
 * Send a password-reset email via the Resend HTTP API.
 *
 * @param string $toEmail   Recipient email address
 * @param string $resetLink Full URL with token for password reset
 * @return bool             True on success, false on failure
 */
function send_reset_email(string $toEmail, string $resetLink): bool
{
  $payload = json_encode([
    'from' => RESEND_FROM_EMAIL,
    'to' => [$toEmail],
    'subject' => 'Reset your Lost Knowledge password',
    'html' => build_reset_email_html($toEmail, $resetLink),
  ]);

  $ch = curl_init('https://api.resend.com/emails');
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . RESEND_API_KEY,
      'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 15,
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);

  if ($error) {
    error_log("[LK] Resend cURL error: $error");
    return false;
  }

  if ($httpCode >= 200 && $httpCode < 300) {
    return true;
  }

  error_log("[LK] Resend API error (HTTP $httpCode): $response");
  return false;
}

/**
 * Build the HTML body for the reset email.
 */
function build_reset_email_html(string $email, string $link): string
{
  $escapedLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
  return <<<HTML






<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif;background:#1A1208;color:#F5EFE6;">
  <div style="max-width:520px;margin:40px auto;background:#2A2015;border:1px solid #6B5228;border-radius:10px;overflow:hidden;">
    <!-- Header -->
    <div style="background:#211A0E;padding:28px 32px;border-bottom:2px solid #A0812A;text-align:center;">
      <h1 style="margin:0;font-size:24px;color:#D89748;font-family:Georgia,serif;">Lost Knowledge</h1>
      <p style="margin:6px 0 0;font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#8A7A66;">Archive of Vanishing Wisdom</p>
    </div>
    <!-- Body -->
    <div style="padding:32px;">
      <h2 style="margin:0 0 12px;font-size:20px;color:#F5EFE6;">Password Reset Request</h2>
      <p style="color:#C4B49A;line-height:1.7;font-size:15px;">
        We received a request to reset the password for the account associated with
        <strong style="color:#D89748;">{$email}</strong>.
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
        If you didn't request this, you can safely ignore this email. Your password won't be changed.
      </p>
    </div>
    <!-- Footer -->
    <div style="background:#211A0E;padding:16px 32px;border-top:1px solid #4A3820;text-align:center;">
      <p style="margin:0;color:#8A7A66;font-size:12px;">✦ Lost Knowledge — Preserving wisdom for tomorrow ✦</p>
    </div>
  </div>
</body>
</html>
HTML;
}
