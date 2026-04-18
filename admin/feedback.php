<?php
// ============================================================
// admin/feedback.php — View & manage all feedback submissions
// Admin only
// ============================================================

require_once dirname(__DIR__) . '/config/auth.php';
require_once dirname(__DIR__) . '/config/db.php';
require_admin();

// ── Handle status update ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $fbId   = (int)($_POST['id'] ?? 0);

    if ($fbId > 0 && in_array($action, ['read','unread','resolved','delete'], true)) {
        try {
            $pdo = get_pdo();
            if ($action === 'delete') {
                $pdo->prepare('DELETE FROM feedback WHERE id = ?')->execute([$fbId]);
                $_SESSION['flash_success'] = 'Feedback #' . $fbId . ' deleted.';
            } else {
                $pdo->prepare('UPDATE feedback SET status = ? WHERE id = ?')->execute([$action, $fbId]);
                $_SESSION['flash_success'] = 'Feedback #' . $fbId . ' marked as ' . $action . '.';
            }
        } catch (Exception $e) {
            $_SESSION['flash_error'] = 'Action failed. Please try again.';
        }
    }
    header('Location: /lost-knowledge/admin/feedback.php');
    exit;
}

// ── Filters ───────────────────────────────────────────────
$filterStatus  = $_GET['status']  ?? 'all';
$filterSubject = $_GET['subject'] ?? 'all';
$page          = max(1, (int)($_GET['page'] ?? 1));
$perPage       = 15;
$offset        = ($page - 1) * $perPage;

$where    = [];
$bindings = [];

if ($filterStatus !== 'all' && in_array($filterStatus, ['unread','read','resolved'])) {
    $where[]    = 'status = ?';
    $bindings[] = $filterStatus;
}
if ($filterSubject !== 'all') {
    $where[]    = 'subject = ?';
    $bindings[] = $filterSubject;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $pdo = get_pdo();

    // Counts for tabs
    $counts = $pdo->query(
        'SELECT status, COUNT(*) n FROM feedback GROUP BY status'
    )->fetchAll();
    $statusCounts = ['unread' => 0, 'read' => 0, 'resolved' => 0];
    foreach ($counts as $c) $statusCounts[$c['status']] = (int)$c['n'];
    $totalAll = array_sum($statusCounts);

    // Subject breakdown
    $subjectCounts = $pdo->query(
        'SELECT subject, COUNT(*) n FROM feedback GROUP BY subject ORDER BY n DESC'
    )->fetchAll();

    // Total for pagination
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM feedback $whereSQL");
    $totalStmt->execute($bindings);
    $total      = (int)$totalStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));

    // Fetch page
    $dataBindings   = array_merge($bindings, [$perPage, $offset]);
    $rows = $pdo->prepare(
        "SELECT * FROM feedback $whereSQL ORDER BY created_at DESC LIMIT ? OFFSET ?"
    );
    $rows->execute($dataBindings);
    $feedbackList = $rows->fetchAll();

    // Average rating
    $avgStmt = $pdo->query('SELECT AVG(rating) FROM feedback');
    $avgRating = round((float)$avgStmt->fetchColumn(), 1);

} catch (Exception $e) {
    $feedbackList = [];
    $total = 0; $totalPages = 1;
    $statusCounts = ['unread'=>0,'read'=>0,'resolved'=>0];
    $totalAll = 0; $avgRating = 0;
    $subjectCounts = [];
}

$ok  = $_SESSION['flash_success'] ?? ''; unset($_SESSION['flash_success']);
$err = $_SESSION['flash_error']   ?? ''; unset($_SESSION['flash_error']);

// Subject labels
$subjectLabels = [
    'general' => 'General Feedback',
    'question'=> 'Question',
    'suggest' => 'Suggestion',
    'error'   => 'Error Report',
    'bug'     => 'Bug Report',
    'feature' => 'Feature Request',
];

// Subject badge colours (inline)
$subjectColors = [
    'general' => '#3A6B4A',
    'question'=> '#3A5A8A',
    'suggest' => '#6B5A3A',
    'error'   => '#6B3A3A',
    'bug'     => '#8B3A2A',
    'feature' => '#3A5A6B',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feedback — Admin — Lost Knowledge</title>
  <link rel="stylesheet" href="/lost-knowledge/assets/css/style.css">
  <style>
    .fb-card {
      background: var(--bg-panel);
      border: 1px solid var(--border-dark);
      border-radius: var(--radius-xl);
      padding: var(--space-3) var(--space-4);
      margin-bottom: 12px;
      transition: border-color var(--transition);
      position: relative;
    }
    .fb-card.unread  { border-left: 3px solid var(--amber); }
    .fb-card.read    { border-left: 3px solid var(--border-gold); }
    .fb-card.resolved{ border-left: 3px solid var(--green); opacity: .75; }
    .fb-card:hover   { border-color: var(--border-gold); }

    .fb-header { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:12px; }
    .fb-meta   { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:8px; }
    .fb-badge  { font-size:10px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; padding:3px 9px; border-radius:20px; }
    .fb-status-unread   { background:rgba(184,119,42,.2);  color:var(--amber-light); }
    .fb-status-read     { background:var(--bg-panel-h);    color:var(--text-on-dark-faint); }
    .fb-status-resolved { background:rgba(58,107,74,.2);   color:#6BC48A; }
    .fb-name   { font-family:var(--font-display); font-size:1rem; font-weight:600; color:var(--text-on-dark); }
    .fb-email  { font-size:12px; color:var(--amber-light); }
    .fb-date   { font-size:11px; color:var(--text-on-dark-faint); }
    .fb-message{ color:var(--text-on-dark-muted); font-size:.9rem; line-height:1.75; white-space:pre-wrap; word-break:break-word; padding:12px 16px; background:var(--bg-void); border-radius:var(--radius-lg); margin:10px 0; border:1px solid var(--border-dark); }
    .fb-details{ display:flex; flex-wrap:wrap; gap:10px; font-size:11px; color:var(--text-on-dark-faint); margin-bottom:12px; }
    .fb-det    { background:var(--bg-void); border:1px solid var(--border-dark); border-radius:var(--radius-md); padding:3px 8px; }
    .fb-actions{ display:flex; gap:6px; flex-wrap:wrap; }

    .rating-stars { display:inline-flex; gap:2px; }
    .star-filled  { color:var(--amber-light); }
    .star-empty   { color:var(--border-dark); }

    .stat-bar { background:var(--bg-panel); border:1px solid var(--border-dark); border-radius:var(--radius-lg); padding:var(--space-3); }
    .stat-bar-title { font-size:11px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--text-on-dark-faint); margin-bottom:var(--space-2); }

    .filter-chip { display:inline-flex; align-items:center; font-size:12px; font-weight:500; padding:5px 12px; border-radius:20px; border:1px solid var(--border-dark); color:var(--text-on-dark-muted); background:var(--bg-panel); cursor:pointer; transition:all var(--transition); text-decoration:none; }
    .filter-chip:hover, .filter-chip.active { border-color:var(--amber); color:var(--amber-light); background:rgba(184,119,42,.1); text-decoration:none; }
    .filter-chip .count { background:var(--border-dark); border-radius:20px; padding:1px 7px; margin-left:5px; font-size:10px; }
    .filter-chip.active .count { background:var(--amber-dark); color:var(--bg-cream); }
  </style>
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
      <a href="/lost-knowledge/dashboard.php"             class="nav-link">Dashboard</a>
      <a href="/lost-knowledge/admin/admin_dashboard.php" class="nav-link">Admin Panel</a>
      <a href="/lost-knowledge/admin/feedback.php"        class="nav-link active">Feedback</a>
      <a href="/lost-knowledge/logout.php"                class="nav-link">Sign Out</a>
    </nav>
  </div>
</header>

<div class="page-header">
  <div class="container">
    <div class="section-label">Admin</div>
    <h1>Feedback Inbox</h1>
    <p><?= $totalAll ?> total submissions · <?= $statusCounts['unread'] ?> unread · Avg rating <?= $avgRating ?>/10</p>
  </div>
</div>

<main>
  <div class="container" style="padding:var(--space-5) var(--space-3) var(--space-8)">

    <?php if ($ok):  ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($ok)  ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error"   data-autohide><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- Top stats row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:var(--space-2);margin-bottom:var(--space-4)">
      <div class="stat-bar" style="text-align:center">
        <div class="stat-bar-title">Total</div>
        <div style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--amber-light)"><?= $totalAll ?></div>
      </div>
      <div class="stat-bar" style="text-align:center">
        <div class="stat-bar-title">Unread</div>
        <div style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--amber)"><?= $statusCounts['unread'] ?></div>
      </div>
      <div class="stat-bar" style="text-align:center">
        <div class="stat-bar-title">Read</div>
        <div style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--text-on-dark-muted)"><?= $statusCounts['read'] ?></div>
      </div>
      <div class="stat-bar" style="text-align:center">
        <div class="stat-bar-title">Resolved</div>
        <div style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:#6BC48A"><?= $statusCounts['resolved'] ?></div>
      </div>
      <div class="stat-bar" style="text-align:center">
        <div class="stat-bar-title">Avg Rating</div>
        <div style="font-family:var(--font-display);font-size:2rem;font-weight:700;color:var(--amber-light)"><?= $avgRating ?><span style="font-size:1rem;color:var(--text-on-dark-faint)">/10</span></div>
      </div>
    </div>

    <!-- Filter bar -->
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:var(--space-3)">
      <span style="font-size:12px;color:var(--text-on-dark-faint);font-weight:600;letter-spacing:.08em;text-transform:uppercase;margin-right:4px">Status:</span>
      <?php
        $statuses = ['all'=>'All', 'unread'=>'Unread', 'read'=>'Read', 'resolved'=>'Resolved'];
        foreach ($statuses as $s => $lbl):
          $cnt = $s === 'all' ? $totalAll : ($statusCounts[$s] ?? 0);
          $active = $filterStatus === $s ? 'active' : '';
          $qs = http_build_query(['status'=>$s, 'subject'=>$filterSubject]);
      ?>
      <a href="?<?= $qs ?>" class="filter-chip <?= $active ?>">
        <?= $lbl ?><span class="count"><?= $cnt ?></span>
      </a>
      <?php endforeach; ?>

      <span style="width:1px;height:20px;background:var(--border-dark);margin:0 6px"></span>
      <span style="font-size:12px;color:var(--text-on-dark-faint);font-weight:600;letter-spacing:.08em;text-transform:uppercase;margin-right:4px">Subject:</span>

      <a href="?<?= http_build_query(['status'=>$filterStatus,'subject'=>'all']) ?>"
         class="filter-chip <?= $filterSubject==='all'?'active':'' ?>">All</a>

      <?php foreach ($subjectCounts as $sc):
        $active2 = $filterSubject === $sc['subject'] ? 'active' : '';
        $qs2 = http_build_query(['status'=>$filterStatus,'subject'=>$sc['subject']]);
      ?>
      <a href="?<?= $qs2 ?>" class="filter-chip <?= $active2 ?>">
        <?= htmlspecialchars($subjectLabels[$sc['subject']] ?? ucfirst($sc['subject'])) ?>
        <span class="count"><?= $sc['n'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Feedback list -->
    <?php if (empty($feedbackList)): ?>
      <div class="empty-state">
        <div class="empty-icon">📬</div>
        <h3>No feedback yet</h3>
        <p>Submissions will appear here once users send feedback from the About page.</p>
      </div>

    <?php else: ?>

    <div style="margin-bottom:var(--space-2);font-size:12px;color:var(--text-on-dark-faint)">
      Showing <?= count($feedbackList) ?> of <?= $total ?> entries
    </div>

    <?php foreach ($feedbackList as $fb):
      $subjectLabel = $subjectLabels[$fb['subject']] ?? ucfirst($fb['subject']);
      $subjectColor = $subjectColors[$fb['subject']] ?? '#3A5A6B';
      $ratingInt    = (int)$fb['rating'];
    ?>
    <div class="fb-card <?= htmlspecialchars($fb['status']) ?>">

      <!-- Header: name + email + date + badges -->
      <div class="fb-header">
        <div>
          <div class="fb-meta">
            <span class="fb-status-<?= $fb['status'] ?> fb-badge"><?= ucfirst($fb['status']) ?></span>
            <span style="font-size:10px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;padding:3px 9px;border-radius:20px;background:rgba(<?= $subjectColor === '#3A6B4A' ? '58,107,74' : ($subjectColor === '#8B3A2A' ? '139,58,42' : '58,90,107') ?>,.2);color:var(--amber-light)">
              <?= htmlspecialchars($subjectLabel) ?>
            </span>
            <span class="fb-date">
              #<?= $fb['id'] ?> · <?= date('d M Y, H:i', strtotime($fb['created_at'])) ?>
            </span>
          </div>
          <div class="fb-name"><?= htmlspecialchars($fb['name']) ?></div>
          <div class="fb-email">
            <a href="mailto:<?= htmlspecialchars($fb['email']) ?>" style="color:var(--amber-light)">
              <?= htmlspecialchars($fb['email']) ?>
            </a>
          </div>
        </div>

        <!-- Rating stars -->
        <div style="text-align:right;flex-shrink:0">
          <div class="rating-stars">
            <?php for ($i = 1; $i <= 10; $i++): ?>
            <span class="<?= $i <= $ratingInt ? 'star-filled' : 'star-empty' ?>" style="font-size:13px">★</span>
            <?php endfor; ?>
          </div>
          <div style="font-size:11px;color:var(--text-on-dark-faint);margin-top:3px"><?= $ratingInt ?>/10</div>
        </div>
      </div>

      <!-- Extra details row -->
      <div class="fb-details">
        <?php if ($fb['phone']): ?>
        <span class="fb-det">📞 <?= htmlspecialchars($fb['phone']) ?></span>
        <?php endif; ?>
        <?php if ($fb['age']): ?>
        <span class="fb-det">Age <?= (int)$fb['age'] ?></span>
        <?php endif; ?>
        <?php if ($fb['country']): ?>
        <span class="fb-det">🌍 <?= htmlspecialchars($fb['country']) ?></span>
        <?php endif; ?>
        <?php if ($fb['source']): ?>
        <span class="fb-det">Found via: <?= htmlspecialchars($fb['source']) ?></span>
        <?php endif; ?>
        <?php if ($fb['interests']): ?>
        <span class="fb-det">Interests: <?= htmlspecialchars($fb['interests']) ?></span>
        <?php endif; ?>
        <?php if ($fb['bug_date']): ?>
        <span class="fb-det" style="color:#C46060">Bug date: <?= htmlspecialchars($fb['bug_date']) ?></span>
        <?php endif; ?>
        <?php if ($fb['ip_address']): ?>
        <span class="fb-det" style="color:var(--border-gold)">IP: <?= htmlspecialchars($fb['ip_address']) ?></span>
        <?php endif; ?>
      </div>

      <!-- Message body -->
      <div class="fb-message"><?= htmlspecialchars($fb['message']) ?></div>

      <!-- Action buttons -->
      <div class="fb-actions">
        <?php if ($fb['status'] !== 'read'): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="read">
          <input type="hidden" name="id"     value="<?= $fb['id'] ?>">
          <button type="submit" class="btn btn-ghost btn-sm">✓ Mark Read</button>
        </form>
        <?php endif; ?>

        <?php if ($fb['status'] !== 'unread'): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="unread">
          <input type="hidden" name="id"     value="<?= $fb['id'] ?>">
          <button type="submit" class="btn btn-ghost btn-sm">↩ Mark Unread</button>
        </form>
        <?php endif; ?>

        <?php if ($fb['status'] !== 'resolved'): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="resolved">
          <input type="hidden" name="id"     value="<?= $fb['id'] ?>">
          <button type="submit" class="btn btn-outline btn-sm" style="color:#6BC48A;border-color:#2A5A3A">✦ Resolve</button>
        </form>
        <?php endif; ?>

        <a href="mailto:<?= htmlspecialchars($fb['email']) ?>?subject=Re: Your feedback on Lost Knowledge"
           class="btn btn-amber btn-sm">Reply →</a>

        <form method="POST" style="display:inline;margin-left:auto">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id"     value="<?= $fb['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm"
                  onclick="return confirm('Permanently delete this feedback?')">✕ Delete</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination" style="margin-top:var(--space-4)">
      <?php for ($i = 1; $i <= $totalPages; $i++):
        $qs = http_build_query(['status'=>$filterStatus,'subject'=>$filterSubject,'page'=>$i]);
      ?>
      <a href="?<?= $qs ?>" class="page-btn <?= $i===$page?'current':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>

  </div>
</main>

<footer class="site-footer" style="padding:1.5rem 0">
  <div class="container">
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> Lost Knowledge — Admin</span>
      <span class="footer-bottom-diamond">✦</span>
      <span><a href="/lost-knowledge/admin/admin_dashboard.php" style="color:var(--amber-light)">← Back to Admin Panel</a></span>
    </div>
  </div>
</footer>

<script src="/lost-knowledge/assets/js/script.js"></script>
</body>
</html>
