<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/notify.php';
require_login('/dashboard.php');

$userId   = current_user_id();
$username = current_username();
$role     = $_SESSION['role'];

try {
  $pdo = get_pdo();

  $stmt = $pdo->prepare(
    'SELECT ke.id, ke.title, ke.status, ke.created_at,
            c.name AS cat,
            (SELECT COUNT(*) FROM votes v WHERE v.entry_id=ke.id AND v.vote_type="up")   AS up,
            (SELECT COUNT(*) FROM votes v WHERE v.entry_id=ke.id AND v.vote_type="down") AS dn
     FROM knowledge_entries ke
     LEFT JOIN categories c ON ke.category_id=c.id
     WHERE ke.user_id=? ORDER BY ke.created_at DESC'
  );
  $stmt->execute([$userId]);
  $entries = $stmt->fetchAll();

  $stats = $pdo->prepare(
    'SELECT COUNT(*) t,
            SUM(status="approved") a,
            SUM(status="pending")  p,
            SUM(status="rejected") r
     FROM knowledge_entries WHERE user_id=?'
  );
  $stats->execute([$userId]);
  $s = $stats->fetch();

} catch (Exception $e) {
  $entries = []; $s = ['t'=>0,'a'=>0,'p'=>0,'r'=>0];
}

// Bookmarked entries
try {
  $bmStmt = $pdo->prepare(
    'SELECT ke.id, ke.title, ke.status, c.name AS cat, bm.created_at AS saved_at
     FROM bookmarks bm
     JOIN knowledge_entries ke ON bm.entry_id = ke.id
     LEFT JOIN categories c ON ke.category_id = c.id
     WHERE bm.user_id = ? ORDER BY bm.created_at DESC'
  );
  $bmStmt->execute([$userId]);
  $bookmarks = $bmStmt->fetchAll();
} catch (Exception $e) { $bookmarks = []; }

$ok  = $_SESSION['flash_success'] ?? ''; unset($_SESSION['flash_success']);
$err = $_SESSION['flash_error']   ?? ''; unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Lost Knowledge</title>
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
    <nav class="nav-links">
      <a href="/lost-knowledge/index.html"    class="nav-link">Archive</a>
      <a href="/lost-knowledge/dashboard.php" class="nav-link active">Dashboard</a>
      <?php if ($role==='admin'): ?>
      <a href="/lost-knowledge/admin/admin_dashboard.php" class="nav-link">Admin</a>
      <?php endif; ?>
      <a href="/lost-knowledge/submit.php"    class="nav-link nav-cta">+ Submit</a>
      <a href="/lost-knowledge/logout.php"    class="nav-link">Sign Out</a>
    </nav>
  </div>
</header>

<div class="page-header">
  <div class="container">
    <div class="section-label">Keeper portal</div>
    <h1>Welcome back, <?= $username ?>.</h1>
  </div>
</div>

<main>
  <div class="container">
    <div class="dash-wrap">

      <!-- Sidebar -->
      <aside class="dash-sidebar">
        <div class="dash-user">
          <div class="dash-avatar"><?= strtoupper(substr($username,0,1)) ?></div>
          <div>
            <div class="dash-uname"><?= $username ?></div>
            <div class="dash-role"><?= ucfirst($role) ?></div>
          </div>
        </div>
        <ul class="dash-nav">
          <li><a class="active" href="/lost-knowledge/dashboard.php">📜 &nbsp;My Entries</a></li>
          <li><a href="/lost-knowledge/dashboard.php?tab=bookmarks">🔖 &nbsp;Saved Entries</a></li>
          <li><a href="/lost-knowledge/edit_profile.php">✏️ &nbsp;Edit Profile</a></li>
          <li><a href="/lost-knowledge/profile.php?username=<?= urlencode($username) ?>">👤 &nbsp;My Profile</a></li>
          <li><a href="/lost-knowledge/submit.php">✦ &nbsp;New Entry</a></li>
          <?php if ($role==='admin'): ?>
          <li><a href="/lost-knowledge/admin/admin_dashboard.php">⚙ &nbsp;Admin Panel</a></li>
          <?php endif; ?>
          <li><a href="/lost-knowledge/logout.php">↩ &nbsp;Sign Out</a></li>
        </ul>

        <?php
          // Karma display
          $userKarma = 0;
          try { $kStmt = $pdo->prepare('SELECT karma FROM users WHERE id = ?'); $kStmt->execute([$userId]); $userKarma = (int)$kStmt->fetchColumn(); } catch(Exception $e) {}
          [$kLevel, $kClass] = karma_level($userKarma);
        ?>
        <div style="margin-top:1.5rem;padding:1rem;background:var(--bg-panel);border:1px solid var(--border-dark);border-radius:var(--radius-lg);text-align:center">
          <div style="font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--text-on-dark-faint);margin-bottom:8px">Reputation</div>
          <div style="font-size:1.5rem;font-weight:700;color:var(--amber-light)"><?= number_format($userKarma) ?></div>
          <div style="margin-top:4px"><span class="karma-badge <?= $kClass ?>">✦ <?= $kLevel ?></span></div>
          <div style="font-size:11px;color:var(--text-on-dark-faint);margin-top:8px;line-height:1.5">
            Submit entries, get votes, and contribute to grow your karma.
          </div>
        </div>


      </aside>

      <!-- Main -->
      <div class="dash-content">

        <?php if ($ok): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($ok) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-error" data-autohide><?= htmlspecialchars($err) ?></div><?php endif; ?>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:.75rem">
          <h2 style="border:none;padding:0;margin:0">
            <?php echo isset($_GET['tab']) && $_GET['tab']==='bookmarks' ? 'Saved Entries' : 'My Contributions'; ?>
          </h2>
          <a href="/lost-knowledge/submit.php" class="btn btn-primary btn-sm">+ Add Entry</a>
        </div>

        <?php if (isset($_GET['tab']) && $_GET['tab'] === 'bookmarks'): ?>
        <!-- BOOKMARKS TAB -->
        <?php if (empty($bookmarks)): ?>
          <div class="empty-state">
            <div class="empty-icon">🔖</div>
            <h3>No saved entries</h3>
            <p>Browse the archive and click <strong>☆ Save Entry</strong> on any entry to bookmark it.</p>
            <a href="/lost-knowledge/index.html" class="btn btn-outline mt-2">Browse Archive</a>
          </div>
        <?php else: ?>
          <div class="data-table-wrap">
            <table class="data-table">
              <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Saved On</th><th>Action</th></tr></thead>
              <tbody>
                <?php foreach ($bookmarks as $b): ?>
                <tr>
                  <td><a href="/lost-knowledge/entry.php?id=<?= $b['id'] ?>" style="color:var(--amber-dark);font-weight:500"><?= htmlspecialchars($b['title']) ?></a></td>
                  <td><?= htmlspecialchars($b['cat'] ?? '—') ?></td>
                  <td><span class="ec-status <?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                  <td style="font-size:.82rem"><?= date('d M Y', strtotime($b['saved_at'])) ?></td>
                  <td>
                    <form method="POST" action="/lost-knowledge/bookmark_process.php" style="display:inline">
                      <input type="hidden" name="entry_id" value="<?= $b['id'] ?>">
                      <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red-accent)"
                              onclick="return confirm('Remove this bookmark?')">✕ Remove</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- MY ENTRIES TAB (original) -->

        <!-- Tiles -->
        <div class="tile-row">
          <div class="tile"><div class="tile-num" style="color:var(--amber-dark)"><?= $s['t'] ?></div><div class="tile-lbl">Total</div></div>
          <div class="tile"><div class="tile-num" style="color:var(--green-acc)"><?= $s['a'] ?></div><div class="tile-lbl">Approved</div></div>
          <div class="tile"><div class="tile-num" style="color:var(--amber)"><?= $s['p'] ?></div><div class="tile-lbl">Pending</div></div>
          <div class="tile"><div class="tile-num" style="color:var(--red-accent)"><?= $s['r'] ?></div><div class="tile-lbl">Rejected</div></div>
        </div>

        <?php if (empty($entries)): ?>
          <div class="empty-state">
            <div class="empty-icon">📝</div>
            <h3>No entries yet</h3>
            <p>Share the first piece of knowledge you want to preserve.</p>
            <a href="/lost-knowledge/submit.php" class="btn btn-outline mt-2">Submit your first entry</a>
          </div>
        <?php else: ?>
          <div class="data-table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Title</th><th>Category</th><th>Status</th>
                  <th>Votes</th><th>Date</th><th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($entries as $e): ?>
                <tr>
                  <td style="max-width:220px">
                    <a href="/lost-knowledge/entry.php?id=<?= $e['id'] ?>" style="color:var(--amber-dark);font-weight:500">
                      <?= htmlspecialchars($e['title']) ?>
                    </a>
                  </td>
                  <td><?= htmlspecialchars($e['cat'] ?? '—') ?></td>
                  <td>
                    <span class="ec-status <?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span>
                  </td>
                  <td style="font-weight:600;font-size:.82rem">
                    <span style="color:var(--green-acc)">▲ <?= $e['up'] ?></span>
                    &nbsp;
                    <span style="color:var(--red-accent)">▼ <?= $e['dn'] ?></span>
                  </td>
                  <td style="white-space:nowrap;font-size:.82rem"><?= date('d M Y', strtotime($e['created_at'])) ?></td>
                  <td>
                    <div class="table-actions">
                      <a href="/lost-knowledge/edit_entry.php?id=<?= $e['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                      <a href="/lost-knowledge/delete_entry.php?id=<?= $e['id'] ?>"
                         class="btn btn-danger btn-sm"
                         data-confirm="Delete this entry permanently?">Del</a>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <?php endif; // end tab check ?>

      </div><!-- /dash-content -->
    </div><!-- /dash-wrap -->
  </div>
</main>

<footer class="site-footer" style="padding:1.5rem 0">
  <div class="container">
    <div class="footer-bottom"><span>&copy; <?= date('Y') ?> Lost Knowledge</span></div>
  </div>
</footer>

<script src="/lost-knowledge/assets/js/script.js"></script>
<script src="/lost-knowledge/assets/js/features.js"></script>
</body>
</html>
