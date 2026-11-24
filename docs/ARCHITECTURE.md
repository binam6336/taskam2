# معماری

معماری فعال از نسخه ۹: `public/index.php` → زیرساخت مشترک Session/PID → Laravel Kernel/Router → Middleware/FormRequest → کنترلر کاربری → Service → Repository/Database Manager و Eloquent → قالب PHP یا Response لاراول. مسیر `/admin` همچنان روی روتر و کنترلر ادمین قبلی است. [نصب، مرز زیرساخت مشترک، تست و بازگشت](migration/laravel.md).

مسیر قبلی برای ادمین و حالت `USER_ROUTER=legacy`:

درخواست → `public/index.php` → `App::run()` (هدرهای امنیتی، سشن) → `Router` (تطبیق مسیر + میدلورها) → `Controller/ApiController` → `Service` (منطق) → `Model` (SQL) → `View` یا JSON.

- **میدلورها**: `guest`, `auth` (کاربر فعال), `authany` (هر وضعیت), `panel` (پنل کاربر؛ ادمین به /admin می‌رود), `admin`, `csrf` (خودکار برای POST وب), `api` (Bearer/سشن + CSRF در حالت کوکی)
- کدهای دامنهٔ فعال کاربر در `laravel/app/Services` و `laravel/app/Repositories` قرار دارند؛ نسخهٔ `app/` فقط برای ادمین/بازگشت نگه داشته شده است.
- **Access**: تنها جایی که مجوز «سازنده / مسئول / همکار» تصمیم‌گیری می‌شود.
- **Services** (`TaskService`, `ProjectService`, `ColleagueService`) بین پنل وب و API مشترک‌اند؛ هر قانونی فقط یک‌بار پیاده شده.
- **API spec** (`config/api_spec.php`) → صفحه‌ی `/docs` و `GET /api/v1/`.
- **فایل‌ها**: `Uploader` (اعتبارسنجی/ذخیره) و `FileController` (سرو با دسترسی).
