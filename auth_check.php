<?php
// ============================================================
// api/auth_check.php — Lightweight auth status check for AJAX
// Returns JSON with login status so static HTML pages can
// update their nav dynamically (e.g., show Dashboard/Sign Out).
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config/auth.php';

echo json_encode([
    'logged_in' => is_logged_in(),
    'username'  => is_logged_in() ? current_username() : null,
    'is_admin'  => is_admin(),
], JSON_UNESCAPED_UNICODE);
