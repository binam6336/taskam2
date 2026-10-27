-- ============================================================================
--  اعلان‌های Web Push (نسخه‌ی PWA): اشتراک هر دستگاه/مرورگر کاربر. روی دیتابیس موجود اجرا کنید.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `endpoint`      TEXT NOT NULL                COMMENT 'آدرس سرویس push مرورگر',
  `endpoint_hash` CHAR(64) NOT NULL            COMMENT 'SHA-256 آدرس (برای یکتایی)',
  `p256dh`        VARCHAR(255) NOT NULL,
  `auth`          VARCHAR(64) NOT NULL,
  `user_agent`    VARCHAR(255) NULL DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_ok_at`    TIMESTAMP NULL DEFAULT NULL,
  `fail_count`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_push_endpoint` (`endpoint_hash`),
  KEY `idx_push_user` (`user_id`),
  CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
