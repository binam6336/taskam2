<?php
declare(strict_types=1);

// دیتابیس توسعه‌ی پیکربندی‌شده؛ داده‌های آزمون در پایان کاملاً rollback می‌شوند.
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\DB;
use App\Core\Auth;
use App\Services\BlockService;
use App\Services\GlobalSearch;
use App\Services\LabelService;
use App\Models\TextModel;

$check = static function (bool $ok, string $label): void {
    if (!$ok) {
        throw new RuntimeException($label);
    }
    echo "PASS $label\n";
};
$pdo = DB::pdo();
$pdo->beginTransaction();
try {
    $q = 'Regression' . bin2hex(random_bytes(5));
    $users = [];
    foreach (['owner', 'manager', 'member', 'outsider'] as $name) {
        $users[$name] = DB::insert('users', ['mobile' => '09' . random_int(100000000, 999999999), 'first_name' => $name, 'last_name' => '', 'password_hash' => Auth::hash($q), 'status' => 'active']);
    }
    [$owner, $manager, $member, $outsider] = array_values($users);
    $project = DB::insert('projects', ['user_id' => $owner, 'title' => $q]);
    foreach ([$manager, $member] as $uid) {
        DB::insert('project_members', ['project_id' => $project, 'user_id' => $uid, 'permissions' => json_encode($uid === $manager ? ['create_label'] : [])]);
    }
    $label = DB::insert('subjects', ['user_id' => $owner, 'project_id' => $project, 'title' => $q]);
    $ids = [];
    foreach ([0, 1] as $done) {
        for ($i = 0; $i < GlobalSearch::PER_GROUP + 1; $i++) {
            $ids[] = DB::insert('tasks', ['user_id' => $owner, 'project_id' => $project, 'subject_id' => $label, 'title' => $q . ' ' . $i, 'is_completed' => $done, 'board_status' => $done ? 'done' : 'todo']);
        }
    }
    $hidden = DB::insert('tasks', ['user_id' => $owner, 'project_id' => $project, 'title' => $q . ' secret', 'is_confidential' => 1]);
    $blocked = DB::insert('tasks', ['user_id' => $owner, 'project_id' => $project, 'subject_id' => $label, 'title' => $q . ' blocked', 'blocked_at' => date('Y-m-d H:i:s')]);
    $note = TextModel::create($member, $q, 'Selected note');
    TextModel::create($outsider, $q, 'Private note');
    $result = GlobalSearch::run($member, $q);
    $groups = array_column($result['groups'], null, 'key');
    $check(count($groups['tasks']['items']) === GlobalSearch::PER_GROUP && count($groups['tasks_done']['items']) === GlobalSearch::PER_GROUP, 'open and completed tasks have independent result limits');
    $check(!in_array(true, array_column($groups['tasks']['items'], 'done'), true) && !in_array(false, array_column($groups['tasks_done']['items'], 'done'), true), 'completed tasks only appear in their own group');
    $found = array_column(array_merge($groups['tasks']['items'], $groups['tasks_done']['items']), 'id');
    $check(!in_array(pid('tasks', $hidden), $found, true) && !in_array(pid('tasks', $blocked), $found, true), 'confidential and blocked tasks remain hidden');
    $check(count($groups['notes']['items']) === 1 && $groups['notes']['items'][0]['url'] === url('/notes?focus=' . $note['id']), 'note search links to the selected owned note');
    $check(TextModel::owned($note['id'], $outsider) === null, 'another user cannot read the selected note');
    $check(!isset(BlockService::KINDS['label']) && BlockService::block('label', $owner, $label)['ok'] === false, 'label blocking is unavailable in the service');
    $before = DB::all('SELECT id, title, is_completed, board_status, blocked_at FROM tasks WHERE project_id = ? ORDER BY id', [$project]);
    $check(LabelService::delete($member, $label)['http'] === 403, 'member without label permission cannot delete');
    $check(LabelService::delete($outsider, $label)['http'] === 404, 'outsider cannot delete or discover the label');
    $check((int) DB::value('SELECT COUNT(*) FROM tasks WHERE subject_id = ?', [$label]) === count($ids) + 1, 'denied deletion preserves label associations including blocked tasks');
    $check(LabelService::delete($manager, $label)['ok'], 'member with create_label can delete');
    $check(DB::value('SELECT id FROM subjects WHERE id = ?', [$label]) === null && (int) DB::value('SELECT COUNT(*) FROM tasks WHERE subject_id = ?', [$label]) === 0, 'label and all associations are removed');
    $check(DB::all('SELECT id, title, is_completed, board_status, blocked_at FROM tasks WHERE project_id = ? ORDER BY id', [$project]) === $before, 'task records and completion states are preserved');
    $ownLabel = DB::insert('subjects', ['user_id' => $owner, 'project_id' => $project, 'title' => $q . ' owner']);
    $check(LabelService::delete($owner, $ownLabel)['ok'], 'project creator can delete');
    $legacy = DB::insert('subjects', ['user_id' => $member, 'title' => $q . ' legacy']);
    $check(LabelService::delete($owner, $legacy)['http'] === 404 && LabelService::delete($member, $legacy)['ok'], 'legacy personal label can only be deleted by its owner');
} finally {
    $pdo->rollBack();
}
