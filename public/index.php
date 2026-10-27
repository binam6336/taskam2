<?php
/**
 * Front controller + پیش‌بررسی محیط.
 * (عمداً با سینتکس قدیمی‌پسند نوشته شده تا حتی روی PHP قدیمی، به‌جای صفحه‌ی خالی ۵۰۰، پیام روشن نشان بدهد.)
 */
$root = dirname(__DIR__);
$problems = array();

if (PHP_VERSION_ID < 70400) {
    $problems[] = 'نسخه‌ی PHP سرور ' . PHP_VERSION . ' است؛ این پروژه به PHP 7.4 یا بالاتر نیاز دارد (پیشنهادی 8.2). از پنل هاست (Select PHP Version) نسخه را عوض کنید.';
}
foreach (array('pdo_mysql', 'mbstring', 'json', 'session', 'ctype') as $ext) {
    if (!extension_loaded($ext)) {
        $problems[] = 'افزونه‌ی PHP «' . $ext . '» فعال نیست؛ از پنل هاست فعالش کنید.';
    }
}
if (!is_file($root . '/.env')) {
    if (is_file($root . '/.env.txt')) {
        $problems[] = 'فایل با نام «.env.txt» ساخته شده؛ پسوند .txt را حذف کنید تا نام فقط «.env» باشد.';
    } elseif (is_file($root . '/.env.example')) {
        $problems[] = 'فایل «.env» پیدا نشد. فایل .env.example را کپی کرده و نام کپی را دقیقاً «.env» بگذارید (کنار پوشه‌ی app، نه داخل public).';
    } else {
        $problems[] = 'فایل «.env» پیدا نشد.';
    }
}
$envRaw = is_file($root . '/.env') ? (string) @file_get_contents($root . '/.env') : '';
if ($envRaw !== '' && preg_match('/^\s*(?:ENCODE_PASSWORD|encode_password)\s*=\s*(.*)$/mi', $envRaw, $mm)) {
    $pw = trim(preg_replace('/\s+#.*$/', '', trim($mm[1])), " \t\"'");
    if ($pw !== '') {
        if (strlen($pw) < 12) {
            $problems[] = 'ENCODE_PASSWORD در .env کمتر از ۱۲ نویسه است؛ برای جلوگیری از ذخیره‌ی پیام‌ها به‌صورت متن‌ساده، برنامه اجرا نمی‌شود. رمز طولانی‌تری بگذارید.';
        }
        if (!extension_loaded('openssl')) {
            $problems[] = 'برای رمزگذاری پیام‌ها افزونه‌ی PHP «openssl» لازم است؛ از پنل هاست فعالش کنید.';
        }
    }
}
foreach (array('storage/cache', 'storage/logs', 'storage/uploads') as $dir) {
    if (!is_dir($root . '/' . $dir) || !is_writable($root . '/' . $dir)) {
        $problems[] = 'پوشه‌ی «' . $dir . '» وجود ندارد یا قابل نوشتن نیست (دسترسی 755 یا 775 بدهید).';
    }
}

if ($problems) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>راه‌اندازی ناقص</title>'
        . '<body style="font-family:Tahoma,sans-serif;background:#060908;color:#e9f3ee;max-width:720px;margin:6vh auto;padding:0 1rem;line-height:2">'
        . '<h2 style="color:#4ade80">⚠ پیش‌نیازهای راه‌اندازی کامل نیست</h2><ul>';
    foreach ($problems as $p) {
        echo '<li>' . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul><p style="color:#8fa89b">بعد از رفع، صفحه را رفرش کنید.</p></body></html>';
    exit;
}

// خطای مرگبار (Fatal/Parse) را همیشه لاگ کن؛ در حالت APP_DEBUG=true روی صفحه هم نشان بده
register_shutdown_function(function () use ($root) {
    $e = error_get_last();
    if (!$e || !in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        return;
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line'] . "\n";
    @file_put_contents($root . '/storage/logs/fatal-' . date('Y-m-d') . '.log', $line, FILE_APPEND);
    $env = @file_get_contents($root . '/.env');
    if ($env && preg_match('/^\s*APP_DEBUG\s*=\s*true/mi', $env)) {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=utf-8');
        }
        echo "FATAL: " . $line;
    }
});

require $root . '/app/bootstrap.php';

App\Core\App::run();
