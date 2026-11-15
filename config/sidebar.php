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
    // v7.18: «پروفایل، نصب، آموزش و پشتیبانی» در منوی حساب (کلیک روی کارت کاربر پایین سایدبار) هستند، نه در سایدبار؛
    // برای همین در فهرست صفحه‌های قابل انتخاب سایدبار نیستند (چیدمان ذخیره‌شده‌ی قدیمی هم بدون آن‌ها نمایش داده می‌شود).
    'pages' => [
        'dashboard'  => ['/dashboard', 'fa-house', 'پیشخوان'],
        'tasks'      => ['/tasks', 'fa-list-check', 'تسک‌ها'],
        'board'      => ['/board', 'fa-table-columns', 'بورد'],
        'calendar'   => ['/calendar', 'fa-calendar-days', 'تقویم'],
        'subjects'   => ['/labels', 'fa-tag', 'لیبل‌ها'],
        'texts'      => ['/notes', 'fa-note-sticky', 'یادداشت‌ها'],
        'colleagues' => ['/colleagues', 'fa-users', 'همکاران'],
        'chat'       => ['/chat', 'fa-comments', 'گفتگو'],
        'projects'   => ['/projects', 'fa-folder', 'پروژه‌ها'],
        'tokens'     => ['/tokens', 'fa-key', 'توکن API'],
        'docs'       => ['/docs', 'fa-book', 'مستندات API'],
    ],
    // v7.18: وظایف / مدیریت تیم / وب‌سرویس؛ هر گروه با کلیک روی عنوانش زیرمنوی خود را باز و بسته می‌کند (پاپ‌آپ گروه)
    'default' => [
        ['t' => 'page', 'page' => 'dashboard', 'label' => 'پیشخوان'],
        ['t' => 'group', 'id' => 'tasks', 'icon' => 'fa-list-check', 'label' => 'وظایف', 'items' => [
            ['t' => 'page', 'page' => 'tasks', 'label' => 'تسک‌ها'],
            ['t' => 'page', 'page' => 'board', 'label' => 'بورد'],
            ['t' => 'page', 'page' => 'calendar', 'label' => 'تقویم'],
            ['t' => 'page', 'page' => 'subjects', 'label' => 'لیبل‌ها'],
            ['t' => 'page', 'page' => 'texts', 'label' => 'یادداشت‌ها'],
        ]],
        ['t' => 'group', 'id' => 'team', 'icon' => 'fa-users', 'label' => 'مدیریت تیم', 'items' => [
            ['t' => 'page', 'page' => 'colleagues', 'label' => 'همکاران'],
            ['t' => 'page', 'page' => 'chat', 'label' => 'گفتگو'],
            ['t' => 'page', 'page' => 'projects', 'label' => 'پروژه‌ها'],
        ]],
        ['t' => 'group', 'id' => 'api', 'icon' => 'fa-code', 'label' => 'وب‌سرویس', 'items' => [
            ['t' => 'page', 'page' => 'tokens', 'label' => 'توکن API'],
            ['t' => 'page', 'page' => 'docs', 'label' => 'مستندات API'],
        ]],
    ],
];
