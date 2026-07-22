<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/auth.php';
$user = require_login($pdo);

if (!can_enter_marks($user)) { flash('error', 'Your role does not have access to marks entry.'); header('Location: exams.php'); exit; }

$examId = $_GET['exam'] ?? $_POST['exam_id'] ?? null;
$st = $pdo->prepare("SELECT * FROM exams WHERE id=?"); $st->execute([$examId]);
$exam = $st->fetch(PDO::FETCH_ASSOC);
if (!$exam) { flash('error', 'Exam not found.'); header('Location: exams.php'); exit; }

$stM = $pdo->prepare("SELECT COUNT(DISTINCT learner_id) c FROM marks WHERE exam_id=?"); $stM->execute([$exam['id']]);
$started = $stM->fetch(PDO::FETCH_ASSOC)['c'] > 0;
$canEdit = can_edit_saved($user);
if ($started && !$canEdit) { flash('error', 'Marks for this exam are already saved — only an Administrator can edit them now.'); header('Location: exams.php'); exit; }

$csubj = class_subjects($pdo, $exam['class_id']);
$learners = marks_eligible_learners($pdo, $exam['class_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_marks') {
    foreach ($learners as $l) {
        $lid = $l['id'];
        foreach ($csubj as $s) {
            $field = 'm_' . $lid . '_' . $s['code'];
            if (!isset($_POST[$field])) continue;
            $raw = preg_replace('/[^0-9]/', '', (string)$_POST[$field]);
            $chk = $pdo->prepare("SELECT score FROM marks WHERE exam_id=? AND learner_id=? AND subject_code=?");
            $chk->execute([$exam['id'], $lid, $s['code']]);
            $existing = $chk->fetch(PDO::FETCH_ASSOC);
            if ($existing !== false && !$canEdit) continue; // locked, keep as-is
            if ($raw === '') continue;
            $val = max(0, min(99, (int)$raw));
            $pdo->prepare("INSERT INTO marks (exam_id,learner_id,subject_code,score) VALUES (?,?,?,?)
                           ON CONFLICT(exam_id,learner_id,subject_code) DO UPDATE SET score=excluded.score")
                ->execute([$exam['id'], $lid, $s['code'], $val]);
        }
    }
    log_activity($pdo, 'Marks saved: ' . $exam['name'] . ' — ' . class_label($pdo, $exam['class_id']) . ' (by ' . $user['role'] . ')');
    flash('success', 'Marks saved.');
    header('Location: marks.php?exam=' . $exam['id']); exit;
}

// Whole-school class switcher options
$groupExams = [];
if ($exam['exam_group']) {
    $stG = $pdo->prepare("SELECT * FROM exams WHERE exam_group=? ORDER BY id"); $stG->execute([$exam['exam_group']]);
    foreach ($stG->fetchAll(PDO::FETCH_ASSOC) as $ge) {
        $stC = $pdo->prepare("SELECT COUNT(DISTINCT learner_id) c FROM marks WHERE exam_id=?"); $stC->execute([$ge['id']]);
        $ge['marked'] = $stC->fetch(PDO::FETCH_ASSOC)['c'];
        $ge['total'] = count(marks_eligible_learners($pdo, $ge['class_id']));
        $groupExams[] = $ge;
    }
}

// Load existing marks for display
$existingMarks = [];
foreach ($learners as $l) {
    $stE = $pdo->prepare("SELECT subject_code,score FROM marks WHERE exam_id=? AND learner_id=?");
    $stE->execute([$exam['id'], $l['id']]);
    foreach ($stE->fetchAll(PDO::FETCH_ASSOC) as $r) $existingMarks[$l['id']][$r['subject_code']] = $r['score'];
}

$pageTitle = 'Marks Entry'; $activeNav = 'exams';
require __DIR__ . '/includes/layout_top.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
  <a href="exams.php" class="btn btn-ghost btn-sm">&#8592; Back to Exams</a>
  <div style="font-family:'Syne',sans-serif;font-weight:700;font-size:15px"><?= $started ? 'Edit Marks' : 'Enter Marks' ?> — <?= h($exam['name']) ?> — <?= h(class_label($pdo, $exam['class_id'])) ?> — <?= h($exam['term']) ?> <?= h($exam['year']) ?></div>
  <button class="btn btn-primary btn-sm" type="submit" form="marksForm">&#10003; Save Marks</button>
</div>

<?php if (count($groupExams) > 1): ?>
<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
  <label class="fl" style="margin-bottom:0">Class (this exam is set for every class below)</label>
  <select class="fs" style="max-width:320px" onchange="if(this.value)window.location='marks.php?exam='+this.value">
    <?php foreach ($groupExams as $ge): ?>
      <option value="<?= $ge['id'] ?>" <?= $ge['id'] == $exam['id'] ? 'selected' : '' ?>><?= h(class_label($pdo, $ge['class_id'])) ?> (<?= $ge['marked'] ?>/<?= $ge['total'] ?> marked)</option>
    <?php endforeach; ?>
  </select>
</div>
<?php endif; ?>

<div style="font-size:12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:8px 12px;margin-bottom:14px">
  <?php if ($canEdit): ?>
    Signed in as <strong><?= h($user['role']) ?></strong> — you can enter and edit marks, including previously saved ones.
  <?php else: ?>
    Signed in as <strong><?= h($user['role']) ?></strong> — you can enter marks for this exam. Once saved, only an Administrator can make further changes.
  <?php endif; ?>
</div>

<div class="card">
  <div style="overflow-x:auto">
  <?php if (!count($csubj)): ?>
    <div class="empty-state"><div class="icon">&#128221;</div><p>No subjects have a teacher assigned for <?= h(class_label($pdo, $exam['class_id'])) ?> yet. Assign subject teachers on the class first.</p></div>
  <?php elseif (!count($learners)): ?>
    <div class="empty-state"><div class="icon">&#128221;</div><p>No active learners in this class.</p></div>
  <?php else: ?>
    <form method="post" id="marksForm">
      <input type="hidden" name="action" value="save_marks">
      <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
      <table id="marksTable">
        <thead><tr><th>Learner</th><?php foreach ($csubj as $s): ?><th><?= h($s['code']) ?></th><?php endforeach; ?><th>Total</th><th>Avg</th><th>Level</th></tr></thead>
        <tbody>
        <?php foreach ($learners as $l):
            $calc = calc_learner_exam($pdo, $exam['id'], $l['id'], $exam['class_id']);
        ?>
          <tr id="mrow-<?= h($l['id']) ?>">
            <td style="font-weight:600"><?= h($l['first_name'] . ' ' . $l['last_name']) ?><div class="staff-id"><?= h($l['id']) ?></div></td>
            <?php foreach ($csubj as $s):
                $v = $existingMarks[$l['id']][$s['code']] ?? '';
                $saved = isset($existingMarks[$l['id']][$s['code']]);
                $locked = $saved && !$canEdit;
            ?>
              <td><input type="number" min="0" max="99" step="1"
                value="<?= h($v) ?>"
                name="m_<?= h($l['id']) ?>_<?= h($s['code']) ?>"
                class="fi mark-input" data-lid="<?= h($l['id']) ?>"
                style="width:64px;padding:5px 7px<?= $locked ? ';background:var(--surface2);color:#888;cursor:not-allowed' : '' ?>"
                oninput="sanitizeMarkInput(this);recalcRow('<?= h($l['id']) ?>')"
                onkeypress="return /[0-9]/.test(event.key)"
                onkeydown="handleMarksKeydown(event,this)"
                <?= $locked ? 'disabled title="Already saved — only an Administrator can edit"' : '' ?>></td>
            <?php endforeach; ?>
            <td id="mtot-<?= h($l['id']) ?>"><?= $calc['total'] ?></td>
            <td id="mavg-<?= h($l['id']) ?>"><?= round($calc['average']) ?>%</td>
            <td id="mlvl-<?= h($l['id']) ?>"><span class="badge lvl-<?= $calc['level']['group'] ?>"><?= $calc['level']['code'] ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </form>
  <?php endif; ?>
  </div>
</div>

<script>
function sanitizeMarkInput(inp){
  var v=inp.value.replace(/[^0-9]/g,'');
  if(v.length>1)v=v.replace(/^0+(?=\d)/,'');
  if(v!==''&&parseInt(v,10)>99)v='99';
  inp.value=v;
}
function handleMarksKeydown(e,el){
  if(e.key!=='Enter')return;
  e.preventDefault();
  var inputs=Array.prototype.slice.call(document.querySelectorAll('#marksTable tbody input'));
  var idx=inputs.indexOf(el);
  if(idx>-1&&idx<inputs.length-1){var next=inputs[idx+1];next.focus();if(next.select)next.select();}
}
function recalcRow(lid){
  var inputs=document.querySelectorAll('#mrow-'+lid+' input');
  var total=0,count=0;
  inputs.forEach(function(inp){
    var v=parseInt(inp.value,10);
    if(!isNaN(v)){total+=v;count++;}
  });
  var avg=inputs.length?(total/inputs.length):0;
  document.getElementById('mtot-'+lid).textContent=total;
  document.getElementById('mavg-'+lid).textContent=Math.round(avg)+'%';
}
</script>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
