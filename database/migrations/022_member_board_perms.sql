-- ============================================================================
--  نقش «عضو» پروژه در بورد همه‌ی جابه‌جایی‌ها را دارد جز تکمیل و جابه‌جایی آزاد (v3.39.0).
--  اعضایی که دقیقاً نقش «عضو» قبلی را داشتند (تسک: ویرایش اطلاعات + یادداشت، بدون دسترسی پروژه و برد، فقط تسک‌های خودشان)
--  دسترسی‌های برد نقش جدید را می‌گیرند تا همچنان «عضو» بمانند. اعضای سفارشی دست نمی‌خورند.
--  اگر اجرا نشود، برنامه یک بار خودش آن را اجرا می‌کند.
-- ============================================================================
UPDATE `project_members`
   SET `board_permissions` = '["to_todo","to_progress","to_review","block","unblock","move_back"]'
 WHERE `task_permissions` = '["edit_info","notes"]'
   AND (`permissions` IS NULL OR `permissions` = '[]')
   AND (`board_permissions` IS NULL OR `board_permissions` = '[]')
   AND (`board_view_users` IS NULL OR `board_view_users` = '[]');
