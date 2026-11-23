ALTER TABLE `users` ADD COLUMN `storage_limit_mb` INT UNSIGNED NULL DEFAULT NULL, ADD COLUMN `storage_custom` TINYINT(1) NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS `storage_files` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `directory` VARCHAR(32) NOT NULL,
  `file_name` VARCHAR(190) NOT NULL,
  `bytes` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_storage_path` (`directory`, `file_name`),
  KEY `idx_storage_user` (`user_id`),
  CONSTRAINT `fk_storage_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
