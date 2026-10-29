<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Uploader;
use App\Core\Validate;
use App\Models\NoteModel;
use App\Models\ProjectModel;
use App\Models\SubjectModel;
use App\Models\TaskModel;
use App\Services\Access;
use App\Services\BoardService;
use App\Services\Priority;
use App\Services\TaskHistory;
use App\Services\TaskService;

final class TaskController extends Controller
{
    private const PER_PAGE = 15;

    public function index()
    {
        $uid = $this->uid();
        $r = $this->request;
        $scope = $r->query['scope'] ?? 'mine';
        $scope = $scope === 'others' ? 'others' : 'mine';
        $done = ($r->query['status'] ?? 'open') === 'done';
        $q = trim((string) ($r->query['q'] ?? ''));
        $page = max(1, (int) ($r->query['page'] ?? 1));
        $sort = (string) ($r->query['sort'] ?? 'created');
        $sort = isset(TaskModel::SORTS[$sort]) ? $sort : 'created';
        $when = (string) ($r->query['when'] ?? '');
        $when = in_array($when, ['overdue', 'today', 'tomorrow'], true) || Validate::date($when) !== null ? $when : '';
        $prio = in_array($r->query['prio'] ?? '', ['high', 'low'], true) ? (string) $r->query['prio'] : '';
        $proj = max(0, (int) ($r->query['project'] ?? 0));
        $filters = ['q' => $q, 'sort' => $sort, 'when' => $when, 'prio' => $prio, 'project' => $proj];
        $res = TaskModel::paginate($uid, $scope, $done, self::PER_PAGE, ($page - 1) * self::PER_PAGE, $filters);
        $pages = max(1, (int) ceil($res['total'] / self::PER_PAGE));
        foreach ($res['rows'] as &$t) {
            $t['can'] = TaskService::effective($t, $uid);
        }
        unset($t);

        $counts = [
            'mine_open'   => (int) DB::value('SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND is_completed = 0', [$uid]),
            'mine_done'   => (int) DB::value('SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND is_completed = 1', [$uid]),
            'others_open' => (int) DB::value('SELECT COUNT(*) FROM tasks WHERE user_id = ? AND (assignee_id IS NULL OR assignee_id <> ?) AND is_completed = 0', [$uid, $uid]),
            'others_done' => (int) DB::value('SELECT COUNT(*) FROM tasks WHERE user_id = ? AND (assignee_id IS NULL OR assignee_id <> ?) AND is_completed = 1', [$uid, $uid]),
        ];

        // نمای اشتراکی: /tasks?task=<token>
        $shared = null;
        $tok = Validate::shareToken((string) ($r->query['task'] ?? ''));
        if ($tok !== null) {
            $t = TaskModel::findByToken($tok, $uid);
            if ($t !== null && TaskService::canViewShared($t, $uid)) {
                $shared = $this->detail($t, $uid);
            } else {
                // خطای دسترسی و بازگشت به تسک‌های خود کاربر
                $this->flash($t === null ? 'لینک تسک نامعتبر است.' : 'شما اجازه‌ی مشاهده‌ی این تسک را ندارید.', 'error');
                $this->redirect('/tasks');
            }
        }

        $this->view('tasks/index', [
            'tasks' => $res['rows'], 'total' => $res['total'], 'page' => $page, 'pages' => $pages,
            'scope' => $scope, 'done' => $done, 'q' => $q, 'counts' => $counts, 'filters' => $filters,
            'dueDates' => TaskModel::dueDates($uid, $scope, $done),
            'subjects' => SubjectModel::forUser($uid),
            'projects' => ProjectModel::forUser($uid),
            'assignees' => self::assigneeOptions($uid),
            'shared' => $shared,
        ], ['title' => 'تسک‌ها', 'page' => 'tasks']);
    }

    /** گزینه‌های مسئول: خودم + همکاران + هم‌پروژه‌ای‌ها (فرم تسک در صفحه‌ی تسک‌ها و برد). */
    public static function assigneeOptions(int $uid): array
    {
        $rows = DB::all("SELECT DISTINCT u.id, u.first_name, u.last_name, u.mobile, u.avatar FROM users u
            WHERE u.status = 'active' AND u.is_bot = 0 AND u.id <> ? AND (
              u.id IN (SELECT colleague_user_id FROM colleagues WHERE user_id = ?)
              OR u.id IN (SELECT pm2.user_id FROM project_members pm2 WHERE pm2.project_id IN (SELECT project_id FROM project_members WHERE user_id = ?))
            ) ORDER BY u.first_name, u.last_name", [$uid, $uid, $uid]);
        $me = DB::one('SELECT id, first_name, last_name, mobile, avatar FROM users WHERE id = ?', [$uid]);
        array_unshift($rows, $me + ['is_me' => true]);
        return array_map(static fn($u) => ['id' => (int) $u['id'], 'name' => full_name($u) . (!empty($u['is_me']) ? ' (خودم)' : ''), 'avatar' => avatar_url($u['avatar'])], $rows);
    }

    /** ساختار کامل برای مودال جزئیات. */
    private function detail(array $t, int $uid): array
    {
        $can = TaskService::effective($t, $uid);
        $notes = array_reverse(NoteModel::forTask((int) $t['id']));   // جدیدترین بالا
        foreach ($notes as &$n) {
            $n['author'] = full_name($n);
            $n['avatar_url'] = avatar_url($n['avatar']);
            $n['mine'] = (int) $n['user_id'] === $uid;
            $n['can_delete'] = $n['mine'] || $can['edit_meta'];
            $n['can_edit'] = $n['mine'] || (int) $t['user_id'] === $uid;
            $n['created_fa'] = jdate($n['created_at']);
            foreach ($n['attachments'] as &$a) {
                $a['url'] = url('files/note_attachments/' . rawurlencode($a['file_path']));
                $a['size_fa'] = human_size((int) $a['file_size']);
                $a['icon'] = file_icon($a['file_ext']);
            }
        }
        unset($n, $a);
        $files = NoteModel::taskAttachments((int) $t['id']);
        foreach ($files as &$f) {
            $f['url'] = url('files/task_attachments/' . rawurlencode($f['file_path']));
            $f['size_fa'] = human_size((int) $f['file_size']);
            $f['icon'] = file_icon($f['file_ext']);
            $f['can_delete'] = (int) $f['user_id'] === $uid || $can['edit_info'];
        }
        unset($f);
        return [
            'id' => (int) $t['id'], 'title' => $t['title'], 'description' => $t['description'], 'priority' => $t['priority'],
            'priority_percent' => $t['priority_percent'] !== null ? (int) $t['priority_percent'] : Priority::PERCENT[$t['priority']] ?? 50,
            'priority_type' => $t['project_priority_type'] ?? 'category',
            'mentionable' => $t['project_id'] ? TaskService::mentionable((int) $t['project_id'], $uid) : [],
            'is_completed' => (int) $t['is_completed'] === 1, 'due_date' => $t['due_date'], 'due_time' => $t['due_time'] ? substr((string) $t['due_time'], 0, 5) : null,
            'due_fa' => $t['due_date'] ? jdate($t['due_date'] . ' 00:00:00', false) . ($t['due_time'] ? ' - ' . fa_num(substr((string) $t['due_time'], 0, 5)) : '') : null,
            'subject_id' => $t['subject_id'] !== null ? (int) $t['subject_id'] : null, 'subject_title' => $t['subject_title'], 'subject_color' => $t['subject_color'] ?? null,
            'project_id' => $t['project_id'] !== null ? (int) $t['project_id'] : null, 'project_title' => $t['project_title'],
            'assignee_id' => $t['assignee_id'] !== null ? (int) $t['assignee_id'] : null, 'assignee_name' => $t['assignee_id'] ? full_name($t, 'assignee_') : null,
            'creator_id' => (int) $t['user_id'], 'creator_name' => full_name($t, 'creator_'),
            'created_fa' => jdate($t['created_at']), 'completed_fa' => $t['completed_at'] ? jdate($t['completed_at']) : null,
            'share_token' => $t['share_token'], 'can' => $can, 'on_board' => BoardService::canSee($t, $uid), 'notes' => $notes, 'files' => $files,
        ];
    }

    public function show(string $id)
    {
        $uid = $this->uid();
        $t = TaskModel::find((int) $id, $uid);
        if ($t === null || !Access::canTask($t, $uid, 'view')) {
            $this->fail('تسک یافت نشد.', 404);
        }
        $this->ok('', ['task' => $this->detail($t, $uid)]);
    }

    /** تاریخچه‌ی تغییرات تسک (همان دسترسی مشاهده‌ی جزئیات) */
    public function history(string $id)
    {
        $uid = $this->uid();
        $t = TaskModel::find((int) $id, $uid);
        if ($t === null || (!Access::canTask($t, $uid, 'view') && !TaskService::canViewShared($t, $uid))) {
            $this->fail('تسک یافت نشد.', 404);
        }
        $this->ok('', ['items' => TaskHistory::forTask($t)]);
    }

    public function store()
    {
        $uid = $this->uid();
        $in = $this->request->all();
        $res = TaskService::create($uid, $in);
        if ($res['ok']) {
            $files = Uploader::normalize($this->request->files['attachments'] ?? null);
            $errors = [];
            if ($files) {
                [, $errors] = NoteModel::storeAttachments('task', (int) $res['data']['id'], $uid, $files);
            }
            $msg = $res['message'] . ($errors ? ' (برخی فایل‌ها ذخیره نشد: ' . implode('، ', $errors) . ')' : '');
            $this->respond(true, $msg, $this->tasksUrl(), ['id' => (int) $res['data']['id']]);
        }
        $this->respond(false, $res['message'], $this->tasksUrl());
    }

    private function tasksUrl(): string
    {
        return '/tasks';
    }

    public function update(string $id)
    {
        $res = TaskService::update($this->uid(), (int) $id, $this->request->all());
        $this->respond($res['ok'], $res['message'], '/tasks');
    }

    public function toggle(string $id)
    {
        $res = TaskService::toggle($this->uid(), (int) $id);
        $this->respond($res['ok'], $res['message'], '/tasks', $res['ok'] ? $res['data'] : []);
    }

    public function destroy(string $id)
    {
        $res = TaskService::delete($this->uid(), (int) $id);
        $this->respond($res['ok'], $res['message'], '/tasks');
    }

    public function share(string $id)
    {
        $uid = $this->uid();
        $t = TaskModel::raw((int) $id);
        if ($t === null || !Access::canTask($t, $uid, 'view')) {
            $this->fail('تسک یافت نشد.', 404);
        }
        $tok = TaskModel::ensureShareToken((int) $id);
        $this->ok('', ['token' => $tok, 'url' => full_url('tasks?task=' . $tok)]);
    }

    public function addNote(string $id)
    {
        $res = TaskService::addNote($this->uid(), (int) $id, $this->request->str('note', 5000), Uploader::normalize($this->request->files['attachments'] ?? null), array_map('intval', $this->request->arr('mentions')));
        $this->respond($res['ok'], $res['message'], '/tasks', $res['ok'] ? (array) $res['data'] : []);
    }

    public function updateNote(string $id)
    {
        $res = TaskService::updateNote($this->uid(), (int) $id, $this->request->str('note', 5000));
        $this->respond($res['ok'], $res['message'], '/tasks');
    }

    public function deleteNote(string $id)
    {
        $res = TaskService::deleteNote($this->uid(), (int) $id);
        $this->respond($res['ok'], $res['message'], '/tasks');
    }

    public function addFiles(string $id)
    {
        $res = TaskService::addTaskFiles($this->uid(), (int) $id, Uploader::normalize($this->request->files['attachments'] ?? null));
        $this->respond($res['ok'], $res['message'], '/tasks', $res['ok'] ? (array) $res['data'] : []);
    }

    public function deleteFile(string $kind, string $id)
    {
        if (!in_array($kind, ['task', 'note'], true)) {
            $this->abort(404);
        }
        $res = TaskService::deleteFile($this->uid(), $kind, (int) $id);
        $this->respond($res['ok'], $res['message'], '/tasks');
    }

    /** اعلان سررسید برای مرورگر (polling). */
    public function due()
    {
        $rows = TaskModel::dueNotifications($this->uid());
        $out = [];
        foreach ($rows as $r) {
            $tok = $r['share_token'] ?: TaskModel::ensureShareToken((int) $r['id']);
            $out[] = ['id' => (int) $r['id'], 'title' => $r['title'], 'project_title' => $r['project_title'], 'url' => url('tasks?task=' . $tok), 'due_time' => substr((string) $r['due_time'], 0, 5)];
        }
        $this->json(['status' => 'success', 'tasks' => $out]);
    }
}
