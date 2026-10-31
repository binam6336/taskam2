<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\TaskModel;

/**
 * بورد پروژه (کانبان).
 * هر تسک یک وضعیت کانبان دارد؛ «done» با is_completed همگام است.
 * دیدن کارت‌ها: تسک‌های خود کاربر + تسک‌های اعضایی که در «مشاهده‌ی تسک‌های» برایش انتخاب شده (Access::canBoardView).
 * جابه‌جایی با «دسترسی بورد پروژه» هر پروژه است (Access::BOARD_PERMS، جدا از دسترسی تسک؛ پیش‌فرض بسته).
 * هر جابه‌جایی فقط یک مرحله جلو/عقب است، مگر با «جابه‌جایی آزاد».
 * آرشیو: تسک انجام‌شده با archived_at؛ ستون جدا بعد از «انجام‌شده». فقط نمایش برد را مرتب می‌کند و روی خود تسک
 * (انجام‌شدن، لیست‌ها، گزارش‌ها) اثری ندارد؛ پس هر کسی که کارت را در برد می‌بیند می‌تواند آرشیو/بازگردانی کند.
 */
final class BoardService
{
    public const STATUSES = [
        'backlog'     => ['label' => 'برنامه‌ریزی‌نشده', 'en' => 'Backlog', 'icon' => 'fa-inbox', 'tone' => 'muted', 'desc' => 'ثبت شده ولی هنوز برای انجام انتخاب نشده'],
        'todo'        => ['label' => 'آماده انجام', 'en' => 'To Do', 'icon' => 'fa-list-ul', 'tone' => 'info', 'desc' => 'انتخاب شده، کار هنوز شروع نشده'],
        'in_progress' => ['label' => 'در حال انجام', 'en' => 'In Progress', 'icon' => 'fa-person-running', 'tone' => 'warn', 'desc' => 'مسئول کار را شروع کرده'],
        'blocked'     => ['label' => 'مسدودشده', 'en' => 'Blocked', 'icon' => 'fa-ban', 'tone' => 'danger', 'desc' => 'متوقف به‌خاطر وابستگی، مشکل فنی یا انتظار'],
        'review'      => ['label' => 'در انتظار بررسی', 'en' => 'In Review', 'icon' => 'fa-magnifying-glass', 'tone' => 'purple', 'desc' => 'انجام شده و منتظر بررسی'],
        'done'        => ['label' => 'انجام‌شده', 'en' => 'Done', 'icon' => 'fa-circle-check', 'tone' => 'ok', 'desc' => 'تکمیل شده و معیارهای اتمام برآورده شده'],
    ];

    /** وضعیت‌هایی که برای هر عضو سقف تعداد تسک دارند (برنامه‌ریزی‌نشده، انجام‌شده و آرشیو سقف ندارند) */
    public const LIMITED = ['todo', 'in_progress', 'blocked', 'review'];
    public const MAX_LIMIT = 999;

    /** project_members.board_limits → [status => n]؛ فقط وضعیت‌های LIMITED و اعداد ۰ تا MAX_LIMIT */
    public static function decodeLimits(?string $raw): array
    {
        $a = json_decode((string) $raw, true);
        $out = [];
        foreach (self::LIMITED as $s) {
            if (is_array($a) && isset($a[$s]) && is_numeric($a[$s])) {
                $out[$s] = max(0, min(self::MAX_LIMIT, (int) $a[$s]));
            }
        }
        return $out;
    }

    /**
     * سقف پر شده؟ تسک‌های مسئولِ این تسک (یا سازنده‌اش اگر مسئول ندارد) در همان ستون همان پروژه شمرده می‌شوند.
     * خروجی: پیام خطا یا null
     */
    private static function limitError(array $t, string $status): ?string
    {
        if (!in_array($status, self::LIMITED, true) || empty($t['project_id'])) {
            return null;
        }
        $owner = (int) ($t['assignee_id'] ?: $t['user_id']);
        $limit = self::decodeLimits(DB::value('SELECT board_limits FROM project_members WHERE project_id = ? AND user_id = ?', [(int) $t['project_id'], $owner]))[$status] ?? null;
        if ($limit === null) {
            return null;
        }
        $n = (int) DB::value('SELECT COUNT(*) FROM tasks WHERE project_id = ? AND COALESCE(assignee_id, user_id) = ? AND board_status = ? AND archived_at IS NULL AND id <> ?',
            [(int) $t['project_id'], $owner, $status, (int) $t['id']]);
        if ($n < $limit) {
            return null;
        }
        $name = full_name(DB::one('SELECT first_name, last_name FROM users WHERE id = ?', [$owner]) ?? []);
        return 'سقف ستون «' . self::STATUSES[$status]['label'] . '» برای ' . $name . ' پر است (حداکثر ' . fa_num($limit) . ' تسک).';
    }

    /**
     * ستون‌هایی که تسک‌های خود کاربر در آن‌ها از سقفش بیشتر است (مثلاً سقف بعداً از ۱۰ به ۵ کم شده و ۷ تسک دارد).
     * فقط پروژه‌ی فیلترشده یا همه‌ی پروژه‌های کاربر. خروجی: status => [[project, count, limit], ...]
     */
    public static function overLimits(int $uid, int $projectId = 0): array
    {
        $sql = 'SELECT pm.project_id, pm.board_limits, p.title FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.user_id = ? AND pm.board_limits IS NOT NULL';
        $params = [$uid];
        if ($projectId > 0) {
            $sql .= ' AND pm.project_id = ?';
            $params[] = $projectId;
        }
        $out = [];
        foreach (DB::all($sql, $params) as $m) {
            $limits = self::decodeLimits($m['board_limits']);
            if (!$limits) {
                continue;
            }
            $counts = [];
            foreach (DB::all('SELECT board_status s, COUNT(*) n FROM tasks WHERE project_id = ? AND COALESCE(assignee_id, user_id) = ? AND archived_at IS NULL GROUP BY board_status', [(int) $m['project_id'], $uid]) as $r) {
                $counts[$r['s']] = (int) $r['n'];
            }
            foreach ($limits as $st => $limit) {
                if (($counts[$st] ?? 0) > $limit) {
                    $out[$st][] = ['project' => (string) $m['title'], 'count' => $counts[$st], 'limit' => $limit];
                }
            }
        }
        return $out;
    }

    /** حداکثر کارت‌های ستون «انجام‌شده» (جدیدترین‌ها) تا برد شلوغ نشود */
    private const DONE_LIMIT = 30;

    private static function res(bool $ok, string $msg = '', string $code = '', int $http = 200, $data = null): array
    {
        return ['ok' => $ok, 'message' => $msg, 'code' => $code, 'http' => $http, 'data' => $data];
    }

    /** پروژه‌هایی که کاربر در آن‌هاست (سازنده یا عضو). */
    public static function projects(int $uid): array
    {
        return DB::all('SELECT p.id, p.title, p.profile_image FROM projects p WHERE p.user_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?) ORDER BY p.title', [$uid, $uid]);
    }

    /** WHERE مشترک برد و آرشیو با فیلترها (اولین پارامتر = بیننده برای دسترسی تسک). */
    private static function where(int $uid, array $f): array
    {
        $where = '(t.user_id = ? OR t.assignee_id = ? OR t.project_id IN (SELECT id FROM projects WHERE user_id = ?) OR t.project_id IN (SELECT project_id FROM project_members WHERE user_id = ?))';
        $params = [$uid, $uid, $uid, $uid, $uid];
        if (!empty($f['project'])) {
            $where .= ' AND t.project_id = ?';
            $params[] = (int) $f['project'];
        }
        if (!empty($f['mine'])) {
            $where .= ' AND t.assignee_id = ?';
            $params[] = $uid;
        }
        if (($f['q'] ?? '') !== '') {
            $where .= ' AND t.title LIKE ?';
            $params[] = '%' . addcslashes((string) $f['q'], '%_\\') . '%';
        }
        return [$where, $params];
    }

    /** آماده‌سازی کارت برای نما: مقصدهای مجاز، لینک و برجستگی */
    private static function card(array $t, int $uid, string $st, int $focus): array
    {
        $t['moves'] = self::targets($t, $uid, $st);
        $t['can_move'] = $t['moves'] !== [];
        $t['is_focus'] = (int) $t['id'] === $focus;
        $t['token'] = $t['share_token'] ?: TaskModel::ensureShareToken((int) $t['id']);
        return $t;
    }

    /** مقصدهای مجاز کارت: مراحل طبق دسترسی برد + آرشیو (از انجام‌شده) / بازگشت به انجام‌شده (از آرشیو) */
    private static function targets(array $t, int $uid, string $st): array
    {
        if ($st === 'archived') {
            return self::canArchive($t, $uid) ? ['done'] : [];
        }
        $out = Access::boardTargets($t, $uid, $st, array_keys(self::STATUSES));
        if ($st === 'done' && self::canArchive($t, $uid)) {
            $out[] = 'archived';
        }
        return $out;
    }

    /** آرشیو کردن / برگرداندن از آرشیو: فقط مخصوص برد است، پس هر کسی که کارت را در برد می‌بیند */
    public static function canArchive(array $t, int $uid): bool
    {
        return Access::canBoardView($t, $uid);
    }

    /**
     * کارت‌های برد، گروه‌بندی‌شده بر اساس وضعیت (بدون آرشیوشده‌ها).
     * فقط تسک‌هایی که کاربر اجازه‌ی دیدنشان را دارد (Access::canBoardView).
     * @param array $f project (شناسه یا ۰)، mine (فقط مسئولیت من)، q (جستجو در عنوان)، focus (تسکی که برجسته می‌شود؛ همیشه نمایش داده می‌شود)
     * @return array{0: array, 1: int} [ستون‌ها، تعداد تسک‌های انجام‌شده‌ای که کاربر می‌تواند آرشیو کند]
     */
    public static function columns(int $uid, array $f): array
    {
        [$where, $params] = self::where($uid, $f);
        $rows = TaskModel::board($where . ' AND t.archived_at IS NULL', $params);
        // کارت برجسته همیشه هست، حتی اگر بیرون از سقف تعداد کارت‌ها افتاده باشد
        $focus = (int) ($f['focus'] ?? 0);
        if ($focus > 0 && !in_array($focus, array_map(static fn($r) => (int) $r['id'], $rows), true)) {
            $rows = array_merge($rows, TaskModel::board('t.id = ? AND t.archived_at IS NULL', [$uid, $focus]));
        }

        $cols = array_fill_keys(array_keys(self::STATUSES), []);
        $archivable = 0;
        foreach ($rows as $t) {
            if (!Access::canBoardView($t, $uid)) {
                continue;
            }
            $st = self::status($t);
            if ($st === 'done' && self::canArchive($t, $uid)) {
                $archivable++;
            }
            if ($st === 'done' && count($cols['done']) >= self::DONE_LIMIT && (int) $t['id'] !== $focus) {
                continue;
            }
            $cols[$st][] = self::card($t, $uid, $st, $focus);
        }
        return [$cols, $archivable];
    }

    /**
     * آرشیو: جدیدترین‌ها اول.
     * @return array{total:int, cards:array} cards = $limit مورد اول (۰ = همه)
     */
    public static function archive(int $uid, array $f, int $limit = 5): array
    {
        [$where, $params] = self::where($uid, $f);
        $total = 0;
        $cards = [];
        $focus = (int) ($f['focus'] ?? 0);
        foreach (TaskModel::archived($where, $params) as $t) {
            if (!Access::canBoardView($t, $uid)) {
                continue;
            }
            $total++;
            if ($limit === 0 || count($cards) < $limit || (int) $t['id'] === $focus) {
                $cards[] = self::card($t, $uid, 'archived', $focus);
            }
        }
        return ['total' => $total, 'cards' => $cards];
    }

    /** همه‌ی تسک‌های انجام‌شده‌ی برد (با همان فیلترها) که کاربر اجازه‌اش را دارد → آرشیو */
    public static function archiveDone(int $uid, array $f): array
    {
        [$where, $params] = self::where($uid, $f);
        $ids = [];
        foreach (TaskModel::board($where . " AND t.archived_at IS NULL AND (t.board_status = 'done' OR t.is_completed = 1)", $params, 5000) as $t) {
            if (self::status($t) === 'done' && Access::canBoardView($t, $uid) && self::canArchive($t, $uid)) {
                $ids[] = (int) $t['id'];
            }
        }
        if (!$ids) {
            return self::res(false, 'تسک انجام‌شده‌ای برای آرشیو نیست.', 'NOTHING_TO_ARCHIVE', 422);
        }
        foreach (array_chunk($ids, 500) as $chunk) {
            DB::run('UPDATE tasks SET archived_at = NOW() WHERE archived_at IS NULL AND id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')', $chunk);
        }
        foreach ($ids as $tid) {
            TaskHistory::log($tid, $uid, 'archived');
        }
        return self::res(true, fa_num(count($ids)) . ' تسک انجام‌شده به آرشیو منتقل شد.', '', 200, ['archived' => count($ids)]);
    }

    private static function status(array $t): string
    {
        if (!empty($t['archived_at'])) {
            return 'archived';
        }
        return isset(self::STATUSES[$t['board_status'] ?? '']) ? $t['board_status'] : ((int) $t['is_completed'] === 1 ? 'done' : 'todo');
    }

    /** آیا تسک در برد کاربر دیده می‌شود؟ (برای آیکون «نمایش در برد» در جزئیات تسک) */
    public static function canSee(array $t, int $uid): bool
    {
        return Access::canBoardView($t, $uid);
    }

    /** انتقال کارت به ستون دیگر. */
    /** $fromTask: از تیک «انجام شد» در لیست تسک‌ها (دیدن تسک قبلاً با مجوز view بررسی شده؛ دیدن کارت در برد لازم نیست) */
    public static function move(int $actor, int $id, string $status, string $reason = '', bool $fromTask = false): array
    {
        if (!isset(self::STATUSES[$status]) && $status !== 'archived') {
            return self::res(false, 'وضعیت نامعتبر است.', 'STATUS_INVALID', 422);
        }
        $t = TaskModel::raw($id);
        if ($t === null || (!$fromTask && !Access::canBoardView($t, $actor))) {
            return self::res(false, 'تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        $from = self::status($t);
        // آرشیو: فقط از «انجام‌شده» به آرشیو و از آرشیو به «انجام‌شده»
        if ($status === 'archived' || $from === 'archived') {
            if ($from === $status) {
                return self::res(true, 'تسک همین حالا در آرشیو است.', '', 200, ['id' => $id, 'status' => $status, 'is_completed' => true]);
            }
            if (!in_array($status, self::targets($t, $actor, $from), true)) {
                return self::res(false, $from === 'archived' ? 'تسک آرشیوشده فقط به «انجام‌شده» برمی‌گردد.' : 'فقط تسک‌های انجام‌شده آرشیو می‌شوند.', 'FORBIDDEN', 403);
            }
            DB::run('UPDATE tasks SET archived_at = ' . ($status === 'archived' ? 'NOW()' : 'NULL') . ', board_moved_at = NOW() WHERE id = ?', [$id]);
            TaskHistory::log($id, $actor, $status === 'archived' ? 'archived' : 'unarchived');
            return self::res(true, $status === 'archived' ? 'تسک به آرشیو منتقل شد.' : 'تسک از آرشیو به «انجام‌شده» برگشت.', '', 200, ['id' => $id, 'status' => $status, 'is_completed' => true]);
        }
        if ($from === $status) {
            return self::res(true, 'تسک همین حالا در «' . self::STATUSES[$status]['label'] . '» است.', '', 200, ['id' => $id, 'status' => $status, 'is_completed' => $status === 'done']);
        }
        if ($status === 'done' && !Access::canTask($t, $actor, 'complete')) {
            return self::res(false, 'شما دسترسی لازم برای تکمیل تسک را ندارید.', 'FORBIDDEN', 403);
        }
        if (!in_array($status, Access::boardTargets($t, $actor, $from, array_keys(self::STATUSES)), true)) {
            return self::res(false, 'اجازه‌ی انتقال این تسک از «' . self::STATUSES[$from]['label'] . '» به «' . self::STATUSES[$status]['label'] . '» را ندارید.', 'FORBIDDEN', 403);
        }
        if ($err = self::limitError($t, $status)) {
            return self::res(false, $err, 'BOARD_LIMIT', 409);
        }
        $reason = trim(mb_substr($reason, 0, 255));
        $done = $status === 'done';
        DB::run('UPDATE tasks SET board_status = ?, blocked_reason = ?, board_moved_at = NOW(), archived_at = NULL, is_completed = ?,
                   completed_at = CASE WHEN ? = 1 THEN COALESCE(completed_at, NOW()) ELSE NULL END WHERE id = ?',
            [$status, $status === 'blocked' && $reason !== '' ? $reason : null, $done ? 1 : 0, $done ? 1 : 0, $id]);
        TaskHistory::log($id, $actor, 'board', ['from' => $from, 'to' => $status, 'reason' => $status === 'blocked' && $reason !== '' ? $reason : null]);
        return self::res(true, 'تسک به «' . self::STATUSES[$status]['label'] . '» منتقل شد.', '', 200, ['id' => $id, 'status' => $status, 'is_completed' => $done]);
    }
}
