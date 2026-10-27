# معماری

درخواست → `public/index.php` → `App::run()` (هدرهای امنیتی، سشن) → `Router` (تطبیق مسیر + میدلورها) → `Controller/ApiController` → `Service` (منطق) → `Model` (SQL) → `View` یا JSON.

- **میدلورها**: `guest`, `auth` (کاربر فعال), `authany` (هر وضعیت), `panel` (پنل کاربر؛ ادمین به /admin می‌رود), `admin`, `csrf` (خودکار برای POST وب), `api` (Bearer/سشن + CSRF در حالت کوکی)
- **Access**: تنها جایی که مجوز «سازنده / مسئول / همکار» تصمیم‌گیری می‌شود.
- **Services** (`TaskService`, `CallService`, `ProjectService`, `ColleagueService`) بین پنل وب و API مشترک‌اند؛ هر قانونی فقط یک‌بار پیاده شده.
- **API spec** (`config/api_spec.php`) → صفحه‌ی `/docs` و `GET /api/v1/`.
- **فایل‌ها**: `Uploader` (اعتبارسنجی/ذخیره) و `FileController` (سرو با دسترسی).
