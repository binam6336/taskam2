-- جلسه‌های ابری پروژه؛ تغییر افزایشی و بدون دستکاری اطلاعات پیشین.
CREATE TABLE IF NOT EXISTS `project_meetings` (
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

CREATE TABLE IF NOT EXISTS `meeting_members` (
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

CREATE TABLE IF NOT EXISTS `meeting_messages` (
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

CREATE TABLE IF NOT EXISTS `meeting_webhook_events` (
  `event_id` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`),
  KEY `idx_meeting_webhook_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
