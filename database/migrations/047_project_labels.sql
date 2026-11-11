-- ============================================================================
--  v7.12.0 — لیبل‌ها در سطح پروژه:
--  subjects.project_id = پروژه‌ی صاحب لیبل (NULL = لیبل شخصی قدیمی قبل از v7.12 که روی هیچ تسک پروژه‌داری نبود)
--  ساخت/ویرایش/مسدود کردن = سازنده‌ی پروژه یا مجوز پروژه‌ی «create_label» (دسترسی ایجاد لیبل).
--  انتقال لیبل‌های شخصی به پروژه‌ها، لیبل پیش‌فرض پروژه‌های بی‌لیبل و افزودن create_label به نقش‌های مدیر کل/مدیر واحد
--  در PHP انجام می‌شود (App\Models\SubjectModel::migrateToProjects، Schema::PHP_DATA 047؛ با php bin/migrate.php هم اجرا می‌شود).
--  اگر اجرا نشود، برنامه خودش ستون را اضافه می‌کند (Schema::COLUMNS).
-- ============================================================================
ALTER TABLE `subjects` ADD COLUMN `project_id` INT UNSIGNED NULL DEFAULT NULL AFTER `user_id`, ADD KEY `idx_subjects_project` (`project_id`);
