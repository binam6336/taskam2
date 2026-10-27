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

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (env('TRUSTED_PROXY', false) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    private static function cookiePath(): string
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
        if (!is_dir($savePath)) {
            @mkdir($savePath, 0700, true);
        }
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
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
        self::enforceLifetime();
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

    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
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
        $_SESSION['_flash'][] = ['message' => $message, 'type' => $type];
    }

    public static function pullFlash(): array
    {
        return (array) self::pull('_flash', []);
    }
}
