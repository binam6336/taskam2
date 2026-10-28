-- ============================================================================
--  Taskam (تسکام) — Database Schema v2.0
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
  `id`                              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `mobile`                          VARCHAR(11)  NOT NULL                COMMENT 'شماره موبایل (۰۹xxxxxxxxx)؛ ربات: 00000000000',
  `first_name`                      VARCHAR(100) NOT NULL,
  `last_name`                       VARCHAR(100) NOT NULL DEFAULT '',
  `email`                           VARCHAR(150) NULL DEFAULT NULL,
  `avatar`                          VARCHAR(255) NULL DEFAULT NULL       COMMENT 'نام فایل در storage/uploads/avatars',
  `password_hash`                   VARCHAR(255) NOT NULL                COMMENT 'Argon2id / bcrypt',
  `role`                            ENUM('admin','user') NOT NULL DEFAULT 'user',
  `status`                          ENUM('pending','active','deactivated','blocked') NOT NULL DEFAULT 'pending',
  `admin_permissions`               TEXT NULL DEFAULT NULL               COMMENT 'JSON: add_user, delete_user, toggle_status, edit_user',
  `is_bot`                          TINYINT(1) NOT NULL DEFAULT 0        COMMENT '1 = ربات اطلاع‌رسانی (امکان ورود ندارد)',
  `allow_colleague_requests`        TINYINT(1) NOT NULL DEFAULT 1,
  `project_join_requires_approval`  TINYINT(1) NOT NULL DEFAULT 0        COMMENT '1 = عضویت/همکاری فقط با تأیید خود کاربر',
  `task_link_access`                ENUM('members','colleagues','me','all') NOT NULL DEFAULT 'members' COMMENT 'چه کسانی می‌توانند تسک‌های من را با لینک ببینند',
  `quick_links`                     TEXT NULL DEFAULT NULL               COMMENT 'JSON: شناسه‌ی لینک‌های دسترسی سریع',
  `theme`                           VARCHAR(20) NOT NULL DEFAULT 'dark'  COMMENT 'تم ظاهری: config/themes.php',
  `tour`                            VARCHAR(20) NULL DEFAULT NULL        COMMENT 'تور آموزشی: NULL پرسیده نشده، skip، done یا شماره‌ی مرحله',
  `last_seen`                       INT UNSIGNED NULL DEFAULT NULL       COMMENT 'Unix time آخرین فعالیت چت',
  `last_login_at`                   DATETIME NULL DEFAULT NULL,
  `last_login_ip`                   VARCHAR(45) NULL DEFAULT NULL,
  `created_at`                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_mobile` (`mobile`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_status_role` (`status`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `api_tokens` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `name`         VARCHAR(100) NOT NULL DEFAULT 'بدون نام',
  `token_hash`   CHAR(64) NOT NULL                 COMMENT 'SHA-256 توکن (خود توکن ذخیره نمی‌شود)',
  `token_prefix` VARCHAR(16) NOT NULL              COMMENT 'چند نویسه‌ی ابتدایی برای شناسایی در پنل',
  `last_used_at` TIMESTAMP NULL DEFAULT NULL,
  `last_ip`      VARCHAR(45) NULL DEFAULT NULL,
  `is_active`    TINYINT(1) NOT NULL DEFAULT 1,
  `expires_at`   DATETIME NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_tokens_hash` (`token_hash`),
  KEY `idx_api_tokens_user` (`user_id`, `is_active`),
  CONSTRAINT `fk_api_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL              COMMENT 'سازنده',
  `title`         VARCHAR(255) NOT NULL,
  `description`   TEXT NULL DEFAULT NULL,
  `max_members`   SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  `priority_type` ENUM('category','percent') NOT NULL DEFAULT 'category' COMMENT 'نحوه‌ی ثبت/نمایش اولویت تسک‌ها',
  `profile_image` VARCHAR(255) NULL DEFAULT NULL,
  `status`        TINYINT(1) NOT NULL DEFAULT 1      COMMENT '1=فعال 0=غیرفعال',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_projects_user` (`user_id`),
  CONSTRAINT `fk_projects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_members` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`  INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL,
  `permissions` TEXT NULL DEFAULT NULL               COMMENT 'JSON: edit_project, add_member, remove_member, manage_permissions, view_analytics',
  `task_permissions` TEXT NULL DEFAULT NULL          COMMENT 'JSON: edit_info, edit_due, reassign, notes, complete, delete — دسترسی روی تسک‌های همین پروژه',
  `board_permissions` TEXT NULL DEFAULT NULL         COMMENT 'JSON: دسترسی عضو در برد تیمی همین پروژه (to_todo, to_progress, to_review, to_done, block, unblock, move_back, move_any)',
  `board_view_users`  TEXT NULL DEFAULT NULL         COMMENT 'JSON: شناسه‌ی اعضایی که تسک‌هایشان در برد این عضو دیده می‌شود (پیش‌فرض فقط خودش)',
  `added_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
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
  `project_id`  INT UNSIGNED NOT NULL,
  `user_id`     INT UNSIGNED NOT NULL                COMMENT 'کاربری که باید تأیید کند',
  `invited_by`  INT UNSIGNED NOT NULL,
  `status`      ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
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
  `user_id`    INT UNSIGNED NOT NULL,
  `title`      VARCHAR(150) NOT NULL,
  `color`      VARCHAR(20) NOT NULL DEFAULT '#22c55e',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subjects_user` (`user_id`),
  CONSTRAINT `fk_subjects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tasks` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL               COMMENT 'سازنده',
  `assignee_id`  INT UNSIGNED NULL DEFAULT NULL      COMMENT 'مسئول انجام',
  `subject_id`   INT UNSIGNED NULL DEFAULT NULL,
  `project_id`   INT UNSIGNED NULL DEFAULT NULL,
  `title`        VARCHAR(255) NOT NULL,
  `description`  TEXT NULL DEFAULT NULL,
  `priority`     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `priority_percent` TINYINT UNSIGNED NULL DEFAULT NULL COMMENT '۰ تا ۱۰۰، مضرب ۵',
  `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
  `board_status` ENUM('backlog','todo','in_progress','blocked','review','done') NOT NULL DEFAULT 'backlog' COMMENT 'وضعیت در برد تیمی (تسک جدید: backlog)',
  `blocked_reason` VARCHAR(255) NULL DEFAULT NULL,
  `board_moved_at` TIMESTAMP NULL DEFAULT NULL,
  `archived_at`  TIMESTAMP NULL DEFAULT NULL          COMMENT 'زمان انتقال به آرشیو برد',
  `due_date`     DATE NULL DEFAULT NULL,
  `due_time`     TIME NULL DEFAULT NULL,
  `notified_at`  DATETIME NULL DEFAULT NULL          COMMENT 'زمان ارسال اعلان سررسید',
  `share_token`  VARCHAR(64) NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tasks_share_token` (`share_token`),
  KEY `idx_tasks_assignee` (`assignee_id`, `is_completed`),
  KEY `idx_tasks_creator` (`user_id`, `is_completed`),
  KEY `idx_tasks_project` (`project_id`),
  KEY `idx_tasks_subject` (`subject_id`),
  KEY `idx_tasks_due` (`due_date`, `due_time`),
  KEY `idx_tasks_archived` (`archived_at`),
  CONSTRAINT `fk_tasks_user`     FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_assignee` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`)    ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_subject`  FOREIGN KEY (`subject_id`)  REFERENCES `subjects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_project`  FOREIGN KEY (`project_id`)  REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_notes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `note`       TEXT NOT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notes_task` (`task_id`, `created_at`),
  KEY `idx_notes_user` (`user_id`),
  CONSTRAINT `fk_notes_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_attachments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `file_name`  VARCHAR(255) NOT NULL                 COMMENT 'نام اصلی',
  `file_path`  VARCHAR(255) NOT NULL                 COMMENT 'نام ذخیره‌شده',
  `file_size`  INT UNSIGNED NULL DEFAULT NULL,
  `file_ext`   VARCHAR(20) NULL DEFAULT NULL,
  `file_mime`  VARCHAR(120) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ta_task` (`task_id`),
  KEY `idx_ta_user` (`user_id`),
  CONSTRAINT `fk_ta_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ta_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `note_attachments` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `note_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `file_name`  VARCHAR(255) NOT NULL,
  `file_path`  VARCHAR(255) NOT NULL,
  `file_size`  INT UNSIGNED NULL DEFAULT NULL,
  `file_ext`   VARCHAR(20) NULL DEFAULT NULL,
  `file_mime`  VARCHAR(120) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_na_note` (`note_id`),
  KEY `idx_na_user` (`user_id`),
  CONSTRAINT `fk_na_note` FOREIGN KEY (`note_id`) REFERENCES `task_notes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_na_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────  همکاران و چت  ─────────────────────────────

CREATE TABLE `colleagues` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`           INT UNSIGNED NOT NULL          COMMENT 'کاربری که همکار را اضافه کرده (صاحب لیست)',
  `colleague_user_id` INT UNSIGNED NOT NULL          COMMENT 'همکار',
  `permissions`       TEXT NULL DEFAULT NULL         COMMENT 'منسوخ از v3.0 (دسترسی تسک‌ها در project_members.task_permissions است)',
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
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

CREATE TABLE `colleague_requests` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `requester_id` INT UNSIGNED NOT NULL,
  `target_id`    INT UNSIGNED NOT NULL               COMMENT 'کسی که باید تأیید کند',
  `status`       ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_colleague_request` (`requester_id`, `target_id`),
  KEY `idx_cr_target` (`target_id`, `status`),
  CONSTRAINT `fk_cr_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cr_target`    FOREIGN KEY (`target_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `chat_messages` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
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
  KEY `idx_chat_conversation` (`sender_id`, `receiver_id`, `id`),
  KEY `idx_chat_inbox` (`receiver_id`, `is_read`, `sender_id`),
  KEY `idx_chat_created` (`created_at`),
  CONSTRAINT `fk_chat_sender`   FOREIGN KEY (`sender_id`)   REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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

-- ─────────────────────────────  تماس / متن‌های آماده  ─────────────────────────────

CREATE TABLE `call_requests` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL               COMMENT 'ثبت‌کننده',
  `assignee_id`  INT UNSIGNED NULL DEFAULT NULL      COMMENT 'مسئول پیگیری',
  `first_name`   VARCHAR(150) NOT NULL,
  `last_name`    VARCHAR(150) NOT NULL,
  `mobile`       VARCHAR(20) NOT NULL,
  `email`        VARCHAR(150) NULL DEFAULT NULL,
  `store`        VARCHAR(255) NULL DEFAULT NULL,
  `website`      VARCHAR(255) NULL DEFAULT NULL,
  `description`  TEXT NULL DEFAULT NULL,
  `status`       TINYINT(1) NOT NULL DEFAULT 0       COMMENT '0=جدید 1=انجام‌شده',
  `share_token`  VARCHAR(64) NULL DEFAULT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_calls_share_token` (`share_token`),
  KEY `idx_calls_user` (`user_id`, `status`),
  KEY `idx_calls_assignee` (`assignee_id`, `status`),
  CONSTRAINT `fk_calls_user`     FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_calls_assignee` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `texts` (
  `id`         VARCHAR(50) NOT NULL                  COMMENT '<hex>_<hex> — برای سازگاری با API نسخه‌ی ۱ رشته است',
  `user_id`    INT UNSIGNED NOT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `content`    TEXT NOT NULL,
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
  `user_id`       INT UNSIGNED NOT NULL,
  `subject`       VARCHAR(255) NOT NULL,
  `priority`      ENUM('low','medium','high','very_high') NOT NULL DEFAULT 'medium',
  `status`        ENUM('open','in_progress','answered','closed') NOT NULL DEFAULT 'open',
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_reply_at` TIMESTAMP NULL DEFAULT NULL,
  `closed_at`     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_st_user` (`user_id`, `status`),
  KEY `idx_st_status` (`status`),
  CONSTRAINT `fk_st_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `support_messages` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_id`        INT UNSIGNED NOT NULL,
  `sender_id`        INT UNSIGNED NOT NULL,
  `sender_role`      ENUM('user','admin') NOT NULL DEFAULT 'user',
  `message`          TEXT NOT NULL,
  `attachment`       VARCHAR(255) NULL DEFAULT NULL COMMENT 'نام تصادفی فایل در storage/uploads/support_files',
  `attachment_name`  VARCHAR(255) NULL DEFAULT NULL COMMENT 'نام اصلی فایل',
  `attachment_size`  INT UNSIGNED NULL DEFAULT NULL,
  `is_read_by_user`  TINYINT(1) NOT NULL DEFAULT 0,
  `is_read_by_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
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
