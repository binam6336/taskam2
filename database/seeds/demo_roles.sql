-- ============================================================================
--  تسکام — نقش‌های آزمایشی برای همکاران داده‌ی تست (demo_kpi.sql)
--  پیش‌نیاز: demo_kpi.sql اجرا شده و برنامه یک بار باز شده باشد (ستون‌های migrationهای ۰۰۹ تا ۰۱۲).
--  اجرای دوباره امن است؛ دسترسی‌های قبلی این اعضا با پکیج نقش جایگزین می‌شود.
--  پکیج نقش‌ها دقیقاً همان Access::ROLES در app/Services/Access.php است.
--  بدون جدول موقت و COLLATE تا به charset پیش‌فرض سرور (مثلاً latin1) وابسته نباشد.
--
--  پروژه‌ی «اپلیکیشن فروشگاه آنلاین» (همه‌ی پنج نقش):
--    09991867332 نیلوفر احمدی  → مدیر کل
--    09991867333 آرش رضایی     → مدیر واحد
--    09991867334 مریم حسینی    → سرپرست
--    09991867335 سینا محمدی    → عضو
--    09991867336 الهام کریمی   → بدون دسترسی
--  پروژه‌ی «کمپین بازاریابی پاییز» (نقش هر پروژه جداست):
--    09991867334 مریم حسینی    → مدیر کل
--    09991867336 الهام کریمی   → سرپرست
--    09991867332 نیلوفر احمدی  → عضو
--  سازنده‌ی هر دو پروژه: 09991867331 بهراد کیانی. رمز همه: 123456
-- ============================================================================
SET NAMES utf8mb4;
START TRANSACTION;

-- پروژه‌های دمو (با نام؛ مقایسه‌ی ستون با متن ثابت به collation سرور وابسته نیست)
SET @owner = (SELECT id FROM users WHERE mobile = '09991867331' LIMIT 1);
SET @p1 = (SELECT id FROM projects WHERE user_id = @owner AND title = 'اپلیکیشن فروشگاه آنلاین' ORDER BY id LIMIT 1);
SET @p2 = (SELECT id FROM projects WHERE user_id = @owner AND title = 'کمپین بازاریابی پاییز' ORDER BY id LIMIT 1);

-- مدیر کل: همه‌چیز، مشاهده‌ی تسک‌های همه
UPDATE project_members pm JOIN users u ON u.id = pm.user_id
   SET pm.task_permissions  = '["edit_info","edit_due","reassign","notes","complete","delete"]',
       pm.permissions       = '["edit_project","add_member","remove_member","manage_permissions","view_analytics"]',
       pm.board_permissions = '["to_todo","to_progress","to_review","to_done","block","unblock","move_back","move_any"]',
       pm.board_view_users  = '"all"'
 WHERE (pm.project_id = @p1 AND u.mobile = '09991867332')
    OR (pm.project_id = @p2 AND u.mobile = '09991867334');

-- مدیر واحد: همه‌چیز جز حذف تسک، مشاهده‌ی تسک‌های همه
UPDATE project_members pm JOIN users u ON u.id = pm.user_id
   SET pm.task_permissions  = '["edit_info","edit_due","reassign","notes","complete"]',
       pm.permissions       = '["edit_project","add_member","remove_member","manage_permissions","view_analytics"]',
       pm.board_permissions = '["to_todo","to_progress","to_review","to_done","block","unblock","move_back","move_any"]',
       pm.board_view_users  = '"all"'
 WHERE (pm.project_id = @p1 AND u.mobile = '09991867333');

-- سرپرست: تسک جز حذف، پروژه جز افزودن/حذف کاربر، برد کامل، فقط تسک‌های خودش
UPDATE project_members pm JOIN users u ON u.id = pm.user_id
   SET pm.task_permissions  = '["edit_info","edit_due","reassign","notes","complete"]',
       pm.permissions       = '["edit_project","manage_permissions","view_analytics"]',
       pm.board_permissions = '["to_todo","to_progress","to_review","to_done","block","unblock","move_back","move_any"]',
       pm.board_view_users  = '[]'
 WHERE (pm.project_id = @p1 AND u.mobile = '09991867334')
    OR (pm.project_id = @p2 AND u.mobile = '09991867336');

-- عضو: فقط ویرایش اطلاعات تسک و یادداشت، فقط تسک‌های خودش
UPDATE project_members pm JOIN users u ON u.id = pm.user_id
   SET pm.task_permissions  = '["edit_info","notes"]',
       pm.permissions       = '[]',
       pm.board_permissions = '[]',
       pm.board_view_users  = '[]'
 WHERE (pm.project_id = @p1 AND u.mobile = '09991867335')
    OR (pm.project_id = @p2 AND u.mobile = '09991867332');

-- بدون دسترسی
UPDATE project_members pm JOIN users u ON u.id = pm.user_id
   SET pm.task_permissions  = '[]',
       pm.permissions       = '[]',
       pm.board_permissions = '[]',
       pm.board_view_users  = '[]'
 WHERE (pm.project_id = @p1 AND u.mobile = '09991867336');

COMMIT;

-- گزارش: اعضای دو پروژه‌ی دمو و دسترسی‌هایشان
SELECT p.title AS project, CONCAT(u.first_name, ' ', u.last_name) AS member, u.mobile,
       pm.task_permissions, pm.permissions, pm.board_view_users
  FROM project_members pm
  JOIN projects p ON p.id = pm.project_id
  JOIN users u ON u.id = pm.user_id
 WHERE pm.project_id IN (@p1, @p2) AND pm.user_id <> @owner
 ORDER BY p.id, u.mobile;
