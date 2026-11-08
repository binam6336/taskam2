-- ============================================================================
--  دپارتمان / گروه v5.12
--  ۱) مسدود کردن گروه به‌جای حذف: گروه مسدود دیگر تسک جدید نمی‌گیرد؛ تسک‌های فعلی‌اش عادی ادامه پیدا می‌کنند.
--  ۲) وظیفه‌ی شخصی هر عضو روی تسک گروهی: سرگروه (یا مدیر پروژه) برای هر عضو یادداشت «وظیفه‌ی تو» می‌گذارد.
-- ============================================================================
ALTER TABLE `project_groups`
  ADD COLUMN `blocked_at` TIMESTAMP NULL DEFAULT NULL AFTER `created_by`,
  ADD COLUMN `blocked_by` INT UNSIGNED NULL DEFAULT NULL AFTER `blocked_at`;

CREATE TABLE IF NOT EXISTS `task_group_duties` (
  `task_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL                 COMMENT 'عضو گروه',
  `duty`       VARCHAR(500) NOT NULL                 COMMENT 'وظیفه‌ی شخصی این عضو در همین تسک',
  `set_by`     INT UNSIGNED NULL DEFAULT NULL        COMMENT 'سرگروه یا مدیر پروژه',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`task_id`, `user_id`),
  CONSTRAINT `fk_tgd_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tgd_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
