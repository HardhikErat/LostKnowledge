<?php
// ============================================================
// config/google_redirect.php — Redirects user to Google OAuth
// ============================================================

require_once __DIR__ . '/google.php';
header('Location: ' . google_auth_url());
exit;
