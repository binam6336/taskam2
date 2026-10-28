<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;
use App\Core\Uploader;
use App\Services\Push;

/** پشتیبانی: کاربر ↔ ادمین (میز تیکت). */
final class SupportModel
{
    public const PRIORITIES = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد', 'very_high' => 'خیلی زیاد'];
    public const PER_PAGE = [10, 20, 30, 50];
    /** فیلترهای فهرست: کلید → [عنوان، شرط SQL] */
    public const FILTERS = [
        'all' => ['همه', ''],
        'open' => ['باز', "st.status IN ('open','in_progress')"],
        'answered' => ['پاسخ‌داده‌شده', "st.status = 'answered'"],
        'closed' => ['بسته', "st.status = 'closed'"],
    ];
    public const MSG_MAX = 5000;

    /**
     * migration 008 اگر روی سرور اجرا نشده باشد، همین‌جا اعمال می‌شود (فقط ستون/مقدار اضافه می‌کند).
     * در هر درخواست صفحه‌ی تیکت یک کوئری سبک روی information_schema است.
     */
    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $col = (int) DB::value("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'support_messages' AND column_name = 'attachment'");
            $enum = (string) DB::value("SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'support_tickets' AND column_name = 'status'");
            if (strpos($enum, 'in_progress') === false) {
                DB::run("ALTER TABLE `support_tickets` MODIFY `status` ENUM('open','in_progress','answered','closed') NOT NULL DEFAULT 'open'");
            }
            if ($col === 0) {
                DB::run("ALTER TABLE `support_messages`
                    ADD COLUMN `attachment` VARCHAR(255) NULL DEFAULT NULL AFTER `message`,
                    ADD COLUMN `attachment_name` VARCHAR(255) NULL DEFAULT NULL AFTER `attachment`,
                    ADD COLUMN `attachment_size` INT UNSIGNED NULL DEFAULT NULL AFTER `attachment_name`,
                    ADD KEY `idx_sm_attachment` (`attachment`)");
            }
        } catch (\Throwable $e) {
            error_log('support schema: ' . $e->getMessage() . ' — database/migrations/008_support_desk.sql را اجرا کنید.');
        }
    }

    /**
     * فهرست صفحه‌بندی‌شده‌ی تیکت‌ها.
     * @param int|null $uid null = همه‌ی تیکت‌ها (ادمین)
     * @return array{rows:array,total:int,page:int,per:int,pages:int}
     */
    public static function tickets(?int $uid, string $filter, string $q, int $page, int $per): array
    {
        $w = [];
        $p = [];
        if ($uid !== null) {
            $w[] = 'st.user_id = ?';
            $p[] = $uid;
        }
        if (($cond = self::FILTERS[$filter][1] ?? '') !== '') {
            $w[] = $cond;
        }
        $q = trim($q);
        if ($q !== '') {
            $id = (int) ltrim(strtr($q, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']), '#');
            $l = DB::like($q);
            $or = ['st.subject LIKE ?'];
            $p[] = $l;
            if ($uid === null) {
                $or[] = "CONCAT_WS(' ', u.first_name, u.last_name) LIKE ?";
                $or[] = 'u.mobile LIKE ?';
                array_push($p, $l, $l);
            }
            if ($id > 0) {
                $or[] = 'st.id = ?';
                $p[] = $id;
            }
            $w[] = '(' . implode(' OR ', $or) . ')';
        }
        $where = $w ? 'WHERE ' . implode(' AND ', $w) : '';
        $from = "FROM support_tickets st INNER JOIN users u ON u.id = st.user_id $where";
        $total = (int) DB::value("SELECT COUNT(*) $from", $p);
        $per = in_array($per, self::PER_PAGE, true) ? $per : 20;
        $pages = max(1, (int) ceil($total / $per));
        $page = min(max(1, $page), $pages);
        $unreadSide = $uid === null ? "sm.sender_role = 'user' AND sm.is_read_by_admin = 0" : "sm.sender_role = 'admin' AND sm.is_read_by_user = 0";
        $order = $uid === null ? "(st.status = 'closed') ASC, " : '';
        $rows = DB::all("SELECT st.*, u.first_name, u.last_name, u.mobile, u.avatar,
                (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id = st.id) AS msg_count,
                (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id = st.id AND $unreadSide) AS unread_count,
                (SELECT sm.sender_role FROM support_messages sm WHERE sm.ticket_id = st.id ORDER BY sm.id DESC LIMIT 1) AS last_role,
                (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id = st.id AND sm.sender_role = 'admin') AS admin_replies
            $from ORDER BY {$order}COALESCE(st.last_reply_at, st.created_at) DESC, st.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per, 'pages' => $pages];
    }

    public static function find(int $id, ?int $uid = null): ?array
    {
        $t = DB::one('SELECT st.*, u.first_name, u.last_name, u.mobile, u.avatar,
                (SELECT COUNT(*) FROM support_messages sm WHERE sm.ticket_id = st.id AND sm.sender_role = \'admin\') AS admin_replies,
                (SELECT sm.sender_role FROM support_messages sm WHERE sm.ticket_id = st.id ORDER BY sm.id DESC LIMIT 1) AS last_role
            FROM support_tickets st INNER JOIN users u ON u.id = st.user_id WHERE st.id = ?' . ($uid !== null ? ' AND st.user_id = ?' : ''), $uid !== null ? [$id, $uid] : [$id]);
        return $t ?: null;
    }

    /**
     * وضعیت نمایشی تیکت: [state => [عنوان، رنگ], wait => [عنوان، رنگ]|null]
     * state: جدید / در حال انجام / تحت بررسی / بسته شده — wait: طرفی که باید پاسخ بدهد
     */
    public static function badges(array $t): array
    {
        $s = (string) $t['status'];
        if ($s === 'closed') {
            $state = ['بسته شده', 'red'];
        } elseif ($s === 'in_progress') {
            $state = ['در حال انجام', 'blue'];
        } elseif ((int) ($t['admin_replies'] ?? 0) === 0) {
            $state = ['جدید', 'yellow'];
        } else {
            $state = ['تحت بررسی', 'blue'];
        }
        $wait = null;
        if ($s !== 'closed') {
            $wait = ($t['last_role'] ?? 'user') === 'admin' ? ['منتظر پاسخ کاربر', 'user'] : ['منتظر پاسخ کارشناس', 'agent'];
        }
        return ['state' => $state, 'wait' => $wait];
    }

    public static function messages(int $ticketId): array
    {
        return DB::all('SELECT sm.*, u.first_name, u.last_name, u.mobile, u.avatar FROM support_messages sm INNER JOIN users u ON u.id = sm.sender_id WHERE sm.ticket_id = ? ORDER BY sm.created_at ASC, sm.id ASC', [$ticketId]);
    }

    /**
     * ثبت پیام (و پیوست) در تیکت و به‌روزرسانی وضعیت.
     * @return array{0:bool,1:string}
     */
    public static function addMessage(array $ticket, int $senderId, string $role, string $message, ?array $file): array
    {
        $att = [];
        if ($file !== null) {
            $r = Uploader::saveAttachment($file, 'support_files', 'sup' . (int) $ticket['id']);
            if (!$r['ok']) {
                return [false, (string) $r['error']];
            }
            $att = ['attachment' => $r['name'], 'attachment_name' => $r['original'], 'attachment_size' => $r['size']];
        }
        if ($message === '' && !$att) {
            return [false, 'متن پیام را بنویسید یا فایلی پیوست کنید.'];
        }
        $admin = $role === 'admin';
        DB::insert('support_messages', ['ticket_id' => (int) $ticket['id'], 'sender_id' => $senderId, 'sender_role' => $admin ? 'admin' : 'user', 'message' => $message,
            'is_read_by_user' => $admin ? 0 : 1, 'is_read_by_admin' => $admin ? 1 : 0] + $att);
        // پاسخ ادمین ⇒ «پاسخ داده شد» (مگر تیکت را «در حال انجام» نگه داشته باشد)؛ پیام کاربر ⇒ دوباره باز
        $status = $admin ? ($ticket['status'] === 'in_progress' ? 'in_progress' : 'answered') : ($ticket['status'] === 'in_progress' ? 'in_progress' : 'open');
        DB::run('UPDATE support_tickets SET status = ?, closed_at = NULL, last_reply_at = NOW() WHERE id = ?', [$status, (int) $ticket['id']]);
        if ($admin) {
            $body = $message !== '' ? mb_substr($message, 0, 160) : '📎 ' . ($att['attachment_name'] ?? 'فایل');
            Push::toUser((int) $ticket['user_id'], ['title' => 'پاسخ پشتیبانی – تیکت #' . fa_num($ticket['id']), 'body' => $body, 'url' => 'support/' . (int) $ticket['id'], 'tag' => 'support' . (int) $ticket['id'], 'kind' => 'support']);
        }
        return [true, $admin ? 'پاسخ ارسال شد.' : 'پیام ارسال شد.'];
    }

    /** حذف تیکت همراه با فایل‌های پیوستش. */
    public static function delete(int $id): void
    {
        foreach (DB::column('SELECT attachment FROM support_messages WHERE ticket_id = ? AND attachment IS NOT NULL', [$id]) as $f) {
            Uploader::delete('support_files', (string) $f);
        }
        DB::run('DELETE FROM support_tickets WHERE id = ?', [$id]);
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
