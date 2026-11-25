-- ============================================================================
--  Karlix (کارلیکس، نام قبلی: تسکام) — Database Schema v2.0
--  ساخت دیتابیس خام و بدون داده. سازگار با MySQL 5.7+ / 8.x و MariaDB 10.3+
--
--  استفاده:
--     mysql -u root -p -e "CREATE DATABASE taskam CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--     mysql -u root -p taskam < database/schema.sql
--  سپس اولین مدیر را بسازید:  php bin/create-admin.php
--
--  اصول طراحی:
--   • InnoDB + utf8mb4_unicode_ci در همه‌ی جدول‌ها (قبلاً ترکیبی از utf8/utf8mb4 و collation های متفاوت بود)
--   • همه‌ی شناسه‌ها INT UNSIGNED و کلیدهای خارجی با رفتار حذف مشخص (CASCADE / SET NULL)
--   • قیدهای UNIQUE برای جلوگیری از داده‌ی تکراری (همکار، عضو پروژه، بلاک، ...)
--   • توکن‌های API به‌صورت SHA-256 ذخیره می‌شوند (نه متن ساده)
--   • ربات اطلاع‌رسانی با ستون is_bot مشخص می‌شود (نه شماره‌ی جادویی)
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────  کاربران و امنیت  ─────────────────────────────

CREATE TABLE `users` (
  `storage_limit_mb` INT UNSIGNED NULL DEFAULT NULL,
  `storage_custom` TINYINT(1) NOT NULL DEFAULT 0,
  `id`                              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`                             CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `mobile`                          VARCHAR(11)  NOT NULL                COMMENT 'شماره موبایل (۰۹xxxxxxxxx)؛ ربات: 00000000000',
  `first_name`                      VARCHAR(100) NOT NULL,
  `last_name`                       VARCHAR(100) NOT NULL DEFAULT '',
  `email`                           VARCHAR(150) NULL DEFAULT NULL,
  `avatar`                          VARCHAR(255) NULL DEFAULT NULL       COMMENT 'نام فایل در storage/uploads/avatars',
  `password_hash`                   VARCHAR(255) NOT NULL                COMMENT 'Argon2id / bcrypt',
  `role`                            ENUM('admin','user') NOT NULL DEFAULT 'user',
  `status`                          ENUM('pending','active','deactivated','blocked') NOT NULL DEFAULT 'pending',
  `admin_permissions`               TEXT NULL DEFAULT NULL               COMMENT 'دسترسی اختصاصی مدیر (v7.4، JSON کلیدهای AdminAccess::PERMS)؛ NULL = طبق نقش admin_role',
  `admin_role`                      VARCHAR(20)  NULL DEFAULT NULL       COMMENT 'نقش مدیر: super | internal | support (فقط برای role = admin)',
  `is_bot`                          TINYINT(1) NOT NULL DEFAULT 0        COMMENT '1 = ربات اطلاع‌رسانی (امکان ورود ندارد)',
  `allow_colleague_requests`        TINYINT(1) NOT NULL DEFAULT 1,
  `project_join_requires_approval`  TINYINT(1) NOT NULL DEFAULT 0        COMMENT '1 = عضویت/همکاری فقط با تأیید خود کاربر',
  `task_link_access`                ENUM('members','colleagues','me','all') NOT NULL DEFAULT 'members' COMMENT 'چه کسانی می‌توانند تسک‌های من را با لینک ببینند',
  `quick_links`                     TEXT NULL DEFAULT NULL               COMMENT 'JSON: شناسه‌ی لینک‌های دسترسی سریع',
  `theme`                           VARCHAR(20) NOT NULL DEFAULT 'dark'  COMMENT 'تم ظاهری: config/themes.php',
  `tour`                            VARCHAR(20) NULL DEFAULT NULL        COMMENT 'تور آموزشی: NULL پرسیده نشده، skip، done یا شماره‌ی مرحله',
  `board_order`                     VARCHAR(255) NULL DEFAULT NULL       COMMENT 'ترتیب ستون‌های بورد (کلیدها با کاما)؛ NULL = پیش‌فرض',
  `last_seen`                       INT UNSIGNED NULL DEFAULT NULL       COMMENT 'Unix time آخرین فعالیت چت',
  `last_login_at`                   DATETIME NULL DEFAULT NULL,
  `last_login_ip`                   VARCHAR(45) NULL DEFAULT NULL,
  `last_active_at`                  DATETIME NULL DEFAULT NULL           COMMENT 'آخرین فعالیت خود کاربر در پنل (بدون ورود مدیر به‌جای او)',
  `created_at`                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_pid` (`pid`),
  UNIQUE KEY `uq_users_mobile` (`mobile`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_status_role` (`status`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `content_texts` (
  `key`        VARCHAR(80) NOT NULL                COMMENT 'مثل tour.step_5',
  `body`       TEXT NOT NULL                       COMMENT 'متن ویرایش‌شده توسط ادمین (پیش‌فرض‌ها در config)',
  `updated_by` INT UNSIGNED NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_tokens` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`          CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `user_id`      INT UNSIGNED NOT NULL,
  `name`         VARCHAR(100) NOT NULL DEFAULT 'بدون نام',
  `token_hash`   CHAR(64) NOT NULL                 COMMENT 'SHA-256 توکن (خود توکن ذخیره نمی‌شود)',
  `token_prefix` VARCHAR(16) NOT NULL              COMMENT 'چند نویسه‌ی ابتدایی برای شناسایی در پنل',
  `scope`        ENUM('read','write') NOT NULL DEFAULT 'write' COMMENT 'read = فقط خواندن',
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `last_ip`      VARCHAR(45) NULL DEFAULT NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `expires_at`   DATETIME NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_tokens_pid` (`pid`),
  UNIQUE KEY `uq_api_tokens_hash` (`token_hash`),
  KEY `idx_api_tokens_user` (`user_id`, `is_active`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- نشست‌ها (هش شناسه + اتصال به دستگاه ورود)
CREATE TABLE `user_sessions` (
  `id_hash`       CHAR(64) NOT NULL                 COMMENT 'SHA-256 شناسه‌ی نشست (خود شناسه ذخیره نمی‌شود)',
  `user_id`       INT UNSIGNED NULL DEFAULT NULL    COMMENT 'NULL = مهمان (فقط توکن CSRF/پیام)',
  `data`          MEDIUMBLOB NOT NULL,
  `ip`            VARCHAR(45) NULL DEFAULT NULL     COMMENT 'IP کامل دستگاه هنگام ورود',
  `ip_prefix`     VARCHAR(45) NULL DEFAULT NULL     COMMENT 'دو بخش اول IP هنگام ورود (مثلاً 5.120)',
  `user_agent`    VARCHAR(255) NULL DEFAULT NULL    COMMENT 'مرورگر هنگام ورود',
  `life`          INT UNSIGNED NOT NULL DEFAULT 0   COMMENT 'ثانیه‌ی مجاز بدون فعالیت',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_activity` INT UNSIGNED NOT NULL             COMMENT 'Unix time آخرین درخواست',
  `reauth_at`     INT UNSIGNED NULL DEFAULT NULL    COMMENT 'Unix time شروع انتظار تأیید دوباره‌ی رمز (تغییر شبکه)',
  PRIMARY KEY (`id_hash`),
  KEY `idx_us_user` (`user_id`),
  KEY `idx_us_activity` (`last_activity`),
  CONSTRAINT `fk_us_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rate_limits` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket`     VARCHAR(32) NOT NULL,
  `key`        CHAR(64) NOT NULL                   COMMENT 'SHA-256 کلید (IP، موبایل|IP، ...)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rate_limits_lookup` (`bucket`, `key`, `created_at`),
  KEY `idx_rate_limits_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED NULL DEFAULT NULL,
  `action`     VARCHAR(64) NOT NULL,
  `details`    TEXT NULL DEFAULT NULL,
  `ip`         VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user_action` (`user_id`, `action`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────  پروژه‌ها  ─────────────────────────────

CREATE TABLE `projects` (
  `storage_limit_mb` INT UNSIGNED NULL DEFAULT NULL,
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`           CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `user_id`       INT UNSIGNED NOT NULL              COMMENT 'سازنده',
  `title`         VARCHAR(255) NOT NULL,
  `description`   MEDIUMTEXT NULL DEFAULT NULL,
  `max_members`   SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  `priority_type` ENUM('category','percent') NOT NULL DEFAULT 'category' COMMENT 'نحوه‌ی ثبت/نمایش اولویت تسک‌ها',
  `board_config` TEXT NULL DEFAULT NULL COMMENT 'ستون‌های بورد اختصاصی پروژه (JSON؛ NULL = بورد پیش‌فرض)',
  `workload_capacity` INT NOT NULL DEFAULT 12 COMMENT 'ظرفیت هفتگی پیش‌فرض هر نفر در «بار کاری» (امتیاز، ۴ تا ۳۰)',
  `profile_image` VARCHAR(255) NULL DEFAULT NULL,
  `status`        TINYINT(1) NOT NULL DEFAULT 1      COMMENT '1=فعال 0=غیرفعال',
  `is_sample`     TINYINT(1) NOT NULL DEFAULT 0      COMMENT '1 = پروژه‌ی نمونه‌ی خودکار ثبت‌نام (در پنل مدیر شمرده نمی‌شود)',
  `blocked_at`    TIMESTAMP NULL DEFAULT NULL        COMMENT 'مسدودشده توسط سازنده (به‌جای حذف)',
  `blocked_by`    INT UNSIGNED NULL DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projects_pid` (`pid`),
  KEY `idx_projects_user` (`user_id`),
  CONSTRAINT `fk_projects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_members` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`         CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `project_id`  INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `permissions` TEXT NULL DEFAULT NULL               COMMENT 'JSON: edit_project, add_member, remove_member, manage_permissions, view_analytics',
  `task_permissions` TEXT NULL DEFAULT NULL          COMMENT 'JSON: edit_info, edit_due, reassign, notes, complete, block — دسترسی روی تسک‌های همین پروژه',
  `board_permissions` TEXT NULL DEFAULT NULL         COMMENT 'JSON: دسترسی عضو در برد تیمی همین پروژه (to_todo, to_progress, to_review, to_done, block, unblock, move_back, move_any)',
  `board_view_users`  TEXT NULL DEFAULT NULL         COMMENT 'JSON: شناسه‌ی اعضایی که تسک‌هایشان در برد این عضو دیده می‌شود (پیش‌فرض فقط خودش)',
  `board_limits`      TEXT NULL DEFAULT NULL         COMMENT 'JSON: سقف تسک‌های این عضو در هر ستون برد ({"todo":5,...})؛ نبودن = بدون محدودیت',
  `weekly_capacity`   INT NULL DEFAULT NULL          COMMENT 'ظرفیت هفتگی اختصاصی عضو در «بار کاری» (امتیاز)؛ NULL = ظرفیت پروژه',
  `added_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_members_pid` (`pid`),
  UNIQUE KEY `uq_project_member` (`project_id`, `user_id`),
  KEY `idx_pm_user` (`user_id`),
  CONSTRAINT `fk_pm_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_blocks` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL                 COMMENT 'کاربری که پروژه را مسدود کرده',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_block` (`project_id`, `user_id`),
  CONSTRAINT `fk_pb_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pb_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_join_requests` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`         CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `project_id`  INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL                COMMENT 'کاربری که باید تأیید کند',
  `invited_by`  INT UNSIGNED NOT NULL,
  `status`      ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_join_requests_pid` (`pid`),
  UNIQUE KEY `uq_pjr_pending` (`project_id`, `user_id`, `status`),
  KEY `idx_pjr_user_status` (`user_id`, `status`),
  CONSTRAINT `fk_pjr_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pjr_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_pjr_inviter` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_leave_history` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`    INT UNSIGNED NOT NULL              COMMENT 'بدون FK: پروژه ممکن است حذف شده باشد',
  `user_id`       INT UNSIGNED NOT NULL,
  `project_title` VARCHAR(255) NULL DEFAULT NULL,
  `project_image` VARCHAR(255) NULL DEFAULT NULL,
  `left_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plh_user` (`user_id`),
  CONSTRAINT `fk_plh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────  تسک‌ها  ─────────────────────────────

CREATE TABLE `subjects` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`        CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `user_id`    INT UNSIGNED NOT NULL                 COMMENT 'سازنده‌ی لیبل',
  `project_id` INT UNSIGNED NULL DEFAULT NULL        COMMENT 'پروژه‌ی صاحب لیبل (v7.12)؛ NULL = لیبل شخصی قدیمی',
  `title`      VARCHAR(150) NOT NULL,
  `color`      VARCHAR(20) NOT NULL DEFAULT '#22c55e',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_subjects_pid` (`pid`),
  KEY `idx_subjects_user` (`user_id`),
  KEY `idx_subjects_project` (`project_id`),
  CONSTRAINT `fk_subjects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_recurrences` (
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

CREATE TABLE `tasks` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`          CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `user_id`      INT UNSIGNED NOT NULL               COMMENT 'سازنده',
  `assignee_id`  INT UNSIGNED NULL DEFAULT NULL      COMMENT 'مسئول انجام (NULL وقتی تسک به گروه داده شده)',
  `subject_id`   INT UNSIGNED NULL DEFAULT NULL,
  `project_id`   INT UNSIGNED NULL DEFAULT NULL,
  `title`        MEDIUMTEXT NOT NULL,
  `description`  MEDIUMTEXT NULL DEFAULT NULL,
  `priority`     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `priority_percent` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '۰ تا ۱۰۰، مضرب ۵',
  `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
  `is_quick`     TINYINT(1) NOT NULL DEFAULT 0       COMMENT '1 = با «ثبت سریع» ساخته شده و هنوز وضعیتش در بورد تغییر نکرده',
  `is_confidential` TINYINT(1) NOT NULL DEFAULT 0    COMMENT '1 = محرمانه: فقط سازنده، مسئول و task_viewers',
  `board_status` VARCHAR(24) NOT NULL DEFAULT 'backlog' COMMENT 'کلید ستون بورد پروژه (BoardConfig)؛ rework/review/done ثابت؛ تسک جدید = ستون شروع پروژه',
  `blocked_reason` VARCHAR(255) NULL DEFAULT NULL,
  `board_moved_at` TIMESTAMP NULL DEFAULT NULL,
  `archived_at`  TIMESTAMP NULL DEFAULT NULL          COMMENT 'زمان انتقال به آرشیو برد',
  `blocked_at`   TIMESTAMP NULL DEFAULT NULL          COMMENT 'مسدودشده توسط کاربر (به‌جای حذف)',
  `blocked_by`   INT UNSIGNED NULL DEFAULT NULL,
  `due_date`     DATE NULL DEFAULT NULL,
  `due_time`     TIME NULL DEFAULT NULL,
  `notified_at`  DATETIME NULL DEFAULT NULL          COMMENT 'زمان ارسال اعلان سررسید',
  `share_token`  VARCHAR(64) NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `delivered_at` TIMESTAMP NULL DEFAULT NULL          COMMENT 'آخرین تحویل برای بررسی سرپرست',
  `delivered_by` INT UNSIGNED NULL DEFAULT NULL,
  `approved_by`  INT UNSIGNED NULL DEFAULT NULL        COMMENT 'تأییدکننده در بررسی سرپرست',
  `first_delivered_at` TIMESTAMP NULL DEFAULT NULL    COMMENT 'اولین تحویل برای بررسی (KPI؛ با برگشت پاک نمی‌شود)',
  `rework_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0  COMMENT 'تعداد برگشت برای اصلاح (بازبینی مسئول)',
  `rework_category` VARCHAR(120) NULL DEFAULT NULL    COMMENT 'دسته‌های علت آخرین برگشت (unclear,quality,incomplete,no_attachment,out_of_scope)',
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `recurrence_id` INT UNSIGNED NULL DEFAULT NULL,
  `recurrence_previous_id` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tasks_pid` (`pid`),
  UNIQUE KEY `uq_tasks_share_token` (`share_token`),
  KEY `idx_tasks_assignee` (`assignee_id`, `is_completed`),
  KEY `idx_tasks_creator` (`user_id`, `is_completed`),
  KEY `idx_tasks_project` (`project_id`),
  KEY `idx_tasks_subject` (`subject_id`),
  KEY `idx_tasks_due` (`due_date`, `due_time`),
  KEY `idx_tasks_archived` (`archived_at`),
  KEY `idx_tasks_blocked` (`blocked_at`),
  CONSTRAINT `fk_tasks_user`     FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_assignee` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`)    ON DELETE SET NULL,
  KEY `idx_tasks_recurrence` (`recurrence_id`),
  UNIQUE KEY `uq_tasks_recurrence_previous` (`recurrence_previous_id`),
  CONSTRAINT `fk_tasks_recurrence` FOREIGN KEY (`recurrence_id`) REFERENCES `task_recurrences` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_recurrence_previous` FOREIGN KEY (`recurrence_previous_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_subject`  FOREIGN KEY (`subject_id`)  REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_project`  FOREIGN KEY (`project_id`)  REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تسک محرمانه (v6.1): کاربرانی که علاوه بر سازنده و مسئول، تسک محرمانه را می‌بینند (حداکثر ۳)
CREATE TABLE `task_viewers` (
  `task_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `added_by`   INT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`task_id`, `user_id`),
  KEY `idx_task_viewers_user` (`user_id`),
  CONSTRAINT `fk_task_viewers_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_viewers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_notes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`        CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `task_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `note`       MEDIUMTEXT NOT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_notes_pid` (`pid`),
  KEY `idx_notes_task` (`task_id`, `created_at`),
  KEY `idx_notes_user` (`user_id`),
  CONSTRAINT `fk_notes_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_events` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NULL DEFAULT NULL          COMMENT 'انجام‌دهنده‌ی تغییر',
  `type`       VARCHAR(30) NOT NULL                     COMMENT 'title, description, priority, due, label, project, assignee, completed, reopened, board, archived, unarchived, blocked, unblocked, note_edited, note_deleted, file_deleted',
  `data`       TEXT NULL DEFAULT NULL                   COMMENT 'JSON: from/to و جزئیات',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_events_task` (`task_id`, `created_at`),
  CONSTRAINT `fk_task_events_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_mentions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`    INT UNSIGNED NOT NULL,
  `note_id`    INT UNSIGNED NULL DEFAULT NULL,
  `user_id`    INT UNSIGNED NOT NULL              COMMENT 'کاربر منشن‌شده',
  `actor_id`   INT UNSIGNED NULL DEFAULT NULL     COMMENT 'نویسنده‌ی یادداشت',
  `seen_at`    TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tm_user` (`user_id`, `seen_at`),
  KEY `idx_tm_task` (`task_id`),
  CONSTRAINT `fk_tm_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_note` FOREIGN KEY (`note_id`) REFERENCES `task_notes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- دستیار هوش مصنوعی کارلیکس (v7.19): هر یادداشت دارای @کارلیکس = یک درخواست
CREATE TABLE `ai_requests` (
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
  `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'تعداد تلاش‌های ناموفق قبلی (تلاش مجدد خودکار، حداکثر ۲)',
  `retry_at`      TIMESTAMP NULL DEFAULT NULL        COMMENT 'زمان تلاش مجدد بعدی (NULL = بدون انتظار)',
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

-- دستیار هوش مصنوعی بورد (v7.20): هر گفتگو برای یک کاربر در یک پروژه
CREATE TABLE `ai_board_sessions` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`          CHAR(12) NOT NULL                  COMMENT 'شناسه‌ی عمومی تصادفی گفتگو (Pid::new)',
  `user_id`      INT UNSIGNED NOT NULL              COMMENT 'صاحب گفتگو؛ همه‌ی ابزارها و عملیات با دسترسی همین کاربر',
  `project_id`   INT UNSIGNED NOT NULL              COMMENT 'پروژه‌ی بورد؛ دستیار فقط تسک‌های همین پروژه را می‌بیند',
  `real_user_id` INT UNSIGNED NULL DEFAULT NULL     COMMENT 'مدیر واقعی اگر گفتگو در «ورود به پنل کاربر» ساخته شده باشد',
  `title`        VARCHAR(120) NULL DEFAULT NULL     COMMENT 'اولین پیام کاربر (کوتاه‌شده)',
  `refs`         MEDIUMTEXT NULL                    COMMENT 'JSON نگاشت ارجاع‌های کوتاه مدل به شناسه‌ی واقعی: t (تسک T1…)، u (کاربر U1…)، l (لیبل L1…)',
  `busy_at`      TIMESTAMP NULL DEFAULT NULL        COMMENT 'قفل پردازش پیام (یک پیام در حال پردازش برای هر گفتگو)',
  `progress`     VARCHAR(20) NULL DEFAULT NULL      COMMENT 'مرحله‌ی پردازش پیام فعلی: think | tools | retry:N',
  `pending_ref`  CHAR(16) NULL DEFAULT NULL         COMMENT 'شناسه‌ی مرورگر برای پیام در حال پردازش',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ai_bs_pid` (`pid`),
  KEY `idx_ai_bs_user` (`user_id`, `project_id`, `updated_at`),
  CONSTRAINT `fk_ai_bs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_bs_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- پیام‌های گفتگوی دستیار بورد (پیام کاربر و پاسخ دستیار با بلوک‌های نمایشی ساخته‌شده در سرور)
CREATE TABLE `ai_board_messages` (
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
  `client_ref`   CHAR(16) NULL DEFAULT NULL         COMMENT 'شناسه‌ی مرورگر پیام (پیام کاربر و پاسخ دستیار آن) برای بازیابی پاسخ',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_bm_session` (`session_id`, `id`),
  KEY `idx_ai_bm_created` (`role`, `created_at`),
  CONSTRAINT `fk_ai_bm_session` FOREIGN KEY (`session_id`) REFERENCES `ai_board_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- لیست‌های تسک ساخته‌شده در گفتگو (result_set_id) برای ارجاع در پیام‌های بعدی و عملیات روی همان لیست
CREATE TABLE `ai_result_sets` (
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
CREATE TABLE `ai_actions` (
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
CREATE TABLE `ai_exports` (
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

-- سهمیه‌ی مصرف هوش مصنوعی هر کاربر (v7.21): NULL = نامحدود
CREATE TABLE `ai_quotas` (
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
CREATE TABLE `ai_usage` (
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
  KEY `idx_ai_usage_user_status` (`user_id`, `status`, `created_at`),
  CONSTRAINT `fk_ai_usage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- صف نوبتی FIFO فراخوانی‌های مدل (v7.26): ردیف فقط در مدت انتظار/فراخوانی وجود دارد
CREATE TABLE `ai_queue` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'شماره‌ی نوبت (ترتیب ورود)',
  `user_id`    INT UNSIGNED NULL DEFAULT NULL       COMMENT 'کاربر درخواست‌کننده (فقط برای گزارش)',
  `feature`    VARCHAR(24) NOT NULL DEFAULT 'other'  COMMENT 'task_note | board',
  `state`      VARCHAR(8) NOT NULL DEFAULT 'wait'    COMMENT 'wait = در صف | run = در حال فراخوانی مدل',
  `beat_at`    DATETIME NOT NULL                     COMMENT 'آخرین نشانه‌ی زنده بودن',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_queue_state` (`state`, `beat_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- شمارنده‌های تجمیعی مصرف هر کاربر در هر دوره (بررسی سهمیه بدون جمع زدن کل سوابق)
CREATE TABLE `ai_usage_counters` (
  `user_id`    INT UNSIGNED NOT NULL,
  `period`     VARCHAR(12) NOT NULL                    COMMENT 'd:YYYY-MM-DD (روز تهران) | m:YYYY-MM (ماه شمسی) | total',
  `used`       BIGINT UNSIGNED NOT NULL DEFAULT 0      COMMENT 'توکن مصرف‌شده‌ی ثبت‌شده',
  `reserved`   BIGINT UNSIGNED NOT NULL DEFAULT 0      COMMENT 'توکن رزروشده‌ی درخواست‌های در حال اجرا',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `period`),
  KEY `idx_ai_counters_period` (`period`),
  CONSTRAINT `fk_ai_counters_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_attachments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`        CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `task_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `file_name`  VARCHAR(255) NOT NULL                 COMMENT 'نام اصلی',
  `file_path`  VARCHAR(255) NOT NULL                 COMMENT 'نام ذخیره‌شده',
  `file_size`  INT UNSIGNED NULL DEFAULT NULL,
  `file_ext`   VARCHAR(20) NULL DEFAULT NULL,
  `file_mime`  VARCHAR(120) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_attachments_pid` (`pid`),
  KEY `idx_ta_task` (`task_id`),
  KEY `idx_ta_user` (`user_id`),
  CONSTRAINT `fk_ta_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ta_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `note_attachments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`        CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `note_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `file_name`  VARCHAR(255) NOT NULL,
  `file_path`  VARCHAR(255) NOT NULL,
  `file_size`  INT UNSIGNED NULL DEFAULT NULL,
  `file_ext`   VARCHAR(20) NULL DEFAULT NULL,
  `file_mime`  VARCHAR(120) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_note_attachments_pid` (`pid`),
  KEY `idx_na_note` (`note_id`),
  KEY `idx_na_user` (`user_id`),
  CONSTRAINT `fk_na_note` FOREIGN KEY (`note_id`) REFERENCES `task_notes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_na_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────  همکاران و چت  ─────────────────────────────

CREATE TABLE `colleagues` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`          CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `user_id`           INT UNSIGNED NOT NULL          COMMENT 'کاربری که همکار را اضافه کرده (صاحب لیست)',
  `colleague_user_id` INT UNSIGNED NOT NULL          COMMENT 'همکار',
  `permissions`       TEXT NULL DEFAULT NULL         COMMENT 'منسوخ از v3.0 (دسترسی تسک‌ها در project_members.task_permissions است)',
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_colleagues_pid` (`pid`),
  UNIQUE KEY `uq_colleague_pair` (`user_id`, `colleague_user_id`),
  KEY `idx_colleagues_reverse` (`colleague_user_id`),
  CONSTRAINT `fk_col_user` FOREIGN KEY (`user_id`)           REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_col_col`  FOREIGN KEY (`colleague_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `colleague_blocks` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blocker_user_id` INT UNSIGNED NOT NULL,
  `blocked_user_id` INT UNSIGNED NOT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_colleague_block` (`blocker_user_id`, `blocked_user_id`),
  KEY `idx_cb_blocked` (`blocked_user_id`),
  CONSTRAINT `fk_cb_blocker` FOREIGN KEY (`blocker_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cb_blocked` FOREIGN KEY (`blocked_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `colleague_block_ghosts` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blocker_user_id` INT UNSIGNED NOT NULL,
  `ghost_key`       CHAR(24) NOT NULL                  COMMENT 'همان شناسه‌ی مبهم (HMAC) که برای ردیف‌های پنهان واقعی هم ساخته می‌شود',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_block_ghost` (`blocker_user_id`, `ghost_key`),
  CONSTRAINT `fk_bg_blocker` FOREIGN KEY (`blocker_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `colleague_requests` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`          CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `requester_id` INT UNSIGNED NOT NULL,
  `target_id`    INT UNSIGNED NOT NULL               COMMENT 'کسی که باید تأیید کند',
  `status`       ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_colleague_requests_pid` (`pid`),
  UNIQUE KEY `uq_colleague_request` (`requester_id`, `target_id`),
  KEY `idx_cr_target` (`target_id`, `status`),
  CONSTRAINT `fk_cr_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_target`    FOREIGN KEY (`target_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_messages` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`             CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `sender_id`       INT UNSIGNED NOT NULL,
  `receiver_id`     INT UNSIGNED NOT NULL            COMMENT 'sender = receiver ⇒ «پیام‌های ذخیره‌شده»',
  `message`         TEXT NOT NULL                  COMMENT 'متن پیام؛ با ENCODE_PASSWORD به‌صورت enc:v1:… رمزشده ذخیره می‌شود',
  `attachment`      VARCHAR(255) NULL DEFAULT NULL COMMENT 'نام تصادفی فایل روی دیسک',
  `attachment_name` TEXT NULL DEFAULT NULL         COMMENT 'نام اصلی فایل؛ رمزشده (طولانی‌تر از متن اصلی)',
  `attachment_size` INT UNSIGNED NULL DEFAULT NULL,
  `attachment_type` VARCHAR(50) NULL DEFAULT NULL,
  `forwarded_from`  INT UNSIGNED NULL DEFAULT NULL COMMENT 'فرستنده‌ی اصلی پیام بازارسال‌شده',
  `is_read`         TINYINT(1) NOT NULL DEFAULT 0,
  `read_at`         TIMESTAMP NULL DEFAULT NULL,
  `is_edited`       TINYINT(1) NOT NULL DEFAULT 0,
  `edited_at`       TIMESTAMP NULL DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_messages_pid` (`pid`),
  KEY `idx_chat_conversation` (`sender_id`, `receiver_id`, `id`),
  KEY `idx_chat_inbox` (`receiver_id`, `is_read`, `sender_id`),
  KEY `idx_chat_created` (`created_at`),
  CONSTRAINT `fk_chat_sender`   FOREIGN KEY (`sender_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_requests` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`          CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `requester_id` INT UNSIGNED NOT NULL                 COMMENT 'فرستنده‌ی پیام اول',
  `target_id`    INT UNSIGNED NOT NULL                 COMMENT 'گیرنده که باید بپذیرد',
  `status`       ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_requests_pid` (`pid`),
  UNIQUE KEY `uq_chat_request` (`requester_id`, `target_id`),
  KEY `idx_chat_request_target` (`target_id`, `status`),
  CONSTRAINT `fk_chr_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chr_target`    FOREIGN KEY (`target_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_history_clears` (
  `user_id`         INT UNSIGNED NOT NULL              COMMENT 'کسی که تاریخچه را پاک کرده',
  `other_user_id`   INT UNSIGNED NOT NULL              COMMENT 'طرف گفتگو (برای پیام‌های ذخیره‌شده = خود کاربر)',
  `cleared_upto_id` BIGINT UNSIGNED NOT NULL           COMMENT 'پیام‌های با شناسه‌ی کمتر/مساوی برای این کاربر نمایش داده نمی‌شوند',
  `cleared_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `other_user_id`),
  CONSTRAINT `fk_chc_user`  FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chc_other` FOREIGN KEY (`other_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────  یادداشت‌ها (متن‌های آماده‌ی قدیمی)  ─────────────────────────────

CREATE TABLE `texts` (
  `id`         VARCHAR(50) NOT NULL                  COMMENT '<hex>_<hex> — برای سازگاری با API نسخه‌ی ۱ رشته است',
  `user_id`    INT UNSIGNED NOT NULL,
  `title`      MEDIUMTEXT NOT NULL,
  `content`    MEDIUMTEXT NOT NULL,
  `color`      VARCHAR(12) NOT NULL DEFAULT 'yellow'  COMMENT 'رنگ یادداشت: yellow|green|blue|red|purple',
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_texts_user_order` (`user_id`, `sort_order`),
  CONSTRAINT `fk_texts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────  پشتیبانی (کاربر ↔ ادمین)  ─────────────────────────────

CREATE TABLE `support_tickets` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`           CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `user_id`       INT UNSIGNED NOT NULL,
  `subject`       VARCHAR(255) NOT NULL,
  `priority`      ENUM('low','medium','high','very_high') NOT NULL DEFAULT 'medium',
  `status`        ENUM('open','in_progress','answered','closed') NOT NULL DEFAULT 'open',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_reply_at` TIMESTAMP NULL DEFAULT NULL,
  `closed_at`     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_tickets_pid` (`pid`),
  KEY `idx_st_user` (`user_id`, `status`),
  KEY `idx_st_status` (`status`),
  CONSTRAINT `fk_st_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `support_messages` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`              CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `ticket_id`        INT UNSIGNED NOT NULL,
  `sender_id`        INT UNSIGNED NOT NULL,
  `sender_role`      ENUM('user','admin') NOT NULL DEFAULT 'user',
  `message`          MEDIUMTEXT NOT NULL,
  `attachment`       VARCHAR(255) NULL DEFAULT NULL COMMENT 'نام تصادفی فایل در storage/uploads/support_files',
  `attachment_name`  VARCHAR(255) NULL DEFAULT NULL COMMENT 'نام اصلی فایل',
  `attachment_size`  INT UNSIGNED NULL DEFAULT NULL,
  `is_read_by_user`  TINYINT(1) NOT NULL DEFAULT 0,
  `is_read_by_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_support_messages_pid` (`pid`),
  KEY `idx_sm_ticket` (`ticket_id`, `created_at`),
  KEY `idx_sm_unread` (`sender_role`, `is_read_by_admin`),
  KEY `idx_sm_attachment` (`attachment`),
  CONSTRAINT `fk_sm_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sm_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `push_subscriptions` (
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

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
--  (اختیاری) ساخت مدیر اولیه به‌صورت دستی بدون اسکریپت PHP
--  1) هش رمز را بسازید:  php -r "echo password_hash('YourStrongPass1', PASSWORD_BCRYPT, ['cost'=>12]);"
--  2) مقدار هش را جایگزین کنید و خط‌های زیر را از حالت کامنت خارج کنید:
--
--  INSERT INTO `users` (`mobile`,`first_name`,`last_name`,`password_hash`,`role`,`status`)
--  VALUES ('09120000000','مدیر','سیستم','<<PASTE_HASH_HERE>>','admin','active');
-- ============================================================================

-- ورود مدیر به پنل کاربر در تب جداگانه (v5.1)
CREATE TABLE `impersonations` (
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

-- لاگ امنیتی مدیران (v5.2)
CREATE TABLE `admin_logs` (
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

CREATE TABLE `storage_files` (
  `project_id` INT UNSIGNED NULL DEFAULT NULL,
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `directory` VARCHAR(32) NOT NULL,
  `file_name` VARCHAR(190) NOT NULL,
  `bytes` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_storage_path` (`directory`, `file_name`),
  KEY `idx_storage_user` (`user_id`),
  KEY `idx_storage_project` (`project_id`),
  CONSTRAINT `fk_storage_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_storage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جلسه‌های ابری پروژه؛ تغییر افزایشی و بدون دستکاری اطلاعات پیشین.
CREATE TABLE `project_meetings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` CHAR(12) NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `host_id` INT UNSIGNED NOT NULL,
  `active_project_id` INT UNSIGNED NULL DEFAULT NULL,
  `room_name` VARCHAR(80) NOT NULL,
  `room_sid` VARCHAR(80) NULL DEFAULT NULL,
  `mode` ENUM('audio','video') NOT NULL DEFAULT 'audio',
  `status` ENUM('waiting','active','ended') NOT NULL DEFAULT 'waiting',
  `sync_at` DATETIME NULL DEFAULT NULL,
  `cleanup_pending` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` DATETIME NULL DEFAULT NULL,
  `ended_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meeting_pid` (`pid`),
  UNIQUE KEY `uq_meeting_room` (`room_name`),
  UNIQUE KEY `uq_meeting_active_project` (`active_project_id`),
  KEY `idx_meeting_project` (`project_id`,`id`),
  CONSTRAINT `fk_meeting_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_meeting_host` FOREIGN KEY (`host_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `meeting_members` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `meeting_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `identity` CHAR(40) NOT NULL,
  `role` ENUM('host','speaker','listener') NOT NULL DEFAULT 'listener',
  `invite_state` ENUM('invited','accepted','declined','removed') NOT NULL DEFAULT 'invited',
  `can_microphone` TINYINT(1) NOT NULL DEFAULT 0,
  `can_camera` TINYINT(1) NOT NULL DEFAULT 0,
  `can_screen` TINYINT(1) NOT NULL DEFAULT 0,
  `raised_hand` TINYINT(1) NOT NULL DEFAULT 0,
  `activation_request` VARCHAR(20) NULL DEFAULT NULL,
  `request_ref` CHAR(32) NULL DEFAULT NULL,
  `present` TINYINT(1) NOT NULL DEFAULT 0,
  `sync_needed` TINYINT(1) NOT NULL DEFAULT 0,
  `last_event_at` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meeting_user` (`meeting_id`,`user_id`),
  UNIQUE KEY `uq_meeting_identity` (`identity`),
  KEY `idx_meeting_invites` (`user_id`,`invite_state`),
  CONSTRAINT `fk_meeting_member_room` FOREIGN KEY (`meeting_id`) REFERENCES `project_meetings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_meeting_member_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `meeting_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` CHAR(12) NOT NULL,
  `meeting_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `client_ref` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `body` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_meeting_message_pid` (`pid`),
  UNIQUE KEY `uq_meeting_message_ref` (`meeting_id`,`user_id`,`client_ref`),
  KEY `idx_meeting_messages` (`meeting_id`,`id`),
  CONSTRAINT `fk_meeting_message_room` FOREIGN KEY (`meeting_id`) REFERENCES `project_meetings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_meeting_message_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `meeting_webhook_events` (
  `event_id` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`),
  KEY `idx_meeting_webhook_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- v9.1.1: نوبت‌های دارای tasks.recurrence_id در بورد/آرشیو/ظرفیت بورد نمایش داده نمی‌شوند؛ داده و تقویم آن‌ها حفظ می‌شود.
-- task_events: تاریخچهٔ همهٔ نوع‌های ذخیره‌شده با ساعت دقیق و جزئیات، بدون انتشار شناسهٔ داخلی کاربران/گروه‌های حذف‌شده.
-- meeting_members دعوت‌های باز منبع باکس پیشخوان‌اند؛ پیام ربات و Web Push پیوند عمومی همان جلسه را دارند.
