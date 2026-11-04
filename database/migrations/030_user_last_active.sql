-- ============================================================================
--  v4.5.0 — «آخرین بازدید» کاربر در پنل مدیر: زمان آخرین درخواست خود کاربر به پنل (نه ورود مدیر به‌جای او).
--  حداکثر هر ۵ دقیقه یک‌بار در Authenticate به‌روز می‌شود. مقدار اولیه = آخرین ورود.
--  اگر اجرا نشود، برنامه یک بار خودش آن را اجرا می‌کند (SCHEMA_AUTO).
-- ============================================================================
ALTER TABLE `users` ADD COLUMN `last_active_at` DATETIME NULL DEFAULT NULL COMMENT 'آخرین فعالیت خود کاربر در پنل (بدون ورود مدیر به‌جای او)' AFTER `last_login_ip`;

UPDATE `users` SET `last_active_at` = `last_login_at` WHERE `last_active_at` IS NULL AND `last_login_at` IS NOT NULL;
