<?php
require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/notify.php';
require_admin();

// Handle approve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
  $action  = $_POST['action'];
  $entryId = (int)($_POST['entry_id'] ?? 0);
  if ($entryId > 0 && in_array($action, ['approve','reject'])) {
    $status = $action === 'approve' ? 'approved' : 'rejected';
    try {
      $pdo = get_pdo();
      // Get the entry owner before updating
      $ownerStmt = $pdo->prepare('SELECT user_id, title FROM knowledge_entries WHERE id = ?');
      $ownerStmt->execute([$entryId]);
      $entryInfo = $ownerStmt->fetch();

      $pdo->prepare('UPDATE knowledge_entries SET status=?, updated_at=NOW() WHERE id=?')
               ->execute([$status, $entryId]);

      // Send notification & karma to the entry author
      if ($entryInfo) {
        $ownerId = (int)$entryInfo['user_id'];
        $entryTitle = $entryInfo['title'];
        if ($action === 'approve') {
          create_notification($ownerId, 'entry_approved',
            "Your entry \"{$entryTitle}\" has been approved and is now live!",
            "/lost-knowledge/entry.php?id={$entryId}");
          award_karma($ownerId, 25); // +25 karma for approval
        } else {
          create_notification($ownerId, 'entry_rejected',
            "Your entry \"{$entryTitle}\" was not approved. Consider revising and resubmitting.",
            "/lost-knowledge/edit_entry.php?id={$entryId}");
        }
      }

      $_SESSION['flash_success'] = "Entry #{$entryId} has been {$status}.";
    } catch (Exception $e) {
      $_SESSION['flash_error'] = 'Action failed.';
    }
  }
  header('Location: /lost-knowledge/admin/admin_dashboard.php'); exit;
}

try {
  $pdo = get_pdo();
  $stats = $pdo->query(
    'SELECT
      (SELECT COUNT(*) FROM users) users,
      (SELECT COUNT(*) FROM knowledge_entries) total,
      (SELECT COUNT(*) FROM knowledge_entries WHERE status="pending")  pending,
      (SELECT COUNT(*) FROM knowledge_entries WHERE status="approved") approved,
      (SELECT COUNT(*) FROM knowledge_entries WHERE status="rejected") rejected,
      (SELECT COUNT(*) FROM votes) votes'
  )->fetch();

  $pending = $pdo->query(
    'SELECT ke.id,ke.title,ke.summary,ke.created_at,ke.region,
            u.username, c.name cat
     FROM knowledge_entries ke
     LEFT JOIN users u ON ke.user_id=u.id
     LEFT JOIN categories c ON ke.category_id=c.id
     WHERE ke.status="pending" ORDER BY ke.created_at ASC LIMIT 50'
  )->fetchAll();

  $allEntries = $pdo->query(
    'SELECT ke.id,ke.title,ke.status,ke.created_at, u.username, c.name cat
     FROM knowledge_entries ke
     LEFT JOIN users u ON ke.user_id=u.id
     LEFT JOIN categories c ON ke.category_id=c.id
     ORDER BY ke.created_at DESC LIMIT 100'
  )->fetchAll();

  $users = $pdo->query(
    'SELECT id,username,email,role,created_at FROM users ORDER BY created_at DESC LIMIT 30'
  )->fetchAll();

} catch (Exception $e) {
  $stats = array_fill_keys(['users','total','pending','approved','rejected','votes'], 0);
  $pending = $allEntries = $users = [];
}

$ok  = $_SESSION['flash_success'] ?? ''; unset($_SESSION['flash_success']);
$err = $_SESSION['flash_error']   ?? ''; unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Lost Knowledge</title>
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
      <a href="/lost-knowledge/index.html"                class="nav-link">Archive</a>
      <a href="/lost-knowledge/dashboard.php"             class="nav-link">My Dashboard</a>
      <a href="/lost-knowledge/admin/admin_dashboard.php" class="nav-link active">Admin</a>
      <a href="/lost-knowledge/logout.php"                class="nav-link">Sign Out</a>
    </nav>
  </div>
</header>

<div class="page-header">
  <div class="container">
    <div class="section-label">Administration</div>
    <h1>Archive Control Centre</h1>
    <p>Review submissions, manage entries, and oversee the community.</p>
  </div>
</div>

<main>
  <div class="container" style="padding-top:2.5rem;padding-bottom:4rem">

    <?php if ($ok):  ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($ok)  ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"   data-autohide><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- Stats tiles -->
    <div class="admin-grid">
      <?php
      $tiles = [
        ['Users',    $stats['users'],    'var(--ink-brown)'],
        ['Entries',  $stats['total'],    'var(--ink-brown)'],
        ['Pending',  $stats['pending'],  'var(--amber)'],
        ['Approved', $stats['approved'], 'var(--green)'],
        ['Rejected', $stats['rejected'], 'var(--burgundy)'],
        ['Votes',    $stats['votes'],    'var(--amber-dark)'],
      ];
      foreach ($tiles as [$lbl,$val,$color]): ?>
      <div class="admin-tile">
        <div class="tile-num" style="color:<?= $color ?>"><?= number_format($val) ?></div>
        <div class="tile-lbl"><?= $lbl ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Tabs -->
    <div class="admin-tabs">
      <span class="admin-tab" id="tab-pending" onclick="showTab('pending')">
        Pending Review <?php if (count($pending)): ?><span style="background:var(--amber);color:#fff;border-radius:10px;padding:.1rem .5rem;font-size:.7rem;margin-left:.4rem"><?= count($pending) ?></span><?php endif; ?>
      </span>
      <span class="admin-tab" id="tab-all"      onclick="showTab('all')">All Entries</span>
      <span class="admin-tab" id="tab-users"    onclick="showTab('users')">Keepers</span>
      <a href="/lost-knowledge/admin/feedback.php" class="admin-tab" style="text-decoration:none">
        Feedback
        <?php
          try {
            $unreadFb = get_pdo()->query('SELECT COUNT(*) FROM feedback WHERE status="unread"')->fetchColumn();
            if ($unreadFb > 0) echo '<span style="background:var(--amber);color:#fff;border-radius:10px;padding:.1rem .5rem;font-size:.7rem;margin-left:.4rem">' . $unreadFb . '</span>';
          } catch (Exception $e) {}
        ?>
      </a>
    </div>

    <!-- Pending tab -->
    <div id="panel-pending">
      <?php if (empty($pending)): ?>
        <div class="empty-state" style="padding:2.5rem">
          <div class="empty-icon">✅</div>
          <h3>Queue is clear</h3>
          <p>No entries awaiting review.</p>
        </div>
      <?php else: ?>
        <?php foreach ($pending as $e): ?>
        <div class="review-card">
          <div class="review-card-top">
            <div style="flex:1;min-width:0">
              <div style="font-size:.68rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--amber-dark);margin-bottom:.35rem">
                <?= htmlspecialchars($e['cat'] ?? 'Uncategorised') ?>
              </div>
              <a href="/lost-knowledge/entry.php?id=<?= $e['id'] ?>" style="font-family:'Playfair Display',Georgia,serif;font-size:1.1rem;font-weight:700;color:var(--ink-black)">
                <?= htmlspecialchars($e['title']) ?>
              </a>
              <p style="color:var(--text-faint);font-size:.875rem;margin:.4rem 0 .5rem">
                <?= htmlspecialchars(substr($e['summary'],0,180)) ?>…
              </p>
              <div style="font-size:.75rem;color:var(--text-faint)">
                By <strong><?= htmlspecialchars($e['username']) ?></strong>
                · <?= date('d M Y', strtotime($e['created_at'])) ?>
                <?php if ($e['region']): ?> · <?= htmlspecialchars($e['region']) ?><?php endif; ?>
              </div>
            </div>
            <div class="review-actions">
              <form method="POST" style="display:inline">
                <input type="hidden" name="action"   value="approve">
                <input type="hidden" name="entry_id" value="<?= $e['id'] ?>">
                <button type="submit" class="btn-approve">✓ Approve</button>
              </form>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action"   value="reject">
                <input type="hidden" name="entry_id" value="<?= $e['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">✕ Reject</button>
              </form>
              <a href="/lost-knowledge/edit_entry.php?id=<?= $e['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- All entries tab -->
    <div id="panel-all" style="display:none">
      <div class="data-table-wrap">
        <table class="data-table">
          <thead><tr><th>#</th><th>Title</th><th>Category</th><th>By</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($allEntries as $e): ?>
            <tr>
              <td style="color:var(--text-faint)"><?= $e['id'] ?></td>
              <td style="max-width:220px"><a href="/lost-knowledge/entry.php?id=<?= $e['id'] ?>" style="color:var(--amber-dark);font-weight:500"><?= htmlspecialchars($e['title']) ?></a></td>
              <td><?= htmlspecialchars($e['cat'] ?? '—') ?></td>
              <td><?= htmlspecialchars($e['username']) ?></td>
              <td><span class="ec-status <?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></td>
              <td style="white-space:nowrap;font-size:.82rem"><?= date('d M Y', strtotime($e['created_at'])) ?></td>
              <td>
                <div class="table-actions">
                  <?php if ($e['status'] !== 'approved'): ?>
                  <form method="POST" style="display:inline"><input type="hidden" name="action" value="approve"><input type="hidden" name="entry_id" value="<?= $e['id'] ?>"><button type="submit" class="btn-approve" style="font-size:.72rem;padding:.3rem .6rem">✓</button></form>
                  <?php endif; ?>
                  <?php if ($e['status'] !== 'rejected'): ?>
                  <form method="POST" style="display:inline"><input type="hidden" name="action" value="reject"><input type="hidden" name="entry_id" value="<?= $e['id'] ?>"><button type="submit" class="btn btn-danger btn-sm" style="padding:.3rem .55rem">✕</button></form>
                  <?php endif; ?>
                  <a href="/lost-knowledge/edit_entry.php?id=<?= $e['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
                  <a href="/lost-knowledge/delete_entry.php?id=<?= $e['id'] ?>" class="btn btn-danger btn-sm" data-confirm="Delete entry #<?= $e['id'] ?> permanently?">Del</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Users tab -->
    <div id="panel-users" style="display:none">
      <div class="data-table-wrap">
        <table class="data-table">
          <thead><tr><th>#</th><th>Username</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td style="color:var(--text-faint)"><?= $u['id'] ?></td>
              <td style="font-weight:500;color:var(--ink-black)"><?= htmlspecialchars($u['username']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td><span class="ec-status <?= $u['role']==='admin' ? 'approved' : 'pending' ?>"><?= ucfirst($u['role']) ?></span></td>
              <td style="font-size:.82rem"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<footer class="site-footer" style="padding:1.5rem 0">
  <div class="container">
    <div class="footer-bottom"><span>&copy; <?= date('Y') ?> Lost Knowledge — Admin</span></div>
  </div>
</footer>

<script src="/lost-knowledge/assets/js/script.js"></script>
<script src="/lost-knowledge/assets/js/features.js"></script>
<script>
function showTab(name) {
  ['pending','all','users'].forEach(t => {
    document.getElementById('panel-' + t).style.display = t===name ? '' : 'none';
    document.getElementById('tab-' + t).classList.toggle('active', t===name);
  });
}
</script>
</body>
</html>
