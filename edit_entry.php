<?php
// ============================================================
// edit_entry.php — Edit a knowledge entry (with revision history)
// ============================================================
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/notify.php';
require_login('/edit_entry.php');

$userId = current_user_id();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /lost-knowledge/dashboard.php'); exit; }

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM knowledge_entries WHERE id = ?');
    $stmt->execute([$id]);
    $entry = $stmt->fetch();
    if (!$entry) { header('Location: /lost-knowledge/dashboard.php'); exit; }

    // Only owner or admin
    if ((int)$entry['user_id'] !== $userId && !is_admin()) {
        $_SESSION['flash_error'] = 'You can only edit your own entries.';
        header('Location: /lost-knowledge/dashboard.php'); exit;
    }

    $cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
    $tagList = get_entry_tags($id);
    $tagStr = implode(',', array_column($tagList, 'name'));
} catch (Exception $e) {
    header('Location: /lost-knowledge/dashboard.php'); exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title']   ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $body    = trim($_POST['body']    ?? '');
    $region  = trim($_POST['region']  ?? '');
    $era     = trim($_POST['era']     ?? '');
    $catId   = (int)($_POST['cat']    ?? 0);
    $tags    = trim($_POST['tags']    ?? '');

    if (!$title || strlen($title) < 5 || strlen($title) > 200) $errors[] = 'Title must be 5–200 characters.';
    if (!$summary || strlen($summary) < 10 || strlen($summary) > 400) $errors[] = 'Summary must be 10–400 characters.';
    if (!$body || strlen(strip_tags($body)) < 30) $errors[] = 'Body must be at least 30 characters.';

    // Image upload
    $imagePath = $entry['image_path'];
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === 0) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($_FILES['image']['type'], $allowed)) {
            $errors[] = 'Image must be JPEG, PNG, WebP, or GIF.';
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image must be under 5MB.';
        } else {
            $dir = __DIR__ . '/uploads/entries';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $fname = 'entry_' . $id . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], "$dir/$fname")) {
                $imagePath = "/lost-knowledge/uploads/entries/$fname";
            }
        }
    }

    if (empty($errors)) {
        try {
            // Save revision of current state BEFORE updating
            save_revision($id, $userId, $entry);

            $pdo->prepare(
                'UPDATE knowledge_entries
                 SET title=?, summary=?, body=?, region=?, era=?, category_id=?, image_path=?, status="pending", updated_at=NOW()
                 WHERE id=?'
            )->execute([$title, $summary, $body, $region ?: null, $era ?: null, $catId ?: null, $imagePath, $id]);

            // Process tags
            process_tags($id, $tags);

            $_SESSION['flash_success'] = 'Entry updated — it will be re-reviewed before appearing publicly.';
            header('Location: /lost-knowledge/dashboard.php'); exit;
        } catch (Exception $e) {
            $errors[] = 'Database error. Please try again.';
        }
    }

    // Re-fill entry array with posted data for the form
    $entry['title'] = $title;
    $entry['summary'] = $summary;
    $entry['body'] = $body;
    $entry['region'] = $region;
    $entry['era'] = $era;
    $entry['category_id'] = $catId;
    $tagStr = $tags;
}

// Count revisions
try {
    $revCount = $pdo->prepare('SELECT COUNT(*) FROM entry_revisions WHERE entry_id = ?');
    $revCount->execute([$id]);
    $revisionCount = (int)$revCount->fetchColumn();
} catch (Exception $e) { $revisionCount = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Entry — Lost Knowledge</title>
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
    <button class="nav-toggle" aria-label="Menu"><span></span><span></span><span></span></button>
    <nav class="nav-links">
      <a href="/lost-knowledge/index.html" class="nav-link">Archive</a>
      <a href="/lost-knowledge/dashboard.php" class="nav-link">Dashboard</a>
      <a href="/lost-knowledge/logout.php" class="nav-link">Sign Out</a>
    </nav>
  </div>
</header>

<div class="page-header">
  <div class="container">
    <div class="section-label">Edit knowledge</div>
    <h1>Update entry</h1>
    <div style="display:flex;gap:12px;margin-top:12px">
      <a href="/lost-knowledge/entry.php?id=<?= $id ?>" class="btn btn-ghost btn-sm">View entry</a>
      <?php if ($revisionCount > 0): ?>
        <a href="/lost-knowledge/revisions.php?id=<?= $id ?>" class="btn btn-ghost btn-sm">📜 <?= $revisionCount ?> revision<?= $revisionCount !== 1 ? 's' : '' ?></a>
      <?php endif; ?>
    </div>
  </div>
</div>

<main>
  <div class="form-wrap" style="max-width:720px">
    <div class="form-card anim-1" style="padding:2.25rem">

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data">

        <div class="form-group">
          <label for="title">Title <span class="req">*</span></label>
          <input type="text" id="title" name="title" maxlength="200" value="<?= htmlspecialchars($entry['title']) ?>">
        </div>

        <div class="form-group">
          <label for="summary">Brief summary <span class="req">*</span></label>
          <input type="text" id="summary" name="summary" maxlength="400" value="<?= htmlspecialchars($entry['summary']) ?>">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="cat">Category</label>
            <select id="cat" name="cat">
              <option value="0">— Select —</option>
              <?php foreach ($cats as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($entry['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="region">Region / Origin</label>
            <input type="text" id="region" name="region" maxlength="100" value="<?= htmlspecialchars($entry['region'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="era">Era / Time Period</label>
          <input type="text" id="era" name="era" maxlength="100" value="<?= htmlspecialchars($entry['era'] ?? '') ?>">
        </div>

        <!-- Tags -->
        <div class="form-group">
          <label>Tags <small style="font-weight:400;color:var(--text-on-dark-faint)">(press Enter to add, max 8)</small></label>
          <div class="tag-input-wrap" id="tagInputWrap">
            <input type="text" class="tag-input" placeholder="e.g. weaving, polynesia…">
          </div>
          <input type="hidden" name="tags" id="tagsHidden" value="<?= htmlspecialchars($tagStr) ?>">
        </div>

        <!-- Image upload -->
        <div class="form-group">
          <label>Entry Image</label>
          <div class="image-upload-zone" id="imageUploadZone">
            <div class="image-upload-icon">📷</div>
            <div class="image-upload-text"><?= $entry['image_path'] ? 'Click to change image' : 'Click or drag to upload an image' ?></div>
            <?php if ($entry['image_path']): ?>
              <img src="<?= htmlspecialchars($entry['image_path']) ?>" class="image-preview" id="imagePreview" style="display:block">
            <?php else: ?>
              <img src="" class="image-preview" id="imagePreview" style="display:none">
            <?php endif; ?>
          </div>
          <input type="file" name="image" id="entryImage" accept="image/*" style="display:none">
        </div>

        <div class="form-group">
          <label for="body">Full account <span class="req">*</span></label>
          <textarea id="body" name="body" rows="10"><?= htmlspecialchars($entry['body']) ?></textarea>
        </div>

        <hr class="form-divider">

        <div style="display:flex;gap:.75rem;justify-content:flex-end;flex-wrap:wrap">
          <a href="/lost-knowledge/dashboard.php" class="btn btn-ghost">Cancel</a>
          <button type="submit" class="btn btn-amber">Save changes →</button>
        </div>

      </form>
    </div>
  </div>
</main>

<footer class="site-footer" style="padding:1.5rem 0">
  <div class="container"><div class="footer-bottom"><span>&copy; <?= date('Y') ?> Lost Knowledge</span></div></div>
</footer>

<script src="/lost-knowledge/assets/js/script.js"></script>
<script src="/lost-knowledge/assets/js/features.js"></script>
<script>
  // Pre-fill tags from hidden input
  document.addEventListener('DOMContentLoaded', () => {
    const hidden = document.getElementById('tagsHidden');
    const wrap = document.getElementById('tagInputWrap');
    const input = wrap?.querySelector('.tag-input');
    if (!hidden || !wrap || !input || !hidden.value) return;
    hidden.value.split(',').filter(Boolean).forEach(t => {
      input.value = t.trim();
      input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
    });
  });
</script>
</body>
</html>
