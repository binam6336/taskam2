<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\ErrorHandler;

/**
 * اعلان‌های Web Push نسخه‌ی PWA (حتی وقتی برنامه بسته است، مثل اپ‌های اندروید).
 *  - پیام‌ها در صف می‌روند و بعد از ارسال پاسخ به کاربر فرستاده می‌شوند تا درخواست کند نشود.
 *  - اشتراک‌های منقضی (۴۰۴/۴۱۰) خودکار حذف می‌شوند.
 *  - اگر migration 007 اجرا نشده باشد، بی‌صدا هیچ کاری نمی‌کند.
 *  - آدرس‌ها نسبی‌اند (بدون / ابتدایی) و service worker آن‌ها را نسبت به scope برنامه کامل می‌کند؛ پس از کران‌جاب هم درست‌اند.
 */
final class Push
{
    private static array $queue = [];
    private static bool $hooked = false;

    public static function enabled(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $ok = !array_filter(self::diagnose(), static fn($r) => !$r[1]);
        }
        return $ok;
    }

    /** همه‌ی پیش‌نیازها (سرور + دیتابیس) با وضعیت هر کدام: [عنوان، برقرار است؟، راه‌حل] */
    public static function diagnose(): array
    {
        static $d = null;
        if ($d !== null) {
            return $d;
        }
        $d = WebPush::requirements();
        try {
            $table = DB::value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'push_subscriptions'");
            $hasTable = (int) $table > 0;
        } catch (\Throwable $e) {
            try {
                DB::value('SELECT 1 FROM push_subscriptions LIMIT 1');
                $hasTable = true;
            } catch (\Throwable $e2) {
                $hasTable = false;
            }
        }
        if (!$hasTable) {
            // جدول فقط اضافه می‌شود و به داده‌های دیگر کاری ندارد؛ اگر کاربر دیتابیس اجازه‌ی CREATE دارد خودکار ساخته می‌شود
            try {
                $sql = (string) file_get_contents(BASE_PATH . '/database/migrations/007_push_subscriptions.sql');
                $sql = trim((string) preg_replace('/^\s*--.*$/m', '', $sql));
                if ($sql !== '') {
                    DB::run($sql);
                    $hasTable = true;
                }
            } catch (\Throwable $e) {
                ErrorHandler::log($e);
            }
        }
        $d['table'] = ['جدول push_subscriptions در دیتابیس', $hasTable, 'فایل database/migrations/007_push_subscriptions.sql را روی همین دیتابیس اجرا کنید (phpMyAdmin ← SQL).'];
        $dir = STORAGE_PATH;
        $keyOk = is_file($dir . '/vapid.json') || ((string) env('VAPID_PUBLIC_KEY', '') !== '' && (string) env('VAPID_PRIVATE_KEY', '') !== '') || is_writable($dir);
        $d['keys'] = ['ذخیره‌ی کلید VAPID در پوشه‌ی storage', $keyOk, 'به پوشه‌ی storage دسترسی نوشتن (755 یا 775) بدهید، یا VAPID_PUBLIC_KEY و VAPID_PRIVATE_KEY را در .env بگذارید.'];
        return $d;
    }

    /** متن کوتاه علت غیرفعال بودن (برای پیام خطا) */
    public static function reason(): string
    {
        $bad = array_filter(self::diagnose(), static fn($r) => !$r[1]);
        return $bad ? 'اعلان‌های PWA روی سرور فعال نیست: ' . implode('، ', array_map(static fn($r) => $r[0] . ' — ' . $r[2], $bad)) : '';
    }

    // ───── اشتراک‌ها ─────

    public static function subscribe(int $uid, string $endpoint, string $p256dh, string $auth, string $ua): void
    {
        DB::run('INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent, fail_count) VALUES (?, ?, ?, ?, ?, ?, 0)
                 ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent), fail_count = 0',
            [$uid, $endpoint, hash('sha256', $endpoint), $p256dh, $auth, mb_substr($ua, 0, 250)]);
    }

    public static function unsubscribe(int $uid, string $endpoint): void
    {
        DB::run('DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?', [hash('sha256', $endpoint), $uid]);
    }

    public static function count(int $uid): int
    {
        return self::enabled() ? (int) DB::value('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?', [$uid]) : 0;
    }

    // ───── ارسال ─────

    /**
     * اعلان برای همه‌ی دستگاه‌های کاربر.
     * @param array{title:string,body?:string,url?:string,tag?:string,kind?:string} $n
     */
    public static function toUser(int $uid, array $n, bool $now = false): void
    {
        if ($uid <= 0 || !self::enabled()) {
            return;
        }
        self::$queue[] = [$uid, $n];
        if ($now) {
            self::flush();
            return;
        }
        if (!self::$hooked) {
            self::$hooked = true;
            register_shutdown_function([self::class, 'flush']);
        }
    }

    /** ارسال صف؛ ابتدا پاسخ HTTP بسته می‌شود تا کاربر منتظر سرویس push نماند. */
    public static function flush(): void
    {
        if (!self::$queue) {
            return;
        }
        if (PHP_SAPI !== 'cli' && function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }
        $q = self::$queue;
        self::$queue = [];
        foreach ($q as [$uid, $n]) {
            try {
                self::deliver($uid, $n);
            } catch (\Throwable $e) {
                ErrorHandler::log($e);
            }
        }
    }

    /** @return int تعداد دستگاه‌هایی که پیام را پذیرفتند */
    private static function deliver(int $uid, array $n): int
    {
        $subs = DB::all('SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?', [$uid]);
        if (!$subs) {
            return 0;
        }
        $unread = (int) DB::value('SELECT COUNT(*) FROM chat_messages WHERE receiver_id = ? AND is_read = 0 AND sender_id <> receiver_id', [$uid]);
        $payload = [
            'title' => mb_substr((string) $n['title'], 0, 120),
            'body' => mb_substr(trim((string) ($n['body'] ?? '')), 0, 300),
            'url' => (string) ($n['url'] ?? 'dashboard'),
            'tag' => (string) ($n['tag'] ?? ''),
            'kind' => (string) ($n['kind'] ?? 'info'),
            'icon' => 'assets/img/icon-192.png',
            'badge' => 'assets/img/badge-96.png',
            'unread' => $unread,
            'ts' => time() * 1000,
        ];
        $ok = 0;
        foreach ($subs as $s) {
            $code = WebPush::send($s, $payload, 86400, 'high', $payload['tag'] ?: null);
            if ($code >= 200 && $code < 300) {
                $ok++;
                DB::run('UPDATE push_subscriptions SET last_ok_at = NOW(), fail_count = 0 WHERE id = ?', [(int) $s['id']]);
            } elseif ($code === 404 || $code === 410) {
                DB::run('DELETE FROM push_subscriptions WHERE id = ?', [(int) $s['id']]);   // اشتراک منقضی/لغوشده
            } else {
                DB::run('UPDATE push_subscriptions SET fail_count = LEAST(fail_count + 1, 250) WHERE id = ?', [(int) $s['id']]);
                DB::run('DELETE FROM push_subscriptions WHERE id = ? AND fail_count >= 20', [(int) $s['id']]);
            }
        }
        return $ok;
    }

    /** ارسال فوری (برای «اعلان آزمایشی»)؛ خروجی = تعداد دستگاه‌های موفق */
    public static function test(int $uid): int
    {
        if (!self::enabled()) {
            return 0;
        }
        return self::deliver($uid, ['title' => 'تسکام ✅', 'body' => 'اعلان‌های این دستگاه فعال است؛ پیام‌ها و یادآوری‌ها حتی وقتی برنامه بسته است می‌رسند.', 'url' => 'dashboard', 'tag' => 'test', 'kind' => 'test']);
    }

    // ───── رویدادها ─────

    /** پیام چت (کاربر یا ربات اطلاع‌رسانی) → اعلان برای گیرنده */
    public static function chatMessage(int $msgId, int $sender, int $receiver, string $text, ?string $fileName, bool $senderIsBot): void
    {
        if ($sender === $receiver || !self::enabled()) {
            return;
        }
        $link = null;
        if (preg_match('/\[\[(task|call):([a-f0-9]{16,64})\]\]/i', $text, $m)) {
            $link = $m[1] === 'task' ? 'tasks?task=' . $m[2] : 'calls?call=' . $m[2];
        }
        $clean = trim((string) preg_replace('/\[\[(?:task|call):[a-f0-9]+\]\]/i', '', $text));
        if ($senderIsBot) {
            // اعلان سیستمی: خط اول عنوان، بقیه متن
            $lines = array_values(array_filter(array_map('trim', explode("\n", $clean)), static fn($l) => $l !== ''));
            $title = $lines[0] ?? 'تسکام';
            $body = implode(' • ', array_slice($lines, 1, 4));
            self::toUser($receiver, ['title' => $title, 'body' => $body, 'url' => $link ?? 'chat?user=' . $sender, 'tag' => 'n' . $msgId, 'kind' => 'system']);
            return;
        }
        $u = DB::one('SELECT first_name, last_name, mobile FROM users WHERE id = ?', [$sender]);
        if ($clean === '' && $fileName) {
            $clean = '📎 ' . $fileName;
        }
        self::toUser($receiver, ['title' => 'پیام جدید – ' . ($u ? full_name($u) : 'کاربر'), 'body' => $clean, 'url' => 'chat?user=' . $sender, 'tag' => 'msg' . $sender, 'kind' => 'chat']);
    }

    /**
     * یادآوری تسک‌هایی که مهلتشان رسیده، برای کاربرانی که اعلان PWA دارند.
     * با هر درخواست به سایت حداکثر دقیقه‌ای یک‌بار اجرا می‌شود (و با bin/push-cron.php از کران‌جاب).
     */
    public static function sweepDue(bool $force = false): int
    {
        $lock = STORAGE_PATH . '/cache/push_due.lock';
        if (!$force && is_file($lock) && time() - (int) filemtime($lock) < 60) {
            return 0;
        }
        @touch($lock);
        if (!self::enabled()) {
            return 0;
        }
        $rows = DB::all("SELECT t.id, t.title, t.description, t.assignee_id, t.share_token, t.due_time, p.title AS project_title
              FROM tasks t LEFT JOIN projects p ON p.id = t.project_id
             WHERE t.is_completed = 0 AND t.notified_at IS NULL AND t.due_date IS NOT NULL AND t.due_time IS NOT NULL
               AND TIMESTAMP(t.due_date, t.due_time) <= NOW() AND TIMESTAMP(t.due_date, t.due_time) >= (NOW() - INTERVAL 120 MINUTE)
               AND t.assignee_id IN (SELECT user_id FROM push_subscriptions)
             LIMIT 200");
        foreach ($rows as $t) {
            DB::run('UPDATE tasks SET notified_at = NOW() WHERE id = ? AND notified_at IS NULL', [(int) $t['id']]);
            $tok = $t['share_token'] ?: \App\Models\TaskModel::ensureShareToken((int) $t['id']);
            $body = trim(($t['project_title'] ? 'پروژه: ' . $t['project_title'] . ' • ' : '') . 'مهلت: ' . fa_num(substr((string) $t['due_time'], 0, 5)) . ($t['description'] ? ' • ' . mb_substr(trim((string) $t['description']), 0, 120) : ''));
            self::toUser((int) $t['assignee_id'], ['title' => '⏰ یادآوری وظیفه – ' . $t['title'], 'body' => $body, 'url' => 'tasks?task=' . $tok, 'tag' => 'task' . $t['id'], 'kind' => 'due']);
        }
        return count($rows);
    }
}
