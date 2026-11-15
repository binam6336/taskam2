-- ============================================================================
--  v7.10.0 — «بار کاری» (Workload) هر پروژه:
--  projects.workload_capacity = ظرفیت هفتگی پیش‌فرض هر نفر (امتیاز؛ پیش‌فرض ۱۲، از صفحه‌ی بار کاری ۴ تا ۳۰)
--  project_members.weekly_capacity = ظرفیت اختصاصی همان عضو؛ NULL = ظرفیت پروژه
--  اگر اجرا نشود، برنامه خودش ستون‌ها را اضافه می‌کند (Schema::COLUMNS).
-- ============================================================================
ALTER TABLE `projects` ADD COLUMN `workload_capacity` INT NOT NULL DEFAULT 12 AFTER `board_config`;
ALTER TABLE `project_members` ADD COLUMN `weekly_capacity` INT NULL DEFAULT NULL AFTER `board_limits`;
