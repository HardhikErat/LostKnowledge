<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/notify.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /lost-knowledge/index.html'); exit; }

try {
  $pdo = get_pdo();
  $stmt = $pdo->prepare(
    'SELECT ke.*, u.username, u.karma, u.avatar_path as user_avatar, c.name cat, c.slug cat_slug,
            (SELECT COUNT(*) FROM votes v WHERE v.entry_id=ke.id AND v.vote_type="up")   up,
            (SELECT COUNT(*) FROM votes v WHERE v.entry_id=ke.id AND v.vote_type="down") dn
     FROM knowledge_entries ke
     LEFT JOIN users u      ON ke.user_id=u.id
     LEFT JOIN categories c ON ke.category_id=c.id
     WHERE ke.id=? AND (ke.status="approved" OR ?=1) LIMIT 1'
  );
  $stmt->execute([$id, is_admin() ? 1 : 0]);
  $e = $stmt->fetch();
  if (!$e) { header('HTTP/1.1 404 Not Found'); header('Location: /lost-knowledge/index.html'); exit; }

  // Increment view counter
  $pdo->prepare('UPDATE knowledge_entries SET views = views + 1 WHERE id = ?')->execute([$id]);
  $e['views'] = ($e['views'] ?? 0) + 1;

  // Get tags
  $entryTags = get_entry_tags($id);

  // Author karma level
  [$authorLevel, $authorLevelClass] = karma_level($e['karma'] ?? 0);

  $userVote = null; $isBookmarked = false;
  if (is_logged_in()) {
    $uid = current_user_id();
    $vs  = $pdo->prepare('SELECT vote_type FROM votes WHERE entry_id=? AND user_id=? LIMIT 1');
    $vs->execute([$id, $uid]);
    $userVote = ($vs->fetch())['vote_type'] ?? null;
    $bs = $pdo->prepare('SELECT id FROM bookmarks WHERE user_id=? AND entry_id=? LIMIT 1');
    $bs->execute([$uid, $id]);
    $isBookmarked = (bool)$bs->fetch();
  }

  $comments = $pdo->prepare(
    'SELECT cm.id, cm.body, cm.created_at, u.username, u.id AS uid
     FROM comments cm JOIN users u ON cm.user_id=u.id
     WHERE cm.entry_id=? ORDER BY cm.created_at ASC'
  );
  $comments->execute([$id]);
  $commentList = $comments->fetchAll();

  $related = [];
  if ($e['category_id']) {
    $rel = $pdo->prepare(
      'SELECT ke.id, ke.title, ke.summary, ke.image_path FROM knowledge_entries ke
       WHERE ke.category_id=? AND ke.id!=? AND ke.status="approved"
       ORDER BY RAND() LIMIT 3'
    );
    $rel->execute([$e['category_id'], $id]);
    $related = $rel->fetchAll();
  }

  // Count revisions
  $revStmt = $pdo->prepare('SELECT COUNT(*) FROM entry_revisions WHERE entry_id = ?');
  $revStmt->execute([$id]);
  $revisionCount = (int)$revStmt->fetchColumn();

} catch (Exception $ex) {
  error_log('[LK] entry.php error: ' . $ex->getMessage());
  header('Location: /lost-knowledge/index.html'); exit;
}

$flashOk  = $_SESSION['flash_success'] ?? ''; unset($_SESSION['flash_success']);
$flashErr = $_SESSION['flash_error']   ?? ''; unset($_SESSION['flash_error']);

// Social card image
$ogImage = $e['image_path'] ?? '/lost-knowledge/assets/og-default.jpg';
$ogUrl   = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/lost-knowledge/entry.php?id=' . $id;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($e['summary']) ?>">

  <!-- Open Graph / Social Cards -->
  <meta property="og:type"        content="article">
  <meta property="og:title"       content="<?= htmlspecialchars($e['title']) ?> — Lost Knowledge">
  <meta property="og:description" content="<?= htmlspecialchars($e['summary']) ?>">
  <meta property="og:url"         content="<?= htmlspecialchars($ogUrl) ?>">
  <?php if ($ogImage): ?>
  <meta property="og:image"       content="<?= htmlspecialchars($ogImage) ?>">
  <?php endif; ?>

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image">
  <meta name="twitter:title"       content="<?= htmlspecialchars($e['title']) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($e['summary']) ?>">
  <?php if ($ogImage): ?>
  <meta name="twitter:image"       content="<?= htmlspecialchars($ogImage) ?>">
  <?php endif; ?>

  <title><?= htmlspecialchars($e['title']) ?> — Lost Knowledge</title>
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
      <div class="search-wrap">
        <input type="text" id="navSearch" placeholder="🔍 Quick search…" autocomplete="off"
               style="background:var(--bg-panel);border:1px solid var(--border-dark);border-radius:20px;padding:.35rem .85rem;font-size:.82rem;color:var(--text-on-dark);outline:none;width:160px;transition:width .3s"
               onfocus="this.style.width='210px'" onblur="this.style.width='160px'">
        <div class="ac-dropdown" id="acDrop"></div>
      </div>
      <a href="/lost-knowledge/index.html" class="nav-link">← Archive</a>
      <?php if (is_logged_in()): ?>
      <a href="/lost-knowledge/dashboard.php" class="nav-link">Dashboard</a>
      <a href="/lost-knowledge/submit.php"    class="nav-link nav-cta">+ Submit</a>
      <a href="/lost-knowledge/logout.php"    class="nav-link">Sign Out</a>
      <?php else: ?>
      <a href="/lost-knowledge/login.html"    class="nav-link">Sign In</a>
      <a href="/lost-knowledge/register.html" class="nav-link nav-cta">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<div class="page-header">
  <div class="container">
    <div class="section-label">
      <a href="/lost-knowledge/index.html?cat=<?= htmlspecialchars($e['cat_slug']??'') ?>" style="color:var(--amber)">
        <?= htmlspecialchars($e['cat']??'Archive') ?>
      </a>
    </div>
    <h1 style="max-width:720px"><?= htmlspecialchars($e['title']) ?></h1>
    <p style="margin-top:.6rem;color:var(--text-on-dark-muted)"><?= htmlspecialchars($e['summary']) ?></p>
  </div>
</div>

<article>
  <div class="entry-detail-wrap">

    <?php if ($flashOk):  ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="alert alert-error"   data-autohide><?= htmlspecialchars($flashErr) ?></div><?php endif; ?>

    <!-- Meta strip with view counter -->
    <div class="entry-meta-strip">
      <?php if ($e['region']): ?><span class="meta-item">🌍 <?= htmlspecialchars($e['region']) ?></span><?php endif; ?>
      <?php if ($e['era']):    ?><span class="meta-item">⏳ <?= htmlspecialchars($e['era'])    ?></span><?php endif; ?>
      <span class="meta-item">✍️
        <a href="/lost-knowledge/profile.php?username=<?= urlencode($e['username']??'') ?>"
           style="color:var(--amber-light);font-weight:500">
          <?= htmlspecialchars($e['username']??'Anonymous') ?>
        </a>
        <span class="karma-badge <?= $authorLevelClass ?>" style="margin-left:4px;font-size:9px">
          <?= $authorLevel ?>
        </span>
      </span>
      <span class="meta-item">📅 <?= date('d F Y', strtotime($e['created_at'])) ?></span>
      <span class="meta-item">💬 <?= count($commentList) ?> comment<?= count($commentList)!==1?'s':'' ?></span>
      <span class="meta-item view-count">👁️ <?= number_format($e['views']) ?> view<?= $e['views']!==1?'s':'' ?></span>
      <span class="ec-status <?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span>
    </div>

    <!-- Tags -->
    <?php if (!empty($entryTags)): ?>
    <div class="tag-list" style="margin-bottom:1rem">
      <?php foreach ($entryTags as $tag): ?>
      <a href="/lost-knowledge/index.html?tag=<?= htmlspecialchars($tag['slug']) ?>" class="tag-pill">#<?= htmlspecialchars($tag['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Entry hero image -->
    <?php if ($e['image_path']): ?>
    <img src="<?= htmlspecialchars($e['image_path']) ?>" alt="<?= htmlspecialchars($e['title']) ?>" class="entry-hero-image">
    <?php endif; ?>

    <!-- Entry body -->
    <div class="entry-body">
      <?php
        $bodyHtml = $e['body'];
        // If it contains HTML tags (from Quill), render as-is; otherwise paragraph-split
        if (strip_tags($bodyHtml) === $bodyHtml) {
          foreach (array_filter(explode("\n\n", $bodyHtml)) as $para) {
            echo '<p>' . nl2br(htmlspecialchars($para)) . '</p>';
          }
        } else {
          // Sanitize: allow safe tags from Quill
          echo $bodyHtml;
        }
      ?>
    </div>

    <!-- Action row: Vote + Bookmark + PDF + Share -->
    <div style="display:flex;align-items:flex-start;gap:1rem;margin-top:2rem;flex-wrap:wrap">
      <div class="vote-panel" style="flex:1;min-width:240px">
        <p>Does this knowledge deserve preservation?</p>
        <div style="display:flex;gap:.65rem">
          <button class="vote-btn <?= $userVote==='up'?'up-active':'' ?>"
                  onclick="castVote(<?=$id?>,'up',this)" style="padding:.5rem 1.25rem;font-size:.87rem">
            ▲ Preserve &nbsp;<span><?= $e['up'] ?></span>
          </button>
          <button class="vote-btn <?= $userVote==='down'?'down-active':'' ?>"
                  onclick="castVote(<?=$id?>,'down',this)" style="padding:.5rem 1.25rem;font-size:.87rem">
            ▼ Dispute &nbsp;<span><?= $e['dn'] ?></span>
          </button>
        </div>
        <?php if (!is_logged_in()): ?>
        <p style="margin-top:.5rem;font-size:.78rem;color:var(--text-on-dark-faint);text-transform:none;letter-spacing:0">
          <a href="/lost-knowledge/login.html" style="color:var(--amber-light)">Sign in</a> to vote.
        </p>
        <?php endif; ?>
      </div>
      <div style="display:flex;flex-direction:column;gap:.5rem;padding-top:.5rem">
        <?php if (is_logged_in()): ?>
        <button class="bookmark-btn <?= $isBookmarked?'saved':'' ?>" id="bmBtn" onclick="toggleBookmark(<?=$id?>)">
          <?= $isBookmarked ? '🔖 Bookmarked' : '☆ Save Entry' ?>
        </button>
        <?php endif; ?>
        <button class="bookmark-btn" onclick="shareEntry()">🔗 Share</button>
        <button class="btn-pdf" id="pdfExportBtn">📄 Export PDF</button>
      </div>
    </div>

    <!-- Edit / Delete / Revisions (owner or admin) -->
    <?php if (is_logged_in() && (current_user_id()===(int)$e['user_id'] || is_admin())): ?>
    <div style="display:flex;gap:.65rem;margin-top:1.25rem;flex-wrap:wrap;align-items:center">
      <a href="/lost-knowledge/edit_entry.php?id=<?=$id?>" class="btn btn-outline btn-sm">✏️ Edit entry</a>
      <?php if ($revisionCount > 0): ?>
      <a href="/lost-knowledge/revisions.php?id=<?=$id?>" class="btn btn-ghost btn-sm">📜 <?= $revisionCount ?> revision<?= $revisionCount !== 1 ? 's' : '' ?></a>
      <?php endif; ?>
      <?php if (is_admin()): ?>
      <a href="/lost-knowledge/delete_entry.php?id=<?=$id?>" class="btn btn-danger btn-sm"
         data-confirm="Delete this entry permanently?">Delete</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- COMMENTS -->
    <section class="comments-section" id="comments" style="margin-top:2.5rem; padding-top:2rem; border-top:2px solid var(--border-dark)">
      <h2 style="font-size:1.1rem;font-weight:600;color:var(--text-on-dark);margin-bottom:1.5rem;display:flex;align-items:center;gap:.6rem">
        Discussion
        <span style="background:var(--bg-panel);color:var(--text-on-dark-faint);font-size:.72rem;font-weight:700;padding:.2rem .6rem;border-radius:20px"><?= count($commentList) ?></span>
      </h2>

      <?php if (empty($commentList)): ?>
      <div style="text-align:center;padding:2rem;color:var(--text-on-dark-faint)">
        <div style="font-size:1.5rem;margin-bottom:.5rem">💬</div>
        <p style="font-size:.88rem">No comments yet. Be the first to share your thoughts.</p>
      </div>
      <?php else: ?>
        <?php foreach ($commentList as $cm): ?>
        <div id="comment-<?= $cm['id'] ?>" style="background:var(--bg-panel);border:1px solid var(--border-dark);border-radius:var(--radius-lg);padding:1rem 1.25rem;margin-bottom:.85rem">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;flex-wrap:wrap;gap:.5rem">
            <div style="display:flex;align-items:center;gap:.65rem">
              <div style="width:32px;height:32px;border-radius:50%;background:var(--amber-dark);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:var(--text-on-dark);flex-shrink:0">
                <?= strtoupper(substr($cm['username'],0,1)) ?>
              </div>
              <div>
                <a href="/lost-knowledge/profile.php?username=<?= urlencode($cm['username']) ?>" style="font-weight:600;font-size:.88rem;color:var(--amber-light);text-decoration:none">
                  <?= htmlspecialchars($cm['username']) ?>
                </a>
                <div style="font-size:.75rem;color:var(--text-on-dark-faint)"><?= date('d M Y, H:i', strtotime($cm['created_at'])) ?></div>
              </div>
            </div>
            <?php if (is_logged_in() && (current_user_id()===(int)$cm['uid'] || is_admin())): ?>
            <form method="POST" action="/lost-knowledge/comment_process.php" style="display:inline">
              <input type="hidden" name="action"     value="delete">
              <input type="hidden" name="entry_id"   value="<?=$id?>">
              <input type="hidden" name="comment_id" value="<?=$cm['id']?>">
              <button type="submit" style="background:none;border:none;color:#C46060;font-size:.75rem;cursor:pointer;padding:0;opacity:.6;font-family:inherit" onclick="return confirm('Delete this comment?')">✕ Delete</button>
            </form>
            <?php endif; ?>
          </div>
          <div style="font-size:.9rem;color:var(--text-on-dark-muted);line-height:1.7;white-space:pre-wrap"><?= htmlspecialchars($cm['body']) ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (is_logged_in()): ?>
      <div style="background:var(--bg-panel);border:1px solid var(--border-dark);border-radius:var(--radius-lg);padding:1.25rem;margin-top:1.25rem">
        <div style="display:flex;align-items:center;gap:.65rem;margin-bottom:.75rem">
          <div style="width:32px;height:32px;border-radius:50%;background:var(--amber-dark);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;color:var(--text-on-dark)">
            <?= strtoupper(substr(current_username(),0,1)) ?>
          </div>
          <span style="font-size:.85rem;font-weight:600;color:var(--text-on-dark)"><?= current_username() ?></span>
        </div>
        <form id="commentForm" method="POST" action="/lost-knowledge/comment_process.php">
          <input type="hidden" name="action"   value="add">
          <input type="hidden" name="entry_id" value="<?=$id?>">
          <textarea name="body" id="cmBody" maxlength="1000"
                    placeholder="Share your thoughts, corrections, or additional knowledge…" required
                    style="width:100%;background:var(--bg-void);border:1.5px solid var(--border-dark);border-radius:var(--radius-md);color:var(--text-on-dark);font-family:var(--font-body);font-size:.9rem;padding:.65rem .9rem;resize:vertical;min-height:90px;outline:none;transition:border-color .2s"></textarea>
          <div style="text-align:right;font-size:.72rem;color:var(--text-on-dark-faint);margin-top:.2rem">
            <span id="cmCount">0</span>/1000
          </div>
          <div style="display:flex;justify-content:flex-end;margin-top:.75rem">
            <button type="submit" class="btn btn-amber btn-sm">Post Comment →</button>
          </div>
        </form>
      </div>
      <?php else: ?>
      <div style="text-align:center;padding:1.25rem;background:var(--bg-panel);border:1px solid var(--border-dark);border-radius:var(--radius-lg);margin-top:1rem">
        <p style="font-size:.9rem;color:var(--text-on-dark-muted);margin:0">
          <a href="/lost-knowledge/login.html" style="color:var(--amber-light)">Sign in</a> to join the discussion.
        </p>
      </div>
      <?php endif; ?>
    </section>

    <!-- RELATED -->
    <?php if (!empty($related)): ?>
    <section style="margin-top:3rem;padding-top:2rem;border-top:1px solid var(--border-dark)">
      <h2 style="font-size:1.1rem;color:var(--text-on-dark);margin-bottom:.25rem">
        More from <?= htmlspecialchars($e['cat']??'this category') ?>
      </h2>
      <p style="font-size:.82rem;color:var(--text-on-dark-faint);margin-bottom:.85rem">Other entries worth preserving.</p>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:1rem;margin-top:1rem">
        <?php foreach ($related as $r): ?>
        <div class="review-card" onclick="window.location='/lost-knowledge/entry.php?id=<?=$r['id']?>'" style="cursor:pointer">
          <?php if ($r['image_path']): ?>
          <img src="<?= htmlspecialchars($r['image_path']) ?>" alt="" style="width:100%;height:100px;object-fit:cover;border-radius:var(--radius-md);margin-bottom:8px">
          <?php endif; ?>
          <div style="font-weight:600;font-size:.9rem;color:var(--text-on-dark);margin-bottom:.3rem;line-height:1.3"><?= htmlspecialchars($r['title']) ?></div>
          <p style="font-size:.78rem;color:var(--text-on-dark-faint);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= htmlspecialchars($r['summary']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <div style="margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid var(--border-dark)">
      <a href="/lost-knowledge/index.html" class="btn btn-ghost" style="padding-left:0">← Back to archive</a>
    </div>

  </div>
</article>

<footer class="site-footer" style="padding:1.5rem 0;margin-top:0">
  <div class="container">
    <div class="footer-bottom"><span>&copy; <?= date('Y') ?> Lost Knowledge</span></div>
  </div>
</footer>

<script src="/lost-knowledge/assets/js/script.js"></script>
<script src="/lost-knowledge/assets/js/features.js"></script>
<script>
// Character counter
const cmBody = document.getElementById('cmBody');
const cmCount = document.getElementById('cmCount');
if (cmBody && cmCount) {
  cmBody.addEventListener('input', () => {
    cmCount.textContent = cmBody.value.length;
    cmCount.style.color = cmBody.value.length > 900 ? '#C46060' : '';
  });
}

// Bookmark
function toggleBookmark(id) {
  fetch('/lost-knowledge/bookmark_process.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
    body:'entry_id='+id
  }).then(r=>r.json()).then(d=>{
    const btn = document.getElementById('bmBtn');
    if (btn && d.success) {
      btn.textContent = d.bookmarked ? '🔖 Bookmarked' : '☆ Save Entry';
      btn.classList.toggle('saved', d.bookmarked);
      showToast(d.bookmarked ? 'Entry bookmarked' : 'Bookmark removed', 'success');
    }
  }).catch(()=>{});
}

// Share with toast
function shareEntry() {
  if (navigator.share) {
    navigator.share({ title: document.title, url: location.href }).catch(()=>{});
  } else {
    navigator.clipboard.writeText(location.href)
      .then(()=> showToast('Link copied to clipboard!', 'success'))
      .catch(()=> prompt('Copy link:', location.href));
  }
}

// Autocomplete search
(function() {
  const inp  = document.getElementById('navSearch');
  const drop = document.getElementById('acDrop');
  if (!inp || !drop) return;
  let t;
  inp.addEventListener('input', () => {
    clearTimeout(t);
    const q = inp.value.trim();
    if (q.length < 2) { drop.style.display='none'; return; }
    t = setTimeout(() => {
      fetch('/lost-knowledge/api/knowledge.php?search='+encodeURIComponent(q)+'&per_page=6&status=approved')
        .then(r=>r.json()).then(d=>{
          if (!d.success || !d.data?.length) {
            drop.innerHTML='<div style="padding:.75rem 1rem;font-size:.85rem;color:var(--text-on-dark-faint);font-style:italic">No results found.</div>';
          } else {
            drop.innerHTML=d.data.map(e=>`
              <div class="ac-item" onclick="location='/lost-knowledge/entry.php?id=${e.id}'" style="padding:.65rem 1rem;cursor:pointer;border-bottom:1px solid var(--border-dark);transition:background .15s">
                <div style="font-size:.88rem;font-weight:500;color:var(--text-on-dark)">${e.title.replace(/</g,'&lt;')}</div>
                <div style="font-size:.72rem;color:var(--amber-light);margin-top:.12rem">${(e.category_name||'General').replace(/</g,'&lt;')}</div>
              </div>`).join('');
          }
          drop.style.display='block';
        }).catch(()=>{ drop.style.display='none'; });
    }, 280);
  });
  document.addEventListener('click', ev => { if (!inp.contains(ev.target)&&!drop.contains(ev.target)) drop.style.display='none'; });
  inp.addEventListener('keydown', ev => {
    if (ev.key==='Escape') drop.style.display='none';
    if (ev.key==='Enter') { ev.preventDefault(); location='/lost-knowledge/index.html?search='+encodeURIComponent(inp.value.trim()); }
  });
})();
</script>
</body>
</html>
