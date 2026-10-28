-- ============================================================================
--  محتوای قابل ویرایش توسط ادمین (v3.15.0) — فعلاً متن‌های تور آموزشی (کلید tour.<id>)
--  فقط متن‌های تغییرکرده ذخیره می‌شوند؛ پیش‌فرض‌ها در config/tour_texts.php هستند.
--  اگر اجرا نشود، برنامه در اولین استفاده (در صورت داشتن مجوز CREATE) خودش جدول را می‌سازد.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `content_texts` (
  `key`        VARCHAR(80) NOT NULL,
  `body`       TEXT NOT NULL,
  `updated_by` INT UNSIGNED NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
