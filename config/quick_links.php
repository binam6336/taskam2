<?php
declare(strict_types=1);

/**
 * لینک‌های «دسترسی سریع»؛ کاربر در پروفایل انتخاب می‌کند. مسیرها نسبی‌اند.
 * از همان فهرست صفحه‌های سایدبار (config/sidebar.php) ساخته می‌شود تا هر صفحه‌ی جدید خودکار این‌جا هم باشد.
 * شناسه‌ها همان کلید صفحه‌اند (در users.quick_links ذخیره می‌شوند).
 */
$titles = [                       // نام مستقل‌تر از نام کوتاه سایدبار (بیرون از گروه‌ها خوانده می‌شود)
    'tasks'    => 'وظایف',
    'subjects' => 'لیبل‌های وظایف',
    'chat'     => 'گفتگوها',
    'tokens'   => 'توکن‌های API',
    'support'  => 'تیکت پشتیبانی',
    'tour'     => 'آموزش پنل',
];
$links = [];
foreach ((require __DIR__ . '/sidebar.php')['pages'] as $id => [$path, $icon, $label]) {
    $links[] = ['id' => $id, 'title' => $titles[$id] ?? $label, 'path' => $path, 'icon' => $icon];
}
return $links;
