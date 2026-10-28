<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Services\Access;

final class ProjectModel
{
    public static function find(int $id): ?array
    {
        return DB::one('SELECT p.*, u.first_name AS creator_first_name, u.last_name AS creator_last_name FROM projects p INNER JOIN users u ON u.id = p.user_id WHERE p.id = ?', [$id]);
    }

    /** پروژه‌هایی که کاربر سازنده یا عضو آن‌هاست. */
    public static function forUser(int $uid, string $q = '', string $role = 'all', int $limit = 200, int $offset = 0): array
    {
        $w = '(p.user_id = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?))';
        $p = [$uid, $uid];
        if ($q !== '') {
            $w .= ' AND p.title LIKE ?';
            $p[] = DB::like($q);
        }
        if ($role === 'creator') {
            $w .= ' AND p.user_id = ?';
            $p[] = $uid;
        } elseif ($role === 'member') {
            $w .= ' AND p.user_id <> ?';
            $p[] = $uid;
        }
        $rows = DB::all("SELECT p.*,
              (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) AS members_count,
              (SELECT COUNT(*) FROM project_blocks pb WHERE pb.project_id = p.id AND pb.user_id = ?) AS is_blocked_by_me,
              (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS tasks_count,
              (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_completed = 1) AS tasks_done,
              (SELECT permissions FROM project_members pm2 WHERE pm2.project_id = p.id AND pm2.user_id = ? LIMIT 1) AS my_permissions,
              (SELECT task_permissions FROM project_members pm3 WHERE pm3.project_id = p.id AND pm3.user_id = ? LIMIT 1) AS my_task_permissions,
              (SELECT board_permissions FROM project_members pm4 WHERE pm4.project_id = p.id AND pm4.user_id = ? LIMIT 1) AS my_board_permissions
            FROM projects p WHERE $w ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?", array_merge([$uid, $uid, $uid, $uid], $p, [$limit, $offset]));
        foreach ($rows as &$r) {
            $r['is_creator'] = (int) $r['user_id'] === $uid;
            $r['perms'] = $r['is_creator'] ? array_keys(Access::PROJECT_PERMS) : Access::decode($r['my_permissions'], array_keys(Access::PROJECT_PERMS));
            $r['task_perms'] = $r['is_creator'] ? array_keys(Access::TASK_PERMS) : Access::decode($r['my_task_permissions'], array_keys(Access::TASK_PERMS));
            $r['board_perms'] = $r['is_creator'] ? array_keys(Access::BOARD_PERMS) : Access::decode($r['my_board_permissions'], array_keys(Access::BOARD_PERMS));
        }
        return $rows;
    }

    public static function members(int $projectId): array
    {
        return DB::all('SELECT pm.id AS member_row_id, pm.user_id, pm.permissions, pm.task_permissions, pm.board_permissions, pm.added_at, u.first_name, u.last_name, u.mobile, u.email, u.avatar, u.status AS user_status
            FROM project_members pm INNER JOIN users u ON u.id = pm.user_id WHERE pm.project_id = ? ORDER BY pm.added_at ASC, pm.id ASC', [$projectId]);
    }

    public static function pendingRequestsFor(int $uid): array
    {
        return DB::all("SELECT r.id, r.project_id, r.created_at, p.title, p.profile_image, p.description, u.first_name, u.last_name, u.mobile
            FROM project_join_requests r INNER JOIN projects p ON p.id = r.project_id INNER JOIN users u ON u.id = r.invited_by
            WHERE r.user_id = ? AND r.status = 'pending' ORDER BY r.created_at DESC", [$uid]);
    }

    public static function pendingUserIds(int $projectId): array
    {
        return array_map('intval', DB::column("SELECT user_id FROM project_join_requests WHERE project_id = ? AND status = 'pending'", [$projectId]));
    }

    public static function leaveHistory(int $uid): array
    {
        return DB::all('SELECT * FROM project_leave_history WHERE user_id = ? ORDER BY left_at DESC LIMIT 50', [$uid]);
    }

    /** آمار تحلیل پروژه. */
    public static function analytics(int $projectId): array
    {
        $totals = DB::one("SELECT COUNT(*) total, COALESCE(SUM(is_completed = 1),0) done, COALESCE(SUM(is_completed = 0),0) open,
              COALESCE(SUM(is_completed = 0 AND due_date IS NOT NULL AND due_date < CURDATE()),0) overdue,
              COALESCE(SUM(priority='high'),0) high, COALESCE(SUM(priority='medium'),0) medium, COALESCE(SUM(priority='low'),0) low,
              AVG(CASE WHEN is_completed = 1 AND completed_at > created_at THEN TIMESTAMPDIFF(MINUTE, created_at, completed_at) END)/60 avg_hours
            FROM tasks WHERE project_id = ?", [$projectId]) ?? [];
        $perMember = DB::all("SELECT u.id, u.first_name, u.last_name, u.mobile, u.avatar,
              COUNT(t.id) total, COALESCE(SUM(t.is_completed = 1),0) done, COALESCE(SUM(t.is_completed = 0),0) open,
              COALESCE(SUM(t.is_completed = 0 AND t.due_date IS NOT NULL AND t.due_date < CURDATE()),0) overdue
            FROM project_members pm INNER JOIN users u ON u.id = pm.user_id
            LEFT JOIN tasks t ON t.project_id = pm.project_id AND t.assignee_id = u.id
            WHERE pm.project_id = ? GROUP BY u.id, u.first_name, u.last_name, u.mobile, u.avatar ORDER BY done DESC, total DESC", [$projectId]);
        $created = array_column(DB::all('SELECT DATE(created_at) d, COUNT(*) c FROM tasks WHERE project_id = ? AND created_at >= (CURDATE() - INTERVAL 13 DAY) GROUP BY DATE(created_at)', [$projectId]), 'c', 'd');
        $finished = array_column(DB::all('SELECT DATE(completed_at) d, COUNT(*) c FROM tasks WHERE project_id = ? AND completed_at >= (CURDATE() - INTERVAL 13 DAY) GROUP BY DATE(completed_at)', [$projectId]), 'c', 'd');
        $trend = ['labels' => [], 'created' => [], 'done' => []];
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $trend['labels'][] = \App\Core\Jalali::md($d);
            $trend['created'][] = (int) ($created[$d] ?? 0);
            $trend['done'][] = (int) ($finished[$d] ?? 0);
        }
        $recent = DB::all('SELECT t.id, t.title, t.is_completed, t.priority, t.due_date, t.created_at, u.first_name, u.last_name, u.mobile FROM tasks t LEFT JOIN users u ON u.id = t.assignee_id WHERE t.project_id = ? ORDER BY t.created_at DESC LIMIT 8', [$projectId]);
        return ['totals' => array_map(static fn($v) => $v === null ? 0 : (float) $v, $totals), 'members' => $perMember, 'trend' => $trend, 'recent' => $recent];
    }
}
