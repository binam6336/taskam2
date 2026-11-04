<?php
declare(strict_types=1);

/**
 * اعمال تغییرات افزایشی دیتابیس (همان Schema::ensure) از خط فرمان — فقط CLI:
 *   php bin/migrate.php
 * وقتی SCHEMA_AUTO=false است، برنامه در درخواست‌های وب ALTER/CREATE اجرا نمی‌کند و کاربر DB آن
 * فقط SELECT/INSERT/UPDATE/DELETE لازم دارد؛ بعد از هر به‌روزرسانی این اسکریپت را با کاربری که ALTER/CREATE دارد اجرا کنید
 * (یا فایل‌های database/migrations را دستی در phpMyAdmin اجرا کنید).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

// --force: بدون توجه به فایل نشانه‌ی storage/cache/schema-NNN (مثلاً وقتی نشانه هست ولی ستون‌ها در دیتابیس نیستند)
$force = in_array('--force', $argv, true) || \App\Core\Schema::missing() !== [];
echo 'دیتابیس: ' . \App\Core\DB::value('SELECT DATABASE()') . ' — نسخه‌ی تغییرات: ' . \App\Core\Schema::VERSION . "\n";
\App\Core\Schema::ensure($force, static function (string $m): void {
    echo $m . "\n";
});
$miss = \App\Core\Schema::missing();
if ($miss) {
    echo "ناقص — هنوز در دیتابیس نیست: " . implode('، ', $miss) . "\n";
    echo "معمولاً یعنی کاربر دیتابیس مجوز ALTER/CREATE/INDEX ندارد؛ با کاربر دارای این مجوزها اجرا کنید یا فایل‌های database/migrations را در phpMyAdmin اجرا کنید.\n";
    exit(1);
}
echo "OK — همه‌ی تغییرات دیتابیس اعمال شده است.\n";
