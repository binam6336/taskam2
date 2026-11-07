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
        'block'     => ['label' => 'مسدود کردن تسک', 'icon' => 'fa-ban', 'desc' => 'مسدود کردن تسک (به‌جای حذف؛ تسک از فهرست‌ها کنار می‌رود) و رفع مسدودی آن'],
    ];
    /** کارهایی که فقط سازنده‌ی تسک/پروژه انجام می‌دهد (مجوز جدا ندارند) */
    public const TASK_OWNER_ONLY = ['edit_meta', 'change_project'];

    /**
     * دسترسی‌های بورد پروژه (کانبان) به ازای هر پروژه؛ جدا از دسترسی تسک‌ها و پیش‌فرض بسته.
     * مسیر اصلی مراحل: (rework →) backlog → todo → in_progress → review → done؛ «بازبینی مسئول» مرحله‌ی کناری است (ستون «مسدودشده» از v4.0 برداشته شد).
     * بازبینی مسئول (rework): سرپرست در «بررسی سرپرست» ایرادی دیده و تسک را برای اصلاح برگردانده؛
     * برگرداندن با همان مجوز تأیید (to_done) و ارسال دوباره برای بررسی با to_review است (مجوز جدید لازم نیست).
     * دیدن کارت‌ها جداست: هر کس تسک‌های خودش را می‌بیند + تسک‌های اعضایی که در «مشاهده‌ی تسک‌های» برایش انتخاب شده
     * (project_members.board_view_users)؛ سازنده‌ی پروژه همه را می‌بیند.
     */
    public const BOARD_PERMS = [
        'to_todo'     => ['label' => 'برنامه‌ریزی‌نشده ← آماده انجام', 'icon' => 'fa-list-ul', 'desc' => 'انتخاب تسک برای انجام؛ و بردن تسک برگشته از «بازبینی مسئول» به برنامه‌ریزی‌نشده (مرحله‌ی بعد آن)'],
        'to_progress' => ['label' => 'آماده انجام ← در حال انجام', 'icon' => 'fa-person-running', 'desc' => 'شروع کار روی تسک'],
        'to_review'   => ['label' => 'در حال انجام ← بررسی سرپرست', 'icon' => 'fa-magnifying-glass', 'desc' => 'ارسال کار انجام‌شده برای بررسی سرپرست (و ارسال دوباره از «بازبینی مسئول»)'],
        'to_done'     => ['label' => 'بررسی سرپرست ← انجام‌شده', 'icon' => 'fa-circle-check', 'desc' => 'تأیید بررسی و تکمیل تسک، برگرداندن آن به «بازبینی مسئول» برای اصلاح، یا بازکردن دوباره‌ی تسک انجام‌شده'],
        'move_back'   => ['label' => 'بازگرداندن به مرحله‌ی قبل', 'icon' => 'fa-rotate-left', 'desc' => 'مثلاً بررسی سرپرست ← در حال انجام یا بازبینی مسئول ← در حال انجام (بازکردن تسک انجام‌شده مجوز «بررسی سرپرست ← انجام‌شده» می‌خواهد)'],
        'move_any'    => ['label' => 'جابه‌جایی آزاد', 'icon' => 'fa-shuffle', 'desc' => 'انتقال بین هر دو مرحله، از جمله پرش از چند مرحله'],
    ];
    private const BOARD_CHAIN = ['backlog', 'todo', 'in_progress', 'review', 'done'];
    private const BOARD_FORWARD = ['todo' => 'to_todo', 'in_progress' => 'to_progress', 'review' => 'to_review', 'done' => 'to_done'];

    public const PROJECT_PERMS = [
        'edit_project'       => ['label' => 'ویرایش پروژه', 'icon' => 'fa-pen', 'desc' => 'ویرایش نام، توضیحات، تصویر و تنظیمات'],
        'add_member'         => ['label' => 'افزودن کاربر', 'icon' => 'fa-user-plus', 'desc' => 'افزودن همکاران جدید به پروژه'],
        'remove_member'      => ['label' => 'خارج کردن کاربر', 'icon' => 'fa-user-minus', 'desc' => 'خارج کردن اعضای فعلی از پروژه (عضو حذف نمی‌شود؛ تسک‌ها و سابقه‌اش می‌ماند)'],
        'manage_permissions' => ['label' => 'اعطای دسترسی', 'icon' => 'fa-key', 'desc' => 'تغییر دسترسی سایر اعضا (فقط مجوزهایی که خودتان دارید)'],
        'view_analytics'     => ['label' => 'مشاهده تحلیل‌ها', 'icon' => 'fa-chart-line', 'desc' => 'دسترسی به صفحه‌ی تحلیل و آمار پروژه'],
    ];

    /**
     * نقش‌های آماده‌ی عضو پروژه: پکیج دسترسی تسک + پروژه + بورد پروژه + «مشاهده‌ی تسک‌ها»ی برد (all = همه‌ی اعضا، own = فقط خودش).
     * نقش ذخیره نمی‌شود؛ از روی دسترسی‌های فعلی تشخیص داده می‌شود (هر تغییر دستی = «سفارشی»).
     */
    public const ROLES = [
        'admin'        => ['label' => 'مدیر کل', 'icon' => 'fa-crown', 'tone' => 'green', 'desc' => 'همه‌ی دسترسی‌ها در همه‌ی بخش‌های پروژه؛ در برد تسک‌های همه را می‌بیند.',
            'task' => '*', 'project' => '*', 'board' => '*', 'view' => 'all'],
        'unit_manager' => ['label' => 'مدیر واحد', 'icon' => 'fa-user-tie', 'tone' => 'blue', 'desc' => 'همه‌چیز جز مسدود کردن تسک؛ در برد تسک‌های همه را می‌بیند.',
            'task' => ['edit_info', 'edit_due', 'reassign', 'notes', 'complete'], 'project' => '*', 'board' => '*', 'view' => 'all'],
        'supervisor'   => ['label' => 'سرپرست', 'icon' => 'fa-user-shield', 'tone' => 'yellow', 'desc' => 'تسک: همه‌چیز جز مسدود کردن. پروژه: همه‌چیز جز افزودن و خارج کردن کاربر. برد: همه‌چیز؛ پیش‌فرض فقط تسک‌های خودش را می‌بیند.',
            'task' => ['edit_info', 'edit_due', 'reassign', 'notes', 'complete'], 'project' => ['edit_project', 'manage_permissions', 'view_analytics'], 'board' => '*', 'view' => 'own'],
        // عضو در برد همه‌ی جابه‌جایی‌ها را دارد جز تکمیل (to_done) و جابه‌جایی آزاد (move_any که بررسی سرپرست را دور می‌زند)
        'member'       => ['label' => 'عضو', 'icon' => 'fa-user', 'tone' => '', 'desc' => 'تسک: فقط ویرایش اطلاعات و نوشتن یادداشت. برد: همه‌ی جابه‌جایی‌ها جز تکمیل (انجام‌شده) و جابه‌جایی آزاد؛ بدون دسترسی پروژه؛ فقط تسک‌های خودش را می‌بیند.',
            'task' => ['edit_info', 'notes'], 'project' => [], 'board' => self::MEMBER_BOARD, 'view' => 'own'],
        'none'         => ['label' => 'بدون دسترسی', 'icon' => 'fa-user-slash', 'tone' => 'red', 'desc' => 'هیچ دسترسی‌ای ندارد؛ فقط تسک‌های خودش را می‌بیند.',
            'task' => [], 'project' => [], 'board' => [], 'view' => 'own'],
    ];
    /** دسترسی برد نقش «عضو» (v3.39): همه جز to_done و move_any */
    public const MEMBER_BOARD = ['to_todo', 'to_progress', 'to_review', 'move_back'];
    public const ROLE_CUSTOM = ['label' => 'سفارشی', 'icon' => 'fa-sliders', 'tone' => 'purple', 'desc' => 'دسترسی‌ها دستی تنظیم شده و با هیچ نقش آماده‌ای یکی نیست.'];

    /** نقش پیش‌فرض هر عضو تازه‌ی پروژه (افزودن مستقیم یا پذیرفتن دعوت) */
    public const DEFAULT_ROLE = 'member';

    /** افزودن عضو به پروژه با دسترسی‌های نقش پیش‌فرض (اگر از قبل عضو باشد تغییری نمی‌کند) */
    public static function addMemberWithDefaultRole(int $projectId, int $uid): void
    {
        [$task, $project, $board, $all] = self::rolePackage(self::DEFAULT_ROLE);
        DB::run('INSERT IGNORE INTO project_members (pid, project_id, user_id, permissions, task_permissions, board_permissions, board_view_users) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [\App\Core\Pid::new(), $projectId, $uid, json_encode(array_values($project)), json_encode(array_values($task)), json_encode(array_values($board)), $all ? 'all' : null]);
    }

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

    /**
     * «مسئول» تسک (v5.10): خود مسئول، یا هر عضو گروهی که تسک به آن داده شده (tasks.assignee_group_id).
     * در دسترسی‌ها، دیدن در برد و تیک «انجام شد»، عضو گروه مثل مسئول رفتار می‌کند.
     */
    public static function isAssignee(array $task, int $uid): bool
    {
        if ($uid > 0 && (int) ($task['assignee_id'] ?? 0) === $uid) {
            return true;
        }
        $g = (int) ($task['assignee_group_id'] ?? 0);
        return $g > 0 && \App\Models\GroupModel::isMember($g, $uid);
    }

    /** سرگروه گروهِ مسئول تسک؟ */
    public static function isGroupHead(array $task, int $uid): bool
    {
        $g = (int) ($task['assignee_group_id'] ?? 0);
        if ($g <= 0) {
            return false;
        }
        $grp = \App\Models\GroupModel::find($g);
        return $grp !== null && (int) $grp['head_id'] === $uid && \App\Models\GroupModel::isMember($g, $uid);
    }

    /** آیا $uid می‌تواند عملِ $action را روی درخواست تماس انجام دهد؟ */
    public static function can(array $row, int $uid, string $action): bool
    {
        // درخواست مسدودشده فقط دیده می‌شود (ثبت‌کننده می‌تواند مسدودی‌اش را بردارد)
        if (!empty($row['blocked_at']) && !in_array($action, ['view', 'block'], true)) {
            return false;
        }
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
    /**
     * انتخاب مسئول هنگام «ساخت» تسک پروژه (v5.5): سازنده‌ی پروژه و هر عضوی که نقشش «بدون دسترسی» نیست، می‌تواند تسک را
     * به هر عضو پروژه بدهد (قابل تنظیم نیست). تغییر مسئولِ تسکِ ثبت‌شده همچنان مجوز «تغییر مسئول» (reassign) می‌خواهد.
     */
    public static function canAssignOnCreate(int $projectId, int $uid): bool
    {
        [$creator] = self::projectRole($projectId, $uid);
        if ($creator === $uid) {
            return true;
        }
        $row = DB::one('SELECT permissions, task_permissions, board_permissions, board_view_users FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $uid]);
        return $row !== null && self::memberRole($row) !== 'none';
    }

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
        $pid = (int) ($task['project_id'] ?? 0);
        // سازنده‌ی تسک فقط تا وقتی عضو پروژه است امتیاز کامل دارد (بعد از خروج/حذف از پروژه، هیچ)
        if ((int) $task['user_id'] === $uid && ($pid === 0 || self::isProjectMember($pid, $uid))) {
            // تغییر مسئولِ تسکی که خودش ساخته هم فقط با مجوز «تغییر مسئول» در پروژه (جلوی دور زدن محدودیت واگذاری)
            if ($pid > 0 && !in_array('reassign', self::projectTaskPerms($pid, $uid), true)) {
                return array_values(array_diff($all, ['reassign']));
            }
            return $all;
        }
        $out = [];
        if ($pid > 0) {
            [$creator, $perms] = self::projectRole($pid, $uid);
            if ($creator === $uid) {
                return $all;                                  // مدیر پروژه روی همه‌ی تسک‌های پروژه
            }
            $out = $perms ?? [];
            // دسترسی‌های نقش پروژه فقط روی تسک‌هایی اعمال می‌شود که کاربر در برد می‌بیند (تسک‌های خودش یا مسئولانِ
            // انتخاب‌شده در «مشاهده‌ی تسک‌های»)؛ دارنده‌ی «مشاهده‌ی تحلیل» بقیه را فقط می‌بیند (بدون ویرایش)
            if ($out && !self::canBoardView($task, $uid)) {
                $out = self::projectCan($pid, $uid, 'view_analytics') ? ['view'] : [];
            }
        } elseif (self::isAssignee($task, $uid)) {
            $out = ['complete', 'notes'];                     // تسک‌های قدیمیِ بدون پروژه: رفتار قبلی برای مسئول
        }
        if ($out || self::isAssignee($task, $uid)) {
            $out[] = 'view';
        }
        return array_values(array_unique($out));
    }

    /**
     * تیک «انجام شد» = تحویل برای «بررسی سرپرست» (v4.1): مسئول، سازنده‌ی تسک، یا عضوی با مجوز تکمیل تسک
     * یا مجوز برد «ارسال برای بررسی» — بدون نیاز به مجوز مرحله‌های میانی برد.
     */
    public static function canDeliver(array $task, int $uid): bool
    {
        if (!empty($task['blocked_at']) || !self::canTask($task, $uid, 'view')) {
            return false;
        }
        if (self::isAssignee($task, $uid) || (int) $task['user_id'] === $uid) {
            return true;
        }
        return self::canTask($task, $uid, 'complete') || in_array('to_review', self::boardPerms($task, $uid), true);
    }

    /**
     * دسترسی تأیید (v5.4): کسی که تسک «بررسی سرپرست» را می‌تواند به «انجام‌شده» ببرد (مجوز تکمیل + انتقال review → done در بورد).
     * تیک «انجام شد» چنین کسی مستقیم تسک را انجام‌شده می‌کند (بدون رفتن به بررسی سرپرست).
     */
    public static function canApprove(array $task, int $uid): bool
    {
        return $task['project_id'] !== null && empty($task['blocked_at']) && self::canTask($task, $uid, 'complete')
            && in_array('done', self::boardTargets($task, $uid, 'review', ['done']), true);
    }

    public static function canTask(array $task, int $uid, string $action): bool
    {
        // تسک مسدودشده فقط دیده می‌شود (و دارنده‌ی مجوز «مسدود کردن» می‌تواند مسدودی‌اش را بردارد)
        if (!empty($task['blocked_at']) && !in_array($action, ['view', 'block'], true)) {
            return false;
        }
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

    /**
     * دسترسی‌های برد کاربر روی یک تسک: سازنده‌ی پروژه همه؛ بقیه (حتی سازنده‌ی خود تسک) طبق نقششان در پروژه،
     * تا ساختن تسک راهی برای دور زدن مراحل برد نباشد.
     */
    public static function boardPerms(array $task, int $uid): array
    {
        $pid = (int) ($task['project_id'] ?? 0);
        return $pid > 0 ? self::projectBoardPerms($pid, $uid) : [];
    }

    /** مجوز لازم برای جابه‌جایی کارت از مرحله‌ی $from به $to (همیشه move_any هم کافی است). */
    public static function boardMovePerm(string $from, string $to): string
    {
        // بازبینی مسئول: برگرداندن از بررسی سرپرست = مجوز تأیید؛ ارسال دوباره = مجوز ارسال برای بررسی؛ بازگشت به در حال انجام = مرحله‌ی قبل
        // برگرداندن از بررسی سرپرست به بازبینی مسئول، و بازکردن تسک انجام‌شده = کار تأییدکننده (همان مجوز تکمیل)
        if (($from === 'review' && $to === 'rework') || $from === 'done') {
            return 'to_done';
        }
        if ($from === 'rework') {
            // مرحله‌ی بعدِ بازبینی مسئول: برنامه‌ریزی‌نشده (برنامه‌ریزی دوباره) — همان مجوز انتخاب تسک برای انجام
            if ($to === 'backlog') {
                return 'to_todo';
            }
            if ($to === 'review') {
                return 'to_review';
            }
            if ($to === 'in_progress') {
                return 'move_back';
            }
        }
        $i = array_search($from, self::BOARD_CHAIN, true);
        $j = array_search($to, self::BOARD_CHAIN, true);
        if ($i !== false && $j !== false) {
            if ($j === $i + 1) {
                return self::BOARD_FORWARD[$to];
            }
            return $j === $i - 1 ? 'move_back' : 'move_any';
        }
        return 'move_any';
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
        if ((int) $task['user_id'] === $uid || self::isAssignee($task, $uid)) {
            return true;
        }
        $pid = (int) ($task['project_id'] ?? 0);
        if ($pid <= 0) {
            return false;
        }
        $scope = self::boardViewScope($pid, $uid);
        if ($scope === null || ($assignee > 0 && in_array($assignee, $scope, true))) {
            return true;
        }
        // تسک گروهی: کسی که تسک‌های یکی از اعضای گروه را در برد می‌بیند، تسک گروه را هم می‌بیند
        $g = (int) ($task['assignee_group_id'] ?? 0);
        return $g > 0 && (bool) array_intersect(\App\Models\GroupModel::memberIds($g), $scope);
    }

    /**
     * شرط SQL «ردیف همکاری تأییدشده» برای جدول colleagues با نام مستعار $c:
     * طرف مقابل درخواست را پذیرفته یا خودش هم این کاربر را همکار کرده است.
     * ردیف‌های یک‌طرفه‌ی قدیمی (قبل از v3.25) بدون رضایت طرف مقابل، دسترسی‌ای نمی‌دهند.
     */
    public static function approvedSql(string $c = 'c'): string
    {
        return "(EXISTS (SELECT 1 FROM colleague_requests cq WHERE cq.requester_id = $c.user_id AND cq.target_id = $c.colleague_user_id AND cq.status = 'accepted')
            OR EXISTS (SELECT 1 FROM colleagues cr WHERE cr.user_id = $c.colleague_user_id AND cr.colleague_user_id = $c.user_id))";
    }

    /** همکاری تأییدشده بین دو نفر (در هر جهت) */
    public static function isColleague(int $a, int $b): bool
    {
        return (bool) DB::value('SELECT 1 FROM colleagues c WHERE ((c.user_id = ? AND c.colleague_user_id = ?) OR (c.user_id = ? AND c.colleague_user_id = ?)) AND ' . self::approvedSql('c') . ' LIMIT 1', [$a, $b, $b, $a]);
    }

    /**
     * پیام مسدودی بین انجام‌دهنده و طرف مقابل (null = مسدودی نیست):
     * او شما را مسدود کرده / شما او را مسدود کرده‌اید.
     */
    public static function blockMessage(int $actor, int $other): ?string
    {
        if ($actor === $other || $other <= 0) {
            return null;
        }
        if (DB::value('SELECT 1 FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ? LIMIT 1', [$other, $actor])) {
            return 'این کاربر شما را مسدود کرده است.';
        }
        if (DB::value('SELECT 1 FROM colleague_blocks WHERE blocker_user_id = ? AND blocked_user_id = ? LIMIT 1', [$actor, $other])) {
            return 'شما این کاربر را مسدود کرده‌اید.';
        }
        return null;
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
        if (self::isBlockedBetween($actorId, $assigneeId)) {
            return false;                                     // بعد از مسدودسازی (در هر جهت) واگذاری ممکن نیست
        }
        // تسک/رکورد پروژه‌دار فقط به اعضای همان پروژه واگذار می‌شود (همکارِ غیرعضو محتوای تسک پروژه را نبیند)
        if ($projectId !== null) {
            return self::isProjectMember($projectId, $actorId) && self::isProjectMember($projectId, $assigneeId);
        }
        return (bool) DB::value('SELECT 1 FROM colleagues c WHERE c.user_id = ? AND c.colleague_user_id = ? AND ' . self::approvedSql('c') . ' LIMIT 1', [$actorId, $assigneeId]);
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
