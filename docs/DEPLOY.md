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

## نرم‌افزار اندروید (APK)
فایل نصبی اندروید را با همین نام در این مسیر روی سرور بگذارید:

```
storage/app/taskam.apk
```

صفحه‌ی «تنظیمات ← نصب» دکمه‌ی «دریافت APK» دارد که فایل را از همین مسیر و از طریق `GET /install/android` (فقط برای کاربر واردشده) می‌فرستد. پوشه‌ی `storage` از وب مستقیم در دسترس نیست. اگر فایل نباشد، کاربر پیام «خطا در دریافت نرم‌افزار، لطفاً بعداً تلاش کنید.» می‌بیند. برای نسخه‌ی جدید فقط فایل را جایگزین کنید (حجم و تاریخ به‌روزرسانی خودکار نمایش داده می‌شود). فایل در git ثبت نمی‌شود.
