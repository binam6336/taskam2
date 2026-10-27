<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** پشتیبانی: کاربر ↔ ادمین. */
final class SupportModel
{
    public const PRIORITIES = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد', 'very_high' => 'خیلی زیاد'];

    public static function userTickets(int $uid): array
    {
        return DB::all("SELECT st.*, (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id = st.id) AS msg_count,
            (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id = st.id AND sm.sender_role = 'admin' AND sm.is_read_by_user = 0) AS unread_count
            FROM support_tickets st WHERE st.user_id = ? ORDER BY COALESCE(st.last_reply_at, st.created_at) DESC", [$uid]);
    }

    public static function messages(int $ticketId): array
    {
        return DB::all('SELECT sm.*, u.first_name, u.last_name, u.mobile, u.avatar FROM support_messages sm INNER JOIN users u ON u.id = sm.sender_id WHERE sm.ticket_id = ? ORDER BY sm.created_at ASC, sm.id ASC', [$ticketId]);
    }

    public static function adminTickets(string $status = 'all'): array
    {
        $w = in_array($status, ['open', 'answered', 'closed'], true) ? 'WHERE st.status = ?' : '';
        $p = $w ? [$status] : [];
        return DB::all("SELECT st.*, u.first_name, u.last_name, u.mobile,
            (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id = st.id) AS msg_count,
            (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id = st.id AND sm.sender_role = 'user' AND sm.is_read_by_admin = 0) AS unread_count
            FROM support_tickets st INNER JOIN users u ON u.id = st.user_id $w ORDER BY (st.status = 'closed') ASC, COALESCE(st.last_reply_at, st.created_at) DESC", $p);
    }

    public static function unreadForUser(int $uid): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM support_messages sm INNER JOIN support_tickets st ON st.id = sm.ticket_id WHERE st.user_id = ? AND sm.sender_role = 'admin' AND sm.is_read_by_user = 0", [$uid]);
    }

    public static function unreadForAdmin(): int
    {
        return (int) DB::value("SELECT COUNT(*) FROM support_messages WHERE sender_role = 'user' AND is_read_by_admin = 0");
    }
}
