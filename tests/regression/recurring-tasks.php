<?php
declare(strict_types=1);

// اجرای مستقل روی دیتابیس توسعه؛ همه‌ی داده‌های این آزمون rollback می‌شوند.
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\DB;
use App\Core\Throttle;
use App\Models\TaskModel;
use App\Services\TaskRecurrence;
use App\Services\TaskService;
use App\Services\BoardService;

$check = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
};
$dates = [
    ['2032-01-31', 'daily', 0, 31, '2032-01-31', '2032-02-01'],
    ['2032-01-31', 'weekly', 0, 31, '2032-01-31', '2032-02-07'],
    ['2032-01-31', 'custom', 3, 31, '2032-01-31', '2032-02-03'],
    ['2032-01-31', 'monthly', 0, 31, '2032-01-31', '2032-02-29'],
    ['2031-01-31', 'monthly', 0, 31, '2031-01-31', '2031-02-28'],
    ['2032-02-29', 'monthly', 0, 31, '2032-02-29', '2032-03-31'],
    ['2032-12-31', 'monthly', 0, 31, '2032-12-31', '2033-01-31'],
    ['2032-01-31', 'weekly', 0, 31, '2032-02-20', '2032-02-21'],
    ['2032-01-31', 'custom', 4, 31, '2032-02-20', '2032-02-24'],
    ['2032-01-31', 'monthly', 0, 31, '2032-05-15', '2032-05-31'],
    ['2032-02-30', 'monthly', 0, 30, '2032-02-01', null],
    ['2032-01-31', 'custom', 0, 31, '2032-01-31', null],
    ['9999-12-31', 'daily', 0, 31, '9999-12-31', null],
];
foreach ($dates as $i => $case) {
    $expected = array_pop($case);
    $check(TaskRecurrence::nextDate(...$case) === $expected, 'calendar case ' . ($i + 1));
}
foreach ([null, 0, -1, '1.5', 'abc', true, [], '999999999999999999999'] as $bad) {
    $check(is_string(TaskRecurrence::validate(['repeat_enabled' => 1, 'repeat_kind' => 'custom', 'repeat_days' => $bad], null, '2032-01-31')), 'invalid custom interval rejected: ' . json_encode($bad));
}
$check(TaskRecurrence::validate(['repeat_enabled' => 1, 'repeat_kind' => 'custom', 'repeat_days' => '۰۰۰۳'], null, '2032-01-31')['interval_days'] === 3, 'Persian digits and leading zeros normalize correctly');
$check(is_string(TaskRecurrence::validate(['repeat_enabled' => 1], null, null)), 'recurrence requires first date');
$check(is_string(TaskRecurrence::validate(['repeat_enabled' => 1, 'repeat_kind' => 'invalid'], null, '2032-01-31')), 'unknown interval rejected');
$check(TaskRecurrence::validate(['repeat_enabled' => 0], null, null)['enabled'] === 0, 'ordinary undated task remains valid');

$pdo = DB::pdo();
$pdo->beginTransaction();
try {
    $uid = DB::insert('users', ['mobile' => '09' . random_int(100000000, 999999999), 'first_name' => 'Recurring', 'last_name' => 'test', 'password_hash' => Auth::hash(bin2hex(random_bytes(12))), 'status' => 'active']);
    $member = DB::insert('users', ['mobile' => '09' . random_int(100000000, 999999999), 'first_name' => 'Member', 'last_name' => '', 'password_hash' => Auth::hash(bin2hex(random_bytes(12))), 'status' => 'active']);
    $project = DB::insert('projects', ['user_id' => $uid, 'title' => 'Recurring regression']);
    DB::insert('project_members', ['project_id' => $project, 'user_id' => $member, 'permissions' => '[]', 'task_permissions' => '[]']);
    $create = static function (array $in = []) use ($uid, $member, $project): array {
        return TaskService::create($uid, $in + ['title' => 'Recurring regression', 'description' => 'Preserve template', 'project_id' => $project, 'assignee_id' => $member, 'due_date' => '2032-01-31', 'due_time' => '09:45', 'repeat_enabled' => 1, 'repeat_kind' => 'daily']);
    };
    $finish = static function (int $id) use ($uid, $member, $check): void {
        $r = BoardService::deliver($member, $id);
        $check($r['ok'] && !TaskModel::raw($id)['is_completed'], 'delivery is not final completion');
        $check(DB::value('SELECT id FROM tasks WHERE recurrence_previous_id = ?', [$id]) === null, 'no successor before final approval');
        $r = BoardService::move($uid, $id, 'done', '', true);
        $check($r['ok'], 'final approval succeeds');
    };
    foreach (['daily' => '2032-02-01', 'weekly' => '2032-02-07', 'monthly' => '2032-02-29', 'custom' => '2032-02-03'] as $kind => $date) {
        $r = $create(['repeat_kind' => $kind, 'repeat_days' => 3]);
        $check($r['ok'], $kind . ' creation succeeds');
        $id = (int) $r['data']['id'];
        $check($r['data']['due_date'] === '2032-01-31', 'first occurrence keeps chosen date');
        $check(TaskRecurrence::generate($id) === null, 'open task never creates another occurrence');
        $finish($id);
        $child = DB::one('SELECT * FROM tasks WHERE recurrence_previous_id = ?', [$id]);
        $check($child !== null && $child['due_date'] === $date && $child['due_time'] === '09:45:00', $kind . ' successor has correct date and time');
        $check(!$child['is_completed'] && empty($child['completed_at']) && $child['description'] === 'Preserve template', 'successor starts fresh and keeps task information');
        $check(TaskModel::raw($id)['is_completed'] == 1 && (int) DB::value('SELECT COUNT(*) FROM task_events WHERE task_id = ?', [$id]) > 0, 'previous occurrence and history preserved');
        $check(TaskRecurrence::generate($id) === null, 'repeated generation is idempotent');
        if ($kind === 'monthly') {
            $check(TaskService::update($uid, $id, ['repeat_enabled' => 0])['ok'] && TaskService::update($uid, $id, ['repeat_enabled' => 1])['ok'], 'monthly series can pause and resume without losing anchor');
            $finish((int) $child['id']);
            $check(DB::value('SELECT due_date FROM tasks WHERE recurrence_previous_id = ?', [(int) $child['id']]) === '2032-03-31', 'monthly anchor survives leap February');
        }
    }
    $id = (int) $create()['data']['id'];
    $check(!TaskService::update($member, $id, ['repeat_kind' => 'weekly'])['ok'], 'unauthorized recurrence edit rejected');
    $check(TaskService::update($uid, $id, ['repeat_kind' => 'custom', 'repeat_days' => 5])['ok'], 'interval can be edited');
    $check(!TaskService::update($uid, $id, ['due_date' => ''])['ok'], 'cannot silently remove date of enabled series');
    $check(!TaskService::update($uid, $id, ['due_date' => '2032-02-30'])['ok'], 'invalid date rejected');
    $finish($id);
    $child = DB::one('SELECT * FROM tasks WHERE recurrence_previous_id = ?', [$id]);
    $check($child['due_date'] === '2032-02-05', 'edited interval used for next occurrence');
    $check(TaskService::update($uid, $id, ['repeat_enabled' => 0])['ok'], 'old occurrence can disable the whole series');
    $finish((int) $child['id']);
    $check(DB::value('SELECT id FROM tasks WHERE recurrence_previous_id = ?', [(int) $child['id']]) === null, 'disabled series creates no successor');
    $check(TaskService::update($uid, $id, ['repeat_enabled' => 1, 'repeat_kind' => 'weekly'])['ok'], 'completed paused series can be re-enabled');
    $check(DB::value('SELECT due_date FROM tasks WHERE recurrence_previous_id = ?', [(int) $child['id']]) === '2032-02-12', 're-enable continues latest occurrence without changing history');
    $id = (int) $create(['due_date' => '2020-01-01'])['data']['id'];
    $check(TaskRecurrence::generate($id) === null, 'overdue but incomplete task creates nothing');
    $finish($id);
    $check(DB::value('SELECT due_date FROM tasks WHERE recurrence_previous_id = ?', [$id]) > date('Y-m-d'), 'late completion skips missed dates and creates a single future occurrence');
    $id = (int) $create(['repeat_enabled' => 0, 'due_date' => ''])['data']['id'];
    $finish($id);
    $check(empty(TaskModel::raw($id)['recurrence_id']) && TaskRecurrence::generate($id) === null, 'ordinary task completion creates no recurrence');
    $editor = DB::insert('users', ['mobile' => '09' . random_int(100000000, 999999999), 'first_name' => 'Editor', 'last_name' => '', 'password_hash' => Auth::hash(bin2hex(random_bytes(12))), 'status' => 'active']);
    DB::insert('project_members', ['project_id' => $project, 'user_id' => $editor, 'task_permissions' => '["edit_due"]']);
    $root = (int) $create(['assignee_id' => $editor])['data']['id'];
    $check(BoardService::deliver($uid, $root)['ok'], 'approver may complete directly through the existing flow');
    $child = (int) DB::value('SELECT id FROM tasks WHERE recurrence_previous_id = ?', [$root]);
    $project2 = DB::insert('projects', ['user_id' => $uid, 'title' => 'Other project']);
    $check(TaskService::update($uid, $child, ['project_id' => $project2, 'assignee_id' => $uid])['ok'], 'current occurrence can move through normal permission checks');
    $check(TaskService::update($editor, $root, ['repeat_enabled' => 0])['code'] === 'RECURRENCE_FORBIDDEN' && TaskRecurrence::present(TaskModel::raw($root))['enabled'], 'permission on historical task does not grant control of inaccessible current occurrence');
    $paused = (int) $create()['data']['id'];
    DB::update('tasks', ['is_completed' => 1, 'board_status' => 'done', 'completed_at' => date('Y-m-d H:i:s')], 'id = ?', [$paused]);
    DB::update('projects', ['blocked_at' => date('Y-m-d H:i:s')], 'id = ?', [$project]);
    $check(TaskRecurrence::generate($paused) === null, 'blocked project pauses generation');
    DB::update('projects', ['blocked_at' => null], 'id = ?', [$project]);
    DB::update('users', ['status' => 'deactivated'], 'id = ?', [$uid]);
    $check(TaskRecurrence::generate($paused) === null, 'inactive owner pauses generation');
    DB::update('users', ['status' => 'active'], 'id = ?', [$uid]);
    $check(TaskRecurrence::generate($paused) !== null, 'generation resumes after temporary restrictions clear');
    $legacy = TaskModel::create(['user_id' => $uid, 'assignee_id' => $uid, 'title' => 'Legacy personal recurring task', 'due_date' => '2032-01-31']);
    $check(TaskService::update($uid, $legacy, ['repeat_enabled' => 1])['ok'] && TaskService::toggle($uid, $legacy)['ok'], 'legacy personal task supports recurrence without changing its completion flow');
    $check(DB::value('SELECT due_date FROM tasks WHERE recurrence_previous_id = ?', [$legacy]) === '2032-02-01', 'legacy personal completion creates its next occurrence');
    Throttle::clear('create_task', (string) $uid);
} finally {
    $pdo->rollBack();
}
