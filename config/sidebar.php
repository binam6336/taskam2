<?php
/**
 * سایدبار پنل کاربر.
 * pages: صفحه‌هایی که ادمین می‌تواند در «محتوا ← سایدبار» انتخاب کند — کلید (همان page صفحه برای فعال بودن و شمارنده) => [مسیر، آیکون، نام پیش‌فرض]
 * default: چیدمان پیش‌فرض (تا وقتی ادمین چیدمان را ذخیره نکرده)؛ هر گزینه:
 *   ['t' => 'page', 'page' => کلید، 'label' => نام، 'soon' => به‌زودی/غیرفعال]
 *   ['t' => 'link', 'url' => لینک خارجی، 'label' => نام]
 *   ['t' => 'group', 'id' => شناسه، 'icon' => آیکون، 'label' => نام، 'items' => [...]]
 */
return [
    // توضیح کوتاه هر صفحه و گروه در پاپ‌آپ زیرمنوهای سایدبار (v5.8)؛ کلید گروه: group:<id>
    'desc' => [
        'group:tasks' => 'برنامه‌ریزی و پیگیری کارها',
        'tasks' => 'لیست تسک‌های من و تیم',
        'calendar' => 'نمای تقویمی سررسیدها',
        'board' => 'بورد اختصاصی هر پروژه',
        'subjects' => 'دسته‌بندی و رنگ‌بندی تسک‌ها',
        'texts' => 'یادداشت‌های رنگی شخصی',
        'group:team' => 'همکاران، پروژه‌ها و گفتگو',
        'colleagues' => 'اعضای تیم و دسترسی‌ها',
        'projects' => 'ساخت و مدیریت پروژه‌ها',
        'chat' => 'پیام‌های داخلی تیم',
        'group:api' => 'اتصال تسکام به سیستم‌های دیگر',
        'tokens' => 'ساخت و مدیریت کلیدهای دسترسی',
        'docs' => 'راهنمای استفاده از وب‌سرویس',
        'group:settings' => 'حساب کاربری و نصب',
        'profile' => 'اطلاعات حساب و امنیت',
        'install' => 'نصب اپ روی دستگاه',
        'tour' => 'راهنمای کار با تسکام',
        'group:support' => 'کمک و ارتباط با ما',
        'support' => 'ارتباط با تیم پشتیبانی',
        'dashboard' => 'خلاصه‌ی کارهای امروز',
    ],
    'pages' => [
        'dashboard'  => ['/dashboard', 'fa-gauge-high', 'پیشخوان'],
        'tasks'      => ['/tasks', 'fa-list-check', 'تسک‌ها'],
        'calendar'   => ['/calendar', 'fa-calendar-days', 'تقویم'],
        'board'      => ['/board', 'fa-table-columns', 'بورد پروژه'],
        'subjects'   => ['/labels', 'fa-tags', 'لیبل‌ها'],
        'texts'      => ['/notes', 'fa-note-sticky', 'یادداشت‌ها'],
        'colleagues' => ['/colleagues', 'fa-users', 'همکاران'],
        'projects'   => ['/projects', 'fa-diagram-project', 'پروژه‌ها'],
        'chat'       => ['/chat', 'fa-comments', 'گفتگو'],
        'tokens'     => ['/tokens', 'fa-key', 'توکن API'],
        'docs'       => ['/docs', 'fa-book', 'مستندات API'],
        'profile'    => ['/profile', 'fa-user-gear', 'پروفایل'],
        'install'    => ['/install', 'fa-download', 'نصب'],
        'tour'       => ['/tour/start', 'fa-graduation-cap', 'آموزش'],
        'support'    => ['/support', 'fa-headset', 'تیکت'],
    ],
    'default' => [
        ['t' => 'page', 'page' => 'dashboard', 'label' => 'پیشخوان'],
        ['t' => 'group', 'id' => 'tasks', 'icon' => 'fa-layer-group', 'label' => 'وظایف', 'items' => [
            ['t' => 'page', 'page' => 'tasks', 'label' => 'تسک‌ها'],
            ['t' => 'page', 'page' => 'calendar', 'label' => 'تقویم'],
            ['t' => 'page', 'page' => 'board', 'label' => 'بورد پروژه'],
            ['t' => 'page', 'page' => 'texts', 'label' => 'یادداشت‌ها'],
            ['t' => 'page', 'page' => 'subjects', 'label' => 'لیبل‌ها'],
        ]],
        ['t' => 'group', 'id' => 'team', 'icon' => 'fa-people-group', 'label' => 'مدیریت تیم', 'items' => [
            ['t' => 'page', 'page' => 'colleagues', 'label' => 'همکاران'],
            ['t' => 'page', 'page' => 'projects', 'label' => 'پروژه‌ها'],
            ['t' => 'page', 'page' => 'chat', 'label' => 'گفتگو'],
        ]],
        ['t' => 'group', 'id' => 'api', 'icon' => 'fa-code', 'label' => 'وب سرویس', 'items' => [
            ['t' => 'page', 'page' => 'tokens', 'label' => 'توکن API'],
            ['t' => 'page', 'page' => 'docs', 'label' => 'مستندات API'],
        ]],
        ['t' => 'group', 'id' => 'settings', 'icon' => 'fa-gear', 'label' => 'تنظیمات', 'items' => [
            ['t' => 'page', 'page' => 'profile', 'label' => 'پروفایل'],
            ['t' => 'page', 'page' => 'install', 'label' => 'نصب'],
            ['t' => 'page', 'page' => 'tour', 'label' => 'آموزش'],
        ]],
        ['t' => 'group', 'id' => 'support', 'icon' => 'fa-life-ring', 'label' => 'پشتیبانی', 'items' => [
            ['t' => 'page', 'page' => 'support', 'label' => 'تیکت'],
        ]],
    ],
];
