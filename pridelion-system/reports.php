<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/auth.php';
$user = require_login($pdo);

$classes = $pdo->query("SELECT * FROM classes ORDER BY grade, stream")->fetchAll(PDO::FETCH_ASSOC);
$settings = all_settings($pdo);

$scope = $_GET['scope'] ?? 'exam';
$classId = $_GET['class_id'] ?? '';
$examId = $_GET['exam_id'] ?? '';
$term = $_GET['term'] ?? get_setting($pdo, 'term');
$year = $_GET['year'] ?? get_setting($pdo, 'year');
$learnerId = $_GET['learner_id'] ?? '';
$includeRank = !empty($_GET['rank']);
$schoolNameOverride = $_GET['school_name'] ?? $settings['school_name'];
$footerText = $_GET['footer'] ?? $settings['report_footer'];
$mode = $_GET['mode'] ?? ''; // '', 'preview', 'class_all', 'class_summary'

$classExams = [];
if ($classId) {
    $st = $pdo->prepare("SELECT * FROM exams WHERE class_id=? ORDER BY id DESC"); $st->execute([$classId]);
    $classExams = $st->fetchAll(PDO::FETCH_ASSOC);
}
$classLearnersAll = $classId ? class_learners($pdo, $classId) : [];

function scope_exam_ids($pdo, $scope, $classId, $examId, $term, $year) {
    if (!$classId) return [];
    if ($scope === 'exam') return $examId ? [(int)$examId] : [];
    $sql = "SELECT id FROM exams WHERE class_id=?";
    $params = [$classId];
    if ($scope === 'term') { $sql .= " AND term=? AND year=?"; $params[] = $term; $params[] = $year; }
    elseif ($scope === 'year') { $sql .= " AND year=?"; $params[] = $year; }
    $st = $pdo->prepare($sql); $st->execute($params);
    return array_map(function ($r) { return $r['id']; }, $st->fetchAll(PDO::FETCH_ASSOC));
}

function scope_label($pdo, $scope, $classId, $examId, $term, $year) {
    if ($scope === 'exam') {
        if (!$examId) return '';
        $st = $pdo->prepare("SELECT * FROM exams WHERE id=?"); $st->execute([$examId]);
        $ex = $st->fetch(PDO::FETCH_ASSOC);
        return $ex ? ($ex['name'] . ' · ' . $ex['term'] . ' ' . $ex['year']) : '';
    }
    if ($scope === 'term') return "$term $year — All Exams";
    if ($scope === 'year') return "$year — Full Year Report";
    return '';
}

function report_header_html($settings, $schoolName) {
    $out = '<div class="ps-header"><div><div class="ps-co">' . h($schoolName) . '</div>';
    $out .= '<div style="font-size:12px;color:#555">' . h($settings['motto']) . '</div>';
    $out .= '<div style="font-size:11px;color:#777;margin-top:3px">' . h($settings['address']) . ($settings['phone'] ? ' · ' . h($settings['phone']) : '') . '</div></div>';
    if (!empty($settings['logo'])) $out .= '<img src="' . h($settings['logo']) . '" style="max-height:60px;max-width:120px;object-fit:contain">';
    $out .= '</div>';
    return $out;
}

function report_footer_block($settings, $classId, $pdo, $footerText) {
    $cls = null;
    if ($classId) { $st = $pdo->prepare("SELECT * FROM classes WHERE id=?"); $st->execute([$classId]); $cls = $st->fetch(PDO::FETCH_ASSOC); }
    $classTeacherName = ($cls && $cls['class_teacher_id']) ? teacher_name($pdo, $cls['class_teacher_id']) : '';
    $out = '<div style="display:flex;justify-content:space-between;margin-top:36px;font-size:12px">';
    $out .= '<div>Class Teacher: ' . h($classTeacherName ?: '______________________') . '</div>';
    $out .= '<div>Head of Institution: ' . h($settings['head_name'] ?: '______________________') . '</div>';
    $out .= '</div>';
    if ($footerText) $out .= '<div style="margin-top:24px;padding-top:10px;border-top:1px solid #eee;font-size:11px;color:#777;text-align:center">' . h($footerText) . '</div>';
    return $out;
}

function build_learner_report_html($pdo, $settings, $scope, $classId, $examIds, $scopeLabel, $learnerId, $includeRank, $schoolName, $footerText) {
    if (!count($examIds)) return '';
    $st = $pdo->prepare("SELECT * FROM learners WHERE id=?"); $st->execute([$learnerId]);
    $l = $st->fetch(PDO::FETCH_ASSOC);
    if (!$l) return '';
    $csubj = class_subjects($pdo, $classId);

    $subjectRows = '';
    foreach ($csubj as $s) {
        $cells = ''; $scores = [];
        foreach ($examIds as $eid) {
            $stM = $pdo->prepare("SELECT score FROM marks WHERE exam_id=? AND learner_id=? AND subject_code=?");
            $stM->execute([$eid, $learnerId, $s['code']]);
            $r = $stM->fetch(PDO::FETCH_ASSOC);
            $v = $r ? $r['score'] : null;
            if ($v !== null) $scores[] = $v;
            if ($scope === 'exam') continue; // single exam shows one score column below
            $cells .= '<td style="text-align:center">' . ($v !== null ? $v : '-') . '</td>';
        }
        $tName = subject_teacher_for($pdo, $classId, $s['code']);
        $tNameStr = $tName ? teacher_name($pdo, $tName) : '-';
        if ($scope === 'exam') {
            $v = count($scores) ? $scores[0] : null;
            $lvl = $v !== null ? cbc_level($v) : null;
            $subjectRows .= '<tr><td>' . h($s['name']) . '</td><td style="text-align:center">' . ($v !== null ? $v : '-') . '</td>'
                . '<td style="text-align:center">' . ($lvl ? ('<span class="badge lvl-' . $lvl['group'] . '">' . $lvl['code'] . '</span>') : '-') . '</td>'
                . '<td style="text-align:center;font-size:11px">' . h($tNameStr) . '</td></tr>';
        } else {
            $avg = count($scores) ? array_sum($scores) / count($scores) : null;
            $lvl = $avg !== null ? cbc_level($avg) : null;
            $subjectRows .= '<tr><td>' . h($s['name']) . '</td>' . $cells
                . '<td style="text-align:center;font-weight:700">' . ($avg !== null ? round($avg) : '-') . '</td>'
                . '<td style="text-align:center">' . ($lvl ? ('<span class="badge lvl-' . $lvl['group'] . '">' . $lvl['code'] . '</span>') : '-') . '</td>'
                . '<td style="text-align:center;font-size:11px">' . h($tNameStr) . '</td></tr>';
        }
    }

    $examAverages = [];
    foreach ($examIds as $eid) {
        $stC = $pdo->prepare("SELECT COUNT(*) c FROM marks WHERE exam_id=? AND learner_id=?"); $stC->execute([$eid, $learnerId]);
        if ($stC->fetch(PDO::FETCH_ASSOC)['c'] > 0) {
            $calc = calc_learner_exam($pdo, $eid, $learnerId, $classId);
            $examAverages[] = $calc['average'];
        }
    }
    $overallAvg = count($examAverages) ? array_sum($examAverages) / count($examAverages) : 0;
    $overallLevel = cbc_level($overallAvg);

    $rankInfo = '';
    if ($includeRank) {
        $rows = count($examIds) === 1 && $scope === 'exam' ? rank_class($pdo, $examIds[0], $classId) : rank_class_aggregate($pdo, $classId, $examIds);
        foreach ($rows as $r) {
            if ($r['learner_id'] === $learnerId) {
                $rankInfo = '<div style="margin-top:6px;font-size:13px"><strong>Class Position:</strong> ' . $r['position'] . ' out of ' . count($rows) . '</div>';
                break;
            }
        }
    }

    $examHeaders = '';
    if ($scope !== 'exam') {
        foreach ($examIds as $eid) {
            $stN = $pdo->prepare("SELECT name FROM exams WHERE id=?"); $stN->execute([$eid]);
            $en = $stN->fetch(PDO::FETCH_ASSOC);
            $examHeaders .= '<th style="padding:7px;font-size:10px;text-transform:uppercase;text-align:center">' . h($en['name'] ?? '') . '</th>';
        }
    }

    $out = report_header_html($settings, $schoolName);
    $out .= '<div style="text-align:center;font-family:Syne,sans-serif;font-weight:800;font-size:16px;margin-bottom:4px;letter-spacing:.5px">LEARNER REPORT FORM</div>';
    $out .= '<div style="text-align:center;font-size:12px;color:#555;margin-bottom:18px">' . h($scopeLabel) . '</div>';
    $out .= '<table class="ps-table" style="margin-bottom:10px">'
        . '<tr><td style="width:25%"><strong>Name</strong></td><td>' . h($l['first_name'] . ' ' . $l['last_name']) . '</td><td style="width:25%"><strong>Admission No.</strong></td><td>' . h($l['id']) . '</td></tr>'
        . '<tr><td><strong>Class</strong></td><td>' . h(class_label($pdo, $classId)) . '</td><td><strong>Gender</strong></td><td>' . h($l['gender'] ?: '-') . '</td></tr>'
        . '</table>';
    $out .= '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;margin-bottom:14px">'
        . '<thead><tr style="background:#f0f0f5"><th style="text-align:left;padding:7px;font-size:11px;text-transform:uppercase">Subject</th>'
        . ($scope === 'exam' ? '<th style="padding:7px;font-size:11px;text-transform:uppercase">Score</th>' : $examHeaders)
        . '<th style="padding:7px;font-size:11px;text-transform:uppercase">' . ($scope === 'exam' ? 'CBC Level' : 'Average') . '</th>'
        . ($scope !== 'exam' ? '<th style="padding:7px;font-size:10px;text-transform:uppercase">Level</th>' : '')
        . '<th style="padding:7px;font-size:11px;text-transform:uppercase">Subject Teacher</th></tr></thead>'
        . '<tbody>' . $subjectRows . '</tbody></table></div>';
    $out .= '<div class="ps-net" style="margin-bottom:6px">'
        . '<div>' . ($scope === 'exam' ? 'Total &amp; Average: ' . round($overallAvg) . '%' : 'Overall Average: ' . round($overallAvg) . '% &nbsp;·&nbsp; ' . count($examIds) . ' exam(s) included') . '</div>'
        . '<div><span class="badge lvl-' . $overallLevel['group'] . '" style="background:rgba(255,255,255,.25);color:#fff">' . $overallLevel['code'] . ' — ' . $overallLevel['label'] . '</span></div>'
        . '</div>' . $rankInfo;
    $out .= report_footer_block($settings, $classId, $pdo, $footerText);
    return $out;
}

function build_class_summary_html($pdo, $settings, $scope, $classId, $examIds, $scopeLabel, $schoolName, $footerText) {
    if (!count($examIds)) return '';
    $csubj = class_subjects($pdo, $classId);
    $rows = (count($examIds) === 1 && $scope === 'exam') ? rank_class($pdo, $examIds[0], $classId) : rank_class_aggregate($pdo, $classId, $examIds);
    $subjHeaders = '';
    foreach ($csubj as $s) $subjHeaders .= '<th style="padding:6px;font-size:9px;text-transform:uppercase;text-align:center">' . h($s['code']) . '</th>';
    $bodyRows = '';
    foreach ($rows as $r) {
        $subjCells = '';
        foreach ($csubj as $s) {
            $scores = [];
            foreach ($examIds as $eid) {
                $stM = $pdo->prepare("SELECT score FROM marks WHERE exam_id=? AND learner_id=? AND subject_code=?");
                $stM->execute([$eid, $r['learner_id'], $s['code']]);
                $m = $stM->fetch(PDO::FETCH_ASSOC);
                if ($m) $scores[] = $m['score'];
            }
            $avg = count($scores) ? round(array_sum($scores) / count($scores)) : null;
            $subjCells .= '<td style="text-align:center;padding:5px">' . ($avg !== null ? $avg : '-') . '</td>';
        }
        $avgVal = $r['average'] ?? 0;
        $lvl = cbc_level($avgVal);
        $bodyRows .= '<tr><td style="padding:5px;text-align:center">' . $r['position'] . '</td><td style="padding:5px">' . h($r['name']) . '<div class="staff-id">' . h($r['adm_no']) . '</div></td>'
            . $subjCells . '<td style="text-align:center;font-weight:700;padding:5px">' . round($avgVal) . '%</td>'
            . '<td style="text-align:center;padding:5px"><span class="badge lvl-' . $lvl['group'] . '">' . $lvl['code'] . '</span></td></tr>';
    }
    $out = report_header_html($settings, $schoolName);
    $out .= '<div style="text-align:center;font-family:Syne,sans-serif;font-weight:800;font-size:16px;margin-bottom:4px;letter-spacing:.5px">CLASS EXAM REPORT</div>';
    $out .= '<div style="text-align:center;font-size:12px;color:#555;margin-bottom:18px">' . h(class_label($pdo, $classId)) . ' · ' . h($scopeLabel) . '</div>';
    $out .= '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;margin-bottom:14px">'
        . '<thead><tr style="background:#f0f0f5"><th style="padding:6px;font-size:10px">Pos</th><th style="text-align:left;padding:6px;font-size:10px">Learner</th>' . $subjHeaders
        . '<th style="padding:6px;font-size:10px">Average</th><th style="padding:6px;font-size:10px">Level</th></tr></thead><tbody>' . $bodyRows . '</tbody></table></div>';
    $out .= report_footer_block($settings, $classId, $pdo, $footerText);
    return $out;
}

$reportHtml = '';
if ($mode && $classId) {
    $examIds = scope_exam_ids($pdo, $scope, $classId, $examId, $term, $year);
    $sLabel = scope_label($pdo, $scope, $classId, $examId, $term, $year);
    if ($mode === 'preview' && $learnerId) {
        $reportHtml = build_learner_report_html($pdo, $settings, $scope, $classId, $examIds, $sLabel, $learnerId, $includeRank, $schoolNameOverride, $footerText);
    } elseif ($mode === 'class_summary') {
        $reportHtml = build_class_summary_html($pdo, $settings, $scope, $classId, $examIds, $sLabel, $schoolNameOverride, $footerText);
    } elseif ($mode === 'class_all') {
        $parts = [];
        foreach ($classLearnersAll as $l) {
            $parts[] = '<div style="page-break-after:always;padding:10px 0">' . build_learner_report_html($pdo, $settings, $scope, $classId, $examIds, $sLabel, $l['id'], $includeRank, $schoolNameOverride, $footerText) . '</div>';
        }
        $reportHtml = implode('', $parts);
    }
}

if ($mode && $reportHtml) {
    // Standalone printable output
    ?><!DOCTYPE html><html><head><meta charset="UTF-8"><title>Report</title><link rel="stylesheet" href="assets/style.css"></head>
    <body style="padding:20px">
    <div class="no-print" style="margin-bottom:14px"><button class="btn btn-primary" onclick="window.print()">&#128424; Print / Save as PDF</button> <a class="btn btn-ghost" href="javascript:history.back()">&#8592; Back</a></div>
    <?= $reportHtml ?>
    </body></html><?php
    exit;
}

$pageTitle = 'Report Forms'; $activeNav = 'reports';
require __DIR__ . '/includes/layout_top.php';
?>
<h1 style="font-size:20px;margin-bottom:18px">Generate Report Form</h1>

<div class="card">
  <div class="card-body">
    <form method="get" id="rfForm">
      <div class="frow">
        <div class="fg">
          <label class="fl">Report Covers</label>
          <select class="fs" name="scope" id="rfScope" onchange="this.form.submit()">
            <option value="exam" <?= $scope === 'exam' ? 'selected' : '' ?>>Single Exam</option>
            <option value="term" <?= $scope === 'term' ? 'selected' : '' ?>>Whole Term (all exams that term)</option>
            <option value="year" <?= $scope === 'year' ? 'selected' : '' ?>>Whole Year (all exams that year)</option>
          </select>
        </div>
        <div class="fg">
          <label class="fl">Class</label>
          <select class="fs" name="class_id" onchange="this.form.submit()">
            <option value="">Select class...</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $classId == $c['id'] ? 'selected' : '' ?>><?= h(class_label($pdo, $c['id'])) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php if ($scope === 'exam'): ?>
      <div class="fg">
        <label class="fl">Exam</label>
        <select class="fs" name="exam_id">
          <option value="">Select exam...</option>
          <?php foreach ($classExams as $ex): ?>
            <option value="<?= $ex['id'] ?>" <?= $examId == $ex['id'] ? 'selected' : '' ?>><?= h($ex['name']) ?> (<?= h($ex['term']) ?> <?= h($ex['year']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php elseif ($scope === 'term'): ?>
      <div class="frow">
        <div class="fg"><label class="fl">Term</label>
          <select class="fs" name="term">
            <?php foreach (['Term 1','Term 2','Term 3'] as $t): ?><option value="<?= $t ?>" <?= $term === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fg"><label class="fl">Year</label><input class="fi" name="year" type="number" value="<?= h($year) ?>"></div>
      </div>
      <?php else: ?>
      <div class="fg"><label class="fl">Year</label><input class="fi" name="year" type="number" value="<?= h($year) ?>"></div>
      <?php endif; ?>

      <div class="fg">
        <label class="fl">Learner</label>
        <select class="fs" name="learner_id">
          <option value="">Select learner...</option>
          <?php foreach ($classLearnersAll as $l): ?>
            <option value="<?= h($l['id']) ?>" <?= $learnerId === $l['id'] ? 'selected' : '' ?>><?= h($l['first_name'] . ' ' . $l['last_name']) ?> (<?= h($l['id']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="frow">
        <div class="fg"><label class="fl">School Name (shown on report)</label><input class="fi" name="school_name" value="<?= h($schoolNameOverride) ?>"></div>
        <div class="fg"><label class="fl">Footer Text</label><input class="fi" name="footer" value="<?= h($footerText) ?>"></div>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:14px;cursor:pointer">
        <input type="checkbox" name="rank" value="1" <?= $includeRank ? 'checked' : '' ?>> Include class ranking / position on report form
      </label>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-primary btn-sm" type="submit" name="mode" value="preview">&#128065; Preview Report Form</button>
        <button class="btn btn-ghost btn-sm" type="submit" name="mode" value="class_all">&#128424; Generate Report Forms — Whole Class</button>
        <button class="btn btn-ghost btn-sm" type="submit" name="mode" value="class_summary">&#128203; Class Exam Report (Summary)</button>
      </div>
    </form>
  </div>
</div>

<?php if ($classId):
    $examIdsForRank = scope_exam_ids($pdo, $scope, $classId, $examId, $term, $year);
?>
<div class="card">
  <div class="card-header">&#128202; Class Performance — Ranked</div>
  <div class="card-body" style="overflow-x:auto">
    <?php if (!count($examIdsForRank)): ?>
      <div class="empty-state"><div class="icon">&#128202;</div><p>Select an exam or a period with recorded exams above to view class ranking.</p></div>
    <?php else:
      $rows = (count($examIdsForRank) === 1 && $scope === 'exam') ? rank_class($pdo, $examIdsForRank[0], $classId) : rank_class_aggregate($pdo, $classId, $examIdsForRank);
    ?>
      <?php if (!count($rows)): ?>
        <div class="empty-state"><p>No learners in this class.</p></div>
      <?php else: ?>
      <table>
        <thead><tr><th>Pos</th><th>Learner</th><th>Average</th><th>Level</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td><?= $r['position'] ?></td><td><?= h($r['name']) ?> <span class="staff-id"><?= h($r['adm_no']) ?></span></td><td><?= round($r['average']) ?>%</td><td><span class="badge lvl-<?= $r['level']['group'] ?>"><?= $r['level']['code'] ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
