<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * قوانین دسترسی مشترک بین وب و API (تنها منبع حقیقت).
 *  - سازنده: همه‌ی دسترسی‌ها
 *  - مسئول (assignee): مشاهده، تکمیل، یادداشت
 *  - همکار: بر اساس مجوزهای ثبت‌شده توسط صاحب (colleagues.permissions)
 */
final class Access
{
    public const COLLEAGUE_PERMS = [
        'edit'           => ['label' => 'ویرایش تسک', 'icon' => 'fa-pen', 'desc' => 'تغییر عنوان، توضیحات، اولویت و سررسید'],
        'delete'         => ['label' => 'حذف تسک', 'icon' => 'fa-trash', 'desc' => 'حذف کامل تسک از سیستم'],
        'complete'       => ['label' => 'تکمیل تسک', 'icon' => 'fa-circle-check', 'desc' => 'علامت‌گذاری به‌عنوان انجام‌شده یا بازگردانی'],
        'reassign'       => ['label' => 'تغییر مسئول', 'icon' => 'fa-user-group', 'desc' => 'واگذاری تسک به همکار دیگر'],
        'notes'          => ['label' => 'یادداشت و پیوست', 'icon' => 'fa-comment-dots', 'desc' => 'ثبت و حذف گزارش پیشرفت و فایل'],
        'change_project' => ['label' => 'تغییر پروژه', 'icon' => 'fa-folder-tree', 'desc' => 'انتقال تسک به پروژه‌ی دیگر'],
    ];

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

    /** مجوزهای همکار $uid روی تسک‌های $ownerId. */
    public static function colleaguePerms(int $ownerId, int $uid): array
    {
        static $cache = [];
        $k = $ownerId . ':' . $uid;
        if (!isset($cache[$k])) {
            $row = DB::one('SELECT permissions FROM colleagues WHERE user_id = ? AND colleague_user_id = ? LIMIT 1', [$ownerId, $uid]);
            $cache[$k] = $row === null ? null : self::decode($row['permissions'], array_keys(self::COLLEAGUE_PERMS));
        }
        return $cache[$k] ?? [];
    }

    /** آیا $uid می‌تواند عملِ $action را روی رکورد (تسک/درخواست تماس) انجام دهد؟ */
    public static function can(array $row, int $uid, string $action): bool
    {
        if ((int) $row['user_id'] === $uid) {
            return true;
        }
        if ((int) ($row['assignee_id'] ?? 0) === $uid && in_array($action, ['view', 'complete', 'notes'], true)) {
            return true;
        }
        $perms = self::colleaguePerms((int) $row['user_id'], $uid);
        return $action === 'view' ? $perms !== [] : in_array($action, $perms, true);
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
