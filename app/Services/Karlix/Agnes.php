<?php
declare(strict_types=1);

namespace App\Services\Karlix;

use App\Core\DB;
use App\Core\Log;

/**
 * اتصال به سرویس Agnes AI (چت سازگار با قالب chat/completions) — فقط از بک‌اند.
 * کلید در .env (AGNES_API_KEY) است و هرگز در خروجی، لاگ یا JS نمی‌آید. خطاها به کدهای ثابت تبدیل می‌شوند:
 * not_configured | timeout | network | auth | rate_limited | server | bad_response | busy.
 *
 * v7.24 (هم‌زمانی): حداکثر AI_MAX_CONCURRENT فراخوانی هم‌زمان در کل سرور؛ بقیه حداکثر AI_QUEUE_SECONDS در صف می‌مانند تا جا باز شود
 * (وگرنه busy). v7.26: صف نوبتی FIFO روی جدول ai_queue (به‌جای قفل‌های نام‌دار) و سقف منتظرها AI_MAX_WAITING.
 * پیام خطای «کلید نامعتبر» هرگز به کاربر گفته نمی‌شود («خطا در اتصال به کارلیکس»)؛ علت واقعی فقط در storage/logs/AI.
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
        'auth'           => 'خطا در اتصال به کارلیکس.',
        'rate_limited'   => 'سقف درخواست سرویس هوش مصنوعی پر شده است؛ کمی بعد دوباره امتحان کنید.',
        'server'         => 'سرویس هوش مصنوعی موقتاً در دسترس نیست.',
        'bad_response'   => 'پاسخ سرویس هوش مصنوعی قابل فهم نبود.',
        'busy'           => 'سرویس هوش مصنوعی هم‌اکنون شلوغ است؛ کمی بعد دوباره امتحان کنید.',
    ];

    /** حداکثر فراخوانی هم‌زمان مدل در کل سرور (۰ = بدون محدودیت) */
    public static function concurrency(): int
    {
        return max(0, min(64, (int) env('AI_MAX_CONCURRENT', 8)));
    }

    /** حداکثر درخواست منتظر در صف (v7.26، AI-04): بیشتر از این ⇐ فوراً busy تا worker های وب پشت صف پر نشوند (۰ = بدون سقف) */
    public static function maxWaiting(): int
    {
        return max(0, min(500, (int) env('AI_MAX_WAITING', 16)));
    }

    /**
     * v7.26 (AI-05): صف نوبتی FIFO روی جدول ai_queue به‌جای چرخش تصادفی روی GET_LOCK.
     * هر فراخوانی یک نوبت (id افزایشی) می‌گیرد و فقط وقتی اجرا می‌شود که کمتر از AI_MAX_CONCURRENT نوبتِ زنده‌ی قبل از آن مانده باشد
     * (اجرا یا انتظار) — پس زودتر آمده زودتر اجرا می‌شود، روی چند سرور با یک دیتابیس هم سراسری است و قفل دیتابیسی نگه داشته نمی‌شود.
     * منتظرها هر ۰٫۴ ثانیه beat_at را تازه می‌کنند؛ نوبت منتظری که ۱۰ ثانیه تازه نشده یا نوبت اجرایی بیش از Timeout + ۶۰ ثانیه (فرایند مرده) حذف می‌شود.
     * خروجی: 0 = بدون صف (خاموش یا جدول در دسترس نیست)، null = تا AI_QUEUE_SECONDS نوبت نرسید یا صف پر است، عدد = نوبت گرفته‌شده.
     */
    private static function acquire(int $uid, string $feature): ?int
    {
        $n = self::concurrency();
        if ($n === 0) {
            return 0;
        }
        try {
            self::sweep();
            $cap = self::maxWaiting();
            if ($cap > 0 && (int) DB::value("SELECT COUNT(*) FROM ai_queue WHERE state = 'wait'") >= $cap) {
                return null;
            }
            $id = DB::insert('ai_queue', ['user_id' => $uid, 'feature' => mb_substr($feature, 0, 24), 'state' => 'wait', 'beat_at' => date('Y-m-d H:i:s')]);
            register_shutdown_function(static function () use ($id): void {   // فرایند وسط کار تمام شد: نوبت آزاد شود
                try {
                    DB::run('DELETE FROM ai_queue WHERE id = ?', [$id]);
                } catch (\Throwable $e) {
                }
            });
            $deadline = microtime(true) + max(1, min(60, (int) env('AI_QUEUE_SECONDS', 20)));
            $lastSweep = microtime(true);
            while (true) {
                if ((int) DB::value('SELECT COUNT(*) FROM ai_queue WHERE id < ?', [$id]) < $n) {
                    DB::run("UPDATE ai_queue SET state = 'run', beat_at = NOW() WHERE id = ?", [$id]);
                    return $id;
                }
                if (microtime(true) >= $deadline) {
                    DB::run('DELETE FROM ai_queue WHERE id = ?', [$id]);
                    return null;
                }
                usleep(400000);
                DB::run('UPDATE ai_queue SET beat_at = NOW() WHERE id = ?', [$id]);
                if (microtime(true) - $lastSweep > 3) {
                    self::sweep();
                    $lastSweep = microtime(true);
                }
            }
        } catch (\Throwable $e) {
            return 0;                                           // جدول صف هنوز ساخته نشده/در دسترس نیست: بدون صف ادامه
        }
    }

    /** حذف نوبت‌های رهاشده (منتظر بی‌تپش، یا اجرای خیلی طولانی‌تر از Timeout) */
    private static function sweep(): void
    {
        DB::run("DELETE FROM ai_queue WHERE (state = 'wait' AND beat_at < (NOW() - INTERVAL 10 SECOND)) OR (state = 'run' AND beat_at < (NOW() - INTERVAL " . (self::timeout() * 2 + 60) . ' SECOND))');
    }

    private static function releaseSlot(?int $id): void
    {
        if ($id !== null && $id > 0) {
            try {
                DB::run('DELETE FROM ai_queue WHERE id = ?', [$id]);
            } catch (\Throwable $e) {
            }
        }
    }

    /** وضعیت صف برای مستندات پنل: [running, waiting] */
    public static function queueState(): array
    {
        try {
            $r = DB::one("SELECT SUM(state = 'run') r, SUM(state = 'wait') w FROM ai_queue");
            return [(int) ($r['r'] ?? 0), (int) ($r['w'] ?? 0)];
        } catch (\Throwable $e) {
            return [0, 0];
        }
    }

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
     * یک درخواست چت — تنها مسیر فراخوانی مدل در کل برنامه و همیشه با سهمیه (Quota): پیش از ارسال رزرو، بعد از پاسخ ثبت مصرف واقعی.
     * $meta: user (الزامی؛ کاربری که مصرف به سهمیه‌اش نوشته می‌شود)، feature (task_note|board)، key (شناسه‌ی یکتای این فراخوانی)، real_user (مدیر در ورود به پنل کاربر)،
     *        prompt (پیام خود کاربر، فقط برای لاگ خطا)، attempt (شماره‌ی تلاش، فقط برای لاگ)، retry (false = بدون تکرار سریع داخلی؛ فراخواننده خودش تکرار می‌کند).
     * خروجی: ['ok', 'content', 'tokens', 'usage' => [input, output, total], 'error', 'message' (متن کاربرپسند خطا)، 'http', 'retry_after' (ثانیه، هدر Retry-After)]
     * $messages = [['role' => 'system'|'user'|'assistant', 'content' => string], …]
     */
    public static function chat(array $messages, int $maxTokens = 700, array $meta = []): array
    {
        $fail = static fn(string $e, int $http = 0, ?string $msg = null) => ['ok' => false, 'content' => '', 'tokens' => null, 'usage' => null, 'error' => $e,
            'message' => $msg ?? (self::ERRORS[$e] ?? self::ERRORS['network']), 'http' => $http];
        if (!self::enabled()) {
            return $fail('not_configured');
        }
        $uid = (int) ($meta['user'] ?? 0);
        if ($uid <= 0) {
            return $fail('not_configured', 0, 'درخواست هوش مصنوعی بدون کاربر مشخص پذیرفته نمی‌شود.');    // هیچ فراخوانی بیرون از سهمیه
        }
        if (!function_exists('curl_init')) {
            return $fail('network');
        }
        $body = ['model' => self::model(), 'messages' => $messages, 'temperature' => 0.1, 'max_tokens' => $maxTokens, 'stream' => false];
        // حالت JSON فقط اگر روی سرور فعال شده باشد (پشتیبانی همه‌ی مدل‌ها از response_format تضمین‌شده نیست)
        if (filter_var(env('AI_JSON_MODE', false), FILTER_VALIDATE_BOOLEAN)) {
            $body['response_format'] = ['type' => 'json_object'];
        }
        $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
        $key = (string) ($meta['key'] ?? ('x:' . bin2hex(random_bytes(8))));
        // v7.23: هر فراخوانی ناموفق در storage/logs/AI (پیام کاربر، درخواست، جواب خام، علت، زمان)
        $logFail = static fn(string $err, int $http, string $msg, ?string $body, int $ms) => AiLog::failure([
            'feature' => $meta['feature'] ?? 'other', 'user' => $uid, 'real_user' => $meta['real_user'] ?? null, 'key' => $key,
            'attempt' => $meta['attempt'] ?? null, 'error' => $err, 'http' => $http, 'message' => $msg, 'ms' => $ms, 'prompt' => $meta['prompt'] ?? null,
            'request' => ['model' => self::model(), 'max_tokens' => $maxTokens, 'bytes' => strlen((string) $payload), 'messages' => $messages],
            'response' => $body,
        ]);
        $q = Quota::reserve($uid, (string) ($meta['feature'] ?? 'other'), $key, self::model(), Quota::estimate((string) $payload, $maxTokens), isset($meta['real_user']) ? (int) $meta['real_user'] : null);
        if (!$q['ok']) {
            $logFail((string) $q['code'], 0, (string) $q['message'], null, 0);
            return $fail($q['code'], 0, $q['message']);
        }
        $started = microtime(true);
        $slot = self::acquire($uid, (string) ($meta['feature'] ?? 'other'));
        if ($slot === null) {
            $res = ['body' => '', 'http' => 0, 'error' => 'busy', 'retry_after' => 0];
        } else {
            try {
                $res = self::post($payload);
                // یک تلاش دوباره فقط برای خطای موقت شبکه/سرور، و فقط اگر زمان کافی مانده باشد
                if (($meta['retry'] ?? true) && in_array($res['error'], ['network', 'server'], true) && microtime(true) - $started < self::timeout() / 3) {
                    usleep(600000);
                    $res = self::post($payload);
                }
            } finally {
                self::releaseSlot($slot);
            }
        }
        $j = $res['body'] !== '' ? json_decode($res['body'], true) : null;
        $usage = self::usage($j);
        $content = $res['error'] === null && is_array($j) ? ($j['choices'][0]['message']['content'] ?? null) : null;
        $ok = $res['error'] === null && is_string($content) && trim($content) !== '';
        $err = $res['error'] ?? ($ok ? null : 'bad_response');
        // v7.26 (AI-01): فقط پاسخ 2xx بدون usage طبق AI_USAGE_MISSING حساب می‌شود؛ 429/5xx/401 بدون usage = صفر
        $charged = Quota::settle((int) $q['id'], $usage, $ok, $res['http'] >= 200 && $res['http'] < 300, $err);
        if (!$ok) {
            $ms = (int) ((microtime(true) - $started) * 1000);
            Log::warning('karlix.api_error', ['error' => $err, 'http' => $res['http'], 'ms' => $ms, 'charged' => $charged]);
            $logFail((string) $err, (int) $res['http'], self::ERRORS[$err] ?? '', $res['body'], $ms);
            return $fail((string) $err, $res['http']) + ['charged' => $charged, 'retry_after' => (int) ($res['retry_after'] ?? 0)];
        }
        return ['ok' => true, 'content' => $content, 'tokens' => $usage['total'] ?? null, 'usage' => $usage, 'charged' => $charged, 'error' => null, 'message' => null, 'http' => $res['http']];
    }

    /** usage گزارش‌شده‌ی سرویس (OpenAI-سازگار): prompt_tokens / completion_tokens / total_tokens — یا null */
    private static function usage($j): ?array
    {
        $u = is_array($j) && is_array($j['usage'] ?? null) ? $j['usage'] : null;
        if ($u === null) {
            return null;
        }
        $n = static fn($v) => is_numeric($v) && $v >= 0 ? (int) $v : null;
        $r = ['input' => $n($u['prompt_tokens'] ?? $u['input_tokens'] ?? null), 'output' => $n($u['completion_tokens'] ?? $u['output_tokens'] ?? null), 'total' => $n($u['total_tokens'] ?? null)];
        if ($r['total'] === null && ($r['input'] !== null || $r['output'] !== null)) {
            $r['total'] = (int) $r['input'] + (int) $r['output'];
        }
        return $r['total'] === null ? null : $r;
    }

    /** @return array{body:string,http:int,error:?string,retry_after:int} */
    private static function post(string $payload): array
    {
        $retryAfter = 0;
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
            CURLOPT_HEADERFUNCTION => static function ($ch, string $h) use (&$retryAfter): int {
                if (stripos($h, 'retry-after:') === 0) {
                    $retryAfter = max(0, min(120, (int) trim(substr($h, 12))));
                }
                return strlen($h);
            },
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($errno !== 0 || $body === false) {
            return ['body' => '', 'http' => $http, 'error' => $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'network', 'retry_after' => 0];
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
        return ['body' => (string) $body, 'http' => $http, 'error' => $error, 'retry_after' => $retryAfter];
    }
}
