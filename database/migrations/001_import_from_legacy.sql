-- ============================================================================
--  مهاجرت داده‌ها از دیتابیس نسخه‌ی قدیمی (mahyarsa_task.sql) به اسکیمای v2
--
--  روش (غیرمخرب — دیتابیس قدیمی دست‌نخورده می‌ماند):
--   1) دیتابیس جدید را با database/schema.sql بسازید (مثلاً taskam).
--   2) دامپ قدیمی را در یک دیتابیس موقت ایمپورت کنید:
--        mysql -u root -p -e "CREATE DATABASE taskam_legacy CHARACTER SET utf8mb4"
--        mysql -u root -p taskam_legacy < mahyarsa_task.sql
--   3) این فایل را روی دیتابیس «جدید» اجرا کنید (کاربر MySQL باید به هر دو دیتابیس دسترسی داشته باشد):
--        mysql -u root -p taskam < database/migrations/001_import_from_legacy.sql
--      اگر نام دیتابیس قدیمی چیز دیگری است، `taskam_legacy` را در همین فایل جایگزین کنید.
--   4) پوشه‌ی uploads/ قدیمی را به storage/uploads/ منتقل کنید (نام زیرپوشه‌ها یکسان است):
--        avatars, projects, task_attachments, note_attachments, chat_files
--
--  تغییرات مهم حین مهاجرت:
--   • users.password → password_hash (هش‌های bcrypt قدیمی معتبر می‌مانند و در اولین ورود به Argon2id ارتقا می‌یابند)
--   • ربات (موبایل 00000000000) با is_bot = 1 علامت می‌خورد
--   • ایمیل‌های خالی/تکراری → NULL
--   • توکن‌های API متن‌ساده → SHA-256 (توکن‌های فعلی کاربران همچنان کار می‌کنند)
--   • colleague_messages → chat_messages
--   • شناسه‌های دسترسی سریع به نام‌های جدید تبدیل می‌شوند
--   • رکوردهای یتیم (اشاره به کاربر/پروژه‌ی حذف‌شده) نادیده گرفته می‌شوند
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION';

-- ───── users ─────
INSERT INTO `users` (`id`,`mobile`,`first_name`,`last_name`,`email`,`avatar`,`password_hash`,`role`,`status`,`admin_permissions`,`is_bot`,
                     `allow_colleague_requests`,`project_join_requires_approval`,`quick_links`,`last_seen`,`created_at`)
SELECT u.id, LEFT(TRIM(u.mobile), 11), LEFT(u.first_name, 100), LEFT(COALESCE(u.last_name, ''), 100),
       CASE WHEN TRIM(COALESCE(u.email,'')) = '' THEN NULL
            WHEN u.id = (SELECT MIN(x.id) FROM `taskam_legacy`.`users` x WHERE x.email = u.email) THEN LEFT(TRIM(u.email), 150) ELSE NULL END,
       NULLIF(u.avatar, ''), u.password, u.role, u.status, u.admin_permissions,
       IF(TRIM(u.mobile) = '00000000000', 1, 0),
       u.allow_colleague_requests, u.project_join_requires_approval,
       CASE WHEN u.quick_links IS NULL OR u.quick_links = '' THEN NULL ELSE
            REPLACE(REPLACE(REPLACE(REPLACE(u.quick_links, '"subject"', '"subjects"'), '"hamkar"', '"colleagues"'), '"call_request"', '"calls"'), '"prpfile"', '"profile"') END,
       u.last_seen, u.created_at
  FROM `taskam_legacy`.`users` u;

-- ───── api_tokens (متن ساده → هش) ─────
INSERT INTO `api_tokens` (`id`,`user_id`,`name`,`token_hash`,`token_prefix`,`last_used_at`,`last_ip`,`is_active`,`created_at`)
SELECT t.id, t.user_id, t.name, SHA2(t.token, 256), LEFT(t.token, 10), t.last_used_at, t.last_ip, t.is_active, t.created_at
  FROM `taskam_legacy`.`api_tokens` t
 WHERE t.user_id IN (SELECT id FROM `users`) AND t.token IS NOT NULL AND t.token <> '';

-- ───── audit_log ─────
INSERT INTO `audit_log` (`id`,`user_id`,`action`,`details`,`ip`,`user_agent`,`created_at`)
SELECT id, user_id, action, details, ip, user_agent, created_at FROM `taskam_legacy`.`audit_log`;

-- ───── projects ─────
INSERT INTO `projects` (`id`,`user_id`,`title`,`description`,`max_members`,`profile_image`,`status`,`created_at`)
SELECT p.id, p.user_id, p.title, p.description, LEAST(GREATEST(p.max_members, 1), 100), NULLIF(p.profile_image, ''), p.status, p.created_at
  FROM `taskam_legacy`.`projects` p WHERE p.user_id IN (SELECT id FROM `users`);

INSERT IGNORE INTO `project_members` (`id`,`project_id`,`user_id`,`permissions`,`added_at`)
SELECT m.id, m.project_id, m.user_id, m.permissions, m.added_at FROM `taskam_legacy`.`project_members` m
 WHERE m.project_id IN (SELECT id FROM `projects`) AND m.user_id IN (SELECT id FROM `users`);

-- سازنده‌ی هر پروژه حتماً عضو باشد
INSERT IGNORE INTO `project_members` (`project_id`,`user_id`,`permissions`)
SELECT p.id, p.user_id, '["edit_project","add_member","remove_member","manage_permissions","view_analytics"]' FROM `projects` p;

INSERT IGNORE INTO `project_blocks` (`project_id`,`user_id`,`created_at`)
SELECT b.project_id, b.user_id, b.created_at FROM `taskam_legacy`.`project_blocks` b
 WHERE b.project_id IN (SELECT id FROM `projects`) AND b.user_id IN (SELECT id FROM `users`);

INSERT IGNORE INTO `project_join_requests` (`id`,`project_id`,`user_id`,`invited_by`,`status`,`created_at`,`resolved_at`)
SELECT r.id, r.project_id, r.user_id, r.invited_by, r.status, r.created_at, r.resolved_at FROM `taskam_legacy`.`project_join_requests` r
 WHERE r.project_id IN (SELECT id FROM `projects`) AND r.user_id IN (SELECT id FROM `users`) AND r.invited_by IN (SELECT id FROM `users`);

INSERT INTO `project_leave_history` (`id`,`project_id`,`user_id`,`project_title`,`project_image`,`left_at`)
SELECT h.id, h.project_id, h.user_id, h.project_title, h.project_image, h.left_at FROM `taskam_legacy`.`project_leave_history` h
 WHERE h.user_id IN (SELECT id FROM `users`);

-- ───── subjects / tasks ─────
INSERT INTO `subjects` (`id`,`user_id`,`title`,`color`,`created_at`)
SELECT s.id, s.user_id, s.title, COALESCE(NULLIF(s.color,''), '#22c55e'), s.created_at FROM `taskam_legacy`.`subjects` s WHERE s.user_id IN (SELECT id FROM `users`);

INSERT INTO `tasks` (`id`,`user_id`,`assignee_id`,`subject_id`,`project_id`,`title`,`description`,`priority`,`is_completed`,`due_date`,`due_time`,`notified_at`,`share_token`,`created_at`,`completed_at`)
SELECT t.id, t.user_id,
       IF(t.assignee_id IN (SELECT id FROM `users`), t.assignee_id, t.user_id),
       IF(t.subject_id IN (SELECT id FROM `subjects`), t.subject_id, NULL),
       IF(t.project_id IN (SELECT id FROM `projects`), t.project_id, NULL),
       t.title, t.description, t.priority, t.is_completed,
       NULLIF(t.due_date, '0000-00-00'), t.due_time, t.notified_at, NULLIF(t.share_token, ''), t.created_at, t.completed_at
  FROM `taskam_legacy`.`tasks` t WHERE t.user_id IN (SELECT id FROM `users`);

INSERT INTO `task_notes` (`id`,`task_id`,`user_id`,`note`,`updated_at`,`created_at`)
SELECT n.id, n.task_id, n.user_id, n.note, n.updated_at, n.created_at FROM `taskam_legacy`.`task_notes` n
 WHERE n.task_id IN (SELECT id FROM `tasks`) AND n.user_id IN (SELECT id FROM `users`);

INSERT INTO `task_attachments` (`id`,`task_id`,`user_id`,`file_name`,`file_path`,`file_size`,`file_ext`,`file_mime`,`created_at`)
SELECT a.id, a.task_id, a.user_id, a.file_name, a.file_path, a.file_size, a.file_ext, a.file_mime, a.created_at FROM `taskam_legacy`.`task_attachments` a
 WHERE a.task_id IN (SELECT id FROM `tasks`) AND a.user_id IN (SELECT id FROM `users`);

INSERT INTO `note_attachments` (`id`,`note_id`,`user_id`,`file_name`,`file_path`,`file_size`,`file_ext`,`file_mime`,`created_at`)
SELECT a.id, a.note_id, a.user_id, a.file_name, a.file_path, a.file_size, a.file_ext, a.file_mime, a.created_at FROM `taskam_legacy`.`note_attachments` a
 WHERE a.note_id IN (SELECT id FROM `task_notes`) AND a.user_id IN (SELECT id FROM `users`);

-- ───── colleagues / chat ─────
INSERT IGNORE INTO `colleagues` (`id`,`user_id`,`colleague_user_id`,`permissions`,`created_at`)
SELECT c.id, c.user_id, c.colleague_user_id, c.permissions, c.created_at FROM `taskam_legacy`.`colleagues` c
 WHERE c.user_id IN (SELECT id FROM `users`) AND c.colleague_user_id IN (SELECT id FROM `users`);

INSERT IGNORE INTO `colleague_blocks` (`id`,`blocker_user_id`,`blocked_user_id`,`created_at`)
SELECT b.id, b.blocker_user_id, b.blocked_user_id, b.created_at FROM `taskam_legacy`.`colleague_blocks` b
 WHERE b.blocker_user_id IN (SELECT id FROM `users`) AND b.blocked_user_id IN (SELECT id FROM `users`);

INSERT IGNORE INTO `colleague_requests` (`id`,`requester_id`,`target_id`,`status`,`created_at`,`resolved_at`)
SELECT r.id, r.requester_id, r.target_id, r.status, r.created_at, r.resolved_at FROM `taskam_legacy`.`colleague_requests` r
 WHERE r.requester_id IN (SELECT id FROM `users`) AND r.target_id IN (SELECT id FROM `users`);

INSERT INTO `chat_messages` (`id`,`sender_id`,`receiver_id`,`message`,`attachment`,`attachment_name`,`attachment_size`,`attachment_type`,`is_read`,`read_at`,`is_edited`,`edited_at`,`created_at`)
SELECT m.id, m.sender_id, m.receiver_id, COALESCE(m.message, ''), m.attachment, m.attachment_name, m.attachment_size, m.attachment_type, m.is_read, m.read_at, m.is_edited, m.edited_at, m.created_at
  FROM `taskam_legacy`.`colleague_messages` m
 WHERE m.sender_id IN (SELECT id FROM `users`) AND m.receiver_id IN (SELECT id FROM `users`);

-- ───── تماس / متن‌ها (بخش «تیکت شخصی» در v2.1 حذف شده و منتقل نمی‌شود) ─────
INSERT INTO `call_requests` (`id`,`user_id`,`assignee_id`,`first_name`,`last_name`,`mobile`,`email`,`store`,`website`,`description`,`status`,`share_token`,`created_at`,`completed_at`)
SELECT r.id, r.user_id, IF(r.assignee_id IN (SELECT id FROM `users`), r.assignee_id, NULL), r.first_name, r.last_name, r.mobile, NULLIF(r.email,''), NULLIF(r.store,''), NULLIF(r.website,''), r.description, r.status, NULLIF(r.share_token,''), r.created_at, r.completed_at
  FROM `taskam_legacy`.`call_requests` r WHERE r.user_id IN (SELECT id FROM `users`);

INSERT INTO `texts` (`id`,`user_id`,`title`,`content`,`sort_order`,`created_at`,`updated_at`)
SELECT t.id, t.user_id, t.title, t.content, t.sort_order, t.created_at, t.updated_at FROM `taskam_legacy`.`texts` t WHERE t.user_id IN (SELECT id FROM `users`);

-- ───── پشتیبانی ─────
INSERT INTO `support_tickets` (`id`,`user_id`,`subject`,`priority`,`status`,`created_at`,`updated_at`,`last_reply_at`,`closed_at`)
SELECT s.id, s.user_id, s.subject, s.priority, s.status, s.created_at, s.updated_at, s.last_reply_at, s.closed_at FROM `taskam_legacy`.`support_tickets` s WHERE s.user_id IN (SELECT id FROM `users`);

INSERT INTO `support_messages` (`id`,`ticket_id`,`sender_id`,`sender_role`,`message`,`is_read_by_user`,`is_read_by_admin`,`created_at`)
SELECT m.id, m.ticket_id, m.sender_id, m.sender_role, m.message, m.is_read_by_user, m.is_read_by_admin, m.created_at FROM `taskam_legacy`.`support_messages` m
 WHERE m.ticket_id IN (SELECT id FROM `support_tickets`) AND m.sender_id IN (SELECT id FROM `users`);

SET FOREIGN_KEY_CHECKS = 1;

-- ───── بررسی نهایی: تعداد رکوردها (قدیمی ↔ جدید) ─────
SELECT 'users' AS tbl, (SELECT COUNT(*) FROM `taskam_legacy`.`users`) AS legacy, (SELECT COUNT(*) FROM `users`) AS migrated
UNION ALL SELECT 'tasks', (SELECT COUNT(*) FROM `taskam_legacy`.`tasks`), (SELECT COUNT(*) FROM `tasks`)
UNION ALL SELECT 'projects', (SELECT COUNT(*) FROM `taskam_legacy`.`projects`), (SELECT COUNT(*) FROM `projects`)
UNION ALL SELECT 'chat_messages', (SELECT COUNT(*) FROM `taskam_legacy`.`colleague_messages`), (SELECT COUNT(*) FROM `chat_messages`)
UNION ALL SELECT 'api_tokens', (SELECT COUNT(*) FROM `taskam_legacy`.`api_tokens`), (SELECT COUNT(*) FROM `api_tokens`);
