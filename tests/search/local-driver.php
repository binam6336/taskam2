<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require BASE_PATH . '/laravel/bootstrap/app.php';
use KarlixLaravel\Database\DB;
use KarlixLaravel\Services\TaskHistory;
use App\Core\Pid;
if (!str_ends_with((string) env('DB_NAME'), '_test')) { exit(1); }
$args = json_decode($argv[1] ?? '{}', true);
if (($args['action'] ?? '') === 'fixture') {
    $users = []; $password = 'Test' . bin2hex(random_bytes(6)) . '!';
    foreach (['مالك سنجه', 'بیگانه سنجه'] as $name) {
        $mobile = '099' . random_int(10000000,99999999);
        $id = DB::insert('users', ['mobile' => $mobile, 'first_name' => $name, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'status' => 'active', 'tour' => 'done']);
        $users[] = ['pid' => pid('users', $id), 'mobile' => $mobile, 'password' => $password];
    }
    $uid = Pid::id('users', $users[0]['pid']);
    $task = DB::insert('tasks', ['user_id' => $uid, 'assignee_id' => $uid, 'title' => 'سنجهواجهة تاریخچه', 'description' => str_repeat("شرح طولانی تسک\n", 160)]);
    TaskHistory::log($task, $uid, 'title', ['from' => 'عنوان قبلی سنجه', 'to' => 'سنجهواجهة تاریخچه']);
    TaskHistory::log($task, $uid, 'confidential_off', ['count' => 0]);
    $token = bin2hex(random_bytes(24));
    DB::insert('api_tokens', ['user_id' => $uid, 'name' => 'جستجوی آزمایشی', 'token_hash' => hash('sha256', $token), 'token_prefix' => substr($token,0,8), 'scope' => 'read']);
    echo json_encode(['users' => $users, 'task' => pid('tasks', $task), 'token' => $token, 'testDatabase' => env('DB_NAME')]);
} elseif (($args['action'] ?? '') === 'cleanup') {
    foreach (($args['users'] ?? []) as $user) { $uid = Pid::id('users', $user['pid']); if ($uid) { DB::run('DELETE FROM users WHERE id = ?', [$uid]); } }
    echo '{}';
}
