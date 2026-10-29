<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Validate;
use App\Models\ProjectModel;
use App\Models\SubjectModel;
use App\Models\TaskModel;
use App\Services\TaskService;

/** تقویم تسک‌ها: نمای ماهانه‌ی شمسی بر اساس سررسید، با مشاهده/ویرایش/جابه‌جایی تسک در همان صفحه. */
final class CalendarController extends Controller
{
    public function index()
    {
        $uid = $this->uid();
        $this->view('tasks/calendar', [
            'subjects' => SubjectModel::forUser($uid),
            'projects' => ProjectModel::forUser($uid),
            'assignees' => TaskController::assigneeOptions($uid),
        ], ['title' => 'تقویم', 'page' => 'calendar']);
    }

    /** GET /calendar/data?from=YYYY-MM-DD&to=YYYY-MM-DD&scope=all|mine|others&done=0|1 */
    public function data()
    {
        $uid = $this->uid();
        $q = $this->request->query;
        $from = Validate::date((string) ($q['from'] ?? ''));
        $to = Validate::date((string) ($q['to'] ?? ''));
        if ($from === null || $to === null || $from > $to || (strtotime($to) - strtotime($from)) > 62 * 86400) {
            $this->fail('بازه‌ی تاریخ نامعتبر است.');
        }
        $scope = in_array($q['scope'] ?? '', ['mine', 'others'], true) ? (string) $q['scope'] : 'all';
        $items = [];
        foreach (TaskModel::calendar($uid, $from, $to, $scope, !empty($q['done'])) as $t) {
            $can = TaskService::effective($t, $uid);
            $time = $t['due_time'] ? substr((string) $t['due_time'], 0, 5) : '';
            $done = (int) $t['is_completed'] === 1;
            $items[] = [
                'id' => (int) $t['id'], 'title' => $t['title'], 'date' => $t['due_date'], 'time' => $time, 'done' => $done,
                'overdue' => !$done && strtotime($t['due_date'] . ' ' . ($time ?: '23:59') . ':59') < time(),
                'priority' => $t['priority'], 'percent' => $t['priority_percent'] !== null ? (int) $t['priority_percent'] : null, 'priority_type' => $t['project_priority_type'] ?? 'category',
                'label' => $t['subject_title'], 'label_color' => $t['subject_color'], 'project' => $t['project_title'],
                'assignee' => $t['assignee_id'] ? full_name($t, 'assignee_') : null, 'assignee_avatar' => avatar_url($t['assignee_avatar'] ?? null),
                'mine' => (int) $t['assignee_id'] === $uid,
                'can_move' => $can['edit_due'], 'can_edit' => $can['edit'],
                'edit' => $can['edit'] ? [
                    'title' => $t['title'], 'description' => $t['description'], 'priority' => $t['priority'], 'priority_percent' => $t['priority_percent'] !== null ? (int) $t['priority_percent'] : null,
                    'due_date' => $t['due_date'], 'due_time' => $time, 'subject_id' => $t['subject_id'], 'project_id' => $t['project_id'], 'assignee_id' => $t['assignee_id'], '_id' => (int) $t['id'], '_can' => $can,
                ] : null,
            ];
        }
        $this->ok('', ['from' => $from, 'to' => $to, 'items' => $items]);
    }
}
