-- ============================================================================
--  v7.0.0 — حذف کامل قابلیت «دپارتمان / گروه»: جدول‌های گروه و ستون‌های گروهِ تسک پاک می‌شوند.
--  تسک‌هایی که به گروه داده شده بودند بدون مسئول می‌مانند (مسئول = سازنده؛ assignee_id پیش‌تر NULL بود و حالا سازنده می‌شود).
--  پیش از به‌روزرسانی، اگر به داده‌های گروه نیاز دارید از این جدول‌ها پشتیبان بگیرید.
--  اگر اجرا نشود، برنامه یک بار خودش آن را اجرا می‌کند (SCHEMA_AUTO؛ Schema::DROP_COLUMNS / DATA).
-- ============================================================================
-- (پیش از حذف ستون در Schema::DROP_COLUMNS) UPDATE tasks SET assignee_id = user_id WHERE assignee_id IS NULL AND assignee_group_id IS NOT NULL;
DROP TABLE IF EXISTS task_group_ticks;
DROP TABLE IF EXISTS task_group_duties;
DROP TABLE IF EXISTS project_group_members;
DROP TABLE IF EXISTS project_groups;
-- ستون‌ها (Schema::DROP_COLUMNS فقط اگر وجود داشته باشند):
-- ALTER TABLE tasks DROP INDEX idx_tasks_group, DROP COLUMN assignee_group_id, DROP COLUMN group_head_approval;
