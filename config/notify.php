<?php
// ============================================================
// config/notify.php — Notification + Karma helper functions
// ============================================================

require_once __DIR__ . '/db.php';

/**
 * Create a notification for a user.
 */
function create_notification(int $userId, string $type, string $message, ?string $link = null): void
{
    try {
        get_pdo()->prepare(
            'INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)'
        )->execute([$userId, $type, $message, $link]);
    } catch (Exception $e) {
        error_log('[LK] Notification error: ' . $e->getMessage());
    }
}

/**
 * Award karma points to a user.
 */
function award_karma(int $userId, int $points): void
{
    try {
        get_pdo()->prepare('UPDATE users SET karma = karma + ? WHERE id = ?')
            ->execute([$points, $userId]);
    } catch (Exception $e) {
        error_log('[LK] Karma error: ' . $e->getMessage());
    }
}

/**
 * Get karma level name and CSS class.
 */
function karma_level(int $karma): array
{
    if ($karma >= 500) return ['Elder', 'elder'];
    if ($karma >= 200) return ['Sage', 'sage'];
    if ($karma >= 50)  return ['Scholar', 'scholar'];
    return ['Novice', 'novice'];
}

/**
 * Save an entry revision before editing.
 */
function save_revision(int $entryId, int $userId, array $entry): void
{
    try {
        get_pdo()->prepare(
            'INSERT INTO entry_revisions (entry_id, user_id, title, summary, body, region, era)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $entryId, $userId,
            $entry['title'], $entry['summary'], $entry['body'],
            $entry['region'] ?? null, $entry['era'] ?? null
        ]);
    } catch (Exception $e) {
        error_log('[LK] Revision save error: ' . $e->getMessage());
    }
}

/**
 * Process tags for an entry (comma-separated string).
 */
function process_tags(int $entryId, string $tagString): void
{
    try {
        $pdo = get_pdo();
        // Clear old tags
        $pdo->prepare('DELETE FROM entry_tags WHERE entry_id = ?')->execute([$entryId]);

        $tags = array_filter(array_map('trim', explode(',', $tagString)));
        foreach ($tags as $tag) {
            $tag = preg_replace('/[^a-z0-9\-]/', '', strtolower($tag));
            if (strlen($tag) < 2) continue;

            $slug = $tag;
            // Upsert tag
            $pdo->prepare('INSERT IGNORE INTO tags (name, slug) VALUES (?, ?)')->execute([$tag, $slug]);
            $tagId = $pdo->query("SELECT id FROM tags WHERE slug = " . $pdo->quote($slug))->fetchColumn();
            if ($tagId) {
                $pdo->prepare('INSERT IGNORE INTO entry_tags (entry_id, tag_id) VALUES (?, ?)')->execute([$entryId, $tagId]);
            }
        }
    } catch (Exception $e) {
        error_log('[LK] Tag processing error: ' . $e->getMessage());
    }
}

/**
 * Get tags for an entry.
 */
function get_entry_tags(int $entryId): array
{
    try {
        $stmt = get_pdo()->prepare(
            'SELECT t.name, t.slug FROM tags t
             JOIN entry_tags et ON t.id = et.tag_id
             WHERE et.entry_id = ?
             ORDER BY t.name'
        );
        $stmt->execute([$entryId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}
