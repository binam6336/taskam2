-- ============================================================================
--  حذف کامل بخش «تیکت‌های شخصی» (شماره تیکت، لینک تسک، کامنت‌ها) از دیتابیس
--  ⚠️ غیرقابل بازگشت است؛ قبلش بکاپ بگیرید.
--  جدول‌های پشتیبانی (support_tickets / support_messages) دست‌نخورده می‌مانند.
-- ============================================================================
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `ticket_comments`;
DROP TABLE IF EXISTS `tickets`;

SET FOREIGN_KEY_CHECKS = 1;

-- پاک‌کردن میان‌بر «تیکت‌ها» از دسترسی سریع کاربران
UPDATE `users`
   SET `quick_links` = REPLACE(REPLACE(REPLACE(`quick_links`, '"tickets",', ''), ',"tickets"', ''), '"tickets"', '')
 WHERE `quick_links` LIKE '%"tickets"%';
