<?php
declare(strict_types=1);

namespace App\Core;

/**
 * تغییرات افزایشی دیتابیس (migrationهای ۰۰۴ به بعد) که اگر روی سرور اجرا نشده باشند خودکار اعمال می‌شوند؛
 * تا جا انداختن یک migration دیگر به خطای ۵۰۰ ختم نشود. فقط ستون/جدول اضافه می‌کند و به داده‌ها دست نمی‌زند.
 * بعد از موفقیت یک فایل نشانه در storage/cache نوشته می‌شود تا بررسی در هر درخواست تکرار نشود.
 */
final class Schema
{
    public const VERSION = '029';

    /** [جدول، ستون، DDL افزودن، کوئری پس از افزودن (اختیاری)] */
    private const COLUMNS = [
        // 029
        ['users', 'admin_role', "ADD COLUMN `admin_role` VARCHAR(20) NULL DEFAULT NULL AFTER `admin_permissions`", null],
        // 028
        ['tasks', 'delivered_at', "ADD COLUMN `delivered_at` TIMESTAMP NULL DEFAULT NULL AFTER `completed_at`, ADD COLUMN `delivered_by` INT UNSIGNED NULL DEFAULT NULL AFTER `delivered_at`, ADD COLUMN `approved_by` INT UNSIGNED NULL DEFAULT NULL AFTER `delivered_by`", null],
        // 027
        ['tasks', 'blocked_at', "ADD COLUMN `blocked_at` TIMESTAMP NULL DEFAULT NULL AFTER `archived_at`, ADD COLUMN `blocked_by` INT UNSIGNED NULL DEFAULT NULL AFTER `blocked_at`, ADD KEY `idx_tasks_blocked` (`blocked_at`)", null],
        ['projects', 'blocked_at', "ADD COLUMN `blocked_at` TIMESTAMP NULL DEFAULT NULL AFTER `status`, ADD COLUMN `blocked_by` INT UNSIGNED NULL DEFAULT NULL AFTER `blocked_at`", null],
        ['subjects', 'blocked_at', "ADD COLUMN `blocked_at` TIMESTAMP NULL DEFAULT NULL AFTER `color`, ADD COLUMN `blocked_by` INT UNSIGNED NULL DEFAULT NULL AFTER `blocked_at`", null],
        ['call_requests', 'blocked_at', "ADD COLUMN `blocked_at` TIMESTAMP NULL DEFAULT NULL AFTER `completed_at`, ADD COLUMN `blocked_by` INT UNSIGNED NULL DEFAULT NULL AFTER `blocked_at`", null],
        // 026
        ['tasks', 'is_quick', "ADD COLUMN `is_quick` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_completed`", null],
        // 025
        ['user_sessions', 'reauth_at', "ADD COLUMN `reauth_at` INT UNSIGNED NULL DEFAULT NULL AFTER `last_activity`", null],
        // 008
        ['support_messages', 'attachment', "ADD COLUMN `attachment` VARCHAR(255) NULL DEFAULT NULL AFTER `message`, ADD COLUMN `attachment_name` VARCHAR(255) NULL DEFAULT NULL AFTER `attachment`, ADD COLUMN `attachment_size` INT UNSIGNED NULL DEFAULT NULL AFTER `attachment_name`, ADD KEY `idx_sm_attachment` (`attachment`)", null],
        // 004
        ['projects', 'priority_type', "ADD COLUMN `priority_type` ENUM('category','percent') NOT NULL DEFAULT 'category' AFTER `max_members`", null],
        ['tasks', 'priority_percent', 'ADD COLUMN `priority_percent` TINYINT UNSIGNED NULL DEFAULT NULL AFTER `priority`',
            "UPDATE `tasks` SET `priority_percent` = CASE `priority` WHEN 'low' THEN 25 WHEN 'high' THEN 75 ELSE 50 END WHERE `priority_percent` IS NULL"],
        ['users', 'task_link_access', "ADD COLUMN `task_link_access` ENUM('members','colleagues','me','all') NOT NULL DEFAULT 'members' AFTER `project_join_requires_approval`", null],
        // 005
        ['users', 'theme', "ADD COLUMN `theme` VARCHAR(20) NOT NULL DEFAULT 'dark' AFTER `quick_links`", null],
        // 006
        ['chat_messages', 'forwarded_from', 'ADD COLUMN `forwarded_from` INT UNSIGNED NULL DEFAULT NULL AFTER `attachment_type`', null],
        // 009
        ['project_members', 'task_permissions', 'ADD COLUMN `task_permissions` TEXT NULL DEFAULT NULL AFTER `permissions`', null],
        // 010
        ['tasks', 'board_status', "ADD COLUMN `board_status` ENUM('backlog','todo','in_progress','blocked','review','done') NOT NULL DEFAULT 'todo' AFTER `is_completed`",
            "UPDATE `tasks` SET `board_status` = 'done' WHERE `is_completed` = 1"],
        ['tasks', 'blocked_reason', 'ADD COLUMN `blocked_reason` VARCHAR(255) NULL DEFAULT NULL AFTER `board_status`', null],
        ['tasks', 'board_moved_at', 'ADD COLUMN `board_moved_at` TIMESTAMP NULL DEFAULT NULL AFTER `blocked_reason`', null],
        // 011
        ['project_members', 'board_permissions', 'ADD COLUMN `board_permissions` TEXT NULL DEFAULT NULL AFTER `task_permissions`', null],
        // 012
        ['project_members', 'board_view_users', 'ADD COLUMN `board_view_users` TEXT NULL DEFAULT NULL AFTER `board_permissions`', null],
        // 013
        ['tasks', 'archived_at', 'ADD COLUMN `archived_at` TIMESTAMP NULL DEFAULT NULL AFTER `board_moved_at`, ADD KEY `idx_tasks_archived` (`archived_at`)', null],
        // 014
        ['users', 'tour', "ADD COLUMN `tour` VARCHAR(20) NULL DEFAULT NULL AFTER `theme`", "UPDATE `users` SET `tour` = 'done'"],
        // 016
        ['project_members', 'board_limits', 'ADD COLUMN `board_limits` TEXT NULL DEFAULT NULL AFTER `board_view_users`', null],
        // 018
        ['api_tokens', 'scope', "ADD COLUMN `scope` ENUM('read','write') NOT NULL DEFAULT 'write' AFTER `token_prefix`", null],
        // 023
        ['users', 'board_order', 'ADD COLUMN `board_order` VARCHAR(255) NULL DEFAULT NULL AFTER `tour`', null],
    ];

    /** مقدارهای تازه‌ی ENUM: [جدول، ستون، مقداری که باید باشد، DDL] */
    private const ENUMS = [
        // 008
        ['support_tickets', 'status', 'in_progress', "MODIFY `status` ENUM('open','in_progress','answered','closed') NOT NULL DEFAULT 'open'"],
        // 021
        ['tasks', 'board_status', 'rework', "MODIFY `board_status` ENUM('backlog','todo','in_progress','blocked','review','rework','done') NOT NULL DEFAULT 'backlog'"],
    ];

    /** تغییر یک‌باره‌ی داده‌ها: [شناسه، فایل migration]؛ هر کدام فقط یک بار (فایل نشانه‌ی data-<شناسه> در storage/cache) */
    private const DATA = [
        ['022', '022_member_board_perms.sql'],
        ['025', '025_chat_requests_block_ghosts.sql'],
        ['027', '027_block_instead_of_delete.sql'],
        ['028', '028_task_delivery_review.sql'],
        ['029', '029_admin_roles.sql'],
    ];

    /** جدول‌های جدید: [نام، فایل migration] (همه‌ی DDL زمان اجرا فقط همین‌جاست و با SCHEMA_AUTO=false خاموش می‌شود) */
    private const TABLES = [
        ['chat_history_clears', '006_chat_forward_and_clear.sql'],
        ['push_subscriptions', '007_push_subscriptions.sql'],
        ['chat_requests', '025_chat_requests_block_ghosts.sql'],
        ['colleague_block_ghosts', '025_chat_requests_block_ghosts.sql'],
        ['content_texts', '015_content_texts.sql'],
        ['task_events', '017_task_events.sql'],
        ['user_sessions', '019_user_sessions.sql'],
        ['task_mentions', '020_task_mentions.sql'],
    ];

    /** تغییرات دیتابیس این نسخه هنوز کامل اعمال نشده؟ (برای مستندات پنل وقتی SCHEMA_AUTO خاموش است) */
    public static function pending(): bool
    {
        return !is_file(STORAGE_PATH . '/cache/schema-' . self::VERSION);
    }

    /** خطاهای آخرین اجرای ناموفق (برای مستندات پنل و php bin/migrate.php) */
    public static function lastErrors(): array
    {
        $f = STORAGE_PATH . '/cache/schema-errors.json';
        $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
        return is_array($d) ? $d : [];
    }

    /** ستون‌ها/جدول‌های این نسخه که در دیتابیس نیستند (بررسی واقعی، مستقل از فایل نشانه) */
    public static function missing(): array
    {
        $out = [];
        try {
            $cols = [];
            foreach (DB::all('SELECT table_name AS t, column_name AS c FROM information_schema.columns WHERE table_schema = DATABASE()') as $r) {
                $cols[strtolower((string) $r['t']) . '.' . strtolower((string) $r['c'])] = true;
            }
            foreach (self::COLUMNS as [$table, $col]) {
                if (!isset($cols[$table . '.' . $col])) {
                    $out[] = $table . '.' . $col;
                }
            }
            foreach (self::TABLES as [$table]) {
                if (!isset($cols[$table . '.id']) && (int) DB::value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]) === 0) {
                    $out[] = $table;
                }
            }
        } catch (\Throwable $e) {
            $out[] = 'information_schema: ' . $e->getMessage();
        }
        return $out;
    }

    /**
     * اعمال تغییرات افزایشی. $force: بدون توجه به فایل نشانه (php bin/migrate.php --force)؛ $report: گزارش هر مرحله (CLI).
     * خطاها هم در storage/logs و هم در storage/cache/schema-errors.json ثبت می‌شوند تا در «مستندات پنل» دیده شوند.
     */
    public static function ensure(bool $force = false, ?callable $report = null): void
    {
        $flag = STORAGE_PATH . '/cache/schema-' . self::VERSION;
        if (!$force && is_file($flag)) {
            return;
        }
        $say = $report ?? static function (string $m): void {
        };
        $errors = [];
        $fail = static function (string $what, \Throwable $e) use (&$errors, $say): void {
            $errors[] = $what . ' — ' . $e->getMessage();
            $say('✗ ' . $what . ' — ' . $e->getMessage());
            error_log('schema: ' . $what . ' — ' . $e->getMessage());
            ErrorHandler::log(new \RuntimeException('schema: ' . $what . ' — ' . $e->getMessage(), 0, $e));
        };
        $ok = true;
        try {
            $cols = [];
            foreach (DB::all('SELECT table_name AS t, column_name AS c FROM information_schema.columns WHERE table_schema = DATABASE()') as $r) {
                $cols[strtolower((string) $r['t']) . '.' . strtolower((string) $r['c'])] = true;
            }
            foreach (self::COLUMNS as [$table, $col, $ddl, $after]) {
                if (isset($cols[$table . '.' . $col])) {
                    continue;
                }
                try {
                    DB::run('ALTER TABLE `' . $table . '` ' . $ddl);
                    if ($after !== null) {
                        DB::run($after);
                    }
                    $say('✓ ستون ' . $table . '.' . $col . ' اضافه شد');
                } catch (\Throwable $e) {
                    $ok = false;
                    $fail('ستون ' . $table . '.' . $col, $e);
                }
            }
            foreach (self::ENUMS as [$table, $col, $value, $ddl]) {
                $type = (string) DB::value('SELECT column_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$table, $col]);
                if ($type === '' || strpos($type, "'" . $value . "'") !== false) {
                    continue;
                }
                try {
                    DB::run('ALTER TABLE `' . $table . '` ' . $ddl);
                    $say('✓ مقدار ' . $value . ' به ' . $table . '.' . $col . ' اضافه شد');
                } catch (\Throwable $e) {
                    $ok = false;
                    $fail('مقدار ENUM ' . $table . '.' . $col, $e);
                }
            }
            foreach (self::TABLES as [$table, $file]) {
                if ((int) DB::value('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]) > 0) {
                    continue;
                }
                $sql = (string) @file_get_contents(BASE_PATH . '/database/migrations/' . $file);
                if (preg_match('/CREATE TABLE IF NOT EXISTS `' . preg_quote($table, '/') . '`.*?;/s', $sql, $m)) {
                    try {
                        DB::run($m[0]);
                        $say('✓ جدول ' . $table . ' ساخته شد');
                    } catch (\Throwable $e) {
                        $ok = false;
                        $fail('جدول ' . $table, $e);
                    }
                }
            }
            foreach (self::DATA as [$id, $file]) {
                $dflag = STORAGE_PATH . '/cache/data-' . $id;
                if (is_file($dflag)) {
                    continue;
                }
                $sql = (string) @file_get_contents(BASE_PATH . '/database/migrations/' . $file);
                try {
                    foreach (preg_match_all('/^(UPDATE|INSERT|DELETE)\b.*?;/ms', $sql, $m) ? $m[0] : [] as $q) {
                        DB::run(rtrim($q, ';'));
                    }
                    @file_put_contents($dflag, date('c'));
                    $say('✓ اصلاح داده ' . $id . ' اجرا شد');
                } catch (\Throwable $e) {
                    $ok = false;
                    $fail('اصلاح داده ' . $id, $e);
                }
            }
        } catch (\Throwable $e) {
            $ok = false;
            $fail('بررسی دیتابیس', $e);
        }
        $ef = STORAGE_PATH . '/cache/schema-errors.json';
        if ($ok) {
            if (@file_put_contents($flag, date('c')) === false) {
                $say('! فایل نشانه ' . $flag . ' نوشته نشد (پوشه‌ی storage/cache قابل نوشتن نیست؛ بررسی در هر درخواست تکرار می‌شود)');
            }
            @unlink($ef);
        } else {
            @file_put_contents($ef, json_encode(['at' => date('c'), 'version' => self::VERSION, 'errors' => $errors], JSON_UNESCAPED_UNICODE));
        }
    }
}
