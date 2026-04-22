<?php
session_start();
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_login('/login.html');

$entryId = (int)($_GET['id'] ?? 0);
if ($entryId <= 0) {
    $_SESSION['flash_error'] = 'Invalid entry ID.';
    header('Location: /dashboard.php');
    exit;
}

try {
    $pdo = get_pdo();

    // Verify ownership or check if admin
    $stmt = $pdo->prepare('SELECT user_id, image_path FROM knowledge_entries WHERE id = ?');
    $stmt->execute([$entryId]);
    $entry = $stmt->fetch();

    if (!$entry) {
        $_SESSION['flash_error'] = 'Entry not found.';
        header('Location: /dashboard.php');
        exit;
    }

    $userId = current_user_id();
    $role   = $_SESSION['role'] ?? 'user';

    if ($entry['user_id'] != $userId && $role !== 'admin') {
        $_SESSION['flash_error'] = 'You do not have permission to delete this entry.';
        header('Location: /dashboard.php');
        exit;
    }

    // Delete associated image file if any
    if (!empty($entry['image_path'])) {
        $imgPath = __DIR__ . '/' . ltrim($entry['image_path'], '/');
        if (file_exists($imgPath)) {
            @unlink($imgPath);
        }
    }

    // Delete from database
    $delStmt = $pdo->prepare('DELETE FROM knowledge_entries WHERE id = ?');
    $delStmt->execute([$entryId]);

    $_SESSION['flash_success'] = "Entry #{$entryId} deleted successfully.";

} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Failed to delete entry: ' . $e->getMessage();
}

$referer = $_SERVER['HTTP_REFERER'] ?? '/dashboard.php';
// basic safety to avoid external redirects
if (strpos($referer, 'lost-knowledge') === false) {
    $referer = '/dashboard.php';
}

header('Location: ' . $referer);
exit;
