<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * لاگ برنامه (v7.11):
 *  - لاگ عمومی روزانه: storage/logs/YYYY-MM-DD.log — رویدادهای سیستم (ورود/خروج، مهاجرت، پنجره‌ی مدیر، …) و
 *    یک خط خلاصه برای هر خطا که به فایل بخش مربوط اشاره می‌کند.
 *  - لاگ خطای هر بخش: storage/logs/<بخش>/YYYY-MM-DD.log (مثل tasks، projects، users، auth) — فقط خطاها،
 *    با نوع، پیام، زمان دقیق، بخش، فایل و خط، درخواست و Stack Trace کامل (بدون آرگومان توابع).
 * پوشه‌ها خودکار ساخته می‌شوند؛ فایل‌های قدیمی‌تر از LOG_RETENTION_DAYS (پیش‌فرض ۳۰، صفر = نگه‌داشتن) روزی یک‌بار پاک می‌شوند.
 * داده‌ی حساس (رمز، توکن، کوکی، هش، موبایل و ایمیل کامل) قبل از نوشتن حذف یا پوشانده می‌شود (scrub).
 */
final class Log
{
    /** بخش‌ها بر اساس نام کلاس (بدون پسوند Controller/Api/Service/Model)؛ اولین کلاس برنامه در مسیر خطا تعیین‌کننده است */
    public const CLASS_SECTIONS = [
        'Task' => 'tasks', 'TaskHistory' => 'tasks', 'TaskAnalytics' => 'tasks', 'Review' => 'tasks', 'Note' => 'tasks',
        'Calendar' => 'tasks', 'Priority' => 'tasks', 'Block' => 'tasks', 'Limits' => 'tasks',
        'Board' => 'board', 'BoardConfig' => 'board',
        'Project' => 'projects', 'Workload' => 'projects',
        'Subject' => 'labels', 'Label' => 'labels',
        'Text' => 'notes',
        'Chat' => 'chat',
        'Colleague' => 'colleagues',
        'User' => 'users', 'Profile' => 'users', 'Token' => 'users', 'Tour' => 'users',
        'Auth' => 'auth', 'Access' => 'auth', 'Session' => 'auth', 'DbSessionHandler' => 'auth', 'Captcha' => 'auth',
        'Csrf' => 'auth', 'Throttle' => 'auth',
        'Admin' => 'admin', 'AdminLog' => 'admin', 'AdminAccess' => 'admin', 'Impersonation' => 'admin',
        'Content' => 'admin', 'Sidebar' => 'admin',
        'Support' => 'support', 'SupportDesk' => 'support',
        'Notifier' => 'notifications', 'Push' => 'notifications', 'WebPush' => 'notifications', 'Live' => 'notifications',
        'File' => 'files', 'Uploader' => 'files',
        'Dashboard' => 'dashboard',
        'Docs' => 'docs', 'DocsHelper' => 'docs', 'Meta' => 'docs',
        'Pwa' => 'pwa',
        'Search' => 'search',
        'Schema' => 'database', 'DB' => 'database',
    ];

    /** بخش بر اساس اولین قطعه‌ی آدرس (وقتی کلاس برنامه‌ای در مسیر خطا نیست) */
    public const PATH_SECTIONS = [
        'tasks' => 'tasks', 'calendar' => 'tasks', 'block' => 'tasks', 'unblock' => 'tasks', 'blocked' => 'tasks', 'reviews' => 'tasks',
        'board' => 'board', 'projects' => 'projects', 'labels' => 'labels', 'notes' => 'notes', 'texts' => 'notes',
        'chat' => 'chat', 'colleagues' => 'colleagues', 'profile' => 'users', 'tokens' => 'users', 'tour' => 'users',
        'login' => 'auth', 'logout' => 'auth', 'register' => 'auth', 'reauth' => 'auth', 'captcha' => 'auth', 'pending' => 'auth',
        'admin' => 'admin', 'support' => 'support', 'notifications' => 'notifications', 'push' => 'notifications', 'live' => 'notifications',
        'files' => 'files', 'docs' => 'docs', 'install' => 'pwa', 'dashboard' => 'dashboard', '' => 'dashboard',
    ];

    /** کلیدهایی که مقدارشان هرگز در لاگ نوشته نمی‌شود */
    private const SECRET_KEYS = '/pass(word|wd)?|pwd|token|secret|api[_-]?key|authorization|cookie|otp|captcha|csrf|session|hash|encode|app_key|signature|sig\b/i';

    private static ?string $rid = null;
    private static bool $writing = false;

    // ───── لاگ عمومی ─────

    public static function info(string $event, array $context = []): void
    {
        self::general('INFO', $event, $context);
    }

    public static function warning(string $event, array $context = []): void
    {
        self::general('WARNING', $event, $context);
    }

    /** یک خط در لاگ عمومی روز: [زمان] سطح رویداد req=… user=… ip=… {زمینه} */
    public static function general(string $level, string $event, array $context = []): void
    {
        $req = self::requestInfo();
        $line = sprintf(
            "[%s] %s %s req=%s%s%s%s\n",
            self::now(),
            $level,
            self::scrub($event),
            self::requestId(),
            $req['user'] !== null ? ' user=' . $req['user'] : '',
            $req['ip'] !== '' ? ' ip=' . $req['ip'] : '',
            $context ? ' ' . json_encode(self::clean($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : ''
        );
        self::write(null, $line);
    }

    // ───── لاگ خطا ─────

    /**
     * ثبت خطا در پوشه‌ی بخش مربوط (و یک خط خلاصه در لاگ عمومی). $section خالی = تشخیص خودکار از مسیر خطا و آدرس درخواست.
     * @return string بخش استفاده‌شده
     */
    public static function error(Throwable $e, ?string $section = null, array $context = []): string
    {
        $section = self::safeSection($section ?? self::sectionOf($e));
        $req = self::requestInfo();
        $code = $e->getCode();
        $out = [];
        $out[] = str_repeat('═', 22) . ' ' . self::now() . ' ' . str_repeat('═', 22);
        $out[] = 'section:  ' . $section;
        $out[] = 'request:  ' . self::requestId() . ($req['line'] !== '' ? '  ' . $req['line'] : '');
        if ($req['user'] !== null || $req['ip'] !== '') {
            $out[] = 'actor:    ' . ($req['user'] !== null ? 'user ' . $req['user'] : 'guest') . ($req['ip'] !== '' ? '  ip ' . $req['ip'] : '');
        }
        $out[] = 'type:     ' . get_class($e) . (($code !== 0 && $code !== '') ? ' (code ' . self::scrub((string) $code) . ')' : '')
            . ($e instanceof \ErrorException ? ' [' . self::severity($e->getSeverity()) . ']' : '');
        $out[] = 'message:  ' . self::scrub($e->getMessage());
        $out[] = 'location: ' . self::rel($e->getFile()) . ':' . $e->getLine();
        $origin = self::appFrame($e);
        if ($origin !== null && $origin !== self::rel($e->getFile()) . ':' . $e->getLine()) {
            $out[] = 'app code: ' . $origin;                   // اولین فراخوانی از کد برنامه (وقتی خطا داخل هسته/PDO رخ داده)
        }
        if ($context) {
            $out[] = 'context:  ' . json_encode(self::clean($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        $out[] = 'stack trace:';
        $out[] = self::trace($e);
        $depth = 0;
        for ($p = $e->getPrevious(); $p !== null && $depth < 5; $p = $p->getPrevious(), $depth++) {
            $out[] = 'caused by: ' . get_class($p) . ': ' . self::scrub($p->getMessage()) . ' in ' . self::rel($p->getFile()) . ':' . $p->getLine();
            $out[] = self::trace($p);
        }
        self::write($section, implode("\n", $out) . "\n\n");
        self::general('ERROR', $section . ' ' . get_class($e) . ': ' . mb_substr(self::scrub($e->getMessage()), 0, 300)
            . ' @ ' . self::rel($e->getFile()) . ':' . $e->getLine() . ' → logs/' . $section . '/' . date('Y-m-d') . '.log');
        return $section;
    }

    /** خطای مرگبار (Fatal/Parse) از register_shutdown_function که شیء استثنا ندارد */
    public static function fatal(array $err): void
    {
        $file = (string) ($err['file'] ?? '');
        $section = self::safeSection(self::sectionFromFile($file) ?? self::sectionFromPath() ?? 'system');
        $req = self::requestInfo();
        $out = [
            str_repeat('═', 22) . ' ' . self::now() . ' ' . str_repeat('═', 22),
            'section:  ' . $section,
            'request:  ' . self::requestId() . ($req['line'] !== '' ? '  ' . $req['line'] : ''),
            'type:     FatalError [' . self::severity((int) ($err['type'] ?? 0)) . ']',
            'message:  ' . self::scrub((string) ($err['message'] ?? '')),
            'location: ' . self::rel($file) . ':' . (int) ($err['line'] ?? 0),
            'stack trace: (not available for fatal errors)',
        ];
        self::write($section, implode("\n", $out) . "\n\n");
        self::general('ERROR', $section . ' FatalError: ' . mb_substr(self::scrub((string) ($err['message'] ?? '')), 0, 300)
            . ' @ ' . self::rel($file) . ':' . (int) ($err['line'] ?? 0) . ' → logs/' . $section . '/' . date('Y-m-d') . '.log');
    }

    /** شناسه‌ی این درخواست (در لاگ عمومی، لاگ خطا و پاسخ خطای ۵۰۰ به‌عنوان «کد پیگیری») */
    public static function requestId(): string
    {
        return self::$rid ??= bin2hex(random_bytes(4));
    }

    // ───── تشخیص بخش ─────

    public static function sectionOf(Throwable $e): string
    {
        $files = [$e->getFile()];
        foreach ($e->getTrace() as $f) {
            if (isset($f['file'])) {
                $files[] = $f['file'];
            }
        }
        // اول کلاس‌های Controller/Service/Model (محل منطق برنامه)، بعد هسته
        foreach ($files as $file) {
            if (preg_match('#/app/(Controllers|Services|Models|Middleware)/#', $file)) {
                $s = self::sectionFromFile($file);
                if ($s !== null) {
                    return $s;
                }
            }
        }
        $path = self::sectionFromPath();
        if ($path !== null) {
            return $path;
        }
        foreach ($files as $file) {
            $s = self::sectionFromFile($file);
            if ($s !== null) {
                return $s;
            }
        }
        if ($e instanceof \PDOException) {
            return 'database';
        }
        return PHP_SAPI === 'cli' ? 'cli' : 'system';
    }

    private static function sectionFromFile(string $file): ?string
    {
        if ($file === '' || !str_contains($file, '/app/')) {
            return null;
        }
        $base = basename($file, '.php');
        $name = preg_replace('/(Controller|Api|Service|Model)$/', '', $base);
        return self::CLASS_SECTIONS[$name] ?? self::CLASS_SECTIONS[$base] ?? null;
    }

    private static function sectionFromPath(): ?string
    {
        if (PHP_SAPI === 'cli') {
            return null;
        }
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $path = preg_replace('#^/as/[^/]+#', '', $path);           // پنجره‌ی ورود مدیر به پنل کاربر
        $seg = array_values(array_filter(explode('/', trim((string) $path, '/')), 'strlen'));
        $first = $seg[0] ?? '';
        if ($first === 'api') {
            $first = $seg[2] ?? ($seg[1] ?? '');                    // /api/v1/<بخش>
            if (!isset(self::PATH_SECTIONS[$first])) {
                return 'api';
            }
        }
        return self::PATH_SECTIONS[$first] ?? null;
    }

    private static function safeSection(string $s): string
    {
        $s = strtolower(preg_replace('/[^A-Za-z0-9_-]/', '', $s));
        return $s !== '' ? mb_substr($s, 0, 32) : 'system';
    }

    // ───── پاک‌سازی داده‌ی حساس ─────

    /** متن آزاد: رمز/توکن بعد از نام کلید، Bearer، هش رمز، رشته‌های طولانی شبیه کلید، موبایل و ایمیل */
    public static function scrub(string $s): string
    {
        $s = preg_replace('/\b(pass(?:word|wd)?|pwd|token|secret|api[_-]?key|authorization|otp|captcha)(["\']?\s*[:=]\s*["\']?)[^\s"\'&,;)]+/i', '$1$2[REDACTED]', $s) ?? $s;
        $s = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9._~+\/=-]{8,}/i', '$1 [REDACTED]', $s) ?? $s;
        $s = preg_replace('/\$(2[aby]|argon2i?d?)\$[^\s\'"]+/', '[REDACTED-HASH]', $s) ?? $s;
        $s = preg_replace('/(?<![A-Za-z0-9])[A-Fa-f0-9]{32,}(?![A-Za-z0-9])/', '[REDACTED]', $s) ?? $s;
        $s = preg_replace('/(?<![A-Za-z0-9+_-])[A-Za-z0-9+_-]{40,}={0,2}(?![A-Za-z0-9+_-])/', '[REDACTED]', $s) ?? $s;
        $s = preg_replace('/(?<!\d)(\+?98|0)?(9\d{2})\d{4}(\d{3})(?!\d)/', '$1$2****$3', $s) ?? $s;
        $s = preg_replace('/([A-Za-z0-9._%+-])[A-Za-z0-9._%+-]*@([A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+)/', '$1***@$2', $s) ?? $s;
        return $s;
    }

    /** آرایه‌ی زمینه: کلید حساس = [REDACTED]، متن‌ها scrub، عمق و طول محدود */
    public static function clean(array $data, int $depth = 0): array
    {
        $out = [];
        $n = 0;
        foreach ($data as $k => $v) {
            if (++$n > 50) {
                $out['…'] = 'truncated';
                break;
            }
            if (is_string($k) && preg_match(self::SECRET_KEYS, $k)) {
                $out[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $out[$k] = $depth < 3 ? self::clean($v, $depth + 1) : '[…]';
            } elseif (is_string($v)) {
                $out[$k] = self::scrub(mb_substr($v, 0, 500));
            } elseif (is_scalar($v) || $v === null) {
                $out[$k] = $v;
            } else {
                $out[$k] = is_object($v) ? get_class($v) : gettype($v);
            }
        }
        return $out;
    }

    // ───── کمکی‌ها ─────

    /** Stack trace بدون آرگومان‌ها (ممکن است رمز یا داده‌ی کاربر باشند) */
    private static function trace(Throwable $e): string
    {
        $lines = [];
        foreach ($e->getTrace() as $i => $f) {
            $where = isset($f['file']) ? self::rel($f['file']) . '(' . ($f['line'] ?? 0) . ')' : '[internal]';
            $fn = ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '') . '()';
            $lines[] = '  #' . $i . ' ' . $where . ': ' . $fn;
            if ($i >= 60) {
                $lines[] = '  … ' . (count($e->getTrace()) - 61) . ' more';
                break;
            }
        }
        $lines[] = '  #' . count($lines) . ' {main}';
        return implode("\n", $lines);
    }

    /** اولین فریم در کد برنامه (Controllers/Services/Models/Views) با فایل و خط */
    private static function appFrame(Throwable $e): ?string
    {
        $frames = array_merge([['file' => $e->getFile(), 'line' => $e->getLine()]], $e->getTrace());
        foreach ($frames as $f) {
            if (isset($f['file']) && preg_match('#/app/(Controllers|Services|Models|Views|Middleware)/#', $f['file'])) {
                return self::rel($f['file']) . ':' . ($f['line'] ?? 0);
            }
        }
        return null;
    }

    private static function rel(string $file): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        return str_starts_with($file, $base . '/') ? substr($file, strlen($base) + 1) : $file;
    }

    private static function now(): string
    {
        $t = microtime(true);
        return date('Y-m-d H:i:s', (int) $t) . sprintf('.%03d', (int) (($t - floor($t)) * 1000)) . ' ' . date('P', (int) $t);
    }

    private static function severity(int $n): string
    {
        $map = [E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE', E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR', E_COMPILE_ERROR => 'E_COMPILE_ERROR', E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE', E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED', E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR'];
        return $map[$n] ?? ('E_' . $n);
    }

    /** متد، مسیر (بدون query string)، کاربر و IP بدون باز کردن نشست یا کوئری دیتابیس */
    private static function requestInfo(): array
    {
        if (PHP_SAPI === 'cli') {
            $argv = $_SERVER['argv'] ?? [];
            return ['line' => 'cli ' . self::scrub(basename((string) ($argv[0] ?? 'php'))), 'user' => null, 'ip' => ''];
        }
        $method = preg_replace('/[^A-Z]/', '', strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')));
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $path = preg_replace('#^/as/[^/]+#', '/as/[…]', $path);     // توکن پنجره‌ی ورود مدیر در لاگ نوشته نشود
        $user = null;
        $ip = '';
        try {
            $user = Auth::loadedId();
            $r = Request::capture();
            $user ??= isset($r->params['_api_user']) ? (int) $r->params['_api_user'] : null;
            $ip = $r->ip();
        } catch (Throwable $x) {
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        }
        return ['line' => trim($method . ' ' . mb_substr(self::scrub((string) $path), 0, 300)), 'user' => $user, 'ip' => $ip];
    }

    /** نوشتن با قفل؛ پوشه‌ی بخش خودکار ساخته می‌شود؛ خطای خود لاگ هرگز به برنامه برنمی‌گردد */
    private static function write(?string $section, string $text): void
    {
        if (self::$writing) {
            return;
        }
        self::$writing = true;
        try {
            $root = (defined('STORAGE_PATH') ? STORAGE_PATH : dirname(__DIR__, 2) . '/storage') . '/logs';
            $dir = $section === null ? $root : $root . '/' . $section;
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            @file_put_contents($dir . '/' . date('Y-m-d') . '.log', $text, FILE_APPEND | LOCK_EX);
            self::rotate($root);
        } catch (Throwable $x) {
            // بی‌صدا
        } finally {
            self::$writing = false;
        }
    }

    /** روزی یک‌بار: حذف فایل‌های لاگ تاریخ‌دار قدیمی‌تر از LOG_RETENTION_DAYS (پوشه‌ی اصلی و پوشه‌های بخش) */
    private static function rotate(string $root): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $days = (int) env('LOG_RETENTION_DAYS', 30);
        $flag = $root . '/.cleaned';
        $today = date('Y-m-d');
        if ($days <= 0 || @file_get_contents($flag) === $today) {
            return;
        }
        @file_put_contents($flag, $today, LOCK_EX);
        $cut = date('Y-m-d', strtotime('-' . $days . ' days'));
        foreach (array_merge(glob($root . '/*.log') ?: [], glob($root . '/*/*.log') ?: []) as $f) {
            if (preg_match('/(\d{4}-\d{2}-\d{2})\.log$/', $f, $m) && $m[1] < $cut) {
                @unlink($f);
            }
        }
    }

    public static function retentionDays(): int
    {
        return (int) env('LOG_RETENTION_DAYS', 30);
    }
}
