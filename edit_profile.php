<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
require_login('/edit_profile.php');

$userId   = current_user_id();
$username = current_username();
$errors = [];
$ok = '';

try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare('SELECT username, email, phone, bio, avatar_path, karma FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // Load email preferences
    $epStmt = $pdo->prepare('SELECT digest_freq, notify_email FROM email_preferences WHERE user_id = ?');
    $epStmt->execute([$userId]);
    $prefs = $epStmt->fetch() ?: ['digest_freq' => 'weekly', 'notify_email' => 1];
} catch (Exception $e) {
    $user = ['username' => $username, 'email' => '', 'phone' => '', 'bio' => '', 'avatar_path' => '', 'karma' => 0];
    $prefs = ['digest_freq' => 'weekly', 'notify_email' => 1];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';

    if ($action === 'profile') {
        $newEmail = trim($_POST['email'] ?? '');
        $newPhone = trim($_POST['phone'] ?? '');
        $newBio   = trim($_POST['bio'] ?? '');

        if (!$newEmail || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
        if (strlen($newBio) > 500) $errors[] = 'Bio must be under 500 characters.';

        // Avatar upload
        $avatarPath = $user['avatar_path'];
        if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === 0) {
            $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($_FILES['avatar']['type'], $allowed)) {
                $errors[] = 'Avatar must be JPEG, PNG, WebP, or GIF.';
            } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                $errors[] = 'Avatar must be under 2MB.';
            } else {
                $dir = __DIR__ . '/uploads/avatars';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $fname = 'av_' . $userId . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], "$dir/$fname")) {
                    $avatarPath = "/lost-knowledge/uploads/avatars/$fname";
                }
            }
        }

        if (empty($errors)) {
            try {
                $pdo->prepare('UPDATE users SET email = ?, phone = ?, bio = ?, avatar_path = ? WHERE id = ?')
                    ->execute([$newEmail, $newPhone ?: null, $newBio ?: null, $avatarPath, $userId]);
                $ok = 'Profile updated successfully!';
                $user['email'] = $newEmail;
                $user['phone'] = $newPhone;
                $user['bio'] = $newBio;
                $user['avatar_path'] = $avatarPath;
            } catch (Exception $e) {
                $errors[] = 'Update failed. Email or phone may already be in use.';
            }
        }
    }

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!$current) $errors[] = 'Current password required.';
        if (strlen($newPass) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($newPass !== $confirm) $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $pwStmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
            $pwStmt->execute([$userId]);
            $hash = $pwStmt->fetchColumn();

            if (!$hash || !password_verify($current, $hash)) {
                $errors[] = 'Current password is incorrect.';
            } else {
                $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                    ->execute([password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]), $userId]);
                $ok = 'Password changed successfully!';
            }
        }
    }

    if ($action === 'email_prefs') {
        $freq = $_POST['digest_freq'] ?? 'weekly';
        $notifyEmail = isset($_POST['notify_email']) ? 1 : 0;
        if (!in_array($freq, ['none', 'daily', 'weekly', 'monthly'])) $freq = 'weekly';

        try {
            $pdo->prepare(
                'INSERT INTO email_preferences (user_id, digest_freq, notify_email)
                 VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE digest_freq = VALUES(digest_freq), notify_email = VALUES(notify_email)'
            )->execute([$userId, $freq, $notifyEmail]);
            $prefs['digest_freq'] = $freq;
            $prefs['notify_email'] = $notifyEmail;
            $ok = 'Email preferences saved!';
        } catch (Exception $e) {
            $errors[] = 'Failed to save preferences.';
        }
    }
}

require_once __DIR__ . '/config/notify.php';
[$levelName, $levelClass] = karma_level($user['karma'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profile — Lost Knowledge</title>
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
      <a href="/lost-knowledge/edit_profile.php" class="nav-link active">Profile</a>
      <a href="/lost-knowledge/logout.php"    class="nav-link">Sign Out</a>
    </nav>
  </div>
</header>

<div class="page-header">
  <div class="container">
    <div class="section-label">Account settings</div>
    <h1>Edit Profile</h1>
  </div>
</div>

<main>
  <div class="form-wrap" style="max-width:680px">

    <?php if ($ok): ?><div class="alert alert-success" data-autohide><?= htmlspecialchars($ok) ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert alert-error"><?= htmlspecialchars(implode(' ', $errors)) ?></div><?php endif; ?>

    <!-- PROFILE INFO -->
    <div class="form-card anim-1" style="padding:2rem">
      <div class="form-card-header" style="margin-bottom:1.5rem">
        <h2>Profile Information</h2>
        <p>Update your personal details</p>
      </div>

      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="profile">

        <!-- Avatar -->
        <div style="text-align:center;margin-bottom:1.5rem">
          <label class="profile-avatar-upload">
            <?php if ($user['avatar_path']): ?>
              <img src="<?= htmlspecialchars($user['avatar_path']) ?>" alt="Avatar" id="avatarPreview">
            <?php else: ?>
              <div class="avatar-placeholder" id="avatarPreview"><?= strtoupper(substr($username, 0, 1)) ?></div>
            <?php endif; ?>
            <div class="avatar-overlay">📷</div>
            <input type="file" name="avatar" id="avatarFile" accept="image/*">
          </label>
          <div style="margin-top:8px">
            <span class="karma-badge <?= $levelClass ?>" title="<?= $user['karma'] ?> karma points">✦ <?= $levelName ?></span>
            <span class="karma-points" style="margin-left:8px">
              <span class="karma-icon">⚡</span> <?= number_format($user['karma'] ?? 0) ?> karma
            </span>
          </div>
        </div>

        <div class="form-group">
          <label>Username</label>
          <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled style="opacity:.6">
          <small style="color:var(--text-on-dark-faint)">Username cannot be changed</small>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="email">Email <span class="req">*</span></label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
          </div>
          <div class="form-group">
            <label for="phone">Phone</label>
            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+91 98765 43210">
          </div>
        </div>

        <div class="form-group">
          <label for="bio">Bio</label>
          <textarea id="bio" name="bio" rows="3" maxlength="500" placeholder="Tell the community about yourself…"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
          <small style="color:var(--text-on-dark-faint)"><span id="bioCount"><?= strlen($user['bio'] ?? '') ?></span>/500</small>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:1rem">
          <button type="submit" class="btn btn-amber">Save Profile</button>
        </div>
      </form>
    </div>

    <!-- CHANGE PASSWORD -->
    <div class="form-card anim-2" style="padding:2rem;margin-top:1.5rem">
      <div class="form-card-header" style="margin-bottom:1.5rem">
        <h2>Change Password</h2>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="password">
        <div class="form-group">
          <label for="current_password">Current Password</label>
          <input type="password" id="current_password" name="current_password" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" required minlength="8">
          </div>
          <div class="form-group">
            <label for="confirm_password">Confirm</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:1rem">
          <button type="submit" class="btn btn-outline">Change Password</button>
        </div>
      </form>
    </div>

    <!-- EMAIL DIGEST PREFERENCES -->
    <div class="form-card anim-3" style="padding:2rem;margin-top:1.5rem">
      <div class="form-card-header" style="margin-bottom:1.5rem">
        <h2>Email Preferences</h2>
        <p>Control notification and digest emails</p>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="email_prefs">

        <div class="pref-card">
          <div class="pref-toggle">
            <div>
              <strong style="font-size:.9rem">Email Notifications</strong>
              <div style="font-size:.8rem;color:var(--text-on-dark-faint)">Receive email when your entries are approved/rejected</div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" name="notify_email" <?= $prefs['notify_email'] ? 'checked' : '' ?>>
              <span class="toggle-slider"></span>
            </label>
          </div>
        </div>

        <div class="form-group" style="margin-top:1rem">
          <label for="digest_freq">Digest Frequency</label>
          <select id="digest_freq" name="digest_freq">
            <option value="none"    <?= $prefs['digest_freq']==='none'    ? 'selected' : '' ?>>None — no digest emails</option>
            <option value="daily"   <?= $prefs['digest_freq']==='daily'   ? 'selected' : '' ?>>Daily digest</option>
            <option value="weekly"  <?= $prefs['digest_freq']==='weekly'  ? 'selected' : '' ?>>Weekly digest</option>
            <option value="monthly" <?= $prefs['digest_freq']==='monthly' ? 'selected' : '' ?>>Monthly digest</option>
          </select>
          <small style="color:var(--text-on-dark-faint)">Get a summary of new entries in categories you follow</small>
        </div>

        <div style="display:flex;justify-content:flex-end;margin-top:1rem">
          <button type="submit" class="btn btn-outline">Save Preferences</button>
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
<script>
  const bioEl = document.getElementById('bio');
  const bioCount = document.getElementById('bioCount');
  if (bioEl && bioCount) {
    bioEl.addEventListener('input', () => { bioCount.textContent = bioEl.value.length; });
  }
</script>
</body>
</html>
