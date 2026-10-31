-- ============================================================================
--  نشست‌ها در دیتابیس (v3.28.0) به‌جای فایل در storage/cache/sessions
--  شناسه‌ی نشست (همان کوکی) ذخیره نمی‌شود، فقط هش SHA-256 آن؛ نشت بکاپ دیتابیس کوکی قابل‌استفاده‌ای لو نمی‌دهد.
--  هر نشست واردشده به دستگاه ورود گره می‌خورد: IP کامل، دو بخش اول IP و مرورگر.
--  اگر اجرا نشود، برنامه در اولین استفاده (در صورت داشتن مجوز CREATE) خودش آن را می‌سازد؛ تا آن موقع نشست‌ها فایلی می‌مانند.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id_hash`       CHAR(64) NOT NULL                 COMMENT 'SHA-256 شناسه‌ی نشست (خود شناسه ذخیره نمی‌شود)',
  `user_id`       INT UNSIGNED NULL DEFAULT NULL    COMMENT 'NULL = مهمان (فقط توکن CSRF/پیام)',
  `data`          MEDIUMBLOB NOT NULL,
  `ip`            VARCHAR(45) NULL DEFAULT NULL     COMMENT 'IP کامل دستگاه هنگام ورود',
  `ip_prefix`     VARCHAR(45) NULL DEFAULT NULL     COMMENT 'دو بخش اول IP هنگام ورود (مثلاً 5.120)',
  `user_agent`    VARCHAR(255) NULL DEFAULT NULL    COMMENT 'مرورگر هنگام ورود',
  `life`          INT UNSIGNED NOT NULL DEFAULT 0   COMMENT 'ثانیه‌ی مجاز بدون فعالیت',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` INT UNSIGNED NOT NULL             COMMENT 'Unix time آخرین درخواست',
  PRIMARY KEY (`id_hash`),
  KEY `idx_us_user` (`user_id`),
  KEY `idx_us_activity` (`last_activity`),
  CONSTRAINT `fk_us_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
