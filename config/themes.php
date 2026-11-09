<?php
declare(strict_types=1);

/**
 * تم‌های ظاهری پنل (v7.0): سه پالت × روشن/تیره. کلید = data-theme روی <html>؛ mode = data-mode (light|dark).
 * تم در دیتابیس ذخیره نمی‌شود: کوکی tk_theme روی همان دستگاه (هر دستگاه تم خودش را دارد؛ user_theme()).
 * palette: [dark, main, accent, card, bg] — رنگ‌های پایه‌ی هر پالت (app.css توکن‌ها را از همین‌ها می‌سازد).
 * preview: رنگ‌های پیش‌نمایش کارت انتخاب تم [پس‌زمینه، سایدبار، کارت، رنگ اصلی]؛ meta: theme-color مرورگر.
 */
return [
    'green-light'    => ['title' => 'سبز تسکام — روشن', 'desc' => 'Taskam Green، روشن (پیش‌فرض)', 'mode' => 'light', 'meta' => '#FBFAEC',
        'palette' => ['#2F4A2F', '#4A7C45', '#8DBB86', '#F4F1D8', '#FBFAEC'], 'preview' => ['#FBFAEC', '#F8F6E6', '#FFFFFF', '#4A7C45']],
    'green-dark'     => ['title' => 'سبز تسکام — تیره', 'desc' => 'Taskam Green، تیره', 'mode' => 'dark', 'meta' => '#08100B',
        'palette' => ['#2F4A2F', '#4A7C45', '#8DBB86', '#F4F1D8', '#FBFAEC'], 'preview' => ['#08100B', '#0F1A13', '#142219', '#4FC46A']],
    'momentum-light' => ['title' => 'Action & Momentum — روشن', 'desc' => 'نارنجی پرانرژی، روشن', 'mode' => 'light', 'meta' => '#F8F7F5',
        'palette' => ['#202631', '#F07836', '#FFB36B', '#F4E9DF', '#F8F7F5'], 'preview' => ['#F8F7F5', '#FAF4EE', '#FFFFFF', '#F07836']],
    'momentum-dark'  => ['title' => 'Action & Momentum — تیره', 'desc' => 'نارنجی پرانرژی، تیره', 'mode' => 'dark', 'meta' => '#0d0f13',
        'palette' => ['#202631', '#F07836', '#FFB36B', '#F4E9DF', '#F8F7F5'], 'preview' => ['#0d0f13', '#141820', '#1b2029', '#F07836']],
    'violet-light'   => ['title' => 'Midnight Violet — روشن', 'desc' => 'بنفش، روشن', 'mode' => 'light', 'meta' => '#FAF9FF',
        'palette' => ['#241B3D', '#6D4AFF', '#A78BFA', '#F0ECFF', '#FAF9FF'], 'preview' => ['#FAF9FF', '#F6F3FF', '#FFFFFF', '#6D4AFF']],
    'violet-dark'    => ['title' => 'Midnight Violet — تیره', 'desc' => 'بنفش، تیره', 'mode' => 'dark', 'meta' => '#0e0b17',
        'palette' => ['#241B3D', '#6D4AFF', '#A78BFA', '#F0ECFF', '#FAF9FF'], 'preview' => ['#0e0b17', '#151027', '#1c1633', '#A78BFA']],
];
