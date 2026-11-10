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

    /** کاربر مؤثر: داخل پنجره‌ی ورود به پنل کاربر (/as/<توکن>، Impersonation) کاربر هدف، وگرنه صاحب نشست. */
    public static function user(): ?array
    {
        if (Impersonation::active()) {
            return self::realUser() !== null ? Impersonation::current()['target'] : null;
        }
        return self::realUser();
    }

    /** صاحب واقعی نشست (مدیر، حتی داخل پنجره‌ی ورود به پنل کاربر) با همه‌ی بررسی‌های اتصال نشست به دستگاه */
    public static function realUser(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        // نشست قدیمی (قبل از v5.1) که هنوز در حالت ورود به‌جای کاربر بود: به همان مدیر برمی‌گردد
        if ((int) Session::get('imp_admin', 0) > 0) {
            Session::put('uid', (int) Session::get('imp_admin'));
            Session::forget('imp_admin');
            Session::forget('imp_pv');
        }
        $id = (int) Session::get('uid', 0);
        if ($id <= 0) {
            return self::$user = null;
        }
        // اتصال نشست به دستگاه ورود: مرورگر + شبکه (دو بخش اول IP).
        // مرورگر دیگر = نابودی کامل نشست (نشانه‌ی سرقت کوکی). فقط عوض شدن شبکه (Wi-Fi ↔ دیتا) = نشست می‌ماند ولی
        // تا وارد کردن دوباره‌ی رمز (صفحه‌ی /reauth) کاربر واردشده حساب نمی‌شود.
        $req = Request::capture();
        $ua = self::uaHash($req);
        $bound = Session::get('ua');
        $uaOk = $bound !== null && hash_equals((string) $bound, $ua);
        if ($uaOk && !self::sameNetwork($req)) {
            if (Session::get('reauth') === null) {
                Audit::log($id, 'session_network_changed', ['ip' => $req->ip(), 'login_ip' => DbSessionHandler::binding()['ip'] ?? Session::get('ipf')]);
                Session::put('reauth', time());
            }
            self::$reauth = true;
            return self::$user = null;
        }
        if (!$uaOk || !self::sameNetwork($req)) {
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
        // با تغییر رمز عبور، سایر نشست‌های همان کاربر نامعتبر می‌شوند
        $pv = self::pv($u);
        $sess = Session::get('pv');
        if ($sess !== null && !hash_equals((string) $sess, $pv)) {
            self::logout();
            return self::$user = null;
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

    /** نشست معتبر است ولی شبکه عوض شده و باید رمز دوباره وارد شود (Auth::user() در این حالت null است) */
    private static bool $reauth = false;

    public static function needsReauth(): bool
    {
        self::user();
        return self::$reauth;
    }

    /** کاربرِ نشستی که منتظر تأیید دوباره‌ی رمز است (برای صفحه‌ی /reauth) */
    public static function reauthUser(): ?array
    {
        if (!self::needsReauth()) {
            return null;
        }
        return DB::one("SELECT * FROM users WHERE id = ? AND is_bot = 0 LIMIT 1", [(int) Session::get('uid', 0)]);
    }

    /**
     * تأیید دوباره‌ی رمز بعد از عوض شدن شبکه: موفق = اتصال نشست به شبکه‌ی جدید و چرخش شناسه‌ی نشست؛
     * بعد از REAUTH_MAX تلاش ناموفق نشست کامل باطل می‌شود.
     * @return array{0:bool,1:string}
     */
    public static function reauth(
        #[\SensitiveParameter]
        string $password,
        Request $req
    ): array {
        $u = self::reauthUser();
        if ($u === null) {
            return [false, 'نشست شما منقضی شده است. دوباره وارد شوید.'];
        }
        $key = (string) $u['id'] . '|' . Session::get('reauth');
        if (!Throttle::attempt('reauth', $key, self::REAUTH_MAX, 900)) {
            Audit::log((int) $u['id'], 'session_reauth_locked');
            self::logout();
            return [false, 'تلاش‌های ناموفق زیاد بود؛ نشست باطل شد. دوباره وارد شوید.'];
        }
        // تلاش‌های reauth در همان شمارنده‌ی ورود (موبایل + IP) هم حساب می‌شوند: کوکی دزدیده‌شده راه حدس جدا ندارد
        $win = max(1, (int) env('LOGIN_WINDOW_MINUTES', 15)) * 60;
        $loginKey = (string) $u['mobile'] . '|' . $req->rateIp();
        if (Throttle::count('login_user', $loginKey, $win) >= max(3, (int) env('LOGIN_MAX_ATTEMPTS', 5))) {
            Audit::log((int) $u['id'], 'session_reauth_locked', ['by' => 'login_user']);
            self::logout();
            return [false, 'تلاش‌های ناموفق زیاد بود؛ نشست باطل شد. دوباره وارد شوید.'];
        }
        if (!password_verify($password, (string) $u['password_hash'])) {
            Throttle::hit('login_user', $loginKey);
            Audit::log((int) $u['id'], 'session_reauth_failed');
            return [false, 'رمز عبور اشتباه است.'];
        }
        Throttle::clear('login_user', $loginKey);
        Session::regenerate();
        Session::put('ipf', $req->ip());
        Session::put('ipp', $req->ipPrefix());
        Session::forget('reauth');
        DbSessionHandler::bind($req->ip(), $req->ipPrefix(), $req->userAgent());
        Throttle::clear('reauth', $key);
        Audit::log((int) $u['id'], 'session_reauth', ['ip' => $req->ip()]);
        self::$loaded = false;
        self::$reauth = false;
        self::$user = null;
        return [true, ''];
    }

    public const REAUTH_MAX = 5;

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

    /**
     * حساب «غیرفعال» (deactivated، v7.8): کاربر وارد می‌شود و همه‌چیز را می‌بیند (فقط خواندنی) ولی هیچ تغییری نمی‌تواند بدهد
     * (Authenticate / ApiAuth همه‌ی درخواست‌های تغییر‌دهنده را با ACCOUNT_INACTIVE رد می‌کنند). «مسدود» و «در انتظار تأیید» اصلاً وارد پنل نمی‌شوند.
     */
    public static function isReadOnly(?array $u = null): bool
    {
        $u ??= self::user();
        return $u !== null && $u['status'] === 'deactivated' && !self::isImpersonating();
    }

    /** درخواست‌های تغییر‌دهنده‌ای که حساب غیرفعال هم مجاز است (خروج، تم دستگاه، دیده‌شدن یادآوری) */
    public const READ_ONLY_ALLOWED = ['/logout', '/profile/appearance', '/notifications/ack', '/reauth'];

    public const INACTIVE_MSG = 'حساب کاربری شما غیرفعال است؛ فقط می‌توانید اطلاعات را ببینید و امکان ثبت یا تغییر ندارید. برای فعال‌سازی با مدیر تماس بگیرید.';

    public static function isActive(?array $u = null): bool
    {
        $u ??= self::user();
        return $u !== null && $u['status'] === 'active';
    }

    /** این درخواست داخل پنجره‌ی ورود مدیر به پنل کاربر است؟ (فقط از روی آدرس /as/<توکن> معتبر؛ نه داده‌ی نشست) */
    public static function isImpersonating(): bool
    {
        return Impersonation::active();
    }

    public static function impersonator(): ?array
    {
        return Impersonation::active() ? Impersonation::current()['admin'] : null;
    }

    /** همه‌ی دسترسی‌های پنل مدیر (فهرست و توضیح در AdminAccess::PERMS؛ v7.4) */
    public const ADMIN_PERMS = ['view_analysis', 'view_users', 'add_user', 'edit_user', 'toggle_status', 'delete_user', 'manage_admins', 'impersonate',
        'manage_impersonations', 'edit_sidebar', 'edit_tour', 'edit_board', 'edit_placeholders', 'edit_errors', 'support_view', 'support_reply',
        'support_manage', 'view_security', 'view_panel_docs', 'chat_encrypt'];

    /** مدیر کل = اولین کاربر admin. */
    public static function superAdminId(): int
    {
        static $id = null;
        return $id ??= (int) DB::value("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    }

    /** نقش‌های مدیر (users.admin_role) — v4.4 */
    public const ADMIN_ROLES = [
        'super' => ['label' => 'مدیر کل', 'tone' => 'green'],
        'internal' => ['label' => 'مدیر داخلی', 'tone' => 'blue'],
        'support' => ['label' => 'پشتیبان', 'tone' => 'purple'],
    ];

    /** نقش مدیر (اولین مدیر همیشه مدیر کل؛ مقدار نامعتبر/خالی = مدیر داخلی) */
    public static function adminRole(array $u): ?string
    {
        if (($u['role'] ?? '') !== 'admin') {
            return null;
        }
        if ((int) $u['id'] === self::superAdminId()) {
            return 'super';
        }
        $r = (string) ($u['admin_role'] ?? '');
        return isset(self::ADMIN_ROLES[$r]) ? $r : 'internal';
    }

    /**
     * دسترسی‌های مدیر (v7.4، SEC-001): مدیر کل همه؛ بقیه طبق نقش (قابل تنظیم در «تنظیمات ← دسترسی») یا دسترسی اختصاصی خودشان.
     */
    public static function adminPermissions(array $u): array
    {
        return AdminAccess::of($u);
    }

    /** نقش مدیر کل (نه فقط «هر مدیری») */
    public static function isSuperAdmin(?array $u = null): bool
    {
        $u ??= self::user();
        return $u !== null && self::adminRole($u) === 'super';
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
        bool $remember = false,
        bool $session = true
    ): array
    {
        $ip = $req->rateIp();                                  // IPv6: پیشوند ‎/64
        $max = max(3, (int) env('LOGIN_MAX_ATTEMPTS', 5));
        $win = max(1, (int) env('LOGIN_WINDOW_MINUTES', 15)) * 60;
        $keyUser = $mobile . '|' . $ip;
        $busy = 'تعداد تلاش‌های ناموفق زیاد است. چند دقیقه بعد دوباره تلاش کنید.';
        $locked = 'به‌دلیل تلاش‌های ناموفق زیاد، ورود به این حساب ۳۰ دقیقه قفل شده است.';

        // سقف‌ها به ترتیب: کل IP → موبایل+IP → خود حساب. اول ثبت، بعد شمارش (Race). درخواستی که به سقف IP خورده
        // در شمارنده‌های بعدی ثبت نمی‌شود (نه قفل حساب دیگران، نه رشد بی‌دلیل جدول). ورود موفق ثبت‌ها را برمی‌دارد.
        if (!Throttle::attempt('login_ip', $ip, $max * 5, $win)) {
            Audit::log(null, 'login_throttled', ['mobile' => $mobile, 'scope' => 'ip']);
            return ['ok' => false, 'message' => $busy];
        }
        if (!Throttle::attempt('login_user', $keyUser, $max, $win)) {
            Audit::log(null, 'login_throttled', ['mobile' => $mobile]);
            return ['ok' => false, 'message' => $busy];
        }

        // قفل حساب فقط برای حمله‌ی توزیع‌شده: هر شبکه (IPv4 ‎/24، IPv6 ‎/48) در هر بازه فقط «یک بار» در شمارنده‌ی حساب حساب می‌شود،
        // پس مهاجمِ تنها (یا چند IP معدود) نمی‌تواند حساب کسی را قفل کند؛ سقف موبایل+IP جلوی حدس رمزش را می‌گیرد.
        // دستگاهی که قبلاً با همین حساب موفق وارد شده مشمول قفل نیست (صاحب حساب همیشه می‌تواند وارد شود و قفل را بردارد).
        if (!self::trustedDevice($mobile)) {
            if (Throttle::count('login_lock', $mobile, self::LOCK_SECONDS) > 0) {
                Audit::log(null, 'login_locked', ['mobile' => $mobile]);
                return ['ok' => false, 'message' => $locked];
            }
            // «منبع» = شبکه‌ی بزرگ (IPv4 ‎/24، IPv6 ‎/48): یک مشترک IPv6 یا VPS با هزاران آدرس فقط یک منبع حساب می‌شود
            $src = $mobile . '|' . $req->widePrefix();
            if (Throttle::count('login_src', $src, $win) === 0) {
                Throttle::hit('login_src', $src);
                Throttle::hit('login_acct', $mobile);
            }
            if (Throttle::count('login_acct', $mobile, $win) > self::lockSources()) {
                Throttle::hit('login_lock', $mobile);
                Throttle::clear('login_acct', $mobile);
                Audit::log(null, 'login_locked', ['mobile' => $mobile, 'start' => true]);
                return ['ok' => false, 'message' => $locked];
            }
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
        if ($session) {                                        // $session = false: فقط بررسی رمز (ساخت توکن در «تست API»)، بدون ورود به پنل
            self::login($u, $req, $remember);
            self::trustDevice($mobile, $u);
        }
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

    /** قفل حساب وقتی تلاش ناموفق از بیش از این تعداد IP/شبکه‌ی مختلف در یک بازه آمده باشد */
    public static function lockSources(): int
    {
        return max(3, (int) env('LOGIN_MAX_ATTEMPTS', 5)) * 2;
    }

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

    public static function uaHashOf(Request $req): string
    {
        return self::uaHash($req);
    }

    public static function passwordVersion(array $u): string
    {
        return self::pv($u);
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
        Session::forget('imp_sk');                    // پنجره‌های ورود به پنل کاربر نشست قبلی به این ورود منتقل نمی‌شوند
        Session::forget('imp_stepup');
        Session::forget('_scope');
        Session::forget('reauth');
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
            try {
                Impersonation::endSession('logout');     // همه‌ی پنجره‌های ورود به پنل کاربرِ همین نشست مدیر
            } catch (\Throwable $e) {
            }
        }
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
        self::$reauth = false;
    }

    /** ثبت‌نام کاربر جدید (وضعیت pending). */
    public static function register(array $d): int
    {
        $id = DB::insert('users', [
            'mobile'        => $d['mobile'],
            'first_name'    => $d['first_name'],
            'last_name'     => $d['last_name'],
            'email'         => $d['email'] !== '' ? $d['email'] : null,
            'password_hash' => self::hash($d['password']),
            'status'        => $d['status'] ?? 'pending',
            'role'          => $d['role'] ?? 'user',
            'admin_permissions' => $d['admin_permissions'] ?? null,
        ]);
        \App\Models\SubjectModel::seedDefaults($id);
        return $id;
    }
}
