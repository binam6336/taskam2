-- انتقال سقف‌ها و اتصال فایل‌های قبلی با StorageQuota::migrateProjects در php bin/migrate.php انجام می‌شود.
ALTER TABLE `projects` ADD COLUMN `storage_limit_mb` INT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE `storage_files` ADD COLUMN `project_id` INT UNSIGNED NULL DEFAULT NULL,
  ADD KEY `idx_storage_project` (`project_id`),
  ADD CONSTRAINT `fk_storage_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL;
