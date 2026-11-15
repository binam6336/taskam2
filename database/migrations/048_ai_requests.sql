-- ============================================================================
--  v7.19.0 — دستیار هوش مصنوعی «کارلیکس» در یادداشت‌های تسک:
--  هر یادداشتی که «@کارلیکس» دارد یک ردیف ai_requests می‌سازد (یکتا روی note_id = هر درخواست فقط یک بار اجرا می‌شود).
--  عملیات پیشنهادی مدل بعد از اعتبارسنجی در بک‌اند و فقط با دسترسی همان کاربر (user_id) از سرویس‌های موجود اجرا می‌شود؛
--  پاسخ کارلیکس یک یادداشت معمولی روی همان تسک است که نویسنده‌اش کاربر ربات (users.is_bot = 1) است (reply_note_id).
--  اگر اجرا نشود، برنامه خودش جدول را می‌سازد (Schema::TABLES).
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ai_requests` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`       INT UNSIGNED NOT NULL,
  `note_id`       INT UNSIGNED NOT NULL              COMMENT 'یادداشت کاربر که کارلیکس در آن منشن شده (هر یادداشت یک درخواست)',
  `user_id`       INT UNSIGNED NOT NULL              COMMENT 'درخواست‌کننده؛ همه‌ی عملیات با دسترسی همین کاربر اجرا می‌شود',
  `status`        VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending | running | done | partial | failed | clarify | refused | error',
  `reply_note_id` INT UNSIGNED NULL DEFAULT NULL     COMMENT 'یادداشت پاسخ کارلیکس',
  `actions`       TEXT NULL                          COMMENT 'JSON: عملیات معتبر پیشنهادی مدل و نتیجه‌ی واقعی اجرای هرکدام',
  `error`         VARCHAR(255) NULL DEFAULT NULL     COMMENT 'کد خطای سرویس هوش مصنوعی یا اعتبارسنجی (بدون اطلاعات حساس)',
  `model`         VARCHAR(64) NULL DEFAULT NULL,
  `tokens`        INT UNSIGNED NULL DEFAULT NULL     COMMENT 'مصرف توکن گزارش‌شده‌ی سرویس',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at`    TIMESTAMP NULL DEFAULT NULL,
  `finished_at`   TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_note` (`note_id`),
  KEY `idx_ai_task` (`task_id`, `created_at`),
  KEY `idx_ai_user` (`user_id`, `created_at`),
  CONSTRAINT `fk_ai_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_note` FOREIGN KEY (`note_id`) REFERENCES `task_notes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
