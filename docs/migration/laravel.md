# بک‌اند کاربری Laravel 12 — نسخه 9.0

## مسیر فعال

پیش‌فرض `USER_ROUTER=laravel` است. ۲۳۰ Route بخش کاربر (شامل ۶۰ Route API) در `laravel/routes/web.php` و `laravel/routes/api.php` تعریف شده‌اند. `EndpointController` کنترلرهای `KarlixLaravel\Http\Controllers` را اجرا می‌کند؛ هیچ فراخوانی `LegacyRouter::dispatch` یا Controller کاربری `App\Controllers` در این مسیر وجود ندارد. `EndpointController` فقط پل تزریق Request و حفظ پارامترهای PID است؛ منطق عملیات در کنترلرهای منتقل‌شده اجرا می‌شود.

## ساختار

- `Http/Controllers`: تمام کنترلرهای وب کاربری و API v1؛ Controller و ApiController از Controller لاراول مشتق می‌شوند.
- `Http/Requests/PanelRequest`: Form Request برای رد ورودی‌های غیرمتنی؛ قواعد دقیق دامنه و پیام‌های قبلی در Serviceها حفظ شده‌اند.
- `Services`: منطق پروژه، تسک، سطح دسترسی، تکرار، گزارش، چت و دستیار AI در فضای نام جدید.
- `Repositories`: متدهای استاتیک مدل‌های قبلی برای سازگاری و SQLهای دامنه؛ اتصال اصلی آن‌ها از Database Manager لاراول گرفته می‌شود. این مخزن‌ها حذف یا با mass assignment جایگزین نشده‌اند.
- `Models`: مدل‌های Eloquent متناظر جدول‌ها و روابط اصلی، جلوگیری از mass assignment عمومی، رمزگشایی محتوای حفاظت‌شده و پنهان‌سازی هش‌ها. جدول‌های با کلید مرکب برای نوشتن از Repository/Query Builder استفاده می‌کنند؛ save/delete عمومی روی مدل مرکب مسدود است.
- `Auth/Authentication`: منطق احراز هویت کاربر، قفل ورود، ارتقای هش، کنترل وضعیت و ابطال توکن منتقل شده است. Guard سفارشی `karlix` در Laravel ثبت شده و هویت نشست یا Bearer را در دسترس Policyها قرار می‌دهد.
- `Policies`: سیاست‌های Task و Project در Gate ثبت شده‌اند. Serviceهای منتقل‌شده نیز کنترل‌های جزئی و وابسته به پروژه را حفظ می‌کنند؛ افزودن Policy جای بررسی‌های Service را نگرفته است.
- `Http/Middleware`: حل PID مسیر، احراز هویت، CSRF، سطح دسترسی، نرخ، CORS و حساب غیرفعال در pipeline لاراول.
- `Http/Response` و `CompletedResponse`: پاسخ ترمینال کنترلر به Response یا StreamedResponse واقعی Laravel تبدیل می‌شود؛ دیگر exit در کنترلرهای منتقل‌شده نیست. استثناهای داخلی به خطای عمومی با کد پیگیری تبدیل می‌شوند.
- `Console/Commands`: صف AI، آماده‌سازی تکرار و یادآوری با Artisan و همان جدول‌ها و قفل‌های قبلی؛ Cronهای `bin/` به موتور انتخاب‌شده می‌روند.
- `Session/SharedStore`: Store استاندارد Laravel با داده‌های کمکی داخل سطل مجزای نشست مشترک؛ flash، ورودی قبلی و کلیدهای تو در تو پشتیبانی می‌شوند و scoped بودن پنجره‌های مدیر حفظ می‌شود. `SESSION_DRIVER=db|file` همچنان همان معنای قبلی را دارد.
- `Files/Uploader` و `View/PhpView`: فایل و قالب کاربری مستقل؛ قالب‌های PHP بدون تغییر طراحی فرانت‌اند به `resources/views` منتقل شده‌اند.

## بخش مشترکِ حفظ‌شده برای سازگاری ادمین

جدول نشست `user_sessions`، کوکی و PHP Session driver، قرارداد CSRF، PID، impersonation، Crypto/ContentCipher، کلیدها، Env، Audit، Log، محدودکننده نرخ و توابع عمومی همان زیرساخت مشترک قبلی‌اند. این‌ها با Session/Cookie/Encryption پیش‌فرض و ناسازگار Laravel جایگزین نشده‌اند. تغییر قالب نشست می‌توانست ورود ادمین یا پنجره‌های impersonation را خراب کند. این تصمیم به معنی حفظ Controller یا Service دامنه کاربری قدیمی در مسیر Laravel نیست.

کدهای اصلی ادمین و کتابخانه‌های مشترک آن در `app/` باقی مانده‌اند. نسخه قدیمی کد کاربری برای بازگشت/مقایسه نگه داشته شده و در مسیر فعال Laravel اجرا نمی‌شود. helperهای عمومی تاریخ، فرمت و آدرس مشترک‌اند؛ helper قدیمی auth_user و ثابت Priority نیز برای سازگاری ادمین باقی مانده‌اند. مسیر احراز هویت کنترلرهای کاربری از کلاس جدید عبور می‌کند.

## نصب و بازگشت

1. PHP 8.2+ و افزونه‌های Composer/Laravel از جمله DOM، Tokenizer، PDO MySQL و افزونه‌های قبلی پنل لازم است.
2. `composer install --working-dir=laravel --no-dev --prefer-dist`
3. `USER_ROUTER=laravel`؛ اگر نصب قبلی این گزینه را legacy گذاشته است باید صریح تغییر کند.
4. `.env`، کلیدها، فایل‌ها و دیتابیس موجود حفظ شوند. هیچ migrate/seed/reset مربوط به Laravel اجرا نشود. ساختار دیتابیس این نسخه تغییر نکرده است.
5. برای بازگشت موقت فقط `USER_ROUTER=legacy` بگذارید؛ تبدیل داده یا نشست لازم نیست.

vendor در گیت نیست. روی هاست فاقد Composer می‌توان وابستگی‌ها را در محیط ساخت نصب و پوشه vendor را همراه خروجی منتقل کرد. `php artisan migrate` و ساخت جدول‌های Laravel مجاز یا لازم نیستند. استفاده از Octane یا worker دائمی HTTP در این نسخه پشتیبانی نمی‌شود، چون زیرساخت مشترک نشست/Request برای PHP-FPM/Apache/LiteSpeed در هر درخواست ساخته می‌شود.

## تست و محدودیت ادعای سازگاری

- اجرای واقعی روی PHP 8.4 و MariaDB 11.8؛ dependency lock برای حداقل PHP 8.2 حل شده و check-platform-reqs و composer audit موفق‌اند. همه نسخه‌های PHP/MySQL تست نشده‌اند.
- ۷۴ مقایسه خواندن/خطا/CSRF/OPTIONS و ادمین با موتور قدیمی؛ فایل `bin/test-laravel-contracts.js`.
- فلو واقعی Bearer: ساخت پروژه، تسک تکراری، ویرایش، یادداشت شخصی، جستجو، منع نوشتن توکن read و IDOR بین کاربران.
- مرورگر واقعی: داشبورد، لیست تسک، تقویم، پروژه، یادداشت، پروفایل، چت، بورد و تحلیل؛ ثبت گزارش همراه فایل و آپلود/دانلود آواتار؛ رد title آرایه‌ای با INVALID_INPUT؛ بدون خطای JS.
- نشست دیتابیسی و نشست فایلی آزمایش شده‌اند؛ runtime جداسازی سطل Laravel، ماندگاری داده و flash را هم بررسی می‌کند.
- `bin/test-recurrence.php --laravel`، `bin/test-project-isolation.php --laravel` و `bin/test-storage.php --laravel` روی سرویس‌های جدید اجرا می‌شوند.
- فرمان AI با صف خالی و `--once --procs=1` و فرمان تکرار اجرا شده‌اند؛ تماس واقعی مدل خارجی آزمایش نشده است.
- `bin/test-laravel-runtime.php`: تمام ۲۳۰ handler کلاس Laravel دارند؛ Eloquent، رمزگشایی، عدم افشای هش، Policy، Guard و اشتراک تراکنش بررسی می‌شود.
- `bin/test-laravel-sessions.js`: درخواست دیررس Service Worker با شناسه مهمان، عدم تثبیت نشست، ماندگاری ورود، ورود مدیر به پنل کاربر، منع تغییر رمز در پنجره و حفظ نشست اصلی ادمین بررسی می‌شود.
- تست عدم افشای پیوست تسک/گزارش محرمانه حتی با لینک اشتراک عمومی اضافه شده است؛ SELECT مسیر فایل اصلاح شد تا پرچم محرمانگی و مسئولان گروه را برای تصمیم دسترسی بخواند. نوشتن API با کوکی بدون CSRF نیز رد و با CSRF صحیح پذیرفته شد.
- تمام حالت‌های تجاری ۲۳۰ مسیر، ارسال Push واقعی، سرویس AI خارجی، هر پسوند فایل و بار سنگین هم‌زمان تست نشده‌اند. این گزارش ادعای تست جامع همه عملیات نیست.
- فایل‌های schema.sql، migrationها و Schema.php نسبت به نسخه قبل تغییر ندارند. تست‌های داده فقط روی دیتابیس _test انجام شده‌اند.

Graphify فراخوانی شد ولی `no_authorized_repositories` برگرداند؛ هیچ گراف مجازی برای ادعای پوشش کد استفاده نشده است.

## بازاجرای تست‌های یکپارچگی

این فرمان‌ها فقط با `.env` یک دیتابیس آزمایشی با پسوند `_test` اجرا شوند. fixtureها دو حساب و توکن آزمایشی می‌سازند و تست فلو در همان دیتابیس داده می‌نویسد. فایل خصوصی `storage/cache/laravel-test-fixtures.json` را منتشر یا کامیت نکنید. هیچ اعتبارنامه‌ای در خروجی تست چاپ نمی‌شود. دو سرور آزمایشی باید از همین DB استفاده کنند: `USER_ROUTER=legacy` روی 8081 و `USER_ROUTER=laravel` روی 8082؛ Document Root همان `public` و router توسعه باید درخواست ناموجود را به `public/index.php` هدایت کند.

```sh
php bin/test-laravel-runtime.php
php bin/test-recurrence.php --laravel
php bin/test-project-isolation.php --laravel
php bin/test-storage.php --laravel
php bin/test-workload.php --laravel
node bin/test-workload.js
node bin/test-laravel-contracts.js
php bin/create-laravel-fixtures.php
node bin/test-laravel-flows.js
node bin/test-laravel-sessions.js
node bin/test-laravel-browser.js
```

تست مرورگر به Playwright و Chromium نیاز دارد (`CHROMIUM_PATH` و در نصب بیرونی `NODE_PATH`). آدرس سرورها با `LEGACY_TEST_URL` و `LARAVEL_TEST_URL` قابل انتخاب است. مقایسهٔ نشست نیازمند `KARLIX_TEST_MOBILE` و `KARLIX_TEST_PASSWORD` و بخش ادمین نیازمند متغیرهای هم‌نام با `ADMIN_` است. در HTML تحلیل ادمین، ردیف audit زنده و ترتیب ردیف‌های امتیاز مساوی نرمال می‌شوند؛ وضعیت، هدرها، آمار و بقیهٔ محتوا مقایسه می‌شوند.
