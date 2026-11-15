<?php
declare(strict_types=1);

namespace App\Services\Karlix;

use App\Core\Log;

/**
 * اتصال به سرویس Agnes AI (چت سازگار با قالب chat/completions) — فقط از بک‌اند.
 * کلید در .env (AGNES_API_KEY) است و هرگز در خروجی، لاگ یا JS نمی‌آید. خطاها به کدهای ثابت تبدیل می‌شوند:
 * not_configured | timeout | network | auth | rate_limited | server | bad_response.
 */
final class Agnes
{
    public const DEFAULT_URL = 'https://apihub.agnes-ai.com/v1/chat/completions';
    public const DEFAULT_MODEL = 'agnes-2.5-flash';

    /** متن کاربرپسند هر کد خطا (در پاسخ کارلیکس) */
    public const ERRORS = [
        'not_configured' => 'دستیار هوش مصنوعی روی این سرور پیکربندی نشده است.',
        'timeout'        => 'سرویس هوش مصنوعی در زمان مجاز پاسخ نداد.',
        'network'        => 'ارتباط با سرویس هوش مصنوعی برقرار نشد.',
        'auth'           => 'کلید سرویس هوش مصنوعی معتبر نیست؛ مدیر سیستم باید تنظیمات را بررسی کند.',
        'rate_limited'   => 'سقف درخواست سرویس هوش مصنوعی پر شده است؛ کمی بعد دوباره امتحان کنید.',
        'server'         => 'سرویس هوش مصنوعی موقتاً در دسترس نیست.',
        'bad_response'   => 'پاسخ سرویس هوش مصنوعی قابل فهم نبود.',
    ];

    public static function enabled(): bool
    {
        return filter_var(env('AI_ENABLED', true), FILTER_VALIDATE_BOOLEAN) && self::key() !== '';
    }

    public static function model(): string
    {
        $m = trim((string) env('AGNES_MODEL', self::DEFAULT_MODEL));
        return preg_match('/^[A-Za-z0-9._:\-]{1,64}$/', $m) ? $m : self::DEFAULT_MODEL;
    }

    private static function key(): string
    {
        return trim((string) env('AGNES_API_KEY', ''));
    }

    private static function url(): string
    {
        $u = trim((string) env('AGNES_API_URL', self::DEFAULT_URL));
        return preg_match('~^https://[^\s]+$~', $u) ? $u : self::DEFAULT_URL;
    }

    /** ثانیه‌ی مجاز برای هر درخواست (۵ تا ۶۰) */
    public static function timeout(): int
    {
        return max(5, min(60, (int) env('AI_TIMEOUT', 30)));
    }

    /**
     * یک درخواست چت. خروجی: ['ok' => bool, 'content' => string, 'tokens' => ?int, 'error' => ?string, 'http' => int]
     * $messages = [['role' => 'system'|'user'|'assistant', 'content' => string], …]
     */
    public static function chat(array $messages, int $maxTokens = 700): array
    {
        if (!self::enabled()) {
            return ['ok' => false, 'content' => '', 'tokens' => null, 'error' => 'not_configured', 'http' => 0];
        }
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'content' => '', 'tokens' => null, 'error' => 'network', 'http' => 0];
        }
        $body = ['model' => self::model(), 'messages' => $messages, 'temperature' => 0.1, 'max_tokens' => $maxTokens, 'stream' => false];
        // حالت JSON فقط اگر روی سرور فعال شده باشد (پشتیبانی همه‌ی مدل‌ها از response_format تضمین‌شده نیست)
        if (filter_var(env('AI_JSON_MODE', false), FILTER_VALIDATE_BOOLEAN)) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        $started = microtime(true);
        $res = self::post($payload);
        // یک تلاش دوباره فقط برای خطای موقت شبکه/سرور، و فقط اگر زمان کافی مانده باشد
        if (in_array($res['error'], ['network', 'server'], true) && microtime(true) - $started < self::timeout() / 3) {
            usleep(600000);
            $res = self::post($payload);
        }
        if ($res['error'] !== null) {
            Log::warning('karlix.api_error', ['error' => $res['error'], 'http' => $res['http'], 'ms' => (int) ((microtime(true) - $started) * 1000)]);
            return ['ok' => false, 'content' => '', 'tokens' => null, 'error' => $res['error'], 'http' => $res['http']];
        }
        $j = json_decode($res['body'], true);
        $content = is_array($j) ? ($j['choices'][0]['message']['content'] ?? null) : null;
        if (!is_string($content) || trim($content) === '') {
            Log::warning('karlix.bad_response', ['http' => $res['http'], 'len' => strlen($res['body'])]);
            return ['ok' => false, 'content' => '', 'tokens' => null, 'error' => 'bad_response', 'http' => $res['http']];
        }
        $tokens = isset($j['usage']['total_tokens']) && is_numeric($j['usage']['total_tokens']) ? (int) $j['usage']['total_tokens'] : null;
        return ['ok' => true, 'content' => $content, 'tokens' => $tokens, 'error' => null, 'http' => $res['http']];
    }

    /** @return array{body:string,http:int,error:?string} */
    private static function post(string $payload): array
    {
        $ch = curl_init(self::url());
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . self::key(), 'Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => self::timeout(),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_MAXFILESIZE => 2 * 1024 * 1024,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($errno !== 0 || $body === false) {
            return ['body' => '', 'http' => $http, 'error' => $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'network'];
        }
        $error = null;
        if ($http === 401 || $http === 403) {
            $error = 'auth';
        } elseif ($http === 429) {
            $error = 'rate_limited';
        } elseif ($http >= 500) {
            $error = 'server';
        } elseif ($http < 200 || $http >= 300) {
            $error = 'bad_response';
        }
        return ['body' => (string) $body, 'http' => $http, 'error' => $error];
    }
}
