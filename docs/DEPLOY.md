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
