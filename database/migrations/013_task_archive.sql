-- ============================================================================
--  آرشیو برد تیمی (v3.6.0)
--  تسک انجام‌شده‌ی آرشیوشده: archived_at مقدار دارد (board_status همچنان done و is_completed = 1).
--  اگر اجرا نشود، برنامه در اولین استفاده (در صورت داشتن مجوز ALTER) خودش آن را اضافه می‌کند.
-- ============================================================================
ALTER TABLE `tasks`
  ADD COLUMN `archived_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'زمان انتقال به آرشیو برد' AFTER `board_moved_at`,
  ADD KEY `idx_tasks_archived` (`archived_at`);
