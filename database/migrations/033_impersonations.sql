-- ============================================================================
--  ورود مدیر به پنل کاربر در تب جداگانه (v5.1.0)
--  هر «پنجره‌ی ورود به پنل کاربر» یک ردیف است؛ نشست مدیر دست نمی‌خورد و پنل کاربر زیر آدرس /as/<توکن>/… باز می‌شود.
--  خود توکن ذخیره نمی‌شود، فقط SHA-256 آن؛ توکن فقط همراه کوکی نشست همان مدیر، همان مرورگر و کلید مخفی همان نشست معتبر است.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `impersonations` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`          CHAR(12) NULL DEFAULT NULL           COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `token_hash`   CHAR(64) NOT NULL                    COMMENT 'SHA-256 توکن آدرس /as/<توکن>',
  `admin_id`     INT UNSIGNED NOT NULL                COMMENT 'مدیری که وارد شده',
  `target_id`    INT UNSIGNED NOT NULL                COMMENT 'کاربری که مدیر وارد پنلش شده',
  `session_hash` CHAR(64) NOT NULL                    COMMENT 'SHA-256 کلید مخفی نشست مدیر (imp_sk)',
  `admin_pv`     VARCHAR(32) NOT NULL                 COMMENT 'نسخه‌ی رمز مدیر هنگام شروع؛ تغییر رمز = پایان',
  `ua_hash`      CHAR(64) NOT NULL                    COMMENT 'مرورگر مدیر هنگام شروع',
  `ip`           VARCHAR(45) NULL DEFAULT NULL        COMMENT 'IP مدیر هنگام شروع',
  `last_ip`      VARCHAR(45) NULL DEFAULT NULL        COMMENT 'IP آخرین درخواست',
  `actions`      INT UNSIGNED NOT NULL DEFAULT 0      COMMENT 'تعداد درخواست‌های تغییر‌دهنده (POST) در این پنجره',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` DATETIME NOT NULL                    COMMENT 'آخرین درخواست (سقف بی‌فعالیتی)',
  `expires_at`   DATETIME NOT NULL                    COMMENT 'پایان قطعی (سقف عمر)',
  `ended_at`     DATETIME NULL DEFAULT NULL           COMMENT 'NULL = باز',
  `end_reason`   VARCHAR(32) NULL DEFAULT NULL        COMMENT 'exit / admin_end / end_all / logout / idle / expired / admin_changed / target_inactive / misuse / limit',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_imp_token` (`token_hash`),
  UNIQUE KEY `uq_impersonations_pid` (`pid`),
  KEY `idx_imp_admin` (`admin_id`, `ended_at`),
  CONSTRAINT `fk_imp_admin`  FOREIGN KEY (`admin_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_imp_target` FOREIGN KEY (`target_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
