<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\Push;
use App\Services\WebPush;

/** نسخه‌ی PWA: manifest، service worker، صفحه‌ی آفلاین، صفحه‌ی نصب و اشتراک اعلان‌ها. */
final class PwaController extends Controller
{
    private static function version(): string
    {
        $v = trim((string) @file_get_contents(BASE_PATH . '/VERSION')) ?: '0';
        $files = [APP_PATH . '/Views/pwa/sw.js', BASE_PATH . '/public/assets/css/app.css', BASE_PATH . '/public/assets/js/app.js'];
        return $v . '-' . substr(md5(implode('|', array_map(static fn($f) => (string) @filemtime($f), $files))), 0, 8);
    }

    /** مسیر پایه با اسلش پایانی، مثل /taskam2/ */
    private static function scope(): string
    {
        return rtrim(base_url(), '/') . '/';
    }

    public function manifest()
    {
        $name = (string) app_name();
        $s = self::scope();
        // آیکن‌ها و رنگ‌ها مطابق تم کاربر (manifest با crossorigin="use-credentials" درخواست می‌شود تا نشست همراهش باشد)
        $th = user_theme(Auth::user());
        $sx = icon_suffix($th);
        $icon = static fn(string $f, string $size, string $purpose = 'any') => ['src' => $s . 'assets/img/' . $f . $sx . '.png', 'sizes' => $size, 'type' => 'image/png', 'purpose' => $purpose];
        $theme = themes()[$th]['meta'];
        $m = [
            'id' => $s,
            'name' => $name . ' — مدیریت وظایف و تیم',
            'short_name' => $name,
            'description' => 'مدیریت وظایف، پروژه‌ها، تیم و گفتگو',
            'lang' => 'fa',
            'dir' => 'rtl',
            'start_url' => $s . '?source=pwa',
            'scope' => $s,
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'any',
            'background_color' => $theme,
            'theme_color' => $theme,
            'categories' => ['productivity', 'business'],
            'icons' => [
                $icon('icon-192', '192x192'), $icon('icon-512', '512x512'),
                $icon('icon-maskable-192', '192x192', 'maskable'), $icon('icon-maskable-512', '512x512', 'maskable'),
            ],
            'shortcuts' => [
                ['name' => 'تسک‌ها', 'short_name' => 'تسک‌ها', 'url' => $s . 'tasks', 'icons' => [$icon('icon-192', '192x192')]],
                ['name' => 'گفتگو', 'short_name' => 'گفتگو', 'url' => $s . 'chat', 'icons' => [$icon('icon-192', '192x192')]],
                ['name' => 'پیشخوان', 'short_name' => 'پیشخوان', 'url' => $s . 'dashboard', 'icons' => [$icon('icon-192', '192x192')]],
            ],
        ];
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: no-cache');
        echo json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    /** service worker از ریشه‌ی برنامه (/service-worker) سرو می‌شود تا scope آن کل پنل باشد. */
    public function serviceWorker()
    {
        $js = (string) file_get_contents(APP_PATH . '/Views/pwa/sw.js');
        $js = str_replace('__VERSION__', self::version(), $js);
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . self::scope());
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $js;
        exit;
    }

    /** صفحه‌ی آفلاین (در کش service worker نگه داشته می‌شود) */
    public function offline()
    {
        $this->view('pwa/offline', [], ['title' => 'آفلاین'], null);
    }

    /** فایل نرم‌افزار اندروید (v5.14): کنار برنامه، بیرون از پوشه‌ی عمومی؛ آپلود دستی روی سرور */
    public const APK_FILE = 'app/karlix.apk';
    /** نام قبلی فایل (پیش از ریبرند v7.5)؛ اگر فایل تازه نباشد همین خوانده می‌شود */
    private const APK_LEGACY = 'app/taskam.apk';

    private static function apkPath(): ?string
    {
        foreach ([self::APK_FILE, self::APK_LEGACY] as $rel) {
            $f = STORAGE_PATH . '/' . $rel;
            if (is_file($f) && is_readable($f) && filesize($f) > 0) {
                return $f;
            }
        }
        return null;
    }

    public function apkCheck()
    {
        $f = self::apkPath();
        if ($f === null) {
            $this->fail('خطا در دریافت نرم‌افزار، لطفاً بعداً تلاش کنید.', 404);
        }
        $this->ok('', ['size' => filesize($f), 'size_fa' => fa_num(round(filesize($f) / 1048576, 1)) . ' مگابایت', 'updated' => jdate(date('Y-m-d H:i:s', (int) filemtime($f)))]);
    }

    public function apk()
    {
        $f = self::apkPath();
        if ($f === null) {
            \App\Core\Session::flash('error', 'خطا در دریافت نرم‌افزار، لطفاً بعداً تلاش کنید.');
            $this->redirect('/install');
        }
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();          // دانلود طولانی جلوی بقیه‌ی درخواست‌های کاربر را نگیرد
        }
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="karlix.apk"');
        header('Content-Length: ' . filesize($f));
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        readfile($f);
        exit;
    }

    public function install()
    {
        $u = $this->user();
        $admin = Auth::isAdmin($u) && !Auth::isImpersonating();
        $this->view('pwa/install', [
            'pushReady' => Push::enabled(),
            'checks' => Push::diagnose(),
            'devices' => Push::count((int) $u['id']),
            'apk' => ($a = self::apkPath()) !== null ? ['size' => fa_num(round(filesize($a) / 1048576, 1)) . ' مگابایت', 'updated' => jdate(date('Y-m-d H:i:s', (int) filemtime($a)))] : null,
            'https' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (\App\Core\Request::fromTrustedProxy() && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'], true) || str_starts_with((string) ($_SERVER['HTTP_HOST'] ?? ''), '127.0.0.1:') || str_starts_with((string) ($_SERVER['HTTP_HOST'] ?? ''), 'localhost:'),
        ], ['title' => 'نصب نرم‌افزار', 'page' => $admin ? 'admin-install' : 'install'], $admin ? 'admin' : 'app');
    }

    // ───── اشتراک اعلان (Web Push) ─────

    public function pushKey()
    {
        if (!Push::enabled()) {
            $this->fail(Push::reason(), 503);
        }
        $this->ok('', ['key' => WebPush::vapid()['public']]);
    }

    public function subscribe()
    {
        // اشتراک اعلان دستگاه مدیر نباید به حساب کاربری که مدیر واردش شده وصل شود
        $this->denyImpersonation('/profile');
        if (!Push::enabled()) {
            $this->fail(Push::reason(), 503);
        }
        $r = $this->request;
        $endpoint = trim($r->str('endpoint', 2000));
        $p256dh = trim($r->str('p256dh', 255));
        $auth = trim($r->str('auth', 64));
        if (!Push::allowedEndpoint($endpoint) || strlen(WebPush::unb64u($p256dh)) !== 65 || strlen(WebPush::unb64u($auth)) < 16) {
            $this->fail('اطلاعات اشتراک اعلان نامعتبر است.');
        }
        if (!Push::canClaim($this->uid(), $endpoint, $p256dh, $auth)) {
            $this->fail('اطلاعات اشتراک اعلان نامعتبر است.');
        }
        if (Push::atLimit($this->uid(), $endpoint)) {
            $this->fail('حداکثر ' . fa_num(Push::MAX_DEVICES) . ' دستگاه می‌توانند اعلان بگیرند؛ ابتدا اعلان یکی از دستگاه‌های قبلی را غیرفعال کنید.');
        }
        Push::subscribe($this->uid(), $endpoint, $p256dh, $auth, (string) ($r->header('User-Agent') ?? ''));
        $this->ok('اعلان‌ها روی این دستگاه فعال شد.', ['devices' => Push::count($this->uid())]);
    }

    public function unsubscribe()
    {
        // اشتراک اعلان دستگاه مدیر نباید به حساب کاربری که مدیر واردش شده وصل شود
        $this->denyImpersonation('/profile');
        if (Push::enabled()) {
            Push::unsubscribe($this->uid(), trim($this->request->str('endpoint', 2000)));
        }
        $this->ok('اعلان‌های این دستگاه غیرفعال شد.');
    }

    public function test()
    {
        // اشتراک اعلان دستگاه مدیر نباید به حساب کاربری که مدیر واردش شده وصل شود
        $this->denyImpersonation('/profile');
        if (!\App\Core\Throttle::attempt('push_test', (string) $this->uid(), 5, 600)) {
            $this->fail('اعلان آزمایشی را چند دقیقه بعد دوباره امتحان کنید.', 429);
        }
        $n = Push::test($this->uid());
        if ($n === 0) {
            $this->fail('هیچ دستگاهی اعلان را دریافت نکرد. ابتدا «فعال‌سازی اعلان‌ها» را بزنید.');
        }
        $this->ok('اعلان آزمایشی به ' . fa_num($n) . ' دستگاه ارسال شد.');
    }
}
