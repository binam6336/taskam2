<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\TaskModel;

/**
 * برد تیمی (کانبان) — نسخه‌ی دمو.
 * هر تسک یک وضعیت کانبان دارد؛ «done» با is_completed همگام است.
 * دیدن کارت‌ها: تسک‌های خود کاربر + تسک‌های اعضایی که در «مشاهده‌ی تسک‌های» برایش انتخاب شده (Access::canBoardView).
 * جابه‌جایی با «دسترسی برد تیمی» هر پروژه است (Access::BOARD_PERMS، جدا از دسترسی تسک؛ پیش‌فرض بسته).
 * هر جابه‌جایی فقط یک مرحله جلو/عقب است، مگر با «جابه‌جایی آزاد».
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

    /** حداکثر کارت‌های ستون «انجام‌شده» (جدیدترین‌ها) تا برد شلوغ نشود */
    private const DONE_LIMIT = 30;

    private static function res(bool $ok, string $msg = '', string $code = '', int $http = 200, $data = null): array
    {
        return ['ok' => $ok, 'message' => $msg, 'code' => $code, 'http' => $http, 'data' => $data];
    }

    /** پروژه‌هایی که کاربر در آن‌هاست (سازنده یا عضو). */
    public static function projects(int $uid): array
    {
        return DB::all('SELECT p.id, p.title FROM projects p WHERE p.user_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?) ORDER BY p.title', [$uid, $uid]);
    }

    /**
     * کارت‌های برد، گروه‌بندی‌شده بر اساس وضعیت.
     * فقط تسک‌هایی که کاربر اجازه‌ی مشاهده‌شان را دارد (طبق دسترسی تسک پروژه).
     * @param array $f project (شناسه یا ۰)، mine (فقط مسئولیت من)، q (جستجو در عنوان)، focus (تسکی که برجسته می‌شود؛ همیشه نمایش داده می‌شود)
     */
    public static function columns(int $uid, array $f): array
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
        $rows = TaskModel::board($where, $params);

        $cols = array_fill_keys(array_keys(self::STATUSES), []);
        $focus = (int) ($f['focus'] ?? 0);
        foreach ($rows as $t) {
            $st = self::status($t);
            $isFocus = (int) $t['id'] === $focus;
            if ($st === 'done' && count($cols['done']) >= self::DONE_LIMIT && !$isFocus) {
                continue;
            }
            if (!Access::canBoardView($t, $uid)) {
                continue;
            }
            $t['moves'] = Access::boardTargets($t, $uid, $st, array_keys(self::STATUSES));
            $t['can_move'] = $t['moves'] !== [];
            $t['is_focus'] = $isFocus;
            $t['token'] = $t['share_token'] ?: TaskModel::ensureShareToken((int) $t['id']);
            $cols[$st][] = $t;
        }
        return $cols;
    }

    private static function status(array $t): string
    {
        return isset(self::STATUSES[$t['board_status'] ?? '']) ? $t['board_status'] : ((int) $t['is_completed'] === 1 ? 'done' : 'todo');
    }

    /** آیا تسک در برد کاربر دیده می‌شود؟ (برای آیکون «نمایش در برد» در جزئیات تسک) */
    public static function canSee(array $t, int $uid): bool
    {
        return Access::canBoardView($t, $uid);
    }

    /** انتقال کارت به ستون دیگر. */
    public static function move(int $actor, int $id, string $status, string $reason = ''): array
    {
        if (!isset(self::STATUSES[$status])) {
            return self::res(false, 'وضعیت نامعتبر است.', 'STATUS_INVALID', 422);
        }
        $t = TaskModel::raw($id);
        if ($t === null || !Access::canBoardView($t, $actor)) {
            return self::res(false, 'تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        $from = self::status($t);
        if ($from === $status) {
            return self::res(true, 'تسک همین حالا در «' . self::STATUSES[$status]['label'] . '» است.', '', 200, ['id' => $id, 'status' => $status, 'is_completed' => $status === 'done']);
        }
        if (!in_array($status, Access::boardTargets($t, $actor, $from, array_keys(self::STATUSES)), true)) {
            return self::res(false, 'اجازه‌ی انتقال این تسک از «' . self::STATUSES[$from]['label'] . '» به «' . self::STATUSES[$status]['label'] . '» را ندارید.', 'FORBIDDEN', 403);
        }
        $reason = trim(mb_substr($reason, 0, 255));
        $done = $status === 'done';
        DB::run('UPDATE tasks SET board_status = ?, blocked_reason = ?, board_moved_at = NOW(), is_completed = ?,
                   completed_at = CASE WHEN ? = 1 THEN COALESCE(completed_at, NOW()) ELSE NULL END WHERE id = ?',
            [$status, $status === 'blocked' && $reason !== '' ? $reason : null, $done ? 1 : 0, $done ? 1 : 0, $id]);
        return self::res(true, 'تسک به «' . self::STATUSES[$status]['label'] . '» منتقل شد.', '', 200, ['id' => $id, 'status' => $status, 'is_completed' => $done]);
    }
}
