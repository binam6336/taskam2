-- ============================================================================
--  دپارتمان / گروه (v6.0.0)
--  هر گروه مال یک پروژه است؛ اعضایش از اعضای همان پروژه‌اند و یک سرگروه اختیاری دارد.
--  تسک می‌تواند به‌جای یک کاربر به یک گروه داده شود (tasks.assignee_group_id؛ در این حالت assignee_id = NULL).
--  مدیریت گروه‌ها = هر کسی که در پروژه مجوز «افزودن کاربر» (add_member) دارد، و سازنده‌ی پروژه.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `project_groups` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid`         CHAR(12) NULL DEFAULT NULL            COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)',
  `project_id`  INT UNSIGNED NOT NULL                 COMMENT 'پروژه‌ی گروه',
  `title`       VARCHAR(80) NOT NULL                  COMMENT 'نام گروه / دپارتمان',
  `description` VARCHAR(255) NULL DEFAULT NULL,
  `head_id`     INT UNSIGNED NULL DEFAULT NULL        COMMENT 'سرگروه (اختیاری؛ باید عضو گروه باشد)',
  `created_by`  INT UNSIGNED NULL DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_groups_pid` (`pid`),
  UNIQUE KEY `uq_project_groups_title` (`project_id`, `title`),
  CONSTRAINT `fk_pg_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pg_head`    FOREIGN KEY (`head_id`)    REFERENCES `users` (`id`)    ON DELETE SET NULL,
  CONSTRAINT `fk_pg_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `project_group_members` (
  `group_id`   INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`, `user_id`),
  KEY `idx_pgm_user` (`user_id`),
  CONSTRAINT `fk_pgm_group` FOREIGN KEY (`group_id`) REFERENCES `project_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pgm_user`  FOREIGN KEY (`user_id`)  REFERENCES `users` (`id`)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تیک «انجام دادم» هر عضو روی تسک گروهی (کنار پروفایلش نمایش داده می‌شود)؛ با برگشت تسک برای اصلاح یا بازکردن پاک می‌شود
CREATE TABLE IF NOT EXISTS `task_group_ticks` (
  `task_id`    INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`task_id`, `user_id`),
  CONSTRAINT `fk_tgt_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tgt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `tasks`
  ADD COLUMN `assignee_group_id` INT UNSIGNED NULL DEFAULT NULL AFTER `assignee_id`,
  ADD COLUMN `group_head_approval` TINYINT(1) NOT NULL DEFAULT 0 AFTER `assignee_group_id`,
  ADD KEY `idx_tasks_group` (`assignee_group_id`, `is_completed`);
