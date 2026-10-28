<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Models\ChatModel;
use App\Models\SupportModel;
use App\Models\TaskModel;

/**
 * سیستم زنده‌ی یکپارچه (polling سبک):
 *  - شمارنده‌ی نشان‌های سایدبار (پیام، پشتیبانی، همکاران، پروژه‌ها، تسک‌ها)
 *  - «امضا» (signature) داده‌های صفحه‌ی جاری؛ با تغییر آن، مرورگر فقط بخش‌های data-live را تازه می‌کند
 *  - پیام‌های جدید و تسک‌های سررسیدشده برای اعلان مرورگر
 */
final class LiveController extends Controller
{
    public function poll(): void
    {
        $u = $this->user();
        $uid = (int) $u['id'];
        $admin = Auth::isAdmin($u) && !Auth::isImpersonating();
        $scope = (string) preg_replace('/[^a-z\-]/', '', (string) ($this->request->query['scope'] ?? ''));

        if ($admin) {
            $this->json([
                'status' => 'success',
                'counts' => ['support' => SupportModel::unreadForAdmin()],
                'sig' => $this->signature($uid, true, $scope),
                'messages' => [],
                'due' => [],
                'msg_base' => null,
            ]);
        }

        $counts = [
            'chat'       => ChatModel::totalUnread($uid),
            'support'    => SupportModel::unreadForUser($uid),
            'colleagues' => (int) DB::value("SELECT COUNT(*) FROM colleague_requests WHERE target_id = ? AND status = 'pending'", [$uid]),
            'projects'   => (int) DB::value("SELECT COUNT(*) FROM project_join_requests WHERE user_id = ? AND status = 'pending'", [$uid]),
            'tasks'      => (int) DB::value('SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND is_completed = 0', [$uid]),
        ];

        // ── پیام‌های جدید برای اعلان ──
        $messages = [];
        $base = null;
        if (!empty($this->request->query['baseline'])) {
            $base = (int) DB::value('SELECT COALESCE(MAX(id), 0) FROM chat_messages WHERE receiver_id = ? AND sender_id <> receiver_id', [$uid]);
        } else {
            $after = (int) ($this->request->query['last_msg'] ?? 0);
            $rows = DB::all('SELECT cm.id, cm.sender_id, cm.receiver_id, cm.message, cm.attachment_name, u.first_name, u.last_name, u.mobile
                               FROM chat_messages cm INNER JOIN users u ON u.id = cm.sender_id
                              WHERE cm.receiver_id = ? AND cm.sender_id <> cm.receiver_id AND cm.is_read = 0 AND cm.id > ?
                              ORDER BY cm.id ASC LIMIT 5', [$uid, $after]);
            foreach ($rows as $r) {
                $plain = (string) ChatModel::open($r['message'], (int) $r['sender_id'], (int) $r['receiver_id']);
                $text = trim((string) preg_replace('/\[\[(?:task|call):[a-f0-9]+\]\]/i', '', $plain));
                if ($text === '' && !empty($r['attachment_name'])) {
                    $text = '📎 ' . ChatModel::open($r['attachment_name'], (int) $r['sender_id'], (int) $r['receiver_id']);
                }
                $messages[] = [
                    'id'     => (int) $r['id'],
                    'sender' => (int) $r['sender_id'],
                    'title'  => 'پیام جدید – ' . full_name($r),
                    'body'   => self::clip($text, 160),
                    'url'    => url('/chat?user=' . (int) $r['sender_id']),
                ];
            }
        }

        // ── یادآوری تسک (در لحظه‌ی ساعت و دقیقه‌ی مهلت) ──
        $due = [];
        foreach (TaskModel::dueNotifications($uid) as $t) {
            $tok = $t['share_token'] ?: TaskModel::ensureShareToken((int) $t['id']);
            $desc = self::clip(trim((string) $t['description']), 160);
            $due[] = [
                'id'    => (int) $t['id'],
                'title' => 'یادآوری وظیفه – ' . $t['title'],
                'body'  => $desc !== '' ? $desc : 'زمان انجام این وظیفه فرا رسیده است.',
                'url'   => url('/tasks?task=' . $tok),
            ];
        }

        $this->json([
            'status'   => 'success',
            'counts'   => $counts,
            'sig'      => $this->signature($uid, false, $scope),
            'messages' => $messages,
            'due'      => $due,
            'msg_base' => $base,
        ]);
    }

    private static function clip(string $s, int $max): string
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));
        return mb_strlen($s, 'UTF-8') > $max ? mb_substr($s, 0, $max, 'UTF-8') . '…' : $s;
    }

    /** امضای داده‌های مرتبط با صفحه‌ی جاری؛ تغییر آن یعنی باید بخش‌های زنده تازه شوند. */
    private function signature(int $uid, bool $admin, string $scope): string
    {
        $agg = static function (string $sql, array $params = []): string {
            $r = DB::one($sql, $params) ?: [];
            return implode('.', array_map('strval', $r));
        };
        $p = [];
        $mine = 'SELECT id FROM tasks WHERE user_id = ? OR assignee_id = ?';

        if ($admin) {
            if ($scope === 'admin-users') {
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(CRC32(CONCAT_WS('|', id, first_name, last_name, mobile, email, role, status, last_login_at))), 0) h FROM users WHERE is_bot = 0");
            } elseif ($scope === 'admin-support') {
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(MAX(id), 0) m, COALESCE(SUM(CRC32(CONCAT_WS('|', id, status, last_reply_at))), 0) h FROM support_tickets");
                $p[] = $agg('SELECT COUNT(*) c, COALESCE(SUM(is_read_by_admin), 0) r FROM support_messages');
            }
            return md5(implode('~', $p));
        }

        switch ($scope) {
            case 'tasks':
            case 'subjects':
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(CRC32(CONCAT_WS('|', id, title, description, priority, is_completed, due_date, due_time, assignee_id, subject_id, project_id))), 0) h FROM tasks WHERE user_id = ? OR assignee_id = ?", [$uid, $uid]);
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(MAX(id), 0) m, COALESCE(SUM(CRC32(note)), 0) h FROM task_notes WHERE task_id IN ($mine)", [$uid, $uid]);
                $p[] = $agg("SELECT COUNT(*) c FROM task_attachments WHERE task_id IN ($mine)", [$uid, $uid]);
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(CRC32(CONCAT_WS('|', id, title, color))), 0) h FROM subjects WHERE user_id = ?", [$uid]);
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(CRC32(CONCAT_WS('|', p.id, p.title, p.profile_image))), 0) h FROM projects p WHERE p.user_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?)", [$uid, $uid]);
                break;
            case 'board':
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(CRC32(CONCAT_WS('|', id, title, board_status, blocked_reason, is_completed, priority_percent, due_date, due_time, assignee_id, project_id))), 0) h FROM tasks
                    WHERE user_id = ? OR assignee_id = ? OR project_id IN (SELECT id FROM projects WHERE user_id = ?) OR project_id IN (SELECT project_id FROM project_members WHERE user_id = ?)", [$uid, $uid, $uid, $uid]);
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(CRC32(CONCAT_WS('|', pm.user_id, pm.task_permissions))), 0) h FROM project_members pm WHERE pm.project_id IN (SELECT project_id FROM project_members WHERE user_id = ?)", [$uid]);
                break;
            case 'calls':
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(CRC32(CONCAT_WS('|', id, status, assignee_id, first_name, last_name, mobile, email, store, website, description))), 0) h FROM call_requests WHERE user_id = ? OR assignee_id = ?", [$uid, $uid]);
                break;
            case 'colleagues':
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(CRC32(CONCAT_WS('|', c.id, c.permissions, u.first_name, u.last_name, u.status))), 0) h FROM colleagues c INNER JOIN users u ON u.id = c.colleague_user_id WHERE c.user_id = ?", [$uid]);
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(id), 0) s FROM colleague_requests WHERE (target_id = ? OR requester_id = ?) AND status = 'pending'", [$uid, $uid]);
                $p[] = $agg('SELECT COUNT(*) c, COALESCE(SUM(id), 0) s FROM colleague_blocks WHERE blocker_user_id = ?', [$uid]);
                break;
            case 'projects':
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(CRC32(CONCAT_WS('|', p.id, p.title, p.description, p.max_members, p.profile_image, p.status))), 0) h FROM projects p WHERE p.user_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?)", [$uid, $uid]);
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(CRC32(CONCAT_WS('|', pm.id, pm.user_id, pm.permissions))), 0) h FROM project_members pm WHERE pm.project_id IN (SELECT project_id FROM project_members WHERE user_id = ?)", [$uid]);
                $p[] = $agg('SELECT COUNT(*) c, COALESCE(SUM(is_completed), 0) d FROM tasks WHERE project_id IN (SELECT project_id FROM project_members WHERE user_id = ?)', [$uid]);
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(id), 0) s FROM project_join_requests WHERE status = 'pending' AND (user_id = ? OR project_id IN (SELECT id FROM projects WHERE user_id = ?))", [$uid, $uid]);
                $p[] = $agg('SELECT COUNT(*) c FROM project_leave_history WHERE user_id = ?', [$uid]);
                break;
            case 'support':
                $p[] = $agg("SELECT COUNT(*) c, COALESCE(SUM(CRC32(CONCAT_WS('|', id, status, last_reply_at))), 0) h FROM support_tickets WHERE user_id = ?", [$uid]);
                $p[] = $agg('SELECT COUNT(*) c, COALESCE(SUM(is_read_by_user), 0) r FROM support_messages WHERE ticket_id IN (SELECT id FROM support_tickets WHERE user_id = ?)', [$uid]);
                break;
            default:
                return '';
        }
        return md5(implode('~', $p));
    }
}
