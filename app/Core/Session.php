<?php
declare(strict_types=1);

namespace App\Core;

/** مدیریت سشن با تنظیمات امن + flash. */
final class Session
{
    private static bool $started = false;

    /** نشست‌های «مرا به خاطر بسپار» (روز) و بدون آن (دقیقه‌ی عدم فعالیت). */
    public static function rememberSeconds(): int
    {
        return max(1, (int) env('SESSION_REMEMBER_DAYS', 30)) * 86400;
    }

    public static function idleSeconds(): int
    {
        return max(5, (int) env('SESSION_IDLE_MINUTES', 60)) * 60;
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (\App\Core\Request::fromTrustedProxy() && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    public static function cookiePath(): string
    {
        return base_url() === '' ? '/' : base_url() . '/';
    }

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        // فایل نشست باید حداقل به‌اندازه‌ی بلندترین نشست (۳۰ روز) روی دیسک بماند؛ انقضای واقعی در enforceLifetime اعمال می‌شود
        ini_set('session.gc_maxlifetime', (string) (self::rememberSeconds() + 86400));
        // بدون lazy_write: هر درخواست فایل نشست را لمس می‌کند تا GC سرور نشست فعال را قبل از موعد پاک نکند
        ini_set('session.lazy_write', '0');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        $savePath = STORAGE_PATH . '/cache/sessions';
        if (self::usesDb()) {
            session_set_save_handler(new DbSessionHandler(), true);
        } else {
            if (!is_dir($savePath)) {
                @mkdir($savePath, 0700, true);
            }
            if (is_dir($savePath) && is_writable($savePath)) {
                session_save_path($savePath);
            }
        }

        session_name('taskam_sid');
        session_set_cookie_params([
            'lifetime' => 0,                     // کوکی مهمان؛ برای کاربر واردشده در remember() تنظیم می‌شود
            'path'     => self::cookiePath(),
            'domain'   => '',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::$started = true;
        // شناسه‌ی قدیمیِ چرخانده‌شده: فقط چند دقیقه (برای درخواست‌های هم‌زمان) معتبر است
        if (isset($_SESSION['_destroyed']) && (int) $_SESSION['_destroyed'] < time() - 180) {
            $_SESSION = [];
        }
        self::enforceLifetime();
        self::rotate();
        if (random_int(1, 100) === 1) {
            try {
                self::usesDb() ? DbSessionHandler::sweep() : self::sweep($savePath);
            } catch (\Throwable $e) {
                ErrorHandler::log($e);
            }
        }
    }

    /**
     * محل ذخیره‌ی نشست: SESSION_DRIVER=db (پیش‌فرض، جدول user_sessions) یا file.
     * اگر جدول هنوز ساخته نشده (بدون مجوز CREATE)، فایل.
     */
    public static function usesDb(): bool
    {
        static $db = null;
        if ($db !== null) {
            return $db;
        }
        if (strtolower((string) env('SESSION_DRIVER', 'db')) !== 'db') {
            return $db = false;
        }
        $flag = STORAGE_PATH . '/cache/sessions-db';
        if (is_file($flag)) {
            return $db = true;
        }
        try {
            DB::value('SELECT 1 FROM user_sessions LIMIT 1');
            @file_put_contents($flag, date('c'));
            // فایل‌های نشست قدیمی دیگر استفاده نمی‌شوند؛ پاک می‌شوند تا روی دیسک (و در بکاپ/zip) نمانند
            foreach ((array) @glob(STORAGE_PATH . '/cache/sessions/sess_*', GLOB_NOSORT) as $f) {
                @unlink($f);
            }
            return $db = true;
        } catch (\Throwable $e) {
            return $db = false;
        }
    }

    /**
     * پاک‌سازی فایل‌های نشست (به GC سرور تکیه نمی‌کنیم؛ روی Debian/Ubuntu خاموش است):
     * نشست مهمان (بدون uid) بعد از ۲ ساعت، نشست واردشده بعد از مدت مجازش (_life) و هر فایلی بعد از سقف «مرا به خاطر بسپار».
     * حداکثر هر ۱۰ دقیقه یک بار و حداکثر ۵۰۰۰ فایل در هر اجرا.
     */
    private static function sweep(string $dir): void
    {
        $flag = $dir . '/.sweep';
        $now = time();
        if (is_file($flag) && $now - (int) @filemtime($flag) < 600) {
            return;
        }
        @touch($flag);
        $max = self::rememberSeconds() + 86400;
        $n = 0;
        foreach ((array) @glob($dir . '/sess_*', GLOB_NOSORT) as $f) {
            if (++$n > 5000) {
                break;
            }
            if ($f === $dir . '/sess_' . session_id()) {
                continue;
            }
            $age = $now - (int) @filemtime($f);
            if ($age < 7200) {
                continue;
            }
            $del = $age > $max;
            if (!$del) {
                $data = (string) @file_get_contents($f, false, null, 0, 4096);
                if (strpos($data, 'uid|') === false) {
                    $del = true;                                  // مهمان قدیمی (فقط توکن CSRF/فلش)
                } elseif (preg_match('/_life\|i:(\d+)/', $data, $m) && $age > (int) $m[1] + 3600) {
                    $del = true;                                  // نشست منقضی‌شده
                }
            }
            if ($del) {
                @unlink($f);
            }
        }
    }

    /**
     * چرخش دوره‌ای شناسه‌ی نشست واردشده (هر ۶ ساعت فعالیت) تا شناسه‌ی لورفته مدت طولانی (مثلاً ۳۰ روزِ «مرا به خاطر بسپار») کار نکند.
     * الگوی امن PHP: نشست قدیم علامت می‌خورد و فقط کوتاه‌مدت معتبر می‌ماند.
     */
    private static function rotate(): void
    {
        if (empty($_SESSION['uid']) || isset($_SESSION['_destroyed']) || self::isBackground() || headers_sent()) {
            return;
        }
        $now = time();
        $rot = (int) ($_SESSION['_rot'] ?? 0);
        if ($rot === 0) {
            $_SESSION['_rot'] = $now;
            return;
        }
        if ($now - $rot < 6 * 3600) {
            return;
        }
        $data = $_SESSION;
        $new = session_create_id();
        if (!is_string($new) || $new === '') {
            return;
        }
        $_SESSION['_destroyed'] = $now;
        session_commit();
        ini_set('session.use_strict_mode', '0');
        session_id($new);
        session_start();                                   // strict_mode در درخواست بعدی دوباره در start() روشن می‌شود
        $_SESSION = $data;
        $_SESSION['_rot'] = $now;
        $life = (int) ($_SESSION['_life'] ?? 0);
        if ($life > 0) {
            self::sendCookie($life);
        }
    }

    /** درخواست‌های پس‌زمینه (polling) نباید نشست غیرفعال را زنده نگه دارند. */
    private static function isBackground(): bool
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        foreach (['/live/poll', '/chat/api/heartbeat', '/pending/status'] as $p) {
            if (strpos($uri, $p) !== false) {
                return true;
            }
        }
        return false;
    }

    /** انقضای لغزنده: اگر مدت مجاز بدون فعالیت کاربر گذشته باشد نشست پاک می‌شود. */
    private static function enforceLifetime(): void
    {
        $life = (int) ($_SESSION['_life'] ?? 0);
        if ($life <= 0) {
            return;
        }
        $now = time();
        $act = (int) ($_SESSION['_act'] ?? $now);
        if ($now - $act > $life) {
            $_SESSION = [];
            $_SESSION['_flash'][] = ['message' => 'نشست شما به‌دلیل عدم فعالیت منقضی شد. دوباره وارد شوید.', 'type' => 'warning'];
            return;
        }
        if (!self::isBackground() && $now - $act >= 30) {
            $_SESSION['_act'] = $now;
            self::sendCookie($life);               // انقضای کوکی هم همراه فعالیت کاربر جلو می‌رود
        }
    }

    private static function sendCookie(int $life): void
    {
        if (headers_sent() || session_id() === '') {
            return;
        }
        setcookie(session_name(), session_id(), [
            'expires'  => time() + $life,
            'path'     => self::cookiePath(),
            'domain'   => '',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** بعد از ورود موفق: «مرا به خاطر بسپار» = ۳۰ روز؛ در غیر این صورت ۱ ساعت عدم فعالیت. */
    public static function remember(bool $remember): void
    {
        self::start();
        $life = $remember ? self::rememberSeconds() : self::idleSeconds();
        $_SESSION['_life'] = $life;
        $_SESSION['_act'] = time();
        $_SESSION['_remember'] = $remember ? 1 : 0;
        self::sendCookie($life);
    }

    /** شناسه‌ی نشست فقط هنگام ورود عوض می‌شود (چرخش دوره‌ای باعث خروج کاربر در درخواست‌های هم‌زمان می‌شد). */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['_rot'] = time();
        unset($_SESSION['_destroyed']);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'], 'secure' => (bool) $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
        self::$started = false;
    }

    /** مرورگر کوکی نشست فرستاده است؟ */
    public static function hasCookie(): bool
    {
        return isset($_COOKIE['taskam_sid']) && $_COOKIE['taskam_sid'] !== '';
    }

    public static function isActive(): bool
    {
        return self::$started && session_status() === PHP_SESSION_ACTIVE;
    }

    /** خواندن بدون کوکی نشست، نشست (و فایل) جدید نمی‌سازد */
    private static function lazy(): bool
    {
        if (self::$started || self::hasCookie()) {
            self::start();
            return true;
        }
        return false;
    }

    public static function get(string $key, $default = null)
    {
        if (!self::lazy()) {
            return $default;
        }
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        if (!self::lazy()) {
            return;
        }
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, $default = null)
    {
        $v = self::get($key, $default);
        self::forget($key);
        return $v;
    }

    /** پیام فلش: type = success | error | warning | info */
    public static function flash(string $message, string $type = 'success'): void
    {
        self::start();
        if ($type === 'error' || $type === 'warning') {
            $message = ErrorText::apply($message);
        }
        $_SESSION['_flash'][] = ['message' => $message, 'type' => $type];
    }

    public static function pullFlash(): array
    {
        return (array) self::pull('_flash', []);
    }
}
