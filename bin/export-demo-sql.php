<?php
declare(strict_types=1);

/**
 * خروجی SQL از داده‌ی نمونه (ساخته‌شده با bin/seed-demo.php) برای ایمپورت در دیتابیس دیگر (phpMyAdmin) — فقط CLI:
 *   php bin/export-demo-sql.php > demo-data.sql
 * فایل خروجی مستقل از شناسه‌هاست: کاربران با شماره موبایل پیدا می‌شوند (INSERT IGNORE: کاربر موجود دست نمی‌خورد و رمزش همان می‌ماند؛
 * کاربر ناموجود با رمز Demo12345 ساخته می‌شود)، و پروژه/لیبل/تسک با LAST_INSERT_ID به هم وصل می‌شوند.
 * اول داده‌ی نمونه‌ی قبلی (پروژه‌های [داده‌ی نمونه] و کاربران 0999100xxxx) پاک می‌شود. دیتابیس مقصد باید ساختار نسخه‌ی 5.0 به بعد (ستون pid) را داشته باشد؛ شناسه‌ی عمومی تازه برای هر ردیف ساخته می‌شود.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\DB;
use App\Core\Pid;

const MARK = '[داده‌ی نمونه]';
$pdo = DB::pdo();
$q = static fn($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v));

$pids = array_map('intval', DB::column('SELECT id FROM projects WHERE description LIKE ? ORDER BY id', ['%' . MARK . '%']));
if (!$pids) {
    fwrite(STDERR, "داده‌ی نمونه‌ای نیست؛ اول php bin/seed-demo.php --yes را اجرا کنید.\n");
    exit(1);
}
$in = DB::in($pids);
$projects = DB::all("SELECT * FROM projects WHERE id IN ($in) ORDER BY id", $pids);
$members = DB::all("SELECT * FROM project_members WHERE project_id IN ($in) ORDER BY id", $pids);
$tasks = DB::all("SELECT * FROM tasks WHERE project_id IN ($in) ORDER BY id", $pids);
$tids = array_map(static fn($t) => (int) $t['id'], $tasks);
$tin = DB::in($tids);
$events = [];
foreach (DB::all("SELECT * FROM task_events WHERE task_id IN ($tin) ORDER BY id", $tids) as $e) {
    $events[(int) $e['task_id']][] = $e;
}
$notes = [];
foreach (DB::all("SELECT * FROM task_notes WHERE task_id IN ($tin) ORDER BY id", $tids) as $n) {
    $notes[(int) $n['task_id']][] = $n;
}
$sids = array_values(array_unique(array_filter(array_map(static fn($t) => (int) $t['subject_id'], $tasks))));
$subjects = $sids ? DB::all('SELECT * FROM subjects WHERE id IN (' . DB::in($sids) . ') ORDER BY id', $sids) : [];

// همه‌ی کاربرانی که در داده دیده می‌شوند
$uids = [];
foreach ($projects as $p) {
    $uids[] = (int) $p['user_id'];
}
foreach ($members as $m) {
    $uids[] = (int) $m['user_id'];
}
foreach ($tasks as $t) {
    foreach (['user_id', 'assignee_id', 'blocked_by', 'delivered_by', 'approved_by'] as $c) {
        if ($t[$c]) {
            $uids[] = (int) $t[$c];
        }
    }
}
foreach ($events as $list) {
    foreach ($list as $e) {
        if ($e['user_id']) {
            $uids[] = (int) $e['user_id'];
        }
    }
}
foreach ($notes as $list) {
    foreach ($list as $n) {
        $uids[] = (int) $n['user_id'];
    }
}
foreach ($subjects as $s) {
    $uids[] = (int) $s['user_id'];
}
$uids = array_values(array_unique($uids));
$users = DB::all('SELECT id, mobile, first_name, last_name, created_at FROM users WHERE id IN (' . DB::in($uids) . ') ORDER BY id', $uids);
$uv = static fn($id) => $id === null || $id === '' || (int) $id === 0 ? 'NULL' : '@u' . (int) $id;
$pass = Auth::hash('Demo12345');

$o = fopen('php://stdout', 'w');
$w = static fn(string $s) => fwrite($o, $s . "\n");
$w('-- ============================================================================');
$w('--  داده‌ی نمونه‌ی کارلیکس: شرکت ' . count($users) . ' نفره، ' . count($tasks) . ' تسک در ' . count($projects) . ' پروژه (ساخته‌شده ' . date('Y-m-d H:i') . ')');
$w('--  ایمپورت: phpMyAdmin ← دیتابیس برنامه ← Import (charset: utf8mb4). ساختار دیتابیس باید نسخه‌ی 5.0 به بعد باشد (ستون pid).');
$w('--  کاربران با موبایل پیدا می‌شوند؛ کاربر موجود تغییر نمی‌کند، کاربر ناموجود با رمز Demo12345 ساخته می‌شود.');
$w('--  حذف: پروژه‌هایی که توضیحشان «' . MARK . '» دارد (با تسک‌ها) و کاربران 0999100xxxx — یا php bin/seed-demo.php --clean --yes');
$w('-- ============================================================================');
$w('SET NAMES utf8mb4;');
$w("SET time_zone = '" . date('P') . "';");
$w('START TRANSACTION;');
$w('');
$w('-- پاک کردن داده‌ی نمونه‌ی قبلی');
$w("DELETE t FROM tasks t JOIN projects p ON p.id = t.project_id WHERE p.description LIKE " . $q('%' . MARK . '%') . ';');
$w("DELETE FROM projects WHERE description LIKE " . $q('%' . MARK . '%') . ';');
$w("DELETE FROM users WHERE mobile LIKE '0999100%';");
$w('');
$w('-- کاربران');
foreach ($users as $u) {
    $w('INSERT IGNORE INTO users (pid, mobile, first_name, last_name, password_hash, role, status, tour, created_at) VALUES ('
        . $q(Pid::new()) . ', ' . $q($u['mobile']) . ', ' . $q($u['first_name']) . ', ' . $q($u['last_name']) . ', ' . $q($pass) . ", 'user', 'active', 'done', " . $q($u['created_at']) . ');');
    $w('SET @u' . (int) $u['id'] . ' = (SELECT id FROM users WHERE mobile = ' . $q($u['mobile']) . ');');
}
$w('');
$w('-- همکاری‌ها (دوطرفه = تأییدشده)');
$pairs = DB::all('SELECT user_id, colleague_user_id FROM colleagues WHERE user_id IN (' . DB::in($uids) . ') AND colleague_user_id IN (' . DB::in($uids) . ')', array_merge($uids, $uids));
foreach (array_chunk($pairs, 200) as $chunk) {
    $w('INSERT IGNORE INTO colleagues (pid, user_id, colleague_user_id) VALUES ' . implode(', ', array_map(static fn($c) => '(' . $q(Pid::new()) . ', ' . $uv($c['user_id']) . ', ' . $uv($c['colleague_user_id']) . ')', $chunk)) . ';');
}
$w('');
$w('-- پروژه‌ها و اعضا');
foreach ($projects as $p) {
    $w('INSERT INTO projects (pid, user_id, title, description, max_members, priority_type, status, created_at) VALUES (' . $q(Pid::new()) . ', ' . $uv($p['user_id']) . ', ' . $q($p['title']) . ', ' . $q($p['description'])
        . ', ' . (int) $p['max_members'] . ', ' . $q($p['priority_type']) . ', 1, ' . $q($p['created_at']) . ');');
    $w('SET @p' . (int) $p['id'] . ' = LAST_INSERT_ID();');
}
foreach ($members as $m) {
    $w('INSERT IGNORE INTO project_members (pid, project_id, user_id, permissions, task_permissions, board_permissions, board_view_users, added_at) VALUES (' . $q(Pid::new()) . ', @p' . (int) $m['project_id'] . ', ' . $uv($m['user_id'])
        . ', ' . $q($m['permissions']) . ', ' . $q($m['task_permissions']) . ', ' . $q($m['board_permissions']) . ', ' . $q($m['board_view_users']) . ', ' . $q($m['added_at']) . ');');
}
$w('');
$w('-- لیبل‌ها (اگر صاحب لیبل از قبل همین عنوان را دارد، همان استفاده می‌شود)');
foreach ($subjects as $s) {
    $w('INSERT INTO subjects (pid, user_id, title, color) SELECT ' . $q(Pid::new()) . ', ' . $uv($s['user_id']) . ', ' . $q($s['title']) . ', ' . $q($s['color'])
        . ' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM subjects WHERE user_id = ' . $uv($s['user_id']) . ' AND title = ' . $q($s['title']) . ');');
    $w('SET @s' . (int) $s['id'] . ' = (SELECT id FROM subjects WHERE user_id = ' . $uv($s['user_id']) . ' AND title = ' . $q($s['title']) . ' ORDER BY id LIMIT 1);');
}
$w('');
$w('-- تسک‌ها + تاریخچه + یادداشت‌ها');
$cols = ['user_id', 'assignee_id', 'subject_id', 'project_id', 'title', 'description', 'priority', 'priority_percent', 'is_completed', 'is_quick', 'board_status', 'blocked_reason', 'board_moved_at',
    'archived_at', 'blocked_at', 'blocked_by', 'due_date', 'due_time', 'notified_at', 'share_token', 'created_at', 'completed_at', 'delivered_at', 'delivered_by', 'approved_by', 'first_delivered_at', 'rework_count'];
foreach ($tasks as $t) {
    $vals = [];
    foreach ($cols as $c) {
        $v = $t[$c];
        if (in_array($c, ['user_id', 'assignee_id', 'blocked_by', 'delivered_by', 'approved_by'], true)) {
            $vals[] = $uv($v);
        } elseif ($c === 'project_id') {
            $vals[] = '@p' . (int) $v;
        } elseif ($c === 'subject_id') {
            $vals[] = $v ? '@s' . (int) $v : 'NULL';
        } elseif ($c === 'share_token') {
            $vals[] = $q(bin2hex(random_bytes(16)));                       // توکن تازه (یکتا در مقصد)
        } elseif (in_array($c, ['priority_percent', 'is_completed', 'is_quick'], true)) {
            $vals[] = $v === null ? 'NULL' : (string) (int) $v;
        } else {
            $vals[] = $q($v);
        }
    }
    $w('INSERT INTO tasks (pid, ' . implode(', ', $cols) . ') VALUES (' . $q(Pid::new()) . ', ' . implode(', ', $vals) . ');');
    $w('SET @t = LAST_INSERT_ID();');
    $ev = $events[(int) $t['id']] ?? [];
    if ($ev) {
        $w('INSERT INTO task_events (task_id, user_id, type, data, created_at) VALUES ' . implode(', ', array_map(static fn($e) => '(@t, ' . $uv($e['user_id']) . ', ' . $q($e['type']) . ', ' . $q($e['data']) . ', ' . $q($e['created_at']) . ')', $ev)) . ';');
    }
    $nt = $notes[(int) $t['id']] ?? [];
    if ($nt) {
        $w('INSERT INTO task_notes (pid, task_id, user_id, note, created_at) VALUES ' . implode(', ', array_map(static fn($n) => '(' . $q(Pid::new()) . ', @t, ' . $uv($n['user_id']) . ', ' . $q($n['note']) . ', ' . $q($n['created_at']) . ')', $nt)) . ';');
    }
}
$w('');
$w('COMMIT;');
fwrite(STDERR, 'خروجی: ' . count($users) . ' کاربر، ' . count($projects) . ' پروژه، ' . count($tasks) . ' تسک، ' . array_sum(array_map('count', $events)) . ' رویداد، ' . array_sum(array_map('count', $notes)) . " یادداشت.\n");
