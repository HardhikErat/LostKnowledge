<?php
// ============================================================
// api/notifications.php — Notification system API
// GET  ?action=count  — unread count
// GET  ?action=list   — list notifications
// POST { action:"read", id:N }       — mark single as read
// POST { action:"read_all" }         — mark all as read
// ============================================================

header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/auth.php';

function json_out(array $d, int $c = 200) { http_response_code($c); echo json_encode($d); exit; }

if (!is_logged_in()) json_out(['success' => false, 'error' => 'unauthenticated'], 401);

$userId = current_user_id();
$pdo = get_pdo();

// GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'count') {
        try {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
            $stmt->execute([$userId]);
            json_out(['success' => true, 'count' => (int) $stmt->fetchColumn()]);
        } catch (Exception $e) {
            json_out(['success' => true, 'count' => 0]);
        }
    }

    if ($action === 'list') {
        try {
            $stmt = $pdo->prepare(
                'SELECT id, type, message, link, is_read, created_at
                 FROM notifications WHERE user_id = ?
                 ORDER BY created_at DESC LIMIT 50'
            );
            $stmt->execute([$userId]);
            json_out(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            json_out(['success' => true, 'data' => []]);
        }
    }
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $action = $body['action'] ?? '';

    if ($action === 'read' && !empty($body['id'])) {
        try {
            $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
                ->execute([(int)$body['id'], $userId]);
            json_out(['success' => true]);
        } catch (Exception $e) {
            json_out(['success' => false], 500);
        }
    }

    if ($action === 'read_all') {
        try {
            $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')
                ->execute([$userId]);
            json_out(['success' => true]);
        } catch (Exception $e) {
            json_out(['success' => false], 500);
        }
    }
}

json_out(['success' => false, 'error' => 'Invalid request'], 400);
