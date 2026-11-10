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

/**
 * رنگ‌های لوگوی کارلیکس برای هر تم (v7.5): [a = رنگ روشن‌تر گرادیان/خط‌ها، b = رنگ پررنگ تیک، base = زمینه‌ی کاشی، inner = داخل نقطه، glass = رنگ براقی، glass_o = شدت براقی]
 * منبع: کلید logo در config/themes.php — همین رنگ‌ها در app.css به‌صورت --logo-* برای هر تم تکرار شده‌اند (لوگوی داخل صفحه با تغییر تم فوراً عوض می‌شود).
 */
function logo_palette(string $theme): array
{
    $t = themes()[theme_key($theme)];
    return $t['logo'] + [5 => 1];
}

/** نام برنامه (v7.5: کارلیکس). مقدار قدیمی APP_NAME («تسکام») نادیده گرفته می‌شود. */
function app_name(): string
{
    $n = trim((string) env('APP_NAME', ''));
    return $n === '' || preg_match('/^(تسکام|taskam)$/iu', $n) ? 'کارلیکس' : $n;
}

/**
 * بدنه‌ی نشان کارلیکس در مختصات 0..100 (کاشی 14..86): کاشی شیشه‌ای + کمان + خطوط سرعت + تیک.
 * $theme = null: رنگ از کلاس‌های .lg-* (متغیرهای --logo-* تم فعلی صفحه)؛ وگرنه رنگ ثابت همان تم.
 */
function logo_mark(string $u, ?string $theme, bool $bleed = false): string
{
    $c = $theme !== null ? logo_palette($theme) : null;
    // ویژگی رنگ: ثابت (تم مشخص) یا کلاس (بدون style به‌خاطر CSP)
    $at = static fn(string $cls, string $prop, int $i) => $c !== null ? $prop . '="' . $c[$i] . '"' : 'class="' . $cls . '"';
    $tile = $bleed ? 'x="14" y="14" width="72" height="72"' : 'x="14" y="14" width="72" height="72" rx="22"';
    return '<defs>'
        . '<linearGradient id="' . $u . 'k" gradientUnits="userSpaceOnUse" x1="30" y1="29" x2="71" y2="71"><stop offset="0" ' . $at('lg-a', 'stop-color', 0) . '/><stop offset="1" ' . $at('lg-b', 'stop-color', 1) . '/></linearGradient>'
        . '<linearGradient id="' . $u . 'g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" ' . $at('lg-gl', 'stop-color', 4) . ' stop-opacity=".32"/><stop offset="1" ' . $at('lg-gl', 'stop-color', 4) . ' stop-opacity=".12"/></linearGradient>'
        . '<linearGradient id="' . $u . 'e" x1="0" y1="0" x2="1" y2="1"><stop offset="0" ' . $at('lg-a', 'stop-color', 0) . ' stop-opacity=".35"/><stop offset=".55" ' . $at('lg-a', 'stop-color', 0) . ' stop-opacity=".12"/><stop offset="1" ' . $at('lg-gl', 'stop-color', 4) . ' stop-opacity=".8"/></linearGradient>'
        . '</defs>'
        . '<rect ' . $tile . ' ' . $at('lg-base', 'fill', 2) . '/>'
        . '<rect ' . $tile . ' ' . $at('lg-tint', 'fill', 0) . ' fill-opacity=".10"/>'
        . '<rect ' . $tile . ' fill="url(#' . $u . 'g)" ' . ($c !== null ? 'opacity="' . $c[5] . '"' : 'class="lg-glass"') . '/>'
        . ($bleed ? '<g transform="translate(9 9) scale(.82)">' : '<rect x="14.6" y="14.6" width="70.8" height="70.8" rx="21.4" fill="none" stroke="url(#' . $u . 'e)" stroke-width="1.2"/>')
        . '<path d="M33.1 38.5 A21 21 0 1 1 33.1 61.5" fill="none" stroke="url(#' . $u . 'k)" stroke-width="3.2" stroke-linecap="round"/>'
        . '<path d="M22 44.5 H29 M18 50 H31 M22 55.5 H29" ' . $at('lg-ln', 'stroke', 0) . ' stroke-width="3.2" stroke-linecap="round" opacity=".6"/>'
        . '<path d="M43 45 L50 52 L62 39" fill="none" ' . $at('lg-ck', 'stroke', 1) . ' stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round"/>'
        . '<circle cx="50" cy="52" r="2.4" ' . ($c !== null ? 'fill="' . $c[3] . '" stroke="' . $c[1] . '"' : 'class="lg-dot"') . ' stroke-width="1.6"/>'
        . ($bleed ? '</g>' : '');
}

/**
 * لوگوی کارلیکس (SVG، فقط نشان).
 * $theme = null: رنگ از متغیرهای CSS تم فعلی صفحه (--logo-*)؛ وگرنه رنگ ثابت همان تم (فاوآیکن، آیکن PWA).
 * $bleed: نسخه‌ی تمام‌صفحه (maskable / apple-touch): زمینه بدون گوشه‌ی گرد و طرح در ناحیه‌ی امن.
 */
function logo_svg(string $uid = '', ?string $theme = null, bool $bleed = false): string
{
    static $n = 0;
    $u = $uid !== '' ? $uid : 'lg' . (++$n);
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="14 14 72 72" width="100%" height="100%" aria-hidden="true" focusable="false">'
        . logo_mark($u, $theme, $bleed) . '</svg>';
}

/**
 * لوگوی کامل (نشان + نوشته‌ی «کارلیکس» یا «Karlix») از فایل‌های public/assets/img/brand؛ رنگ نشان از تم، رنگ نوشته .lg-word.
 */
function brand_lockup_svg(string $lang = 'fa', string $label = ''): string
{
    static $paths = [], $n = 0;
    $lang = $lang === 'en' ? 'en' : 'fa';
    if (!isset($paths[$lang])) {
        $raw = (string) @file_get_contents(BASE_PATH . '/public/assets/img/brand/karlix-' . $lang . '.svg');
        $paths[$lang] = preg_match('~<path transform="([^"]+)" fill="[^"]*" d="([^"]+)"~', $raw, $m) ? [$m[1], $m[2]] : ['', ''];
    }
    [$tr, $d] = $paths[$lang];
    $label = $label !== '' ? $label : ($lang === 'en' ? 'Karlix' : 'کارلیکس');
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 180 146" width="100%" height="100%" role="img" aria-label="' . e($label) . '" focusable="false">'
        . '<g transform="translate(40 2)">' . logo_mark('bl' . (++$n), null) . '</g>'
        . ($d !== '' ? '<path transform="' . e($tr) . '" class="lg-word" d="' . e($d) . '"/>' : '')
        . '</svg>';
}

/** فاو‌آیکن تم به‌صورت data URI (بدون درخواست جداگانه به سرور). */
function favicon_uri(string $theme = THEME_DEFAULT): string
{
    return 'data:image/svg+xml,' . rawurlencode(str_replace(' aria-hidden="true" focusable="false"', '', logo_svg('fav', $theme)));
}

/** پسوند فایل آیکن‌های PNG تم (v7.5: هر تم آیکن خودش را دارد، مثل -violet-dark؛ bin/build-brand-icons.js). */
function icon_suffix(string $theme): string
{
    return '-' . theme_key($theme);
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
const THEME_DEFAULT = 'green-light';       // v7.3: دستگاه بدون کوکی (و ثبت‌نام تازه) = سبز روشن
const THEME_COOKIE = 'tk_theme';

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

/** تم این دستگاه از کوکی؛ بدون کوکی = THEME_DEFAULT (سبز روشن). users.theme از v7.3 اصلاً خوانده نمی‌شود. */
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
