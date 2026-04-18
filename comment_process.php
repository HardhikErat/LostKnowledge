<?php
// ============================================================
// comment_process.php — Handle comment add/delete
// ============================================================
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/notify.php';

if (!is_logged_in()) {
    $_SESSION['flash_error'] = 'You must be logged in to comment.';
    header('Location: /lost-knowledge/login.html');
    exit;
}

$userId  = current_user_id();
$action  = $_POST['action']  ?? '';
$entryId = (int)($_POST['entry_id'] ?? 0);

if ($entryId <= 0) { header('Location: /lost-knowledge/index.html'); exit; }

$pdo = get_pdo();

// ── ADD COMMENT ──────────────────────────────────────────────
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['body'] ?? '');

    if (!$body || strlen($body) < 2 || strlen($body) > 1000) {
        $_SESSION['flash_error'] = 'Comment must be 2–1000 characters.';
        header("Location: /lost-knowledge/entry.php?id=$entryId#comments");
        exit;
    }

    try {
        $pdo->prepare('INSERT INTO comments (entry_id, user_id, body) VALUES (?, ?, ?)')
            ->execute([$entryId, $userId, $body]);

        // Award karma for commenting (+2)
        award_karma($userId, 2);

        // Send notification to entry owner
        $ownerStmt = $pdo->prepare('SELECT user_id, title FROM knowledge_entries WHERE id = ?');
        $ownerStmt->execute([$entryId]);
        $entry = $ownerStmt->fetch();

        if ($entry && (int)$entry['user_id'] !== $userId) {
            $commenter = current_username();
            create_notification(
                (int)$entry['user_id'],
                'new_comment',
                "{$commenter} commented on your entry \"{$entry['title']}\"",
                "/lost-knowledge/entry.php?id={$entryId}#comments"
            );
        }

        $_SESSION['flash_success'] = 'Comment posted! (+2 karma)';
    } catch (Exception $e) {
        $_SESSION['flash_error'] = 'Failed to post comment.';
    }

    header("Location: /lost-knowledge/entry.php?id=$entryId#comments");
    exit;
}

// ── DELETE COMMENT ───────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $commentId = (int)($_POST['comment_id'] ?? 0);

    if ($commentId <= 0) {
        $_SESSION['flash_error'] = 'Invalid comment.';
        header("Location: /lost-knowledge/entry.php?id=$entryId#comments");
        exit;
    }

    try {
        // Only the comment owner or an admin can delete
        $stmt = $pdo->prepare('SELECT user_id FROM comments WHERE id = ?');
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();

        if (!$comment) {
            $_SESSION['flash_error'] = 'Comment not found.';
        } elseif ((int)$comment['user_id'] !== $userId && !is_admin()) {
            $_SESSION['flash_error'] = 'You can only delete your own comments.';
        } else {
            $pdo->prepare('DELETE FROM comments WHERE id = ?')->execute([$commentId]);
            $_SESSION['flash_success'] = 'Comment deleted.';
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = 'Failed to delete comment.';
    }

    header("Location: /lost-knowledge/entry.php?id=$entryId#comments");
    exit;
}

// Fallback
header("Location: /lost-knowledge/entry.php?id=$entryId");
exit;
