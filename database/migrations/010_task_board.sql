-- ============================================================================
--  برد تیمی (کانبان) — v3.1.0
--  هر تسک یک وضعیت کانبان دارد: backlog، todo، in_progress، blocked، review، done.
--  done با is_completed همگام است (تکمیل تسک = done و برعکس).
--  اگر اجرا نشود، برنامه در اولین استفاده (در صورت داشتن مجوز ALTER) خودش آن را اضافه می‌کند.
-- ============================================================================
ALTER TABLE `tasks`
  ADD COLUMN `board_status` ENUM('backlog','todo','in_progress','blocked','review','done') NOT NULL DEFAULT 'todo'
    COMMENT 'وضعیت در برد تیمی' AFTER `is_completed`,
  ADD COLUMN `blocked_reason` VARCHAR(255) NULL DEFAULT NULL COMMENT 'علت توقف (وضعیت blocked)' AFTER `board_status`,
  ADD COLUMN `board_moved_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'آخرین جابه‌جایی در برد' AFTER `blocked_reason`;

UPDATE `tasks` SET `board_status` = 'done' WHERE `is_completed` = 1;
