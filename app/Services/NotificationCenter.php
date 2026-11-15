<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Pid;
use App\Models\ChatModel;
use App\Models\UserModel;

/**
 * مرکز اعلان‌های نوار بالا (v7.15، زنگ «اعلان‌ها»).
 * سیستم موازی ندارد: هر اعلان همان پیام «ربات کارلیکس» در chat_messages است که Notifier می‌فرستد
 * (واگذاری، منشن، یادداشت، بررسی سرپرست، برگشت برای اصلاح، تغییر حساب توسط مدیر، …).
 * خوانده‌شدن = همان is_read/read_at پیام (پس با خواندن در «گفتگو» هم هماهنگ است)؛ «حذف تاریخچه»ی گفتگوی ربات
 * اعلان‌های قبلی را از این فهرست هم پنهان می‌کند. درخواست‌های همکاری/عضویت پروژه شمارنده‌ی جدا دارند.
 */
final class NotificationCenter
{
    public const PER_PAGE = 15;

    /** شمار اعلان‌های خوانده‌نشده */
    public static function unread(int $uid): int
    {
        $bot = UserModel::botId();
        return (int) DB::value('SELECT COUNT(*) FROM chat_messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 0 AND id > ?', [$bot, $uid, ChatModel::clearedUpTo($uid, $bot)]);
    }

    /**
     * فهرست اعلان‌ها (جدیدترین اول) با صفحه‌بندی cursor (شناسه‌ی عمومی آخرین مورد دیده‌شده).
     * @return array{items:array,unread:int,has_more:bool,requests:array,bot:string}
     */
    public static function feed(int $uid, string $before = '', int $limit = self::PER_PAGE): array
    {
        $bot = UserModel::botId();
        $limit = max(1, min(30, $limit));
        $where = 'sender_id = ? AND receiver_id = ? AND id > ?';
        $args = [$bot, $uid, ChatModel::clearedUpTo($uid, $bot)];
        if ($before !== '') {
            $bid = Pid::id('chat_messages', $before);
            if ($bid !== null && $bid > 0) {
                $where .= ' AND id < ?';
                $args[] = $bid;
            }
        }
        $rows = DB::all("SELECT id, pid, sender_id, receiver_id, message, is_read, created_at FROM chat_messages WHERE $where ORDER BY id DESC LIMIT " . ($limit + 1), $args);
        $more = count($rows) > $limit;
        $items = array_map([self::class, 'item'], array_slice($rows, 0, $limit));
        // تسک مرتبط: اگر هنوز قابل مشاهده است، شناسه‌ی عمومی برای باز شدن در همان صفحه (وگرنه فقط لینک /tasks?task=… که خودش دسترسی را می‌سنجد)
        foreach ($items as &$it) {
            $it['task_id'] = null;
            if ($it['task'] !== null) {
                $t = DB::one('SELECT * FROM tasks WHERE share_token = ? LIMIT 1', [$it['task']]);
                if ($t && Access::canTask($t, $uid, 'view')) {
                    $it['task_id'] = pid('tasks', $t);
                }
                $it['url'] = url('/tasks?task=' . $it['task']);
            }
            unset($it['task']);
        }
        unset($it);
        return [
            'items'    => $items,
            'unread'   => self::unread($uid),
            'has_more' => $more,
            'requests' => [
                'colleagues' => (int) DB::value("SELECT COUNT(*) FROM colleague_requests WHERE target_id = ? AND status = 'pending'", [$uid]),
                'projects'   => (int) DB::value("SELECT COUNT(*) FROM project_join_requests WHERE user_id = ? AND status = 'pending'", [$uid]),
            ],
            'bot'      => pid('users', $bot),
        ];
    }

    /** علامت خوانده‌شده: یک اعلان ($id = شناسه‌ی عمومی) یا همه (null). @return int تعداد ردیف تغییرکرده */
    public static function markRead(int $uid, ?string $id): int
    {
        $bot = UserModel::botId();
        if ($id === null) {
            return ChatModel::markRead($uid, $bot);
        }
        $mid = Pid::id('chat_messages', $id);
        if ($mid === null || $mid <= 0) {
            return 0;
        }
        return DB::run('UPDATE chat_messages SET is_read = 1, read_at = NOW() WHERE id = ? AND sender_id = ? AND receiver_id = ? AND is_read = 0', [$mid, $bot, $uid]);
    }

    /** یک ردیف پیام ربات → اعلان: عنوان = خط اول، متن = بقیه (کوتاه‌شده)، پیوند تسک از [[task:…]] */
    private static function item(array $r): array
    {
        $plain = (string) ChatModel::open($r['message'], (int) $r['sender_id'], (int) $r['receiver_id']);
        $task = preg_match('/\[\[task:([a-f0-9]+)\]\]/i', $plain, $m) ? strtolower($m[1]) : null;
        $text = trim((string) preg_replace('/\[\[(?:task|call):[a-z0-9]+\]\]/i', '', $plain));
        $lines = preg_split('/\R/u', $text) ?: [];
        $title = trim((string) array_shift($lines));
        // ایموجی ابتدای پیام‌های ربات → آیکون (متن بدون ایموجی نمایش داده می‌شود)
        $title = trim((string) preg_replace('/^[\x{1F000}-\x{1FFFF}\x{2190}-\x{21FF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}\s]+/u', '', $title));
        $body = trim((string) preg_replace('/\s+/u', ' ', implode(' ', array_filter(array_map('trim', $lines), static fn($l) => $l !== ''))));
        if (mb_strlen($body, 'UTF-8') > 180) {
            $body = mb_substr($body, 0, 180, 'UTF-8') . '…';
        }
        return [
            'id'      => $r['pid'] ?: pid('chat_messages', (int) $r['id']),
            'kind'    => self::kind($title),   // فقط خط اول (متن پیام بررسی هم «برای اصلاح» دارد)
            'title'   => $title !== '' ? $title : 'اعلان',
            'body'    => $body,
            'task'    => $task,
            'is_read' => (int) $r['is_read'] === 1,
            'ts'      => (int) strtotime((string) $r['created_at']),
            'when'    => jdate((string) $r['created_at']),
        ];
    }

    /** نوع اعلان برای آیکون (بر اساس عنوان ثابت پیام‌های Notifier) */
    private static function kind(string $t): string
    {
        $map = ['منشن' => 'mention', 'برای اصلاح' => 'rework', 'بررسی سرپرست' => 'review', 'واگذار شد' => 'reassign',
                'وظیفه جدیدی' => 'assign', 'یادداشت جدید' => 'note', 'مدیر سیستم' => 'security'];
        foreach ($map as $needle => $kind) {
            if (mb_strpos($t, $needle, 0, 'UTF-8') !== false) {
                return $kind;
            }
        }
        return 'info';
    }
}
