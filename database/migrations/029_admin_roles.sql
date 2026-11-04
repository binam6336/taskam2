-- ============================================================================
--  v4.4.0 — نقش‌های مدیر: super (مدیر کل)، internal (مدیر داخلی)، support (پشتیبان).
--  فعلاً همه‌ی نقش‌ها همه‌ی دسترسی‌ها را دارند (محدودسازی بعداً)؛ admin_permissions دیگر استفاده نمی‌شود.
--  مدیر اول (کمترین id) مدیر کل و بقیه‌ی مدیران موجود مدیر داخلی می‌شوند.
--  اگر اجرا نشود، برنامه یک بار خودش آن را اجرا می‌کند (SCHEMA_AUTO).
-- ============================================================================
ALTER TABLE `users` ADD COLUMN `admin_role` VARCHAR(20) NULL DEFAULT NULL COMMENT 'نقش مدیر: super | internal | support (فقط برای role = admin)' AFTER `admin_permissions`;

UPDATE `users` SET `admin_role` = 'internal' WHERE `role` = 'admin' AND `admin_role` IS NULL;
UPDATE `users` u JOIN (SELECT MIN(id) id FROM `users` WHERE `role` = 'admin') f ON f.id = u.id SET u.`admin_role` = 'super';
