<?php
declare(strict_types=1);

namespace App\Services\Karlix;

use App\Core\DB;
use App\Core\Jalali;
use App\Core\Log;

/**
 * سهمیه‌ی مصرف هوش مصنوعی (v7.21) — تنها مسیر کنترل و ثبت مصرف؛ Agnes::chat پیش و پس از هر فراخوانی مدل از این‌جا عبور می‌کند.
 *
 * سه سقف مستقل برای هر کاربر (ai_quotas؛ NULL = نامحدود): روزانه (روز تقویم تهران)، ماهانه (ماه شمسی) و کل.
 * مصرف = توکن واقعی گزارش‌شده‌ی سرویس (usage: prompt_tokens + completion_tokens = total_tokens).
 *
 * هم‌زمانی: reserve() در یک تراکنش ردیف سهمیه‌ی کاربر را قفل می‌کند (SELECT … FOR UPDATE)، مصرف + رزروهای در جریان + رزرو این
 * درخواست را با هر سقف مقایسه می‌کند و فقط اگر جا باشد رزرو را در شمارنده‌ها (ai_usage_counters) و یک ردیف ai_usage (یکتا روی
 * request_key) ثبت می‌کند؛ پس درخواست‌های هم‌زمان نمی‌توانند با هم از سقف بگذرند. settle() بعد از پاسخ، رزرو را برمی‌دارد و مصرف واقعی
 * را می‌نویسد (فقط یک بار؛ status = reserved شرط به‌روزرسانی است).
 * پاسخ بدون usage: سیاست AI_USAGE_MISSING (reserved = همان رزرو به‌عنوان سقف مصرف حساب می‌شود | zero = صفر)؛ خطای بدون پاسخ = صفر.
 */
final class Quota
{
    /** پیش‌فرض اولیه (قابل تغییر در پنل مدیر ← تنظیمات ← مصرف AI؛ null = نامحدود) */
    public const DEFAULTS = ['daily' => 100000, 'monthly' => 2000000, 'total' => null];
    public const KEY = 'ai.quota.defaults';
    public const KINDS = ['daily' => 'روزانه', 'monthly' => 'ماهانه', 'total' => 'کلی'];
    private const COL = ['daily' => 'daily_limit', 'monthly' => 'monthly_limit', 'total' => 'total_limit'];
    /** رزرو در جریان بعد از این چند ثانیه رها می‌شود (فرایند قطع شده) */
    public const STALE_SECONDS = 600;
    public const FEATURES = ['task_note' => 'کارلیکس در یادداشت تسک', 'board' => 'دستیار بورد'];

    // ───────── پیش‌فرض و سهمیه‌ی کاربر ─────────

    public static function defaults(): array
    {
        static $d = null;
        if ($d === null) {
            $d = self::DEFAULTS;
            try {
                $raw = json_decode((string) DB::value('SELECT body FROM content_texts WHERE `key` = ?', [self::KEY]), true);
                if (is_array($raw)) {
                    foreach (self::COL as $k => $_) {
                        if (array_key_exists($k, $raw)) {
                            $d[$k] = $raw[$k] === null ? null : max(0, (int) $raw[$k]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // جدول/کلید هنوز نیست
            }
        }
        return $d;
    }

    /** ذخیره‌ی پیش‌فرض و اعمال روی همه‌ی کاربرانی که سهمیه‌ی اختصاصی ندارند */
    public static function setDefaults(array $d, int $by): int
    {
        DB::run('INSERT INTO content_texts (`key`, body, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body), updated_by = VALUES(updated_by)',
            [self::KEY, json_encode($d), $by]);
        return DB::run('UPDATE ai_quotas SET daily_limit = ?, monthly_limit = ?, total_limit = ?, updated_by = ? WHERE is_custom = 0', [$d['daily'], $d['monthly'], $d['total'], $by]);
    }

    /** ردیف سهمیه (اگر نباشد با پیش‌فرض فعلی ساخته می‌شود — کاربر جدید یا قدیمی) */
    public static function ensure(int $uid): void
    {
        $d = self::defaults();
        DB::run('INSERT IGNORE INTO ai_quotas (user_id, daily_limit, monthly_limit, total_limit, is_custom) VALUES (?, ?, ?, ?, 0)', [$uid, $d['daily'], $d['monthly'], $d['total']]);
    }

    /** @return array{daily:?int,monthly:?int,total:?int,custom:bool} */
    public static function limits(int $uid): array
    {
        $r = DB::one('SELECT * FROM ai_quotas WHERE user_id = ?', [$uid]);
        if ($r === null) {
            self::ensure($uid);
            $d = self::defaults();
            return $d + ['custom' => false];
        }
        $n = static fn($v) => $v === null ? null : (int) $v;
        return ['daily' => $n($r['daily_limit']), 'monthly' => $n($r['monthly_limit']), 'total' => $n($r['total_limit']), 'custom' => (bool) $r['is_custom']];
    }

    /** سهمیه‌ی اختصاصی (مدیر)؛ $limits = null یعنی برگشت به پیش‌فرض. سوابق مصرف دست نمی‌خورد. */
    public static function setUser(int $uid, ?array $limits, int $by): void
    {
        self::ensure($uid);
        if ($limits === null) {
            $d = self::defaults();
            DB::run('UPDATE ai_quotas SET daily_limit = ?, monthly_limit = ?, total_limit = ?, is_custom = 0, updated_by = ? WHERE user_id = ?', [$d['daily'], $d['monthly'], $d['total'], $by, $uid]);
            return;
        }
        DB::run('UPDATE ai_quotas SET daily_limit = ?, monthly_limit = ?, total_limit = ?, is_custom = 1, updated_by = ? WHERE user_id = ?',
            [$limits['daily'], $limits['monthly'], $limits['total'], $by, $uid]);
    }

    // ───────── دوره‌ها ─────────

    /** کلید دوره‌ها برای اکنون: روز تهران، ماه شمسی، کل */
    public static function periods(?\DateTimeImmutable $at = null): array
    {
        $d = $at ?? Common::today();
        [$jy, $jm] = Jalali::fromGregorian((int) $d->format('Y'), (int) $d->format('n'), (int) $d->format('j'));
        return ['daily' => 'd:' . $d->format('Y-m-d'), 'monthly' => 'm:' . sprintf('%04d-%02d', $jy, $jm), 'total' => 'total'];
    }

    /** زمان بازنشانی هر دوره به زبان کاربر */
    public static function resetText(string $kind): string
    {
        if ($kind === 'daily') {
            $t = Common::today()->modify('+1 day')->format('Y-m-d');
            return 'فردا (' . jdate($t . ' 00:00:00', false, true) . ')';
        }
        if ($kind === 'monthly') {
            $first = (new \DateTimeImmutable(Common::now()['jalali_month_end']))->modify('+1 day')->format('Y-m-d');
            return jdate($first . ' 00:00:00', false, true);
        }
        return '';
    }

    /** مصرف و رزرو جاری کاربر در سه دوره */
    public static function counters(int $uid): array
    {
        $p = self::periods();
        $rows = DB::all('SELECT period, used, reserved FROM ai_usage_counters WHERE user_id = ? AND period IN (?, ?, ?)', [$uid, $p['daily'], $p['monthly'], $p['total']]);
        $by = array_column($rows, null, 'period');
        $out = [];
        foreach ($p as $k => $key) {
            $out[$k] = ['used' => (int) ($by[$key]['used'] ?? 0), 'reserved' => (int) ($by[$key]['reserved'] ?? 0)];
        }
        return $out;
    }

    /** وضعیت کامل برای نمایش (پنل کاربر و مدیر) */
    public static function status(int $uid): array
    {
        $lim = self::limits($uid);
        $c = self::counters($uid);
        $out = ['custom' => $lim['custom'], 'blocked' => null];
        foreach (self::KINDS as $k => $label) {
            $l = $lim[$k];
            $u = $c[$k]['used'];
            $out[$k] = ['label' => $label, 'used' => $u, 'reserved' => $c[$k]['reserved'], 'limit' => $l, 'unlimited' => $l === null,
                'percent' => $l === null ? null : ($l > 0 ? min(100, (int) floor($u * 100 / $l)) : 100), 'remaining' => $l === null ? null : max(0, $l - $u),
                'reset' => $k === 'total' ? null : self::resetText($k)];
        }
        $b = self::check($uid, 0);
        $out['blocked'] = $b['message'] ?? null;
        $out['blocked_kind'] = $b['kind'] ?? null;
        return $out;
    }

    /** پیام پایان سهمیه (متن ثابت با جای خالی؛ در config/error_texts.php هم هست) */
    public static function message(string $kind, bool $insufficient = false, int $remaining = 0): string
    {
        if ($kind === 'total') {
            return $insufficient
                ? 'باقی‌مانده‌ی سقف کلی مصرف هوش مصنوعی شما (' . fa_num(number_format($remaining)) . ' توکن) برای این درخواست کافی نیست؛ برای افزایش سقف با مدیر سیستم هماهنگ کنید.'
                : 'سقف کلی مصرف هوش مصنوعی شما تکمیل شده است؛ برای افزایش سقف با مدیر سیستم هماهنگ کنید.';
        }
        $lbl = self::KINDS[$kind] . "\u{200C}ی";              // روزانه‌ی / ماهانه‌ی
        $when = $kind === 'daily' ? 'سهمیه‌ی روزانه ' . self::resetText('daily') . ' دوباره فعال می‌شود.' : 'سهمیه‌ی ماهانه از ' . self::resetText('monthly') . ' دوباره فعال می‌شود.';
        return $insufficient
            ? 'باقی‌مانده‌ی سهمیه‌ی ' . $lbl . ' هوش مصنوعی شما (' . fa_num(number_format($remaining)) . ' توکن) برای این درخواست کافی نیست؛ ' . $when
            : 'سهمیه‌ی مصرف ' . $lbl . ' هوش مصنوعی شما به پایان رسیده است؛ ' . $when;
    }

    /**
     * آیا درخواستی با رزرو $need توکن مجاز است؟ null = بله؛ وگرنه ['kind', 'code', 'message'].
     * $need = 0: فقط «تمام شده یا نه» (برای نمایش و پیش‌بررسی).
     */
    public static function check(int $uid, int $need, ?array $lim = null, ?array $c = null): ?array
    {
        $lim = $lim ?? self::limits($uid);
        $c = $c ?? self::counters($uid);
        foreach (['daily', 'monthly', 'total'] as $k) {
            $l = $lim[$k];
            if ($l === null) {
                continue;
            }
            $busy = $c[$k]['used'] + $c[$k]['reserved'];
            if ($c[$k]['used'] >= $l) {
                return ['kind' => $k, 'code' => 'quota_' . $k, 'message' => self::message($k)];
            }
            if ($need > 0 && $busy + $need > $l) {
                return ['kind' => $k, 'code' => 'quota_' . $k, 'message' => self::message($k, true, max(0, $l - $busy))];
            }
        }
        return null;
    }

    /** تخمین رزرو: حجم ورودی (بایت ÷ AI_RESERVE_BYTES_PER_TOKEN، رو به بالا) + سقف خروجی max_tokens */
    public static function estimate(string $payload, int $maxTokens): int
    {
        $div = max(1.0, (float) env('AI_RESERVE_BYTES_PER_TOKEN', 3));
        return (int) ceil(strlen($payload) / $div) + $maxTokens;
    }

    // ───────── رزرو و ثبت ─────────

    /**
     * رزرو پیش از فراخوانی مدل. خروجی: ['ok' => true, 'id' => شناسه‌ی ai_usage] یا ['ok' => false, 'code', 'message'].
     * $key یکتاست؛ تکرار همان کلید (ثبت دوباره‌ی یک درخواست) رد می‌شود.
     */
    public static function reserve(int $uid, string $feature, string $key, ?string $model, int $tokens, ?int $realUid = null): array
    {
        self::ensure($uid);
        $p = self::periods();
        try {
            return DB::transaction(static function () use ($uid, $feature, $key, $model, $tokens, $realUid, $p) {
                DB::one('SELECT user_id FROM ai_quotas WHERE user_id = ? FOR UPDATE', [$uid]);       // قفل سهمیه‌ی همین کاربر
                self::expireStale($uid);
                if ((int) DB::value('SELECT COUNT(*) FROM ai_usage WHERE request_key = ?', [$key]) > 0) {
                    return ['ok' => false, 'code' => 'duplicate', 'message' => 'این درخواست قبلاً ثبت شده است.'];
                }
                $deny = self::check($uid, $tokens);
                if ($deny !== null) {
                    return ['ok' => false, 'code' => $deny['code'], 'message' => $deny['message'], 'kind' => $deny['kind']];
                }
                $id = DB::insert('ai_usage', ['user_id' => $uid, 'real_user_id' => $realUid, 'feature' => mb_substr($feature, 0, 24), 'model' => $model,
                    'request_key' => mb_substr($key, 0, 100), 'status' => 'reserved', 'reserved_tokens' => $tokens,
                    'day' => substr($p['daily'], 2), 'month' => substr($p['monthly'], 2)]);
                foreach ($p as $period) {
                    DB::run('INSERT INTO ai_usage_counters (user_id, period, used, reserved) VALUES (?, ?, 0, ?) ON DUPLICATE KEY UPDATE reserved = reserved + VALUES(reserved)', [$uid, $period, $tokens]);
                }
                return ['ok' => true, 'id' => $id];
            });
        } catch (\Throwable $e) {
            Log::error($e, 'karlix', ['quota' => 'reserve']);
            return ['ok' => false, 'code' => 'quota_error', 'message' => 'بررسی سهمیه‌ی هوش مصنوعی ممکن نشد؛ کمی بعد دوباره امتحان کنید.'];
        }
    }

    /**
     * ثبت مصرف واقعی و برداشتن رزرو (فقط یک بار برای هر ردیف).
     * $usage = ['input' => ?int, 'output' => ?int, 'total' => ?int] یا null وقتی سرویس usage نداد؛ $reached = پاسخی از سرویس رسید.
     * خروجی: توکنی که به سهمیه نوشته شد.
     */
    public static function settle(int $id, ?array $usage, bool $ok, bool $reached, ?string $error = null): int
    {
        try {
            return (int) DB::transaction(static function () use ($id, $usage, $ok, $reached, $error) {
                $row = DB::one("SELECT * FROM ai_usage WHERE id = ? AND status = 'reserved' FOR UPDATE", [$id]);
                if ($row === null) {
                    return 0;                                   // قبلاً ثبت/منقضی شده: ثبت تکراری نداریم
                }
                [$in, $out, $total, $status] = self::charge($row, $usage, $ok, $reached);
                self::apply($row, $total);
                $cost = self::cost($in, $out);
                DB::run('UPDATE ai_usage SET status = ?, input_tokens = ?, output_tokens = ?, total_tokens = ?, cost = ?, currency = ?, error = ?, finished_at = NOW() WHERE id = ?',
                    [$status, $in, $out, $total, $cost, $cost !== null ? self::currency() : null, $error !== null ? mb_substr($error, 0, 32) : null, $id]);
                return $total;
            });
        } catch (\Throwable $e) {
            Log::error($e, 'karlix', ['quota' => 'settle', 'usage' => $id]);
            return 0;
        }
    }

    /** مقدار قابل احتساب: [input, output, total, status] */
    private static function charge(array $row, ?array $usage, bool $ok, bool $reached): array
    {
        if ($usage !== null && ($usage['total'] !== null || $usage['input'] !== null || $usage['output'] !== null)) {
            $in = max(0, (int) ($usage['input'] ?? 0));
            $out = max(0, (int) ($usage['output'] ?? 0));
            $total = $usage['total'] !== null ? max(0, (int) $usage['total']) : $in + $out;
            return [$in, $out, $total, $ok ? 'ok' : 'error'];
        }
        if (!$reached) {
            return [0, 0, 0, 'error'];                          // پاسخی نرسید (شبکه/زمان): مصرفی گزارش نشده
        }
        // پاسخ بدون usage: حدس نمی‌زنیم؛ طبق سیاست تنظیم‌شده
        $charged = self::missingPolicy() === 'zero' ? 0 : (int) $row['reserved_tokens'];
        return [0, 0, $charged, 'no_usage'];
    }

    public static function missingPolicy(): string
    {
        return env('AI_USAGE_MISSING', 'reserved') === 'zero' ? 'zero' : 'reserved';
    }

    /** برداشتن رزرو و افزودن مصرف در شمارنده‌های دوره‌های همان ردیف */
    private static function apply(array $row, int $total): void
    {
        [$jy, $jm] = explode('-', (string) $row['month']);
        $periods = ['d:' . $row['day'], 'm:' . $jy . '-' . $jm, 'total'];
        foreach ($periods as $p) {
            DB::run('INSERT INTO ai_usage_counters (user_id, period, used, reserved) VALUES (?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE used = used + VALUES(used), reserved = IF(reserved >= ?, reserved - ?, 0)',
                [(int) $row['user_id'], $p, $total, (int) $row['reserved_tokens'], (int) $row['reserved_tokens']]);
        }
    }

    /** رزروهای رهاشده (فرایند قطع شده) — سیاست پاسخ بدون usage روی آن‌ها اعمال می‌شود */
    private static function expireStale(int $uid): void
    {
        foreach (DB::all("SELECT * FROM ai_usage WHERE user_id = ? AND status = 'reserved' AND created_at < (NOW() - INTERVAL " . self::STALE_SECONDS . ' SECOND) FOR UPDATE', [$uid]) as $row) {
            $total = self::missingPolicy() === 'zero' ? 0 : (int) $row['reserved_tokens'];
            self::apply($row, $total);
            DB::run("UPDATE ai_usage SET status = 'expired', total_tokens = ?, error = 'stale', finished_at = NOW() WHERE id = ?", [$total, (int) $row['id']]);
        }
    }

    /** هزینه‌ی تخمینی از روی نرخ .env (اختیاری؛ برای توسعه‌ی بعدی) */
    private static function cost(int $in, int $out): ?float
    {
        $pi = (float) env('AI_PRICE_INPUT_PER_1M', 0);
        $po = (float) env('AI_PRICE_OUTPUT_PER_1M', 0);
        return $pi > 0 || $po > 0 ? round(($in * $pi + $out * $po) / 1000000, 6) : null;
    }

    private static function currency(): string
    {
        $c = strtoupper((string) env('AI_PRICE_CURRENCY', 'USD'));
        return preg_match('/^[A-Z]{3}$/', $c) ? $c : 'USD';
    }

    /** مصرف ماه جاری به تفکیک بخش (پنل کاربر) */
    public static function byFeature(int $uid): array
    {
        $m = substr(self::periods()['monthly'], 2);
        $out = [];
        foreach (DB::all("SELECT feature, COUNT(*) n, SUM(total_tokens) t FROM ai_usage WHERE user_id = ? AND month = ? AND status <> 'reserved' GROUP BY feature", [$uid, $m]) as $r) {
            $out[] = ['feature' => self::FEATURES[$r['feature']] ?? $r['feature'], 'requests' => (int) $r['n'], 'tokens' => (int) $r['t']];
        }
        return $out;
    }
}
