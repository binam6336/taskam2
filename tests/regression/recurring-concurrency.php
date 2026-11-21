<?php
declare(strict_types=1);

// نیازمند دیتابیس توسعه و proc_open؛ فقط داده‌های ساخته‌شده‌ی همین آزمون باقی‌مانده‌زدایی می‌شوند.
require dirname(__DIR__, 2) . '/app/bootstrap.php';
use App\Core\Auth;
use App\Core\DB;
use App\Services\BoardService;
use App\Services\TaskRecurrence;
use App\Services\TaskService;

if (($argv[1] ?? '') === '--worker') {
    $id = (int) $argv[2];
    if (($argv[3] ?? '') === 'approve') {
        $owner = (int) DB::value('SELECT user_id FROM tasks WHERE id = ?', [$id]);
        $r = BoardService::move($owner, $id, 'done', '', true);
        if (!$r['ok']) throw new RuntimeException($r['message']);
    } else {
        TaskRecurrence::generate($id);
    }
    exit;
}
$uid = 0;
$series = [];
try {
    $uid = DB::insert('users', ['mobile' => '09' . random_int(100000000, 999999999), 'first_name' => 'Recurring concurrency', 'last_name' => '', 'password_hash' => Auth::hash(bin2hex(random_bytes(12))), 'status' => 'active']);
    $project = DB::insert('projects', ['user_id' => $uid, 'title' => 'Recurring concurrency']);
    foreach (['approve', 'recover'] as $mode) {
        $r = TaskService::create($uid, ['project_id' => $project, 'title' => 'Concurrent recurring task', 'due_date' => '2032-01-31', 'repeat_enabled' => 1, 'repeat_kind' => 'monthly']);
        if (!$r['ok']) throw new RuntimeException($r['message']);
        $id = (int) $r['data']['id'];
        $series[] = (int) $r['data']['recurrence_id'];
        DB::update('tasks', ['board_status' => $mode === 'approve' ? 'review' : 'done', 'is_completed' => $mode === 'approve' ? 0 : 1, 'completed_at' => $mode === 'approve' ? null : date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $command = [PHP_BINARY, '-n', '-d', 'extension_dir=' . ini_get('extension_dir')];
        foreach (['mbstring', 'pdo', 'mysqlnd', 'pdo_mysql', 'fileinfo', 'ctype', 'tokenizer'] as $ext) {
            if (is_file(ini_get('extension_dir') . '/' . $ext . '.so')) array_push($command, '-d', 'extension=' . $ext);
        }
        array_push($command, __FILE__, '--worker', (string) $id, $mode);
        $workers = [];
        for ($i = 0; $i < 12; ++$i) {
            $pipes = [];
            $proc = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($proc)) throw new RuntimeException('Could not start worker');
            fclose($pipes[0]);
            $workers[] = [$proc, $pipes];
        }
        $errors = [];
        foreach ($workers as [$proc, $pipes]) {
            $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            if (proc_close($proc) !== 0) $errors[] = $out;
        }
        if ($errors) throw new RuntimeException('Workers failed: ' . implode(' | ', $errors));
        if ((int) DB::value('SELECT COUNT(*) FROM tasks WHERE recurrence_previous_id = ?', [$id]) !== 1) throw new RuntimeException('Expected exactly one successor');
        if (DB::value('SELECT due_date FROM tasks WHERE recurrence_previous_id = ?', [$id]) !== '2032-02-29') throw new RuntimeException('Incorrect successor date');
        echo "PASS 12 concurrent $mode workers created exactly one occurrence\n";
    }
} finally {
    foreach ($series as $sid) DB::run('DELETE FROM task_recurrences WHERE id = ?', [$sid]);
    if ($uid) {
        DB::run('DELETE FROM rate_limits WHERE `key` = ?', [hash('sha256', (string) $uid)]);
        DB::run('DELETE FROM users WHERE id = ?', [$uid]);
    }
}
