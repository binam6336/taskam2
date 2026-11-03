<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\CallModel;
use App\Models\TaskModel;

/**
 * «مسدود کردن» به‌جای حذف (v4.0): کاربر تسک، پروژه، لیبل و درخواست تماس را حذف نمی‌کند، مسدود می‌کند.
 * ردیف در دیتابیس می‌ماند (blocked_at, blocked_by)، از همه‌ی فهرست‌ها، شمارنده‌ها، بورد، تقویم و انتخاب‌گرها کنار می‌رود
 * و فقط در پنجره‌ی «مسدودشده‌ها»ی همان صفحه دیده می‌شود؛ «رفع مسدودی» همه‌چیز را مثل قبل برمی‌گرداند.
 *  - task: مجوز «مسدود کردن تسک» (Access::TASK_PERMS block؛ سازنده‌ی تسک/پروژه همیشه). تسک مسدود فقط‌خواندنی است.
 *  - project: فقط سازنده‌ی پروژه. پروژه‌ی مسدود از فهرست همه‌ی اعضا کنار می‌رود و تسک یا عضو جدید نمی‌گیرد؛ تسک‌های قبلی‌اش می‌مانند.
 *  - label: صاحب لیبل. لیبل مسدود برای تسک تازه انتخاب نمی‌شود؛ تسک‌هایی که دارندش همچنان نشانش می‌دهند.
 *  - call: ثبت‌کننده‌ی درخواست تماس (Access::can).
 * فقط «متن‌های آماده» همچنان حذف می‌شوند.
 */
final class BlockService
{
    /** نوع => [جدول، عنوان نوع، پیام مسدودی، پیام رفع مسدودی، خطای پیدا نشدن] */
    public const KINDS = [
        'task'    => ['tasks', 'تسک', 'تسک مسدود شد.', 'مسدودی تسک برداشته شد.', 'تسک یافت نشد.', 'TASK_NOT_FOUND'],
        'project' => ['projects', 'پروژه', 'پروژه مسدود شد.', 'مسدودی پروژه برداشته شد.', 'پروژه یافت نشد.', 'PROJECT_NOT_FOUND'],
        'label'   => ['subjects', 'لیبل', 'لیبل مسدود شد.', 'مسدودی لیبل برداشته شد.', 'لیبل یافت نشد.', 'SUBJECT_NOT_FOUND'],
        'call'    => ['call_requests', 'درخواست تماس', 'درخواست تماس مسدود شد.', 'مسدودی درخواست تماس برداشته شد.', 'درخواست یافت نشد.', 'CALL_NOT_FOUND'],
    ];

    private static function res(bool $ok, string $msg = '', string $code = '', int $http = 200, $data = null): array
    {
        return ['ok' => $ok, 'message' => $msg, 'code' => $code, 'http' => $http, 'data' => $data];
    }

    public static function isBlocked(string $kind, int $id): bool
    {
        $k = self::KINDS[$kind] ?? null;
        return $k !== null && $id > 0 && DB::value('SELECT blocked_at FROM `' . $k[0] . '` WHERE id = ?', [$id]) !== null;
    }

    /** ردیف + [می‌بیند؟، می‌تواند مسدود/رفع مسدودی کند؟] */
    private static function load(string $kind, int $id, int $uid): array
    {
        switch ($kind) {
            case 'task':
                $r = TaskModel::raw($id);
                return [$r, $r !== null && (Access::canTask($r, $uid, 'view') || Access::canTask($r, $uid, 'block')), $r !== null && Access::canTask($r, $uid, 'block')];
            case 'project':
                $r = DB::one('SELECT * FROM projects WHERE id = ?', [$id]);
                $own = $r !== null && (int) $r['user_id'] === $uid;
                return [$r, $r !== null && ($own || Access::isProjectMember($id, $uid)), $own];
            case 'label':
                $r = DB::one('SELECT * FROM subjects WHERE id = ?', [$id]);
                $own = $r !== null && (int) $r['user_id'] === $uid;
                return [$r, $own, $own];
            case 'call':
                $r = CallModel::find($id);
                return [$r, $r !== null && Access::can($r, $uid, 'view'), $r !== null && Access::can($r, $uid, 'block')];
        }
        return [null, false, false];
    }

    public static function block(string $kind, int $uid, int $id): array
    {
        return self::set($kind, $uid, $id, true);
    }

    public static function unblock(string $kind, int $uid, int $id): array
    {
        return self::set($kind, $uid, $id, false);
    }

    private static function set(string $kind, int $uid, int $id, bool $on): array
    {
        $k = self::KINDS[$kind] ?? null;
        if ($k === null) {
            return self::res(false, 'نوع نامعتبر است.', 'INVALID_KIND', 422);
        }
        [$row, $see, $can] = self::load($kind, $id, $uid);
        if ($row === null || !$see) {
            return self::res(false, $k[4], $k[5], 404);
        }
        if (!$can) {
            return self::res(false, $on ? 'اجازه‌ی مسدود کردن این ' . $k[1] . ' را ندارید.' : 'اجازه‌ی رفع مسدودی این ' . $k[1] . ' را ندارید.', 'FORBIDDEN', 403);
        }
        $is = !empty($row['blocked_at']);
        if ($is !== $on) {
            DB::run('UPDATE `' . $k[0] . '` SET blocked_at = ' . ($on ? 'NOW()' : 'NULL') . ', blocked_by = ? WHERE id = ?', [$on ? $uid : null, $id]);
            if ($kind === 'task') {
                TaskHistory::log($id, $uid, $on ? 'blocked' : 'unblocked');
            }
        }
        $at = DB::value('SELECT blocked_at FROM `' . $k[0] . '` WHERE id = ?', [$id]);
        return self::res(true, $on ? $k[2] : $k[3], '', 200, ['id' => $id, 'kind' => $kind, 'is_blocked' => $on, 'blocked_at' => $at]);
    }

    /**
     * فهرست مسدودشده‌های قابل دیدن برای کاربر (جدیدترین اول).
     * هر ردیف: id, title, sub (توضیح کوتاه), blocked_at, blocked_by_name, can_unblock, token (برای باز کردن تسک)
     */
    public static function list(string $kind, int $uid, int $limit = 200, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);
        $who = 'LEFT JOIN users ub ON ub.id = x.blocked_by';
        $out = [];
        switch ($kind) {
            case 'task':
                $rows = DB::all("SELECT x.*, p.title AS project_title, ub.first_name AS bf, ub.last_name AS bl FROM tasks x LEFT JOIN projects p ON p.id = x.project_id $who
                    WHERE x.blocked_at IS NOT NULL AND (x.user_id = ? OR x.assignee_id = ? OR x.blocked_by = ? OR x.project_id IN (SELECT id FROM projects WHERE user_id = ?)
                        OR x.project_id IN (SELECT project_id FROM project_members WHERE user_id = ?))
                    ORDER BY x.blocked_at DESC, x.id DESC LIMIT 1000", [$uid, $uid, $uid, $uid, $uid]);
                foreach ($rows as $r) {
                    if (!Access::canTask($r, $uid, 'view') && !Access::canTask($r, $uid, 'block')) {
                        continue;
                    }
                    $out[] = ['id' => (int) $r['id'], 'title' => (string) $r['title'], 'sub' => $r['project_title'] ? 'پروژه: ' . $r['project_title'] : '',
                        'token' => $r['share_token'] ?: TaskModel::ensureShareToken((int) $r['id']), 'can_unblock' => Access::canTask($r, $uid, 'block')] + self::meta($r);
                }
                break;
            case 'project':
                foreach (DB::all("SELECT x.*, ub.first_name AS bf, ub.last_name AS bl, (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = x.id) AS members
                        FROM projects x $who WHERE x.blocked_at IS NOT NULL AND x.user_id = ? ORDER BY x.blocked_at DESC, x.id DESC LIMIT 1000", [$uid]) as $r) {
                    $out[] = ['id' => (int) $r['id'], 'title' => (string) $r['title'], 'sub' => fa_num((int) $r['members']) . ' عضو', 'can_unblock' => true] + self::meta($r);
                }
                break;
            case 'label':
                foreach (DB::all("SELECT x.*, ub.first_name AS bf, ub.last_name AS bl, (SELECT COUNT(*) FROM tasks t WHERE t.subject_id = x.id AND t.blocked_at IS NULL) AS n
                        FROM subjects x $who WHERE x.blocked_at IS NOT NULL AND x.user_id = ? ORDER BY x.blocked_at DESC, x.id DESC LIMIT 1000", [$uid]) as $r) {
                    $out[] = ['id' => (int) $r['id'], 'title' => (string) $r['title'], 'sub' => fa_num((int) $r['n']) . ' تسک', 'color' => (string) $r['color'], 'can_unblock' => true] + self::meta($r);
                }
                break;
            case 'call':
                foreach (DB::all("SELECT x.*, ub.first_name AS bf, ub.last_name AS bl FROM call_requests x $who
                        WHERE x.blocked_at IS NOT NULL AND (x.user_id = ? OR x.assignee_id = ?) ORDER BY x.blocked_at DESC, x.id DESC LIMIT 1000", [$uid, $uid]) as $r) {
                    $out[] = ['id' => (int) $r['id'], 'title' => trim($r['first_name'] . ' ' . $r['last_name']), 'sub' => (string) ($r['store'] ?: $r['mobile']),
                        'can_unblock' => Access::can($r, $uid, 'block')] + self::meta($r);
                }
                break;
        }
        return ['total' => count($out), 'items' => array_slice($out, $offset, $limit)];
    }

    private static function meta(array $r): array
    {
        return ['blocked_at' => (string) $r['blocked_at'], 'blocked_at_fa' => jdate((string) $r['blocked_at']),
            'blocked_by_name' => $r['bf'] !== null ? full_name(['first_name' => $r['bf'], 'last_name' => $r['bl']]) : ''];
    }
}
