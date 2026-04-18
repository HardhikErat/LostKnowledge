<?php
// ============================================================
// api/knowledge.php — REST-style JSON API
//
// GET  /api/knowledge.php              — list approved entries (paginated)
// GET  /api/knowledge.php?action=stats — site stats
// GET  /api/knowledge.php?id=N         — single entry
// POST /api/knowledge.php              — vote  { action:"vote", entry_id, vote_type }
// ============================================================

// ── CORS / headers ───────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Bootstrap ────────────────────────────────────────────────
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/notify.php';

/**
 * Send a JSON response and exit.
 */
function json_response(array $payload, int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send a JSON error and exit.
 */
function json_error(string $msg, int $code = 400): void
{
    json_response(['success' => false, 'error' => $msg], $code);
}

// ── Route: POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);

    if (!$body || empty($body['action'])) {
        json_error('Missing action.', 400);
    }

    // ── Vote ──────────────────────────────────────────────────
    if ($body['action'] === 'vote') {

        if (!is_logged_in()) {
            json_error('unauthenticated', 401);
        }

        $entryId  = (int) ($body['entry_id']  ?? 0);
        $voteType = $body['vote_type'] ?? '';

        if ($entryId <= 0 || !in_array($voteType, ['up', 'down'], true)) {
            json_error('Invalid vote parameters.', 400);
        }

        try {
            $pdo    = get_pdo();
            $userId = current_user_id();

            // Check entry exists and is approved
            $es = $pdo->prepare('SELECT id FROM knowledge_entries WHERE id = ? AND status = "approved"');
            $es->execute([$entryId]);
            if (!$es->fetch()) json_error('Entry not found or not approved.', 404);

            // Get existing vote
            $vs = $pdo->prepare('SELECT id, vote_type FROM votes WHERE entry_id = ? AND user_id = ?');
            $vs->execute([$entryId, $userId]);
            $existing = $vs->fetch();

            if ($existing) {
                if ($existing['vote_type'] === $voteType) {
                    // Same vote again = remove (toggle off)
                    $pdo->prepare('DELETE FROM votes WHERE id = ?')->execute([$existing['id']]);
                } else {
                    // Different vote = switch
                    $pdo->prepare('UPDATE votes SET vote_type = ?, voted_at = NOW() WHERE id = ?')
                        ->execute([$voteType, $existing['id']]);
                }
            } else {
                // New vote
                $pdo->prepare('INSERT INTO votes (entry_id, user_id, vote_type) VALUES (?, ?, ?)')
                    ->execute([$entryId, $userId, $voteType]);

                // Karma + notification for upvotes on entry owner
                if ($voteType === 'up') {
                    $ownerStmt = $pdo->prepare('SELECT user_id, title FROM knowledge_entries WHERE id = ?');
                    $ownerStmt->execute([$entryId]);
                    $entryInfo = $ownerStmt->fetch();
                    if ($entryInfo && (int)$entryInfo['user_id'] !== $userId) {
                        award_karma((int)$entryInfo['user_id'], 5);
                        // Milestone notifications at 5, 10, 25, 50, 100 votes
                        $upCount = (int)($counts['votes_up'] ?? 0) + 1;
                        if (in_array($upCount, [5, 10, 25, 50, 100])) {
                            create_notification(
                                (int)$entryInfo['user_id'], 'vote_milestone',
                                "Your entry \"{$entryInfo['title']}\" has reached {$upCount} upvotes!",
                                "/lost-knowledge/entry.php?id={$entryId}"
                            );
                        }
                    }
                }
            }

            // Return updated counts
            $cs = $pdo->prepare(
                'SELECT
                    SUM(vote_type = "up")   AS votes_up,
                    SUM(vote_type = "down") AS votes_down
                 FROM votes WHERE entry_id = ?'
            );
            $cs->execute([$entryId]);
            $counts = $cs->fetch();

            // Current user's vote after change
            $uvs = $pdo->prepare('SELECT vote_type FROM votes WHERE entry_id = ? AND user_id = ?');
            $uvs->execute([$entryId, $userId]);
            $uvRow = $uvs->fetch();

            json_response([
                'success'    => true,
                'votes_up'   => (int) ($counts['votes_up']   ?? 0),
                'votes_down' => (int) ($counts['votes_down'] ?? 0),
                'user_vote'  => $uvRow['vote_type'] ?? null,
            ]);

        } catch (RuntimeException | PDOException $e) {
            error_log('[LK API] Vote error: ' . $e->getMessage());
            json_error('Server error.', 500);
        }
    }

    json_error('Unknown action.', 400);
}

// ── Route: GET ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $action = $_GET['action'] ?? '';

    // ── Stats ─────────────────────────────────────────────────
    if ($action === 'stats') {
        try {
            $pdo    = get_pdo();
            $entries = (int) $pdo->query('SELECT COUNT(*) FROM knowledge_entries WHERE status = "approved"')->fetchColumn();
            $users   = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $votes   = (int) $pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn();
            json_response(['success' => true, 'stats' => compact('entries', 'users', 'votes')]);
        } catch (RuntimeException | PDOException $e) {
            json_error('Server error.', 500);
        }
    }

    // ── Single Entry ──────────────────────────────────────────
    $singleId = (int) ($_GET['id'] ?? 0);
    if ($singleId > 0) {
        try {
            $pdo  = get_pdo();
            $stmt = $pdo->prepare(
                'SELECT ke.id, ke.title, ke.summary, ke.body, ke.region, ke.era, ke.status, ke.image_path, ke.views, ke.created_at,
                        u.username,
                        c.name AS category_name, c.slug AS category_slug,
                        (SELECT COUNT(*) FROM votes v WHERE v.entry_id = ke.id AND v.vote_type = "up")   AS votes_up,
                        (SELECT COUNT(*) FROM votes v WHERE v.entry_id = ke.id AND v.vote_type = "down") AS votes_down
                 FROM   knowledge_entries ke
                 LEFT   JOIN users      u ON ke.user_id     = u.id
                 LEFT   JOIN categories c ON ke.category_id = c.id
                 WHERE  ke.id = ? AND ke.status = "approved"'
            );
            $stmt->execute([$singleId]);
            $entry = $stmt->fetch();
            if (!$entry) json_error('Entry not found.', 404);

            // Attach tags
            $tagStmt = $pdo->prepare('SELECT t.name, t.slug FROM tags t JOIN entry_tags et ON t.id = et.tag_id WHERE et.entry_id = ?');
            $tagStmt->execute([$singleId]);
            $entry['tags'] = $tagStmt->fetchAll();

            json_response(['success' => true, 'data' => $entry]);
        } catch (RuntimeException | PDOException $e) {
            json_error('Server error.', 500);
        }
    }

    // ── List Entries (paginated, filterable, sortable) ────────
    $page     = max(1, (int) ($_GET['page']     ?? 1));
    $perPage  = min(50, max(1, (int) ($_GET['per_page'] ?? 9)));
    $offset   = ($page - 1) * $perPage;
    $category = trim($_GET['category'] ?? '');
    $search   = trim($_GET['search']   ?? '');
    $sort     = trim($_GET['sort']     ?? 'newest');
    $tag      = trim($_GET['tag']      ?? '');
    $status   = $_GET['status'] ?? 'approved';

    // Non-admins can only see approved entries
    if (!is_admin()) $status = 'approved';
    if (!in_array($status, ['approved', 'pending', 'rejected', 'all'], true)) $status = 'approved';

    // Build WHERE clause
    $whereParts = [];
    $bindings   = [];

    if ($status !== 'all') {
        $whereParts[] = 'ke.status = ?';
        $bindings[]   = $status;
    }

    if ($category !== '') {
        $whereParts[] = 'c.slug = ?';
        $bindings[]   = $category;
    }

    if ($search !== '') {
        $whereParts[] = 'MATCH(ke.title, ke.summary, ke.body) AGAINST(? IN BOOLEAN MODE)';
        $bindings[]   = $search . '*';
    }

    if ($tag !== '') {
        $whereParts[] = 'ke.id IN (SELECT et.entry_id FROM entry_tags et JOIN tags t ON et.tag_id = t.id WHERE t.slug = ?)';
        $bindings[]   = $tag;
    }

    $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    try {
        $pdo = get_pdo();

        // Total count for pagination meta
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM knowledge_entries ke
             LEFT JOIN categories c ON ke.category_id = c.id $where"
        );
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetchColumn();

        $userId = current_user_id();

        // Data query
        $dataBindings   = $bindings;
        $dataBindings[] = $perPage;
        $dataBindings[] = $offset;

        // Sort order
        $orderBy = match($sort) {
            'oldest'        => 'ke.created_at ASC',
            'most_votes'    => '(SELECT COUNT(*) FROM votes v3 WHERE v3.entry_id = ke.id AND v3.vote_type = "up") DESC, ke.created_at DESC',
            'most_comments' => '(SELECT COUNT(*) FROM comments cm2 WHERE cm2.entry_id = ke.id) DESC, ke.created_at DESC',
            'most_views'    => 'ke.views DESC, ke.created_at DESC',
            default         => 'ke.created_at DESC',
        };

        $dataStmt = $pdo->prepare(
            "SELECT ke.id, ke.title, ke.summary, ke.region, ke.era, ke.status, ke.image_path, ke.views, ke.created_at,
                    u.username,
                    c.name AS category_name, c.slug AS category_slug,
                    (SELECT COUNT(*) FROM votes v WHERE v.entry_id = ke.id AND v.vote_type = 'up')   AS votes_up,
                    (SELECT COUNT(*) FROM votes v WHERE v.entry_id = ke.id AND v.vote_type = 'down') AS votes_down,
                    (SELECT COUNT(*) FROM comments cm3 WHERE cm3.entry_id = ke.id) AS comment_count
                    " . ($userId ? ",
                    (SELECT vote_type FROM votes v2 WHERE v2.entry_id = ke.id AND v2.user_id = {$userId} LIMIT 1) AS user_vote" : ", NULL AS user_vote") . "
             FROM   knowledge_entries ke
             LEFT   JOIN users      u ON ke.user_id     = u.id
             LEFT   JOIN categories c ON ke.category_id = c.id
             $where
             ORDER  BY $orderBy
             LIMIT  ? OFFSET ?"
        );
        $dataStmt->execute($dataBindings);
        $entries = $dataStmt->fetchAll();

        json_response([
            'success' => true,
            'data'    => $entries,
            'meta'    => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $total,
                'total_pages'  => (int) ceil($total / $perPage),
            ],
        ]);

    } catch (RuntimeException | PDOException $e) {
        error_log('[LK API] List error: ' . $e->getMessage());
        json_error('Server error while fetching entries.', 500);
    }
}

// ── Fallback ──────────────────────────────────────────────────
json_error('Method not allowed.', 405);
