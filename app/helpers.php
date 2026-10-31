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
function base_url(): string
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
        return $origin . url($path);
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (\App\Core\Request::fromTrustedProxy() && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    // هدر Host از کاربر می‌آید؛ فقط نام میزبان/پورت معتبر پذیرفته می‌شود (جلوگیری از تزریق Host در لینک‌ها). APP_URL را در .env تنظیم کنید.
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9-]{1,63}(?::\d{1,5})?$|^\[[0-9a-f:.]+\](?::\d{1,5})?$/i', $host)) {
        $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
    }
    return ($https ? 'https' : 'http') . '://' . $host . url($path);
}

function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
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
 * رنگ‌های لوگو برای هر تم: [گرادیان خط ۱، گرادیان خط ۲، پس‌زمینه ۱، پس‌زمینه ۲، داخل مربع]
 * همین رنگ‌ها در app.css به‌صورت --logo-* برای هر تم تعریف شده‌اند (لوگوی داخل صفحه با تغییر تم فوراً عوض می‌شود).
 */
function logo_palette(string $theme): array
{
    $p = [
        'dark' => ['#22c55e', '#10b981', '#121816', '#0a0f0d', '#111c16'],
        'sage' => ['#7FB77E', '#4E9A5B', '#FBFAEB', '#E3E4C9', '#F7F6DC'],
    ];
    return $p[$theme] ?? $p['dark'];
}

/**
 * لوگوی تسکام (SVG).
 * $theme = null: رنگ از متغیرهای CSS تم فعلی صفحه (--logo-*) با پیش‌فرض تم تیره؛ وگرنه رنگ ثابت همان تم (فاوآیکن، آیکن PWA).
 * $bleed: نسخه‌ی تمام‌صفحه (maskable / apple-touch): پس‌زمینه بدون گوشه‌ی گرد و طرح کوچک‌تر در ناحیه‌ی امن.
 */
function logo_svg(string $uid = '', ?string $theme = null, bool $bleed = false): string
{
    static $n = 0;
    $u = $uid !== '' ? $uid : 'lg' . (++$n);
    $c = logo_palette($theme ?? 'dark');
    $vars = ['--logo-a', '--logo-b', '--logo-bg1', '--logo-bg2', '--logo-inner'];
    // رنگ i: ثابت یا var(--logo-*, پیش‌فرض)
    $col = static fn(int $i, string $prop) => $theme !== null ? $prop . '="' . $c[$i] . '"' : 'style="' . $prop . ':var(' . $vars[$i] . ',' . $c[$i] . ')"';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" width="100%" height="100%" aria-hidden="true" focusable="false">'
        . '<defs>'
        . '<linearGradient id="' . $u . 'g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" ' . $col(0, 'stop-color') . '/><stop offset="100%" ' . $col(1, 'stop-color') . '/></linearGradient>'
        . '<linearGradient id="' . $u . 'b" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" ' . $col(2, 'stop-color') . '/><stop offset="100%" ' . $col(3, 'stop-color') . '/></linearGradient>'
        . '<filter id="' . $u . 'f" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="10" result="blur"/><feComposite in="SourceGraphic" in2="blur" operator="over"/></filter>'
        . '</defs>'
        . '<rect width="400" height="400" rx="' . ($bleed ? 0 : 90) . '" fill="url(#' . $u . 'b)"/>'
        . ($bleed ? '<g transform="translate(40 40) scale(.8)">' : '')
        . '<path d="M 200 75 A 125 125 0 1 1 85 200" fill="none" stroke="url(#' . $u . 'g)" stroke-width="14" stroke-linecap="round" opacity="0.35"/>'
        . '<path d="M 85 160 L 85 200 L 125 200" fill="none" stroke="url(#' . $u . 'g)" stroke-width="14" stroke-linecap="round" stroke-linejoin="round" opacity="0.35"/>'
        . '<rect x="125" y="125" width="150" height="150" rx="40" ' . ($theme !== null ? 'fill="' . $c[4] . '"' : 'style="fill:var(--logo-inner,' . $c[4] . ')"') . ' stroke="url(#' . $u . 'g)" stroke-width="10" filter="url(#' . $u . 'f)"/>'
        . '<path d="M 160 200 L 195 235 L 245 170" fill="none" stroke="url(#' . $u . 'g)" stroke-width="16" stroke-linecap="round" stroke-linejoin="round"/>'
        . ($bleed ? '</g>' : '')
        . '</svg>';
}

/** فاو‌آیکن تم به‌صورت data URI (بدون درخواست جداگانه به سرور). */
function favicon_uri(string $theme = 'dark'): string
{
    return 'data:image/svg+xml,' . rawurlencode(str_replace(' aria-hidden="true" focusable="false"', '', logo_svg('fav', $theme)));
}

/** پسوند فایل آیکن‌های PNG تم ('' برای تیره، '-light' برای تم‌های روشن). */
function icon_suffix(string $theme): string
{
    return $theme === 'sage' ? '-light' : '';
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
        return '<span class="badge prio-pct" style="--pc:' . priority_color($p) . '" title="اولویت ' . fa_num($p) . '٪"><i class="fa-solid fa-signal"></i> ' . fa_num($p) . '٪</span>';
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

/** تم معتبر کاربر؛ اگر ستون theme هنوز با migration اضافه نشده باشد، تم پیش‌فرض. */
function user_theme(?array $u): string
{
    $t = (string) ($u['theme'] ?? 'dark');
    return isset(themes()[$t]) ? $t : 'dark';
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
