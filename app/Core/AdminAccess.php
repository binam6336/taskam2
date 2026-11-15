<?php
declare(strict_types=1);

namespace App\Core;

/**
 * دسترسی‌های پنل مدیر بر اساس نقش (v7.4، SEC-001 گزارش امنیتی).
 *
 * - PERMS: فهرست همه‌ی کارهایی که در پنل مدیر انجام می‌شود (هر متد AdminController یکی از این‌ها را می‌خواهد).
 * - نقش «مدیر کل» (super) همیشه همه را دارد و قابل محدود کردن نیست؛ صفحه‌ی «تنظیمات ← دسترسی» (/admin/access) فقط مال اوست.
 * - دسترسی نقش‌های دیگر (internal / support) در content_texts با کلید CONTENT_KEY (JSON {نقش: [دسترسی‌ها]}) ذخیره می‌شود؛
 *   نبودنش = DEFAULTS.
 * - هر مدیر می‌تواند دسترسی اختصاصی داشته باشد: users.admin_permissions = آرایه‌ی JSON (جایگزین دسترسی نقش)؛ NULL = طبق نقش.
 */
final class AdminAccess
{
    public const CONTENT_KEY = 'admin.access';

    /** گروه‌ها برای نمایش در صفحه‌ی دسترسی */
    public const GROUPS = [
        'dash'     => ['fa-gauge-high', 'پیشخوان و گزارش'],
        'users'    => ['fa-user-shield', 'کاربران'],
        'content'  => ['fa-pen-to-square', 'محتوا'],
        'support'  => ['fa-life-ring', 'پشتیبانی'],
        'settings' => ['fa-gear', 'تنظیمات'],
    ];

    /** کلید => [گروه، عنوان، توضیح] */
    public const PERMS = [
        'view_analysis'         => ['dash', 'پیشخوان مدیر و آمار', 'صفحه‌ی پیشخوان پنل مدیر، آمار سیستم و فهرست‌های کاربران/پروژه‌ها/تسک‌ها با جزئیات پروژه.'],
        'view_users'            => ['users', 'دیدن فهرست کاربران', 'صفحه‌ی «لیست کاربران» و پنجره‌ی جزئیات هر کاربر (پروژه‌ها و تسک‌ها).'],
        'add_user'              => ['users', 'افزودن کاربر', 'ساخت کاربر عادی تازه از پنل مدیر.'],
        'edit_user'             => ['users', 'ویرایش کاربر', 'ویرایش نام، موبایل، ایمیل و رمز کاربران عادی (تغییر رمز یا موبایل رمز خود مدیر را هم می‌خواهد).'],
        'toggle_status'         => ['users', 'تغییر وضعیت کاربر', 'تأیید، غیرفعال یا مسدود کردن کاربران عادی.'],
        'delete_user'           => ['users', 'حذف کاربر', 'حذف کامل کاربر عادی و همه‌ی داده‌هایش (برگشت‌ناپذیر).'],
        'manage_admins'         => ['users', 'مدیریت مدیران', 'ساخت مدیر تازه، ویرایش/تغییر وضعیت/حذف مدیران دیگر و تعیین نقش مدیر (جز حساب مدیر کل اصلی).'],
        'impersonate'           => ['users', 'ورود به پنل کاربر', 'باز کردن پنل کاربر در تب جدید (و «ورود به پروژه» از جزئیات پروژه)؛ همه‌ی کارهای داخل آن در لاگ امنیتی ثبت می‌شود.'],
        'manage_impersonations' => ['users', 'مدیریت پنجره‌های ورود', 'دیدن پنجره‌های باز «ورود به پنل کاربر» همه‌ی مدیران و بستن آن‌ها.'],
        'edit_sidebar'          => ['content', 'ویرایش سایدبار', 'چیدمان، نام‌ها و توضیح‌های منوی پنل کاربر (محتوا ← سایدبار).'],
        'edit_tour'             => ['content', 'ویرایش تور آموزشی', 'متن مراحل تور آموزشی کاربران.'],
        'edit_board'            => ['content', 'ویرایش بورد پیش‌فرض', 'ستون‌های بورد پیش‌فرض پروژه‌ها.'],
        'edit_placeholders'     => ['content', 'ویرایش Placeholder', 'متن راهنمای داخل فیلدهای پنل کاربر.'],
        'edit_errors'           => ['content', 'ویرایش متن خطاها', 'متن پیام‌های خطای نمایش‌داده‌شده به کاربران.'],
        'support_view'          => ['support', 'دیدن تیکت‌ها', 'فهرست و گفتگوی تیکت‌های پشتیبانی.'],
        'support_reply'         => ['support', 'پاسخ به تیکت', 'نوشتن پاسخ (و پیوست) در تیکت‌ها.'],
        'support_manage'        => ['support', 'مدیریت تیکت', 'تغییر وضعیت و حذف تیکت‌ها.'],
        'view_security'         => ['settings', 'دیدن لاگ امنیتی', 'صفحه‌ی «تنظیمات ← امنیت»: ورود/خروج مدیران به پنل کاربران و تغییرات ثبت‌شده.'],
        'view_panel_docs'       => ['settings', 'مستندات پنل', 'صفحه‌ی محدودیت‌ها و قواعد پنل.'],
        'chat_encrypt'          => ['settings', 'رمزگذاری پیام‌های قدیمی', 'رمزگذاری یک‌باره‌ی پیام‌ها و فایل‌های گفتگوی قدیمی (پیشخوان مدیر).'],
    ];

    /** دسترسی پیش‌فرض نقش‌ها (مدیر کل همیشه همه) */
    public const DEFAULTS = [
        'internal' => ['view_analysis', 'view_users', 'add_user', 'edit_user', 'toggle_status', 'impersonate', 'manage_impersonations',
            'edit_sidebar', 'edit_tour', 'edit_board', 'edit_placeholders', 'edit_errors', 'support_view', 'support_reply', 'support_manage', 'view_security', 'view_panel_docs'],
        'support'  => ['view_users', 'support_view', 'support_reply', 'support_manage', 'view_panel_docs'],
    ];

    /** صفحه‌ی هر دسترسی در منو (برای فرود بعد از ورود و فهرست «اولین صفحه‌ی مجاز») */
    public const LANDING = [
        'view_analysis' => '/admin/analysis', 'view_users' => '/admin', 'support_view' => '/admin/support',
        'edit_sidebar' => '/admin/content/sidebar', 'edit_tour' => '/admin/content/tour', 'edit_board' => '/admin/content/board',
        'edit_placeholders' => '/admin/content/placeholders', 'edit_errors' => '/admin/content/errors',
        'view_security' => '/admin/security', 'view_panel_docs' => '/admin/docs', 'manage_impersonations' => '/admin/impersonations',
    ];

    /** پاک‌سازی فهرست: فقط کلیدهای معتبر، بدون تکرار، به ترتیب PERMS */
    public static function clean(array $perms): array
    {
        return array_values(array_intersect(array_keys(self::PERMS), array_map('strval', $perms)));
    }

    /** دسترسی همه‌ی نقش‌ها (super = همه) */
    public static function roles(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $saved = [];
        try {
            $raw = DB::value('SELECT body FROM content_texts WHERE `key` = ?', [self::CONTENT_KEY]);
            $saved = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
        } catch (\Throwable $e) {
            $saved = [];
        }
        $out = ['super' => array_keys(self::PERMS)];
        foreach (self::DEFAULTS as $role => $def) {
            $out[$role] = isset($saved[$role]) && is_array($saved[$role]) ? self::clean($saved[$role]) : $def;
        }
        return $cache = $out;
    }

    /** ذخیره‌ی دسترسی نقش‌ها (super قابل تغییر نیست) */
    public static function saveRoles(array $map, int $by): void
    {
        $data = [];
        foreach (array_keys(self::DEFAULTS) as $role) {
            $data[$role] = self::clean((array) ($map[$role] ?? []));
        }
        DB::run('INSERT INTO content_texts (`key`, body, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body), updated_by = VALUES(updated_by)',
            [self::CONTENT_KEY, json_encode($data, JSON_UNESCAPED_UNICODE), $by]);
    }

    public static function resetRoles(): void
    {
        DB::run('DELETE FROM content_texts WHERE `key` = ?', [self::CONTENT_KEY]);
    }

    /** دسترسی اختصاصی یک مدیر (users.admin_permissions)؛ null = طبق نقش */
    public static function custom(array $u): ?array
    {
        $raw = $u['admin_permissions'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $v = json_decode($raw, true);
        return is_array($v) && array_values($v) === $v ? self::clean($v) : null;
    }

    /** دسترسی نهایی یک کاربر: غیرمدیر = هیچ؛ مدیر کل = همه؛ دسترسی اختصاصی؛ وگرنه نقش */
    public static function of(array $u): array
    {
        $role = Auth::adminRole($u);
        if ($role === null) {
            return [];
        }
        if ($role === 'super') {
            return array_keys(self::PERMS);
        }
        return self::custom($u) ?? (self::roles()[$role] ?? []);
    }

    /** اولین صفحه‌ی پنل مدیر که کاربر اجازه‌اش را دارد (null = هیچ) */
    public static function landing(array $u): ?string
    {
        $have = self::of($u);
        foreach (self::LANDING as $perm => $path) {
            if (in_array($perm, $have, true)) {
                return $path;
            }
        }
        return null;
    }
}
