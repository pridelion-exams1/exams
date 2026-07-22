<?php
/** CBC Grades in order */
function cbc_grades() {
    return ['Play Group','PP1','PP2','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6','Grade 7','Grade 8','Grade 9'];
}

/** CBC 8-band grading (EE1–BE2) from a 0-99 average */
function cbc_level($avg) {
    $avg = (float)$avg;
    if ($avg >= 90) return ['code' => 'EE1', 'label' => 'Exceeding Expectation', 'group' => 'ee'];
    if ($avg >= 75) return ['code' => 'EE2', 'label' => 'Exceeding Expectation', 'group' => 'ee'];
    if ($avg >= 58) return ['code' => 'ME1', 'label' => 'Meeting Expectation', 'group' => 'me'];
    if ($avg >= 41) return ['code' => 'ME2', 'label' => 'Meeting Expectation', 'group' => 'me'];
    if ($avg >= 31) return ['code' => 'AE1', 'label' => 'Approaching Expectation', 'group' => 'ae'];
    if ($avg >= 21) return ['code' => 'AE2', 'label' => 'Approaching Expectation', 'group' => 'ae'];
    if ($avg >= 11) return ['code' => 'BE1', 'label' => 'Below Expectation', 'group' => 'be'];
    return ['code' => 'BE2', 'label' => 'Below Expectation', 'group' => 'be'];
}

function class_label($pdo, $classId) {
    if (!$classId) return '-';
    $st = $pdo->prepare("SELECT grade,stream FROM classes WHERE id=?");
    $st->execute([$classId]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c) return '-';
    $mode = get_setting($pdo, 'streaming_mode');
    return $mode === 'single' ? $c['grade'] : ($c['grade'] . ' ' . $c['stream']);
}

function get_setting($pdo, $key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach ($pdo->query("SELECT key,value FROM settings") as $row) $cache[$row['key']] = $row['value'];
    }
    return isset($cache[$key]) ? $cache[$key] : $default;
}

function all_settings($pdo) {
    $out = [];
    foreach ($pdo->query("SELECT key,value FROM settings") as $row) $out[$row['key']] = $row['value'];
    return $out;
}

function teacher_name($pdo, $id) {
    if (!$id) return '';
    $st = $pdo->prepare("SELECT name FROM teachers WHERE id=?");
    $st->execute([$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? $r['name'] : '';
}

/** Subjects actually taught in a class = subjects with a teacher assigned for that class */
function class_subjects($pdo, $classId) {
    $st = $pdo->prepare("
        SELECT s.code, s.name, cst.teacher_id
        FROM class_subject_teachers cst
        JOIN subjects s ON s.code = cst.subject_code
        WHERE cst.class_id = ?
        ORDER BY s.name
    ");
    $st->execute([$classId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function subject_teacher_for($pdo, $classId, $code) {
    $st = $pdo->prepare("SELECT teacher_id FROM class_subject_teachers WHERE class_id=? AND subject_code=?");
    $st->execute([$classId, $code]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? $r['teacher_id'] : null;
}

/** Learners in a class eligible for marks entry (Active status only) */
function marks_eligible_learners($pdo, $classId) {
    $st = $pdo->prepare("SELECT * FROM learners WHERE class_id=? AND status='Active' ORDER BY first_name,last_name");
    $st->execute([$classId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function class_learners($pdo, $classId) {
    $st = $pdo->prepare("SELECT * FROM learners WHERE class_id=? ORDER BY first_name,last_name");
    $st->execute([$classId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Compute total/average/level for one learner in one exam, over that class's taught subjects only */
function calc_learner_exam($pdo, $examId, $learnerId, $classId) {
    $subs = class_subjects($pdo, $classId);
    if (!count($subs)) return ['total' => 0, 'average' => 0, 'level' => cbc_level(0), 'marks' => []];
    $st = $pdo->prepare("SELECT subject_code,score FROM marks WHERE exam_id=? AND learner_id=?");
    $st->execute([$examId, $learnerId]);
    $marks = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $marks[$r['subject_code']] = $r['score'];
    $total = 0;
    foreach ($subs as $s) $total += (int)($marks[$s['code']] ?? 0);
    $avg = $total / count($subs);
    return ['total' => $total, 'average' => $avg, 'level' => cbc_level($avg), 'marks' => $marks];
}

/** Rank a class for one exam (learners with average, sorted, tied ranks share position) */
function rank_class($pdo, $examId, $classId) {
    $learners = marks_eligible_learners($pdo, $classId);
    $rows = [];
    foreach ($learners as $l) {
        $calc = calc_learner_exam($pdo, $examId, $l['id'], $classId);
        $rows[] = ['learner_id' => $l['id'], 'name' => $l['first_name'] . ' ' . $l['last_name'],
                   'adm_no' => $l['id'], 'total' => $calc['total'], 'average' => $calc['average'], 'level' => $calc['level']];
    }
    usort($rows, function ($a, $b) { return $b['average'] <=> $a['average']; });
    $pos = 0; $prev = null;
    foreach ($rows as $i => &$r) {
        if ($r['average'] !== $prev) { $pos = $i + 1; $prev = $r['average']; }
        $r['position'] = $pos;
    }
    return $rows;
}

/** Rank a class across several exams (term/year aggregate) */
function rank_class_aggregate($pdo, $classId, $examIds) {
    $learners = marks_eligible_learners($pdo, $classId);
    $rows = [];
    foreach ($learners as $l) {
        $sum = 0; $count = 0;
        foreach ($examIds as $eid) {
            $calc = calc_learner_exam($pdo, $eid, $l['id'], $classId);
            $st = $pdo->prepare("SELECT COUNT(*) c FROM marks WHERE exam_id=? AND learner_id=?");
            $st->execute([$eid, $l['id']]);
            if ($st->fetch(PDO::FETCH_ASSOC)['c'] > 0) { $sum += $calc['average']; $count++; }
        }
        $avg = $count ? $sum / $count : 0;
        $rows[] = ['learner_id' => $l['id'], 'name' => $l['first_name'] . ' ' . $l['last_name'],
                   'adm_no' => $l['id'], 'average' => $avg, 'level' => cbc_level($avg), 'exam_count' => $count];
    }
    usort($rows, function ($a, $b) { return $b['average'] <=> $a['average']; });
    $pos = 0; $prev = null;
    foreach ($rows as $i => &$r) {
        if ($r['average'] !== $prev) { $pos = $i + 1; $prev = $r['average']; }
        $r['position'] = $pos;
    }
    return $rows;
}

function log_activity($pdo, $msg) {
    $st = $pdo->prepare("INSERT INTO activity_log (message,created_at) VALUES (?,?)");
    $st->execute([$msg, date('c')]);
}

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES); }

function flash($type, $msg) {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function show_flash() {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        echo '<div class="flash flash-' . h($f['type']) . '">' . h($f['msg']) . '</div>';
        unset($_SESSION['flash']);
    }
}
