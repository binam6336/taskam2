-- ============================================================================
--  تسکام — نقش‌های آزمایشی برای همکاران داده‌ی تست (demo_kpi.sql)
--  پیش‌نیاز: demo_kpi.sql اجرا شده و برنامه یک بار باز شده باشد (ستون‌های migrationهای ۰۰۹ تا ۰۱۲).
--  اجرای دوباره امن است؛ دسترسی‌های قبلی این اعضا با پکیج نقش جایگزین می‌شود.
--  پکیج نقش‌ها دقیقاً همان Access::ROLES در app/Services/Access.php است.
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

-- نقش‌ها: [task_permissions, permissions, board_permissions, board_view_users]
DROP TEMPORARY TABLE IF EXISTS tmp_roles;
CREATE TEMPORARY TABLE tmp_roles (role VARCHAR(20) PRIMARY KEY, t TEXT, p TEXT, b TEXT, v TEXT);
INSERT INTO tmp_roles VALUES
  ('admin',        '["edit_info","edit_due","reassign","notes","complete","delete"]', '["edit_project","add_member","remove_member","manage_permissions","view_analytics"]', '["to_todo","to_progress","to_review","to_done","block","unblock","move_back","move_any"]', '"all"'),
  ('unit_manager', '["edit_info","edit_due","reassign","notes","complete"]',          '["edit_project","add_member","remove_member","manage_permissions","view_analytics"]', '["to_todo","to_progress","to_review","to_done","block","unblock","move_back","move_any"]', '"all"'),
  ('supervisor',   '["edit_info","edit_due","reassign","notes","complete"]',          '["edit_project","manage_permissions","view_analytics"]',                               '["to_todo","to_progress","to_review","to_done","block","unblock","move_back","move_any"]', '[]'),
  ('member',       '["edit_info","notes"]',                                           '[]',                                                                                    '[]',                                                                                       '[]'),
  ('none',         '[]',                                                              '[]',                                                                                    '[]',                                                                                       '[]');

-- چه کسی در کدام پروژه چه نقشی دارد
DROP TEMPORARY TABLE IF EXISTS tmp_assign;
CREATE TEMPORARY TABLE tmp_assign (project_title VARCHAR(255), mobile VARCHAR(20), role VARCHAR(20));
INSERT INTO tmp_assign VALUES
  ('اپلیکیشن فروشگاه آنلاین', '09991867332', 'admin'),
  ('اپلیکیشن فروشگاه آنلاین', '09991867333', 'unit_manager'),
  ('اپلیکیشن فروشگاه آنلاین', '09991867334', 'supervisor'),
  ('اپلیکیشن فروشگاه آنلاین', '09991867335', 'member'),
  ('اپلیکیشن فروشگاه آنلاین', '09991867336', 'none'),
  ('کمپین بازاریابی پاییز',   '09991867334', 'admin'),
  ('کمپین بازاریابی پاییز',   '09991867336', 'supervisor'),
  ('کمپین بازاریابی پاییز',   '09991867332', 'member');

UPDATE project_members pm
  JOIN projects p    ON p.id = pm.project_id
  JOIN users owner   ON owner.id = p.user_id AND owner.mobile = '09991867331'
  JOIN users u       ON u.id = pm.user_id
  JOIN tmp_assign a  ON a.project_title = p.title COLLATE utf8mb4_unicode_ci AND a.mobile = u.mobile COLLATE utf8mb4_unicode_ci
  JOIN tmp_roles r   ON r.role = a.role
   SET pm.task_permissions = r.t, pm.permissions = r.p, pm.board_permissions = r.b, pm.board_view_users = r.v;

-- گزارش: نقش‌های اعمال‌شده
SELECT p.title AS project, CONCAT(u.first_name, ' ', u.last_name) AS member, u.mobile, a.role
  FROM tmp_assign a
  JOIN projects p ON p.title = a.project_title COLLATE utf8mb4_unicode_ci
  JOIN users owner ON owner.id = p.user_id AND owner.mobile = '09991867331'
  JOIN users u ON u.mobile = a.mobile COLLATE utf8mb4_unicode_ci
  JOIN project_members pm ON pm.project_id = p.id AND pm.user_id = u.id
 ORDER BY p.id, FIELD(a.role, 'admin', 'unit_manager', 'supervisor', 'member', 'none');

DROP TEMPORARY TABLE tmp_assign;
DROP TEMPORARY TABLE tmp_roles;
COMMIT;
