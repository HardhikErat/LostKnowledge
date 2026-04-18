<?php
// ============================================================
// profile.php — Public user profile page
// Shows user info, karma level, and their approved entries.
// URL: /lost-knowledge/profile.php?username=...
// ============================================================

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/notify.php';

$profileUsername = trim($_GET['username'] ?? '');
if (empty($profileUsername)) {
    header('Location: /lost-knowledge/index.html');
    exit;
}

try {
    $pdo = get_pdo();

    // Fetch user data
    $stmt = $pdo->prepare(
        'SELECT id, username, bio, avatar_path, karma, role, created_at
         FROM users WHERE username = ? LIMIT 1'
    );
    $stmt->execute([$profileUsername]);
    $user = $stmt->fetch();

    if (!$user) {
        $notFound = true;
    } else {
        // Karma level
        [$karmaLevel, $karmaClass] = karma_level($user['karma'] ?? 0);

        // Total entries
        $entryStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM knowledge_entries WHERE user_id = ? AND status = "approved"'
        );
        $entryStmt->execute([$user['id']]);
        $totalEntries = (int) $entryStmt->fetchColumn();

        // Total votes received
        $voteStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(CASE WHEN v.vote_type="up" THEN 1 ELSE 0 END),0) AS up,
                    COALESCE(SUM(CASE WHEN v.vote_type="down" THEN 1 ELSE 0 END),0) AS dn
             FROM votes v
             JOIN knowledge_entries ke ON v.entry_id = ke.id
             WHERE ke.user_id = ?'
        );
        $voteStmt->execute([$user['id']]);
        $votes = $voteStmt->fetch();

        // Total views
        $viewStmt = $pdo->prepare(
            'SELECT COALESCE(SUM(views), 0) FROM knowledge_entries WHERE user_id = ? AND status = "approved"'
        );
        $viewStmt->execute([$user['id']]);
        $totalViews = (int) $viewStmt->fetchColumn();

        // Approved entries
        $entriesStmt = $pdo->prepare(
            'SELECT ke.id, ke.title, ke.summary, ke.image_path, ke.views, ke.region, ke.created_at,
                    c.name AS category_name,
                    (SELECT COUNT(*) FROM votes v WHERE v.entry_id=ke.id AND v.vote_type="up") AS votes_up
             FROM knowledge_entries ke
             LEFT JOIN categories c ON ke.category_id = c.id
             WHERE ke.user_id = ? AND ke.status = "approved"
             ORDER BY ke.created_at DESC'
        );
        $entriesStmt->execute([$user['id']]);
        $entries = $entriesStmt->fetchAll();

        // Member since
        $memberSince = date('F Y', strtotime($user['created_at']));
        $notFound = false;
    }
} catch (Exception $e) {
    error_log('[LK] profile.php error: ' . $e->getMessage());
    $notFound = true;
}

$isOwnProfile = is_logged_in() && current_username() === $profileUsername;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= $notFound ? 'User not found' : htmlspecialchars($profileUsername) . ' — Knowledge Keeper profile on Lost Knowledge' ?>">
  <title><?= $notFound ? 'User Not Found' : htmlspecialchars($profileUsername) ?> — Lost Knowledge</title>
  <link rel="stylesheet" href="/lost-knowledge/assets/css/style.css">
  <link rel="stylesheet" href="/lost-knowledge/assets/css/features.css">
</head>
<body>

<header class="site-header">
  <div class="container nav-inner">
    <a href="/lost-knowledge/index.html" class="site-logo">
      <img src="/lost-knowledge/assets/logo.png" alt="Lost Knowledge" class="nav-logo-img">
      <div class="logo-text">
        <span class="logo-mark">Lost Knowledge</span>
        <span class="logo-sub">Archive of Vanishing Wisdom</span>
      </div>
    </a>
    <button class="nav-toggle" aria-label="Menu" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="nav-links" aria-label="Main navigation">
      <a href="/lost-knowledge/index.html" class="nav-link">Archive</a>
      <a href="/lost-knowledge/about.php" class="nav-link">About</a>
      <div class="nav-sep"></div>
      <?php if (is_logged_in()): ?>
        <a href="/lost-knowledge/dashboard.php" class="nav-link">Dashboard</a>
        <a href="/lost-knowledge/logout.php" class="nav-link">Sign Out</a>
      <?php else: ?>
        <a href="/lost-knowledge/register.html" class="nav-link">Register</a>
        <a href="/lost-knowledge/login.html" class="nav-link">Sign In</a>
      <?php endif; ?>
      <a href="/lost-knowledge/submit.php" class="nav-link nav-cta">✦ Submit Entry</a>
    </nav>
  </div>
</header>

<?php if ($notFound): ?>

<!-- ═══ USER NOT FOUND ═══ -->
<div class="page-header">
  <div class="container">
    <div class="section-label">Profile</div>
    <h1>User Not Found</h1>
    <p>The profile you're looking for doesn't exist or has been removed.</p>
  </div>
</div>
<main>
  <div class="form-wrap" style="text-align:center;padding:4rem 1rem">
    <div style="font-size:4rem;margin-bottom:1rem">🔍</div>
    <h3 style="color:var(--text-on-dark);margin-bottom:.5rem">No keeper found</h3>
    <p style="color:var(--text-on-dark-muted)">The username "<strong><?= htmlspecialchars($profileUsername) ?></strong>" doesn't match any account.</p>
    <a href="/lost-knowledge/index.html" class="btn btn-amber" style="margin-top:1.5rem">← Back to Archive</a>
  </div>
</main>

<?php else: ?>

<!-- ═══ PROFILE HEADER ═══ -->
<div class="page-header">
  <div class="container">
    <div class="section-label">Knowledge Keeper</div>
    <h1><?= htmlspecialchars($user['username']) ?></h1>
    <p>Member since <?= $memberSince ?> · <?= $karmaLevel ?> (<?= (int)$user['karma'] ?> karma)</p>
  </div>
</div>

<main>
  <div class="container" style="padding:3rem 0;max-width:960px;margin:0 auto">

    <!-- Profile Card -->
    <div style="display:grid;grid-template-columns:auto 1fr;gap:2rem;align-items:start;margin-bottom:3rem">

      <!-- Avatar -->
      <div style="width:120px;height:120px;border-radius:50%;overflow:hidden;border:3px solid var(--border-gold);display:flex;align-items:center;justify-content:center;background:var(--bg-panel);font-size:3rem;color:var(--amber-light);font-family:var(--font-display)">
        <?php if (!empty($user['avatar_path'])): ?>
          <img src="<?= htmlspecialchars($user['avatar_path']) ?>" alt="Avatar" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <?= strtoupper(substr($user['username'], 0, 1)) ?>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
          <h2 style="font-family:var(--font-display);color:var(--text-on-dark);margin:0;font-size:1.6rem"><?= htmlspecialchars($user['username']) ?></h2>
          <span class="karma-badge <?= $karmaClass ?>" style="font-size:.75rem;padding:3px 10px;border-radius:12px;font-weight:600;background:var(--bg-panel);border:1px solid var(--border-gold);color:var(--amber-light)"><?= $karmaLevel ?></span>
          <?php if ($user['role'] === 'admin'): ?>
            <span style="font-size:.72rem;padding:3px 10px;border-radius:12px;font-weight:600;background:var(--burgundy-pale);border:1px solid var(--red);color:#e88">Admin</span>
          <?php endif; ?>
        </div>

        <?php if (!empty($user['bio'])): ?>
          <p style="color:var(--text-on-dark-muted);line-height:1.7;margin-bottom:16px"><?= nl2br(htmlspecialchars($user['bio'])) ?></p>
        <?php else: ?>
          <p style="color:var(--text-on-dark-faint);font-style:italic;margin-bottom:16px">No bio yet.</p>
        <?php endif; ?>

        <?php if ($isOwnProfile): ?>
          <a href="/lost-knowledge/edit_profile.php" class="btn btn-amber" style="font-size:.82rem;padding:6px 18px">✏️ Edit Profile</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Stats Row -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:3rem">
      <?php
      $stats = [
          ['📜', $totalEntries, 'Entries'],
          ['▲', $votes['up'] ?? 0, 'Upvotes'],
          ['👁️', $totalViews, 'Views'],
          ['✦', (int)$user['karma'], 'Karma'],
      ];
      foreach ($stats as [$icon, $val, $label]):
      ?>
      <div style="background:var(--bg-panel);border:1px solid var(--border-dark);border-radius:8px;padding:20px;text-align:center">
        <div style="font-size:1.5rem;margin-bottom:4px"><?= $icon ?></div>
        <div style="font-size:1.4rem;font-weight:700;color:var(--amber-light);font-family:var(--font-display)"><?= number_format($val) ?></div>
        <div style="font-size:.78rem;color:var(--text-on-dark-muted);text-transform:uppercase;letter-spacing:.08em"><?= $label ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Entries -->
    <div style="margin-bottom:2rem">
      <h3 style="font-family:var(--font-display);color:var(--text-on-dark);margin-bottom:6px">Published Entries</h3>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
        <div style="width:60px;height:1px;background:var(--border-gold)"></div>
        <span style="color:var(--gold);font-size:10px">✦</span>
        <div style="width:60px;height:1px;background:var(--border-gold)"></div>
      </div>
    </div>

    <?php if (empty($entries)): ?>
      <div style="text-align:center;padding:3rem;background:var(--bg-panel);border:1px solid var(--border-dark);border-radius:8px">
        <div style="font-size:2.5rem;margin-bottom:8px">📭</div>
        <h3 style="color:var(--text-on-dark);margin-bottom:4px">No entries yet</h3>
        <p style="color:var(--text-on-dark-muted)">This keeper hasn't published any entries.</p>
      </div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem">
        <?php foreach ($entries as $entry): ?>
        <article class="entry-card" onclick="window.location.href='/lost-knowledge/entry.php?id=<?= $entry['id'] ?>'" role="button" tabindex="0" style="cursor:pointer">
          <?php if ($entry['image_path']): ?>
            <img src="<?= htmlspecialchars($entry['image_path']) ?>" alt="" class="ec-image">
          <?php endif; ?>
          <div class="ec-top">
            <span class="ec-cat"><?= htmlspecialchars($entry['category_name'] ?? 'General') ?></span>
          </div>
          <h3 class="ec-title"><?= htmlspecialchars($entry['title']) ?></h3>
          <p class="ec-summary"><?= htmlspecialchars($entry['summary']) ?></p>
          <div class="ec-foot">
            <div class="ec-meta">
              <?php if ($entry['region']): ?><span><?= htmlspecialchars($entry['region']) ?></span><span class="ec-meta-sep">·</span><?php endif; ?>
              <span>👁️ <?= $entry['views'] ?></span>
              <span class="ec-meta-sep">·</span>
              <span>▲ <?= $entry['votes_up'] ?></span>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</main>

<?php endif; ?>

<footer class="site-footer" style="padding:1.5rem 0">
  <div class="container">
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> Lost Knowledge</span>
      <span class="footer-bottom-diamond">✦</span>
      <span>Preserving wisdom for tomorrow</span>
    </div>
  </div>
</footer>

<script src="/lost-knowledge/assets/js/script.js"></script>
<script src="/lost-knowledge/assets/js/features.js"></script>
</body>
</html>
