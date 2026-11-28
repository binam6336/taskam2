<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/laravel/bootstrap/app.php';
use KarlixLaravel\Database\DB;
use KarlixLaravel\Services\GlobalSearch;
use App\Core\Pid;
if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
function searchCheck(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } echo "PASS $label\n"; }
function searchItems(array $r, string $group): array { foreach ($r['groups'] as $g) { if ($g['key'] === $group) { return $g['items']; } } return []; }
DB::pdo()->beginTransaction();
try {
    $ids = [];
    foreach (['سازنده', 'سنجه همکار', 'بیگانه'] as $name) { $ids[] = DB::insert('users', ['mobile' => '099' . random_int(10000000,99999999), 'first_name' => $name, 'status' => 'active', 'password_hash' => 'fixture']); }
    [$a, $b, $c] = $ids;
    $project = DB::insert('projects', ['user_id' => $a, 'title' => 'سنجه پروژه']);
    DB::insert('project_members', ['project_id' => $project, 'user_id' => $b]);
    $task = static fn(array $d) => DB::insert('tasks', $d + ['user_id' => $a, 'project_id' => $project, 'title' => 'سنجه تسک', 'description' => 'متن رمزگذاری‌شده']);
    $visible = $task([]); $note = DB::insert('task_notes', ['task_id' => $visible, 'user_id' => $a, 'note' => 'سنجه گزارش']);
    DB::insert('tasks', ['user_id' => $a, 'project_id' => $project, 'title' => 'سنجه انجام‌شده', 'is_completed' => 1]);
    DB::insert('texts', ['id' => bin2hex(random_bytes(8)), 'user_id' => $a, 'title' => 'سنجه یادداشت', 'content' => 'متن']);
    DB::insert('subjects', ['user_id' => $a, 'project_id' => $project, 'title' => 'سنجه لیبل']);
    DB::insert('colleagues', ['user_id' => $a, 'colleague_user_id' => $b]);
    DB::insert('support_tickets', ['user_id' => $a, 'subject' => 'سنجه تیکت']);
    $r = GlobalSearch::run($a, 'سنجه');
    searchCheck(count($r['groups']) === 8, 'تمام هشت گروه با فیلدهای واقعی و رمزگذاری فعال برمی‌گردد');
    $expected = pid('tasks', $visible);
    $member = GlobalSearch::run($b, 'سنجه');
    searchCheck(in_array($expected, array_column(searchItems($member, 'tasks'), 'id'), true), 'عضویت پروژه دسترسی جستجوی تسک و گزارش می‌دهد');
    $hidden = $task(['user_id' => $c, 'is_confidential' => 1, 'title' => 'محرمانهشرکت']);
    DB::insert('task_notes', ['task_id' => $hidden, 'user_id' => $c, 'note' => 'محرمانهشرکت']);
    searchCheck(GlobalSearch::run($a, 'محرمانهشرکت')['total'] === 0, 'تسک محرمانه و گزارش آن حتی از سازنده پروژه پنهان است');
    $blocked = $task(['blocked_at' => date('Y-m-d H:i:s'), 'title' => 'مسدودسنجه']);
    $bp = DB::insert('projects', ['user_id' => $a, 'title' => 'مسدودسنجه', 'blocked_at' => date('Y-m-d H:i:s')]);
    $task(['project_id' => $bp, 'title' => 'مسدودسنجه']);
    searchCheck(GlobalSearch::run($a, 'مسدودسنجه')['total'] === 0, 'مسدودشدن تسک یا پروژه رعایت می‌شود');
    searchCheck(GlobalSearch::run($c, 'سنجه')['total'] === 0, 'کاربر بیگانه نتایج دیگران را نمی‌بیند');
    DB::run('DELETE FROM project_members WHERE project_id = ? AND user_id = ?', [$project, $b]);
    searchCheck(!in_array($expected, array_column(searchItems(GlobalSearch::run($b, 'سنجه'), 'tasks'), 'id'), true), 'لغو عضویت بلافاصله اثر می‌کند؛ نتیجه یا مجوز کش نشده است');
    $mixed = $task(['title' => 'كليد', 'description' => '۱۲۳۴']);
    searchCheck(searchItems(GlobalSearch::run($a, 'کلید 1234'), 'tasks')[0]['id'] === pid('tasks', $mixed), 'چندکلمه در دو فیلد؛ حروف عربی و ارقام فارسی یکسان‌سازی می‌شوند');
    $plain = $task(['title' => 'جستجویقدیمی']);
    DB::run('UPDATE tasks SET title = ? WHERE id = ?', ['جستجویقدیمی', $plain]);
    searchCheck(searchItems(GlobalSearch::run($a, 'جستجویقدیمی'), 'tasks')[0]['id'] === pid('tasks', $plain), 'داده plaintext قدیمی در کنار رمزگذاری‌شده قابل جستجو است');
    $fuzzy = $task(['title' => 'پژوهش']);
    searchCheck(in_array(pid('tasks', $fuzzy), array_column(searchItems(GlobalSearch::run($a, 'پژوهشز'), 'tasks'), 'id'), true), 'غلط تایپی عنوان همچنان پشتیبانی می‌شود');
    for ($i = 0; $i < 16; $i++) { $task(['title' => 'سقفجستجو ' . $i, 'is_completed' => $i % 2]); }
    $caps = GlobalSearch::run($a, 'سقفجستجو');
    searchCheck(count(searchItems($caps, 'tasks')) === 6 && count(searchItems($caps, 'tasks_done')) === 6, 'سقف جداگانه شش نتیجه باز و انجام‌شده حفظ شده است');
    $old = $task(['title' => 'هدفقدیمی']);
    DB::insert('task_notes', ['task_id' => $old, 'user_id' => $a, 'note' => 'هدفقدیمی']);
    $oldText = '00000000_' . bin2hex(random_bytes(4));
    DB::insert('texts', ['id' => $oldText, 'user_id' => $a, 'title' => 'هدفقدیمی', 'content' => '', 'updated_at' => '2020-01-01 00:00:00']);
    for ($i = 0; $i < 140; $i++) {
        $filler = $task(['title' => 'داده عادی ' . $i]);
        DB::insert('task_notes', ['task_id' => $filler, 'user_id' => $a, 'note' => 'گزارش عادی ' . $i]);
        DB::insert('texts', ['id' => 'ffffffff_' . str_pad((string) $i, 6, '0', STR_PAD_LEFT), 'user_id' => $a, 'title' => 'عادی', 'content' => '', 'updated_at' => '2020-01-01 00:00:00']);
    }
    $oldResults = GlobalSearch::run($a, 'هدفقدیمی');
    searchCheck(searchItems($oldResults, 'tasks')[0]['id'] === pid('tasks', $old) && count(searchItems($oldResults, 'reports')) === 1 && searchItems($oldResults, 'notes')[0]['id'] === $oldText, 'پیمایش بیش از یک دسته برای تسک، گزارش و یادداشت با تاریخ‌های برابر');
    $foreign = DB::insert('tasks', ['user_id' => $c, 'title' => 'موقت']);
    DB::run('UPDATE tasks SET title = ? WHERE id = ?', ['data:v1:invalid-ciphertext', $foreign]);
    searchCheck(GlobalSearch::run($a, 'سنجه')['total'] > 0, 'داده رمزگذاری‌شده دیگران حتی برای رمزگشایی خوانده نمی‌شود');
    searchCheck(GlobalSearch::run($a, ' ')['total'] === 0 && GlobalSearch::run($a, 'س')['total'] === 0, 'عبارت خالی یا کوتاه اسکن نمی‌شود');
    $legacy = \App\Services\GlobalSearch::run($a, 'سنجه');
    searchCheck($legacy === GlobalSearch::run($a, 'سنجه'), 'مسیر legacy و Laravel خروجی یکسان دارند');
} finally { DB::pdo()->rollBack(); }
