<?php
declare(strict_types=1);

/** تست یکپارچه‌ی سهمیه؛ فقط روی دیتابیس آزمایشی با نام ختم‌شده به _test اجرا شود. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\DB;
use App\Core\Uploader;
use App\Services\StorageQuota as S;

if (!str_ends_with((string) env('DB_NAME'), '_test')) {
    fwrite(STDERR, "فقط روی دیتابیس آزمایشی با پسوند _test اجرا کنید.\n");
    exit(1);
}
// فرایند مستقل برای سنجش رقابت دو آپلود با snapshot قدیمی.
if (($argv[1] ?? '') === '--worker') {
    $uid = (int) ($argv[2] ?? 0);
    $name = basename((string) ($argv[3] ?? ''));
    if ($uid <= 0 || !preg_match('/^quota_race_[a-f0-9]+\.txt$/', $name)) { exit(2); }
    DB::pdo()->beginTransaction();
    DB::value('SELECT COUNT(*) FROM storage_files WHERE user_id = ?', [$uid]);
    file_put_contents(Uploader::dir('task_attachments') . '/' . $name, str_repeat('x', 700000));
    $error = Uploader::account($uid, 'task_attachments', $name);
    usleep(100000);
    DB::pdo()->commit();
    echo $error === null ? 'accepted' : 'denied';
    exit;
}
$files = [];
$uid = 0;
$check = static function (bool $ok, string $name): void {
    if (!$ok) { throw new RuntimeException('FAIL ' . $name); }
    echo 'PASS ' . $name . PHP_EOL;
};
try {
    $oldDefault = DB::one('SELECT body, updated_by FROM content_texts WHERE `key` = ?', ['storage.quota.default']);
    $uid = DB::insert('users', ['mobile' => '099' . random_int(10000000, 99999999), 'first_name' => 'تست', 'last_name' => 'فضا', 'password_hash' => 'test-only', 'status' => 'active']);
    $make = static function (string $type, int $bytes) use (&$files): string {
        $name = 'quota_test_' . bin2hex(random_bytes(8)) . '.txt';
        file_put_contents(Uploader::dir($type) . '/' . $name, str_repeat('x', $bytes));
        $files[] = [$type, $name];
        return $name;
    };
    S::setDefault(2, $uid);
    $check(S::status($uid)['limit'] === 2 * S::MB && S::used($uid) === 0, 'کاربر بدون فایل و سقف پیش‌فرض');
    S::setUser($uid, 1, true);
    $first = $make('chat_files', 600000);
    $check(Uploader::account($uid, 'chat_files', $first) === null, 'ثبت فایل چت زیر سقف');
    $denied = $make('task_attachments', 600000);
    $check(Uploader::account($uid, 'task_attachments', $denied) !== null && Uploader::path('task_attachments', $denied) === null, 'تجمیع بخش‌ها و حذف فایل ردشده');
    $exact = $make('note_attachments', S::MB - 600000);
    $check(Uploader::account($uid, 'note_attachments', $exact) === null && S::used($uid) === S::MB, 'پذیرش دقیقاً تا سقف');
    $overflow = $make('support_files', 1);
    $check(Uploader::account($uid, 'support_files', $overflow) !== null, 'رد حتی یک بایت اضافه');
    Uploader::delete('chat_files', $first);
    $check(S::used($uid) === S::MB - 600000, 'آزادشدن فضا با حذف فایل');
    S::setUser($uid, 0, true);
    $zero = $make('avatars', 1);
    $check(Uploader::account($uid, 'avatars', $zero) !== null, 'سقف صفر روی تصاویر');
    S::setUser($uid, null, true);
    $big = $make('projects', 2 * S::MB);
    $check(Uploader::account($uid, 'projects', $big) === null && S::status($uid)['unlimited'], 'سقف اختصاصی نامحدود');
    S::setUser($uid, null, false);
    $check(S::status($uid)['limit'] === 2 * S::MB, 'بازگشت به پیش‌فرض');
    S::setDefault(null, $uid);
    $check(S::status($uid)['unlimited'], 'اعمال زنده‌ی پیش‌فرض جدید');
    // فایل قبلی بدون دفتر مصرف، از تصویر پروفایل وارد می‌شود.
    $legacy = $make('avatars', 1234);
    DB::update('users', ['avatar' => $legacy], 'id = ?', [$uid]);
    S::backfill();
    $before = S::used($uid);
    S::backfill();
    $check(S::used($uid) === $before && DB::value('SELECT bytes FROM storage_files WHERE directory = ? AND file_name = ?', ['avatars', $legacy]) == 1234, 'ورود فایل قبلی بدون دوباره‌شماری');
    unlink(Uploader::path('avatars', $legacy));
    $check(S::used($uid) === $before - 1234, 'حذف فایل گمشده از مصرف');
    $check(S::fits(0, 1, null) && !S::fits(0, 1, 0) && S::fits(2, 3, 5) && !S::fits(6, 1, 5), 'مرزها و سقف کاهش‌یافته');
    foreach ($files as [$type, $name]) { Uploader::delete($type, $name); }
    S::setUser($uid, 1, true);
    DB::pdo()->beginTransaction();
    DB::value('SELECT COUNT(*) FROM storage_files WHERE user_id = ?', [$uid]);
    $rollback = $make('chat_files', 100);
    $error = Uploader::account($uid, 'chat_files', $rollback);
    DB::pdo()->rollBack();
    $check($error === null && S::used($uid) === 100, 'حفظ حساب فایل روی دیسک پس از rollback فرم');
    Uploader::delete('chat_files', $rollback);
    $command = [PHP_BINARY, '-n', '-d', 'extension_dir=' . ini_get('extension_dir')];
    foreach (get_loaded_extensions() as $ext) {
        $ext = strtolower($ext);
        if (is_file(ini_get('extension_dir') . '/' . $ext . '.so')) {
            $command[] = '-d'; $command[] = 'extension=' . $ext;
        }
    }
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        $name = 'quota_race_' . bin2hex(random_bytes(8)) . '.txt';
        $files[] = ['task_attachments', $name];
        $proc = proc_open(array_merge($command, [__FILE__, '--worker', (string) $uid, $name]), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) { throw new RuntimeException('اجرای فرایند تست ممکن نشد.'); }
        $children[] = [$proc, $pipes];
    }
    $results = [];
    foreach ($children as [$proc, $pipes]) {
        $results[] = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $code = proc_close($proc);
        if ($code !== 0) { throw new RuntimeException($err); }
    }
    sort($results);
    $check($results === ['accepted', 'denied'] && S::used($uid) === 700000, 'دو آپلود هم‌زمان با snapshot قدیمی: فقط یکی مجاز');

} finally {
    if ($uid > 0) { DB::run('DELETE FROM users WHERE id = ?', [$uid]); }
    if ($oldDefault) { DB::run('UPDATE content_texts SET body = ?, updated_by = ? WHERE `key` = ?', [$oldDefault['body'], $oldDefault['updated_by'], 'storage.quota.default']); }
    else { DB::run('DELETE FROM content_texts WHERE `key` = ?', ['storage.quota.default']); }
    foreach ($files as [$type, $name]) {
        $path = Uploader::path($type, $name);
        if ($path !== null) { unlink($path); }
    }
}
