-- ============================================================================
--  v7.24.0 — وضعیت زنده‌ی پردازش دستیار بورد (در حال تحلیل / تلاش مجدد n از ۳) و بازیابی پاسخ
--  اگر اتصال مرورگر وسط پردازش قطع شود. progress = مرحله‌ی فعلی، pending_ref = شناسه‌ی پیام در حال پردازش (ساخته‌ی مرورگر)،
--  client_ref = همان شناسه روی پیام کاربر و پاسخ دستیار. اگر اجرا نشود، برنامه خودش ستون‌ها را اضافه می‌کند (Schema::COLUMNS).
-- ============================================================================
ALTER TABLE `ai_board_sessions`
  ADD COLUMN `progress` VARCHAR(20) NULL DEFAULT NULL COMMENT 'مرحله‌ی پردازش پیام فعلی: think | tools | retry:N' AFTER `busy_at`,
  ADD COLUMN `pending_ref` CHAR(16) NULL DEFAULT NULL COMMENT 'شناسه‌ی مرورگر برای پیام در حال پردازش' AFTER `progress`;
ALTER TABLE `ai_board_messages`
  ADD COLUMN `client_ref` CHAR(16) NULL DEFAULT NULL COMMENT 'شناسه‌ی مرورگر پیام (پیام کاربر و پاسخ دستیار آن) برای بازیابی پاسخ' AFTER `real_user_id`;
