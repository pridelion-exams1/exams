<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/auth.php';
$user = require_login($pdo);

$classes = $pdo->query("SELECT * FROM classes ORDER BY grade, stream")->fetchAll(PDO::FETCH_ASSOC);
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id = trim($_POST['id'] ?? '');
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $admType = $_POST['admission_type'] ?? 'New';
        $prevSchool = trim($_POST['previous_school'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $dob = $_POST['dob'] ?? '';
        $classId = $_POST['class_id'] ?: null;
        $admDate = $_POST['admission_date'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        $guardianName = trim($_POST['guardian_name'] ?? '');
        $guardianPhone = trim($_POST['guardian_phone'] ?? '');
        $guardianEmail = trim($_POST['guardian_email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $editId = trim($_POST['edit_id'] ?? '');

        $missing = [];
        if (!$id) $missing[] = 'Admission Number';
        if (!$first) $missing[] = 'First Name';
        if (!$last) $missing[] = 'Last Name';
        if (!$dob) $missing[] = 'Date of Birth';
        if (!$classId) $missing[] = 'Class';
        if (!$guardianName) $missing[] = 'Guardian Name';
        if ($admType === 'Transfer' && !$prevSchool) $missing[] = 'Previous School';

        if (!$admDate) {
            $missing[] = 'Admission Date';
        } elseif ($admDate > $today) {
            flash('error', 'Admission Date cannot be in the future.');
            header('Location: learners.php'); exit;
        }
        if ($missing) {
            flash('error', 'Please fill in: ' . implode(', ', $missing));
            header('Location: learners.php' . ($editId ? '?edit=' . urlencode($editId) : '')); exit;
        }

        if ($editId) {
            $pdo->prepare("UPDATE learners SET id=?,first_name=?,last_name=?,admission_type=?,previous_school=?,gender=?,dob=?,class_id=?,admission_date=?,status=?,guardian_name=?,guardian_phone=?,guardian_email=?,address=? WHERE id=?")
                ->execute([$id, $first, $last, $admType, $prevSchool, $gender, $dob, $classId, $admDate, $status, $guardianName, $guardianPhone, $guardianEmail, $address, $editId]);
            $pdo->prepare("UPDATE marks SET learner_id=? WHERE learner_id=?")->execute([$id, $editId]);
            flash('success', 'Learner updated.');
        } else {
            $chk = $pdo->prepare("SELECT 1 FROM learners WHERE id=?"); $chk->execute([$id]);
            if ($chk->fetch()) { flash('error', 'That admission number already exists.'); header('Location: learners.php'); exit; }
            $pdo->prepare("INSERT INTO learners (id,first_name,last_name,admission_type,previous_school,gender,dob,class_id,admission_date,status,guardian_name,guardian_phone,guardian_email,address,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$id, $first, $last, $admType, $prevSchool, $gender, $dob, $classId, $admDate, $status, $guardianName, $guardianPhone, $guardianEmail, $address, date('c')]);
            log_activity($pdo, 'New learner admitted: ' . $first . ' ' . $last . ' (' . $id . ')');
            flash('success', 'Learner admitted.');
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        $pdo->prepare("DELETE FROM learners WHERE id=?")->execute([$id]);
        flash('error', 'Learner deleted.');
    }
    header('Location: learners.php'); exit;
}

$search = trim($_GET['q'] ?? '');
$filterClass = $_GET['class'] ?? '';
$sql = "SELECT * FROM learners WHERE 1=1";
$params = [];
if ($search !== '') { $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR id LIKE ?)"; $like = "%$search%"; $params = [$like, $like, $like]; }
if ($filterClass !== '') { $sql .= " AND class_id=?"; $params[] = $filterClass; }
$sql .= " ORDER BY first_name, last_name";
$st = $pdo->prepare($sql); $st->execute($params);
$learners = $st->fetchAll(PDO::FETCH_ASSOC);

$editLearner = null;
if (!empty($_GET['edit'])) {
    $st2 = $pdo->prepare("SELECT * FROM learners WHERE id=?"); $st2->execute([$_GET['edit']]);
    $editLearner = $st2->fetch(PDO::FETCH_ASSOC);
}

$pageTitle = 'Learners'; $activeNav = 'learners';
require __DIR__ . '/includes/layout_top.php';
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
  <h1 style="font-size:20px;margin:0">Learners</h1>
  <a href="#form" class="btn btn-primary btn-sm">+ Admit Learner</a>
</div>

<div class="card" id="form">
  <div class="card-header"><?= $editLearner ? 'Edit Learner' : 'Admit New Learner' ?></div>
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="edit_id" value="<?= h($editLearner['id'] ?? '') ?>">
      <div class="frow">
        <div class="fg"><label class="fl">Admission Number</label><input class="fi" name="id" value="<?= h($editLearner['id'] ?? '') ?>" required></div>
        <div class="fg"><label class="fl">Admission Type</label>
          <select class="fs" name="admission_type" id="admType" onchange="document.getElementById('prevSchoolWrap').style.display=this.value==='Transfer'?'block':'none'">
            <option value="New" <?= (($editLearner['admission_type'] ?? '') === 'New') ? 'selected' : '' ?>>New</option>
            <option value="Transfer" <?= (($editLearner['admission_type'] ?? '') === 'Transfer') ? 'selected' : '' ?>>Transfer</option>
          </select>
        </div>
      </div>
      <div class="frow">
        <div class="fg"><label class="fl">First Name</label><input class="fi" name="first_name" value="<?= h($editLearner['first_name'] ?? '') ?>" required></div>
        <div class="fg"><label class="fl">Last Name</label><input class="fi" name="last_name" value="<?= h($editLearner['last_name'] ?? '') ?>" required></div>
      </div>
      <div class="fg" id="prevSchoolWrap" style="display:<?= (($editLearner['admission_type'] ?? '') === 'Transfer') ? 'block' : 'none' ?>">
        <label class="fl">Previous School</label><input class="fi" name="previous_school" value="<?= h($editLearner['previous_school'] ?? '') ?>">
      </div>
      <div class="frow">
        <div class="fg"><label class="fl">Gender</label>
          <select class="fs" name="gender">
            <option value="Male" <?= (($editLearner['gender'] ?? '') === 'Male') ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= (($editLearner['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
          </select>
        </div>
        <div class="fg"><label class="fl">Date of Birth</label><input class="fi" name="dob" type="date" value="<?= h($editLearner['dob'] ?? '') ?>" required></div>
      </div>
      <div class="frow">
        <div class="fg"><label class="fl">Class</label>
          <select class="fs" name="class_id" required>
            <option value="">Select class...</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>" <?= (($editLearner['class_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= h(class_label($pdo, $c['id'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg"><label class="fl">Admission Date</label><input class="fi" name="admission_date" type="date" max="<?= $today ?>" value="<?= h($editLearner['admission_date'] ?? $today) ?>" required></div>
      </div>
      <div class="fg"><label class="fl">Status</label>
        <select class="fs" name="status">
          <option value="Active" <?= (($editLearner['status'] ?? 'Active') === 'Active') ? 'selected' : '' ?>>Active</option>
          <option value="Transferred" <?= (($editLearner['status'] ?? '') === 'Transferred') ? 'selected' : '' ?>>Transferred</option>
          <option value="Graduated" <?= (($editLearner['status'] ?? '') === 'Graduated') ? 'selected' : '' ?>>Graduated</option>
        </select>
      </div>
      <div class="frow">
        <div class="fg"><label class="fl">Guardian Name</label><input class="fi" name="guardian_name" value="<?= h($editLearner['guardian_name'] ?? '') ?>" required></div>
        <div class="fg"><label class="fl">Guardian Phone</label><input class="fi" name="guardian_phone" value="<?= h($editLearner['guardian_phone'] ?? '') ?>"></div>
      </div>
      <div class="frow">
        <div class="fg"><label class="fl">Guardian Email</label><input class="fi" name="guardian_email" type="email" value="<?= h($editLearner['guardian_email'] ?? '') ?>"></div>
        <div class="fg"><label class="fl">Address</label><input class="fi" name="address" value="<?= h($editLearner['address'] ?? '') ?>"></div>
      </div>
      <button class="btn btn-primary" type="submit"><?= $editLearner ? 'Save Changes' : 'Admit Learner' ?></button>
      <?php if ($editLearner): ?><a class="btn btn-ghost" href="learners.php">Cancel</a><?php endif; ?>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <form method="get" style="display:flex;gap:10px;align-items:center">
      <input class="fi" name="q" placeholder="Search by name or admission no." value="<?= h($search) ?>" style="max-width:240px">
      <select class="fs" name="class" style="max-width:200px" onchange="this.form.submit()">
        <option value="">All classes</option>
        <?php foreach ($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $filterClass == $c['id'] ? 'selected' : '' ?>><?= h(class_label($pdo, $c['id'])) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-ghost btn-sm" type="submit">Filter</button>
    </form>
  </div>
  <div class="card-body">
    <?php if (!count($learners)): ?>
      <div class="empty-state"><div class="icon">&#128100;</div><p>No learners found.</p></div>
    <?php else: ?>
      <table>
        <thead><tr><th>Adm No.</th><th>Name</th><th>Class</th><th>Status</th><th>Guardian</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($learners as $l): ?>
          <tr>
            <td class="mono"><?= h($l['id']) ?></td>
            <td style="font-weight:600"><?= h($l['first_name'] . ' ' . $l['last_name']) ?></td>
            <td><?= h(class_label($pdo, $l['class_id'])) ?></td>
            <td><span class="badge <?= $l['status'] === 'Active' ? 'badge-ee' : 'badge-inactive' ?>"><?= h($l['status']) ?></span></td>
            <td><?= h($l['guardian_name'] ?: '-') ?></td>
            <td>
              <a class="act-btn" href="?edit=<?= urlencode($l['id']) ?>#form" title="Edit">&#9998;</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this learner record?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= h($l['id']) ?>">
                <button class="act-btn" type="submit" title="Delete">&#128465;</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
