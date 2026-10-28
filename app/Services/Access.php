<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * قوانین دسترسی مشترک بین وب و API (تنها منبع حقیقت).
 *
 * تسک‌ها (از v3.0 دسترسی به ازای هر پروژه است، نه همکار):
 *  - سازنده‌ی تسک و سازنده‌ی پروژه‌ی آن: همه‌ی دسترسی‌ها
 *  - عضو پروژه: فقط دسترسی‌هایی که در همان پروژه برایش فعال شده (project_members.task_permissions؛ پیش‌فرض هیچ)
 *  - مسئول تسک: مشاهده (+ دسترسی‌های عضویتش در پروژه)
 * درخواست تماس: سازنده همه‌چیز؛ مسئول مشاهده/تکمیل.
 */
final class Access
{
    /** دسترسی‌های قابل اعطا روی تسک‌های یک پروژه */
    public const TASK_PERMS = [
        'edit_info' => ['label' => 'ویرایش اطلاعات تسک', 'icon' => 'fa-pen', 'desc' => 'فقط عنوان، توضیحات و فایل‌های پیوست'],
        'edit_due'  => ['label' => 'ویرایش زمان انجام', 'icon' => 'fa-calendar-days', 'desc' => 'تغییر تاریخ و ساعت انجام (سررسید)'],
        'reassign'  => ['label' => 'تغییر مسئول', 'icon' => 'fa-user-group', 'desc' => 'تغییر مسئول انجام تسک'],
        'notes'     => ['label' => 'نوشتن یادداشت', 'icon' => 'fa-comment-dots', 'desc' => 'ثبت یادداشت و گزارش پیشرفت'],
        'complete'  => ['label' => 'تکمیل تسک', 'icon' => 'fa-circle-check', 'desc' => 'تغییر وضعیت تسک به تکمیل‌شده'],
        'delete'    => ['label' => 'حذف تسک', 'icon' => 'fa-trash', 'desc' => 'حذف کامل تسک'],
    ];
    /** کارهایی که فقط سازنده‌ی تسک/پروژه انجام می‌دهد (مجوز جدا ندارند) */
    public const TASK_OWNER_ONLY = ['edit_meta', 'change_project'];

    public const PROJECT_PERMS = [
        'edit_project'       => ['label' => 'ویرایش پروژه', 'icon' => 'fa-pen', 'desc' => 'ویرایش نام، توضیحات، تصویر و تنظیمات'],
        'add_member'         => ['label' => 'افزودن کاربر', 'icon' => 'fa-user-plus', 'desc' => 'افزودن همکاران جدید به پروژه'],
        'remove_member'      => ['label' => 'حذف کاربر', 'icon' => 'fa-user-minus', 'desc' => 'حذف اعضای فعلی از پروژه'],
        'manage_permissions' => ['label' => 'اعطای دسترسی', 'icon' => 'fa-key', 'desc' => 'تغییر دسترسی سایر اعضا (فقط مجوزهایی که خودتان دارید)'],
        'view_analytics'     => ['label' => 'مشاهده تحلیل‌ها', 'icon' => 'fa-chart-line', 'desc' => 'دسترسی به صفحه‌ی تحلیل و آمار پروژه'],
    ];

    public static function decode(?string $json, array $allowed): array
    {
        $d = json_decode((string) $json, true);
        return is_array($d) ? array_values(array_intersect($allowed, $d)) : [];
    }

    /** آیا $uid می‌تواند عملِ $action را روی درخواست تماس انجام دهد؟ */
    public static function can(array $row, int $uid, string $action): bool
    {
        if ((int) $row['user_id'] === $uid) {
            return true;
        }
        return (int) ($row['assignee_id'] ?? 0) === $uid && in_array($action, ['view', 'complete', 'notes'], true);
    }

    /** [سازنده‌ی پروژه، دسترسی تسک عضو یا null اگر عضو نیست] — با کش درون درخواست */
    private static function projectRole(int $projectId, int $uid): array
    {
        static $cache = [];
        $k = $projectId . ':' . $uid;
        if (!isset($cache[$k])) {
            $creator = (int) DB::value('SELECT user_id FROM projects WHERE id = ?', [$projectId]);
            $row = DB::one('SELECT task_permissions FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1', [$projectId, $uid]);
            $cache[$k] = [$creator, $row === null ? null : self::decode($row['task_permissions'], array_keys(self::TASK_PERMS))];
        }
        return $cache[$k];
    }

    /** دسترسی تسک یک عضو در پروژه (سازنده‌ی پروژه = همه). */
    public static function projectTaskPerms(int $projectId, int $uid): array
    {
        [$creator, $perms] = self::projectRole($projectId, $uid);
        if ($creator === $uid) {
            return array_keys(self::TASK_PERMS);
        }
        return $perms ?? [];
    }

    /**
     * دسترسی‌های مؤثر کاربر روی یک تسک: view + کلیدهای TASK_PERMS + edit_meta/change_project.
     * @return string[]
     */
    public static function taskPerms(array $task, int $uid): array
    {
        $all = array_merge(['view'], array_keys(self::TASK_PERMS), self::TASK_OWNER_ONLY);
        if ((int) $task['user_id'] === $uid) {
            return $all;
        }
        $out = [];
        $pid = (int) ($task['project_id'] ?? 0);
        if ($pid > 0) {
            [$creator, $perms] = self::projectRole($pid, $uid);
            if ($creator === $uid) {
                return $all;                                  // مدیر پروژه روی همه‌ی تسک‌های پروژه
            }
            $out = $perms ?? [];
        } elseif ((int) ($task['assignee_id'] ?? 0) === $uid) {
            $out = ['complete', 'notes'];                     // تسک‌های قدیمیِ بدون پروژه: رفتار قبلی برای مسئول
        }
        if ($out || (int) ($task['assignee_id'] ?? 0) === $uid) {
            $out[] = 'view';
        }
        return array_values(array_unique($out));
    }

    public static function canTask(array $task, int $uid, string $action): bool
    {
        return in_array($action, self::taskPerms($task, $uid), true);
    }

    public static function isColleague(int $a, int $b): bool
    {
        return (bool) DB::value('SELECT 1 FROM colleagues WHERE (user_id = ? AND colleague_user_id = ?) OR (user_id = ? AND colleague_user_id = ?) LIMIT 1', [$a, $b, $b, $a]);
    }

    public static function isBlockedBetween(int $a, int $b): bool
    {
        return (bool) DB::value('SELECT 1 FROM colleague_blocks WHERE (blocker_user_id = ? AND blocked_user_id = ?) OR (blocker_user_id = ? AND blocked_user_id = ?) LIMIT 1', [$a, $b, $b, $a]);
    }

    public static function isProjectMember(int $projectId, int $uid): bool
    {
        return (bool) DB::value('SELECT 1 FROM projects p WHERE p.id = ? AND (p.user_id = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?)) LIMIT 1', [$projectId, $uid, $uid]);
    }

    /** آیا مجاز است رکوردی را به $assigneeId واگذار کند؟ (خودش / همکار مستقیم / عضو همان پروژه) */
    public static function canAssignTo(int $actorId, int $assigneeId, ?int $projectId): bool
    {
        if ($assigneeId === $actorId) {
            return true;
        }
        if (!DB::value("SELECT 1 FROM users WHERE id = ? AND status = 'active' LIMIT 1", [$assigneeId])) {
            return false;
        }
        if (DB::value('SELECT 1 FROM colleagues WHERE user_id = ? AND colleague_user_id = ? LIMIT 1', [$actorId, $assigneeId])) {
            return true;
        }
        if ($projectId !== null && self::isProjectMember($projectId, $actorId)) {
            return (bool) DB::value('SELECT 1 FROM projects p WHERE p.id = ? AND (p.user_id = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?)) LIMIT 1', [$projectId, $assigneeId, $assigneeId]);
        }
        return false;
    }

    /** مجوزهای پروژه‌ی کاربر (سازنده = همه). */
    public static function projectPerms(int $projectId, int $uid): array
    {
        $creator = (int) DB::value('SELECT user_id FROM projects WHERE id = ?', [$projectId]);
        if ($creator === 0) {
            return [];
        }
        if ($creator === $uid) {
            return array_keys(self::PROJECT_PERMS);
        }
        $row = DB::one('SELECT permissions FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $uid]);
        return $row === null ? [] : self::decode($row['permissions'], array_keys(self::PROJECT_PERMS));
    }

    public static function projectCan(int $projectId, int $uid, string $perm): bool
    {
        return in_array($perm, self::projectPerms($projectId, $uid), true);
    }
}
