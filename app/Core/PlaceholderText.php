<?php
declare(strict_types=1);

namespace App\Core;

/**
 * متن راهنمای فیلدها (placeholder) که ادمین در «محتوا ← Placeholder» ویرایش می‌کند.
 * فهرست، مسیر و پیش‌فرض‌ها در config/placeholder_texts.php؛ فقط متن‌های تغییرکرده در content_texts (کلید ph.<id>).
 * PHP: ph('id') — JS: TK.ph('id', 'پیش‌فرض') با boot.ph (فقط ویرایش‌شده‌ها).
 */
final class PlaceholderText
{
    public const PREFIX = 'ph.';
    public const MAX_LEN = 150;

    private static ?array $over = null;

    public static function defs(): array
    {
        static $defs = null;
        return $defs ??= require BASE_PATH . '/config/placeholder_texts.php';
    }

    /** متن‌های ویرایش‌شده: شناسه => متن */
    public static function overrides(): array
    {
        if (self::$over !== null) {
            return self::$over;
        }
        try {
            $rows = DB::all('SELECT `key`, body FROM content_texts WHERE `key` LIKE ?', [self::PREFIX . '%']);
        } catch (\Throwable $e) {
            return self::$over = [];
        }
        $defs = self::defs();
        $out = [];
        foreach ($rows as $r) {
            $id = substr((string) $r['key'], strlen(self::PREFIX));
            if (isset($defs[$id])) {
                $out[$id] = (string) $r['body'];
            }
        }
        return self::$over = $out;
    }

    public static function get(string $id): string
    {
        return self::overrides()[$id] ?? (self::defs()[$id][3] ?? '');
    }

    public static function forAdmin(): array
    {
        $over = self::overrides();
        $out = [];
        foreach (self::defs() as $id => [$group, $path, $url, $default]) {
            $out[$group][$id] = ['path' => $path, 'url' => $url, 'default' => $default, 'value' => $over[$id] ?? $default, 'changed' => isset($over[$id])];
        }
        return $out;
    }

    /** ذخیره؛ برابر پیش‌فرض = حذف نسخه‌ی ویرایش‌شده. خروجی: خطا یا null */
    public static function save(string $id, string $body, int $uid): ?string
    {
        $defs = self::defs();
        if (!isset($defs[$id])) {
            return 'Placeholder یافت نشد.';
        }
        $body = trim(preg_replace('/\s+/u', ' ', $body));
        if ($body === '') {
            return 'متن نمی‌تواند خالی باشد.';
        }
        if (mb_strlen($body) > self::MAX_LEN) {
            return 'متن حداکثر ' . fa_num(self::MAX_LEN) . ' نویسه است.';
        }
        if ($body === trim(preg_replace('/\s+/u', ' ', $defs[$id][3]))) {
            DB::run('DELETE FROM content_texts WHERE `key` = ?', [self::PREFIX . $id]);
        } else {
            DB::run('INSERT INTO content_texts (`key`, body, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body), updated_by = VALUES(updated_by)', [self::PREFIX . $id, $body, $uid]);
        }
        self::$over = null;
        return null;
    }
}
