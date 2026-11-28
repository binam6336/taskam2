-- فاصله اختیاری یادآوری پس از تأخیر؛ داده‌های فعلی بدون تکرار باقی می‌مانند.
ALTER TABLE tasks
    ADD COLUMN reminder_every SMALLINT UNSIGNED NULL DEFAULT NULL AFTER due_time,
    ADD COLUMN reminder_unit VARCHAR(8) NULL DEFAULT NULL AFTER reminder_every;
