-- v8.0: حذف قابلیت مسدودسازی لیبل‌ها؛ لیبل‌های قبلاً مسدودشده بدون حذف داده به فهرست عادی برمی‌گردند.
-- Schema::DROP_COLUMNS این دو تغییر را فقط در صورت وجود ستون اجرا می‌کند.
ALTER TABLE subjects DROP COLUMN blocked_at, DROP COLUMN blocked_by;
-- ارتباط tasks.subject_id از قبل ON DELETE SET NULL است؛ حذف لیبل خود تسک را حذف نمی‌کند.
