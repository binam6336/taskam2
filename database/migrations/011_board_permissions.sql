-- ============================================================================
--  دسترسی‌های برد تیمی به ازای هر پروژه (v3.2.0)
--  JSON: view, to_todo, to_progress, to_review, to_done, block, unblock, move_back, move_any
--  پیش‌فرض: هیچ (سازنده‌ی تسک و سازنده‌ی پروژه همه‌ی دسترسی‌ها را دارند).
--  اگر اجرا نشود، برنامه در اولین استفاده (در صورت داشتن مجوز ALTER) خودش آن را اضافه می‌کند.
-- ============================================================================
ALTER TABLE `project_members`
  ADD COLUMN `board_permissions` TEXT NULL DEFAULT NULL
    COMMENT 'JSON: دسترسی عضو در برد تیمی همین پروژه'
    AFTER `task_permissions`;
