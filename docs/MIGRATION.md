# مهاجرت از نسخه‌ی قدیمی

1. از دیتابیس و پوشه‌ی `uploads/` قدیمی بکاپ بگیرید.
2. دیتابیس جدید بسازید و `database/schema.sql` را اجرا کنید.
3. دامپ قدیمی (`mahyarsa_task.sql`) را در دیتابیسی موقت به نام `taskam_legacy` ایمپورت کنید.
4. روی دیتابیس **جدید** اجرا کنید: `database/migrations/001_import_from_legacy.sql`
   (خروجی آخر اسکریپت تعداد رکوردهای قدیمی و جدید را مقایسه می‌کند.)
5. محتویات `uploads/` قدیمی را در `storage/uploads/` کپی کنید (نام زیرپوشه‌ها یکسان است).
6. وارد شوید؛ رمزها تغییری نکرده‌اند و هش‌ها در اولین ورود به Argon2id ارتقا می‌یابند.

نگاشت‌ها: `users.password→password_hash` • `colleague_messages→chat_messages` • ربات تسکام با `is_bot=1` • توکن‌های API هش می‌شوند • شناسه‌های دسترسی سریع (`subject, hamkar, call_request, prpfile`) به (`subjects, colleagues, calls, profile`) تبدیل می‌شوند • جدول بلااستفاده‌ی `sidebar_manager_auth` منتقل نمی‌شود.
