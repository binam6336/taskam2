<?php
declare(strict_types=1);

namespace App\Core;

/**
 * متن خطاهایی که ادمین در «محتوا ← خطاها» ویرایش کرده.
 * فهرست و متن پیش‌فرض‌ها در config/error_texts.php است؛ فقط متن‌های تغییرکرده در content_texts (کلید error.<id>) ذخیره می‌شوند.
 * جایگزینی بر اساس خود متن پیام انجام می‌شود (نه محل تولیدش)، پس کد خطاها دست نمی‌خورد:
 * Response::json (message/error)، Session::flash (خطا/هشدار)، صفحه‌ی خطا، آپلود و TK.toast در مرورگر (از طریق boot.errs).
 * متن‌های دارای {name} الگو هستند: مقدار متغیرها از پیام اصلی برداشته و در متن جدید گذاشته می‌شود.
 */
final class ErrorText
{
    public const PREFIX = 'error.';
    public const MAX_LEN = 300;

    /** @var array|null [[پیش‌فرض، متن جدید، regex یا null، نام متغیرها], ...] */
    private static ?array $rules = null;

    public static function defs(): array
    {
        static $defs = null;
        return $defs ??= require BASE_PATH . '/config/error_texts.php';
    }

    /** متن‌های ویرایش‌شده: شناسه => متن */
    public static function overrides(): array
    {
        try {
            $rows = DB::all('SELECT `key`, body FROM content_texts WHERE `key` LIKE ?', [self::PREFIX . '%']);
        } catch (\Throwable $e) {
            return [];
        }
        $defs = self::defs();
        $out = [];
        foreach ($rows as $r) {
            $id = substr((string) $r['key'], strlen(self::PREFIX));
            if (isset($defs[$id])) {
                $out[$id] = (string) $r['body'];
            }
        }
        return $out;
    }

    /** نام متغیرهای یک متن: «سقف {status} …» → ['status'] */
    public static function vars(string $text): array
    {
        preg_match_all('/\{([a-z]+)\}/', $text, $m);
        return array_values(array_unique($m[1]));
    }

    private static function rules(): array
    {
        if (self::$rules !== null) {
            return self::$rules;
        }
        $defs = self::defs();
        $rules = [];
        foreach (self::overrides() as $id => $new) {
            $default = $defs[$id][1];
            $vars = self::vars($default);
            $regex = null;
            if ($vars) {
                $parts = preg_split('/(\{[a-z]+\})/', $default, -1, PREG_SPLIT_DELIM_CAPTURE);
                $regex = '/^' . implode('', array_map(static fn($p) => preg_match('/^\{[a-z]+\}$/', $p) ? '(.+?)' : preg_quote($p, '/'), $parts)) . '$/su';
            }
            $rules[] = [$default, $new, $regex, $vars];
        }
        return self::$rules = $rules;
    }

    /** متن نهایی یک پیام خطا (اگر ادمین ویرایشش کرده باشد) */
    public static function apply(string $message): string
    {
        if ($message === '') {
            return $message;
        }
        try {
            $rules = self::rules();
        } catch (\Throwable $e) {
            return $message;
        }
        foreach ($rules as [$default, $new, $regex, $vars]) {
            if ($regex === null) {
                if ($message === $default) {
                    return $new;
                }
            } elseif (preg_match($regex, $message, $m)) {
                $map = [];
                foreach ($vars as $i => $v) {
                    $map['{' . $v . '}'] = $m[$i + 1];
                }
                return strtr($new, $map);
            }
        }
        return $message;
    }

    /** برای مرورگر (TK.toast): [[پیش‌فرض، متن جدید], ...] — فقط متن‌های ویرایش‌شده */
    public static function clientMap(): array
    {
        try {
            return array_map(static fn($r) => [$r[0], $r[1]], self::rules());
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function forAdmin(): array
    {
        $over = self::overrides();
        $out = [];
        foreach (self::defs() as $id => [$group, $default, $desc]) {
            $out[$group][$id] = ['default' => $default, 'desc' => $desc, 'value' => $over[$id] ?? $default, 'changed' => isset($over[$id]), 'vars' => self::vars($default)];
        }
        return $out;
    }

    /** ذخیره؛ برابر پیش‌فرض = حذف نسخه‌ی ویرایش‌شده. خروجی: خطا یا null */
    public static function save(string $id, string $body, int $uid): ?string
    {
        $defs = self::defs();
        if (!isset($defs[$id])) {
            return 'خطا یافت نشد.';
        }
        $body = trim(preg_replace('/\s+/u', ' ', $body));
        if ($body === '') {
            return 'متن خطا نمی‌تواند خالی باشد.';
        }
        if (mb_strlen($body) > self::MAX_LEN) {
            return 'متن خطا حداکثر ' . fa_num(self::MAX_LEN) . ' نویسه است.';
        }
        $extra = array_diff(self::vars($body), self::vars($defs[$id][1]));
        if ($extra) {
            return 'متغیر {' . reset($extra) . '} در این خطا وجود ندارد؛ فقط ' . (self::vars($defs[$id][1]) ? 'این‌ها مجازند: {' . implode('}، {', self::vars($defs[$id][1])) . '}' : 'متن ساده مجاز است') . '.';
        }
        if ($body === $defs[$id][1]) {
            DB::run('DELETE FROM content_texts WHERE `key` = ?', [self::PREFIX . $id]);
        } else {
            DB::run('INSERT INTO content_texts (`key`, body, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body), updated_by = VALUES(updated_by)', [self::PREFIX . $id, $body, $uid]);
        }
        self::$rules = null;
        return null;
    }
}
