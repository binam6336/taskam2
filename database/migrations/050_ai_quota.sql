-- ============================================================================
--  v7.21.0 — سهمیه‌ی مصرف هوش مصنوعی کاربران (روزانه، ماهانه‌ی شمسی، کل) بر اساس توکن واقعی گزارش‌شده‌ی سرویس.
--  همه‌ی فراخوانی‌های مدل (Agnes::chat) پیش از ارسال رزرو و بعد از پاسخ ثبت می‌شوند (Karlix\Quota).
--  اگر اجرا نشود، برنامه خودش جدول‌ها را می‌سازد (Schema::TABLES).
-- ============================================================================
-- سهمیه‌ی مصرف هوش مصنوعی هر کاربر (v7.21): NULL = نامحدود
CREATE TABLE IF NOT EXISTS `ai_quotas` (
  `user_id`       INT UNSIGNED NOT NULL,
  `daily_limit`   BIGINT UNSIGNED NULL DEFAULT NULL  COMMENT 'سقف توکن روز (تقویم تهران)؛ NULL = نامحدود',
  `monthly_limit` BIGINT UNSIGNED NULL DEFAULT NULL  COMMENT 'سقف توکن ماه شمسی؛ NULL = نامحدود',
  `total_limit`   BIGINT UNSIGNED NULL DEFAULT NULL  COMMENT 'سقف توکن کل عمر حساب؛ NULL = نامحدود',
  `is_custom`     TINYINT(1) NOT NULL DEFAULT 0      COMMENT '1 = سهمیه‌ی اختصاصی مدیر؛ 0 = همراه با پیش‌فرض سراسری',
  `updated_by`    INT UNSIGNED NULL DEFAULT NULL     COMMENT 'آخرین مدیری که سهمیه را تغییر داد',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_ai_quota_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- سابقه‌ی هر فراخوانی مدل هوش مصنوعی (رزرو ← ثبت مصرف واقعی)
CREATE TABLE IF NOT EXISTS `ai_usage` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL              COMMENT 'کاربری که مصرف به سهمیه‌اش نوشته می‌شود',
  `real_user_id`    INT UNSIGNED NULL DEFAULT NULL     COMMENT 'مدیر واقعی در حالت ورود به پنل کاربر',
  `feature`         VARCHAR(24) NOT NULL               COMMENT 'بخش استفاده‌کننده: task_note | board',
  `model`           VARCHAR(64) NULL DEFAULT NULL,
  `request_key`     VARCHAR(100) NOT NULL              COMMENT 'شناسه‌ی یکتای فراخوانی (جلوگیری از ثبت تکراری)',
  `status`          VARCHAR(12) NOT NULL DEFAULT 'reserved' COMMENT 'reserved | ok | error | no_usage | expired',
  `reserved_tokens` INT UNSIGNED NOT NULL DEFAULT 0    COMMENT 'توکن رزروشده پیش از فراخوانی',
  `input_tokens`    INT UNSIGNED NOT NULL DEFAULT 0,
  `output_tokens`   INT UNSIGNED NOT NULL DEFAULT 0,
  `total_tokens`    INT UNSIGNED NOT NULL DEFAULT 0    COMMENT 'مصرفی که به سهمیه نوشته شد',
  `cost`            DECIMAL(14,6) NULL DEFAULT NULL    COMMENT 'هزینه‌ی تخمینی از روی نرخ .env (اختیاری)',
  `currency`        CHAR(3) NULL DEFAULT NULL,
  `day`             DATE NOT NULL                      COMMENT 'روز (تهران) برای سهمیه‌ی روزانه',
  `month`           CHAR(7) NOT NULL                   COMMENT 'ماه شمسی YYYY-MM برای سهمیه‌ی ماهانه',
  `error`           VARCHAR(32) NULL DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at`     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_usage_key` (`request_key`),
  KEY `idx_ai_usage_user` (`user_id`, `day`),
  KEY `idx_ai_usage_status` (`status`, `created_at`),
  CONSTRAINT `fk_ai_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- شمارنده‌های تجمیعی مصرف هر کاربر در هر دوره (بررسی سهمیه بدون جمع زدن کل سوابق)
CREATE TABLE IF NOT EXISTS `ai_usage_counters` (
  `user_id`    INT UNSIGNED NOT NULL,
  `period`     VARCHAR(12) NOT NULL                    COMMENT 'd:YYYY-MM-DD (روز تهران) | m:YYYY-MM (ماه شمسی) | total',
  `used`       BIGINT UNSIGNED NOT NULL DEFAULT 0      COMMENT 'توکن مصرف‌شده‌ی ثبت‌شده',
  `reserved`   BIGINT UNSIGNED NOT NULL DEFAULT 0      COMMENT 'توکن رزروشده‌ی درخواست‌های در حال اجرا',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `period`),
  KEY `idx_ai_counters_period` (`period`),
  CONSTRAINT `fk_ai_counters_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
