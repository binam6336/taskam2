<?php
declare(strict_types=1);

namespace App\Core;

/**
 * احراز هویت سشن‌محور.
 * - فقط user_id در سشن نگهداری می‌شود؛ وضعیت/نقش در هر درخواست از دیتابیس خوانده می‌شود
 *   (غیرفعال‌سازی یا تغییر نقش بلافاصله اعمال می‌شود).
 * - بعد از ورود، شناسه‌ی سشن بازسازی می‌شود (session fixation).
 * - محدودسازی تلاش ورود، مقایسه‌ی زمان‌ثابت و ارتقای خودکار هش.
 */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public const STATUSES = ['pending', 'active', 'deactivated', 'blocked'];

    public static function hash(
        #[\SensitiveParameter]
        string $password
    ): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    private static function algo()
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    /** کاربر مؤثر (در حالت impersonate، کاربر هدف). */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        $id = (int) Session::get('uid', 0);
        if ($id <= 0) {
            return self::$user = null;
        }
        // اتصال سشن به مرورگر (کاهش ریسک سرقت سشن)
        $ua = self::uaHash(Request::capture());
        $bound = Session::get('ua');
        if ($bound !== null && !hash_equals((string) $bound, $ua)) {
            self::logout();
            return self::$user = null;
        }
        $u = DB::one('SELECT * FROM users WHERE id = ? AND is_bot = 0 LIMIT 1', [$id]);
        if ($u === null) {
            self::logout();
            return self::$user = null;
        }
        // ورود به‌جای کاربر: مدیر باید هنوز مدیرِ فعال باشد و رمزش از شروع این حالت عوض نشده باشد
        if (self::isImpersonating()) {
            $adm = DB::one('SELECT id, role, status, password_hash FROM users WHERE id = ? AND is_bot = 0 LIMIT 1', [(int) Session::get('imp_admin', 0)]);
            $impPv = Session::get('imp_pv');
            if ($adm === null || $adm['role'] !== 'admin' || $adm['status'] !== 'active' || !is_string($impPv) || !hash_equals($impPv, self::pv($adm))) {
                self::logout();
                return self::$user = null;
            }
        }
        // با تغییر رمز عبور، سایر نشست‌های همان کاربر نامعتبر می‌شوند
        if (!self::isImpersonating()) {
            $pv = self::pv($u);
            $sess = Session::get('pv');
            if ($sess !== null && !hash_equals((string) $sess, $pv)) {
                self::logout();
                return self::$user = null;
            }
        }
        return self::$user = $u;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int) $u['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(?array $u = null): bool
    {
        $u ??= self::user();
        return $u !== null && $u['role'] === 'admin';
    }

    public static function isActive(?array $u = null): bool
    {
        $u ??= self::user();
        return $u !== null && $u['status'] === 'active';
    }

    public static function isImpersonating(): bool
    {
        return (int) Session::get('imp_admin', 0) > 0;
    }

    public static function impersonator(): ?array
    {
        $id = (int) Session::get('imp_admin', 0);
        return $id > 0 ? DB::one('SELECT id, first_name, last_name, mobile FROM users WHERE id = ? AND role = \'admin\'', [$id]) : null;
    }

    /** دسترسی‌های مدیران (مدیر کل همه را دارد). impersonate و edit_content از v3.25 جدا شدند. */
    public const ADMIN_PERMS = ['add_user', 'delete_user', 'toggle_status', 'edit_user', 'impersonate', 'edit_content'];

    /** مدیر کل = اولین کاربر admin. */
    public static function superAdminId(): int
    {
        static $id = null;
        return $id ??= (int) DB::value("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    }

    public static function adminPermissions(array $u): array
    {
        if ($u['role'] !== 'admin') {
            return [];
        }
        if ((int) $u['id'] === self::superAdminId()) {
            return self::ADMIN_PERMS;
        }
        $p = json_decode((string) ($u['admin_permissions'] ?? '[]'), true);
        return is_array($p) ? array_values(array_intersect($p, self::ADMIN_PERMS)) : [];
    }

    public static function adminCan(string $perm): bool
    {
        $u = self::user();
        return $u !== null && in_array($perm, self::adminPermissions($u), true);
    }

    /**
     * تلاش برای ورود.
     * @return array{ok:bool,message:string,user?:array}
     */
    public static function attempt(
        string $mobile,
        #[\SensitiveParameter]
        string $password,
        Request $req,
        bool $remember = false
    ): array
    {
        $ip = $req->ip();
        $max = max(3, (int) env('LOGIN_MAX_ATTEMPTS', 5));
        $win = max(1, (int) env('LOGIN_WINDOW_MINUTES', 15)) * 60;
        $keyUser = $mobile . '|' . $ip;

        // سه سقف: موبایل+IP، کل IP، و خود حساب از همه‌ی IPها (حمله‌ی توزیع‌شده روی یک حساب).
        // سقف حساب روی دستگاهی که قبلاً با همین حساب موفق وارد شده اعمال نمی‌شود تا مهاجم نتواند حساب دیگران را قفل کند.
        $trusted = self::trustedDevice($mobile);
        if (Throttle::count('login_user', $keyUser, $win) >= $max || Throttle::count('login_ip', $ip, $win) >= $max * 5
            || (!$trusted && Throttle::count('login_acct', $mobile, $win) >= $max * 4)) {
            Audit::log(null, 'login_throttled', ['mobile' => $mobile]);
            return ['ok' => false, 'message' => 'تعداد تلاش‌های ناموفق زیاد است. چند دقیقه بعد دوباره تلاش کنید.'];
        }

        $u = DB::one('SELECT * FROM users WHERE mobile = ? AND is_bot = 0 LIMIT 1', [$mobile]);
        static $dummy = null;
        $dummy ??= password_hash('dummy-password-for-timing', self::algo());
        $valid = password_verify($password, $u['password_hash'] ?? $dummy) && $u !== null;

        if (!$valid) {
            Throttle::hit('login_user', $keyUser);
            Throttle::hit('login_ip', $ip);
            Throttle::hit('login_acct', $mobile);
            Audit::log($u ? (int) $u['id'] : null, 'login_failed', ['mobile' => $mobile]);
            return ['ok' => false, 'message' => 'شماره موبایل یا رمز عبور اشتباه است.'];
        }

        Throttle::clear('login_user', $keyUser);
        Throttle::clear('login_acct', $mobile);
        if (password_needs_rehash((string) $u['password_hash'], self::algo())) {
            $u['password_hash'] = self::hash($password);      // مهم: هش جدید باید در همان $u باشد وگرنه نسخه‌ی رمز در سشن ناهمگام می‌شود و کاربر بلافاصله خارج می‌شود
            DB::update('users', ['password_hash' => $u['password_hash']], 'id = ?', [(int) $u['id']]);
        }
        self::login($u, $req, $remember);
        self::trustDevice($mobile);
        return ['ok' => true, 'message' => '', 'user' => $u];
    }

    // ───── دستگاه مورد اعتماد (کوکی امضاشده بعد از ورود موفق) ─────

    private const TRUST_COOKIE = 'tk_td';

    private static function deviceKey(): string
    {
        $k = (string) env('APP_KEY', '');
        if (strlen($k) >= 16) {
            return $k;
        }
        $file = BASE_PATH . '/storage/cache/device.key';
        $k = is_file($file) ? (string) file_get_contents($file) : '';
        if (strlen($k) < 32) {
            $k = bin2hex(random_bytes(32));
            @file_put_contents($file, $k, LOCK_EX);
            @chmod($file, 0600);
        }
        return $k;
    }

    private static function deviceSig(string $mobile): string
    {
        return hash_hmac('sha256', 'trusted-device|' . $mobile, self::deviceKey());
    }

    /** @return string[] امضاهای ذخیره‌شده در کوکی (حداکثر ۵ حساب در یک دستگاه) */
    private static function deviceSigs(): array
    {
        $raw = (string) ($_COOKIE[self::TRUST_COOKIE] ?? '');
        return array_slice(array_filter(explode('.', $raw), static fn($x) => preg_match('/^[a-f0-9]{64}$/', $x) === 1), 0, 5);
    }

    private static function trustedDevice(string $mobile): bool
    {
        $sig = self::deviceSig($mobile);
        foreach (self::deviceSigs() as $s) {
            if (hash_equals($sig, $s)) {
                return true;
            }
        }
        return false;
    }

    private static function trustDevice(string $mobile): void
    {
        if (headers_sent()) {
            return;
        }
        $sigs = array_values(array_unique(array_merge([self::deviceSig($mobile)], self::deviceSigs())));
        setcookie(self::TRUST_COOKIE, implode('.', array_slice($sigs, 0, 5)), [
            'expires' => time() + 180 * 86400, 'path' => Session::cookiePath(), 'domain' => '',
            'secure' => Session::isHttps(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }

    private static function uaHash(Request $req): string
    {
        return hash('sha256', (string) preg_replace('/[0-9._]+/', '#', $req->userAgent()));
    }

    private static function pv(array $u): string
    {
        return substr(hash('sha256', (string) $u['password_hash']), 0, 24);
    }

    /** بعد از تغییر رمز: نشست فعلی معتبر می‌ماند و بقیه باطل می‌شوند. */
    public static function refreshPasswordVersion(int $userId): void
    {
        $u = DB::one('SELECT password_hash FROM users WHERE id = ?', [$userId]);
        if ($u) {
            Session::put('pv', self::pv($u));
        }
    }

    public static function login(array $u, Request $req, bool $remember = false): void
    {
        Session::regenerate();
        Session::put('uid', (int) $u['id']);
        Session::remember($remember);
        Session::put('pv', self::pv($u));
        Session::put('ua', self::uaHash($req));
        Session::forget('imp_admin');
        Session::forget('imp_pv');
        Session::forget('_csrf');
        DB::update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => $req->ip()], 'id = ?', [(int) $u['id']]);
        Audit::log((int) $u['id'], 'login');
        self::$loaded = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        $id = (int) Session::get('uid', 0);
        if ($id > 0) {
            Audit::log($id, 'logout');
        }
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
    }

    /** ثبت‌نام کاربر جدید (وضعیت pending). */
    public static function register(array $d): int
    {
        return DB::insert('users', [
            'mobile'        => $d['mobile'],
            'first_name'    => $d['first_name'],
            'last_name'     => $d['last_name'],
            'email'         => $d['email'] !== '' ? $d['email'] : null,
            'password_hash' => self::hash($d['password']),
            'status'        => $d['status'] ?? 'pending',
            'role'          => $d['role'] ?? 'user',
            'admin_permissions' => $d['admin_permissions'] ?? null,
        ]);
    }

    public static function startImpersonation(int $adminId, int $targetId): void
    {
        $adm = DB::one('SELECT password_hash FROM users WHERE id = ?', [$adminId]);
        Session::regenerate();
        Session::put('imp_admin', $adminId);
        Session::put('imp_pv', $adm ? self::pv($adm) : '');
        Session::put('uid', $targetId);
        self::$loaded = false;
        self::$user = null;
        Audit::log($adminId, 'impersonate_start', ['target' => $targetId]);
    }

    public static function stopImpersonation(): void
    {
        $adminId = (int) Session::get('imp_admin', 0);
        if ($adminId > 0) {
            Audit::log($adminId, 'impersonate_stop', ['target' => (int) Session::get('uid', 0)]);
            Session::regenerate();
            Session::put('uid', $adminId);
            Session::forget('imp_admin');
            Session::forget('imp_pv');
            self::$loaded = false;
            self::$user = null;
        }
    }
}
