<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Jalali;
use App\Core\Session;

/* ───── polyfill برای PHP 7.4 ───── */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

/** مقدار متغیر محیطی. */
function env(string $key, $default = null)
{
    return Env::get($key, $default);
}

/** escape برای HTML. */
function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** APP_URL نرمال‌شده: اگر کاربر بدون http(s):// نوشته بود، https:// اضافه می‌شود. */
function app_url_config(): string
{
    $cfg = trim((string) env('APP_URL', ''));
    if ($cfg === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $cfg)) {
        $cfg = 'https://' . ltrim($cfg, '/');
    }
    return rtrim($cfg, '/');
}

/** مسیر پایه‌ی اپلیکیشن (بدون اسلش انتهایی)؛ مثل /tapin/task یا خالی. */
/**
 * آدرس پایه‌ی برنامه با پیشوند پنجره‌ی ورود به پنل کاربر (/as/<توکن>) در همان تب؛ همه‌ی لینک‌ها و درخواست‌های آن تب در همان پنجره می‌مانند.
 * برای فایل‌های ثابت (asset)، لینک‌های اشتراکی (full_url) و پنل مدیر از app_base() استفاده کنید.
 */
function base_url(): string
{
    return app_base() . \App\Core\Impersonation::prefix();
}

/** آدرس پایه‌ی خود برنامه (بدون پیشوند پنجره‌ی ورود به پنل کاربر) */
function app_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $cfg = app_url_config();
    if ($cfg !== '') {
        $p = parse_url($cfg, PHP_URL_PATH);
        return $base = rtrim(is_string($p) ? $p : '', '/');
    }
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    if (str_ends_with($dir, '/public')) {
        $dir = substr($dir, 0, -7);
    }
    return $base = $dir;
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

/** URL کامل با دامنه (برای لینک‌های اشتراکی). */
function full_url(string $path = ''): string
{
    $cfg = app_url_config();
    if ($cfg !== '') {
        $p = parse_url($cfg);
        $origin = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? 'localhost') . (isset($p['port']) ? ':' . $p['port'] : '');
        return $origin . app_base() . '/' . ltrim($path, '/');     // لینک اشتراکی هرگز پیشوند پنجره‌ی ورود به پنل کاربر را ندارد
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (\App\Core\Request::fromTrustedProxy() && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    // هدر Host از کاربر می‌آید؛ فقط نام میزبان/پورت معتبر پذیرفته می‌شود (جلوگیری از تزریق Host در لینک‌ها). APP_URL را در .env تنظیم کنید.
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9-]{1,63}(?::\d{1,5})?$|^\[[0-9a-f:.]+\](?::\d{1,5})?$/i', $host)) {
        $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
    }
    return ($https ? 'https' : 'http') . '://' . $host . app_base() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return app_base() . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function old(string $key, $default = '')
{
    $old = Session::get('_old', []);
    return $old[$key] ?? $default;
}

function auth_user(): ?array
{
    return Auth::user();
}

function jdate(?string $dt, bool $time = true, bool $weekday = false): string
{
    return Jalali::format($dt, $time, $weekday);
}

function time_ago(?string $dt): string
{
    return Jalali::ago($dt);
}

/** تبدیل ارقام لاتین به فارسی (برای نمایش). */
function fa_num($v): string
{
    return strtr((string) $v, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

/** JSON امن برای قرار دادن در HTML (data-attribute یا script type=application/json). */
function json_html($data): string
{
    if (is_array($data)) {
        try {
            $data = \App\Core\Pid::out($data);     // کلیدهای *_id → شناسه‌ی عمومی (v5.0)
        } catch (\Throwable $e) {
        }
    }
    $j = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_PARTIAL_OUTPUT_ON_ERROR);
    return str_replace(["\xe2\x80\xa8", "\xe2\x80\xa9"], ['\u2028', '\u2029'], (string) $j);
}

function avatar_url(?string $file): ?string
{
    if ($file === null || $file === '' || !preg_match('/^[A-Za-z0-9_.\-]+$/', basename($file))) {
        return null;
    }
    return url('files/avatars/' . rawurlencode(basename($file)));
}

function project_image_url(?string $file): ?string
{
    if ($file === null || $file === '' || !preg_match('/^[A-Za-z0-9_.\-]+$/', basename($file))) {
        return null;
    }
    return url('files/projects/' . rawurlencode(basename($file)));
}

function full_name(array $u, string $prefix = ''): string
{
    $n = trim(((string) ($u[$prefix . 'first_name'] ?? '')) . ' ' . ((string) ($u[$prefix . 'last_name'] ?? '')));
    return $n !== '' ? $n : (string) ($u[$prefix . 'mobile'] ?? 'کاربر');
}

function initial(string $name): string
{
    $name = trim($name);
    return $name === '' ? '؟' : mb_substr($name, 0, 1, 'UTF-8');
}

function human_size($b): string
{
    $b = (int) $b;
    if ($b < 1024) {
        return fa_num($b) . ' بایت';
    }
    if ($b < 1048576) {
        return fa_num(round($b / 1024, 1)) . ' کیلوبایت';
    }
    return fa_num(round($b / 1048576, 2)) . ' مگابایت';
}

function file_icon(?string $ext): string
{
    $e = strtolower((string) $ext);
    if ($e === 'pdf') {
        return 'fa-file-pdf';
    }
    if (in_array($e, ['xls', 'xlsx', 'csv'], true)) {
        return 'fa-file-excel';
    }
    if (in_array($e, ['doc', 'docx', 'rtf', 'txt'], true)) {
        return 'fa-file-word';
    }
    if (in_array($e, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
        return 'fa-file-image';
    }
    if (in_array($e, ['zip', 'rar', '7z'], true)) {
        return 'fa-file-zipper';
    }
    return 'fa-file';
}

function duration_fa(float $hours): string
{
    if ($hours <= 0) {
        return '—';
    }
    if ($hours < 1) {
        $m = (int) round($hours * 60);
        return $m < 1 ? 'کمتر از ۱ دقیقه' : fa_num($m) . ' دقیقه';
    }
    if ($hours < 24) {
        $h = (int) floor($hours);
        $m = (int) round(($hours - $h) * 60);
        return $m === 0 ? fa_num($h) . ' ساعت' : fa_num($h) . ' ساعت و ' . fa_num($m) . ' دقیقه';
    }
    $d = (int) floor($hours / 24);
    $h = (int) floor($hours - $d * 24);
    return fa_num($d) . ' روز' . ($h > 0 ? ' و ' . fa_num($h) . ' ساعت' : '');
}

/** رنگ‌های ثابت نشان؛ پارامتر تم فقط برای سازگاری فراخوان‌های قدیمی پذیرفته می‌شود. */
function logo_palette(string $theme): array
{
    return ['#A78BFA', '#6D4AFF', '#120E1E', '#120E1E', '#FCFBFF', 1];
}

/** نسخه محتوایی برند؛ مستقل از تم و زمان کپی فایل‌ها روی هاست. */
function brand_version(): string
{
    static $version = null;
    if ($version === null) {
        $body = '';
        foreach (['karlix-favicon.svg', 'karlix-fa.svg', 'karlix-en.svg'] as $file) {
            $body .= (string) file_get_contents(BASE_PATH . '/public/assets/img/brand/' . $file);
        }
        $version = substr(hash('sha256', $body), 0, 16);
    }
    return $version;
}

/** نشانی نسخه‌دار آیکن ثابت بر پایه مسیر نصب و بدون وابستگی به دامنه یا تم. */
function brand_icon_url(string $file): string
{
    return (app_base() . '/assets/img/' . $file) . '?brand=' . brand_version();
}

/** نام برنامه (v7.5: کارلیکس). مقدار قدیمی APP_NAME («تسکام») نادیده گرفته می‌شود. */
function app_name(): string
{
    $n = trim((string) env('APP_NAME', ''));
    return $n === '' || preg_match('/^(تسکام|taskam)$/iu', $n) ? 'کارلیکس' : $n;
}

/** نشان ثابت SVG از فاوآیکن پیوست‌شده؛ تم رنگ آن را تغییر نمی‌دهد. */
function logo_svg(string $uid = '', ?string $theme = null, bool $bleed = false): string
{
    static $body = null;
    if ($body === null) {
        $raw = (string) file_get_contents(BASE_PATH . '/public/assets/img/brand/karlix-favicon.svg');
        $body = preg_replace('~^.*?<svg\b[^>]*>|</svg>\s*$~s', '', $raw);
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="100%" height="100%" aria-hidden="true" focusable="false">'
        . ($bleed ? '<rect width="32" height="32" fill="#120E1E"/><g transform="translate(3.2 3.2) scale(.8)">' : '')
        . $body . ($bleed ? '</g>' : '') . '</svg>';
}

/** لوگوی فارسی یا انگلیسی با همان رنگ‌ها و نسبت ابعاد فایل اصلی. */
function brand_lockup_svg(string $lang = 'fa', string $label = ''): string
{
    $lang = $lang === 'en' ? 'en' : 'fa';
    return '<img class="brand-wordmark" src="' . e(asset('img/brand/karlix-' . $lang . '.svg') . '&brand=' . brand_version())
        . '" width="520" height="176" alt="' . e($label !== '' ? $label : ($lang === 'en' ? 'Karlix' : 'کارلیکس')) . '">';
}

/** فاوآیکن واحد در تمام تم‌ها. */
function favicon_uri(string $theme = THEME_DEFAULT): string
{
    return app_base() . '/assets/img/brand/karlix-favicon.svg?brand=' . brand_version();
}

/** آیکن‌ها دیگر پسوند تم ندارند؛ فراخوان قدیمی با همان امضا حفظ می‌شود. */
function icon_suffix(string $theme): string
{
    return '';
}

/** رنگ اولویت درصدی: سبز (کم) ← زرد (متوسط) ← قرمز (زیاد) */
function priority_color(int $percent): string
{
    $p = max(0, min(100, $percent));
    $hue = $p <= 50 ? 142 - ($p / 50) * 97 : 45 - (($p - 50) / 50) * 45;
    return 'hsl(' . (int) round($hue) . ', 85%, 60%)';
}

/** نشان اولویت تسک: درصدی (اگر نوع اولویت پروژه درصدی باشد) یا کم/متوسط/زیاد */
function priority_badge(array $t): string
{
    if (($t['project_priority_type'] ?? 'category') === 'percent') {
        $p = isset($t['priority_percent']) ? (int) $t['priority_percent'] : (\App\Services\Priority::PERCENT[$t['priority']] ?? 50);
        return '<span class="badge prio-pct" data-css="--pc:' . priority_color($p) . '" title="اولویت ' . fa_num($p) . '٪"><i class="fa-solid fa-signal"></i> ' . fa_num($p) . '٪</span>';
    }
    $map = ['low' => ['کم', 'green'], 'medium' => ['متوسط', 'yellow'], 'high' => ['زیاد', 'red']];
    [$label, $tone] = $map[$t['priority']] ?? $map['medium'];
    return '<span class="badge ' . $tone . '">' . $label . '</span>';
}

/** تم‌های تعریف‌شده (config/themes.php) */
function themes(): array
{
    static $t = null;
    return $t ?? ($t = require BASE_PATH . '/config/themes.php');
}

/** تم پیش‌فرض و نام کوکی تم (v7.0: تم روی هر دستگاه جدا، در کوکی؛ دیگر در دیتابیس ذخیره نمی‌شود) */
const THEME_DEFAULT = 'violet-dark';       // پیش‌فرض دستگاهی که هنوز تم انتخاب نکرده است
// v7.6: نام تازه‌ی کوکی تا همه‌ی دستگاه‌ها یک بار با تم پیش‌فرض شروع کنند (کوکی قبلی tk_theme دیگر خوانده نمی‌شود)
const THEME_COOKIE = 'kx_theme';

/** کلید معتبر تم؛ کلیدهای قدیمی (dark / sage) به معادل تازه‌شان */
function theme_key(?string $t): string
{
    $t = ['dark' => 'green-dark', 'sage' => 'green-light'][(string) $t] ?? (string) $t;
    return isset(themes()[$t]) ? $t : THEME_DEFAULT;
}

/** حالت تم: light | dark */
function theme_mode(string $theme): string
{
    return themes()[theme_key($theme)]['mode'];
}

/** صفت‌های تم روی <html>: data-theme و data-mode (light|dark) */
function theme_attrs(?array $u = null): string
{
    $t = user_theme($u);
    return 'data-theme="' . e($t) . '" data-mode="' . e(theme_mode($t)) . '"';
}

/** تم این دستگاه از کوکی؛ بدون کوکی = THEME_DEFAULT (بنفش تیره). users.theme از v7.3 اصلاً خوانده نمی‌شود. */
function user_theme(?array $u = null): string
{
    return theme_key(isset($_COOKIE[THEME_COOKIE]) ? (string) $_COOKIE[THEME_COOKIE] : null);
}

/** متن placeholder یک فیلد (قابل ویرایش در «محتوا ← Placeholder» پنل ادمین؛ config/placeholder_texts.php) */
function ph(string $id): string
{
    return \App\Core\PlaceholderText::get($id);
}

/** نمایش بخشی از موبایل (برای جاهایی که کاربر هنوز رضایت به دیده‌شدن شماره‌اش نداده): 0912***4567 */
function mask_mobile(?string $m): ?string
{
    $m = (string) $m;
    return $m === '' ? null : (strlen($m) > 7 ? substr($m, 0, 4) . '***' . substr($m, -4) : '***');
}

/** نام کوتاه دستگاه از User-Agent برای «نشست‌های فعال» (مثلاً «Chrome روی Android») */
function device_name(?string $ua): string
{
    $ua = (string) $ua;
    $b = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/OPR\/|Opera/', $ua) ? 'Opera' : (preg_match('/Firefox\//', $ua) ? 'Firefox'
        : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Safari\//', $ua) ? 'Safari' : 'مرورگر'))));
    $o = preg_match('/Android/', $ua) ? 'Android' : (preg_match('/iPhone|iPad|iOS/', $ua) ? 'iOS' : (preg_match('/Windows/', $ua) ? 'Windows'
        : (preg_match('/Mac OS X|Macintosh/', $ua) ? 'macOS' : (preg_match('/Linux/', $ua) ? 'Linux' : ''))));
    return $o !== '' ? $b . ' روی ' . $o : $b;
}

/**
 * شناسه‌ی عمومی برای خروجی HTML (v5.0): pid('tasks', $row) یا pid('tasks', $id) — هرگز id عددی دیتابیس در صفحه چاپ نشود.
 * ردیف با ستون pid مستقیم همان را می‌دهد؛ وگرنه از روی id (با کش). ناموجود = رشته‌ی خالی.
 */
function pid(string $table, $rowOrId): string
{
    return (string) (is_array($rowOrId) ? \App\Core\Pid::row($table, $rowOrId) : \App\Core\Pid::of($table, $rowOrId));
}
