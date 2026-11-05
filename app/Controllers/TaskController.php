<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Pid;
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
        $scope = in_array($scope, ['others', 'project'], true) ? $scope : 'mine';
        $statusQ = (string) ($r->query['status'] ?? 'open');
        $done = $statusQ === 'done';
        $q = trim((string) ($r->query['q'] ?? ''));
        $page = max(1, (int) ($r->query['page'] ?? 1));
        $sort = (string) ($r->query['sort'] ?? 'smart');
        $sort = isset(TaskModel::SORTS[$sort]) ? $sort : 'smart';      // پیش‌فرض هوشمند: عقب‌افتاده، امروز، پیش رو
        $when = (string) ($r->query['when'] ?? '');
        $when = in_array($when, ['overdue', 'today', 'tomorrow'], true) || Validate::date($when) !== null ? $when : '';
        $prio = in_array($r->query['prio'] ?? '', ['high', 'low'], true) ? (string) $r->query['prio'] : '';
        $proj = max(0, (int) ($r->query['project'] ?? 0));
        $label = max(0, (int) ($r->query['label'] ?? 0));   // فیلتر لیبل (از کارت لیبل در صفحه‌ی لیبل‌ها)
        // «تسک‌های پروژه» (آمدن از کارت‌ها و عملکرد اعضای «تحلیل پروژه»): فقط با دسترسی مشاهده‌ی تحلیل همان پروژه
        $projScope = null;
        $assignee = 0;
        if ($scope === 'project') {
            $pr = $proj > 0 ? ProjectModel::find($proj) : null;
            if ($pr === null || !Access::isProjectMember($proj, $uid) || !Access::projectCan($proj, $uid, 'view_analytics')) {
                $this->flash('دسترسی مشاهده‌ی همه‌ی تسک‌های این پروژه را ندارید.', 'error');
                $this->redirect('/tasks');
            }
            $assignee = max(0, (int) ($r->query['assignee'] ?? 0));
            $an = $assignee > 0 ? DB::one('SELECT first_name, last_name FROM users WHERE id = ?', [$assignee]) : null;
            if ($an === null) {
                $assignee = 0;
            }
            $projScope = ['id' => Pid::of('projects', $proj), 'title' => $pr['title'], 'assignee' => $assignee, 'assignee_name' => $an ? full_name($an) : null];
        }
        // «همه» (باز و انجام‌شده با هم): تب همه، و کارت‌های «کل تسک‌ها»/«اولویت زیاد» پیشخوان
        $completed = $statusQ === 'all' ? null : $done;
        // فیلتر سریع جریان کار: review = «منتظر تأیید من» (تسک‌هایی که می‌توانم تأیید کنم)، rework = «برگشت‌خورده برای اصلاح» (مسئولش منم)
        $flow = in_array($r->query['flow'] ?? '', ['review', 'rework'], true) ? (string) $r->query['flow'] : '';
        $filters = ['q' => $q, 'sort' => $sort, 'when' => $when, 'prio' => $prio, 'project' => $proj, 'assignee' => $assignee, 'label' => $label, 'flow' => $flow];
        $flowIds = $flow !== '' ? array_map(static fn($x) => (int) $x['id'], BoardService::waiting($uid, 500)[$flow]) : null;
        if ($flowIds !== null) {
            $filters['ids'] = $flowIds;
        }
        $res = TaskModel::paginate($uid, $scope, $completed, self::PER_PAGE, ($page - 1) * self::PER_PAGE, $filters);
        $pages = max(1, (int) ceil($res['total'] / self::PER_PAGE));
        foreach ($res['rows'] as &$t) {
            $t['can'] = TaskService::effective($t, $uid);
        }
        unset($t);

        if ($projScope !== null) {
            $pc = DB::one('SELECT COUNT(*) total, COALESCE(SUM(is_completed = 1),0) done FROM tasks WHERE project_id = ? AND blocked_at IS NULL' . ($assignee ? ' AND assignee_id = ?' : ''), $assignee ? [$proj, $assignee] : [$proj]);
            $projScope['counts'] = ['all' => (int) $pc['total'], 'done' => (int) $pc['done'], 'open' => (int) $pc['total'] - (int) $pc['done']];
            $projScope['status'] = $completed === null ? 'all' : ($done ? 'done' : 'open');
        }
        $wf = BoardService::waiting($uid, 500);
        $flowCounts = ['review' => count($wf['review']), 'rework' => count($wf['rework'])];
        $counts = [
            'mine_open'   => (int) DB::value('SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND is_completed = 0 AND blocked_at IS NULL', [$uid]),
            'mine_done'   => (int) DB::value('SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND is_completed = 1 AND blocked_at IS NULL', [$uid]),
            'others_open' => (int) DB::value('SELECT COUNT(*) FROM tasks WHERE user_id = ? AND (assignee_id IS NULL OR assignee_id <> ?) AND is_completed = 0 AND blocked_at IS NULL', [$uid, $uid]),
            'others_done' => (int) DB::value('SELECT COUNT(*) FROM tasks WHERE user_id = ? AND (assignee_id IS NULL OR assignee_id <> ?) AND is_completed = 1 AND blocked_at IS NULL', [$uid, $uid]),
            // «در جریان» جدا از «منتظر تأیید»: کار تحویل‌داده‌شده دیگر کار مسئول نیست (v5.4)
            'mine_review' => (int) DB::value("SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND is_completed = 0 AND blocked_at IS NULL AND project_id IS NOT NULL AND archived_at IS NULL AND board_status = 'review'", [$uid]),
            'others_review' => (int) DB::value("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND (assignee_id IS NULL OR assignee_id <> ?) AND is_completed = 0 AND blocked_at IS NULL AND project_id IS NOT NULL AND archived_at IS NULL AND board_status = 'review'", [$uid, $uid]),
        ];

        // نمای اشتراکی: /tasks?task=<token>
        $shared = null;
        $tok = Validate::shareToken((string) ($r->query['task'] ?? ''));
        if ($tok !== null) {
            $t = TaskModel::findByToken($tok, $uid);
            if ($t !== null && TaskService::canViewShared($t, $uid)) {
                TaskService::markMentionsSeen((int) $t['id'], $uid);
                $shared = $this->detail($t, $uid);
            } else {
                // خطای دسترسی و بازگشت به تسک‌های خود کاربر
                $this->flash($t === null ? 'لینک تسک نامعتبر است.' : 'شما اجازه‌ی مشاهده‌ی این تسک را ندارید.', 'error');
                $this->redirect('/tasks');
            }
        }

        $this->view('tasks/index', [
            'tasks' => $res['rows'], 'total' => $res['total'], 'page' => $page, 'pages' => $pages,
            'scope' => $scope, 'done' => $done, 'statusAll' => $completed === null, 'q' => $q, 'counts' => $counts, 'filters' => $filters,
            'dueDates' => TaskModel::dueDates($uid, $scope, $completed, $proj), 'projScope' => $projScope,
            'subjects' => SubjectModel::forUser($uid),
            'projects' => ProjectModel::forUser($uid),
            'assignees' => self::assigneeOptions($uid),
            'shared' => $shared,
            'flowCounts' => $flowCounts,
            'blockedCount' => \App\Services\BlockService::list('task', $uid, 1)['total'],
        ], ['title' => 'تسک‌ها', 'page' => 'tasks']);
    }

    /** گزینه‌های مسئول: خودم + همکاران + هم‌پروژه‌ای‌ها (فرم تسک در صفحه‌ی تسک‌ها و برد). */
    public static function assigneeOptions(int $uid): array
    {
        $rows = DB::all("SELECT DISTINCT u.id, u.first_name, u.last_name, u.mobile, u.avatar FROM users u
            WHERE u.status = 'active' AND u.is_bot = 0 AND u.id <> ? AND (
              u.id IN (SELECT c.colleague_user_id FROM colleagues c WHERE c.user_id = ? AND " . Access::approvedSql('c') . ")
              OR u.id IN (SELECT pm2.user_id FROM project_members pm2 WHERE pm2.project_id IN (SELECT project_id FROM project_members WHERE user_id = ?))
            ) ORDER BY u.first_name, u.last_name", [$uid, $uid, $uid]);
        $me = DB::one('SELECT id, first_name, last_name, mobile, avatar FROM users WHERE id = ?', [$uid]);
        array_unshift($rows, $me + ['is_me' => true]);
        Pid::prime('users', array_column($rows, 'id'));
        return array_map(static fn($u) => ['id' => Pid::of('users', $u['id']), 'name' => full_name($u) . (!empty($u['is_me']) ? ' (خودم)' : ''), 'avatar' => avatar_url($u['avatar'])], $rows);
    }

    /** ساختار کامل برای مودال جزئیات. */
    private function detail(array $t, int $uid): array
    {
        $can = TaskService::effective($t, $uid);
        $notes = array_reverse(NoteModel::forTask((int) $t['id']));   // جدیدترین بالا
        foreach ($notes as &$n) {
            $n['id'] = Pid::row('task_notes', $n);
            unset($n['pid']);
            $n['author'] = full_name($n);
            $n['avatar_url'] = avatar_url($n['avatar']);
            $n['mine'] = (int) $n['user_id'] === $uid;
            $n['can_edit'] = empty($t['blocked_at']) && ($n['mine'] || (int) $t['user_id'] === $uid);
            $n['created_fa'] = jdate($n['created_at']);
            $n['attachments'] = array_map(static fn($a) => [
                'id' => Pid::row('note_attachments', $a), 'file_name' => $a['file_name'], 'file_size' => (int) $a['file_size'], 'file_ext' => $a['file_ext'],
                'url' => url('files/note_attachments/' . rawurlencode($a['file_path'])), 'size_fa' => human_size((int) $a['file_size']), 'icon' => file_icon($a['file_ext']),
            ], $n['attachments']);
        }
        unset($n, $a);
        // خروجی صریح (whitelist): ردیف خام دیتابیس (موبایل آپلودکننده، مسیر/MIME داخلی) به JSON نرود
        $files = array_map(static fn($f) => [
            'id' => Pid::row('task_attachments', $f), 'file_name' => $f['file_name'], 'file_size' => (int) $f['file_size'], 'file_ext' => $f['file_ext'],
            'url' => url('files/task_attachments/' . rawurlencode($f['file_path'])), 'size_fa' => human_size((int) $f['file_size']),
            'icon' => file_icon($f['file_ext']), 'uploader' => full_name($f),
        ], NoteModel::taskAttachments((int) $t['id']));
        return [
            'id' => Pid::row('tasks', $t), 'title' => $t['title'], 'description' => $t['description'], 'priority' => $t['priority'],
            'priority_percent' => $t['priority_percent'] !== null ? (int) $t['priority_percent'] : Priority::PERCENT[$t['priority']] ?? 50,
            'priority_type' => $t['project_priority_type'] ?? 'category',
            'mentionable' => $t['project_id'] ? TaskService::mentionablePublic((int) $t['project_id'], $uid) : [],
            'is_completed' => (int) $t['is_completed'] === 1, 'is_quick' => (int) ($t['is_quick'] ?? 0) === 1, 'due_date' => $t['due_date'], 'due_time' => $t['due_time'] ? substr((string) $t['due_time'], 0, 5) : null,
            'due_fa' => $t['due_date'] ? jdate($t['due_date'] . ' 00:00:00', false) . ($t['due_time'] ? ' - ' . fa_num(substr((string) $t['due_time'], 0, 5)) : '') : null,
            'subject_id' => $t['subject_id'] !== null ? (int) $t['subject_id'] : null, 'subject_title' => $t['subject_title'], 'subject_color' => $t['subject_color'] ?? null,
            'project_id' => $t['project_id'] !== null ? (int) $t['project_id'] : null, 'project_title' => $t['project_title'],
            'assignee_id' => $t['assignee_id'] !== null ? (int) $t['assignee_id'] : null, 'assignee_name' => $t['assignee_id'] ? full_name($t, 'assignee_') : null,
            'creator_id' => (int) $t['user_id'], 'creator_name' => full_name($t, 'creator_'),
            'created_fa' => jdate($t['created_at']), 'completed_fa' => $t['completed_at'] ? jdate($t['completed_at']) : null,
            'share_token' => $t['share_token'], 'can' => $can, 'on_board' => BoardService::canSee($t, $uid), 'notes' => $notes, 'files' => $files,
            'board' => BoardService::taskState($t, $uid),
            // مسدود کردن به‌جای حذف: تسک مسدود فقط‌خواندنی است و از پنجره‌ی «مسدودشده‌ها»ی صفحه‌ی تسک‌ها برمی‌گردد
            'is_blocked' => !empty($t['blocked_at']), 'blocked_fa' => !empty($t['blocked_at']) ? jdate((string) $t['blocked_at']) : null,
            'blocked_by_name' => !empty($t['blocked_by']) ? full_name(DB::one('SELECT first_name, last_name FROM users WHERE id = ?', [(int) $t['blocked_by']]) ?? []) : null,
        ];
    }

    public function show(string $id)
    {
        $uid = $this->uid();
        $t = TaskModel::find((int) $id, $uid);
        if ($t === null || !Access::canTask($t, $uid, 'view')) {
            $this->fail('تسک یافت نشد.', 404);
        }
        TaskService::markMentionsSeen((int) $t['id'], $uid);
        $this->ok('', ['task' => $this->detail($t, $uid)]);
    }

    /** تغییر وضعیت بورد از جزئیات تسک */
    public function status(string $id)
    {
        $res = BoardService::setStatus($this->uid(), (int) $id, $this->request->str('status', 20), $this->request->str('reason', 255));
        $d = $res['ok'] ? Pid::tag((array) $res['data'], 'tasks') : [];
        // «تأیید و بستن» (جزئیات / پیشخوان): پیام با دکمه‌ی «بازگردانی» چند دقیقه‌ای (v5.4)
        if ($res['ok'] && ($d['status'] ?? '') === 'done') {
            $d['undo'] = url('/tasks/' . Pid::of('tasks', $id) . '/undo-done');
        }
        $this->respond($res['ok'], $res['message'], '/tasks', $d);
    }

    /** «انتقال به مرحله‌ی بعد» در جزئیات تسک (همان قواعد بورد: دسترسی مرحله‌ها، تکمیل و سقف ستون) */
    public function advance(string $id)
    {
        $res = BoardService::advance($this->uid(), (int) $id, $this->request->str('from', 20));
        $this->respond($res['ok'], $res['message'], '/tasks', $res['ok'] ? Pid::tag((array) $res['data'], 'tasks') : []);
    }

    /** تاریخچه‌ی تغییرات تسک (همان دسترسی مشاهده‌ی جزئیات) */
    public function history(string $id)
    {
        $uid = $this->uid();
        $t = TaskModel::find((int) $id, $uid);
        // نمای اشتراکی فقط با خود توکن لینک (نه شناسه‌ی عددی) مجاز است
        $tok = Validate::shareToken((string) ($this->request->query['token'] ?? ''));
        $viaLink = $t !== null && $tok !== null && $t['share_token'] && hash_equals((string) $t['share_token'], $tok) && TaskService::canViewShared($t, $uid);
        if ($t === null || (!Access::canTask($t, $uid, 'view') && !$viaLink)) {
            $this->fail('تسک یافت نشد.', 404);
        }
        $this->ok('', ['items' => TaskHistory::forTask($t)]);
    }

    /** «ثبت سریع تسک»: فقط عنوان + مسئول (@) و پروژه (#)؛ بدون پروژه = پروژه‌ی پیش‌فرض */
    public function quick()
    {
        $uid = $this->uid();
        $r = $this->request;
        $pid = $r->int('project_id') ?: (int) TaskService::defaultProject($uid);
        if ($pid <= 0) {
            $this->respond(false, 'برای ثبت تسک ابتدا یک پروژه بسازید یا عضو پروژه‌ای شوید.', $this->tasksUrl());
        }
        $res = TaskService::create($uid, ['title' => $r->str('title', 300), 'project_id' => $pid, 'assignee_id' => $r->int('assignee_id') ?: $uid], true);
        if (!$res['ok']) {
            $this->respond(false, $res['message'], $this->tasksUrl());
        }
        $t = $res['data'];
        $this->respond(true, 'تسک با ثبت سریع ایجاد شد.', $this->tasksUrl(), ['id' => Pid::row('tasks', $t), 'share_token' => $t['share_token'] ?? null]);
    }

    public function quickOptions()
    {
        $this->json(['status' => 'success'] + TaskService::quickOptions($this->uid()));
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
            $this->respond(true, $msg, $this->tasksUrl(), ['id' => Pid::row('tasks', $res['data'])]);
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
        // بعد از «انجام شد»، دکمه‌ی «بازگردانی» در پیام (همان مسیر تیک، چند ثانیه)
        // تحویل برای بررسی ← «بازگردانی» = پس گرفتن تحویل؛ تکمیل مستقیم (تسک قدیمی بی‌پروژه) ← همان تیک
        $d = $res['ok'] ? Pid::tag((array) $res['data'], 'tasks') : [];
        $undo = ($d['status'] ?? '') === 'review' && isset($d['from']) ? url('/tasks/' . Pid::of('tasks', $id) . '/withdraw')
            : (($d['status'] ?? '') === 'done' && isset($d['from']) ? url('/tasks/' . Pid::of('tasks', $id) . '/undo-done')   // تکمیل مستقیم با تیک (دسترسی تأیید)
            : (!empty($d['is_completed']) && !isset($d['status']) ? url('/tasks/' . Pid::of('tasks', $id) . '/toggle') : null));
        $extra = $res['ok'] ? Pid::tag($d, 'tasks') + ($undo ? ['undo' => $undo] : []) : [];
        $this->respond($res['ok'], $res['message'], '/tasks', $extra);
    }

    /** «بازگردانی» بعد از تکمیل مستقیم یا تأیید (v5.4) */
    public function undoDone(string $id)
    {
        $res = BoardService::undoDone($this->uid(), (int) $id);
        $this->respond($res['ok'], $res['message'], '/tasks', $res['ok'] ? Pid::tag((array) $res['data'], 'tasks') : []);
    }

    /** پس گرفتن تحویل (دکمه‌ی «بازگردانی» پیام بعد از تیک) */
    public function withdraw(string $id)
    {
        $res = BoardService::withdraw($this->uid(), (int) $id);
        $this->respond($res['ok'], $res['message'], '/tasks', $res['ok'] ? Pid::tag((array) $res['data'], 'tasks') : []);
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
        $this->respond($res['ok'], $res['message'], '/tasks', $res['ok'] ? Pid::tag((array) $res['data'], 'task_notes') : []);
    }

    public function updateNote(string $id)
    {
        $res = TaskService::updateNote($this->uid(), (int) $id, $this->request->str('note', 5000));
        $this->respond($res['ok'], $res['message'], '/tasks');
    }

    public function addFiles(string $id)
    {
        $res = TaskService::addTaskFiles($this->uid(), (int) $id, Uploader::normalize($this->request->files['attachments'] ?? null));
        $this->respond($res['ok'], $res['message'], '/tasks', $res['ok'] ? Pid::tag((array) $res['data'], 'tasks') : []);
    }

    /** اعلان سررسید برای مرورگر (polling). */
    /** یادآوری‌هایی که مرورگر نمایش داد: ثبت notified_at (POST + CSRF) */
    public function dueAck()
    {
        $n = TaskModel::markNotified($this->uid(), Pid::ids('tasks', $this->request->arr('ids')));
        $this->json(['status' => 'success', 'marked' => $n]);
    }

    public function due()
    {
        $rows = TaskModel::dueNotifications($this->uid());
        $out = [];
        foreach ($rows as $r) {
            $tok = $r['share_token'] ?: TaskModel::ensureShareToken((int) $r['id']);
            $out[] = ['id' => Pid::row('tasks', $r), 'title' => $r['title'], 'project_title' => $r['project_title'], 'url' => url('tasks?task=' . $tok), 'due_time' => substr((string) $r['due_time'], 0, 5)];
        }
        $this->json(['status' => 'success', 'tasks' => $out]);
    }
}
