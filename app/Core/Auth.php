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
        // اتصال نشست به دستگاه ورود: مرورگر + شبکه (دو بخش اول IP). استفاده از همین نشست روی دستگاه دیگر = نابودی کامل نشست
        $req = Request::capture();
        $ua = self::uaHash($req);
        $bound = Session::get('ua');
        if (($bound !== null && !hash_equals((string) $bound, $ua)) || !self::sameNetwork($req)) {
            Audit::log($id, 'session_device_mismatch', ['ip' => $req->ip(), 'login_ip' => DbSessionHandler::binding()['ip'] ?? Session::get('ipf')]);
            Session::destroy();                               // ردیف/فایل نشست حذف می‌شود؛ دستگاه اصلی هم باید دوباره وارد شود
            Session::flash('این نشست روی دستگاه یا شبکه‌ی دیگری استفاده شد و برای امنیت باطل شد. دوباره وارد شوید.', 'error');
            self::$user = null;
            return null;
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

    /**
     * درخواست از همان شبکه‌ی ورود است؟ SESSION_IP_BIND: prefix (پیش‌فرض، دو بخش اول IP)، full (IP کامل) یا off.
     * مرجع: ستون‌های ip/ip_prefix جدول user_sessions؛ در حالت نشست فایلی، مقدارهای ذخیره‌شده در خود نشست.
     */
    private static function sameNetwork(Request $req): bool
    {
        $mode = strtolower((string) env('SESSION_IP_BIND', 'prefix'));
        if ($mode === 'off') {
            return true;
        }
        $b = DbSessionHandler::binding();
        $ip = $b['ip'] ?? Session::get('ipf');
        $prefix = $b['ip_prefix'] ?? Session::get('ipp');
        if ($ip === null || $prefix === null) {
            return false;                                     // نشست بدون اتصال دستگاه (قدیمی) پذیرفته نمی‌شود
        }
        return $mode === 'full' ? hash_equals((string) $ip, $req->ip()) : hash_equals((string) $prefix, $req->ipPrefix());
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
        // اول ثبت، بعد شمارش (Race): ده‌ها درخواست هم‌زمان هم فقط به اندازه‌ی سقف حدس می‌زنند. ورود موفق ثبت‌ها را برمی‌دارد.
        $trusted = self::trustedDevice($mobile);
        Throttle::hit('login_user', $keyUser);
        Throttle::hit('login_ip', $ip);
        if (!$trusted) {
            Throttle::hit('login_acct', $mobile);
        }
        // قفل حساب: بعد از ۲۰ تلاش ناموفق (از هر دستگاه/IP) ورود به حساب ۳۰ دقیقه بسته است؛ دستگاه مورد اعتماد صاحب حساب مستثناست
        if (!$trusted && Throttle::count('login_lock', $mobile, self::LOCK_SECONDS) > 0) {
            Audit::log(null, 'login_locked', ['mobile' => $mobile]);
            return ['ok' => false, 'message' => 'به‌دلیل تلاش‌های ناموفق زیاد، ورود به این حساب ۳۰ دقیقه قفل شده است.'];
        }
        if (!$trusted && Throttle::count('login_acct', $mobile, $win) > $max * 4) {
            Throttle::hit('login_lock', $mobile);
            Throttle::clear('login_acct', $mobile);
            Audit::log(null, 'login_locked', ['mobile' => $mobile, 'start' => true]);
            return ['ok' => false, 'message' => 'به‌دلیل تلاش‌های ناموفق زیاد، ورود به این حساب ۳۰ دقیقه قفل شده است.'];
        }
        if (Throttle::count('login_user', $keyUser, $win) > $max || Throttle::count('login_ip', $ip, $win) > $max * 5) {
            Audit::log(null, 'login_throttled', ['mobile' => $mobile]);
            return ['ok' => false, 'message' => 'تعداد تلاش‌های ناموفق زیاد است. چند دقیقه بعد دوباره تلاش کنید.'];
        }

        $u = DB::one('SELECT * FROM users WHERE mobile = ? AND is_bot = 0 LIMIT 1', [$mobile]);
        static $dummy = null;
        $dummy ??= password_hash('dummy-password-for-timing', self::algo());
        $valid = password_verify($password, $u['password_hash'] ?? $dummy) && $u !== null;

        if (!$valid) {
            Audit::log($u ? (int) $u['id'] : null, 'login_failed', ['mobile' => $mobile]);
            return ['ok' => false, 'message' => 'شماره موبایل یا رمز عبور اشتباه است.'];
        }

        Throttle::clear('login_user', $keyUser);
        Throttle::clear('login_acct', $mobile);
        Throttle::clear('login_lock', $mobile);                // ورود موفق صاحب حساب (از دستگاه مورد اعتماد) قفل را برمی‌دارد
        Throttle::undo('login_ip', $ip);                       // ورود موفق از سهمیه‌ی IP (مثلاً دفتر با NAT مشترک) کم نکند
        if (password_needs_rehash((string) $u['password_hash'], self::algo())) {
            $u['password_hash'] = self::hash($password);      // مهم: هش جدید باید در همان $u باشد وگرنه نسخه‌ی رمز در سشن ناهمگام می‌شود و کاربر بلافاصله خارج می‌شود
            DB::update('users', ['password_hash' => $u['password_hash']], 'id = ?', [(int) $u['id']]);
        }
        self::login($u, $req, $remember);
        self::trustDevice($mobile, $u);
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

    public const TRUST_DAYS = 90;
    /** مدت قفل حساب بعد از تلاش‌های ناموفق زیاد */
    public const LOCK_SECONDS = 1800;

    /**
     * امضای «دستگاه مورد اعتماد»: موبایل + نسخه‌ی رمز (pv) + تاریخ انقضا.
     * با تغییر رمز همه‌ی این کوکی‌ها باطل می‌شوند و بدون دسترسی به هش رمز در دیتابیس، حتی با کلید امضا هم جعل نمی‌شوند.
     */
    private static function deviceSig(string $mobile, string $pv, int $exp): string
    {
        return hash_hmac('sha256', 'trusted-device|' . $mobile . '|' . $pv . '|' . $exp, self::deviceKey());
    }

    /** @return array<int,array{0:int,1:string}> ورودی‌های کوکی [انقضا، امضا] (حداکثر ۵ حساب در یک دستگاه) */
    private static function deviceEntries(): array
    {
        $out = [];
        foreach (explode('~', (string) ($_COOKIE[self::TRUST_COOKIE] ?? '')) as $x) {
            if (preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $x, $m) && (int) $m[1] > time()) {
                $out[] = [(int) $m[1], $m[2]];
            }
        }
        return array_slice($out, 0, 5);
    }

    private static function trustedDevice(string $mobile): bool
    {
        $entries = self::deviceEntries();
        if (!$entries) {
            return false;
        }
        $hash = DB::value('SELECT password_hash FROM users WHERE mobile = ? AND is_bot = 0 LIMIT 1', [$mobile]);
        if (!is_string($hash)) {
            return false;
        }
        $pv = self::pv(['password_hash' => $hash]);
        foreach ($entries as [$exp, $sig]) {
            if (hash_equals(self::deviceSig($mobile, $pv, $exp), $sig)) {
                return true;
            }
        }
        return false;
    }

    private static function trustDevice(string $mobile, array $u): void
    {
        if (headers_sent()) {
            return;
        }
        $exp = time() + self::TRUST_DAYS * 86400;
        $list = [$exp . '.' . self::deviceSig($mobile, self::pv($u), $exp)];
        foreach (self::deviceEntries() as [$e, $s]) {
            $list[] = $e . '.' . $s;
        }
        setcookie(self::TRUST_COOKIE, implode('~', array_slice(array_values(array_unique($list)), 0, 5)), [
            'expires' => $exp, 'path' => Session::cookiePath(), 'domain' => '',
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
            if (Session::usesDb() && session_id() !== '') {
                DbSessionHandler::destroyOthers($userId, session_id());   // نشست‌های دستگاه‌های دیگر همان لحظه حذف می‌شوند
            }
        }
    }

    public static function login(array $u, Request $req, bool $remember = false): void
    {
        Session::regenerate();
        Session::put('uid', (int) $u['id']);
        Session::remember($remember);
        Session::put('pv', self::pv($u));
        Session::put('ua', self::uaHash($req));
        // دستگاه ورود: IP کامل و دو بخش اول آن در جدول user_sessions (و در خود نشست برای حالت فایلی)
        Session::put('ipf', $req->ip());
        Session::put('ipp', $req->ipPrefix());
        DbSessionHandler::bind($req->ip(), $req->ipPrefix(), $req->userAgent());
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
