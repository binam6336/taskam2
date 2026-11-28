<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require BASE_PATH . '/laravel/bootstrap/app.php';
use KarlixLaravel\Database\DB;
use App\Core\Pid;
if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
$input = json_decode($argv[1] ?? '{}', true);
if (($input['action'] ?? '') === 'fixture') {
    $users = []; $password = 'Brand' . bin2hex(random_bytes(6)) . '!';
    foreach ([false, true] as $admin) {
        $mobile = '099' . random_int(10000000, 99999999);
        $id = DB::insert('users', ['mobile' => $mobile, 'first_name' => 'آزمون برند', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'status' => 'active', 'tour' => 'done', 'role' => $admin ? 'admin' : 'user', 'admin_role' => $admin ? 'super' : null]);
        $users[] = ['pid' => pid('users', $id), 'mobile' => $mobile, 'password' => $password];
    }
    echo json_encode(['users' => $users, 'testDatabase' => env('DB_NAME')]);
} elseif (($input['action'] ?? '') === 'cleanup') {
    foreach (($input['users'] ?? []) as $user) { $id = Pid::id('users', $user['pid']); if ($id) DB::run('DELETE FROM users WHERE id=?', [$id]); }
    echo '{}';
}
