-- ============================================================================
--  چت: بازارسال پیام و «حذف تاریخچه» یک‌طرفه. روی دیتابیس موجود اجرا کنید.
-- ============================================================================
ALTER TABLE `chat_messages`
  ADD COLUMN `forwarded_from` INT UNSIGNED NULL DEFAULT NULL COMMENT 'فرستنده‌ی اصلی پیام بازارسال‌شده' AFTER `attachment_type`;

CREATE TABLE IF NOT EXISTS `chat_history_clears` (
  `user_id`         INT UNSIGNED NOT NULL              COMMENT 'کسی که تاریخچه را پاک کرده',
  `other_user_id`   INT UNSIGNED NOT NULL              COMMENT 'طرف گفتگو (برای پیام‌های ذخیره‌شده = خود کاربر)',
  `cleared_upto_id` BIGINT UNSIGNED NOT NULL           COMMENT 'پیام‌های با شناسه‌ی کمتر/مساوی برای این کاربر نمایش داده نمی‌شوند',
  `cleared_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `other_user_id`),
  CONSTRAINT `fk_chc_user`  FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chc_other` FOREIGN KEY (`other_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
