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

    /**
     * دسترسی‌های برد تیمی (کانبان) به ازای هر پروژه؛ جدا از دسترسی تسک‌ها و پیش‌فرض بسته.
     * مسیر اصلی مراحل: backlog → todo → in_progress → review → done؛ «مسدودشده» مرحله‌ی کناری است.
     * دیدن کارت‌ها جداست: هر کس تسک‌های خودش را می‌بیند + تسک‌های اعضایی که در «مشاهده‌ی تسک‌های» برایش انتخاب شده
     * (project_members.board_view_users)؛ سازنده‌ی پروژه همه را می‌بیند.
     */
    public const BOARD_PERMS = [
        'to_todo'     => ['label' => 'برنامه‌ریزی‌نشده ← آماده انجام', 'icon' => 'fa-list-ul', 'desc' => 'انتخاب تسک برای انجام'],
        'to_progress' => ['label' => 'آماده انجام ← در حال انجام', 'icon' => 'fa-person-running', 'desc' => 'شروع کار روی تسک'],
        'to_review'   => ['label' => 'در حال انجام ← در انتظار بررسی', 'icon' => 'fa-magnifying-glass', 'desc' => 'ارسال کار انجام‌شده برای بررسی'],
        'to_done'     => ['label' => 'در انتظار بررسی ← انجام‌شده', 'icon' => 'fa-circle-check', 'desc' => 'تأیید بررسی و تکمیل تسک'],
        'block'       => ['label' => 'مسدود کردن', 'icon' => 'fa-ban', 'desc' => 'انتقال از آماده انجام / در حال انجام / در انتظار بررسی به مسدودشده (با علت توقف)'],
        'unblock'     => ['label' => 'رفع مسدودی', 'icon' => 'fa-lock-open', 'desc' => 'برگرداندن از مسدودشده به آماده انجام / در حال انجام / در انتظار بررسی'],
        'move_back'   => ['label' => 'بازگرداندن به مرحله‌ی قبل', 'icon' => 'fa-rotate-left', 'desc' => 'مثلاً در انتظار بررسی ← در حال انجام برای اصلاح، یا انجام‌شده ← در انتظار بررسی'],
        'move_any'    => ['label' => 'جابه‌جایی آزاد', 'icon' => 'fa-shuffle', 'desc' => 'انتقال بین هر دو مرحله، از جمله پرش از چند مرحله'],
    ];
    private const BOARD_CHAIN = ['backlog', 'todo', 'in_progress', 'review', 'done'];
    private const BOARD_FORWARD = ['todo' => 'to_todo', 'in_progress' => 'to_progress', 'review' => 'to_review', 'done' => 'to_done'];
    private const BOARD_ACTIVE = ['todo', 'in_progress', 'review'];

    public const PROJECT_PERMS = [
        'edit_project'       => ['label' => 'ویرایش پروژه', 'icon' => 'fa-pen', 'desc' => 'ویرایش نام، توضیحات، تصویر و تنظیمات'],
        'add_member'         => ['label' => 'افزودن کاربر', 'icon' => 'fa-user-plus', 'desc' => 'افزودن همکاران جدید به پروژه'],
        'remove_member'      => ['label' => 'حذف کاربر', 'icon' => 'fa-user-minus', 'desc' => 'حذف اعضای فعلی از پروژه'],
        'manage_permissions' => ['label' => 'اعطای دسترسی', 'icon' => 'fa-key', 'desc' => 'تغییر دسترسی سایر اعضا (فقط مجوزهایی که خودتان دارید)'],
        'view_analytics'     => ['label' => 'مشاهده تحلیل‌ها', 'icon' => 'fa-chart-line', 'desc' => 'دسترسی به صفحه‌ی تحلیل و آمار پروژه'],
    ];

    /**
     * نقش‌های آماده‌ی عضو پروژه: پکیج دسترسی تسک + پروژه + برد تیمی + «مشاهده‌ی تسک‌ها»ی برد (all = همه‌ی اعضا، own = فقط خودش).
     * نقش ذخیره نمی‌شود؛ از روی دسترسی‌های فعلی تشخیص داده می‌شود (هر تغییر دستی = «سفارشی»).
     */
    public const ROLES = [
        'admin'        => ['label' => 'مدیر کل', 'icon' => 'fa-crown', 'tone' => 'green', 'desc' => 'همه‌ی دسترسی‌ها در همه‌ی بخش‌های پروژه؛ در برد تسک‌های همه را می‌بیند.',
            'task' => '*', 'project' => '*', 'board' => '*', 'view' => 'all'],
        'unit_manager' => ['label' => 'مدیر واحد', 'icon' => 'fa-user-tie', 'tone' => 'blue', 'desc' => 'همه‌چیز جز حذف تسک؛ در برد تسک‌های همه را می‌بیند.',
            'task' => ['edit_info', 'edit_due', 'reassign', 'notes', 'complete'], 'project' => '*', 'board' => '*', 'view' => 'all'],
        'supervisor'   => ['label' => 'سرپرست', 'icon' => 'fa-user-shield', 'tone' => 'yellow', 'desc' => 'تسک: همه‌چیز جز حذف. پروژه: همه‌چیز جز افزودن و حذف کاربر. برد: همه‌چیز؛ پیش‌فرض فقط تسک‌های خودش را می‌بیند.',
            'task' => ['edit_info', 'edit_due', 'reassign', 'notes', 'complete'], 'project' => ['edit_project', 'manage_permissions', 'view_analytics'], 'board' => '*', 'view' => 'own'],
        'member'       => ['label' => 'عضو', 'icon' => 'fa-user', 'tone' => '', 'desc' => 'تسک: فقط ویرایش اطلاعات و نوشتن یادداشت. بدون دسترسی پروژه و برد؛ فقط تسک‌های خودش را می‌بیند.',
            'task' => ['edit_info', 'notes'], 'project' => [], 'board' => [], 'view' => 'own'],
        'none'         => ['label' => 'بدون دسترسی', 'icon' => 'fa-user-slash', 'tone' => 'red', 'desc' => 'هیچ دسترسی‌ای ندارد؛ فقط تسک‌های خودش را می‌بیند.',
            'task' => [], 'project' => [], 'board' => [], 'view' => 'own'],
    ];
    public const ROLE_CUSTOM = ['label' => 'سفارشی', 'icon' => 'fa-sliders', 'tone' => 'purple', 'desc' => 'دسترسی‌ها دستی تنظیم شده و با هیچ نقش آماده‌ای یکی نیست.'];

    /** پکیج کامل یک نقش: [task[], project[], board[], viewAll] */
    public static function rolePackage(string $role): ?array
    {
        $r = self::ROLES[$role] ?? null;
        if ($r === null) {
            return null;
        }
        $all = static fn($v, array $defs) => $v === '*' ? array_keys($defs) : $v;
        return [$all($r['task'], self::TASK_PERMS), $all($r['project'], self::PROJECT_PERMS), $all($r['board'], self::BOARD_PERMS), $r['view'] === 'all'];
    }

    /** نقش عضو از روی دسترسی‌های فعلی‌اش (یا custom). */
    public static function roleOf(array $task, array $project, array $board, bool $viewAll, array $viewIds): string
    {
        $same = static fn(array $a, array $b) => !array_diff($a, $b) && !array_diff($b, $a);
        foreach (array_keys(self::ROLES) as $k) {
            [$t, $p, $b, $all] = self::rolePackage($k);
            if ($same($task, $t) && $same($project, $p) && $same($board, $b) && $viewAll === $all && ($all || !$viewIds)) {
                return $k;
            }
        }
        return 'custom';
    }

    /** نقش یک ردیف project_members (ستون‌های permissions, task_permissions, board_permissions, board_view_users). */
    public static function memberRole(array $row): string
    {
        return self::roleOf(self::decode($row['task_permissions'] ?? null, array_keys(self::TASK_PERMS)), self::decode($row['permissions'] ?? null, array_keys(self::PROJECT_PERMS)),
            self::decode($row['board_permissions'] ?? null, array_keys(self::BOARD_PERMS)), self::viewAll($row['board_view_users'] ?? null), self::ids($row['board_view_users'] ?? null));
    }

    /** board_view_users = "all": تسک‌های همه‌ی اعضا (حتی اعضای بعدی) */
    public static function viewAll(?string $json): bool
    {
        return json_decode((string) $json, true) === 'all';
    }

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

    /** [سازنده‌ی پروژه، دسترسی تسک عضو یا null اگر عضو نیست، دسترسی برد عضو، اعضایی که تسک‌هایشان را در برد می‌بیند] — با کش درون درخواست */
    private static function projectRole(int $projectId, int $uid): array
    {
        static $cache = [];
        $k = $projectId . ':' . $uid;
        if (!isset($cache[$k])) {
            $creator = (int) DB::value('SELECT user_id FROM projects WHERE id = ?', [$projectId]);
            $row = DB::one('SELECT task_permissions, board_permissions, board_view_users FROM project_members WHERE project_id = ? AND user_id = ? LIMIT 1', [$projectId, $uid]);
            $cache[$k] = [$creator, $row === null ? null : self::decode($row['task_permissions'], array_keys(self::TASK_PERMS)),
                $row === null ? [] : self::decode($row['board_permissions'], array_keys(self::BOARD_PERMS)),
                $row === null ? [] : (self::viewAll($row['board_view_users']) ? null : self::ids($row['board_view_users']))];
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

    /** آرایه‌ی JSON شناسه‌ها → int[] یکتا و مثبت */
    public static function ids(?string $json): array
    {
        $d = json_decode((string) $json, true);
        return is_array($d) ? array_values(array_unique(array_filter(array_map('intval', array_filter($d, 'is_numeric')), static fn($v) => $v > 0))) : [];
    }

    /**
     * اعضایی که کاربر تسک‌هایشان را در برد این پروژه می‌بیند (همیشه شامل خودش).
     * null = همه (سازنده‌ی پروژه)؛ [] = عضو پروژه نیست.
     */
    public static function boardViewScope(int $projectId, int $uid): ?array
    {
        [$creator, $perms, , $view] = self::projectRole($projectId, $uid);
        if ($creator === $uid || ($perms !== null && $view === null)) {
            return null;                                       // سازنده یا «همه‌ی اعضا»
        }
        return $perms === null ? [] : array_values(array_unique(array_merge([$uid], $view)));
    }

    /** دسترسی برد یک عضو در پروژه (سازنده‌ی پروژه = همه). */
    public static function projectBoardPerms(int $projectId, int $uid): array
    {
        [$creator, , $board] = self::projectRole($projectId, $uid);
        return $creator === $uid ? array_keys(self::BOARD_PERMS) : $board;
    }

    /** دسترسی‌های برد کاربر روی یک تسک: سازنده‌ی تسک و سازنده‌ی پروژه همه؛ بقیه طبق عضویتشان در پروژه. */
    public static function boardPerms(array $task, int $uid): array
    {
        if ((int) $task['user_id'] === $uid) {
            return array_keys(self::BOARD_PERMS);
        }
        $pid = (int) ($task['project_id'] ?? 0);
        return $pid > 0 ? self::projectBoardPerms($pid, $uid) : [];
    }

    /** مجوز لازم برای جابه‌جایی کارت از مرحله‌ی $from به $to (همیشه move_any هم کافی است). */
    public static function boardMovePerm(string $from, string $to): string
    {
        $i = array_search($from, self::BOARD_CHAIN, true);
        $j = array_search($to, self::BOARD_CHAIN, true);
        if ($i !== false && $j !== false) {
            if ($j === $i + 1) {
                return self::BOARD_FORWARD[$to];
            }
            return $j === $i - 1 ? 'move_back' : 'move_any';
        }
        if ($to === 'blocked') {
            return in_array($from, self::BOARD_ACTIVE, true) ? 'block' : 'move_any';
        }
        return $from === 'blocked' && in_array($to, self::BOARD_ACTIVE, true) ? 'unblock' : 'move_any';
    }

    /** مراحلی که کاربر می‌تواند کارت را از وضعیت فعلی‌اش به آن‌ها ببرد. */
    public static function boardTargets(array $task, int $uid, string $from, array $statuses): array
    {
        $have = self::boardPerms($task, $uid);
        if (!$have) {
            return [];
        }
        $any = in_array('move_any', $have, true);
        return array_values(array_filter($statuses, static fn($to) => $to !== $from && ($any || in_array(self::boardMovePerm($from, $to), $have, true))));
    }

    /**
     * دیدن تسک در برد: تسک‌های خودش (سازنده یا مسئول)، و در پروژه، تسک‌هایی که مسئولشان
     * جزو اعضای انتخاب‌شده در «مشاهده‌ی تسک‌های» اوست (سازنده‌ی پروژه = همه).
     */
    public static function canBoardView(array $task, int $uid): bool
    {
        $assignee = (int) ($task['assignee_id'] ?? 0);
        if ((int) $task['user_id'] === $uid || $assignee === $uid) {
            return true;
        }
        $pid = (int) ($task['project_id'] ?? 0);
        if ($pid <= 0) {
            return false;
        }
        $scope = self::boardViewScope($pid, $uid);
        return $scope === null || ($assignee > 0 && in_array($assignee, $scope, true));
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
