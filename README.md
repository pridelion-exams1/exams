# Pridelion School Management System

A complete, portable, database-backed school management system built with **PHP + SQLite**.

## Why SQLite?
The entire database lives in **one file**: `data/pridelion.sqlite`. That makes it genuinely
portable — to back up, copy that one file. To move the whole system to another server, copy
the whole folder. No separate database server to configure.

## Requirements
- PHP 7.4 or later (PHP 8.x recommended) with the **pdo_sqlite** extension enabled.
  Virtually all shared hosting (cPanel etc.) has this by default.
- No MySQL/database setup needed — the database and tables are created automatically
  the first time the site is opened.

## Uploading to your subdomain
1. Upload the **entire contents of this folder** (not the zip itself) into your subdomain's
   document root — the same folder your host showed you in Subdomain Management
   (e.g. `public_html/yoursubdomain`).
2. Make sure the `data/` folder is writable by the web server (most hosts default to this;
   if you get a database error, set that folder's permissions to 755 or 775 via File Manager).
3. Visit `https://yoursubdomain.yourdomain.com/` — the database and default accounts are
   created automatically on first load, and you'll land on the login page.

## Default logins
| Username | Password  | Role                | Rights |
|----------|-----------|---------------------|--------|
| admin    | pride2026 | Administrator       | Full access |
| hoi      | hoi2026   | Limited Access      | Can enter marks/data, cannot edit saved marks or manage exams/classes/subjects/teachers/settings |

**Change both passwords after your first login** (there's no in-app password-change screen yet —
ask me to add one, or an admin can update the `users` table directly via phpMyAdmin/DB tools
that read SQLite, such as "DB Browser for SQLite").

## What's included in this version
- Login with two roles (Administrator / Limited Access "Hoi")
- Learners: admission, edit, search/filter, status (Active/Transferred/Graduated),
  admission date can't be in the future
- Classes & Streams: class teacher (one class per teacher, enforced), optional fee override,
  per-class subject-teacher assignment
- Subjects: master list (teacher assignment happens per class)
- Teachers: full teaching-load listing (which subjects, in which classes)
- Exams: specific-class or whole-school creation, edit/delete (whole-school batches can be
  deleted for one class or all), duplicate-exam prevention
- Marks Entry: only shows subjects actually taught in that class, only Active learners,
  numbers only, "Enter Marks" → "Edit Marks" (Admin-only once saved) workflow, Enter-key
  navigation between cells, class switcher for whole-school exam batches
- Report Forms: single exam / whole term / whole year scope, class ranking, editable
  school name & footer per report, auto-filled Class Teacher & Head of Institution,
  per-subject teacher shown, "whole class" batch printing, and a separate Class Exam
  Report summary sheet
- Settings: school name, motto, address/phone/email, Head of Institution name, default
  term fee, current term/year, streaming mode, default report footer

## Not yet ported from the earlier single-file version
These existed in the offline HTML version and can be added next if you'd like them:
fee/payment tracking, admission/transfer letters, dashboard charts, CSV/JSON import-export,
activity log detail page. Just ask and I'll add them the same way.

## Backing up your data
Regularly download a copy of `data/pridelion.sqlite` via File Manager/FTP. That single file
is your entire school's data — learners, marks, classes, everything.
