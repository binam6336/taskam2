-- ============================================================================
--  v7.20.0 — دستیار هوش مصنوعی بورد کانبان (پنل «هوش مصنوعی» در نوار ابزار بورد):
--  گفتگو (ai_board_sessions / ai_board_messages)، لیست‌های قابل ارجاع (ai_result_sets)،
--  عملیات گروهی با پیش‌نمایش و تأیید یک‌بارمصرف و بازگردانی (ai_actions) و فایل‌های خروجی (ai_exports).
--  اگر اجرا نشود، برنامه خودش جدول‌ها را می‌سازد (Schema::TABLES).
-- ============================================================================
-- دستیار هوش مصنوعی بورد (v7.20): هر گفتگو برای یک کاربر در یک پروژه
CREATE TABLE IF NOT EXISTS `ai_board_sessions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`          CHAR(12) NOT NULL                  COMMENT 'شناسه‌ی عمومی تصادفی گفتگو (Pid::new)',
  `user_id`      INT UNSIGNED NOT NULL              COMMENT 'صاحب گفتگو؛ همه‌ی ابزارها و عملیات با دسترسی همین کاربر',
  `project_id`   INT UNSIGNED NOT NULL              COMMENT 'پروژه‌ی بورد؛ دستیار فقط تسک‌های همین پروژه را می‌بیند',
  `real_user_id` INT UNSIGNED NULL DEFAULT NULL     COMMENT 'مدیر واقعی اگر گفتگو در «ورود به پنل کاربر» ساخته شده باشد',
  `title`        VARCHAR(120) NULL DEFAULT NULL     COMMENT 'اولین پیام کاربر (کوتاه‌شده)',
  `refs`         MEDIUMTEXT NULL                    COMMENT 'JSON نگاشت ارجاع‌های کوتاه مدل به شناسه‌ی واقعی: t (تسک T1…)، u (کاربر U1…)، l (لیبل L1…)',
  `busy_at`      TIMESTAMP NULL DEFAULT NULL        COMMENT 'قفل پردازش پیام (یک پیام در حال پردازش برای هر گفتگو)',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_bs_pid` (`pid`),
  KEY `idx_ai_bs_user` (`user_id`, `project_id`, `updated_at`),
  CONSTRAINT `fk_ai_bs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_bs_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- پیام‌های گفتگوی دستیار بورد (پیام کاربر و پاسخ دستیار با بلوک‌های نمایشی ساخته‌شده در سرور)
CREATE TABLE IF NOT EXISTS `ai_board_messages` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id`   INT UNSIGNED NOT NULL,
  `role`         VARCHAR(12) NOT NULL               COMMENT 'user | assistant',
  `content`      TEXT NULL                          COMMENT 'متن پیام کاربر یا متن ساده‌ی پاسخ دستیار (با توکن‌های [[task:T1]])',
  `blocks`       MEDIUMTEXT NULL                    COMMENT 'JSON بلوک‌های نمایشی پاسخ (متن، جدول تسک، آمار، پیش‌نمایش، نتیجه، خروجی)',
  `tools`        TEXT NULL                          COMMENT 'JSON ابزارهای فراخوانی‌شده (نام، پارامترهای معتبر، خلاصه‌ی نتیجه) برای ممیزی و زمینه‌ی نوبت بعد',
  `context`      TEXT NULL                          COMMENT 'JSON زمینه‌ی پیام کاربر: فیلترهای بورد، مجموعه و تسک‌های انتخاب‌شده',
  `model`        VARCHAR(64) NULL DEFAULT NULL,
  `tokens`       INT UNSIGNED NULL DEFAULT NULL,
  `error`        VARCHAR(64) NULL DEFAULT NULL      COMMENT 'کد خطای سرویس هوش مصنوعی یا پردازش',
  `real_user_id` INT UNSIGNED NULL DEFAULT NULL     COMMENT 'مدیر واقعی در حالت ورود به پنل کاربر',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_bm_session` (`session_id`, `id`),
  KEY `idx_ai_bm_created` (`role`, `created_at`),
  CONSTRAINT `fk_ai_bm_session` FOREIGN KEY (`session_id`) REFERENCES `ai_board_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- لیست‌های تسک ساخته‌شده در گفتگو (result_set_id) برای ارجاع در پیام‌های بعدی و عملیات روی همان لیست
CREATE TABLE IF NOT EXISTS `ai_result_sets` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`          CHAR(12) NOT NULL                  COMMENT 'result_set_id عمومی',
  `session_id`   INT UNSIGNED NOT NULL,
  `seq`          SMALLINT UNSIGNED NOT NULL         COMMENT 'شماره‌ی لیست در گفتگو (R1، R2، …) برای مدل',
  `title`        VARCHAR(160) NOT NULL,
  `task_ids`     MEDIUMTEXT NOT NULL                COMMENT 'JSON شناسه‌ی تسک‌ها به ترتیب نمایش (در لحظه‌ی نمایش دوباره دسترسی بررسی می‌شود)',
  `meta`         TEXT NULL                          COMMENT 'JSON فیلترهای سازنده‌ی لیست',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_rs_pid` (`pid`),
  UNIQUE KEY `uq_ai_rs_seq` (`session_id`, `seq`),
  CONSTRAINT `fk_ai_rs_session` FOREIGN KEY (`session_id`) REFERENCES `ai_board_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- عملیات تغییردهنده‌ی دستیار بورد: پیش‌نمایش ← تأیید کاربر (confirm_token یک‌بارمصرف) ← اجرا ← بازگردانی
CREATE TABLE IF NOT EXISTS `ai_actions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`          CHAR(12) NOT NULL,
  `session_id`   INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NOT NULL              COMMENT 'کاربری که پیش‌نمایش برایش ساخته شد و فقط او تأیید می‌کند',
  `real_user_id` INT UNSIGNED NULL DEFAULT NULL     COMMENT 'مدیر واقعی در حالت ورود به پنل کاربر (ممیزی)',
  `project_id`   INT UNSIGNED NOT NULL,
  `kind`         VARCHAR(16) NOT NULL               COMMENT 'bulk_update | create_tasks',
  `status`       VARCHAR(16) NOT NULL DEFAULT 'preview' COMMENT 'preview | applying | applied | undone | cancelled | expired',
  `token_hash`   CHAR(64) NULL DEFAULT NULL         COMMENT 'SHA-256 توکن تأیید یک‌بارمصرف (خود توکن ذخیره نمی‌شود)',
  `payload`      MEDIUMTEXT NOT NULL                COMMENT 'JSON تغییرات پیش‌نمایش‌شده (هر تسک: مقدار فعلی و جدید)',
  `payload_hash` CHAR(64) NOT NULL                  COMMENT 'SHA-256 همان payload؛ تأیید فقط برای همین تغییرات معتبر است',
  `result`       MEDIUMTEXT NULL                    COMMENT 'JSON نتیجه‌ی اجرا برای هر تسک (موفق/ناموفق و علت)',
  `undo`         MEDIUMTEXT NULL                    COMMENT 'JSON مقدارهای قبلی برای بازگردانی',
  `expires_at`   DATETIME NOT NULL                  COMMENT 'انقضای توکن تأیید',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `applied_at`   TIMESTAMP NULL DEFAULT NULL,
  `undone_at`    TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_act_pid` (`pid`),
  KEY `idx_ai_act_session` (`session_id`),
  KEY `idx_ai_act_user` (`user_id`, `created_at`),
  CONSTRAINT `fk_ai_act_session` FOREIGN KEY (`session_id`) REFERENCES `ai_board_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_act_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- فایل‌های خروجی CSV/PDF دستیار بورد (بیرون از webroot، لینک امضاشده‌ی کوتاه‌مدت، پاک‌سازی خودکار)
CREATE TABLE IF NOT EXISTS `ai_exports` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`           CHAR(12) NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL             COMMENT 'فقط همین کاربر دانلود می‌کند',
  `project_id`    INT UNSIGNED NOT NULL,
  `session_id`    INT UNSIGNED NULL DEFAULT NULL,
  `kind`          VARCHAR(16) NOT NULL              COMMENT 'board | tasks | workload | report',
  `format`        VARCHAR(8) NOT NULL               COMMENT 'csv | pdf',
  `file`          VARCHAR(80) NOT NULL              COMMENT 'نام فایل تصادفی در storage/app/ai-exports',
  `name`          VARCHAR(160) NOT NULL             COMMENT 'نام فایل دانلودی',
  `size`          INT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at`    DATETIME NOT NULL                 COMMENT 'بعد از این زمان لینک باطل و فایل پاک می‌شود',
  `downloaded_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_exp_pid` (`pid`),
  KEY `idx_ai_exp_expires` (`expires_at`),
  CONSTRAINT `fk_ai_exp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_exp_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
