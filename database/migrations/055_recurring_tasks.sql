-- v8.1: تنظیمات مشترک سری و ارتباط نوبت‌ها؛ هیچ نوبت قبلی بازنویسی یا حذف نمی‌شود.
CREATE TABLE IF NOT EXISTS `task_recurrences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `current_task_id` INT UNSIGNED NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `kind` VARCHAR(10) NOT NULL DEFAULT 'daily',
  `interval_days` INT UNSIGNED NULL DEFAULT NULL,
  `anchor_day` TINYINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recurrences_pending` (`enabled`, `current_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `tasks` ADD COLUMN `recurrence_id` INT UNSIGNED NULL DEFAULT NULL,
  ADD KEY `idx_tasks_recurrence` (`recurrence_id`),
  ADD CONSTRAINT `fk_tasks_recurrence` FOREIGN KEY (`recurrence_id`) REFERENCES `task_recurrences` (`id`) ON DELETE SET NULL;
ALTER TABLE `tasks` ADD COLUMN `recurrence_previous_id` INT UNSIGNED NULL DEFAULT NULL,
  ADD UNIQUE KEY `uq_tasks_recurrence_previous` (`recurrence_previous_id`),
  ADD CONSTRAINT `fk_tasks_recurrence_previous` FOREIGN KEY (`recurrence_previous_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL;
