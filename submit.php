<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/notify.php';
require_login('/submit.php');

$userId = current_user_id();
$errors = [];
$old    = [];

try {
  $pdo  = get_pdo();
  $cats = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
} catch (Exception $e) { $cats = []; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $title   = trim($_POST['title']   ?? '');
  $summary = trim($_POST['summary'] ?? '');
  $body    = trim($_POST['body']    ?? '');
  $region  = trim($_POST['region']  ?? '');
  $era     = trim($_POST['era']     ?? '');
  $catId   = (int)($_POST['cat']    ?? 0);
  $tags    = trim($_POST['tags']    ?? '');
  $old = compact('title','summary','body','region','era','catId','tags');

  if (!$title   || strlen($title)   < 5  || strlen($title)   > 200) $errors[] = 'Title must be 5–200 characters.';
  if (!$summary || strlen($summary) < 10 || strlen($summary) > 400) $errors[] = 'Summary must be 10–400 characters.';
  if (!$body    || strlen(strip_tags($body)) < 30)                   $errors[] = 'Body must be at least 30 characters.';
  if (strlen($region) > 100) $errors[] = 'Region too long.';
  if (strlen($era)    > 100) $errors[] = 'Era too long.';

  // Image upload
  $imagePath = null;
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
      $fname = 'entry_' . time() . '_' . uniqid() . '.' . $ext;
      if (move_uploaded_file($_FILES['image']['tmp_name'], "$dir/$fname")) {
        $imagePath = "/lost-knowledge/uploads/entries/$fname";
      }
    }
  }

  if (empty($errors)) {
    try {
      $stmt = $pdo->prepare(
        'INSERT INTO knowledge_entries (user_id,category_id,title,summary,body,region,era,image_path,status)
         VALUES (?,?,?,?,?,?,?,?,"pending")'
      );
      $stmt->execute([$userId, $catId ?: null, $title, $summary, $body, $region ?: null, $era ?: null, $imagePath]);
      $newId = (int)$pdo->lastInsertId();

      // Process tags
      if ($tags) process_tags($newId, $tags);

      // Award karma for submission
      award_karma($userId, 10);

      $_SESSION['flash_success'] = 'Your entry has been submitted and is awaiting review. (+10 karma)';
      header('Location: /lost-knowledge/dashboard.php'); exit;
    } catch (Exception $e) {
      $errors[] = 'Database error. Please try again.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Entry — Lost Knowledge</title>
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
      <a href="/lost-knowledge/dashboard.php" class="nav-link">Dashboard</a>
      <a href="/lost-knowledge/submit.php"    class="nav-link active nav-cta">+ Submit</a>
      <a href="/lost-knowledge/logout.php"    class="nav-link">Sign Out</a>
    </nav>
  </div>
</header>

<div class="page-header">
  <div class="container">
    <div class="section-label">Preserve knowledge</div>
    <h1>Record a lost tradition</h1>
    <p>Your entry will be reviewed by a moderator before it appears in the public archive.</p>
  </div>
</div>

<main>
  <div class="form-wrap" style="max-width:720px">
    <div class="form-card anim-1" style="padding:2.25rem">

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error"><?= htmlspecialchars(implode(' ', $errors)) ?></div>
      <?php endif; ?>

      <div class="form-card-header" style="margin-bottom:1.5rem">
        <h2>New entry</h2>
        <p>Be as detailed as possible — precision preserves truth.</p>
      </div>

      <form id="knowledgeForm" method="POST" action="/lost-knowledge/submit.php" enctype="multipart/form-data" novalidate>

        <div class="form-group">
          <label for="title">Title <span class="req">*</span></label>
          <input type="text" id="title" name="title" maxlength="200"
                 placeholder="e.g. The Lost Art of Sheepgut Bowstring Making"
                 value="<?= htmlspecialchars($old['title'] ?? '') ?>">
          <span class="field-error"></span>
        </div>

        <div class="form-group">
          <label for="summary">Brief summary <span class="req">*</span></label>
          <input type="text" id="summary" name="summary" maxlength="400"
                 placeholder="One sentence describing what this knowledge is…"
                 value="<?= htmlspecialchars($old['summary'] ?? '') ?>">
          <span class="field-error"></span>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="cat">Category</label>
            <select id="cat" name="cat">
              <option value="0">— Select —</option>
              <?php foreach ($cats as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($old['catId'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="region">Region / Origin</label>
            <input type="text" id="region" name="region" maxlength="100"
                   placeholder="e.g. Northern Anatolia"
                   value="<?= htmlspecialchars($old['region'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label for="era">Era / Time Period</label>
          <input type="text" id="era" name="era" maxlength="100"
                 placeholder="e.g. 12th–16th Century"
                 value="<?= htmlspecialchars($old['era'] ?? '') ?>">
        </div>

        <!-- Tags -->
        <div class="form-group">
          <label>Tags <small style="font-weight:400;color:var(--text-on-dark-faint)">(press Enter to add, max 8)</small></label>
          <div class="tag-input-wrap" id="tagInputWrap">
            <input type="text" class="tag-input" placeholder="e.g. weaving, polynesia, pre-colonial…">
          </div>
          <input type="hidden" name="tags" id="tagsHidden" value="<?= htmlspecialchars($old['tags'] ?? '') ?>">
        </div>

        <!-- Image upload -->
        <div class="form-group">
          <label>Cover Image <small style="font-weight:400;color:var(--text-on-dark-faint)">(optional, max 5MB)</small></label>
          <div class="image-upload-zone" id="imageUploadZone">
            <div class="image-upload-icon">📷</div>
            <div class="image-upload-text">Click or drag an image here to upload</div>
            <img src="" class="image-preview" id="imagePreview" style="display:none">
          </div>
          <input type="file" name="image" id="entryImage" accept="image/*" style="display:none">
        </div>

        <div class="form-group">
          <label for="body">Full account <span class="req">*</span></label>
          <textarea id="body" name="body" rows="10"
                    placeholder="Describe the tradition — how it was practiced, why it disappeared, any known sources…"><?= htmlspecialchars($old['body'] ?? '') ?></textarea>
          <span class="field-error"></span>
        </div>

        <hr class="form-divider">

        <div style="display:flex;gap:.75rem;justify-content:flex-end;flex-wrap:wrap">
          <a href="/lost-knowledge/dashboard.php" class="btn btn-ghost">Cancel</a>
          <button type="submit" class="btn btn-amber">✦ Submit for review →</button>
        </div>

      </form>
    </div>
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
