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
        'created'  => 'تاریخ ایجاد',
        'due'      => 'تاریخ انجام (سررسید)',
        'priority' => 'اولویت',
        'project'  => 'پروژه',
        'subject'  => 'لیبل',
    ];

    /** شرط WHERE مشترک فهرست و فیلترها. */
    private static function scopeWhere(int $uid, string $scope, bool $completed): array
    {
        $where = $scope === 'mine'
            ? 't.assignee_id = ?'
            : '(t.user_id = ? AND (t.assignee_id IS NULL OR t.assignee_id <> ?))';
        $params = $scope === 'mine' ? [$uid] : [$uid, $uid];
        $where .= ' AND t.is_completed = ?';
        $params[] = $completed ? 1 : 0;
        return [$where, $params];
    }

    /**
     * فهرست صفحه‌بندی‌شده.
     * @param string $scope mine = مسئول من | others = ساخته‌ی من برای دیگران
     * @param array  $f     فیلتر/مرتب‌سازی: q, sort, when (overdue|today|tomorrow|Y-m-d), prio (high|low), project
     * @return array{rows:array,total:int}
     */
    public static function paginate(int $uid, string $scope, bool $completed, int $limit, int $offset, array $f = []): array
    {
        [$where, $params] = self::scopeWhere($uid, $scope, $completed);
        $q = (string) ($f['q'] ?? '');
        if ($q !== '') {
            $where .= ' AND (t.title LIKE ? OR t.description LIKE ?)';
            $params[] = DB::like($q);
            $params[] = DB::like($q);
        }
        $when = (string) ($f['when'] ?? '');
        if ($when === 'overdue') {
            $where .= " AND t.is_completed = 0 AND t.due_date IS NOT NULL AND TIMESTAMP(t.due_date, COALESCE(t.due_time, '23:59:59')) < NOW()";
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
            default:
                $order = $completed ? 't.completed_at DESC, t.id DESC' : 't.created_at DESC, t.id DESC';
        }
        $rows = DB::all(self::base() . ' WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?', array_merge([$uid], $params, [$limit, $offset]));
        return ['rows' => $rows, 'total' => $total];
    }

    /** تاریخ‌های سررسید موجود در همین بخش (برای فیلتر «تسک‌های فلان روز»). */
    public static function dueDates(int $uid, string $scope, bool $completed): array
    {
        [$where, $params] = self::scopeWhere($uid, $scope, $completed);
        return DB::column('SELECT DISTINCT t.due_date FROM tasks t WHERE ' . $where . ' AND t.due_date IS NOT NULL ORDER BY t.due_date LIMIT 60', $params);
    }

    /** لیست API: تسک‌هایی که کاربر سازنده یا مسئول آن‌هاست. */
    public static function apiList(int $uid, string $status, int $limit, int $offset): array
    {
        $where = '(t.user_id = ? OR t.assignee_id = ?)';
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
        $r = DB::one("SELECT COUNT(*) total, COALESCE(SUM(is_completed = 1),0) completed, COALESCE(SUM(is_completed = 0),0) pending,
                COALESCE(SUM(priority = 'high'),0) high, COALESCE(SUM(priority = 'medium'),0) medium, COALESCE(SUM(priority = 'low'),0) low
                FROM tasks WHERE assignee_id = ?", [$uid]) ?? [];
        return array_map('intval', $r + ['total' => 0, 'completed' => 0, 'pending' => 0, 'high' => 0, 'medium' => 0, 'low' => 0]);
    }

    public static function create(array $d): int
    {
        // تسک جدید در برد تیمی از «برنامه‌ریزی‌نشده» (Backlog) شروع می‌شود
        $d += ['board_status' => 'backlog', 'board_moved_at' => date('Y-m-d H:i:s')];
        $id = DB::insert('tasks', $d);
        self::ensureShareToken($id);
        return $id;
    }

    /** کارت‌های برد تیمی: WHERE آماده با پارامترهایش (اولین پارامتر = بیننده برای دسترسی تسک). */
    public static function board(string $where, array $params, int $limit = 600): array
    {
        return DB::all(self::base() . ' WHERE ' . $where . " ORDER BY t.board_status = 'done', CASE WHEN t.board_status = 'done' THEN COALESCE(t.completed_at, t.board_moved_at) END DESC, COALESCE(t.priority_percent, 50) DESC, t.due_date IS NULL, t.due_date, t.due_time, t.id DESC LIMIT " . max(1, $limit), $params);
    }

    /** تسک‌های آرشیوشده‌ی برد (جدیدترین آرشیو اول). */
    public static function archived(string $where, array $params): array
    {
        return DB::all(self::base() . ' WHERE ' . $where . ' AND t.archived_at IS NOT NULL ORDER BY t.archived_at DESC, t.id DESC LIMIT 2000', $params);
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
        $done = (int) DB::value('SELECT is_completed FROM tasks WHERE id = ?', [$id]) === 1;
        // وضعیت برد تیمی همگام می‌ماند: تکمیل = done، بازگشت = todo
        if ($done) {
            DB::run("UPDATE tasks SET is_completed = 0, completed_at = NULL, board_status = 'todo', board_moved_at = NOW(), archived_at = NULL WHERE id = ?", [$id]);
        } else {
            DB::run("UPDATE tasks SET is_completed = 1, completed_at = NOW(), board_status = 'done', blocked_reason = NULL, board_moved_at = NOW() WHERE id = ?", [$id]);
        }
        return ['id' => $id, 'is_completed' => !$done];
    }

    /** حذف تسک + فایل‌های پیوست و یادداشت‌ها از دیسک. */
    public static function delete(int $id): void
    {
        $tf = DB::column('SELECT file_path FROM task_attachments WHERE task_id = ?', [$id]);
        $nf = DB::column('SELECT na.file_path FROM note_attachments na INNER JOIN task_notes tn ON tn.id = na.note_id WHERE tn.task_id = ?', [$id]);
        DB::run('DELETE FROM tasks WHERE id = ?', [$id]);
        foreach ($tf as $f) {
            Uploader::delete('task_attachments', $f);
        }
        foreach ($nf as $f) {
            Uploader::delete('note_attachments', $f);
        }
    }

    /** تسک‌های سررسیدشده‌ی اخیر که هنوز اعلان نشده‌اند (و علامت‌گذاری آن‌ها). */
    public static function dueNotifications(int $uid): array
    {
        $rows = DB::all("SELECT t.id, t.title, t.description, t.due_date, t.due_time, t.share_token, p.title AS project_title
              FROM tasks t LEFT JOIN projects p ON p.id = t.project_id
             WHERE t.assignee_id = ? AND t.is_completed = 0 AND t.notified_at IS NULL
               AND t.due_date IS NOT NULL AND t.due_time IS NOT NULL
               AND TIMESTAMP(t.due_date, t.due_time) <= NOW()
               AND TIMESTAMP(t.due_date, t.due_time) >= (NOW() - INTERVAL 120 MINUTE)", [$uid]);
        foreach ($rows as $r) {
            DB::run('UPDATE tasks SET notified_at = NOW() WHERE id = ?', [(int) $r['id']]);
        }
        return $rows;
    }

    // ───── آمار داشبورد ─────

    public static function dashboard(int $uid): array
    {
        $s = self::stats($uid);
        $split = DB::one('SELECT COALESCE(SUM(user_id = ? AND assignee_id <> ?),0) by_me, COALESCE(SUM(assignee_id = ? AND user_id <> ?),0) to_me FROM tasks WHERE user_id = ? OR assignee_id = ?', [$uid, $uid, $uid, $uid, $uid, $uid]) ?? ['by_me' => 0, 'to_me' => 0];
        $top = DB::one('SELECT s.title, COUNT(t.id) cnt FROM subjects s LEFT JOIN tasks t ON t.subject_id = s.id WHERE s.user_id = ? GROUP BY s.id, s.title ORDER BY cnt DESC LIMIT 1', [$uid]);
        $dur = static fn(string $table, string $cond, array $p) => DB::one("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, completed_at))/60 a, MIN(TIMESTAMPDIFF(MINUTE, created_at, completed_at))/60 mi, MAX(TIMESTAMPDIFF(MINUTE, created_at, completed_at))/60 ma, COUNT(*) c FROM $table WHERE $cond AND completed_at IS NOT NULL AND completed_at > created_at", $p) ?? [];

        $trend = ['labels' => [], 'tasks' => []];
        $td = array_column(DB::all('SELECT DATE(created_at) d, COUNT(*) c FROM tasks WHERE assignee_id = ? AND created_at >= (CURDATE() - INTERVAL 6 DAY) GROUP BY DATE(created_at)', [$uid]), 'c', 'd');
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $trend['labels'][] = \App\Core\Jalali::md($d);
            $trend['tasks'][] = (int) ($td[$d] ?? 0);
        }
        return [
            'stats' => $s,
            'ratio' => $s['total'] > 0 ? (int) round($s['completed'] / $s['total'] * 100) : 0,
            'by_me' => (int) $split['by_me'], 'to_me' => (int) $split['to_me'],
            'top_subject' => $top['title'] ?? 'بدون لیبل',
            'task_time' => $dur('tasks', '(user_id = ? OR assignee_id = ?) AND is_completed = 1', [$uid, $uid]),
            'call_time' => $dur('call_requests', '(user_id = ? OR assignee_id = ?) AND status = 1', [$uid, $uid]),
            'calls' => CallModel::stats($uid),
            'texts' => (int) DB::value('SELECT COUNT(*) FROM texts WHERE user_id = ?', [$uid]),
            'trend' => $trend,
        ];
    }
}
