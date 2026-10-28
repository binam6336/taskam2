-- ============================================================================
--  تسکام — پخش تسک‌های باز داده‌ی آزمایشی بین ستون‌های برد تیمی (کانبان)
--  پیش‌نیاز: demo_kpi.sql اجرا شده باشد و migration 010 (یا یک بار باز شدن برنامه) اعمال شده باشد.
--  فقط تسک‌های انجام‌نشده‌ی کاربران آزمایشی (09991867331 تا 09991867336) تغییر می‌کنند؛ اجرای دوباره امن است.
--  برای تست جابه‌جایی توسط همکاران، دسترسی «تکمیل تسک» و «نوشتن یادداشت» هم به اعضای پروژه‌های آزمایشی داده می‌شود.
-- ============================================================================
SET NAMES utf8mb4;
START TRANSACTION;

UPDATE tasks t JOIN users u ON u.id = t.user_id
   SET t.board_status = ELT(MOD(t.id, 6) + 1, 'backlog', 'todo', 'todo', 'in_progress', 'blocked', 'review'),
       t.blocked_reason = IF(MOD(t.id, 6) = 4, ELT(MOD(t.id, 4) + 1,
           'منتظر تأیید طراحی از طرف مدیر محصول',
           'وابسته به تکمیل API پرداخت',
           'سرور تست در دسترس نیست',
           'منتظر دریافت محتوا از مشتری'), NULL),
       t.board_moved_at = NOW() - INTERVAL MOD(t.id, 72) HOUR
 WHERE t.is_completed = 0
   AND u.mobile IN ('09991867331', '09991867332', '09991867333', '09991867334', '09991867335', '09991867336');

UPDATE tasks t JOIN users u ON u.id = t.user_id
   SET t.board_status = 'done', t.blocked_reason = NULL
 WHERE t.is_completed = 1
   AND u.mobile IN ('09991867331', '09991867332', '09991867333', '09991867334', '09991867335', '09991867336');

UPDATE project_members pm
  JOIN projects p ON p.id = pm.project_id
  JOIN users o ON o.id = p.user_id
   SET pm.task_permissions = '["notes","complete"]'
 WHERE o.mobile = '09991867331' AND pm.user_id <> p.user_id
   AND (pm.task_permissions IS NULL OR pm.task_permissions = '' OR pm.task_permissions = '[]');

COMMIT;
