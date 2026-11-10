<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Uploader;

final class TaskModel
{
    private const SELECT = "SELECT t.*, s.title AS subject_title, s.color AS subject_color,
            p.title AS project_title, p.profile_image AS project_image, p.priority_type AS project_priority_type,
            ua.first_name AS assignee_first_name, ua.last_name AS assignee_last_name, ua.mobile AS assignee_mobile, ua.avatar AS assignee_avatar,
            uc.first_name AS creator_first_name, uc.last_name AS creator_last_name, uc.mobile AS creator_mobile, uc.avatar AS creator_avatar,
            (SELECT COUNT(*) FROM task_notes tn WHERE tn.task_id = t.id) AS notes_count,
            (SELECT COUNT(*) FROM task_attachments ta WHERE ta.task_id = t.id) AS attachments_count,
            (SELECT pm.task_permissions FROM project_members pm WHERE pm.project_id = t.project_id AND pm.user_id = :me LIMIT 1) AS my_task_permissions
        FROM tasks t
        LEFT JOIN subjects s ON s.id = t.subject_id
        LEFT JOIN projects p ON p.id = t.project_id
        LEFT JOIN users ua ON ua.id = t.assignee_id
        LEFT JOIN users uc ON uc.id = t.user_id";

    /** «مسئولش منم»: یک placeholder (شناسه‌ی کاربر) */
    public static function mine(int $uid, string $t = 't'): string
    {
        return '(' . $t . '.assignee_id = ?)';
    }

    /** SELECT پایه با placeholder موقعیتی: اولین ? = شناسه‌ی کاربر برای دسترسی تسک او در پروژه. */
    private static function base(): string
    {
        return str_replace(':me', '?', self::SELECT);
    }

    public static function find(int $id, int $viewer = 0): ?array
    {
        return DB::one(self::base() . ' WHERE t.id = ? LIMIT 1', [$viewer, $id]);
    }

    public static function findByToken(string $token, int $viewer): ?array
    {
        return DB::one(self::base() . ' WHERE t.share_token = ? LIMIT 1', [$viewer, $token]);
    }

    public static function raw(int $id): ?array
    {
        return DB::one('SELECT * FROM tasks WHERE id = ? LIMIT 1', [$id]);
    }

    public const SORTS = [
        'smart'    => 'هوشمند (عقب‌افتاده، امروز، بدون زمان، پیش رو)',
        'created'  => 'تاریخ ایجاد',
        'due'      => 'تاریخ انجام (سررسید)',
        'priority' => 'اولویت',
        'project'  => 'پروژه',
        'subject'  => 'لیبل',
    ];

    /** شرط SQL دیدن تسک محرمانه برای $uid (عدد مستقیم در SQL؛ بدون پارامتر) */
    public static function confidentialSql(int $uid, string $t = 't'): string
    {
        $u = (int) $uid;
        return "($t.is_confidential = 0 OR $t.user_id = $u OR $t.assignee_id = $u"
            . " OR EXISTS (SELECT 1 FROM task_viewers tv WHERE tv.task_id = $t.id AND tv.user_id = $u))";
    }

    /**
     * شرط WHERE مشترک فهرست و فیلترها.
     * scope: mine | others | project (همه‌ی تسک‌های یک پروژه؛ از «تحلیل پروژه»، شناسه در $projectId)
     * $completed: null = همه (انجام‌شده و نشده)
     */
    private static function scopeWhere(int $uid, string $scope, ?bool $completed, int $projectId = 0): array
    {
        if ($scope === 'project') {
            // تسک محرمانه (v6.1) فقط برای سازنده، مسئول/اعضای گروه مسئول و بیننده‌های انتخابی
            $where = 't.project_id = ? AND ' . self::confidentialSql($uid);
            $params = [$projectId];
        } elseif ($scope === 'mine') {
            $where = self::mine($uid);
            $params = [$uid];
        } else {
            $where = '(t.user_id = ? AND (t.assignee_id IS NULL OR t.assignee_id <> ?))';
            $params = [$uid, $uid];
        }
        $where .= ' AND t.blocked_at IS NULL';               // مسدودشده‌ها فقط در پنجره‌ی «مسدودشده‌ها»
        if ($completed !== null) {
            $where .= ' AND t.is_completed = ?';
            $params[] = $completed ? 1 : 0;
        }
        return [$where, $params];
    }

    /**
     * تقویم: تسک‌های دارای سررسید در بازه‌ی [from, to].
     * scope: mine = مسئولش منم، others = ساخته‌ی من و واگذارشده به دیگران، all = هر دو (همان دو تب صفحه‌ی تسک‌ها).
     */
    public static function calendar(int $uid, string $from, string $to, string $scope, bool $withDone): array
    {
        $where = ['mine' => self::mine($uid), 'others' => '(t.user_id = ? AND (t.assignee_id IS NULL OR t.assignee_id <> ?))'][$scope] ?? '(' . self::mine($uid) . ' OR t.user_id = ?)';
        $params = [$uid, $uid];
        if ($scope === 'mine') {
            $params = [$uid];
        }
        $sql = self::base() . ' WHERE ' . $where . ' AND t.blocked_at IS NULL AND t.due_date BETWEEN ? AND ?' . ($withDone ? '' : ' AND t.is_completed = 0') . ' ORDER BY t.due_date, t.due_time IS NULL, t.due_time, t.id LIMIT 2000';
        return DB::all($sql, array_merge([$uid], $params, [$from, $to]));
    }

    /**
     * فهرست صفحه‌بندی‌شده.
     * @param string $scope mine = مسئول من | others = ساخته‌ی من برای دیگران
     * @param array  $f     فیلتر/مرتب‌سازی: q, sort, when (overdue|today|tomorrow|Y-m-d), prio (high|low), project
     * @return array{rows:array,total:int}
     */
    public static function paginate(int $uid, string $scope, ?bool $completed, int $limit, int $offset, array $f = []): array
    {
        [$where, $params] = self::scopeWhere($uid, $scope, $completed, (int) ($f['project'] ?? 0));
        // فیلترهای سریع «منتظر تأیید من» / «برگشت‌خورده برای اصلاح»: فهرست شناسه‌های آماده (مستقل از تب)
        if (isset($f['ids'])) {
            $ids = array_values(array_filter(array_map('intval', (array) $f['ids'])));
            $where = 't.blocked_at IS NULL AND ' . ($ids ? 't.id IN (' . DB::in($ids) . ')' : '0');
            $params = $ids;
        }
        if (!empty($f['assignee'])) {
            $where .= ' AND t.assignee_id = ?';
            $params[] = (int) $f['assignee'];
        }
        $q = (string) ($f['q'] ?? '');                 // جستجو پایین‌تر، بعد از بقیه‌ی فیلترها (برای غلط تایپی)
        $when = (string) ($f['when'] ?? '');
        if ($when === 'overdue') {
            $where .= ' AND t.is_completed = 0 AND ' . self::OVERDUE;
        } elseif ($when === 'today') {
            $where .= ' AND t.due_date = CURDATE()';
        } elseif ($when === 'tomorrow') {
            $where .= ' AND t.due_date = CURDATE() + INTERVAL 1 DAY';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $when)) {
            $where .= ' AND t.due_date = ?';
            $params[] = $when;
        }
        $prio = (string) ($f['prio'] ?? '');
        if ($prio === 'high' || $prio === 'low') {
            $where .= ' AND t.priority = ?';
            $params[] = $prio;
        }
        if (!empty($f['project'])) {
            $where .= ' AND t.project_id = ?';
            $params[] = (int) $f['project'];
        }
        if (!empty($f['label'])) {
            $where .= ' AND t.subject_id = ?';
            $params[] = (int) $f['label'];
        }
        // جستجو (v5.14): هر کلمه در عنوان یا توضیحات؛ اگر هیچ نتیجه‌ای نبود، با تحمل غلط تایپی در عنوان
        if (\App\Core\Search::tokens($q)) {
            [$qs, $qp] = \App\Core\Search::sql(['t.title', 't.description'], $q);
            $total = (int) DB::value('SELECT COUNT(*) FROM tasks t WHERE ' . $where . ' AND ' . $qs, array_merge($params, $qp));
            $fz = $total === 0 ? \App\Core\Search::fuzzyIds(DB::all('SELECT t.id, t.title FROM tasks t WHERE ' . $where . ' ORDER BY t.id DESC LIMIT ' . \App\Core\Search::FUZZY_SCAN, $params), $q, ['title']) : [];
            if ($fz) {
                $where .= ' AND t.id IN (' . DB::in($fz) . ')';
                $params = array_merge($params, $fz);
            } else {
                $where .= ' AND ' . $qs;
                $params = array_merge($params, $qp);
            }
        }
        $total = (int) DB::value('SELECT COUNT(*) FROM tasks t WHERE ' . $where, $params);
        switch ($f['sort'] ?? 'created') {
            case 'due':
                $order = 't.due_date IS NULL, t.due_date ASC, t.due_time IS NULL, t.due_time ASC, t.id DESC';
                break;
            case 'priority':
                $order = "COALESCE(t.priority_percent, CASE t.priority WHEN 'high' THEN 75 WHEN 'low' THEN 25 ELSE 50 END) DESC, t.due_date IS NULL, t.due_date ASC, t.id DESC";
                break;
            case 'project':
                $order = 'p.title IS NULL, p.title ASC, t.created_at DESC, t.id DESC';
                break;
            case 'subject':
                $order = 's.title IS NULL, s.title ASC, t.created_at DESC, t.id DESC';
                break;
            case 'created':
                $order = $completed ? 't.completed_at DESC, t.id DESC' : 't.created_at DESC, t.id DESC';
                break;
            default:
                // هوشمند (پیش‌فرض): بدون زمان، امروز، فردا، بعدها، عقب‌افتاده، انجام‌شده؛ در هر گروه اولویت بیشتر بالاتر
                $order = self::groupSql($uid) . ', ' . "COALESCE(t.priority_percent, CASE t.priority WHEN 'high' THEN 75 WHEN 'low' THEN 25 ELSE 50 END) DESC, t.due_date, t.due_time IS NULL, t.due_time, t.id DESC";
        }
        $rows = DB::all(self::base() . ' WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?', array_merge([$uid], $params, [$limit, $offset]));
        return ['rows' => $rows, 'total' => $total];
    }

    /** تاریخ‌های سررسید موجود در همین بخش (برای فیلتر «تسک‌های فلان روز»). */
    public static function dueDates(int $uid, string $scope, ?bool $completed, int $projectId = 0): array
    {
        [$where, $params] = self::scopeWhere($uid, $scope, $completed, $projectId);
        return DB::column('SELECT DISTINCT t.due_date FROM tasks t WHERE ' . $where . ' AND t.due_date IS NOT NULL ORDER BY t.due_date LIMIT 60', $params);
    }

    /** لیست API: تسک‌هایی که کاربر سازنده یا مسئول آن‌هاست. */
    public static function apiList(int $uid, string $status, int $limit, int $offset): array
    {
        $where = '(t.user_id = ? OR ' . self::mine($uid) . ') AND t.blocked_at IS NULL';
        $params = [$uid, $uid];
        if ($status === 'pending') {
            $where .= ' AND t.is_completed = 0';
        } elseif ($status === 'completed') {
            $where .= ' AND t.is_completed = 1';
        }
        return DB::all(self::base() . ' WHERE ' . $where . ' ORDER BY t.created_at DESC, t.id DESC LIMIT ? OFFSET ?', array_merge([$uid], $params, [$limit, $offset]));
    }

    public static function stats(int $uid): array
    {
        // v7.7 (DATA-003): completed = تأییدشده، waiting = تحویل‌شده و منتظر تأیید، pending = در جریان (هنوز تحویل نشده)
        $r = DB::one("SELECT COUNT(*) total, COALESCE(SUM(t.is_completed = 1),0) completed, COALESCE(SUM(" . self::WAITING . "),0) waiting, COALESCE(SUM(" . self::UNDELIVERED . "),0) pending,
                COALESCE(SUM(t.priority = 'high'),0) high, COALESCE(SUM(t.priority = 'medium'),0) medium, COALESCE(SUM(t.priority = 'low'),0) low
                FROM tasks t WHERE t.assignee_id = ? AND t.blocked_at IS NULL", [$uid]) ?? [];
        return array_map('intval', $r + ['total' => 0, 'completed' => 0, 'waiting' => 0, 'pending' => 0, 'high' => 0, 'medium' => 0, 'low' => 0]);
    }

    public static function create(array $d): int
    {
        // تسک جدید در «وضعیت اول بعد از ایجاد تسک» بورد همان پروژه (v6.1؛ پیش‌فرض «برنامه‌ریزی‌نشده»)
        $d += ['board_status' => \App\Services\BoardConfig::start(isset($d['project_id']) ? (int) $d['project_id'] : null), 'board_moved_at' => date('Y-m-d H:i:s')];
        $id = DB::insert('tasks', $d);
        self::ensureShareToken($id);
        return $id;
    }

    /** کارت‌های بورد پروژه: WHERE آماده با پارامترهایش (اولین پارامتر = بیننده برای دسترسی تسک). */
    public static function board(string $where, array $params, int $limit = 600): array
    {
        return DB::all(self::base() . ' WHERE (' . $where . ') AND t.blocked_at IS NULL' . " ORDER BY t.board_status = 'done', CASE WHEN t.board_status = 'done' THEN COALESCE(t.completed_at, t.board_moved_at) END DESC, COALESCE(t.priority_percent, 50) DESC, t.due_date IS NULL, t.due_date, t.due_time, t.id DESC LIMIT " . max(1, $limit), $params);
    }

    /** تسک‌های آرشیوشده‌ی برد (جدیدترین آرشیو اول). */
    public static function archived(string $where, array $params): array
    {
        return DB::all(self::base() . ' WHERE ' . $where . ' AND t.archived_at IS NOT NULL AND t.blocked_at IS NULL ORDER BY t.archived_at DESC, t.id DESC LIMIT 2000', $params);
    }

    public static function ensureShareToken(int $id): string
    {
        $t = (string) DB::value('SELECT share_token FROM tasks WHERE id = ?', [$id]);
        if ($t !== '') {
            return $t;
        }
        $t = bin2hex(random_bytes(16));
        DB::run('UPDATE tasks SET share_token = ? WHERE id = ? AND share_token IS NULL', [$t, $id]);
        return (string) DB::value('SELECT share_token FROM tasks WHERE id = ?', [$id]);
    }

    public static function toggle(int $id): array
    {
        $row = DB::one('SELECT is_completed, project_id FROM tasks WHERE id = ?', [$id]) ?? ['is_completed' => 0, 'project_id' => null];
        $done = (int) $row['is_completed'] === 1;
        // وضعیت بورد همگام می‌ماند: تکمیل = done، بازگشت = ستون شروع بورد پروژه
        if ($done) {
            DB::run("UPDATE tasks SET is_completed = 0, completed_at = NULL, board_status = ?, board_moved_at = NOW(), archived_at = NULL WHERE id = ?",
                [\App\Services\BoardConfig::start($row['project_id'] !== null ? (int) $row['project_id'] : null), $id]);
        } else {
            DB::run("UPDATE tasks SET is_completed = 1, completed_at = NOW(), board_status = 'done', blocked_reason = NULL, board_moved_at = NOW() WHERE id = ?", [$id]);
        }
        return ['id' => $id, 'is_completed' => !$done];
    }

    /** تسک‌های سررسیدشده‌ی اخیر که هنوز اعلان نشده‌اند (فقط خواندن؛ علامت‌گذاری با markNotified بعد از نمایش، از مسیر POST) */
    public static function dueNotifications(int $uid): array
    {
        $rows = DB::all("SELECT t.id, t.title, t.description, t.due_date, t.due_time, t.share_token, p.title AS project_title
              FROM tasks t LEFT JOIN projects p ON p.id = t.project_id
             WHERE " . self::mine($uid) . " AND t.is_completed = 0 AND t.notified_at IS NULL AND t.blocked_at IS NULL
               AND t.due_date IS NOT NULL AND t.due_time IS NOT NULL
               AND TIMESTAMP(t.due_date, t.due_time) <= NOW()
               AND TIMESTAMP(t.due_date, t.due_time) >= (NOW() - INTERVAL 120 MINUTE)", [$uid]);
        return $rows;
    }

    /** «یادآوری نمایش داده شد» (POST /notifications/ack) — فقط تسک‌هایی که مسئولشان همین کاربر است */
    public static function markNotified(int $uid, array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
        if (!$ids) {
            return 0;
        }
        $ids = array_slice($ids, 0, 50);
        return DB::run('UPDATE tasks t SET t.notified_at = NOW() WHERE ' . self::mine($uid) . ' AND t.notified_at IS NULL AND id IN (' . DB::in($ids) . ')', array_merge([$uid], $ids));
    }

    // ───── آمار داشبورد ─────

    /** شرط «عقب‌افتاده» (همان فیلتر when=overdue صفحه‌ی تسک‌ها) */
    /** همه‌ی تسک‌های باز عقب‌افتاده (مستقل از ماه تقویم)؛ scope مثل تقویم: mine / others / all */
    public static function overdueCount(int $uid, string $scope): int
    {
        $where = ['mine' => self::mine($uid), 'others' => '(t.user_id = ? AND (t.assignee_id IS NULL OR t.assignee_id <> ?))'][$scope] ?? '(' . self::mine($uid) . ' OR t.user_id = ?)';
        $params = $scope === 'mine' ? [$uid] : [$uid, $uid];
        return (int) DB::value('SELECT COUNT(*) FROM tasks t WHERE ' . $where . ' AND t.blocked_at IS NULL AND t.is_completed = 0 AND ' . self::OVERDUE, $params);
    }

    /**
     * «عقب‌افتاده» فقط روزهای قبل (v4.1، تعریف واحد در پیشخوان، تقویم، فهرست و تحلیل): تسکی که سررسیدش امروز است
     * در «امروز» می‌ماند و اگر ساعتش گذشته باشد فقط برچسب «ساعت گذشته» می‌گیرد.
     */
    public const OVERDUE = "t.due_date IS NOT NULL AND t.due_date < CURDATE() AND NOT (t.project_id IS NOT NULL AND t.archived_at IS NULL AND t.board_status = 'review')";
    // v7.7 (DATA-001): تسکی که مسئول تحویل داده و منتظر تأیید سرپرست است عقب‌افتاده نیست (کار مسئول تمام است؛ تأخیرش با روز تحویل
    // در KPI سنجیده می‌شود). همین تعریف در پیشخوان، «تیم من»، تقویم، فهرست تسک‌ها، تحلیل پروژه و پنل مدیر به کار می‌رود.
    /** «باز و تحویل‌نشده» (در جریان نزد مسئول): نه انجام‌شده، نه منتظر تأیید سرپرست */
    public const UNDELIVERED = "t.is_completed = 0 AND NOT (t.project_id IS NOT NULL AND t.archived_at IS NULL AND t.board_status = 'review')";
    /** منتظر تأیید سرپرست (تحویل‌شده، هنوز تأییدنشده) */
    public const WAITING = "t.is_completed = 0 AND t.project_id IS NOT NULL AND t.archived_at IS NULL AND t.board_status = 'review'";
    /** گروه مرتب‌سازی هوشمند (v4.1.1): ۰ بدون زمان، ۱ امروز، ۲ فردا، ۳ بعدها (پیش رو)، ۴ عقب‌افتاده، ۵ انجام‌شده */
    /**
     * مرتب‌سازی هوشمند (v5.4): نیاز به اصلاح (برگشت‌خورده) ← عقب‌افتاده ← امروز ← بدون زمان ← پیش رو ← منتظر تأیید سرپرست (جمع‌شده) ← انجام‌شده.
     * همان ترتیب در PHP: groupOf() و نام‌ها GROUP_NAMES (سرتیتر گروه‌ها در لیست).
     */
    public const GROUP_SQL = "CASE WHEN t.is_completed = 1 THEN 6 WHEN t.project_id IS NOT NULL AND t.archived_at IS NULL AND t.board_status = 'rework' THEN 0 WHEN t.project_id IS NOT NULL AND t.archived_at IS NULL AND t.board_status = 'review' THEN 5 WHEN t.due_date IS NOT NULL AND t.due_date < CURDATE() THEN 1 WHEN t.due_date = CURDATE() THEN 2 WHEN t.due_date IS NULL THEN 3 ELSE 4 END";
    public const GROUP_NAMES = ['نیاز به اصلاح', 'عقب‌افتاده', 'امروز', 'بدون زمان', 'پیش رو', 'منتظر تأیید سرپرست', 'انجام‌شده'];

    /** مرتب‌سازی هوشمند (سازگاری با فراخوانی قدیمی) */
    public static function groupSql(int $uid): string
    {
        return self::GROUP_SQL;
    }

    public static function groupOf(array $t): int
    {
        if ((int) $t['is_completed'] === 1) {
            return 6;
        }
        $onBoard = $t['project_id'] !== null && empty($t['archived_at']);
        if ($onBoard && ($t['board_status'] ?? '') === 'rework') {
            return 0;
        }
        if ($onBoard && ($t['board_status'] ?? '') === 'review') {
            return 5;
        }
        $today = date('Y-m-d');
        if ($t['due_date'] && $t['due_date'] < $today) {
            return 1;
        }
        return $t['due_date'] === $today ? 2 : ($t['due_date'] ? 4 : 3);
    }

    /**
     * پیشخوان: تسک‌های امروز (سررسید امروز که هنوز دیر نشده) و عقب‌افتاده‌ی کاربر (مسئول = خودش، انجام‌نشده).
     * @return array{today:array, overdue:array, today_count:int, overdue_count:int}
     */
    public static function focus(int $uid, int $limit = 12): array
    {
        $today = self::mine($uid) . ' AND t.blocked_at IS NULL AND t.is_completed = 0 AND t.due_date = CURDATE()';
        $late = self::mine($uid) . ' AND t.blocked_at IS NULL AND t.is_completed = 0 AND ' . self::OVERDUE;
        $rows = static function (string $where, string $order) use ($uid, $limit): array {
            $list = DB::all(self::base() . ' WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ?', [$uid, $uid, $limit]);
            foreach ($list as &$t) {
                $t['share_token'] = $t['share_token'] ?: self::ensureShareToken((int) $t['id']);
            }
            return $list;
        };
        $overdue = $rows($late, 't.due_date, t.due_time IS NULL, t.due_time, t.id');
        foreach ($overdue as &$t) {
            // روزهای تأخیر (پیشخوان v5.6): روز تقویمی از سررسید تا امروز، سمت سرور
            $t['late_days'] = max(1, (int) ((strtotime(date('Y-m-d')) - strtotime((string) $t['due_date'])) / 86400));
        }
        unset($t);
        $both = '(' . $today . ') OR (' . $late . ')';
        // ترکیب اولویت‌ها: همه‌ی تسک‌های امروز + عقب‌افتاده (نه فقط ردیف‌های نمایش‌داده‌شده)
        $mix = DB::one("SELECT COALESCE(SUM(t.priority = 'high'),0) high, COALESCE(SUM(t.priority = 'medium'),0) medium, COALESCE(SUM(t.priority = 'low'),0) low
            FROM tasks t WHERE $both", [$uid, $uid]) ?? [];
        // پیشنهاد شروع: اولویت بالاتر، در اولویت برابر عقب‌افتاده جلوتر از امروز، بعد سررسید قدیمی‌تر
        $suggest = DB::one(self::base() . " WHERE $both ORDER BY FIELD(t.priority, 'high', 'medium', 'low'), t.due_date < CURDATE() DESC, t.due_date, t.due_time IS NULL, t.due_time, t.id LIMIT 1", [$uid, $uid, $uid]);
        if ($suggest) {
            $suggest['share_token'] = $suggest['share_token'] ?: self::ensureShareToken((int) $suggest['id']);
        }
        return [
            'today' => $rows($today, 't.due_time IS NULL, t.due_time, t.id'),
            'overdue' => $overdue,
            'today_count' => (int) DB::value('SELECT COUNT(*) FROM tasks t WHERE ' . $today, [$uid]),
            'overdue_count' => (int) DB::value('SELECT COUNT(*) FROM tasks t WHERE ' . $late, [$uid]),
            'mix' => ['high' => (int) ($mix['high'] ?? 0), 'medium' => (int) ($mix['medium'] ?? 0), 'low' => (int) ($mix['low'] ?? 0)],
            'suggest' => $suggest,
        ];
    }

    /** پیشخوان ← منتظر اقدام: منشن‌های دیده‌نشده، پیام‌های خوانده‌نشده‌ی چت (به تفکیک فرستنده) */
    public static function waiting(int $uid): array
    {
        $mentions = DB::all('SELECT m.id, m.task_id, m.created_at, t.title, t.share_token, u.first_name, u.last_name, u.avatar
            FROM task_mentions m INNER JOIN tasks t ON t.id = m.task_id LEFT JOIN users u ON u.id = m.actor_id
            WHERE m.user_id = ? AND m.seen_at IS NULL AND t.blocked_at IS NULL ORDER BY m.id DESC LIMIT 10', [$uid]);
        $chats = DB::all('SELECT c.sender_id, COUNT(*) AS cnt, MAX(c.id) AS last_id, MAX(c.created_at) AS last_at, u.first_name, u.last_name, u.avatar, u.is_bot
            FROM chat_messages c INNER JOIN users u ON u.id = c.sender_id
            WHERE c.receiver_id = ? AND c.is_read = 0 AND c.sender_id <> c.receiver_id
            GROUP BY c.sender_id, u.first_name, u.last_name, u.avatar, u.is_bot ORDER BY last_id DESC LIMIT 10', [$uid]);
        return ['mentions' => $mentions, 'chats' => $chats];
    }

    public static function dashboard(int $uid): array
    {
        $s = self::stats($uid);
        // «گردش کار»: هر عدد «باز از کل» (پایه‌ی شمارش یکی، مثل تب‌های فهرست تسک‌ها)
        $split = DB::one('SELECT COALESCE(SUM(user_id = ? AND assignee_id <> ?),0) by_me, COALESCE(SUM(user_id = ? AND assignee_id <> ? AND is_completed = 0),0) by_me_open,
                COALESCE(SUM(assignee_id = ? AND user_id <> ?),0) to_me, COALESCE(SUM(assignee_id = ? AND user_id <> ? AND is_completed = 0),0) to_me_open
                FROM tasks WHERE (user_id = ? OR assignee_id = ?) AND blocked_at IS NULL', [$uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid, $uid]) ?? ['by_me' => 0, 'by_me_open' => 0, 'to_me' => 0, 'to_me_open' => 0];
        // پرتکرارترین لیبل: فقط وقتی دست‌کم یک تسک (مسئولش کاربر) لیبل دارد
        $top = DB::one('SELECT s.title, COUNT(t.id) cnt FROM subjects s INNER JOIN tasks t ON t.subject_id = s.id AND t.blocked_at IS NULL AND t.assignee_id = ? WHERE s.user_id = ? GROUP BY s.id, s.title ORDER BY cnt DESC LIMIT 1', [$uid, $uid]);
        // اولویت تسک‌های در جریان (تحویل‌نشده)؛ همان پایه‌ی کارت «در جریان»
        $prioOpen = DB::one("SELECT COALESCE(SUM(t.priority = 'high'),0) high, COALESCE(SUM(t.priority = 'medium'),0) medium, COALESCE(SUM(t.priority = 'low'),0) low FROM tasks t WHERE t.assignee_id = ? AND " . self::UNDELIVERED . " AND t.blocked_at IS NULL", [$uid]) ?? [];

        $trend = ['labels' => [], 'tasks' => []];
        $td = array_column(DB::all('SELECT DATE(created_at) d, COUNT(*) c FROM tasks WHERE assignee_id = ? AND blocked_at IS NULL AND created_at >= (CURDATE() - INTERVAL 6 DAY) GROUP BY DATE(created_at)', [$uid]), 'c', 'd');
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $trend['labels'][] = \App\Core\Jalali::md($d);
            $trend['tasks'][] = (int) ($td[$d] ?? 0);
        }
        return [
            'stats' => $s,
            'ratio' => $s['total'] > 0 ? (int) round($s['completed'] / $s['total'] * 100) : null,
            'by_me' => (int) $split['by_me'], 'to_me' => (int) $split['to_me'], 'by_me_open' => (int) $split['by_me_open'], 'to_me_open' => (int) $split['to_me_open'],
            'top_subject' => $top ? $top['title'] : null, 'top_subject_count' => $top ? (int) $top['cnt'] : 0,
            'prio_open' => array_map('intval', $prioOpen + ['high' => 0, 'medium' => 0, 'low' => 0]),
            // v7.7 (DATA-004): یک تعریف در همه‌جا = ایجاد تا تحویل (TaskAnalytics::finishedAt)، فقط تسک‌هایی که مسئولشان خود کاربر است
            'task_time' => \App\Services\TaskAnalytics::durations(DB::all('SELECT t.is_completed, t.board_status, t.created_at, t.completed_at, t.delivered_at FROM tasks t WHERE t.assignee_id = ? AND t.blocked_at IS NULL AND (t.is_completed = 1 OR t.delivered_at IS NOT NULL)', [$uid])),
            'texts' => (int) DB::value('SELECT COUNT(*) FROM texts WHERE user_id = ?', [$uid]),
            'trend' => $trend,
        ];
    }
}
