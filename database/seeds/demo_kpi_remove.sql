-- ============================================================================
--  تسکام — حذف کامل داده‌ی آزمایشی demo_kpi.sql
--  کاربران 09991867331 تا 09991867336 و هر چیزی که به آن‌ها وابسته است حذف می‌شود:
--  پروژه‌ها و اعضا، تسک‌ها، یادداشت‌ها و پیوست‌ها، موضوعات، همکاران و درخواست‌ها،
--  گفتگوها، تیکت‌های پشتیبانی، توکن‌های API، متن‌های آماده، اشتراک اعلان‌ها و گزارش فعالیت.
--  به کاربران و داده‌های واقعی دست نمی‌زند. اجرای دوباره بی‌خطر است.
-- ============================================================================
SET NAMES utf8mb4;
START TRANSACTION;

CREATE TEMPORARY TABLE `tmp_demo_users` (`id` INT UNSIGNED PRIMARY KEY)
  SELECT `id` FROM `users` WHERE `mobile` IN ('09991867331','09991867332','09991867333','09991867334','09991867335','09991867336');

-- گزارش فعالیت کلید خارجی ندارد؛ جدا پاک می‌شود
DELETE FROM `audit_log` WHERE `user_id` IN (SELECT `id` FROM `tmp_demo_users`);

-- تسک‌هایی که کاربران آزمایشی ساخته‌اند (یادداشت‌ها و پیوست‌هایشان با CASCADE)
DELETE FROM `tasks` WHERE `user_id` IN (SELECT `id` FROM `tmp_demo_users`);
-- تسک‌های داخل پروژه‌های آزمایشی، حتی اگر کس دیگری ساخته باشد
DELETE FROM `tasks` WHERE `project_id` IN (SELECT `id` FROM `projects` WHERE `user_id` IN (SELECT `id` FROM `tmp_demo_users`));

-- خود کاربران؛ بقیه‌ی داده‌ها (پروژه‌ها، اعضا، موضوعات، همکاران، چت، تیکت، توکن، متن آماده،
-- اشتراک اعلان، تاریخچه‌ی ترک پروژه، ...) با ON DELETE CASCADE حذف می‌شوند
DELETE FROM `users` WHERE `id` IN (SELECT `id` FROM `tmp_demo_users`);

DROP TEMPORARY TABLE `tmp_demo_users`;
COMMIT;

-- بررسی: باید ۰ باشد
SELECT COUNT(*) AS remaining_demo_users FROM `users`
 WHERE `mobile` IN ('09991867331','09991867332','09991867333','09991867334','09991867335','09991867336');
