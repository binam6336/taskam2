-- ============================================================================
--  v6.1.0 — بورد اختصاصی هر پروژه، دسترسی یک‌گزینه‌ای «تغییر وضعیت»، تسک محرمانه.
--  ۱) ستون‌های بورد هر پروژه (projects.board_config؛ NULL = بورد پیش‌فرض مدیر سیستم) و
--     tasks.board_status از ENUM به VARCHAR (کلید ستون؛ rework / review / done ماهیت ثابت دارند).
--  ۲) تسک محرمانه: tasks.is_confidential + جدول task_viewers (حداکثر ۳ نفر علاوه بر سازنده و مسئول).
--  ۳) داده: دسترسی‌های قدیمی مرحله‌به‌مرحله‌ی بورد ← change_status؛ «مشاهده‌ی همه‌ی تسک‌ها» برای
--     اعضایی که دسترسی بورد دارند و قبلاً فقط تسک‌های خودشان را می‌دیدند (پیش‌فرض جدید نقش‌ها)؛
--     دارندگان همه‌ی دسترسی‌های پروژه، «مدیریت بورد» (manage_board) را هم می‌گیرند.
--  اگر اجرا نشود، برنامه یک بار خودش آن را اجرا می‌کند (SCHEMA_AUTO).
-- ============================================================================
ALTER TABLE `tasks` MODIFY `board_status` VARCHAR(24) NOT NULL DEFAULT 'backlog';
ALTER TABLE `projects` ADD COLUMN `board_config` TEXT NULL DEFAULT NULL AFTER `priority_type`;
ALTER TABLE `tasks` ADD COLUMN `is_confidential` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_quick`;

CREATE TABLE IF NOT EXISTS `task_viewers` (
  `task_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `added_by`   INT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`task_id`, `user_id`),
  KEY `idx_task_viewers_user` (`user_id`),
  CONSTRAINT `fk_task_viewers_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_viewers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE project_members SET board_permissions = '["change_status"]'
WHERE board_permissions LIKE '%"to\_%' OR board_permissions LIKE '%"move\_%';

UPDATE project_members SET board_view_users = '"all"'
WHERE board_permissions = '["change_status"]' AND (board_view_users IS NULL OR board_view_users = '[]' OR board_view_users = '');

UPDATE project_members SET permissions = REPLACE(permissions, ']', ',"manage_board"]')
WHERE permissions LIKE '%"edit\_project"%' AND permissions LIKE '%"add\_member"%' AND permissions LIKE '%"remove\_member"%'
  AND permissions LIKE '%"manage\_permissions"%' AND permissions LIKE '%"view\_analytics"%' AND permissions NOT LIKE '%"manage\_board"%';
