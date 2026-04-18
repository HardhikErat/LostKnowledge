<?php
// ============================================================
// config/auth.php — Session-based access control helpers
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Experiment 7: Cookies and Session Management
 * Automatically log in a user if a valid "Remember Me" cookie exists.
 */
function check_auto_login(): void
{
    // Skip if already logged in via session
    if (!empty($_SESSION['logged_in'])) return;
    
    // Check if remember cookie exists
    $cookie = $_COOKIE['lk_remember'] ?? '';
    if (empty($cookie)) return;

    $parts = explode(':', $cookie);
    if (count($parts) !== 2) return;

    [$userId, $token] = $parts;

    try {
        require_once __DIR__ . '/db.php';
        $pdo = get_pdo();
        
        // Find token in database
        $stmt = $pdo->prepare('SELECT u.id, u.username, u.role FROM users u 
                               JOIN user_tokens ut ON u.id = ut.user_id 
                               WHERE ut.user_id = ? AND ut.token = ? AND ut.expires > NOW()');
        $stmt->execute([$userId, $token]);
        $user = $stmt->fetch();

        if ($user) {
            // Re-establish session
            $_SESSION['user_id']   = (int) $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_type'] = 'cookie'; // For demonstration purposes
        }
    } catch (Exception $e) {
        // Silent fail for auto-login
    }
}

/**
 * Experiment 7: Tracks the last visit time using cookies.
 */
function update_last_visit(): void
{
    // Only set if not already set in this request to avoid overwrite
    if (!isset($_SESSION['last_visit_shown'])) {
        $lastVisit = $_COOKIE['lk_last_visit'] ?? 'First time';
        $_SESSION['last_visit_prev'] = $lastVisit;
        $_SESSION['last_visit_shown'] = true;
    }

    // Set/Update the cookie for 30 days
    setcookie('lk_last_visit', date('Y-m-d H:i:s'), [
        'expires'  => time() + (86400 * 30),
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Global invocation for auth logic
check_auto_login();
update_last_visit();

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
