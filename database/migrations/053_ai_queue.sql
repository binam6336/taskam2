-- ============================================================================
--  v7.26.0 — آزمون هم‌زمانی هوش مصنوعی (AI-03، AI-05)
--  ۱) صف نوبتی FIFO فراخوانی‌های مدل (Agnes::acquire): هر فراخوانی یک نوبت می‌گیرد و فقط وقتی کمتر از AI_MAX_CONCURRENT
--     نوبت زنده‌ی قبل از آن مانده باشد اجرا می‌شود. ردیف بعد از پایان فراخوانی حذف می‌شود (جدول همیشه کوچک است).
--  ۲) ایندکس (user_id, status, created_at) روی ai_usage تا پیدا کردن رزروهای رهاشده‌ی یک کاربر فقط ردیف‌های همان کاربر را بخواند
--     (قبلاً ایندکس (status, created_at) ردیف‌های همه‌ی کاربران را قفل می‌کرد و بن‌بست می‌ساخت).
--  اگر اجرا نشود، برنامه خودش این تغییرات را اعمال می‌کند (Schema::TABLES و Schema::INDEXES).
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ai_queue` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'شماره‌ی نوبت (ترتیب ورود)',
  `user_id`    INT UNSIGNED NULL DEFAULT NULL       COMMENT 'کاربر درخواست‌کننده (فقط برای گزارش)',
  `feature`    VARCHAR(24) NOT NULL DEFAULT 'other'  COMMENT 'task_note | board',
  `state`      VARCHAR(8) NOT NULL DEFAULT 'wait'    COMMENT 'wait = در صف | run = در حال فراخوانی مدل',
  `beat_at`    DATETIME NOT NULL                     COMMENT 'آخرین نشانه‌ی زنده بودن (منتظر هر ۰٫۴ ثانیه؛ اجرا هنگام شروع)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_queue_state` (`state`, `beat_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ai_usage` ADD KEY `idx_ai_usage_user_status` (`user_id`, `status`, `created_at`);
