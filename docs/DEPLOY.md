# استقرار

## Apache
Document Root = `public/`. فایل `public/.htaccess` آماده است (`mod_rewrite` لازم است).
اگر فقط به ریشه‌ی پروژه دسترسی دارید، `.htaccess` ریشه کار هدایت و مسدودسازی را انجام می‌دهد.

## Nginx
```nginx
server {
    server_name example.com;
    root /var/www/taskam/public;
    index index.php;
    client_max_body_size 55M;

    location / { try_files $uri /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
}
```

## php.ini پیشنهادی
`upload_max_filesize = 50M`, `post_max_size = 55M`, `expose_php = Off`.

## هوش مصنوعی (کارلیکس و دستیار بورد) زیر بار هم‌زمان (v7.26)
هر فراخوانی مدل چند تا چند ده ثانیه طول می‌کشد. پیام دستیار بورد در همان درخواست وب اجرا می‌شود و در این مدت یک worker PHP و یک اتصال دیتابیس را نگه می‌دارد.

- **اجراکننده‌ی سمت سرور (توصیه‌شده):** درخواست‌های «@کارلیکس» در یادداشت تسک را از worker وب بیرون می‌برد. بعد از بسته شدن مرورگر هم اجرا می‌شوند و درخواست قدیمی‌تر از `AI_REQUEST_EXPIRE_MINUTES` (پیش‌فرض ۱۵) «منقضی شد» می‌گیرد:
  ```
  * * * * * php /var/www/karlix/bin/ai-worker.php --procs=4 >/dev/null 2>&1
  ```
  مرورگر تا وقتی تپش این اسکریپت (`storage/cache/ai-worker.beat`) تازه است فقط وضعیت را می‌پرسد. اگر cron متوقف شود، بعد از ۹۰ ثانیه رفتار قبلی (اجرا از مرورگر) برمی‌گردد. با `AI_WORKER=false` خاموش می‌شود.
- **اندازه‌ی استخر PHP-FPM:** `pm.max_children` باید بزرگ‌تر از «تعداد کاربرانی که هم‌زمان از دستیار بورد استفاده می‌کنند + ترافیک عادی» باشد. یک قاعده‌ی ساده: `pm.max_children ≥ AI_MAX_CONCURRENT + AI_MAX_WAITING + ۱۰`. اگر استخر کوچک است، `AI_MAX_CONCURRENT` و `AI_MAX_WAITING` را کم کنید (درخواست اضافه به‌جای اشغال worker فوراً «شلوغ است» می‌گیرد و خودکار دوباره امتحان می‌شود)، یا مسیر `/board/ai/message` را به استخر جدای PHP-FPM بفرستید:
  ```nginx
  location = /board/ai/message {
      include fastcgi_params;
      fastcgi_param SCRIPT_FILENAME $document_root/index.php;
      fastcgi_read_timeout 300s;
      fastcgi_pass unix:/run/php/php8.2-fpm-ai.sock;   # استخر جدا با pm.max_children محدود
  }
  ```
- **Timeout پراکسی:** پاسخ دستیار بورد اگر اتصال قطع شود از `/board/ai/status` بازیابی می‌شود، ولی `fastcgi_read_timeout`/`proxy_read_timeout` حدود ۳۰۰ ثانیه از قطع زودهنگام جلوگیری می‌کند.
- **صف سراسری:** حداکثر `AI_MAX_CONCURRENT` فراخوانی هم‌زمان در کل سرور به ترتیب ورود (جدول `ai_queue`؛ با چند سرور روی یک دیتابیس هم سراسری است). منتظرها حداکثر `AI_QUEUE_SECONDS` صبر می‌کنند و بیش از `AI_MAX_WAITING` منتظر پذیرفته نمی‌شود.

## نرم‌افزار اندروید (APK)
فایل نصبی اندروید را با همین نام در این مسیر روی سرور بگذارید:

```
storage/app/karlix.apk
```

صفحه‌ی «تنظیمات ← نصب» دکمه‌ی «دریافت APK» دارد که فایل را از همین مسیر و از طریق `GET /install/android` (فقط برای کاربر واردشده) می‌فرستد. پوشه‌ی `storage` از وب مستقیم در دسترس نیست. اگر فایل نباشد، کاربر پیام «خطا در دریافت نرم‌افزار، لطفاً بعداً تلاش کنید.» می‌بیند. برای نسخه‌ی جدید فقط فایل را جایگزین کنید (حجم و تاریخ به‌روزرسانی خودکار نمایش داده می‌شود). فایل در git ثبت نمی‌شود.

## تسک‌های تکرارشونده (v8.1)

پس از انتشار، `php bin/migrate.php` را برای ساخت سری‌ها و دو ستون افزایشی تسک‌ها اجرا کنید. نوبت بعد در همان درخواست تکمیل نهایی، سمت سرور ساخته می‌شود؛ به باز بودن مرورگر وابسته نیست. برای بازیابی موارد متوقف‌شده بر اثر خطای موقت، محدودیت نرخ ساخت یا بازگردانی نوبت قبلی، cron زیر را با مسیر واقعی پروژه فعال کنید:

```cron
* * * * * php /path/to/karlix/bin/recurring-tasks.php >> /path/to/karlix/storage/logs/recurring-tasks.log 2>&1
```

هر اجرا حداکثر ۱۰۰ سری را بررسی می‌کند؛ هم‌پوشانی اجراها ایمن است. پروژه/تسک مسدود، حساب غیرفعال یا حذف عضویت لازم، ساخت نوبت را تا رفع مانع متوقف می‌کند. ماهانه بر پایه ماه میلادی است و روز اصلی ماه را حفظ می‌کند. تنظیمات از هر نوبت روی کل سری اعمال می‌شوند؛ تسک‌ها، گزارش‌ها و پیوست‌های قبلی حذف نمی‌شوند.

### صف رمزگذاری محتوای قدیمی
برای ادامه صف با صفحه بسته، Cron هر دقیقه `php /absolute/path/to/bin/content-crypto-worker.php` را اجرا کند. صف از پنل مدیر شروع می‌شود؛ اجرای worker به‌تنهایی صف جدید نمی‌سازد. مسیر PHP وب و Cron باید تنظیمات کلید یکسان نصب را بخوانند. جزئیات در `docs/security/content-encryption.md` آمده است.
