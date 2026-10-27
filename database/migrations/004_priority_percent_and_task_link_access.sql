-- ============================================================================
--  نوع اولویت پروژه (دسته‌بندی/درصدی)، اولویت درصدی تسک‌ها و
--  تنظیم «دسترسی مشاهده‌ی تسک با لینک» در پروفایل. (روی دیتابیس موجود اجرا کنید)
-- ============================================================================
ALTER TABLE `projects`
  ADD COLUMN `priority_type` ENUM('category','percent') NOT NULL DEFAULT 'category' COMMENT 'نحوه‌ی ثبت/نمایش اولویت تسک‌ها' AFTER `max_members`;

ALTER TABLE `tasks`
  ADD COLUMN `priority_percent` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '۰ تا ۱۰۰، مضرب ۵' AFTER `priority`;

-- مقدار درصدی معادل برای تسک‌های فعلی: کم = ۲۵، متوسط = ۵۰، زیاد = ۷۵
UPDATE `tasks` SET `priority_percent` = CASE `priority` WHEN 'low' THEN 25 WHEN 'high' THEN 75 ELSE 50 END WHERE `priority_percent` IS NULL;

ALTER TABLE `users`
  ADD COLUMN `task_link_access` ENUM('members','colleagues','me','all') NOT NULL DEFAULT 'members' COMMENT 'چه کسانی می‌توانند تسک‌های من را با لینک ببینند' AFTER `project_join_requires_approval`;
