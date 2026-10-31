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

\App\Core\Schema::ensure();
echo "Schema " . \App\Core\Schema::VERSION . ": " . (is_file(STORAGE_PATH . '/cache/schema-' . \App\Core\Schema::VERSION) ? "OK" : "ناقص — لاگ storage/logs را ببینید") . "\n";
