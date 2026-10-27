<?php
declare(strict_types=1);

namespace App\Core;

/** تبدیل و قالب‌بندی تاریخ شمسی (تنها نسخه‌ی تاریخ در کل پروژه). */
final class Jalali
{
    public const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    public const WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    /** @return array{0:int,1:int,2:int} [سال، ماه، روز] */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }

    /** برچسب کوتاه «ماه/روز» شمسی برای نمودارها؛ ورودی Y-m-d */
    public static function md(string $ymd): string
    {
        $ts = strtotime($ymd);
        if ($ts === false) {
            return $ymd;
        }
        [, $jm, $jd] = self::fromGregorian((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
        return fa_num(sprintf('%02d/%02d', $jm, $jd));
    }

    public static function format(?string $datetime, bool $withTime = true, bool $weekday = false): string
    {
        if ($datetime === null || $datetime === '') {
            return '';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return $datetime;
        }
        [$jy, $jm, $jd] = self::fromGregorian((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
        $out = fa_num($jd) . ' ' . self::MONTHS[$jm - 1] . ' ' . fa_num($jy);
        if ($weekday) {
            $out = self::WEEKDAYS[((int) date('w', $ts) + 1) % 7] . ' ' . $out;
        }
        if ($withTime) {
            $out .= ' - ' . fa_num(date('H:i', $ts));
        }
        return $out;
    }

    public static function ago(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '—';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '—';
        }
        $diff = time() - $ts;
        if ($diff < 0) {
            $diff = abs($diff);
            return $diff < 300 ? 'لحظاتی دیگر' : self::format($datetime);
        }
        if ($diff < 60) {
            return 'لحظاتی پیش';
        }
        if ($diff < 3600) {
            return fa_num(intdiv($diff, 60)) . ' دقیقه پیش';
        }
        if ($diff < 86400) {
            return fa_num(intdiv($diff, 3600)) . ' ساعت پیش';
        }
        if ($diff < 172800) {
            return 'دیروز';
        }
        if ($diff < 604800) {
            return fa_num(intdiv($diff, 86400)) . ' روز پیش';
        }
        if ($diff < 2592000) {
            return fa_num(intdiv($diff, 604800)) . ' هفته پیش';
        }
        return self::format($datetime, false);
    }

    /** اطلاعات کامل «اکنون» برای endpoint ساعت تهران. */
    public static function nowInfo(): array
    {
        $now = new \DateTime('now', new \DateTimeZone('Asia/Tehran'));
        [$y, $m, $d] = self::fromGregorian((int) $now->format('Y'), (int) $now->format('n'), (int) $now->format('j'));
        return [
            'timezone' => 'Asia/Tehran',
            'iso'      => $now->format('c'),
            'gregorian' => ['date' => $now->format('Y-m-d'), 'time' => $now->format('H:i:s')],
            'persian'  => [
                'year' => $y, 'month' => $m, 'day' => $d,
                'month_name' => self::MONTHS[$m - 1],
                'formatted' => sprintf('%04d/%02d/%02d', $y, $m, $d),
                'formatted_long' => $d . ' ' . self::MONTHS[$m - 1] . ' ' . $y,
                'weekday' => self::WEEKDAYS[((int) $now->format('w') + 1) % 7],
            ],
            'unix_timestamp' => time(),
            'unix_ms' => (int) (microtime(true) * 1000),
        ];
    }
}
