<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Validate;
use App\Models\NoteModel;
use App\Models\SubjectModel;
use App\Models\TaskModel;

/**
 * منطق تسک‌ها — تنها پیاده‌سازی، مشترک بین پنل وب و API.
 * خروجی همه‌ی متدها: ['ok'=>bool,'message'=>string,'code'=>string,'http'=>int,'data'=>mixed]
 */
final class TaskService
{
    private static function res(bool $ok, string $msg = '', string $code = '', int $http = 200, $data = null): array
    {
        return ['ok' => $ok, 'message' => $msg, 'code' => $code, 'http' => $http, 'data' => $data];
    }

    private static function err(string $msg, string $code, int $http = 422): array
    {
        return self::res(false, $msg, $code, $http);
    }

    private static function assigneeAllowed(int $ownerId, int $actorId, int $assigneeId, ?int $projectId): bool
    {
        if ($projectId !== null) {
            return Access::isProjectMember($projectId, $assigneeId);       // مسئول تسک پروژه فقط از اعضای همان پروژه
        }
        return $assigneeId === $ownerId || $assigneeId === $actorId
            || Access::canAssignTo($ownerId, $assigneeId, $projectId)
            || Access::canAssignTo($actorId, $assigneeId, $projectId);
    }

    public static function create(int $actor, array $in): array
    {
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') {
            return self::err('عنوان تسک الزامی است.', 'TITLE_REQUIRED');
        }
        if (mb_strlen($title) > 255) {
            return self::err('عنوان تسک نباید بیشتر از ۲۵۵ نویسه باشد.', 'TITLE_TOO_LONG');
        }
        $desc = trim((string) ($in['description'] ?? ''));
        if (mb_strlen($desc) > 10000) {
            return self::err('توضیحات بیش از حد طولانی است.', 'DESCRIPTION_TOO_LONG');
        }
        $priority = Validate::enum($in['priority'] ?? null, ['low', 'medium', 'high'], 'medium');
        $percent = Priority::percent($in['priority_percent'] ?? null);
        if (isset($in['priority_percent']) && $in['priority_percent'] !== '' && $percent === null) {
            return self::err('اولویت درصدی باید عددی بین ۰ تا ۱۰۰ باشد.', 'PRIORITY_INVALID');
        }
        if ($percent !== null) {
            $priority = Priority::levelOf($percent);
        } else {
            $percent = Priority::PERCENT[$priority];
        }

        $dueRaw = trim((string) ($in['due_date'] ?? ''));
        $due = Validate::date($dueRaw);
        if ($dueRaw !== '' && $due === null) {
            return self::err('فرمت تاریخ سررسید باید YYYY-MM-DD باشد.', 'DUE_DATE_INVALID');
        }
        $timeRaw = trim((string) ($in['due_time'] ?? ''));
        $time = Validate::time($timeRaw);
        if ($timeRaw !== '' && $time === null) {
            return self::err('فرمت ساعت سررسید باید HH:MM باشد.', 'DUE_TIME_INVALID');
        }
        if ($due === null) {
            $time = null;
        }

        // لیبل و پروژه اجباری‌اند
        $subjectId = (int) ($in['subject_id'] ?? 0);
        if ($subjectId <= 0) {
            return self::err('انتخاب لیبل الزامی است.', 'SUBJECT_REQUIRED');
        }
        if (SubjectModel::owned($subjectId, $actor) === null) {
            return self::err('لیبل انتخاب‌شده معتبر نیست.', 'SUBJECT_INVALID');
        }
        $projectId = (int) ($in['project_id'] ?? 0);
        if ($projectId <= 0) {
            return self::err('انتخاب پروژه الزامی است.', 'PROJECT_REQUIRED');
        }
        if (!Access::isProjectMember($projectId, $actor)) {
            return self::err('پروژه‌ی انتخاب‌شده معتبر نیست یا عضو آن نیستید.', 'PROJECT_INVALID');
        }
        $assignee = (int) ($in['assignee_id'] ?? 0) ?: $actor;
        // واگذاری تسک جدید به دیگران فقط با مجوز «تغییر مسئول» در همین پروژه (سازنده‌ی پروژه همیشه)
        if ($assignee !== $actor && !in_array('reassign', Access::projectTaskPerms($projectId, $actor), true)) {
            return self::err('برای واگذاری تسک به عضو دیگر، دسترسی «تغییر مسئول» در این پروژه لازم است؛ تسک را برای خودتان بسازید.', 'ASSIGNEE_FORBIDDEN', 403);
        }
        if (!self::assigneeAllowed($actor, $actor, $assignee, $projectId)) {
            return self::err('امکان واگذاری تسک به این کاربر وجود ندارد (مسئول تسک پروژه باید عضو همان پروژه باشد).', 'ASSIGNEE_INVALID', 403);
        }

        $id = TaskModel::create([
            'user_id' => $actor, 'assignee_id' => $assignee, 'subject_id' => $subjectId, 'project_id' => $projectId,
            'title' => $title, 'description' => $desc !== '' ? $desc : null, 'priority' => $priority, 'priority_percent' => $percent,
            'due_date' => $due, 'due_time' => $time,
        ]);
        $task = TaskModel::raw($id);
        $pt = $projectId ? (string) DB::value('SELECT title FROM projects WHERE id = ?', [$projectId]) : null;
        Notifier::taskAssigned($task, $actor, $pt);
        return self::res(true, 'تسک با موفقیت ایجاد شد.', '', 201, $task);
    }

    public static function update(int $actor, int $id, array $in): array
    {
        $t = TaskModel::raw($id);
        if ($t === null || !Access::canTask($t, $actor, 'view')) {
            return self::err('تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        $set = [];
        $has = static fn(string $k): bool => array_key_exists($k, $in);

        // هر گروه فیلد مجوز خودش را دارد: عنوان/توضیحات = edit_info، سررسید = edit_due، اولویت/لیبل = فقط سازنده
        $needInfo = $needDue = $needMeta = false;
        if ($has('title')) {
            $title = trim((string) $in['title']);
            if ($title === '' || mb_strlen($title) > 255) {
                return self::err('عنوان تسک نامعتبر است.', 'TITLE_INVALID');
            }
            if ($title !== $t['title']) {
                $set['title'] = $title;
                $needInfo = true;
            }
        }
        if ($has('description')) {
            $d = trim((string) $in['description']);
            if (mb_strlen($d) > 10000) {
                return self::err('توضیحات بیش از حد طولانی است.', 'DESCRIPTION_TOO_LONG');
            }
            if (($d !== '' ? $d : null) !== $t['description']) {
                $set['description'] = $d !== '' ? $d : null;
                $needInfo = true;
            }
        }
        if ($has('priority_percent') && trim((string) $in['priority_percent']) !== '') {
            $pc = Priority::percent($in['priority_percent']);
            if ($pc === null) {
                return self::err('اولویت درصدی باید عددی بین ۰ تا ۱۰۰ باشد.', 'PRIORITY_INVALID');
            }
            if ($pc !== ($t['priority_percent'] === null ? null : (int) $t['priority_percent'])) {
                $set['priority_percent'] = $pc;
                $set['priority'] = Priority::levelOf($pc);
                $needMeta = true;
            }
        } elseif ($has('priority')) {
            $p = Validate::enum($in['priority'], ['low', 'medium', 'high'], '');
            if ($p === '') {
                return self::err('اولویت نامعتبر است.', 'PRIORITY_INVALID');
            }
            if ($p !== $t['priority']) {
                $set['priority'] = $p;
                $set['priority_percent'] = Priority::PERCENT[$p];
                $needMeta = true;
            }
        }
        if ($has('due_date')) {
            $raw = trim((string) $in['due_date']);
            $d = Validate::date($raw);
            if ($raw !== '' && $d === null) {
                return self::err('فرمت تاریخ سررسید نامعتبر است.', 'DUE_DATE_INVALID');
            }
            if ($d !== $t['due_date']) {
                $set['due_date'] = $d;
                $set['notified_at'] = null;
                $needDue = true;
            }
            if ($d === null) {
                $set['due_time'] = null;
            }
        }
        if ($has('due_time') && (array_key_exists('due_date', $set) ? $set['due_date'] : $t['due_date']) !== null) {
            $raw = trim((string) $in['due_time']);
            $tm = Validate::time($raw);
            if ($raw !== '' && $tm === null) {
                return self::err('فرمت ساعت سررسید نامعتبر است.', 'DUE_TIME_INVALID');
            }
            if ($tm !== $t['due_time']) {
                $set['due_time'] = $tm;
                $set['notified_at'] = null;
                $needDue = true;
            }
        }
        if ($has('subject_id')) {
            $sid = (int) $in['subject_id'];
            if ($sid <= 0) {
                return self::err('انتخاب لیبل الزامی است.', 'SUBJECT_REQUIRED');
            }
            if (SubjectModel::owned($sid, (int) $t['user_id']) === null && SubjectModel::owned($sid, $actor) === null) {
                return self::err('لیبل انتخاب‌شده معتبر نیست.', 'SUBJECT_INVALID');
            }
            if ($sid !== ($t['subject_id'] === null ? null : (int) $t['subject_id'])) {
                $set['subject_id'] = $sid;
                $needMeta = true;
            }
        }
        if ($needInfo && !Access::canTask($t, $actor, 'edit_info')) {
            return self::err('اجازه‌ی ویرایش اطلاعات این تسک را ندارید.', 'FORBIDDEN', 403);
        }
        if ($needDue && !Access::canTask($t, $actor, 'edit_due')) {
            return self::err('اجازه‌ی تغییر زمان انجام این تسک را ندارید.', 'FORBIDDEN', 403);
        }
        if ($needMeta && !Access::canTask($t, $actor, 'edit_meta')) {
            return self::err('تغییر اولویت و لیبل فقط برای سازنده‌ی تسک یا مدیر پروژه ممکن است.', 'FORBIDDEN', 403);
        }

        $newProject = $t['project_id'] === null ? null : (int) $t['project_id'];
        if ($has('project_id')) {
            $pid = (int) $in['project_id'];
            if ($pid <= 0) {
                return self::err('انتخاب پروژه الزامی است.', 'PROJECT_REQUIRED');
            }
            if ($pid !== $newProject) {
                if (!Access::canTask($t, $actor, 'change_project')) {
                    return self::err('اجازه‌ی تغییر پروژه‌ی این تسک را ندارید.', 'FORBIDDEN', 403);
                }
                if ($pid !== null && !Access::isProjectMember($pid, $actor)) {
                    return self::err('پروژه‌ی انتخاب‌شده معتبر نیست.', 'PROJECT_INVALID');
                }
                $set['project_id'] = $pid;
                $newProject = $pid;
            }
        }
        $reassigned = false;
        if ($has('assignee_id')) {
            $aid = (int) $in['assignee_id'] ?: (int) $t['user_id'];
            if ($aid !== (int) $t['assignee_id']) {
                if (!Access::canTask($t, $actor, 'reassign')) {
                    return self::err('اجازه‌ی تغییر مسئول این تسک را ندارید.', 'FORBIDDEN', 403);
                }
                if (!self::assigneeAllowed((int) $t['user_id'], $actor, $aid, $newProject)) {
                    return self::err('امکان واگذاری تسک به این کاربر وجود ندارد.', 'ASSIGNEE_INVALID', 403);
                }
                $set['assignee_id'] = $aid;
                $set['notified_at'] = null;
                $reassigned = true;
            }
        }
        if ($set) {
            DB::update('tasks', $set, 'id = ?', [$id]);
            TaskHistory::changes($t, $set, $actor);
        }
        $fresh = TaskModel::raw($id);
        if ($reassigned) {
            $fresh['share_token'] = TaskModel::ensureShareToken($id);
            $pt = $fresh['project_id'] ? (string) DB::value('SELECT title FROM projects WHERE id = ?', [(int) $fresh['project_id']]) : null;
            Notifier::taskAssigned($fresh, $actor, $pt);
        }
        return self::res(true, 'تسک به‌روزرسانی شد.', '', 200, $fresh);
    }

    public static function toggle(int $actor, int $id): array
    {
        $t = TaskModel::raw($id);
        if ($t === null || !Access::canTask($t, $actor, 'view') && !Access::canTask($t, $actor, 'complete')) {
            return self::err('تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        if (!Access::canTask($t, $actor, 'complete')) {
            return self::err('شما دسترسی لازم برای تکمیل تسک را ندارید.', 'FORBIDDEN', 403);
        }
        // تسک پروژه‌دار: یک مسیر واحد با بورد پروژه (مجوزهای مراحل برد و سقف ستون‌ها هم اعمال می‌شود)
        if ($t['project_id'] !== null) {
            $to = (int) $t['is_completed'] === 1 ? 'review' : 'done';
            $m = BoardService::move($actor, $id, $to, '', true);
            if (!$m['ok']) {
                return $m;
            }
            $done = $to === 'done';
            return self::res(true, $done ? 'تسک انجام‌شده شد.' : 'تسک به «در انتظار بررسی» برگشت.', '', 200, ['id' => $id, 'is_completed' => $done, 'completed_at' => DB::value('SELECT completed_at FROM tasks WHERE id = ?', [$id])]);
        }
        $r = TaskModel::toggle($id);
        TaskHistory::log($id, $actor, $r['is_completed'] ? 'completed' : 'reopened');
        return self::res(true, $r['is_completed'] ? 'تسک انجام‌شده شد.' : 'تسک به حالت انجام‌نشده برگشت.', '', 200, $r + ['completed_at' => DB::value('SELECT completed_at FROM tasks WHERE id = ?', [$id])]);
    }

    public static function delete(int $actor, int $id): array
    {
        $t = TaskModel::raw($id);
        if ($t === null || !Access::canTask($t, $actor, 'view') && !Access::canTask($t, $actor, 'delete')) {
            return self::err('تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        if (!Access::canTask($t, $actor, 'delete')) {
            return self::err('اجازه‌ی حذف این تسک را ندارید.', 'FORBIDDEN', 403);
        }
        TaskModel::delete($id);
        return self::res(true, 'تسک حذف شد.', '', 200, ['id' => $id]);
    }

    public static function addNote(int $actor, int $taskId, string $text, array $files = [], array $mentions = []): array
    {
        $t = TaskModel::raw($taskId);
        if ($t === null || !Access::canTask($t, $actor, 'view') && !Access::canTask($t, $actor, 'notes')) {
            return self::err('تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        if (!Access::canTask($t, $actor, 'notes')) {
            return self::err('اجازه‌ی ثبت یادداشت ندارید.', 'FORBIDDEN', 403);
        }
        $text = trim($text);
        if ($text === '' && !$files) {
            return self::err('متن یادداشت یا فایل پیوست الزامی است.', 'NOTE_EMPTY');
        }
        if (mb_strlen($text) > 5000) {
            return self::err('یادداشت نباید بیشتر از ۵۰۰۰ نویسه باشد.', 'NOTE_TOO_LONG');
        }
        $nid = DB::insert('task_notes', ['task_id' => $taskId, 'user_id' => $actor, 'note' => $text]);
        [$saved, $errors] = $files ? NoteModel::storeAttachments('note', $nid, $actor, $files) : [[], []];
        $token = TaskModel::ensureShareToken($taskId);
        $t['share_token'] = $token;
        // منشن: فقط اعضای پروژه‌ی همین تسک
        $allowed = $t['project_id'] ? array_column(self::mentionable((int) $t['project_id'], $actor), 'id') : [];
        $mentioned = array_values(array_intersect(array_unique(array_map('intval', $mentions)), $allowed));
        foreach ($mentioned as $uid) {
            Notifier::noteMention($t, $actor, $text !== '' ? $text : '(فایل پیوست)', $uid, true);
        }
        foreach (array_unique(array_filter([(int) $t['user_id'], (int) $t['assignee_id']])) as $uid) {
            if ($uid !== $actor && !in_array($uid, $mentioned, true)) {
                Notifier::noteMention($t, $actor, $text !== '' ? $text : '(فایل پیوست)', $uid);
            }
        }
        return self::res(true, 'یادداشت ثبت شد.' . ($mentioned ? ' ' . fa_num(count($mentioned)) . ' نفر منشن شدند.' : ''), '', 201, ['id' => $nid, 'attachments' => $saved, 'upload_errors' => $errors, 'mentioned' => $mentioned]);
    }

    public static function updateNote(int $actor, int $noteId, string $text): array
    {
        $n = NoteModel::find($noteId);
        if ($n === null) {
            return self::err('یادداشت یافت نشد.', 'NOTE_NOT_FOUND', 404);
        }
        // نویسنده/صاحب تسک فقط تا وقتی هنوز به تسک دسترسی دارد (مثلاً بعد از حذف از پروژه نه)
        $task = TaskModel::raw((int) $n['task_id']);
        if ($task === null || !Access::canTask($task, $actor, 'view')) {
            return self::err('یادداشت یافت نشد.', 'NOTE_NOT_FOUND', 404);
        }
        if ((int) $n['user_id'] !== $actor && (int) $n['task_owner_id'] !== $actor) {
            return self::err('فقط نویسنده‌ی یادداشت یا صاحب تسک می‌تواند آن را ویرایش کند.', 'FORBIDDEN', 403);
        }
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 5000) {
            return self::err('متن یادداشت نامعتبر است.', 'NOTE_INVALID');
        }
        DB::run('UPDATE task_notes SET note = ?, updated_at = NOW() WHERE id = ?', [$text, $noteId]);
        TaskHistory::log((int) $n['task_id'], $actor, 'note_edited', ['author' => (int) $n['user_id']]);
        return self::res(true, 'یادداشت ویرایش شد.');
    }

    public static function deleteNote(int $actor, int $noteId): array
    {
        $n = NoteModel::find($noteId);
        if ($n === null) {
            return self::err('یادداشت یافت نشد.', 'NOTE_NOT_FOUND', 404);
        }
        $task = TaskModel::raw((int) $n['task_id']);
        if ($task === null || !Access::canTask($task, $actor, 'view')) {
            return self::err('یادداشت یافت نشد.', 'NOTE_NOT_FOUND', 404);
        }
        if ((int) $n['user_id'] !== $actor && !Access::canTask($task, $actor, 'edit_meta')) {
            return self::err('اجازه‌ی حذف این یادداشت را ندارید.', 'FORBIDDEN', 403);
        }
        NoteModel::deleteNote($noteId);
        TaskHistory::log((int) $n['task_id'], $actor, 'note_deleted', ['text' => mb_substr((string) $n['note'], 0, 300)]);
        return self::res(true, 'یادداشت حذف شد.');
    }

    public static function addTaskFiles(int $actor, int $taskId, array $files): array
    {
        $t = TaskModel::raw($taskId);
        if ($t === null || !Access::canTask($t, $actor, 'view') && !Access::canTask($t, $actor, 'edit_info')) {
            return self::err('تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        if (!Access::canTask($t, $actor, 'edit_info')) {
            return self::err('اجازه‌ی افزودن پیوست ندارید.', 'FORBIDDEN', 403);
        }
        if (!$files) {
            return self::err('فایلی انتخاب نشده است.', 'NO_FILE');
        }
        [$saved, $errors] = NoteModel::storeAttachments('task', $taskId, $actor, $files);
        if (!$saved) {
            return self::err(implode('، ', $errors) ?: 'آپلود ناموفق بود.', 'UPLOAD_FAILED');
        }
        return self::res(true, 'فایل‌ها پیوست شد.', '', 201, ['attachments' => $saved, 'upload_errors' => $errors]);
    }

    public static function deleteFile(int $actor, string $kind, int $fileId): array
    {
        if ($kind === 'task') {
            $f = DB::one('SELECT a.*, t.id AS tid, t.user_id AS owner_id, t.assignee_id, t.project_id FROM task_attachments a INNER JOIN tasks t ON t.id = a.task_id WHERE a.id = ?', [$fileId]);
            $type = 'task_attachments';
            $table = 'task_attachments';
        } else {
            $f = DB::one('SELECT a.*, t.id AS tid, t.user_id AS owner_id, t.assignee_id, t.project_id FROM note_attachments a INNER JOIN task_notes n ON n.id = a.note_id INNER JOIN tasks t ON t.id = n.task_id WHERE a.id = ?', [$fileId]);
            $type = 'note_attachments';
            $table = 'note_attachments';
        }
        if ($f === null) {
            return self::err('فایل یافت نشد.', 'FILE_NOT_FOUND', 404);
        }
        $row = ['user_id' => $f['owner_id'], 'assignee_id' => $f['assignee_id'], 'project_id' => $f['project_id']];
        if (!Access::canTask($row, $actor, 'view')) {
            return self::err('فایل یافت نشد.', 'FILE_NOT_FOUND', 404);   // بعد از از دست دادن دسترسی به تسک، فایل خودش را هم حذف نمی‌کند
        }
        // پیوست تسک: آپلودکننده یا دارنده‌ی «ویرایش اطلاعات تسک» — پیوست یادداشت: آپلودکننده یا سازنده‌ی تسک/پروژه
        $allowed = (int) $f['user_id'] === $actor || Access::canTask($row, $actor, $kind === 'task' ? 'edit_info' : 'edit_meta');
        if (!$allowed) {
            return self::err('اجازه‌ی حذف این فایل را ندارید.', 'FORBIDDEN', 403);
        }
        DB::run("DELETE FROM `$table` WHERE id = ?", [$fileId]);
        TaskHistory::log((int) $f['tid'], $actor, 'file_deleted', ['name' => (string) $f['file_name'], 'note' => $kind !== 'task']);
        \App\Core\Uploader::delete($type, $f['file_path']);
        return self::res(true, 'فایل حذف شد.');
    }

    /** اعضای پروژه (سازنده + اعضا) به‌جز خود کاربر — برای منشن در یادداشت‌ها. */
    public static function mentionable(int $projectId, int $exceptUid): array
    {
        $rows = DB::all("SELECT u.id, u.first_name, u.last_name, u.avatar FROM users u
            WHERE u.status = 'active' AND u.is_bot = 0 AND u.id <> ?
              AND (u.id = (SELECT user_id FROM projects WHERE id = ?) OR u.id IN (SELECT user_id FROM project_members WHERE project_id = ?))
            ORDER BY u.first_name, u.last_name", [$exceptUid, $projectId, $projectId]);
        return array_map(static fn($u) => ['id' => (int) $u['id'], 'name' => full_name($u), 'avatar' => avatar_url($u['avatar'])], $rows);
    }

    /** گزینه‌های «امنیت تسک‌ها» در پروفایل: [عنوان، توضیح] */
    public const LINK_ACCESS = [
        'members'    => ['اعضای پروژه', 'اعضای پروژه‌ی همان تسک (پیش‌فرض)'],
        'colleagues' => ['همکاران', 'افرادی که در فهرست همکاران شما هستند'],
        'me'         => ['فقط من', 'فقط شما، مسئول تسک و اعضای پروژه که دسترسی تسک دارند'],
        'all'        => ['همه', 'هر کاربر واردشده‌ای که لینک را داشته باشد'],
    ];

    /** آیا کاربر می‌تواند تسک را (با لینک اشتراکی) ببیند؟ بر اساس تنظیم «امنیت تسک‌ها»ی سازنده‌ی تسک. */
    public static function canViewShared(array $task, int $uid): bool
    {
        if (Access::canTask($task, $uid, 'view')) {
            return true;      // سازنده، مدیر پروژه، مسئول، عضو دارای دسترسی
        }
        $owner = (int) $task['user_id'];
        $mode = (string) (DB::value('SELECT task_link_access FROM users WHERE id = ?', [$owner]) ?: 'members');
        switch ($mode) {
            case 'all':
                return true;
            case 'colleagues':
                return Access::isColleague($owner, $uid);
            case 'me':
                return false;
            default:
                return $task['project_id'] !== null && Access::isProjectMember((int) $task['project_id'], $uid);
        }
    }

    /** مجوزهای مؤثر کاربر روی تسک (برای UI). */
    public static function effective(array $task, int $uid): array
    {
        $have = Access::taskPerms($task, $uid);
        $out = [];
        foreach (array_merge(array_keys(Access::TASK_PERMS), Access::TASK_OWNER_ONLY) as $a) {
            $out[$a] = in_array($a, $have, true);
        }
        // هر نوع ویرایشی که فرم ویرایش را لازم دارد
        $out['edit'] = $out['edit_info'] || $out['edit_due'] || $out['edit_meta'] || $out['reassign'] || $out['change_project'];
        return $out;
    }
}
