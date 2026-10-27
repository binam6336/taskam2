<?php
declare(strict_types=1);

/**
 * ساخت مدیر اولیه (فقط CLI):
 *   php bin/create-admin.php 09120000000 "نام" "نام‌خانوادگی"
 * رمز عبور به‌صورت مخفی (یا از متغیر محیطی ADMIN_PASSWORD) دریافت می‌شود.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\DB;
use App\Core\Validate;

$mobile = Validate::mobile((string) ($argv[1] ?? ''));
$first = (string) ($argv[2] ?? 'مدیر');
$last = (string) ($argv[3] ?? 'سیستم');
if ($mobile === null) {
    fwrite(STDERR, "استفاده: php bin/create-admin.php 09xxxxxxxxx [نام] [نام‌خانوادگی]\n");
    exit(1);
}
$pass = getenv('ADMIN_PASSWORD') ?: '';
if ($pass === '') {
    fwrite(STDOUT, 'رمز عبور (حداقل ۸ نویسه، شامل حرف و عدد): ');
    if (stripos(PHP_OS, 'WIN') !== 0) {
        @system('stty -echo');
    }
    $pass = trim((string) fgets(STDIN));
    if (stripos(PHP_OS, 'WIN') !== 0) {
        @system('stty echo');
    }
    fwrite(STDOUT, "\n");
}
if ($err = Validate::passwordError($pass)) {
    fwrite(STDERR, $err . "\n");
    exit(1);
}
if (DB::value('SELECT 1 FROM users WHERE mobile = ?', [$mobile])) {
    fwrite(STDERR, "این شماره قبلاً ثبت شده است.\n");
    exit(1);
}
$id = Auth::register(['mobile' => $mobile, 'first_name' => $first, 'last_name' => $last, 'email' => '', 'password' => $pass, 'role' => 'admin', 'status' => 'active']);
fwrite(STDOUT, "مدیر با شناسه‌ی $id ساخته شد.\n");
