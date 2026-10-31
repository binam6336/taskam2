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
    public const VERSION = '018';

    /** [جدول، ستون، DDL افزودن، کوئری پس از افزودن (اختیاری)] */
    private const COLUMNS = [
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
    ];

    /** جدول‌های جدید: [نام، فایل migration] (۰۰۷ و ۰۰۸ را Push و SupportModel خودشان بررسی می‌کنند) */
    private const TABLES = [
        ['chat_history_clears', '006_chat_forward_and_clear.sql'],
        ['content_texts', '015_content_texts.sql'],
        ['task_events', '017_task_events.sql'],
    ];

    public static function ensure(): void
    {
        $flag = STORAGE_PATH . '/cache/schema-' . self::VERSION;
        if (is_file($flag)) {
            return;
        }
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
                } catch (\Throwable $e) {
                    $ok = false;
                    error_log('schema: ' . $table . '.' . $col . ' — ' . $e->getMessage());
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
                    } catch (\Throwable $e) {
                        $ok = false;
                        error_log('schema: ' . $table . ' — ' . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            $ok = false;
            error_log('schema: ' . $e->getMessage());
        }
        if ($ok) {
            @file_put_contents($flag, date('c'));
        }
    }
}
