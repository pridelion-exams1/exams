<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/auth.php';
$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_admin($user)) { flash('error', 'Only an Administrator can manage teachers.'); header('Location: teachers.php'); exit; }
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $editId = $_POST['edit_id'] ?? '';
        if (!$name) { flash('error', 'Teacher name is required.'); header('Location: teachers.php'); exit; }
        if ($editId) {
            $pdo->prepare("UPDATE teachers SET name=?,phone=? WHERE id=?")->execute([$name, $phone, $editId]);
            flash('success', 'Teacher updated.');
        } else {
            $pdo->prepare("INSERT INTO teachers (name,phone) VALUES (?,?)")->execute([$name, $phone]);
            flash('success', 'Teacher added.');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        $st = $pdo->prepare("SELECT name FROM teachers WHERE id=?"); $st->execute([$id]);
        $t = $st->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE classes SET class_teacher_id=NULL WHERE class_teacher_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM class_subject_teachers WHERE teacher_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM teachers WHERE id=?")->execute([$id]);
        flash('error', 'Teacher "' . ($t['name'] ?? '') . '" deleted, and unassigned from any classes/subjects.');
    }
    header('Location: teachers.php'); exit;
}

$teachers = $pdo->query("SELECT * FROM teachers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$editTeacher = null;
if (!empty($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM teachers WHERE id=?"); $st->execute([$_GET['edit']]);
    $editTeacher = $st->fetch(PDO::FETCH_ASSOC);
}

// Build teaching load per teacher
$classTeacherOf = []; // teacher_id => [class labels]
foreach ($pdo->query("SELECT id,class_teacher_id FROM classes WHERE class_teacher_id IS NOT NULL") as $c) {
    $classTeacherOf[$c['class_teacher_id']][] = class_label($pdo, $c['id']);
}
$subjectsOf = []; // teacher_id => [ "Subject — Class" ]
$rows = $pdo->query("
  SELECT cst.teacher_id, s.name subject_name, c.id class_id
  FROM class_subject_teachers cst
  JOIN subjects s ON s.code=cst.subject_code
  JOIN classes c ON c.id=cst.class_id
  ORDER BY s.name
");
foreach ($rows as $r) {
    $subjectsOf[$r['teacher_id']][] = h($r['subject_name']) . ' <span style="color:#888">— ' . h(class_label($pdo, $r['class_id'])) . '</span>';
}

$pageTitle = 'Teachers'; $activeNav = 'teachers';
require __DIR__ . '/includes/layout_top.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
  <h1 style="font-size:20px;margin:0">Teachers</h1>
</div>

<div class="frow" style="grid-template-columns:1fr 2fr;align-items:start">
  <?php if (is_admin($user)): ?>
  <div class="card">
    <div class="card-header"><?= $editTeacher ? 'Edit Teacher' : 'Add Teacher' ?></div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="edit_id" value="<?= h($editTeacher['id'] ?? '') ?>">
        <div class="fg"><label class="fl">Full Name</label><input class="fi" name="name" value="<?= h($editTeacher['name'] ?? '') ?>" required></div>
        <div class="fg"><label class="fl">Phone</label><input class="fi" name="phone" value="<?= h($editTeacher['phone'] ?? '') ?>"></div>
        <button class="btn btn-primary" type="submit"><?= $editTeacher ? 'Save Changes' : 'Add Teacher' ?></button>
        <?php if ($editTeacher): ?><a class="btn btn-ghost" href="teachers.php">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>
  <?php endif; ?>
  <div class="card">
    <div class="card-header">All Teachers</div>
    <div class="card-body">
      <?php if (!count($teachers)): ?>
        <div class="empty-state"><div class="icon">&#128104;&#8205;&#127979;</div><p>No teachers added yet.</p></div>
      <?php else: ?>
        <table>
          <thead><tr><th>Name</th><th>Phone</th><th>Class Teacher Of</th><th>Subjects Taught</th><?php if (is_admin($user)): ?><th></th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($teachers as $t): ?>
            <tr>
              <td style="font-weight:600;vertical-align:top"><?= h($t['name']) ?></td>
              <td style="vertical-align:top"><?= h($t['phone'] ?: '-') ?></td>
              <td style="vertical-align:top"><?= !empty($classTeacherOf[$t['id']]) ? h(implode(', ', $classTeacherOf[$t['id']])) : '-' ?></td>
              <td style="font-size:12px;vertical-align:top">
                <?php if (!empty($subjectsOf[$t['id']])): ?>
                  <?php foreach ($subjectsOf[$t['id']] as $line): ?><div><?= $line ?></div><?php endforeach; ?>
                <?php else: ?><span style="color:#888">Not assigned to any subject yet</span><?php endif; ?>
              </td>
              <?php if (is_admin($user)): ?>
              <td style="vertical-align:top">
                <a class="act-btn" href="?edit=<?= $t['id'] ?>" title="Edit">&#9998;</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete teacher <?= h($t['name']) ?>?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $t['id'] ?>">
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
