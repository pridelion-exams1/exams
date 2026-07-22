<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/auth.php';
$user = require_login($pdo);

$learnerCount = $pdo->query("SELECT COUNT(*) c FROM learners WHERE status='Active'")->fetch(PDO::FETCH_ASSOC)['c'];
$classCount   = $pdo->query("SELECT COUNT(*) c FROM classes")->fetch(PDO::FETCH_ASSOC)['c'];
$teacherCount = $pdo->query("SELECT COUNT(*) c FROM teachers")->fetch(PDO::FETCH_ASSOC)['c'];
$examCount    = $pdo->query("SELECT COUNT(*) c FROM exams")->fetch(PDO::FETCH_ASSOC)['c'];

$byClass = $pdo->query("
  SELECT c.id, c.grade, c.stream, COUNT(l.id) n
  FROM classes c LEFT JOIN learners l ON l.class_id=c.id AND l.status='Active'
  GROUP BY c.id ORDER BY c.grade, c.stream
")->fetchAll(PDO::FETCH_ASSOC);

$recentActivity = $pdo->query("SELECT * FROM activity_log ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Dashboard'; $activeNav = 'dashboard';
require __DIR__ . '/includes/layout_top.php';
?>
<h1 style="font-size:20px;margin-bottom:18px">Dashboard</h1>

<div class="frow" style="grid-template-columns:repeat(4,1fr);margin-bottom:18px">
  <div class="card"><div class="card-body"><div style="font-size:12px;color:#888">Active Learners</div><div style="font-size:26px;font-weight:800"><?= $learnerCount ?></div></div></div>
  <div class="card"><div class="card-body"><div style="font-size:12px;color:#888">Classes</div><div style="font-size:26px;font-weight:800"><?= $classCount ?></div></div></div>
  <div class="card"><div class="card-body"><div style="font-size:12px;color:#888">Teachers</div><div style="font-size:26px;font-weight:800"><?= $teacherCount ?></div></div></div>
  <div class="card"><div class="card-body"><div style="font-size:12px;color:#888">Exams Recorded</div><div style="font-size:26px;font-weight:800"><?= $examCount ?></div></div></div>
</div>

<div class="frow">
  <div class="card">
    <div class="card-header">Learners by Class</div>
    <div class="card-body">
      <?php if (!count($byClass)): ?>
        <div class="empty-state"><div class="icon">&#127979;</div><p>No classes set up yet. <a href="classes.php" style="text-decoration:underline">Add one</a>.</p></div>
      <?php else: ?>
        <table>
          <thead><tr><th>Class</th><th>Active Learners</th></tr></thead>
          <tbody>
          <?php foreach ($byClass as $c): ?>
            <tr><td><?= h(class_label($pdo, $c['id'])) ?></td><td><?= $c['n'] ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
  <div class="card">
    <div class="card-header">Recent Activity</div>
    <div class="card-body">
      <?php if (!count($recentActivity)): ?>
        <div class="empty-state"><div class="icon">&#128337;</div><p>No activity yet.</p></div>
      <?php else: ?>
        <?php foreach ($recentActivity as $a): ?>
          <div style="padding:8px 0;border-bottom:1px solid var(--border);font-size:13px">
            <?= h($a['message']) ?>
            <div class="staff-id"><?= h(date('d M Y, H:i', strtotime($a['created_at']))) ?></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
