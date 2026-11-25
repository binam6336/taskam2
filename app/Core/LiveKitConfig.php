<?php
declare(strict_types=1);
namespace App\Core;

/** تنظیمات مشترک هدر امنیتی و سرویس جلسه؛ فقط دامنهٔ Cloud و بدون مقصد دلخواه. */
final class LiveKitConfig
{
    public const TOKEN_SECONDS = 120;
    public const SYNC_SECONDS = 20;
    public const CHAT_MAX = 4000;
    public const WRITE_PER_MINUTE = 30;
    public static function url(): ?string
    {
        $url = rtrim(trim((string) env('LIVEKIT_URL', '')), '/');
        return preg_match('~^wss://[a-z0-9][a-z0-9-]*\.livekit\.cloud$~D', $url) ? $url : null;
    }
    public static function configured(): bool
    {
        return self::url() !== null && (string) env('LIVEKIT_API_KEY', '') !== '' && (string) env('LIVEKIT_API_SECRET', '') !== '' && (function_exists('curl_init') || (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN) && in_array('https', stream_get_wrappers(), true)));
    }
    public static function maxParticipants(): int { return max(2, min(100, (int) env('LIVEKIT_MAX_PARTICIPANTS', 30))); }
}
