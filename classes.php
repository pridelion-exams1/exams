<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/auth.php';
$user = require_login($pdo);

$grades = cbc_grades();
$streamingMode = get_setting($pdo, 'streaming_mode', 'multi');
$teachers = $pdo->query("SELECT * FROM teachers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_admin($user)) { flash('error', 'Only an Administrator can manage classes.'); header('Location: classes.php'); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $grade = trim($_POST['grade'] ?? '');
        $stream = trim($_POST['stream'] ?? '') ?: 'Main';
        $classTeacherId = $_POST['class_teacher_id'] ?: null;
        $fee = ($_POST['fee'] ?? '') !== '' ? (float)$_POST['fee'] : null;
        $editId = $_POST['edit_id'] ?? '';

        if ($classTeacherId) {
            $q = "SELECT id FROM classes WHERE class_teacher_id=?" . ($editId ? " AND id!=?" : "");
            $params = $editId ? [$classTeacherId, $editId] : [$classTeacherId];
            $st = $pdo->prepare($q); $st->execute($params);
            $conflict = $st->fetch(PDO::FETCH_ASSOC);
            if ($conflict) {
                flash('error', teacher_name($pdo, $classTeacherId) . ' is already the class teacher of ' . class_label($pdo, $conflict['id']) . '. Remove them from that class first.');
                header('Location: classes.php'); exit;
            }
        }

        if ($editId) {
            $pdo->prepare("UPDATE classes SET grade=?,stream=?,class_teacher_id=?,fee=? WHERE id=?")
                ->execute([$grade, $stream, $classTeacherId, $fee, $editId]);
            $classId = $editId;
            flash('success', 'Class updated.');
        } else {
            $chk = $pdo->prepare("SELECT 1 FROM classes WHERE grade=? AND stream=?"); $chk->execute([$grade, $stream]);
            if ($chk->fetch()) { flash('error', 'That grade/stream already exists.'); header('Location: classes.php'); exit; }
            if ($streamingMode === 'single') {
                $chk2 = $pdo->prepare("SELECT 1 FROM classes WHERE grade=?"); $chk2->execute([$grade]);
                if ($chk2->fetch()) { flash('error', 'Single Stream mode is on — ' . $grade . ' already has a class.'); header('Location: classes.php'); exit; }
            }
            $pdo->prepare("INSERT INTO classes (grade,stream,class_teacher_id,fee) VALUES (?,?,?,?)")
                ->execute([$grade, $stream, $classTeacherId, $fee]);
            $classId = $pdo->lastInsertId();
            log_activity($pdo, 'New class created: ' . $grade . ' ' . $stream);
            flash('success', 'Class added.');
        }

        // Subject-teacher assignments for this class
        $pdo->prepare("DELETE FROM class_subject_teachers WHERE class_id=?")->execute([$classId]);
        foreach ($subjects as $s) {
            $tid = $_POST['subj_' . $s['code']] ?? '';
            if ($tid) {
                $pdo->prepare("INSERT INTO class_subject_teachers (class_id,subject_code,teacher_id) VALUES (?,?,?)")
                    ->execute([$classId, $s['code'], $tid]);
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        $pdo->prepare("DELETE FROM classes WHERE id=?")->execute([$id]);
        flash('error', 'Class deleted.');
    }
    header('Location: classes.php'); exit;
}

$classes = $pdo->query("SELECT * FROM classes ORDER BY grade, stream")->fetchAll(PDO::FETCH_ASSOC);
$learnerCounts = [];
foreach ($pdo->query("SELECT class_id, COUNT(*) n FROM learners GROUP BY class_id") as $r) $learnerCounts[$r['class_id']] = $r['n'];

$editClass = null; $editAssignments = [];
if (!empty($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM classes WHERE id=?"); $st->execute([$_GET['edit']]);
    $editClass = $st->fetch(PDO::FETCH_ASSOC);
    if ($editClass) {
        $st2 = $pdo->prepare("SELECT subject_code,teacher_id FROM class_subject_teachers WHERE class_id=?");
        $st2->execute([$editClass['id']]);
        foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $r) $editAssignments[$r['subject_code']] = $r['teacher_id'];
    }
}

// Teachers already class-teacher elsewhere (to grey out in the dropdown)
$takenTeachers = [];
foreach ($pdo->query("SELECT id,class_teacher_id FROM classes WHERE class_teacher_id IS NOT NULL") as $c) {
    if (!$editClass || $c['id'] != $editClass['id']) $takenTeachers[$c['class_teacher_id']] = class_label($pdo, $c['id']);
}

$pageTitle = 'Classes'; $activeNav = 'classes';
require __DIR__ . '/includes/layout_top.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
  <h1 style="font-size:20px;margin:0">Classes &amp; Streams</h1>
  <?php if (is_admin($user) && !$editClass): ?><a href="#form" class="btn btn-primary btn-sm">+ Add Class</a><?php endif; ?>
</div>

<?php if (is_admin($user)): ?>
<div class="card" id="form">
  <div class="card-header"><?= $editClass ? 'Edit Class' : 'Add Class / Stream' ?></div>
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="edit_id" value="<?= h($editClass['id'] ?? '') ?>">
      <div class="frow">
        <div class="fg">
          <label class="fl">Grade</label>
          <select class="fs" name="grade" required>
            <?php foreach ($grades as $g): ?>
              <option value="<?= h($g) ?>" <?= (($editClass['grade'] ?? '') === $g) ? 'selected' : '' ?>><?= h($g) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($streamingMode !== 'single'): ?>
        <div class="fg"><label class="fl">Stream Name</label><input class="fi" name="stream" value="<?= h($editClass['stream'] ?? '') ?>" placeholder="e.g. A, Blue, North"></div>
        <?php else: ?>
        <input type="hidden" name="stream" value="Main">
        <?php endif; ?>
      </div>
      <div class="fg">
        <label class="fl">Class Teacher</label>
        <select class="fs" name="class_teacher_id">
          <option value="">— None assigned —</option>
          <?php foreach ($teachers as $t): $taken = isset($takenTeachers[$t['id']]); ?>
            <option value="<?= $t['id'] ?>" <?= $taken ? 'disabled' : '' ?> <?= (($editClass['class_teacher_id'] ?? '') == $t['id']) ? 'selected' : '' ?>>
              <?= h($t['name']) ?><?= $taken ? (' (already class teacher of ' . h($takenTeachers[$t['id']]) . ')') : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg"><label class="fl">Term Fee Override (optional)</label><input class="fi" name="fee" type="number" value="<?= h($editClass['fee'] ?? '') ?>" placeholder="Leave blank to use school default"></div>
      <div class="fg">
        <label class="fl">Subject Teachers for this Class</label>
        <div style="display:flex;flex-direction:column;gap:8px;border:1px solid var(--border);border-radius:8px;padding:10px">
          <?php if (!count($subjects)): ?>
            <div style="font-size:12px;color:#888">No subjects set up yet — add some on the Subjects page first.</div>
          <?php endif; ?>
          <?php foreach ($subjects as $s): ?>
            <div style="display:flex;align-items:center;gap:8px;justify-content:space-between">
              <span style="font-size:13px"><?= h($s['name']) ?></span>
              <select class="fs" name="subj_<?= h($s['code']) ?>" style="max-width:220px">
                <option value="">— Not taught in this class —</option>
                <?php foreach ($teachers as $t): ?>
                  <option value="<?= $t['id'] ?>" <?= (($editAssignments[$s['code']] ?? '') == $t['id']) ? 'selected' : '' ?>><?= h($t['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="btn btn-primary" type="submit">Save Class</button>
      <?php if ($editClass): ?><a class="btn btn-ghost" href="classes.php">Cancel</a><?php endif; ?>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">All Classes</div>
  <div class="card-body">
    <?php if (!count($classes)): ?>
      <div class="empty-state"><div class="icon">&#127979;</div><p>No classes added yet.</p></div>
    <?php else: ?>
      <table>
        <thead><tr><th>Grade</th><th>Stream</th><th>Class Teacher</th><th>Learners</th><th>Fee</th><?php if (is_admin($user)): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($classes as $c): ?>
          <tr>
            <td><?= h($c['grade']) ?></td>
            <td><?= $streamingMode === 'single' ? '<span style="color:#888">Single stream</span>' : h($c['stream']) ?></td>
            <td><?= h(teacher_name($pdo, $c['class_teacher_id']) ?: '-') ?></td>
            <td><?= $learnerCounts[$c['id']] ?? 0 ?></td>
            <td><?= $c['fee'] !== null ? number_format($c['fee']) : '<span style="color:#888">school default</span>' ?></td>
            <?php if (is_admin($user)): ?>
            <td>
              <a class="act-btn" href="?edit=<?= $c['id'] ?>#form" title="Edit">&#9998;</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete <?= h(class_label($pdo, $c['id'])) ?>?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
