-- ============================================================================
--  v4.1.0 — تیک «انجام شد» برای همه = تحویل برای «بررسی سرپرست» (حتی مدیر کل/سرپرست)؛ تکمیل فقط با تأیید در بررسی.
--  delivered_at/delivered_by: آخرین تحویل تسک برای بررسی (زمان انجام مسئول در KPI تا همین لحظه حساب می‌شود)
--  approved_by: کسی که در «بررسی سرپرست» تأیید و تکمیل کرد (KPI «میانگین زمان تأیید» سرپرست = completed_at − delivered_at)
--  اگر اجرا نشود، برنامه یک بار خودش آن را اجرا می‌کند (SCHEMA_AUTO).
-- ============================================================================
ALTER TABLE `tasks` ADD COLUMN `delivered_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'آخرین تحویل برای بررسی سرپرست' AFTER `completed_at`, ADD COLUMN `delivered_by` INT UNSIGNED NULL DEFAULT NULL AFTER `delivered_at`, ADD COLUMN `approved_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'تأییدکننده در بررسی سرپرست' AFTER `delivered_by`;

UPDATE `tasks` SET `delivered_at` = `board_moved_at` WHERE `board_status` = 'review' AND `delivered_at` IS NULL;
UPDATE `tasks` SET `delivered_at` = `completed_at` WHERE `is_completed` = 1 AND `delivered_at` IS NULL;
