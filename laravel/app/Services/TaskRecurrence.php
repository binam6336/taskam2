<?php
declare(strict_types=1);

namespace KarlixLaravel\Services;

use KarlixLaravel\Database\DB;
use App\Core\Validate;
use KarlixLaravel\Repositories\TaskModel;

/** تنظیمات مشترک سری + صدور حداکثر یک جانشین برای هر نوبت، مستقل از وضعیت نوبت قبلی. */
final class TaskRecurrence
{
    public const KINDS = ['daily' => 'روزانه', 'weekly' => 'هفتگی', 'monthly' => 'ماهانه', 'custom' => 'شخصی‌سازی'];
    public const FIELDS = ['repeat_enabled', 'repeat_kind', 'repeat_days'];

    public static function requested(array $in): bool
    {
        return (bool) array_intersect(self::FIELDS, array_keys($in));
    }

    public static function settings(array $task): ?array
    {
        if (empty($task['recurrence_id'])) {
            return null;
        }
        return DB::one('SELECT * FROM task_recurrences WHERE id = ?', [(int) $task['recurrence_id']]);
    }

    /** خروجی مجاز پنل/API؛ شناسه‌های داخلی سری و نوبت‌ها منتشر نمی‌شوند. */
    public static function present(array $task): array
    {
        $r = self::settings($task);
        return ['enabled' => $r !== null && (bool) $r['enabled'], 'kind' => $r['kind'] ?? 'daily',
            'days' => isset($r['interval_days']) ? (int) $r['interval_days'] : null];
    }

    /** رشته‌ی خطا یا تنظیمات معتبر؛ در ویرایش، فیلدهای ارسال‌نشده حفظ می‌شوند. */
    public static function validate(array $in, ?array $old, ?string $due)
    {
        $enabled = $old !== null && (bool) $old['enabled'];
        if (array_key_exists('repeat_enabled', $in)) {
            $v = $in['repeat_enabled'];
            if (!in_array($v, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
                return 'مقدار تکرار خودکار باید فعال یا غیرفعال باشد.';
            }
            $enabled = in_array($v, [true, 1, '1', 'true'], true);
        }
        $kind = array_key_exists('repeat_kind', $in) ? $in['repeat_kind'] : ($old['kind'] ?? 'daily');
        if (!is_string($kind) || !isset(self::KINDS[$kind])) {
            return 'بازه تکرار خودکار معتبر نیست.';
        }
        $days = array_key_exists('repeat_days', $in) ? $in['repeat_days'] : ($old['interval_days'] ?? null);
        if ($enabled && $kind === 'custom') {
            if ((!is_int($days) && !is_string($days)) || !preg_match('/^[0-9]+$/D', Validate::digits((string) $days))) {
                return 'فاصله تکرار باید یک عدد صحیح بزرگ‌تر از صفر باشد.';
            }
            $digits = ltrim(Validate::digits((string) $days), '0');
            if ($digits === '') {
                return 'فاصله تکرار باید یک عدد صحیح بزرگ‌تر از صفر باشد.';
            }
            if (strlen($digits) > 7) {
                return 'بازه تکرار از محدوده تاریخ پشتیبانی‌شده خارج است.';
            }
            $days = (int) $digits;
        } else {
            $days = $kind === 'custom' && isset($old['interval_days']) ? (int) $old['interval_days'] : null;
        }
        if ($enabled && ($due === null || Validate::date($due) === null)) {
            return 'برای تکرار خودکار، تاریخ اولین نوبت تسک را انتخاب کنید.';
        }
        if ($enabled && self::nextDate($due, $kind, (int) $days, (int) substr($due, 8, 2), $due) === null) {
            return 'بازه تکرار از محدوده تاریخ پشتیبانی‌شده خارج است.';
        }
        return ['enabled' => $enabled ? 1 : 0, 'kind' => $kind, 'interval_days' => $kind === 'custom' ? $days : null];
    }

    /** فقط در تراکنش TaskService؛ تنظیم از نوبت قدیمی نیز بر کل سری اثر دارد و مجوز نوبت جاری بررسی می‌شود. */
    public static function configure(array $task, array $config, int $actor, bool $dateChanged = false): ?string
    {
        $id = (int) $task['id'];
        $series = !empty($task['recurrence_id']) ? DB::one('SELECT * FROM task_recurrences WHERE id = ? FOR UPDATE', [(int) $task['recurrence_id']]) : null;
        if ($series !== null) {
            $current = (int) $series['current_task_id'] === $id ? $task : TaskModel::raw((int) $series['current_task_id']);
            if ($current === null || !Access::canTask($current, $actor, 'edit_due')) {
                return 'اجازه‌ی تغییر تکرار خودکار نوبت جاری این تسک را ندارید.';
            }
            if ($config['enabled'] && empty($current['due_date'])) {
                return 'برای تکرار خودکار، تاریخ اولین نوبت تسک را انتخاب کنید.';
            }
            $anchor = (int) $series['anchor_day'];
            if (($dateChanged && (int) $series['current_task_id'] === $id) || $config['kind'] !== $series['kind']) {
                $anchor = (int) substr((string) ((int) $series['current_task_id'] === $id ? $task['due_date'] : $current['due_date']), 8, 2);
            }
            DB::update('task_recurrences', $config + ['anchor_day' => $anchor ?: (int) $series['anchor_day']], 'id = ?', [(int) $series['id']]);
        } elseif ($config['enabled']) {
            $seriesId = DB::insert('task_recurrences', $config + ['current_task_id' => $id, 'anchor_day' => (int) substr((string) $task['due_date'], 8, 2)]);
            DB::update('tasks', ['recurrence_id' => $seriesId], 'id = ?', [$id]);
        } else {
            return null;
        }
        TaskHistory::log($id, $actor, 'recurrence', ['enabled' => (bool) $config['enabled'], 'kind' => $config['kind'], 'days' => $config['interval_days']]);
        return null;
    }

    /** تاریخ بعدی در تقویم میلادی؛ روز اصلی ماه حفظ می‌شود (۳۱ ژانویه ← آخر فوریه ← ۳۱ مارس). */
    public static function nextDate(string $base, string $kind, int $days, int $anchorDay, string $after): ?string
    {
        if (Validate::date($base) === null || Validate::date($after) === null || !isset(self::KINDS[$kind])) {
            return null;
        }
        $tz = new \DateTimeZone('UTC');
        $date = new \DateTimeImmutable($base, $tz);
        $floor = new \DateTimeImmutable(max($base, $after), $tz);
        if ($kind === 'monthly') {
            $month = (int) $date->format('Y') * 12 + (int) $date->format('n');
            $floorMonth = (int) $floor->format('Y') * 12 + (int) $floor->format('n') - 1;
            $month = max($month, $floorMonth);
            $year = intdiv($month, 12);
            $m = $month % 12 + 1;
            if ($year > 9999 || $anchorDay < 1 || $anchorDay > 31) {
                return null;
            }
            $first = $date->setDate($year, $m, 1);
            $next = $first->setDate($year, $m, min($anchorDay, (int) $first->format('t')));
            if ($next <= $floor) {
                $first = $first->modify('+1 month');
                $next = $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min($anchorDay, (int) $first->format('t')));
            }
        } else {
            $step = $kind === 'daily' ? 1 : ($kind === 'weekly' ? 7 : $days);
            if ($step < 1 || $step > 3652058) {
                return null;
            }
            $elapsed = (int) $date->diff($floor)->days;
            $jump = (intdiv($elapsed, $step) + 1) * $step;
            $next = $date->modify('+' . $jump . ' days');
        }
        return (int) $next->format('Y') <= 9999 ? $next->format('Y-m-d') : null;
    }

    /** اتمی و قابل تکرار؛ هم از پایان تکمیل سرور و هم از worker بازیابی فراخوانی می‌شود. */
    public static function generate(int $taskId, ?string $horizon = null): ?int
    {
        $hint = TaskModel::raw($taskId);
        if ($hint === null || empty($hint['recurrence_id'])) {
            return null;
        }
        return DB::transaction(static function () use ($taskId, $hint, $horizon): ?int {
            $r = DB::one('SELECT * FROM task_recurrences WHERE id = ? FOR UPDATE', [(int) $hint['recurrence_id']]);
            if ($r === null || !(bool) $r['enabled'] || (int) $r['current_task_id'] !== $taskId) {
                return null;
            }
            $t = DB::one('SELECT * FROM tasks WHERE id = ? FOR UPDATE', [$taskId]);
            if ($t === null || !empty($t['blocked_at']) || empty($t['due_date'])
                || DB::value('SELECT id FROM tasks WHERE recurrence_previous_id = ?', [$taskId])) {
                return null;
            }
            $owner = (int) $t['user_id'];
            $assignee = (int) ($t['assignee_id'] ?: $owner);
            $project = (int) $t['project_id'];
            if (($project > 0 && (BlockService::isBlocked('project', $project) || !Access::isProjectMember($project, $owner)
                || !Access::isProjectMember($project, $assignee) || ($owner !== $assignee && !Access::canAssignOnCreate($project, $owner))))
                || ($project <= 0 && !Access::canAssignTo($owner, $assignee, null)) || Access::isBlockedBetween($owner, $assignee)
                || (int) DB::value("SELECT COUNT(*) FROM users WHERE id IN (?, ?) AND status = 'active' AND is_bot = 0", [$owner, $assignee]) !== ($owner === $assignee ? 1 : 2)) {
                return null;
            }
            $next = self::nextDate((string) $t['due_date'], (string) $r['kind'], (int) $r['interval_days'], (int) $r['anchor_day'], (string) $t['due_date']);
            if ($next === null || ($horizon !== null && $next > $horizon) || Limits::create('task', $owner) !== null) {
                return null;
            }
            $copy = array_intersect_key($t, array_flip(['user_id', 'assignee_id', 'project_id', 'subject_id', 'title', 'description', 'priority', 'priority_percent', 'due_time', 'reminder_every', 'reminder_unit', 'is_confidential']));
            $nextId = TaskModel::create($copy + ['due_date' => $next, 'recurrence_id' => (int) $r['id'], 'recurrence_previous_id' => $taskId]);
            if (!empty($t['is_confidential'])) {
                foreach (DB::column('SELECT user_id FROM task_viewers WHERE task_id = ?', [$taskId]) as $viewer) {
                    DB::insert('task_viewers', ['task_id' => $nextId, 'user_id' => (int) $viewer]);
                }
            }
            DB::update('task_recurrences', ['current_task_id' => $nextId], 'id = ?', [(int) $r['id']]);
            TaskHistory::log($taskId, null, 'recurrence_next', ['due' => $next, 'due_fa' => jdate($next . ' 00:00:00', false)]);
            TaskHistory::log($nextId, null, 'recurrence_created', ['due' => $next, 'due_fa' => jdate($next . ' 00:00:00', false)]);
            $fresh = TaskModel::raw($nextId);
            Notifier::taskAssigned($fresh, $owner, (string) DB::value('SELECT title FROM projects WHERE id = ?', [$project]));
            return $nextId;
        });
    }

    /** پیش از خواندن پنل/API؛ حداکثر یک اجرا در دقیقه برای همه کاربران این نصب. */
    public static function warm(): void
    {
        $path = STORAGE_PATH . '/cache/recurrence-materialize.lock';
        $f = @fopen($path, 'c+');
        if (!$f) { return; }
        try {
            if (!flock($f, LOCK_EX | LOCK_NB)) { return; }
            $last = (int) stream_get_contents($f);
            if (time() - $last < 60) { return; }
            self::recover(100);
            rewind($f); ftruncate($f, 0); fwrite($f, (string) time());
        } finally { fclose($f); }
    }

    /** بدون اتکا به نشست یا مرورگر؛ خطای یک سری مانع بازیابی سری‌های دیگر نمی‌شود. */
    public static function recover(int $limit = 100): int
    {
        $count = 0;
        $limit = max(1, min(1000, $limit));
        $horizon = date('Y-m-d', strtotime('+31 days'));
        $end = microtime(true) + 8;
        do {
            $progress = 0;
            $ids = DB::column('SELECT t.id FROM task_recurrences r INNER JOIN tasks t ON t.id = r.current_task_id WHERE r.enabled = 1 AND t.blocked_at IS NULL AND t.due_date < ? ORDER BY r.updated_at, r.id LIMIT ' . min(100, $limit - $count), [$horizon]);
            foreach ($ids as $id) {
                try {
                    if (self::generate((int) $id, $horizon) !== null) { ++$count; ++$progress; }
                    else { DB::run('UPDATE task_recurrences SET updated_at = NOW() WHERE current_task_id = ?', [(int) $id]); }
                } catch (\Throwable $e) { \App\Core\Log::error($e, 'tasks', ['step' => 'recurrence', 'task' => (int) $id]); }
                if ($count >= $limit || microtime(true) >= $end) { break; }
            }
        } while ($progress > 0 && $count < $limit && microtime(true) < $end);
        return $count;
    }
}
