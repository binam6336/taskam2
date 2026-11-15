<?php
declare(strict_types=1);

namespace App\Core;

/**
 * جستجوی متنی پنل (v5.14).
 * - یکسان‌سازی: فاصله‌های پشت سر هم = یکی، «ي/ك/ة» عربی = «ی/ک/ه»، نیم‌فاصله در متن نادیده و در عبارت جستجو = فاصله، ارقام فارسی/عربی = لاتین، حروف کوچک.
 * - تطابق دقیق: هر کلمه‌ی عبارت جستجو باید در یکی از ستون‌ها باشد (AND بین کلمه‌ها)؛ پس «گزارش  کلی» با «گزارش کلی» پیدا می‌شود.
 * - غلط تایپی: فقط وقتی تطابق دقیق هیچ نتیجه‌ای ندارد (fuzzy)، و سخت‌گیرانه: هر کلمه باید با یک کلمه‌ی متن
 *   حداکثر ۱ حرف (کلمه‌ی ۴ تا ۷ حرفی) یا ۲ حرف (۸ حرف به بالا) فرق داشته باشد؛ کلمه‌های ۱ تا ۳ حرفی باید دقیق باشند.
 *   تا نتیجه‌های بی‌ربط زیاد نشوند.
 */
final class Search
{
    private const ZWNJ = "\u{200C}";
    /** حداکثر ردیف‌هایی که برای غلط تایپی بررسی می‌شوند */
    public const FUZZY_SCAN = 3000;

    public static function norm(string $s): string
    {
        $s = str_replace(['ي', 'ى', 'ك', 'ة', self::ZWNJ, "\u{200F}", "\u{200E}", 'ـ'], ['ی', 'ی', 'ک', 'ه', '', '', '', ''], $s);
        $s = strtr($s, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $s = mb_strtolower($s, 'UTF-8');
        return trim((string) preg_replace('/\s+/u', ' ', $s));
    }

    /** @return string[] کلمه‌های عبارت جستجو (بدون تکرار) */
    public static function tokens(string $q): array
    {
        // نیم‌فاصله در عبارت جستجو = فاصله (در متن ذخیره‌شده حذف می‌شود): «گزارش‌کلی» ↔ «گزارش کلی»، «می‌خواهم» ↔ «میخواهم»
        $q = self::norm(str_replace(self::ZWNJ, ' ', $q));
        return $q === '' ? [] : array_values(array_unique(array_slice(explode(' ', $q), 0, 8)));
    }

    /** همان یکسان‌سازی در SQL روی ستون */
    public static function col(string $col): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(" . $col . ", 'ي', 'ی'), 'ك', 'ک'), 'ة', 'ه'), '" . self::ZWNJ . "', '')";
    }

    /**
     * شرط SQL: هر کلمه در یکی از ستون‌ها. خروجی [sql, params]؛ عبارت خالی = ['1', []].
     * @param string[] $cols
     */
    public static function sql(array $cols, string $q): array
    {
        $tokens = self::tokens($q);
        if (!$tokens) {
            return ['1', []];
        }
        $parts = [];
        $params = [];
        foreach ($tokens as $t) {
            $or = [];
            foreach ($cols as $c) {
                $or[] = self::col($c) . ' LIKE ?';
                $params[] = DB::like($t);
            }
            $parts[] = '(' . implode(' OR ', $or) . ')';
        }
        return ['(' . implode(' AND ', $parts) . ')', $params];
    }

    /** حداکثر فاصله‌ی ویرایشی مجاز برای یک کلمه */
    private static function allowed(int $len): int
    {
        return $len <= 3 ? 0 : ($len <= 7 ? 1 : 2);
    }

    /** فاصله‌ی ویرایشی چندبایتی (Damerau ساده: جابه‌جایی دو حرف کنار هم = ۱) با سقف برای توقف زودهنگام */
    public static function distance(string $a, string $b, int $max = 2): int
    {
        if ($a === $b) {
            return 0;
        }
        $x = preg_split('//u', $a, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $y = preg_split('//u', $b, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($x);
        $m = count($y);
        if (abs($n - $m) > $max) {
            return $max + 1;
        }
        $prev2 = [];
        $prev = range(0, $m);
        for ($i = 1; $i <= $n; $i++) {
            $cur = [$i];
            $best = $i;
            for ($j = 1; $j <= $m; $j++) {
                $cost = $x[$i - 1] === $y[$j - 1] ? 0 : 1;
                $v = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + $cost);
                if ($i > 1 && $j > 1 && $x[$i - 1] === $y[$j - 2] && $x[$i - 2] === $y[$j - 1]) {
                    $v = min($v, $prev2[$j - 2] + 1);
                }
                $cur[$j] = $v;
                $best = min($best, $v);
            }
            if ($best > $max) {
                return $max + 1;
            }
            $prev2 = $prev;
            $prev = $cur;
        }
        return $prev[$m];
    }

    /** آیا متن با همه‌ی کلمه‌های جستجو (با تحمل غلط تایپی) جور است؟ */
    public static function fuzzyMatch(string $text, array $tokens): bool
    {
        $text = self::norm($text);
        if ($text === '' || !$tokens) {
            return false;
        }
        $words = array_unique(explode(' ', (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text)));
        foreach ($tokens as $t) {
            if (mb_strpos($text, $t) !== false) {
                continue;
            }
            $tl = mb_strlen($t);
            $max = self::allowed($tl);
            if ($max === 0) {
                return false;
            }
            $hit = false;
            foreach ($words as $w) {
                $wl = mb_strlen($w);
                if ($w === '' || abs($wl - $tl) > $max) {
                    continue;
                }
                if (self::distance($t, $w, $max) <= $max) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }
        return true;
    }

    /**
     * شناسه‌ی ردیف‌هایی که با غلط تایپی جورند (فقط وقتی تطابق دقیق نتیجه‌ای نداشت صدا زده شود).
     * @param array<int,array<string,mixed>> $rows ردیف‌ها با کلید id و ستون‌های متنی
     * @param string[] $fields
     * @return int[]
     */
    public static function fuzzyIds(array $rows, string $q, array $fields, int $limit = 200): array
    {
        $tokens = self::tokens($q);
        if (!$tokens || !array_filter($tokens, static fn($t) => mb_strlen($t) >= 4)) {
            return [];                                   // عبارتِ فقط کلمه‌های کوتاه: غلط تایپی پذیرفته نمی‌شود
        }
        $out = [];
        foreach ($rows as $r) {
            $txt = implode(' ', array_map(static fn($f) => (string) ($r[$f] ?? ''), $fields));
            if (self::fuzzyMatch($txt, $tokens)) {
                $out[] = (int) $r['id'];
                if (count($out) >= $limit) {
                    break;
                }
            }
        }
        return $out;
    }
}
