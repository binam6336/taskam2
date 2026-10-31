<?php
declare(strict_types=1);

namespace App\Core;

/** محدودسازی نرخ مبتنی بر دیتابیس (مقاوم در برابر پاک‌کردن کوکی/سشن). */
final class Throttle
{
    /** تعداد تلاش‌های ثبت‌شده در پنجره‌ی زمانی. */
    public static function count(string $bucket, string $key, int $windowSeconds): int
    {
        return (int) DB::value(
            'SELECT COUNT(*) FROM rate_limits WHERE bucket = ? AND `key` = ? AND created_at >= (NOW() - INTERVAL ? SECOND)',
            [$bucket, self::k($key), $windowSeconds]
        );
    }

    public static function hit(string $bucket, string $key): void
    {
        DB::insert('rate_limits', ['bucket' => $bucket, 'key' => self::k($key)]);
        if (random_int(1, 50) === 1) {
            DB::run('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
        }
    }

    public static function clear(string $bucket, string $key): void
    {
        DB::run('DELETE FROM rate_limits WHERE bucket = ? AND `key` = ?', [$bucket, self::k($key)]);
    }

    /**
     * ثبت تلاش و بررسی سقف؛ true یعنی مجاز است.
     * اول ثبت، بعد شمارش (شامل همین درخواست): درخواست‌های هم‌زمان نمی‌توانند همه شمارنده‌ی خالی را ببینند و سقف را دور بزنند.
     */
    public static function attempt(string $bucket, string $key, int $max, int $windowSeconds): bool
    {
        self::hit($bucket, $key);
        return self::count($bucket, $key, $windowSeconds) <= $max;
    }

    /** برداشتن آخرین ثبت (مثلاً تلاشی که موفق شد و نباید از سهمیه کم کند) */
    public static function undo(string $bucket, string $key): void
    {
        DB::run('DELETE FROM rate_limits WHERE bucket = ? AND `key` = ? ORDER BY id DESC LIMIT 1', [$bucket, self::k($key)]);
    }

    private static function k(string $key): string
    {
        return hash('sha256', $key);
    }
}
