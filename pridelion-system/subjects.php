<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/auth.php';
$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_admin($user)) { flash('error', 'Only an Administrator can manage subjects.'); header('Location: subjects.php'); exit; }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $editCode = trim($_POST['edit_code'] ?? '');
        if (!$code || !$name) { flash('error', 'Code and name are required.'); header('Location: subjects.php'); exit; }
        if ($editCode) {
            if ($code !== $editCode) {
                $chk = $pdo->prepare("SELECT 1 FROM subjects WHERE code=?"); $chk->execute([$code]);
                if ($chk->fetch()) { flash('error', 'Subject code already exists.'); header('Location: subjects.php'); exit; }
                $pdo->prepare("UPDATE subjects SET code=?,name=? WHERE code=?")->execute([$code, $name, $editCode]);
                $pdo->prepare("UPDATE class_subject_teachers SET subject_code=? WHERE subject_code=?")->execute([$code, $editCode]);
            } else {
                $pdo->prepare("UPDATE subjects SET name=? WHERE code=?")->execute([$name, $code]);
            }
            flash('success', 'Subject updated.');
        } else {
            $chk = $pdo->prepare("SELECT 1 FROM subjects WHERE code=?"); $chk->execute([$code]);
            if ($chk->fetch()) { flash('error', 'Subject code already exists.'); header('Location: subjects.php'); exit; }
            $pdo->prepare("INSERT INTO subjects (code,name) VALUES (?,?)")->execute([$code, $name]);
            flash('success', 'Subject added.');
        }
    } elseif ($action === 'delete') {
        $code = $_POST['code'] ?? '';
        $pdo->prepare("DELETE FROM subjects WHERE code=?")->execute([$code]);
        flash('error', 'Subject deleted.');
    }
    header('Location: subjects.php'); exit;
}

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$classCounts = [];
foreach ($pdo->query("SELECT subject_code, COUNT(*) n FROM class_subject_teachers GROUP BY subject_code") as $r) {
    $classCounts[$r['subject_code']] = $r['n'];
}
$editSubject = null;
if (!empty($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM subjects WHERE code=?"); $st->execute([$_GET['edit']]);
    $editSubject = $st->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = 'Subjects'; $activeNav = 'subjects';
require __DIR__ . '/includes/layout_top.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
  <h1 style="font-size:20px;margin:0">Subjects</h1>
</div>

<div class="frow" style="grid-template-columns:1fr 2fr;align-items:start">
  <?php if (is_admin($user)): ?>
  <div class="card">
    <div class="card-header"><?= $editSubject ? 'Edit Subject' : 'Add Subject' ?></div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="edit_code" value="<?= h($editSubject['code'] ?? '') ?>">
        <div class="fg"><label class="fl">Subject Code</label><input class="fi" name="code" style="text-transform:uppercase" value="<?= h($editSubject['code'] ?? '') ?>" required></div>
        <div class="fg"><label class="fl">Subject Name</label><input class="fi" name="name" value="<?= h($editSubject['name'] ?? '') ?>" required></div>
        <div style="font-size:12px;color:#888;margin-bottom:14px">The teacher for this subject is assigned per class — set it on each class's page.</div>
        <button class="btn btn-primary" type="submit"><?= $editSubject ? 'Save Changes' : 'Add Subject' ?></button>
        <?php if ($editSubject): ?><a class="btn btn-ghost" href="subjects.php">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>
  <?php endif; ?>
  <div class="card">
    <div class="card-header">All Subjects</div>
    <div class="card-body">
      <?php if (!count($subjects)): ?>
        <div class="empty-state"><div class="icon">&#128218;</div><p>No subjects added yet.</p></div>
      <?php else: ?>
        <table>
          <thead><tr><th>Code</th><th>Name</th><th>Assigned</th><?php if (is_admin($user)): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($subjects as $s): ?>
            <tr>
              <td class="mono"><?= h($s['code']) ?></td>
              <td><?= h($s['name']) ?></td>
              <td style="font-size:12px;color:#888"><?= isset($classCounts[$s['code']]) ? ('Assigned in ' . $classCounts[$s['code']] . ' class(es)') : 'Set per class →' ?></td>
              <?php if (is_admin($user)): ?>
              <td>
                <a class="act-btn" href="?edit=<?= urlencode($s['code']) ?>" title="Edit">&#9998;</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete subject <?= h($s['code']) ?>?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="code" value="<?= h($s['code']) ?>">
                  <button class="act-btn" type="submit" title="Delete">&#128465;</button>
                </form>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
