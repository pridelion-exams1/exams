<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/auth.php';
$user = require_login($pdo);

$classes = $pdo->query("SELECT * FROM classes ORDER BY grade, stream")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        if (!is_admin($user)) { flash('error', 'Only an Administrator can create exams.'); header('Location: exams.php'); exit; }
        $name = trim($_POST['name'] ?? '');
        $term = $_POST['term'] ?? 'Term 1';
        $year = (int)($_POST['year'] ?? date('Y'));
        $scope = $_POST['scope'] ?? 'class';

        if (!$name) { flash('error', 'Exam name is required.'); header('Location: exams.php'); exit; }

        if ($scope === 'whole') {
            if (!count($classes)) { flash('error', 'No classes set up yet.'); header('Location: exams.php'); exit; }
            $groupId = 'EG' . time() . rand(100, 999);
            $created = 0; $skipped = [];
            foreach ($classes as $c) {
                $chk = $pdo->prepare("SELECT 1 FROM exams WHERE class_id=? AND term=? AND year=? AND LOWER(name)=LOWER(?)");
                $chk->execute([$c['id'], $term, $year, $name]);
                if ($chk->fetch()) { $skipped[] = class_label($pdo, $c['id']); continue; }
                $pdo->prepare("INSERT INTO exams (name,term,year,class_id,exam_group) VALUES (?,?,?,?,?)")
                    ->execute([$name, $term, $year, $c['id'], $groupId]);
                $created++;
            }
            if (!$created) { flash('error', 'That exam already exists for every class — edit the existing ones instead.'); header('Location: exams.php'); exit; }
            log_activity($pdo, 'Exam created for whole school: ' . $name . ' — ' . $created . ' classes');
            flash('success', $skipped ? ("Created for $created class(es); skipped " . count($skipped) . ' duplicate(s)') : 'Exam created for all classes.');
        } else {
            $classId = $_POST['class_id'] ?? '';
            if (!$classId) { flash('error', 'Please select a class.'); header('Location: exams.php'); exit; }
            $chk = $pdo->prepare("SELECT 1 FROM exams WHERE class_id=? AND term=? AND year=? AND LOWER(name)=LOWER(?)");
            $chk->execute([$classId, $term, $year, $name]);
            if ($chk->fetch()) { flash('error', 'An exam with this name already exists for that class/term/year — edit it instead.'); header('Location: exams.php'); exit; }
            $pdo->prepare("INSERT INTO exams (name,term,year,class_id) VALUES (?,?,?,?)")->execute([$name, $term, $year, $classId]);
            log_activity($pdo, 'Exam created: ' . $name . ' — ' . class_label($pdo, $classId));
            flash('success', 'Exam created.');
        }
    } elseif ($action === 'edit') {
        if (!is_admin($user)) { flash('error', 'Only an Administrator can edit an exam once created.'); header('Location: exams.php'); exit; }
        $id = $_POST['id'] ?? '';
        $name = trim($_POST['name'] ?? '');
        $term = $_POST['term'] ?? 'Term 1';
        $year = (int)($_POST['year'] ?? date('Y'));
        $classId = $_POST['class_id'] ?? '';
        if (!$name || !$classId) { flash('error', 'Name and class are required.'); header('Location: exams.php'); exit; }
        $chk = $pdo->prepare("SELECT 1 FROM exams WHERE class_id=? AND term=? AND year=? AND LOWER(name)=LOWER(?) AND id!=?");
        $chk->execute([$classId, $term, $year, $name, $id]);
        if ($chk->fetch()) { flash('error', 'An exam with this name already exists for that class/term/year.'); header('Location: exams.php'); exit; }
        $pdo->prepare("UPDATE exams SET name=?,term=?,year=?,class_id=? WHERE id=?")->execute([$name, $term, $year, $classId, $id]);
        log_activity($pdo, 'Exam edited: ' . $name . ' — ' . class_label($pdo, $classId));
        flash('success', 'Exam updated.');
    } elseif ($action === 'delete') {
        if (!is_admin($user)) { flash('error', 'Only an Administrator can delete an exam.'); header('Location: exams.php'); exit; }
        $id = $_POST['id'] ?? '';
        $scope = $_POST['delete_scope'] ?? 'one';
        $st = $pdo->prepare("SELECT * FROM exams WHERE id=?"); $st->execute([$id]);
        $ex = $st->fetch(PDO::FETCH_ASSOC);
        if ($ex) {
            if ($ex['exam_group'] && $scope === 'all') {
                $pdo->prepare("DELETE FROM exams WHERE exam_group=?")->execute([$ex['exam_group']]);
                flash('error', 'Exam deleted for all classes.');
            } else {
                $pdo->prepare("DELETE FROM exams WHERE id=?")->execute([$id]);
                flash('error', 'Exam deleted' . ($ex['exam_group'] ? ' for ' . class_label($pdo, $ex['class_id']) : '') . '.');
            }
        }
    }
    header('Location: exams.php'); exit;
}

$exams = $pdo->query("SELECT * FROM exams ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$editExam = null;
if (!empty($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM exams WHERE id=?"); $st->execute([$_GET['edit']]);
    $editExam = $st->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = 'Exams'; $activeNav = 'exams';
require __DIR__ . '/includes/layout_top.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
  <h1 style="font-size:20px;margin:0">Exams</h1>
</div>

<?php if (is_admin($user)): ?>
<div class="card" id="form">
  <div class="card-header"><?= $editExam ? 'Edit Exam' : 'Create Exam' ?></div>
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="action" value="<?= $editExam ? 'edit' : 'create' ?>">
      <?php if ($editExam): ?><input type="hidden" name="id" value="<?= $editExam['id'] ?>"><?php endif; ?>
      <div class="fg"><label class="fl">Exam Name</label><input class="fi" name="name" value="<?= h($editExam['name'] ?? '') ?>" placeholder="e.g. Mid-Term Exam" required></div>
      <div class="frow">
        <div class="fg"><label class="fl">Term</label>
          <select class="fs" name="term">
            <?php foreach (['Term 1','Term 2','Term 3'] as $t): ?>
              <option value="<?= $t ?>" <?= (($editExam['term'] ?? get_setting($pdo,'term')) === $t) ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg"><label class="fl">Year</label><input class="fi" name="year" type="number" value="<?= h($editExam['year'] ?? get_setting($pdo,'year')) ?>"></div>
      </div>
      <?php if (!$editExam): ?>
      <div class="fg">
        <label class="fl">Applies To</label>
        <select class="fs" name="scope" id="examScope" onchange="document.getElementById('classWrap').style.display=this.value==='whole'?'block':'none';document.getElementById('wholeNote').style.display=this.value==='whole'?'block':'none';">
          <option value="class">Specific Class</option>
          <option value="whole">Whole School (every class)</option>
        </select>
      </div>
      <?php endif; ?>
      <div class="fg" id="classWrap">
        <label class="fl">Class</label>
        <select class="fs" name="class_id">
          <option value="">Select class...</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (($editExam['class_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= h(class_label($pdo, $c['id'])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div id="wholeNote" style="display:none;font-size:12px;color:#888;margin-top:-6px;margin-bottom:14px">This will create the exam for every class currently set up. Marks are entered separately per class.</div>
      <button class="btn btn-primary" type="submit"><?= $editExam ? 'Save Changes' : 'Create Exam' ?></button>
      <?php if ($editExam): ?><a class="btn btn-ghost" href="exams.php">Cancel</a><?php endif; ?>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">All Exams</div>
  <div class="card-body">
    <?php if (!count($exams)): ?>
      <div class="empty-state"><div class="icon">&#128221;</div><p>No exams created yet.</p></div>
    <?php else: ?>
      <table>
        <thead><tr><th>Exam</th><th>Class</th><th>Term</th><th>Year</th><th>Marked</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($exams as $ex):
            $total = count(marks_eligible_learners($pdo, $ex['class_id']));
            $st = $pdo->prepare("SELECT COUNT(DISTINCT learner_id) c FROM marks WHERE exam_id=?"); $st->execute([$ex['id']]);
            $marked = $st->fetch(PDO::FETCH_ASSOC)['c'];
            $started = $marked > 0;
        ?>
          <tr>
            <td style="font-weight:600"><?= h($ex['name']) ?><?php if ($ex['exam_group']): ?> <span class="badge badge-pending" title="Part of a whole-school exam batch">Whole School</span><?php endif; ?></td>
            <td><?= h(class_label($pdo, $ex['class_id'])) ?></td>
            <td><?= h($ex['term']) ?></td>
            <td><?= h($ex['year']) ?></td>
            <td><?= $marked ?> / <?= $total ?></td>
            <td>
              <?php if ($started && !is_admin($user)): ?>
                <span class="badge badge-inactive" title="Marks already saved — only an Administrator can edit">&#128274; Edit Marks (Admin only)</span>
              <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="marks.php?exam=<?= $ex['id'] ?>"><?= $started ? 'Edit Marks' : 'Enter Marks' ?></a>
              <?php endif; ?>
              <?php if (is_admin($user)): ?>
                <a class="act-btn" href="?edit=<?= $ex['id'] ?>#form" title="Edit">&#9998;</a>
                <?php if ($ex['exam_group']): ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete '+'<?= h($ex['name']) ?>'+' for ALL classes in this whole-school batch? Click Cancel to instead delete it only for <?= h(class_label($pdo, $ex['class_id'])) ?>.') ? (this.delete_scope.value='all',true) : (confirm('Delete this exam for <?= h(class_label($pdo, $ex['class_id'])) ?> only? Recorded marks for this class will be lost.') ? (this.delete_scope.value='one',true) : false)">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $ex['id'] ?>">
                    <input type="hidden" name="delete_scope" value="one">
                    <button class="act-btn" type="submit" title="Delete">&#128465;</button>
                  </form>
                <?php else: ?>
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete exam <?= h($ex['name']) ?>? All recorded marks will be lost.')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $ex['id'] ?>">
                    <button class="act-btn" type="submit" title="Delete">&#128465;</button>
                  </form>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
