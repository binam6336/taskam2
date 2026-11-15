-- ============================================================================
--  v7.7.0 — KPI (گزارش ممیزی آمار DATA-002): اولین تحویل و تعداد برگشت برای اصلاح هر تسک.
--  برگرداندن تسک به «بازبینی مسئول» دیگر تحویل دیرهنگام را از امتیاز مسئول پاک نمی‌کند.
--  اگر اجرا نشود، برنامه خودش ستون‌ها را اضافه و مقدار قبلی‌ها را از task_events پر می‌کند (Schema::COLUMNS).
-- ============================================================================
ALTER TABLE `tasks`
  ADD COLUMN `first_delivered_at` TIMESTAMP NULL DEFAULT NULL AFTER `approved_by`,
  ADD COLUMN `rework_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `first_delivered_at`;

UPDATE `tasks` t SET
  t.`first_delivered_at` = COALESCE((SELECT MIN(e.`created_at`) FROM `task_events` e WHERE e.`task_id` = t.`id` AND e.`type` = 'board'
      AND (e.`data` LIKE '%"to":"review"%' OR e.`data` LIKE '%"to":"done"%')), t.`delivered_at`),
  t.`rework_count` = (SELECT COUNT(*) FROM `task_events` e WHERE e.`task_id` = t.`id` AND e.`type` = 'board' AND e.`data` LIKE '%"to":"rework"%');
