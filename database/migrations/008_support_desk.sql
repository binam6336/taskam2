-- ============================================================================
--  میز پشتیبانی (v2.4.0): وضعیت «در حال انجام» برای تیکت و پیوست فایل در پیام‌ها.
--  روی دیتابیس موجود اجرا کنید. اگر اجرا نشود، برنامه در اولین باز شدن صفحه‌ی تیکت‌ها
--  (در صورت داشتن مجوز ALTER) همین تغییرات را خودش اعمال می‌کند.
-- ============================================================================
ALTER TABLE `support_tickets`
  MODIFY `status` ENUM('open','in_progress','answered','closed') NOT NULL DEFAULT 'open';

ALTER TABLE `support_messages`
  ADD COLUMN `attachment`      VARCHAR(255) NULL DEFAULT NULL COMMENT 'نام تصادفی فایل در storage/uploads/support_files' AFTER `message`,
  ADD COLUMN `attachment_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'نام اصلی فایل' AFTER `attachment`,
  ADD COLUMN `attachment_size` INT UNSIGNED NULL DEFAULT NULL AFTER `attachment_name`,
  ADD KEY `idx_sm_attachment` (`attachment`);
