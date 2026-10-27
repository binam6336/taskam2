<?php
declare(strict_types=1);

namespace App\Core;

/** اعتبارسنجی‌های مشترک (موبایل، ایمیل، تاریخ، رمز، enum ...). */
final class Validate
{
    private const FA = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    private const EN = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public static function digits(string $s): string
    {
        return str_replace(self::FA, self::EN, $s);
    }

    /** موبایل ایرانی: نرمال‌سازی ارقام و حذف فاصله/خط‌تیره. null یعنی نامعتبر. */
    public static function mobile(string $s): ?string
    {
        $s = preg_replace('/[\s\-]+/', '', self::digits($s)) ?? '';
        if (str_starts_with($s, '+98')) {
            $s = '0' . substr($s, 3);
        } elseif (str_starts_with($s, '0098')) {
            $s = '0' . substr($s, 4);
        } elseif (strlen($s) === 10 && $s[0] === '9') {
            $s = '0' . $s;
        }
        return preg_match('/^09\d{9}$/', $s) ? $s : null;
    }

    public static function email(string $s): bool
    {
        return mb_strlen($s) <= 150 && filter_var($s, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function date(?string $s): ?string
    {
        if ($s === null || $s === '') {
            return null;
        }
        $s = self::digits($s);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }
        return $s;
    }

    public static function time(?string $s): ?string
    {
        if ($s === null || $s === '') {
            return null;
        }
        $s = self::digits($s);
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $s, $m)) {
            return null;
        }
        return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
    }

    public static function enum($v, array $allowed, string $default): string
    {
        return is_string($v) && in_array($v, $allowed, true) ? $v : $default;
    }

    /** @return string|null پیام خطا؛ null یعنی رمز قابل‌قبول است. */
    public static function passwordError(string $p): ?string
    {
        if (strlen($p) < 8) {
            return 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
        }
        if (strlen($p) > 128) {
            return 'رمز عبور بیش از حد طولانی است.';
        }
        if (!preg_match('/\p{L}/u', $p) || !preg_match('/\d/', self::digits($p))) {
            return 'رمز عبور باید شامل حداقل یک حرف و یک عدد باشد.';
        }
        if (in_array(strtolower($p), ['12345678', 'password', 'password1', 'qwerty123', '123456789', '11111111'], true)) {
            return 'این رمز عبور بسیار رایج است؛ رمز دیگری انتخاب کنید.';
        }
        return null;
    }

    public static function name(string $s): bool
    {
        $l = mb_strlen($s);
        return $l >= 1 && $l <= 100 && !preg_match('/[<>\x00-\x1F]/u', $s);
    }

    /** لینک http(s) یا خالی. */
    public static function url(string $s, int $max = 500): ?string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        if (mb_strlen($s) > $max || !preg_match('#^https?://#i', $s) || filter_var($s, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        return $s;
    }

    /** توکن اشتراک‌گذاری (hex). */
    public static function shareToken(string $s): ?string
    {
        return preg_match('/^[a-f0-9]{16,64}$/i', $s) ? strtolower($s) : null;
    }
}
