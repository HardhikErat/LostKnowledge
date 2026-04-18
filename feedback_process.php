<?php
// ============================================================
// feedback_process.php — Handle feedback form submission
// Called via POST from about.php contact form
// ============================================================

session_start();
require_once __DIR__ . '/config/db.php';

// POST only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /lost-knowledge/about.php');
    exit;
}

// ── Collect & sanitise ────────────────────────────────────
$name       = trim($_POST['name']    ?? '');
$email      = trim($_POST['email']   ?? '');
$phone      = trim($_POST['phone']   ?? '');
$age        = (int)($_POST['age']    ?? 0);
$subject    = trim($_POST['subject'] ?? 'general');
$bugDate    = trim($_POST['date']    ?? '');
$message    = trim($_POST['message'] ?? '');
$source     = trim($_POST['source']  ?? '');
$rating     = max(1, min(10, (int)($_POST['rating'] ?? 5)));
$country    = trim($_POST['country'] ?? '');
$interests  = implode(', ', array_map('trim', (array)($_POST['interests'] ?? [])));
$ip         = $_SERVER['HTTP_X_FORWARDED_FOR']
              ?? $_SERVER['REMOTE_ADDR']
              ?? '';

// ── Server-side validation ────────────────────────────────
$errors = [];

if (empty($name) || strlen($name) < 2 || strlen($name) > 120) {
    $errors[] = 'Please enter your full name (2–120 characters).';
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}
if (empty($message) || strlen($message) < 10) {
    $errors[] = 'Message must be at least 10 characters.';
}
if (strlen($message) > 5000) {
    $errors[] = 'Message must be under 5000 characters.';
}

// Valid subjects
$validSubjects = ['general','question','suggest','error','bug','feature'];
if (!in_array($subject, $validSubjects, true)) {
    $subject = 'general';
}

// Validate bug date if provided
if ($bugDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bugDate)) {
    $bugDate = null;
}

if (!empty($errors)) {
    $msg = urlencode(implode(' | ', $errors));
    header("Location: /lost-knowledge/about.php?feedback_error={$msg}#contact");
    exit;
}

// ── Insert into database ──────────────────────────────────
try {
    $pdo = get_pdo();
    $pdo->prepare(
        'INSERT INTO feedback
         (name, email, phone, age, subject, bug_date, message,
          source, rating, interests, country, ip_address, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "unread")'
    )->execute([
        $name,
        $email,
        $phone   ?: null,
        $age > 0 ? $age : null,
        $subject,
        $bugDate ?: null,
        $message,
        $source  ?: null,
        $rating,
        $interests ?: null,
        $country ?: null,
        $ip      ?: null,
    ]);

    // Flash success via session
    $_SESSION['feedback_success'] = 'Thank you, ' . htmlspecialchars($name) . '! Your feedback has been received.';
    header('Location: /lost-knowledge/about.php?feedback=1#contact');
    exit;

} catch (Exception $e) {
    error_log('[LK] Feedback error: ' . $e->getMessage());
    $msg = urlencode('A database error occurred. Please try again.');
    header("Location: /lost-knowledge/about.php?feedback_error={$msg}#contact");
    exit;
}
