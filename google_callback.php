<?php
// ============================================================
// google_callback.php — Google OAuth 2.0 callback handler
// Google redirects here after user authorizes
// ============================================================

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/google.php';

// Check for errors from Google
if (isset($_GET['error'])) {
    error_log('[LK] Google OAuth error: ' . $_GET['error']);
    header('Location: /lost-knowledge/login.html?error=' . urlencode('Google sign-in was cancelled.'));
    exit;
}

// Get the authorization code
$code = $_GET['code'] ?? '';
if (empty($code)) {
    header('Location: /lost-knowledge/login.html?error=' . urlencode('Invalid Google response. Please try again.'));
    exit;
}

try {
    // Exchange code for access token
    $tokenData = google_get_token($code);
    if (!$tokenData || empty($tokenData['access_token'])) {
        throw new RuntimeException('Failed to get access token from Google.');
    }

    // Fetch user profile
    $googleUser = google_get_user($tokenData['access_token']);
    if (!$googleUser || empty($googleUser['id'])) {
        throw new RuntimeException('Failed to fetch Google user profile.');
    }

    $googleId    = $googleUser['id'];
    $googleEmail = strtolower($googleUser['email'] ?? '');
    $googleName  = $googleUser['name'] ?? '';

    $pdo = get_pdo();

    // 1. Check if user exists by google_id
    $stmt = $pdo->prepare('SELECT id, username, email, role FROM users WHERE google_id = ? LIMIT 1');
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if (!$user && !empty($googleEmail)) {
        // 2. Check by email (existing user linking to Google)
        $stmt = $pdo->prepare('SELECT id, username, email, role FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$googleEmail]);
        $user = $stmt->fetch();

        if ($user) {
            // Link Google ID to existing account
            $pdo->prepare('UPDATE users SET google_id = ? WHERE id = ?')
                ->execute([$googleId, $user['id']]);
        }
    }

    if (!$user) {
        // 3. Create new account
        $username = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($googleName));
        $username = substr($username, 0, 45);

        // Make username unique
        $checkUser = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $checkUser->execute([$username]);
        if ($checkUser->fetch()) {
            $username .= '_' . rand(100, 999);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password, google_id, role) VALUES (?, ?, NULL, ?, ?)'
        );
        $stmt->execute([$username, $googleEmail, $googleId, 'user']);

        $user = [
            'id'       => (int) $pdo->lastInsertId(),
            'username' => $username,
            'email'    => $googleEmail,
            'role'     => 'user',
        ];
    }

    // Log the user in
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['role']       = $user['role'];
    $_SESSION['logged_in']  = true;
    $_SESSION['login_type'] = 'google';
    $_SESSION['flash_success'] = 'Welcome, ' . htmlspecialchars($user['username']) . '! Signed in with Google.';

    if ($user['role'] === 'admin') {
        header('Location: /lost-knowledge/admin/admin_dashboard.php');
    } else {
        header('Location: /lost-knowledge/dashboard.php');
    }
    exit;

} catch (Exception $e) {
    error_log('[LK] Google OAuth error: ' . $e->getMessage());
    header('Location: /lost-knowledge/login.html?error=' . urlencode('Google sign-in failed. Please try again.'));
    exit;
}
