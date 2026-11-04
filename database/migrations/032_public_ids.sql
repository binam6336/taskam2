-- ============================================================================
--  v5.0.0 — شناسه‌ی عمومی (pid): شناسه‌ی عددی دیتابیس دیگر بیرون از سرور دیده نمی‌شود.
--  هر جدولی که شناسه‌اش در آدرس، فرم، API یا HTML می‌آید ستون pid (۱۲ نویسه‌ی تصادفی، یکتا) می‌گیرد.
--  pid ردیف‌های موجود را برنامه (Schema::ensure یا php bin/migrate.php) با PHP می‌سازد؛ ردیف تازه را DB::insert.
--  اگر اجرا نشود، برنامه یک بار خودش آن را اجرا می‌کند (SCHEMA_AUTO).
-- ============================================================================
ALTER TABLE `users` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_users_pid` (`pid`);
ALTER TABLE `projects` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_projects_pid` (`pid`);
ALTER TABLE `tasks` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_tasks_pid` (`pid`);
ALTER TABLE `task_notes` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_task_notes_pid` (`pid`);
ALTER TABLE `task_attachments` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_task_attachments_pid` (`pid`);
ALTER TABLE `note_attachments` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_note_attachments_pid` (`pid`);
ALTER TABLE `subjects` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_subjects_pid` (`pid`);
ALTER TABLE `call_requests` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_call_requests_pid` (`pid`);
ALTER TABLE `support_tickets` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_support_tickets_pid` (`pid`);
ALTER TABLE `support_messages` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_support_messages_pid` (`pid`);
ALTER TABLE `chat_messages` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_chat_messages_pid` (`pid`);
ALTER TABLE `api_tokens` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_api_tokens_pid` (`pid`);
ALTER TABLE `project_join_requests` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_project_join_requests_pid` (`pid`);
ALTER TABLE `colleague_requests` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_colleague_requests_pid` (`pid`);
ALTER TABLE `project_members` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_project_members_pid` (`pid`);
ALTER TABLE `colleagues` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_colleagues_pid` (`pid`);
ALTER TABLE `chat_requests` ADD COLUMN `pid` CHAR(12) NULL DEFAULT NULL COMMENT 'شناسه‌ی عمومی تصادفی (بیرون از سرور به‌جای id)' AFTER `id`, ADD UNIQUE KEY `uq_chat_requests_pid` (`pid`);
