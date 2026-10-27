<?php
/**
 * صفحه‌ی عیب‌یابی نصب — فقط وقتی APP_DEBUG=true باشد کار می‌کند.
 * آدرس: /check.php   (بعد از رفع مشکل، این فایل را حذف کنید.)
 */
$root = dirname(__DIR__);
$env = array();
if (is_file($root . '/.env')) {
    foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $l = trim(str_replace("\xEF\xBB\xBF", '', $l));
        if ($l === '' || $l[0] === '#' || strpos($l, '=') === false) { continue; }
        list($k, $v) = explode('=', $l, 2);
        $v = trim($v);
        if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) { $v = trim($v, $v[0]); } else { $v = trim(preg_replace('/\s+#.*$/', '', $v)); }
        $env[trim($k)] = $v;
    }
}
header('Content-Type: text/html; charset=utf-8');
if (strtolower(isset($env['APP_DEBUG']) ? $env['APP_DEBUG'] : '') !== 'true') {
    http_response_code(404);
    exit('برای استفاده از این صفحه، در .env مقدار APP_DEBUG=true بگذارید (و بعد از رفع مشکل حذفش کنید).');
}
$rows = array();
$add = function ($label, $ok, $detail = '') use (&$rows) { $rows[] = array($label, $ok, $detail); };

$add('محیط اجرا', true, php_sapi_name() . ' | ' . (isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : ''));
$add('نسخه‌ی PHP (نیاز ۷.۴+)', PHP_VERSION_ID >= 70400, PHP_VERSION);
foreach (array('pdo_mysql', 'mbstring', 'json', 'session', 'ctype', 'fileinfo', 'gd') as $x) {
    $add('افزونه ' . $x, extension_loaded($x), in_array($x, array('fileinfo', 'gd'), true) ? 'برای آپلود لازم است' : '');
}
$add('فایل .env', is_file($root . '/.env'));
$pwv = isset($env['ENCODE_PASSWORD']) ? $env['ENCODE_PASSWORD'] : (isset($env['encode_password']) ? $env['encode_password'] : '');
$add('رمز رمزگذاری چت (ENCODE_PASSWORD)', strlen($pwv) >= 12, $pwv === '' ? 'تنظیم نشده → پیام‌ها متن‌ساده ذخیره می‌شوند' : (strlen($pwv) >= 12 ? 'تنظیم شده (' . strlen($pwv) . ' نویسه)' : 'کمتر از ۱۲ نویسه'));
$add('OpenSSL با AES-256-GCM', function_exists('openssl_get_cipher_methods') && in_array('aes-256-gcm', openssl_get_cipher_methods(), true));
foreach (array('DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS') as $k) {
    $add('مقدار ' . $k . ' در .env', isset($env[$k]) && ($k === 'DB_PASS' || $env[$k] !== ''), isset($env[$k]) && $k !== 'DB_PASS' ? $env[$k] : '');
}
foreach (array('storage/cache', 'storage/logs', 'storage/uploads') as $d) {
    $add('قابل نوشتن: ' . $d, is_dir($root . '/' . $d) && is_writable($root . '/' . $d));
}
$add('Apache mod_rewrite', function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules(), true) : true, function_exists('apache_get_modules') ? '' : 'قابل تشخیص نیست (CGI/FPM)');

$pdo = null;
try {
    $dsn = 'mysql:host=' . (isset($env['DB_HOST']) ? $env['DB_HOST'] : 'localhost') . ';port=' . (isset($env['DB_PORT']) ? $env['DB_PORT'] : 3306) . ';dbname=' . (isset($env['DB_NAME']) ? $env['DB_NAME'] : '') . ';charset=utf8mb4';
    $pdo = new PDO($dsn, isset($env['DB_USER']) ? $env['DB_USER'] : '', isset($env['DB_PASS']) ? $env['DB_PASS'] : '', array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
    $add('اتصال به دیتابیس', true);
} catch (Exception $e) {
    $add('اتصال به دیتابیس', false, $e->getMessage());
}
if ($pdo) {
    $need = array('users', 'tasks', 'subjects', 'projects', 'api_tokens', 'chat_messages', 'rate_limits', 'audit_log');
    $have = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_diff($need, $have);
    $add('جدول‌های اصلی (schema.sql اجرا شده؟)', !$missing, $missing ? 'ناموجود: ' . implode(', ', $missing) : count($have) . ' جدول');
    if (!$missing) {
        $add('تعداد مدیرها', (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() > 0, (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() . ' مدیر');
    }
}
$log = glob($root . '/storage/logs/*.log');
?>
<!DOCTYPE html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>عیب‌یابی نصب</title>
<body style="font-family:Tahoma,sans-serif;background:#060908;color:#e9f3ee;max-width:820px;margin:4vh auto;padding:0 1rem;line-height:1.9">
<h2 style="color:#4ade80">عیب‌یابی نصب تسکام</h2>
<table style="width:100%;border-collapse:collapse"><?php foreach ($rows as $r): ?>
<tr style="border-bottom:1px solid #1f2e26"><td style="padding:.5rem"><?= $r[1] ? '✅' : '❌' ?></td><td><?= htmlspecialchars($r[0]) ?></td><td style="color:#8fa89b;direction:ltr;text-align:left;font-size:.85em"><?= htmlspecialchars($r[2]) ?></td></tr>
<?php endforeach; ?></table>
<?php if ($log): ?><h3>آخرین لاگ‌ها</h3><?php foreach (array_slice($log, -2) as $f): ?><p style="color:#8fa89b"><?= htmlspecialchars(basename($f)) ?></p><pre style="background:#0a100d;padding:1rem;overflow:auto;direction:ltr;text-align:left;font-size:.8em"><?= htmlspecialchars(substr((string) file_get_contents($f), -2500)) ?></pre><?php endforeach; endif; ?>
<p style="color:#fbbf24">⚠ بعد از رفع مشکل، این فایل (public/check.php) را حذف و APP_DEBUG=false کنید.</p></body></html>
