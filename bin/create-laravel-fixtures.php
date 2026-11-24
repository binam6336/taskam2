<?php
declare(strict_types=1);
/** فقط دیتابیس آزمایشی؛ اطلاعات ورود/توکن در فایل خصوصی و خارج از webroot می‌ماند. */
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$database = (string) env('DB_NAME');
if (!str_ends_with($database, '_test')) { fwrite(STDERR, "فقط دیتابیس با پسوند _test مجاز است.\n"); exit(1); }
require BASE_PATH . '/laravel/bootstrap/app.php';
$users = [];
for ($i = 0; $i < 2; $i++) {
    $mobile = '099' . random_int(10000000, 99999999);
    $password = bin2hex(random_bytes(12)) . '!A7';
    $id = KarlixLaravel\Database\DB::insert('users', ['mobile' => $mobile, 'first_name' => 'مهاجرت', 'last_name' => 'آزمایشی', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'status' => 'active', 'tour' => 'done']);
    $write = KarlixLaravel\Repositories\TokenModel::create($id, 'test-write', null, 'write');
    $read = KarlixLaravel\Repositories\TokenModel::create($id, 'test-read', null, 'read');
    $users[] = ['testDatabase' => $database, 'id' => $id, 'mobile' => $mobile, 'password' => $password, 'pid' => pid('users', $id), 'token' => $write['token'], 'read' => $read['token']];
}
umask(0077);
$path = STORAGE_PATH . '/cache/laravel-test-fixtures.json';
if (file_put_contents($path, json_encode($users, JSON_THROW_ON_ERROR)) === false) { throw new RuntimeException('Cannot write fixtures'); }
chmod($path, 0600);
echo "فایل خصوصی تست ساخته شد: " . $path . "\n";
