-- ============================================================================
--  منشن‌ها (v3.35.0): هر منشن در یادداشت تسک ثبت می‌شود تا در «منتظر اقدام» پیشخوان
--  نمایش داده شود؛ با باز کردن جزئیات همان تسک توسط کاربر منشن‌شده، دیده‌شده (seen_at) می‌شود.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `task_mentions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`    INT UNSIGNED NOT NULL,
  `note_id`    INT UNSIGNED NULL DEFAULT NULL,
  `user_id`    INT UNSIGNED NOT NULL              COMMENT 'کاربر منشن‌شده',
  `actor_id`   INT UNSIGNED NULL DEFAULT NULL     COMMENT 'نویسنده‌ی یادداشت',
  `seen_at`    TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tm_user` (`user_id`, `seen_at`),
  KEY `idx_tm_task` (`task_id`),
  CONSTRAINT `fk_tm_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_note` FOREIGN KEY (`note_id`) REFERENCES `task_notes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
