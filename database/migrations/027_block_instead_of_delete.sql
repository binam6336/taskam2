-- ============================================================================
--  v4.0.0 — کاربر دیگر چیزی را حذف نمی‌کند؛ «حذف» تسک، پروژه، لیبل و درخواست تماس به «مسدود کردن» تبدیل شد.
--  ردیف مسدودشده در دیتابیس می‌ماند ولی از همه‌ی فهرست‌ها کنار می‌رود (blocked_at) و از پنجره‌ی «مسدودشده‌ها» برمی‌گردد.
--  ستون «مسدودشده»ی بورد هم برداشته شد: تسک‌هایی که در آن بودند به «در حال انجام» برمی‌گردند،
--  و دسترسی «حذف تسک» اعضای پروژه (task_permissions: delete) به «مسدود کردن تسک» (block) تبدیل می‌شود.
--  اگر اجرا نشود، برنامه یک بار خودش آن را اجرا می‌کند (SCHEMA_AUTO).
-- ============================================================================
ALTER TABLE `tasks` ADD COLUMN `blocked_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'مسدودشده توسط کاربر (به‌جای حذف)' AFTER `archived_at`, ADD COLUMN `blocked_by` INT UNSIGNED NULL DEFAULT NULL AFTER `blocked_at`, ADD KEY `idx_tasks_blocked` (`blocked_at`);
ALTER TABLE `projects` ADD COLUMN `blocked_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'مسدودشده توسط سازنده (به‌جای حذف)' AFTER `status`, ADD COLUMN `blocked_by` INT UNSIGNED NULL DEFAULT NULL AFTER `blocked_at`;
ALTER TABLE `subjects` ADD COLUMN `blocked_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'لیبل مسدودشده (به‌جای حذف)' AFTER `color`, ADD COLUMN `blocked_by` INT UNSIGNED NULL DEFAULT NULL AFTER `blocked_at`;
ALTER TABLE `call_requests` ADD COLUMN `blocked_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'درخواست مسدودشده (به‌جای حذف)' AFTER `completed_at`, ADD COLUMN `blocked_by` INT UNSIGNED NULL DEFAULT NULL AFTER `blocked_at`;

UPDATE `tasks` SET `board_status` = 'in_progress', `board_moved_at` = NOW() WHERE `board_status` = 'blocked';
UPDATE `project_members` SET `task_permissions` = REPLACE(`task_permissions`, '"delete"', '"block"') WHERE `task_permissions` LIKE '%"delete"%';
UPDATE `project_members` SET `board_permissions` = REPLACE(REPLACE(REPLACE(REPLACE(`board_permissions`, ',"block"', ''), '"block",', ''), ',"unblock"', ''), '"unblock",', '') WHERE `board_permissions` LIKE '%block%';
UPDATE `users` SET `board_order` = REPLACE(REPLACE(`board_order`, ',blocked', ''), 'blocked,', '') WHERE `board_order` LIKE '%blocked%';
