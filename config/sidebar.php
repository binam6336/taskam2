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
        'group:api' => 'اتصال کارلیکس به سیستم‌های دیگر',
        'tokens' => 'ساخت و مدیریت کلیدهای دسترسی',
        'docs' => 'راهنمای استفاده از وب‌سرویس',
        'group:settings' => 'حساب کاربری و نصب',
        'profile' => 'اطلاعات حساب و امنیت',
        'install' => 'نصب اپ روی دستگاه',
        'tour' => 'راهنمای کار با کارلیکس',
        'group:support' => 'کمک و ارتباط با ما',
        'support' => 'ارتباط با تیم پشتیبانی',
        'dashboard' => 'خلاصه‌ی کارهای امروز',
    ],
    'pages' => [
        'dashboard'  => ['/dashboard', 'fa-house', 'پیشخوان'],
        'tasks'      => ['/tasks', 'fa-list-check', 'تسک‌ها'],
        'calendar'   => ['/calendar', 'fa-calendar-days', 'تقویم'],
        'board'      => ['/board', 'fa-table-columns', 'بورد پروژه'],
        'subjects'   => ['/labels', 'fa-tag', 'لیبل‌ها'],
        'texts'      => ['/notes', 'fa-note-sticky', 'یادداشت‌ها'],
        'colleagues' => ['/colleagues', 'fa-users', 'همکاران'],
        'projects'   => ['/projects', 'fa-folder', 'پروژه‌ها'],
        'chat'       => ['/chat', 'fa-comments', 'گفتگو'],
        'tokens'     => ['/tokens', 'fa-key', 'توکن‌های API'],
        'docs'       => ['/docs', 'fa-book', 'مستندات API'],
        'profile'    => ['/profile', 'fa-user', 'پروفایل'],
        'install'    => ['/install', 'fa-download', 'نصب اپلیکیشن'],
        'tour'       => ['/tour/start', 'fa-compass', 'آموزش'],
        'support'    => ['/support', 'fa-life-ring', 'پشتیبانی'],
    ],
    // v7.16 (دموی UI): پیشخوان + چهار بخش «کار من / تیم / توسعه‌دهندگان / راهنما»؛ «پروفایل» در منوی حساب پایین سایدبار است
    'default' => [
        ['t' => 'page', 'page' => 'dashboard', 'label' => 'پیشخوان'],
        ['t' => 'group', 'id' => 'tasks', 'icon' => 'fa-list-check', 'label' => 'کار من', 'items' => [
            ['t' => 'page', 'page' => 'tasks', 'label' => 'تسک‌ها'],
            ['t' => 'page', 'page' => 'board', 'label' => 'بورد پروژه'],
            ['t' => 'page', 'page' => 'calendar', 'label' => 'تقویم'],
            ['t' => 'page', 'page' => 'texts', 'label' => 'یادداشت‌ها'],
        ]],
        ['t' => 'group', 'id' => 'team', 'icon' => 'fa-users', 'label' => 'تیم', 'items' => [
            ['t' => 'page', 'page' => 'projects', 'label' => 'پروژه‌ها'],
            ['t' => 'page', 'page' => 'colleagues', 'label' => 'همکاران'],
            ['t' => 'page', 'page' => 'chat', 'label' => 'گفتگو'],
            ['t' => 'page', 'page' => 'subjects', 'label' => 'لیبل‌ها'],
        ]],
        ['t' => 'group', 'id' => 'api', 'icon' => 'fa-code', 'label' => 'توسعه‌دهندگان', 'items' => [
            ['t' => 'page', 'page' => 'tokens', 'label' => 'توکن‌های API'],
            ['t' => 'page', 'page' => 'docs', 'label' => 'مستندات API'],
        ]],
        ['t' => 'group', 'id' => 'support', 'icon' => 'fa-life-ring', 'label' => 'راهنما', 'items' => [
            ['t' => 'page', 'page' => 'support', 'label' => 'پشتیبانی'],
            ['t' => 'page', 'page' => 'install', 'label' => 'نصب اپلیکیشن'],
            ['t' => 'page', 'page' => 'tour', 'label' => 'آموزش'],
        ]],
    ],
];
