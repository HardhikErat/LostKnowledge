<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';

$entryId = (int)($_GET['id'] ?? 0);
if ($entryId <= 0) { header('Location: /index.html'); exit; }

try {
    $pdo = get_pdo();

    // Get entry info
    $stmt = $pdo->prepare('SELECT ke.title, ke.user_id, u.username FROM knowledge_entries ke LEFT JOIN users u ON ke.user_id = u.id WHERE ke.id = ?');
    $stmt->execute([$entryId]);
    $entry = $stmt->fetch();
    if (!$entry) { header('Location: /index.html'); exit; }

    // Only owner or admin can view revisions
    if (!is_logged_in() || (current_user_id() !== (int)$entry['user_id'] && !is_admin())) {
        $_SESSION['flash_error'] = 'You do not have permission to view revision history.';
        header('Location: /entry.php?id=' . $entryId);
        exit;
    }

    // Get revisions
    $revStmt = $pdo->prepare(
        'SELECT r.*, u.username as editor
         FROM entry_revisions r
         LEFT JOIN users u ON r.user_id = u.id
         WHERE r.entry_id = ?
         ORDER BY r.revised_at DESC'
    );
    $revStmt->execute([$entryId]);
    $revisions = $revStmt->fetchAll();

} catch (Exception $e) {
    $entry = ['title' => 'Unknown']; $revisions = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Revision History — <?= htmlspecialchars($entry['title']) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css">
  <link rel="stylesheet" href="/assets/css/features.css">
</head>
<body>

<header class="site-header">
  <div class="container nav-inner">
    <a href="/index.html" class="site-logo">
      <img src="/assets/logo.png" alt="Lost Knowledge" class="nav-logo-img">
      <div class="logo-text">
        <span class="logo-mark">Lost Knowledge</span>
        <span class="logo-sub">Archive of Vanishing Wisdom</span>
      </div>
    </a>
    <button class="nav-toggle" aria-label="Menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
        <nav class="nav-links" aria-label="Main navigation">
      <a href="/index.html" class="nav-link">Archive</a>
      <a href="/explore_map.html" class="nav-link">🗺️ Map</a>
      <a href="/about.php" class="nav-link">About</a>
      <div class="nav-sep"></div>
      <a href="/register.html" class="nav-link auth-link">Register</a>
      <a href="/login.html" class="nav-link auth-link">Sign In</a>
      <a href="/submit.php" class="nav-link nav-cta">✦ Submit Entry</a>
    </nav>
  </div>
</header>

<div class="page-header">
  <div class="container">
    <div class="section-label">Version history</div>
    <h1>Revision History</h1>
    <p>Changes made to: <strong><?= htmlspecialchars($entry['title']) ?></strong></p>
  </div>
</div>

<main>
  <div class="entry-detail-wrap" style="max-width:760px;margin:0 auto;padding:2rem 1.5rem">

    <?php if (empty($revisions)): ?>
      <div class="empty-state">
        <div class="empty-icon">📜</div>
        <h3>No previous revisions</h3>
        <p>This entry has not been edited since it was created.</p>
      </div>
    <?php else: ?>
      <div style="margin-bottom:1.5rem;font-size:.9rem;color:var(--text-on-dark-muted)">
        <strong><?= count($revisions) ?></strong> revision<?= count($revisions) !== 1 ? 's' : '' ?> recorded
      </div>

      <ul class="revision-timeline">
        <?php foreach ($revisions as $i => $r): ?>
        <li class="revision-item">
          <div class="revision-meta">
            <strong><?= htmlspecialchars($r['editor'] ?? 'Unknown') ?></strong>
            · <?= date('d M Y, H:i', strtotime($r['revised_at'])) ?>
            <?php if ($i === 0): ?><span class="ec-status pending" style="margin-left:8px">Latest</span><?php endif; ?>
          </div>
          <div class="revision-title"><?= htmlspecialchars($r['title']) ?></div>
          <div class="revision-diff"><?= htmlspecialchars(substr($r['summary'], 0, 200)) ?>…</div>
          <details style="margin-top:8px">
            <summary style="font-size:12px;color:var(--amber-light);cursor:pointer">View full content</summary>
            <div style="margin-top:8px;padding:12px;background:var(--bg-void);border:1px solid var(--border-dark);border-radius:var(--radius-md);font-size:13px;color:var(--text-on-dark-muted);white-space:pre-wrap;line-height:1.7">
              <strong>Summary:</strong> <?= htmlspecialchars($r['summary']) ?>

<strong>Body:</strong>
<?= htmlspecialchars($r['body']) ?>

<?php if ($r['region']): ?><strong>Region:</strong> <?= htmlspecialchars($r['region']) ?><?php endif; ?>
<?php if ($r['era']): ?>    <strong>Era:</strong> <?= htmlspecialchars($r['era']) ?><?php endif; ?>
            </div>
          </details>
        </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--border-dark)">
      <a href="/entry.php?id=<?= $entryId ?>" class="btn btn-ghost" style="padding-left:0">← Back to entry</a>
    </div>
  </div>
</main>

<footer class="site-footer" style="padding:1.5rem 0">
  <div class="container"><div class="footer-bottom"><span>&copy; <?= date('Y') ?> Lost Knowledge</span></div></div>
</footer>

<script src="/assets/js/script.js"></script>
<script src="/assets/js/features.js"></script>
</body>
</html>
