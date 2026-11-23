<?php
declare(strict_types=1);

/** تست یکپارچه‌ی فضای پروژه؛ فقط دیتابیس آزمایشی با پسوند _test. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Core\Uploader;
use App\Services\Access;
use App\Services\StorageQuota as S;

if (!str_ends_with((string) env('DB_NAME'), '_test')) { fwrite(STDERR, "فقط دیتابیس آزمایشی با پسوند _test.\n"); exit(1); }
if (($argv[1] ?? '') === '--worker') {
    $uid = (int) ($argv[2] ?? 0); $pid = (int) ($argv[4] ?? 0);
    $name = basename((string) ($argv[3] ?? ''));
    if ($uid <= 0 || $pid <= 0 || !preg_match('/^quota_race_[a-f0-9]+\.txt$/', $name)) { exit(2); }
    DB::pdo()->beginTransaction();
    DB::value('SELECT COUNT(*) FROM storage_files WHERE project_id = ?', [$pid]);
    file_put_contents(Uploader::dir('task_attachments') . '/' . $name, str_repeat('x', 700000));
    $error = Uploader::account($uid, 'task_attachments', $name, $pid);
    DB::pdo()->commit();
    echo $error === null ? 'accepted' : 'denied'; exit;
}
$files = []; $users = [];
$check = static function (bool $ok, string $name): void {
    if (!$ok) { throw new RuntimeException('FAIL ' . $name); }
    echo 'PASS ' . $name . PHP_EOL;
};
try {
    foreach (['user', 'user', 'admin'] as $role) {
        $users[] = DB::insert('users', ['mobile' => '099' . random_int(10000000, 99999999), 'first_name' => 'تست', 'last_name' => 'فضا', 'password_hash' => 'test-only', 'status' => 'active', 'role' => $role]);
    }
    [$owner, $member, $admin] = $users;
    $a = DB::insert('projects', ['user_id' => $owner, 'title' => 'پروژه الف']);
    $b = DB::insert('projects', ['user_id' => $owner, 'title' => 'پروژه ب']);
    DB::insert('project_members', ['project_id' => $a, 'user_id' => $member, 'permissions' => '[]']);
    $make = static function (string $type, int $bytes) use (&$files): string {
        $name = 'quota_test_' . bin2hex(random_bytes(8)) . '.txt';
        file_put_contents(Uploader::dir($type) . '/' . $name, str_repeat('x', $bytes));
        $files[] = [$type, $name]; return $name;
    };
    $check(S::status($a)['unlimited'] && S::used($a) === 0, 'پروژه جدید نامحدود و بدون مصرف');
    $check(Access::canViewStorage($a, $owner) && Access::canViewStorage($a, $admin) && !Access::canViewStorage($a, $member), 'دسترسی سازنده، ادمین و منع عضو عادی');
    $check(!in_array(pid('projects', $a), array_column(S::visible($member), 'pid'), true), 'عدم افشای آمار در پاسخ عضو بدون مجوز');
    DB::run('UPDATE project_members SET permissions = ? WHERE project_id = ? AND user_id = ?', ['["view_storage"]', $a, $member]);
    $check(Access::canViewStorage($a, $member) && in_array(pid('projects', $a), array_column(S::visible($member), 'pid'), true), 'مجوز قابل اعطا در تنظیمات پروژه');
    S::setLimit($a, 1); S::setLimit($b, 1);
    $first = $make('task_attachments', 600000);
    $check(Uploader::account($owner, 'task_attachments', $first, $a) === null, 'آپلود سازنده زیر سقف');
    $over = $make('note_attachments', 600000);
    $check(Uploader::account($member, 'note_attachments', $over, $a) !== null && Uploader::path('note_attachments', $over) === null, 'سقف مشترک پروژه برای دو کاربر و دو نوع پیوست');
    $other = $make('task_attachments', 600000);
    $check(Uploader::account($owner, 'task_attachments', $other, $b) === null, 'استقلال سقف دو پروژه یک سازنده');
    $exact = $make('projects', S::MB - 600000);
    $check(Uploader::account($member, 'projects', $exact, $a) === null && S::used($a) === S::MB, 'رسیدن دقیق به سقف با تصویر پروژه');
    $one = $make('task_attachments', 1);
    $check(Uploader::account($member, 'task_attachments', $one, $a) !== null, 'رد یک بایت اضافه');
    $personal = $make('chat_files', 100);
    $check(Uploader::account($owner, 'chat_files', $personal) === null && S::used($a) === S::MB, 'فایل شخصی خارج از سهمیه پروژه');
    Uploader::delete('task_attachments', $first);
    $check(S::used($a) === S::MB - 600000, 'آزادشدن فضای همان پروژه');
    S::setLimit($a, 0);
    $zero = $make('task_attachments', 1);
    $check(Uploader::account($owner, 'task_attachments', $zero, $a) !== null, 'سقف صفر');
    S::setLimit($a, null);
    $big = $make('note_attachments', 2 * S::MB);
    $check(Uploader::account($member, 'note_attachments', $big, $a) === null, 'فضای نامحدود');
    // انتقال تسک همراه با پیوست تسک و پیوست یادداشت.
    $t = DB::insert('tasks', ['user_id' => $owner, 'assignee_id' => $owner, 'project_id' => $a, 'title' => 'انتقال']);
    $n = DB::insert('task_notes', ['task_id' => $t, 'user_id' => $owner, 'note' => 'تست']);
    $moveFile = $make('task_attachments', 100);
    $noteFile = $make('note_attachments', 100);
    Uploader::account($owner, 'task_attachments', $moveFile, $a);
    Uploader::account($member, 'note_attachments', $noteFile, $a);
    DB::insert('task_attachments', ['task_id' => $t, 'user_id' => $owner, 'file_name' => 't.txt', 'file_path' => $moveFile, 'file_size' => 100, 'file_ext' => 'txt', 'file_mime' => 'text/plain']);
    DB::insert('note_attachments', ['note_id' => $n, 'user_id' => $member, 'file_name' => 'n.txt', 'file_path' => $noteFile, 'file_size' => 100, 'file_ext' => 'txt', 'file_mime' => 'text/plain']);
    S::setLimit($b, 0);
    $check(S::moveTask($t, $b, ['project_id' => $b]) !== null && (int) DB::value('SELECT project_id FROM tasks WHERE id = ?', [$t]) === $a, 'رد انتقال به پروژه بدون فضای کافی');
    S::setLimit($b, 1); $before = S::used($a);
    $check(S::moveTask($t, $b, ['project_id' => $b]) === null && S::used($b) === 600200 && S::used($a) === $before - 200, 'انتقال دقیق حجم تسک و یادداشت');
    // حساب فایل با rollback فرم حذف نمی‌شود.
    DB::pdo()->beginTransaction();
    DB::value('SELECT COUNT(*) FROM storage_files WHERE project_id = ?', [$a]);
    $rb = $make('task_attachments', 123);
    $error = Uploader::account($owner, 'task_attachments', $rb, $a);
    DB::pdo()->rollBack();
    $check($error === null && (int) DB::value('SELECT bytes FROM storage_files WHERE file_name = ?', [$rb]) === 123, 'حفظ حساب فایل پس از rollback');
    foreach ($files as [$type, $name]) { Uploader::delete($type, $name); }
    S::setLimit($a, 1);
    $command = [PHP_BINARY, '-n', '-d', 'extension_dir=' . ini_get('extension_dir')];
    foreach (get_loaded_extensions() as $ext) { $ext = strtolower($ext); if (is_file(ini_get('extension_dir') . '/' . $ext . '.so')) { $command[] = '-d'; $command[] = 'extension=' . $ext; } }
    $children = [];
    foreach ([$owner, $member] as $uid) {
        $name = 'quota_race_' . bin2hex(random_bytes(8)) . '.txt'; $files[] = ['task_attachments', $name];
        $proc = proc_open(array_merge($command, [__FILE__, '--worker', (string) $uid, $name, (string) $a]), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) { throw new RuntimeException('اجرای تست هم‌زمان ممکن نشد.'); }
        $children[] = [$proc, $pipes];
    }
    $results = [];
    foreach ($children as [$proc, $pipes]) {
        $results[] = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); if (proc_close($proc) !== 0) { throw new RuntimeException($err); }
    }
    sort($results);
    $check($results === ['accepted', 'denied'] && S::used($a) === 700000, 'دو عضو هم‌زمان روی یک پروژه: فقط یک آپلود پذیرفته می‌شود');
} finally {
    if (DB::pdo()->inTransaction()) { DB::pdo()->rollBack(); }
    foreach ($files as [$type, $name]) { Uploader::delete($type, $name); }
    foreach ($users as $uid) { DB::run('DELETE FROM users WHERE id = ?', [$uid]); }
}
