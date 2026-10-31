<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\DB;
use App\Models\TaskModel;
use App\Services\Access;
use App\Services\TaskService;

final class TaskApi extends ApiController
{
    public static function format(array $t): array
    {
        return [
            'id' => (int) $t['id'], 'title' => $t['title'], 'description' => $t['description'], 'priority' => $t['priority'], 'priority_percent' => isset($t['priority_percent']) ? (int) $t['priority_percent'] : null,
            'is_completed' => (bool) $t['is_completed'], 'due_date' => $t['due_date'], 'due_time' => $t['due_time'] ? substr((string) $t['due_time'], 0, 5) : null,
            'created_at' => $t['created_at'], 'completed_at' => $t['completed_at'],
            'user_id' => (int) $t['user_id'], 'assignee_id' => $t['assignee_id'] !== null ? (int) $t['assignee_id'] : null,
            'subject_id' => $t['subject_id'] !== null ? (int) $t['subject_id'] : null, 'project_id' => $t['project_id'] !== null ? (int) $t['project_id'] : null,
            'project_title' => $t['project_title'] ?? null, 'subject_title' => $t['subject_title'] ?? null, 'subject_color' => $t['subject_color'] ?? null,
            'board_status' => !empty($t['archived_at']) ? 'archived' : ($t['board_status'] ?? null),
            'assignee_first_name' => $t['assignee_first_name'] ?? null, 'assignee_last_name' => $t['assignee_last_name'] ?? null, 'assignee_mobile' => mask_mobile($t['assignee_mobile'] ?? null),
            'creator_first_name' => $t['creator_first_name'] ?? null, 'creator_last_name' => $t['creator_last_name'] ?? null, 'creator_mobile' => mask_mobile($t['creator_mobile'] ?? null),
            'notes_count' => (int) ($t['notes_count'] ?? 0), 'attachments_count' => (int) ($t['attachments_count'] ?? 0),
        ];
    }

    public function list()
    {
        $status = in_array($this->request->query['status'] ?? 'all', ['all', 'pending', 'completed'], true) ? $this->request->query['status'] ?? 'all' : 'all';
        [$limit, $offset] = $this->paging();
        $rows = array_map([self::class, 'format'], TaskModel::apiList($this->uid, $status, $limit, $offset));
        $this->ok(['count' => count($rows), 'limit' => $limit, 'offset' => $offset, 'status' => $status, 'tasks' => $rows]);
    }

    public function show()
    {
        $id = $this->id();
        $t = TaskModel::find($id, $this->uid);
        if ($t === null || !Access::canTask($t, $this->uid, 'view')) {
            $this->err('تسک یافت نشد.', 404, 'TASK_NOT_FOUND');
        }
        $out = self::format($t);
        $out['notes'] = DB::all('SELECT tn.id, tn.user_id, tn.note, tn.created_at, u.first_name, u.last_name, u.mobile FROM task_notes tn LEFT JOIN users u ON u.id = tn.user_id WHERE tn.task_id = ? ORDER BY tn.created_at DESC, tn.id DESC', [$id]);
        $this->ok($out);
    }

    public function stats()
    {
        $this->ok(TaskModel::stats($this->uid));
    }

    public function create()
    {
        $this->fromService(TaskService::create($this->uid, $this->body()), fn($t) => self::format(TaskModel::find((int) $t['id'], $this->uid) ?? $t));
    }

    public function update()
    {
        $in = $this->body();
        unset($in['id']);
        $id = $this->id();
        $this->fromService(TaskService::update($this->uid, $id, $in), fn($t) => self::format(TaskModel::find((int) $t['id'], $this->uid) ?? $t));
    }

    public function delete()
    {
        $this->fromService(TaskService::delete($this->uid, $this->id()));
    }

    public function toggle()
    {
        $this->fromService(TaskService::toggle($this->uid, $this->id()), static fn($d) => ['id' => $d['id'], 'is_completed' => $d['is_completed']]);
    }
}
