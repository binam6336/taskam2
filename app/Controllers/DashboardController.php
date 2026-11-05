<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Models\ProjectModel;
use App\Models\SubjectModel;
use App\Models\TaskModel;
use App\Services\BoardService;
use App\Services\ColleagueService;

final class DashboardController extends Controller
{
    public function index()
    {
        $uid = $this->uid();
        $this->view('tasks/dashboard', [
            'd' => TaskModel::dashboard($uid),
            'me' => Auth::user() ?? [],
            // باکس مهم: تسک‌های امروز و عقب‌افتاده
            'focus' => TaskModel::focus($uid),
            // منتظر اقدام: منشن‌ها، پیام‌های چت، درخواست همکاری و دعوت به پروژه
            // + تسک‌های منتظر بررسی سرپرست (برای کسی که می‌تواند تأیید کند) و برگشته به بازبینی مسئول (برای مسئول)
            'waiting' => TaskModel::waiting($uid) + BoardService::waiting($uid) + [
                'colleagues' => ColleagueService::incomingRequests($uid),
                'invites' => ProjectModel::pendingRequestsFor($uid),
            ],
            // «تیم من» (v5.4): برای کسی که در پروژه‌ای دسترسی تحلیل دارد — عقب‌افتاده‌ها، منتظر تأیید، مسدودها و اعضای پرعقب‌افتاده
            'team' => self::team($uid),
            // پنجره‌ی جزئیات/ویرایش تسک داخل خود پیشخوان
            'subjects' => SubjectModel::forUser($uid), 'projects' => ProjectModel::forUser($uid), 'assignees' => TaskController::assigneeOptions($uid),
        ], ['title' => 'پیشخوان', 'page' => 'dashboard']);
    }

    /** پروژه‌هایی که کاربر در آن‌ها دسترسی «مشاهده‌ی تحلیل» دارد و عضو دیگری هم دارند، با وضعیت تیم */
    private static function team(int $uid): array
    {
        $out = [];
        foreach (ProjectModel::forUser($uid) as $p) {
            $pid = (int) $p['id'];
            if (!\App\Services\Access::projectCan($pid, $uid, 'view_analytics')) {
                continue;
            }
            $members = (int) DB::value('SELECT COUNT(*) FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.project_id = ? AND pm.user_id <> p.user_id', [$pid]);
            if ($members < 1) {
                continue;                                   // پروژه‌ی تک‌نفره «تیم» ندارد
            }
            $c = DB::one("SELECT COALESCE(SUM(is_completed = 0 AND blocked_at IS NULL AND due_date IS NOT NULL AND due_date < CURDATE()),0) overdue,
                    COALESCE(SUM(is_completed = 0 AND blocked_at IS NULL AND archived_at IS NULL AND board_status = 'review'),0) review,
                    COALESCE(SUM(is_completed = 0 AND blocked_at IS NULL AND archived_at IS NULL AND board_status = 'rework'),0) rework,
                    COALESCE(SUM(blocked_at IS NOT NULL),0) blocked,
                    COALESCE(SUM(is_completed = 0 AND blocked_at IS NULL),0) open
                FROM tasks WHERE project_id = ?", [$pid]) ?? [];
            $top = DB::all("SELECT u.id, u.pid, u.first_name, u.last_name, u.avatar, COUNT(*) n FROM tasks t JOIN users u ON u.id = t.assignee_id
                WHERE t.project_id = ? AND t.is_completed = 0 AND t.blocked_at IS NULL AND t.due_date IS NOT NULL AND t.due_date < CURDATE()
                GROUP BY u.id, u.pid, u.first_name, u.last_name, u.avatar ORDER BY n DESC LIMIT 3", [$pid]);
            $out[] = ['project' => $p, 'members' => $members + 1, 'c' => array_map('intval', $c), 'top' => $top];
        }
        usort($out, static fn($a, $b) => ($b['c']['overdue'] ?? 0) <=> ($a['c']['overdue'] ?? 0));
        return array_slice($out, 0, 6);
    }
}
