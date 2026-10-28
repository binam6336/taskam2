<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/**
 * محتوای قابل ویرایش توسط ادمین. پیش‌فرض‌ها در config هستند و فقط متن‌های تغییرکرده در content_texts ذخیره می‌شوند؛
 * پس با اضافه شدن متن جدید یا اجرا نشدن migration، همان پیش‌فرض‌ها نمایش داده می‌شوند.
 */
final class ContentModel
{
    public const MAX_LEN = 500;

    /** تعریف متن‌های تور: شناسه => [گروه، محل استفاده، پیش‌فرض] */
    public static function tourDefs(): array
    {
        return require BASE_PATH . '/config/tour_texts.php';
    }

    /** متن‌های تغییرکرده‌ی یک پیشوند (مثل tour.) — شناسه بدون پیشوند => متن */
    private static function overrides(string $prefix): array
    {
        try {
            $rows = DB::all('SELECT `key`, body FROM content_texts WHERE `key` LIKE ?', [$prefix . '%']);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[substr((string) $r['key'], strlen($prefix))] = (string) $r['body'];
        }
        return $out;
    }

    /** متن‌های نهایی تور (ویرایش ادمین یا پیش‌فرض) — شناسه => متن */
    public static function tour(): array
    {
        $over = self::overrides('tour.');
        $out = [];
        foreach (self::tourDefs() as $id => [, , $default]) {
            $out[$id] = $over[$id] ?? $default;
        }
        return $out;
    }

    /** برای صفحه‌ی ادمین: هر متن با پیش‌فرض و مقدار فعلی */
    public static function tourForAdmin(): array
    {
        $over = self::overrides('tour.');
        $out = [];
        foreach (self::tourDefs() as $id => [$group, $label, $default]) {
            $out[$group][$id] = ['label' => $label, 'default' => $default, 'value' => $over[$id] ?? $default, 'changed' => isset($over[$id])];
        }
        return $out;
    }

    /** ذخیره‌ی متن؛ برابر با پیش‌فرض = حذف نسخه‌ی ویرایش‌شده */
    public static function setTour(string $id, string $body, int $uid): void
    {
        $default = self::tourDefs()[$id][2];
        if ($body === $default) {
            DB::run('DELETE FROM content_texts WHERE `key` = ?', ['tour.' . $id]);
            return;
        }
        DB::run('INSERT INTO content_texts (`key`, body, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body), updated_by = VALUES(updated_by)', ['tour.' . $id, $body, $uid]);
    }
}
