<?php
// Expects: $pdo, $user, $pageTitle, $activeNav
$schoolName = get_setting($pdo, 'school_name', 'Pridelion Education Network');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'Dashboard') ?> — <?= h($schoolName) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="layout">
  <div class="sidebar no-print">
    <div class="brand"><?= h($schoolName) ?></div>
    <a href="index.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
    <a href="learners.php" class="<?= $activeNav === 'learners' ? 'active' : '' ?>">Learners</a>
    <a href="classes.php" class="<?= $activeNav === 'classes' ? 'active' : '' ?>">Classes &amp; Streams</a>
    <a href="subjects.php" class="<?= $activeNav === 'subjects' ? 'active' : '' ?>">Subjects</a>
    <a href="teachers.php" class="<?= $activeNav === 'teachers' ? 'active' : '' ?>">Teachers</a>
    <a href="exams.php" class="<?= $activeNav === 'exams' ? 'active' : '' ?>">Exams &amp; Marks</a>
    <a href="reports.php" class="<?= $activeNav === 'reports' ? 'active' : '' ?>">Report Forms</a>
    <?php if (is_admin($user)): ?>
    <a href="settings.php" class="<?= $activeNav === 'settings' ? 'active' : '' ?>">Settings</a>
    <?php endif; ?>
    <div class="role-box">
      Signed in as <strong><?= h($user['name']) ?></strong><br>
      <?= h($user['role']) ?><br>
      <a href="logout.php" style="color:#a12a2a;font-weight:600">Sign out</a>
    </div>
  </div>
  <div class="main">
    <?php show_flash(); ?>
