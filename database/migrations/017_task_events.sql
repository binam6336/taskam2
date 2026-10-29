-- ============================================================================
--  تاریخچه‌ی تغییرات تسک (v3.23.0)
--  هر تغییر (عنوان، توضیحات، اولویت، سررسید، لیبل، پروژه، مسئول، انجام/بازگشت، برد، ویرایش/حذف یادداشت و فایل) یک ردیف.
--  ایجاد تسک، یادداشت‌ها و پیوست‌ها از جدول‌های خودشان خوانده می‌شوند (برای تسک‌های قدیمی هم کار می‌کند).
--  اگر اجرا نشود، برنامه در اولین استفاده (در صورت داشتن مجوز CREATE) خودش جدول را می‌سازد.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `task_events` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NULL DEFAULT NULL COMMENT 'انجام‌دهنده‌ی تغییر',
  `type`       VARCHAR(30) NOT NULL,
  `data`       TEXT NULL DEFAULT NULL COMMENT 'JSON: from/to و جزئیات',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_events_task` (`task_id`, `created_at`),
  CONSTRAINT `fk_task_events_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
