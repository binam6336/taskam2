<?php
declare(strict_types=1);

namespace App\Core;

/**
 * ورود مدیر به پنل کاربر در تب جداگانه (v5.1)
 *
 * نشست مدیر عوض نمی‌شود: هر «پنجره‌ی ورود به پنل کاربر» یک ردیف جدول impersonations و یک توکن تصادفی ۱۲۸ بیتی است
 * و پنل کاربر زیر آدرس /as/<توکن>/… باز می‌شود (base_url همان پیشوند را می‌گیرد، پس همه‌ی لینک‌ها و درخواست‌های آن تب
 * در همان پنجره می‌مانند). مدیر هم‌زمان چند تب با کاربران مختلف و تب پنل مدیر را دارد.
 *
 * لایه‌های امنیتی (هر درخواست):
 *  1. توکن فقط به‌صورت SHA-256 ذخیره می‌شود؛ ۳۲ نویسه‌ی hex تصادفی (random_bytes).
 *  2. توکن به نشست همان مدیر گره خورده است: کلید مخفی imp_sk داخل نشست (نه در کوکی یا آدرس)؛ توکن بدون کوکی نشست همان
 *     مرورگر بی‌اثر است (نشت آدرس در تاریخچه/لاگ کافی نیست). خروج مدیر، پایان نشست یا ورود دوباره = پایان همه‌ی پنجره‌ها.
 *  3. همان مرورگر (هش User-Agent) و همان شبکه‌ی نشست مدیر (اتصال نشست به دستگاه Auth)؛ مرورگر دیگر = پایان + ثبت سوءاستفاده.
 *  4. مدیر باید هنوز مدیرِ فعال با دسترسی «ورود به پنل کاربر» باشد و رمزش از شروع عوض نشده باشد.
 *  5. کاربر هدف باید هنوز کاربر عادیِ فعال باشد (مدیر و ربات هرگز).
 *  6. سقف بی‌فعالیتی و سقف عمر قطعی (IMPERSONATE_IDLE_MINUTES / IMPERSONATE_MAX_HOURS) و سقف پنجره‌ی باز هم‌زمان.
 *  8. داده‌ی نشست هر پنجره جداست (Session::scope): پیام‌ها/فرم‌ها بین تب‌ها و پنل مدیر قاطی نمی‌شوند.
 *  9. کارهای حساس حساب (رمز، موبایل، نشست‌ها، توکن API، اشتراک اعلان) و API با نشست در این حالت ممنوع است.
 * 10. هر درخواست تغییر‌دهنده در audit_log با نام مدیر و کاربر ثبت می‌شود؛ صفحه‌ها no-store و بدون Referer.
 */
final class Impersonation
{
    public const PREFIX = '/as/';
    public const MAX_OPEN = 10;
    private const TOUCH_EVERY = 60;

    private static ?string $token = null;
    private static ?array $ctx = null;

    public static function idleSeconds(): int
    {
        return max(5, min(240, (int) env('IMPERSONATE_IDLE_MINUTES', 30))) * 60;
    }

    public static function maxSeconds(): int
    {
        return max(1, min(12, (int) env('IMPERSONATE_MAX_HOURS', 4))) * 3600;
    }

    /** Request::path() توکن پیشوند آدرس را این‌جا می‌گذارد */
    public static function setToken(?string $t): void
    {
        self::$token = $t;
    }

    public static function token(): ?string
    {
        return self::$token;
    }

    /** پیشوند آدرس همین پنجره (برای base_url)؛ بیرون از پنجره = خالی */
    public static function prefix(): string
    {
        return self::$token !== null ? rtrim(self::PREFIX, '/') . '/' . self::$token : '';
    }

    public static function active(): bool
    {
        return self::$ctx !== null;
    }

    /** ردیف پنجره‌ی فعلی + target (ردیف کاربر هدف) + admin */
    public static function current(): ?array
    {
        return self::$ctx;
    }

    /** کلید مخفی نشست مدیر (فقط داخل نشست؛ هرگز بیرون نمی‌رود) */
    private static function sessionKey(bool $create): ?string
    {
        $k = Session::get('imp_sk');
        if ((!is_string($k) || strlen($k) !== 64) && $create) {
            $k = bin2hex(random_bytes(32));
            Session::put('imp_sk', $k);
        }
        return is_string($k) && strlen($k) === 64 ? $k : null;
    }

    /** شروع پنجره‌ی جدید؛ خروجی: توکن (فقط همین یک‌بار به‌صورت خام) */
    public static function start(array $admin, array $target, Request $r): string
    {
        $sk = (string) self::sessionKey(true);
        $sh = hash('sha256', $sk);
        // سقف پنجره‌ی باز هم‌زمان: قدیمی‌ترین‌ها بسته می‌شوند
        $open = DB::column('SELECT id FROM impersonations WHERE admin_id = ? AND ended_at IS NULL ORDER BY last_seen_at DESC, id DESC', [(int) $admin['id']]);
        foreach (array_slice($open, self::MAX_OPEN - 1) as $old) {
            self::end((int) $old, 'limit');
        }
        $token = bin2hex(random_bytes(16));
        $impId = DB::insert('impersonations', [
            'token_hash' => hash('sha256', $token), 'admin_id' => (int) $admin['id'], 'target_id' => (int) $target['id'],
            'session_hash' => $sh, 'admin_pv' => Auth::passwordVersion($admin), 'ua_hash' => Auth::uaHashOf($r),
            'ip' => $r->ip(), 'last_ip' => $r->ip(), 'last_seen_at' => date('Y-m-d H:i:s'), 'expires_at' => date('Y-m-d H:i:s', time() + self::maxSeconds()),
        ]);
        Audit::log((int) $admin['id'], 'impersonate_start', ['target' => (int) $target['id'], 'ip' => $r->ip()]);
        AdminLog::enter($admin, $target, $impId, $r->ip());
        return $token;
    }

    /** آدرس کامل یک مسیر داخل پنجره */
    public static function urlFor(string $token, string $path): string
    {
        return app_base() . rtrim(self::PREFIX, '/') . '/' . $token . '/' . ltrim($path, '/');
    }

    /**
     * App::run: اگر آدرس با /as/<توکن> شروع شده، پنجره اعتبارسنجی می‌شود؛ نامعتبر = صفحه‌ی «این پنجره بسته شده» (هرگز
     * بازگشت بی‌صدا به هویت مدیر یا کاربر دیگر).
     */
    public static function boot(Request $r): void
    {
        if (self::$token === null) {
            return;
        }
        if ($r->isApi()) {
            self::deny('API با نشست مرورگر داخل پنجره‌ی ورود به پنل کاربر در دسترس نیست.', 403);
        }
        if (!preg_match('/^[a-f0-9]{32}$/', self::$token)) {
            self::deny('آدرس این پنجره نامعتبر است.');
        }
        $row = DB::one('SELECT * FROM impersonations WHERE token_hash = ? LIMIT 1', [hash('sha256', self::$token)]);
        if ($row === null) {
            self::deny('این پنجره‌ی ورود به پنل کاربر وجود ندارد.');
        }
        if ($row['ended_at'] !== null) {
            self::deny('این پنجره‌ی ورود به پنل کاربر بسته شده است' . (isset(self::REASONS[$row['end_reason']]) ? ' (' . self::REASONS[$row['end_reason']] . ')' : '') . '.');
        }
        $now = time();
        if (strtotime((string) $row['expires_at']) < $now) {
            self::end((int) $row['id'], 'expired');
            self::deny('زمان این پنجره (حداکثر ' . fa_num(intdiv(self::maxSeconds(), 3600)) . ' ساعت) تمام شده است.');
        }
        if (strtotime((string) $row['last_seen_at']) < $now - self::idleSeconds()) {
            self::end((int) $row['id'], 'idle');
            self::deny('این پنجره بیش از ' . fa_num(intdiv(self::idleSeconds(), 60)) . ' دقیقه بدون فعالیت بود و بسته شد.');
        }
        // مالکیت: همان نشست (کلید مخفی داخل نشست) و همان مرورگر
        $sk = self::sessionKey(false);
        if ($sk === null || !hash_equals((string) $row['session_hash'], hash('sha256', $sk))) {
            // توکن بدون نشست همان مدیر: آدرس نشت کرده یا نشست مدیر عوض شده → پنجره همان‌جا بسته می‌شود
            self::end((int) $row['id'], 'misuse');
            Audit::log((int) $row['admin_id'], 'impersonate_token_misuse', ['target' => (int) $row['target_id'], 'ip' => $r->ip()]);
            self::deny('این پنجره متعلق به نشست دیگری است و برای امنیت بسته شد.');
        }
        if (!hash_equals((string) $row['ua_hash'], Auth::uaHashOf($r))) {
            self::end((int) $row['id'], 'misuse');
            Audit::log((int) $row['admin_id'], 'impersonate_token_misuse', ['target' => (int) $row['target_id'], 'ip' => $r->ip(), 'why' => 'ua']);
            self::deny('این پنجره در مرورگر دیگری باز شد و برای امنیت بسته شد.');
        }
        // نشست مدیر (اتصال به دستگاه و شبکه، نسخه‌ی رمز) باید خودش معتبر باشد
        $admin = Auth::realUser();
        if ($admin === null) {
            self::deny(Auth::needsReauth() ? 'شبکه‌ی اینترنت عوض شده است؛ ابتدا در پنل مدیر رمز خود را دوباره وارد کنید.' : 'نشست مدیر پایان یافته است؛ دوباره وارد پنل مدیر شوید.');
        }
        if ((int) $admin['id'] !== (int) $row['admin_id']) {
            self::end((int) $row['id'], 'misuse');
            self::deny('این پنجره متعلق به مدیر دیگری است.');
        }
        if ($admin['role'] !== 'admin' || $admin['status'] !== 'active' || !in_array('impersonate', Auth::adminPermissions($admin), true)
            || !hash_equals((string) $row['admin_pv'], Auth::passwordVersion($admin))) {
            self::end((int) $row['id'], 'admin_changed');
            self::deny('دسترسی یا رمز مدیر تغییر کرده است؛ پنجره بسته شد.');
        }
        $target = DB::one('SELECT * FROM users WHERE id = ? AND is_bot = 0 LIMIT 1', [(int) $row['target_id']]);
        if ($target === null || $target['role'] === 'admin' || $target['status'] !== 'active') {
            self::end((int) $row['id'], 'target_inactive');
            self::deny('حساب این کاربر دیگر فعال نیست؛ پنجره بسته شد.');
        }
        // درخواست‌های پس‌زمینه (live/poll و …) فعالیت حساب نمی‌شوند تا تب رهاشده بعد از سقف بی‌فعالیتی بسته شود
        if (!Session::isBackground() && (strtotime((string) $row['last_seen_at']) < $now - self::TOUCH_EVERY || $row['last_ip'] !== $r->ip())) {
            DB::run('UPDATE impersonations SET last_seen_at = NOW(), last_ip = ? WHERE id = ?', [$r->ip(), (int) $row['id']]);
        }
        self::$ctx = $row + ['target' => $target, 'admin' => $admin];
        Session::scope('imp' . (int) $row['id']);
        AdminLog::watch(true);                          // هر تغییر این درخواست در لاگ امنیتی مدیران      // پیام‌ها و داده‌ی موقت این پنجره جدا از پنل مدیر و تب‌های دیگر
        // هر درخواست تغییر‌دهنده‌ی پنجره در لاگ ممیزی (به نام مدیر)
        if ($r->method() === 'POST') {
            DB::run('UPDATE impersonations SET actions = actions + 1 WHERE id = ?', [(int) $row['id']]);
            Audit::log((int) $admin['id'], 'impersonate_action', ['target' => (int) $target['id'], 'path' => mb_substr($r->path(), 0, 120)]);
        }
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
    }

    private const REASONS = [
        'exit' => 'خروج از پنجره', 'admin_end' => 'بسته‌شده از پنل مدیر', 'end_all' => 'بستن همه از پنل مدیر', 'logout' => 'خروج مدیر',
        'idle' => 'بی‌فعالیتی', 'expired' => 'پایان زمان', 'admin_changed' => 'تغییر دسترسی یا رمز مدیر', 'target_inactive' => 'غیرفعال شدن کاربر',
        'misuse' => 'استفاده در نشست یا مرورگر دیگر', 'limit' => 'سقف پنجره‌های باز',
    ];

    public static function reasonLabel(?string $r): string
    {
        return self::REASONS[$r ?? ''] ?? '';
    }

    public static function end(int $id, string $reason): void
    {
        $row = DB::one('SELECT admin_id, target_id FROM impersonations WHERE id = ? AND ended_at IS NULL', [$id]);
        if ($row === null) {
            return;
        }
        DB::run('UPDATE impersonations SET ended_at = NOW(), end_reason = ? WHERE id = ? AND ended_at IS NULL', [$reason, $id]);
        AdminLog::exit((int) $row['admin_id'], (int) $row['target_id'], $id, $reason);
        Audit::log((int) $row['admin_id'], 'impersonate_stop', ['target' => (int) $row['target_id'], 'reason' => $reason]);
    }

    /** بستن همه‌ی پنجره‌های باز یک مدیر (خروج مدیر، «بستن همه») */
    public static function endAll(int $adminId, string $reason): int
    {
        $ids = DB::column('SELECT id FROM impersonations WHERE admin_id = ? AND ended_at IS NULL', [$adminId]);
        foreach ($ids as $i) {
            self::end((int) $i, $reason);
        }
        return count($ids);
    }

    /** بستن پنجره‌های باز همین نشست مدیر (خروج؛ نشست‌های مدیر روی دستگاه‌های دیگر دست نمی‌خورند) */
    public static function endSession(string $reason): int
    {
        $sk = self::sessionKey(false);
        if ($sk === null) {
            return 0;
        }
        $ids = DB::column('SELECT id FROM impersonations WHERE session_hash = ? AND ended_at IS NULL', [hash('sha256', $sk)]);
        foreach ($ids as $i) {
            self::end((int) $i, $reason);
        }
        return count($ids);
    }

    /** پنجره‌های باز همین نشست مدیر (برای پنل مدیر)؛ داده‌ی نشست پنجره‌های بسته‌شده هم همین‌جا پاک می‌شود */
    public static function openFor(int $adminId): array
    {
        $sk = self::sessionKey(false);
        if ($sk === null) {
            return [];
        }
        $rows = DB::all('SELECT i.id, i.pid, i.target_id, i.created_at, i.last_seen_at, i.expires_at, i.actions, u.first_name, u.last_name, u.mobile, u.avatar
            FROM impersonations i JOIN users u ON u.id = i.target_id
            WHERE i.admin_id = ? AND i.session_hash = ? AND i.ended_at IS NULL AND i.expires_at > NOW() AND i.last_seen_at > (NOW() - INTERVAL ? SECOND)
            ORDER BY i.last_seen_at DESC', [$adminId, hash('sha256', $sk), self::idleSeconds()]);
        $keep = array_map(static fn($r) => 'imp' . (int) $r['id'], $rows);
        $scopes = (array) Session::get('_scope', []);
        if ($scopes && array_diff(array_keys($scopes), $keep)) {
            Session::put('_scope', array_intersect_key($scopes, array_flip($keep)));
        }
        return $rows;
    }

    /** صفحه‌ی ساده‌ی «پنجره بسته شده» (بدون app.js؛ فقط کلاس‌ها) و پایان درخواست */
    private static function deny(string $msg, int $status = 410): void
    {
        $msg = ErrorText::apply($msg);       // متن ویرایش‌شده در «محتوا ← خطاها»
        self::$token = null;                // لینک‌های صفحه به آدرس اصلی برنامه
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        http_response_code($status);
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
        if (str_contains($accept, 'application/json') || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => $msg, 'code' => 'IMPERSONATION_ENDED'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>پنجره بسته شد</title><link rel="icon" type="image/svg+xml" href="' . e(favicon_uri()) . '">'
            . '<link rel="stylesheet" href="' . e(asset('vendor/vazirmatn/Vazirmatn-font-face.css')) . '"><link rel="stylesheet" href="' . e(asset('vendor/fontawesome/css/karlix-icons.css')) . '">'
            . '<link rel="stylesheet" href="' . e(asset('css/app.css')) . '"></head><body><div class="error-page"><div>'
            . '<h2 class="mt-1"><i class="fa-solid fa-user-lock err-ico"></i> پنجره‌ی ورود به پنل کاربر</h2><p>' . e($msg) . '</p>'
            . '<p class="muted small">این تب را ببندید؛ نشست پنل مدیر شما سر جایش است.</p>'
            . '<div class="flex gap-2 err-actions"><a class="btn btn-primary" href="' . e(app_base() . '/admin') . '"><i class="fa-solid fa-gauge"></i> پنل مدیریت</a></div>'
            . '</div></div></body></html>';
        exit;
    }
}
