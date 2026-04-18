<?php
// ============================================================
// register_process.php — Backend registration handler
// Called via POST from register.html
// Separation of Concerns: ALL logic here, NO HTML output
// Now includes phone number field
// ============================================================

session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /lost-knowledge/register.html');
    exit;
}

$username = trim($_POST['username'] ?? '');
$email    = trim(strtolower($_POST['email'] ?? ''));
$phone    = preg_replace('/[\s\-\(\)\+]/', '', trim($_POST['phone'] ?? ''));
$password = $_POST['password'] ?? '';
$confirm  = $_POST['password_confirm'] ?? '';

// Server-side validation
$errors = [];

if (empty($username))                                      $errors[] = 'Username is required.';
elseif (strlen($username) < 3 || strlen($username) > 50)  $errors[] = 'Username must be 3–50 characters.';
elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username))       $errors[] = 'Username: letters, numbers and underscores only.';

if (empty($email))                                         $errors[] = 'Email address is required.';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))        $errors[] = 'Enter a valid email address.';

if (empty($phone))                                         $errors[] = 'Phone number is required.';
elseif (!preg_match('/^[6-9]\d{9}$/', $phone))             $errors[] = 'Enter a valid 10-digit Indian mobile number (starts with 6–9).';

if (empty($password))                                      $errors[] = 'Password is required.';
elseif (strlen($password) < 8)                             $errors[] = 'Password must be at least 8 characters.';
elseif (!preg_match('/[A-Z]/', $password))                 $errors[] = 'Password needs at least one uppercase letter.';
elseif (!preg_match('/[0-9]/', $password))                 $errors[] = 'Password needs at least one number.';

if ($password !== $confirm)                                $errors[] = 'Passwords do not match.';

if (!empty($errors)) {
    $msg = urlencode(implode(' ', $errors));
    header("Location: /lost-knowledge/register.html?error={$msg}");
    exit;
}

try {
    $pdo = get_pdo();

    // Check uniqueness (username, email, phone)
    $check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? OR phone = ? LIMIT 1');
    $check->execute([$username, $email, $phone]);
    if ($check->fetch()) {
        $msg = urlencode('That username, email, or phone number is already registered.');
        header("Location: /lost-knowledge/register.html?error={$msg}");
        exit;
    }

    // Hash password with bcrypt
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $pdo->prepare('INSERT INTO users (username, email, phone, password, role) VALUES (?, ?, ?, ?, ?)')
        ->execute([$username, $email, $phone, $hash, 'user']);

    $newId = (int) $pdo->lastInsertId();

    // Auto-login after registration
    session_regenerate_id(true);
    $_SESSION['user_id']   = $newId;
    $_SESSION['username']  = $username;
    $_SESSION['role']      = 'user';
    $_SESSION['logged_in'] = true;
    $_SESSION['flash_success'] = 'Welcome to the Archive, ' . htmlspecialchars($username) . '!';

    header('Location: /lost-knowledge/dashboard.php');
    exit;

} catch (Exception $e) {
    error_log('[LK] Register error: ' . $e->getMessage());
    $msg = urlencode('A database error occurred. Please try again.');
    header("Location: /lost-knowledge/register.html?error={$msg}");
    exit;
}
