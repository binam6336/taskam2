-- ============================================================================
--  v7.9.0 — صندوق بررسی سرپرست: دسته‌های علت آخرین «برگشت برای اصلاح» (چندانتخابی، جداشده با کاما)
--  مقادیر مجاز: unclear, quality, incomplete, no_attachment, out_of_scope (BoardService::REWORK_CATEGORIES)
--  اگر اجرا نشود، برنامه خودش ستون را اضافه می‌کند (Schema::COLUMNS).
-- ============================================================================
ALTER TABLE `tasks` ADD COLUMN `rework_category` VARCHAR(120) NULL DEFAULT NULL AFTER `rework_count`;
