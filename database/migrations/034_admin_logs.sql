-- ============================================================================
--  لاگ امنیتی مدیران (v5.2.0): فعلاً ورود مدیر به پنل کاربر — ورود، خروج و هر تغییری که مدیر داخل پنل کاربر داده
--  (با مقدار قبل و بعد هر ستون). پنل مدیر ← تنظیمات ← امنیت.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`         INT UNSIGNED NOT NULL                 COMMENT 'مدیری که کار را انجام داده',
  `admin_role`       VARCHAR(20) NULL DEFAULT NULL         COMMENT 'نقش مدیر در همان لحظه: super / internal / support',
  `target_id`        INT UNSIGNED NULL DEFAULT NULL        COMMENT 'کاربری که مدیر وارد پنلش شده',
  `impersonation_id` INT UNSIGNED NULL DEFAULT NULL        COMMENT 'پنجره‌ی ورود به پنل کاربر (impersonations.id)',
  `action`           VARCHAR(20) NOT NULL                  COMMENT 'enter / exit / create / update / delete',
  `entity`           VARCHAR(40) NULL DEFAULT NULL         COMMENT 'جدول رکورد تغییرکرده (tasks، projects، …)',
  `entity_id`        VARCHAR(64) NULL DEFAULT NULL         COMMENT 'شناسه‌ی داخلی رکورد تغییرکرده',
  `entity_title`     VARCHAR(255) NULL DEFAULT NULL        COMMENT 'عنوان رکورد در لحظه‌ی ثبت (مثلاً عنوان تسک)',
  `changes`          MEDIUMTEXT NULL DEFAULT NULL          COMMENT 'JSON: [{f: ستون، b: قبل، a: بعد}] یا دلیل خروج',
  `request_key`      CHAR(16) NULL DEFAULT NULL            COMMENT 'تغییرهای یک درخواست با یک کلید (گروه‌بندی)',
  `path`             VARCHAR(190) NULL DEFAULT NULL        COMMENT 'مسیر درخواست داخل پنل کاربر',
  `ip`               VARCHAR(45) NULL DEFAULT NULL,
  `created_at`       DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_al_admin` (`admin_id`, `created_at`),
  KEY `idx_al_target` (`target_id`, `created_at`),
  KEY `idx_al_created` (`created_at`),
  CONSTRAINT `fk_al_admin`  FOREIGN KEY (`admin_id`)  REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_al_target` FOREIGN KEY (`target_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
