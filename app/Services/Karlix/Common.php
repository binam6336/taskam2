<?php
declare(strict_types=1);

namespace App\Services\Karlix;

use App\Core\DB;
use App\Core\Jalali;
use App\Core\Throttle;

/**
 * بخش‌های مشترک دستیار یادداشت تسک (Assistant) و دستیار بورد (BoardAgent) — v7.20.
 * تقویم شمسی/میلادی برای تفسیر «فردا ساعت ۱۰»، «شنبه بعد»، «آخر ماه»، خواندن JSON خروجی مدل، نام کاربر و سهمیه‌ی مشترک.
 */
final class Common
{
    private const TZ = 'Asia/Tehran';

    public static function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone(self::TZ));
    }

    /** تقویم $days روز آینده (میلادی + شمسی + روز هفته) تا مدل تاریخ نسبی را بدون حساب دستی به تاریخ دقیق برساند */
    public static function calendar(int $days = 21): array
    {
        $out = [];
        $d = self::today();
        for ($i = 0; $i < $days; $i++) {
            $x = $d->modify('+' . $i . ' day');
            [$jy, $jm, $jd] = Jalali::fromGregorian((int) $x->format('Y'), (int) $x->format('n'), (int) $x->format('j'));
            $out[] = ['offset' => $i, 'date' => $x->format('Y-m-d'), 'jalali' => sprintf('%04d/%02d/%02d', $jy, $jm, $jd) . ' (' . $jd . ' ' . Jalali::MONTHS[$jm - 1] . ')',
                'weekday' => Jalali::WEEKDAYS[((int) $x->format('w') + 1) % 7]];
        }
        return $out;
    }

    /** اکنون + آخر هفته‌ی شمسی (جمعه) و آخر ماه شمسی (میلادی) برای «آخر هفته» و «آخر ماه» */
    public static function now(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone(self::TZ));
        $today = self::today();
        $week = $today->modify('+' . ((5 - (int) $today->format('w') + 7) % 7) . ' day');
        [$jy, $jm] = Jalali::fromGregorian((int) $today->format('Y'), (int) $today->format('n'), (int) $today->format('j'));
        $end = $today;
        for ($i = 0; $i < 32; $i++) {
            $n = $end->modify('+1 day');
            [, $m2] = Jalali::fromGregorian((int) $n->format('Y'), (int) $n->format('n'), (int) $n->format('j'));
            if ($m2 !== $jm) {
                break;
            }
            $end = $n;
        }
        return ['date' => $now->format('Y-m-d'), 'time' => $now->format('H:i'), 'timezone' => self::TZ,
            'weekday' => Jalali::WEEKDAYS[((int) $now->format('w') + 1) % 7], 'jalali' => jdate($now->format('Y-m-d') . ' 00:00:00', false, true),
            'week_end_friday' => $week->format('Y-m-d'), 'jalali_month_end' => $end->format('Y-m-d'), 'jalali_month' => Jalali::MONTHS[$jm - 1] . ' ' . $jy];
    }

    /** اولین شیء JSON معتبر در پاسخ مدل (کد فنس و متن اضافه نادیده گرفته می‌شود)؛ $key = کلیدی که باید باشد */
    public static function json(string $content, ?string $key = null): ?array
    {
        $s = trim($content);
        $s = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $s) ?? $s;
        $j = json_decode($s, true);
        if (!is_array($j)) {
            $a = strpos($s, '{');
            $b = strrpos($s, '}');
            if ($a === false || $b === false || $b <= $a) {
                return null;
            }
            $j = json_decode(substr($s, $a, $b - $a + 1), true);
        }
        return is_array($j) && ($key === null || array_key_exists($key, $j)) ? $j : null;
    }

    public static function who(int $uid): string
    {
        $u = DB::one('SELECT first_name, last_name FROM users WHERE id = ?', [$uid]) ?? [];
        $n = trim(((string) ($u['first_name'] ?? '')) . ' ' . ((string) ($u['last_name'] ?? '')));
        return $n !== '' ? $n : 'کاربر';
    }

    /** درخواست‌های ۲۴ ساعت اخیر کاربر از هر دو دستیار (سقف روزانه‌ی مشترک AI_DAILY_LIMIT) */
    public static function usedToday(int $uid): int
    {
        $n = (int) DB::value('SELECT COUNT(*) FROM ai_requests WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 DAY)', [$uid]);
        try {
            $n += (int) DB::value("SELECT COUNT(*) FROM ai_board_messages m INNER JOIN ai_board_sessions s ON s.id = m.session_id
                WHERE s.user_id = ? AND m.role = 'user' AND m.created_at > (NOW() - INTERVAL 1 DAY)", [$uid]);
        } catch (\Throwable $e) {
            // جدول دستیار بورد هنوز ساخته نشده
        }
        return $n;
    }

    /** سقف دقیقه‌ای مشترک (همان شمارنده‌ی Throttle «karlix») — true = مجاز */
    public static function minuteOk(int $uid): bool
    {
        return Throttle::attempt('karlix', (string) $uid, Assistant::perMinute(), 60);
    }
}
