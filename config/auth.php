<?php
// ============================================================
// config/auth.php — Session-based access control helpers
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns true if a user is logged in.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
}

/**
 * Returns true if the logged-in user is an admin.
 */
function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Require a logged-in user. Redirects to login page otherwise.
 *
 * @param string $redirect  URL to redirect to after login
 */
function require_login(string $redirect = ''): void
{
    if (!is_logged_in()) {
        $ref = $redirect ?: ($_SERVER['REQUEST_URI'] ?? '');
        $qs  = $ref ? '?ref=' . urlencode($ref) : '';
        $_SESSION['flash_error'] = 'Please sign in to access that page.';
        header('Location: /lost-knowledge/login.html' . $qs);
        exit;
    }
}

/**
 * Require admin role. Redirects non-admins to dashboard.
 */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        $_SESSION['flash_error'] = 'You do not have permission to access that area.';
        header('Location: /lost-knowledge/dashboard.php');
        exit;
    }
}

/**
 * Return the logged-in user's ID (int) or null.
 */
function current_user_id(): ?int
{
    return is_logged_in() ? (int) $_SESSION['user_id'] : null;
}

/**
 * Return the logged-in username or empty string.
 */
function current_username(): string
{
    return htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
}
