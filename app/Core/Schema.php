<?php
declare(strict_types=1);

namespace App\Core;

/**
 * تغییرات افزایشی دیتابیس که اگر migration روی سرور اجرا نشده باشد، خودکار اعمال می‌شوند.
 * فقط ستون اضافه می‌کند؛ بعد از موفقیت یک فایل نشانه در storage/cache می‌نویسد تا بررسی تکرار نشود.
 */
final class Schema
{
    private const VERSION = '009';

    public static function ensure(): void
    {
        $flag = STORAGE_PATH . '/cache/schema-' . self::VERSION;
        if (is_file($flag)) {
            return;
        }
        try {
            // 009: دسترسی تسک‌ها به ازای هر پروژه
            $has = (int) DB::value("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'project_members' AND column_name = 'task_permissions'");
            if ($has === 0) {
                DB::run('ALTER TABLE `project_members` ADD COLUMN `task_permissions` TEXT NULL DEFAULT NULL AFTER `permissions`');
            }
            @file_put_contents($flag, date('c'));
        } catch (\Throwable $e) {
            error_log('schema 009: ' . $e->getMessage() . ' — database/migrations/009_project_task_permissions.sql را اجرا کنید.');
        }
    }
}
