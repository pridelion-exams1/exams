<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function current_user($pdo) {
    if (empty($_SESSION['uid'])) return null;
    $st = $pdo->prepare("SELECT id,username,name,role FROM users WHERE id=?");
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    return $u ?: null;
}

function require_login($pdo) {
    $u = current_user($pdo);
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

function is_admin($user) {
    return $user && $user['role'] === 'Administrator';
}

/** Both roles can enter marks into blank cells */
function can_enter_marks($user) {
    return (bool)$user;
}

/** Only Administrator can edit marks that are already saved, or edit/delete exams, classes, subjects, teachers, settings */
function can_edit_saved($user) {
    return is_admin($user);
}

function require_admin($user) {
    if (!is_admin($user)) {
        flash('error', 'Only an Administrator can do that.');
        header('Location: index.php');
        exit;
    }
}
