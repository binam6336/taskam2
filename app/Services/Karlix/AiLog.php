<?php
declare(strict_types=1);

namespace App\Services\Karlix;

use App\Core\Log;

/**
 * لاگ خطاهای هوش مصنوعی (v7.23) — هر درخواستی که به خطا خورد یک بلوک در storage/logs/AI/YYYY-MM-DD.log:
 * زمان (تهران + سرور)، کد پیگیری و درخواست HTTP، کاربر، بخش (task_note | board)، شناسه‌ی فراخوانی و شماره‌ی تلاش،
 * علت خطا (کد + توضیح + HTTP + مدت)، پیام کاربر، درخواست ارسالی به مدل (مدل، max_tokens، حجم، پیام‌ها بدون system prompt)،
 * و جواب خام هوش مصنوعی. همه‌ی متن‌ها از Log::scrub می‌گذرند (توکن/کلید/موبایل/ایمیل پاک یا ماسک می‌شود) و کوتاه می‌شوند؛
 * کلید API هرگز در payload نیست (در هدر است و لاگ نمی‌شود).
 */
final class AiLog
{
    /** حداکثر طول هر بخش متنی در لاگ */
    private const MAX = 6000;

    /** توضیح فارسی کدهای خطا برای خواننده‌ی لاگ */
    public const CAUSES = [
        'not_configured' => 'هوش مصنوعی پیکربندی نشده یا درخواست بدون کاربر',
        'timeout'        => 'سرویس در زمان مجاز (AI_TIMEOUT) پاسخ نداد',
        'network'        => 'خطای شبکه/اتصال (cURL)',
        'auth'           => 'کلید سرویس نامعتبر (HTTP 401/403)',
        'rate_limited'   => 'سقف درخواست سرویس (HTTP 429)',
        'server'         => 'خطای سرور سرویس (HTTP 5xx)',
        'bad_response'   => 'پاسخ HTTP نامعتبر یا بدون محتوا',
        'bad_json'       => 'خروجی مدل JSON قابل فهم نبود',
        'exception'      => 'خطای داخلی برنامه هنگام پردازش',
        'stale'          => 'پردازش نیمه‌کاره ماند (بیش از ۱۵۰ ثانیه)',
        'AI_QUOTA'       => 'سهمیه‌ی مصرف کاربر کافی نبود',
    ];

    /**
     * $e: feature, user, real_user, key, attempt, error (کد), message (متن کاربرپسند)، http، ms، prompt (پیام کاربر)،
     * request (['model','max_tokens','bytes','messages' => [...]])، response (جواب خام)، context (آرایه‌ی آزاد)، exception (Throwable)
     */
    public static function failure(array $e): void
    {
        try {
            $req = Log::request();
            $code = (string) ($e['error'] ?? 'unknown');
            $tehran = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tehran')))->format('Y-m-d H:i:s');
            $uid = isset($e['user']) ? (int) $e['user'] : ($req['user'] ?? null);
            $out = [];
            $out[] = str_repeat('═', 22) . ' ' . $req['time'] . ' ' . str_repeat('═', 22);
            $out[] = 'time:      ' . $tehran . ' (Asia/Tehran)' . (isset($e['ms']) ? '  duration ' . (int) $e['ms'] . ' ms' : '');
            $out[] = 'request:   ' . $req['id'] . ($req['line'] !== '' ? '  ' . $req['line'] : '') . ($req['ip'] !== '' ? '  ip ' . $req['ip'] : '');
            $out[] = 'user:      ' . ($uid ? $uid . ' (' . Common::who($uid) . ')' : 'unknown') . (!empty($e['real_user']) ? '  real admin ' . (int) $e['real_user'] : '');
            $out[] = 'feature:   ' . ($e['feature'] ?? '-') . '  key ' . ($e['key'] ?? '-') . (isset($e['attempt']) ? '  attempt ' . (int) $e['attempt'] : '');
            $out[] = 'error:     ' . $code . ' — ' . (self::CAUSES[$code] ?? self::CAUSES[preg_replace('/_.*$/', '', $code)] ?? 'نامشخص')
                . (!empty($e['http']) ? '  (HTTP ' . (int) $e['http'] . ')' : '');
            if (!empty($e['message'])) {
                $out[] = 'shown:     ' . self::clip((string) $e['message'], 600);
            }
            if (!empty($e['exception']) && $e['exception'] instanceof \Throwable) {
                $x = $e['exception'];
                $out[] = 'exception: ' . get_class($x) . ': ' . self::clip($x->getMessage(), 600) . ' @ ' . basename($x->getFile()) . ':' . $x->getLine();
            }
            if (isset($e['prompt']) && trim((string) $e['prompt']) !== '') {
                $out[] = 'message:   (پیام کاربر)';
                $out[] = self::indent(self::clip((string) $e['prompt'], 2000));
            }
            if (!empty($e['request']) && is_array($e['request'])) {
                $r = $e['request'];
                $out[] = 'ai request: model ' . ($r['model'] ?? '-') . '  max_tokens ' . ($r['max_tokens'] ?? '-') . '  payload ' . ($r['bytes'] ?? '-') . ' bytes  messages ' . count($r['messages'] ?? []);
                foreach ((array) ($r['messages'] ?? []) as $i => $m) {
                    $role = (string) ($m['role'] ?? '?');
                    $content = (string) ($m['content'] ?? '');
                    // system prompt ثابت است و فقط طولش ثبت می‌شود؛ بقیه (زمینه + پیام) کوتاه‌شده
                    $out[] = '  [' . $i . '] ' . $role . ' (' . mb_strlen($content) . ' chars)' . ($role === 'system' ? ' — omitted' : '');
                    if ($role !== 'system') {
                        // تقویم ثابت چندروزه فقط جا می‌گیرد؛ از لاگ حذف می‌شود تا زمینه‌ی مهم (تسک، اعضا، درخواست) کوتاه نشود
                        $content = preg_replace('/"CALENDAR":\[.*?\](?=,"[A-Z_]+":)/su', '"CALENDAR":"(omitted)"', $content) ?? $content;
                        $out[] = self::indent(self::clip($content, 4000), 6);
                    }
                }
            }
            if (array_key_exists('response', $e)) {
                $resp = (string) ($e['response'] ?? '');
                $out[] = 'ai response: ' . ($resp === '' ? '(empty / no response)' : '(' . strlen($resp) . ' bytes)');
                if ($resp !== '') {
                    $out[] = self::indent(self::clip($resp, self::MAX));
                }
            }
            if (!empty($e['context']) && is_array($e['context'])) {
                $out[] = 'context:   ' . json_encode(Log::clean($e['context']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            }
            Log::ai(implode("\n", $out) . "\n\n", ($e['feature'] ?? '-') . ' ' . $code . ' key=' . ($e['key'] ?? '-') . ($uid ? ' user=' . $uid : ''));
        } catch (\Throwable $x) {
            // لاگ هرگز جریان اصلی را نمی‌شکند
        }
    }

    private static function clip(string $s, int $max): string
    {
        $s = Log::scrub($s);
        return mb_strlen($s) > $max ? mb_substr($s, 0, $max) . ' …[+' . (mb_strlen($s) - $max) . ' chars]' : $s;
    }

    private static function indent(string $s, int $n = 4): string
    {
        $p = str_repeat(' ', $n);
        return $p . str_replace("\n", "\n" . $p, rtrim($s));
    }
}
