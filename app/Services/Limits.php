<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Throttle;

/**
 * سقف ساخت داده برای هر کاربر (پنل وب و API مشترک): نرخ ساخت در دقیقه + سقف تعداد کل برای چند نوع داده.
 * جلوی پر کردن دیتابیس با اسکریپت و سیل اعلان/Push را می‌گیرد. اعدادش در «مستندات پنل» نمایش داده می‌شود.
 */
final class Limits
{
    /** حداکثر ساخت هر نوع داده در دقیقه */
    public const CREATE_PER_MIN = 60;

    /** سقف کل تعداد [جدول، ستون مالک، عنوان] */
    public const MAX_TOTAL = [
        'text'    => [500, 'texts', 'user_id', 'متن آماده'],
        'label'   => [100, 'subjects', 'user_id', 'لیبل'],
        'project' => [50, 'projects', 'user_id', 'پروژه'],
    ];

    public const KINDS = ['task' => 'تسک', 'note' => 'یادداشت', 'text' => 'متن آماده', 'label' => 'لیبل', 'project' => 'پروژه', 'call' => 'درخواست تماس'];

    /** null = مجاز؛ در غیر این صورت پیام خطا */
    public static function create(string $kind, int $uid): ?string
    {
        if (isset(self::MAX_TOTAL[$kind])) {
            [$max, $table, $col, $label] = self::MAX_TOTAL[$kind];
            if ((int) DB::value("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?", [$uid]) >= $max) {
                return 'به سقف ' . fa_num($max) . ' ' . $label . ' رسیده‌اید؛ برای ساخت مورد جدید، موارد قدیمی را حذف کنید.';
            }
        }
        if (!Throttle::attempt('create_' . $kind, (string) $uid, self::CREATE_PER_MIN, 60)) {
            return 'سرعت ساخت بیش از حد مجاز است؛ یک دقیقه بعد دوباره تلاش کنید.';
        }
        return null;
    }
}
