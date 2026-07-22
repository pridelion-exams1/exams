<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/auth.php';

if (current_user($pdo)) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    $st = $pdo->prepare("SELECT * FROM users WHERE username=?");
    $st->execute([$u]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify($p, $row['password_hash'])) {
        $_SESSION['uid'] = $row['id'];
        header('Location: index.php');
        exit;
    }
    $error = 'Incorrect username or password.';
}
$schoolName = get_setting($pdo, 'school_name', 'Pridelion Education Network');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — <?= h($schoolName) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-wrap">
  <div class="login-card">
    <h2 style="margin-top:0"><?= h($schoolName) ?></h2>
    <p style="color:#888;margin-top:-8px;font-size:13px">School Management System</p>
    <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="fg"><label class="fl">Username</label><input class="fi" name="username" required autofocus></div>
      <div class="fg"><label class="fl">Password</label><input class="fi" name="password" type="password" required></div>
      <button class="btn btn-primary" style="width:100%" type="submit">Sign In</button>
    </form>
    <div class="login-hint">Demo: admin / pride2026 (full rights)<br>hoi / hoi2026 (limited — data entry only)</div>
  </div>
</div>
</body>
</html>
