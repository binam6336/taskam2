-- ============================================================================
--  v3.48.0 — گزارش امنیتی v2:
--  • N-01 «درخواست گفتگو»: به غیرهمکار (بدون پروژه‌ی مشترک) فقط یک پیام؛ ادامه‌ی گفتگو بعد از پذیرفتن گیرنده.
--  • R-05 مسدودسازی شناسه‌ی ناموجود هم یک ردیف «شبح» می‌سازد تا فهرست مسدودها وجود حساب را لو ندهد.
--  • I-03 ستون reauth_at در user_sessions: نشست‌های منتظر «تأیید دوباره‌ی رمز» در «نشست‌های فعال» علامت می‌خورند.
--  اگر اجرا نشود، برنامه یک بار خودش آن را اجرا می‌کند (SCHEMA_AUTO).
-- ============================================================================
CREATE TABLE IF NOT EXISTS `chat_requests` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `requester_id` INT UNSIGNED NOT NULL                 COMMENT 'فرستنده‌ی پیام اول',
  `target_id`    INT UNSIGNED NOT NULL                 COMMENT 'گیرنده که باید بپذیرد',
  `status`       ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chat_request` (`requester_id`, `target_id`),
  KEY `idx_chat_request_target` (`target_id`, `status`),
  CONSTRAINT `fk_chr_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chr_target`    FOREIGN KEY (`target_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `colleague_block_ghosts` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blocker_user_id` INT UNSIGNED NOT NULL,
  `ghost_key`       CHAR(24) NOT NULL                  COMMENT 'همان شناسه‌ی مبهم (HMAC) که برای ردیف‌های پنهان واقعی هم ساخته می‌شود',
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_block_ghost` (`blocker_user_id`, `ghost_key`),
  CONSTRAINT `fk_bg_blocker` FOREIGN KEY (`blocker_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `user_sessions` ADD COLUMN `reauth_at` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Unix time شروع انتظار تأیید دوباره‌ی رمز (تغییر شبکه)' AFTER `last_activity`;

-- گفتگوهای موجود: اگر هر دو طرف پیام داده‌اند = پذیرفته‌شده؛ اگر فقط یک طرف = درخواست در انتظار (ربات‌ها مستثنا)
INSERT IGNORE INTO `chat_requests` (`requester_id`, `target_id`, `status`, `created_at`, `responded_at`)
SELECT LEAST(m.sender_id, m.receiver_id), GREATEST(m.sender_id, m.receiver_id), 'accepted', MIN(m.created_at), NOW()
  FROM `chat_messages` m
  JOIN `users` us ON us.id = m.sender_id AND us.is_bot = 0
  JOIN `users` ur ON ur.id = m.receiver_id AND ur.is_bot = 0
 WHERE m.sender_id <> m.receiver_id
   AND EXISTS (SELECT 1 FROM `chat_messages` r WHERE r.sender_id = m.receiver_id AND r.receiver_id = m.sender_id)
 GROUP BY LEAST(m.sender_id, m.receiver_id), GREATEST(m.sender_id, m.receiver_id);

INSERT IGNORE INTO `chat_requests` (`requester_id`, `target_id`, `status`, `created_at`)
SELECT m.sender_id, m.receiver_id, 'pending', MIN(m.created_at)
  FROM `chat_messages` m
  JOIN `users` us ON us.id = m.sender_id AND us.is_bot = 0
  JOIN `users` ur ON ur.id = m.receiver_id AND ur.is_bot = 0
 WHERE m.sender_id <> m.receiver_id
   AND NOT EXISTS (SELECT 1 FROM `chat_messages` r WHERE r.sender_id = m.receiver_id AND r.receiver_id = m.sender_id)
 GROUP BY m.sender_id, m.receiver_id;
