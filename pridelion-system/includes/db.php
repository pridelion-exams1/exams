<?php
/**
 * Pridelion School Management System
 * Database bootstrap — SQLite via PDO.
 * The database is a single portable file: /data/pridelion.sqlite
 * Back it up or move it anywhere just by copying that one file.
 */

$DB_PATH = __DIR__ . '/../data/pridelion.sqlite';
$isNew = !file_exists($DB_PATH);

try {
    $pdo = new PDO('sqlite:' . $DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
} catch (Exception $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()) .
        '<br>Make sure PHP has the <b>pdo_sqlite</b> extension enabled and the /data folder is writable by the web server.');
}

if ($isNew) {
    $pdo->exec("
    CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        name TEXT NOT NULL,
        role TEXT NOT NULL CHECK(role IN ('Administrator','Limited Access'))
    );

    CREATE TABLE settings (
        key TEXT PRIMARY KEY,
        value TEXT
    );

    CREATE TABLE teachers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        phone TEXT
    );

    CREATE TABLE classes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        grade TEXT NOT NULL,
        stream TEXT NOT NULL,
        class_teacher_id INTEGER REFERENCES teachers(id) ON DELETE SET NULL,
        fee REAL,
        UNIQUE(grade, stream)
    );

    CREATE TABLE subjects (
        code TEXT PRIMARY KEY,
        name TEXT NOT NULL
    );

    CREATE TABLE class_subject_teachers (
        class_id INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
        subject_code TEXT NOT NULL REFERENCES subjects(code) ON DELETE CASCADE,
        teacher_id INTEGER NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
        PRIMARY KEY(class_id, subject_code)
    );

    CREATE TABLE learners (
        id TEXT PRIMARY KEY,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        admission_type TEXT DEFAULT 'New',
        previous_school TEXT,
        gender TEXT,
        dob TEXT,
        class_id INTEGER REFERENCES classes(id) ON DELETE SET NULL,
        admission_date TEXT,
        status TEXT DEFAULT 'Active' CHECK(status IN ('Active','Transferred','Graduated')),
        guardian_name TEXT,
        guardian_phone TEXT,
        guardian_email TEXT,
        address TEXT,
        release_json TEXT,
        created_at TEXT
    );

    CREATE TABLE exams (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        term TEXT NOT NULL,
        year INTEGER NOT NULL,
        class_id INTEGER NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
        exam_group TEXT
    );

    CREATE TABLE marks (
        exam_id INTEGER NOT NULL REFERENCES exams(id) ON DELETE CASCADE,
        learner_id TEXT NOT NULL REFERENCES learners(id) ON DELETE CASCADE,
        subject_code TEXT NOT NULL,
        score INTEGER,
        PRIMARY KEY(exam_id, learner_id, subject_code)
    );

    CREATE TABLE activity_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        message TEXT NOT NULL,
        created_at TEXT NOT NULL
    );
    ");

    // Seed the two default users (passwords hashed — change these after first login)
    $ins = $pdo->prepare("INSERT INTO users (username,password_hash,name,role) VALUES (?,?,?,?)");
    $ins->execute(['admin', password_hash('pride2026', PASSWORD_DEFAULT), 'Admin', 'Administrator']);
    $ins->execute(['hoi', password_hash('hoi2026', PASSWORD_DEFAULT), 'Hoi', 'Limited Access']);

    // Seed default settings
    $defaults = [
        'school_name' => 'Pridelion Education Network',
        'motto' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
        'head_name' => '',
        'logo' => '',
        'term_fee' => '15000',
        'term' => 'Term 1',
        'year' => date('Y'),
        'streaming_mode' => 'multi',
        'report_footer' => '',
    ];
    $insS = $pdo->prepare("INSERT INTO settings (key,value) VALUES (?,?)");
    foreach ($defaults as $k => $v) $insS->execute([$k, $v]);

    // Seed CBC subjects (common set — editable afterwards)
    $subs = [
        ['ENG','English'], ['KIS','Kiswahili'], ['MAT','Mathematics'],
        ['SCI','Science and Technology'], ['SST','Social Studies'],
        ['CRE','Christian Religious Education'], ['CA','Creative Arts'],
        ['AGR','Agriculture'], ['PTL','Pre-Technical Studies'],
        ['HSC','Home Science'], ['PE','Physical Education'],
    ];
    $insSub = $pdo->prepare("INSERT INTO subjects (code,name) VALUES (?,?)");
    foreach ($subs as $s) $insSub->execute($s);
}
