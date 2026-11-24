<?php
declare(strict_types=1);

namespace KarlixLaravel\Services\Karlix;

/**
 * سازنده‌ی ساده‌ی PDF فارسی (v7.20) برای خروجی گزارش‌های دستیار بورد — بدون کتابخانه‌ی بیرونی.
 *
 * - فونت: همان فونت پنل (Noto Sans Arabic، نسخه‌ی ثابت ۴۰۰/۷۰۰ در resources/fonts از bin/build-pdf-fonts.py)،
 *   جاسازی کامل (FontFile2، فشرده) با Identity-H و ToUnicode تا متن قابل جستجو و کپی باشد.
 * - شکل‌دهی فارسی با نویسه‌های Presentation Forms (اتصال حروف، لام‌الف) و ترتیب نمایش ساده‌شده‌ی دوجهته
 *   (پاراگراف راست‌به‌چپ؛ عدد و لاتین چپ‌به‌راست؛ علامت‌های خنثی بین دو بخش هم‌جهت همان جهت را می‌گیرند).
 * - صفحه A4 عمودی؛ چیدمان جریانی با شکست صفحه و تکرار سرستون جدول.
 * محتوای PDF را فقط کد سرور (از داده‌ی دیتابیس) می‌دهد؛ متن مدل این‌جا نمی‌آید.
 */
final class Pdf
{
    private const W = 595.28;
    private const H = 841.89;
    private const M = 36.0;

    /** [isol, fina, init, medi] — null یعنی آن شکل وجود ندارد (حرف فقط از راست می‌چسبد) */
    private const FORMS = [
        0x0621 => [0xFE80, null, null, null], 0x0622 => [0xFE81, 0xFE82, null, null], 0x0623 => [0xFE83, 0xFE84, null, null],
        0x0624 => [0xFE85, 0xFE86, null, null], 0x0625 => [0xFE87, 0xFE88, null, null], 0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E, null, null], 0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92], 0x0629 => [0xFE93, 0xFE94, null, null],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98], 0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C], 0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4], 0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8], 0x062F => [0xFEA9, 0xFEAA, null, null],
        0x0630 => [0xFEAB, 0xFEAC, null, null], 0x0631 => [0xFEAD, 0xFEAE, null, null], 0x0632 => [0xFEAF, 0xFEB0, null, null],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4], 0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8], 0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0], 0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4], 0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC], 0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0], 0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8], 0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC], 0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4], 0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8], 0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE, null, null], 0x0649 => [0xFEEF, 0xFEF0, null, null], 0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59], 0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D], 0x0698 => [0xFB8A, 0xFB8B, null, null],
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91], 0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95], 0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],
        0x0640 => [0x0640, 0x0640, 0x0640, 0x0640],
    ];

    /** لام + الف ⇐ [isol, fina] */
    private const LAM_ALEF = [0x0627 => [0xFEFB, 0xFEFC], 0x0622 => [0xFEF5, 0xFEF6], 0x0623 => [0xFEF7, 0xFEF8], 0x0625 => [0xFEF9, 0xFEFA]];

    private const MIRROR = [0x28 => 0x29, 0x29 => 0x28, 0x5B => 0x5D, 0x5D => 0x5B, 0x7B => 0x7D, 0x7D => 0x7B, 0xAB => 0xBB, 0xBB => 0xAB, 0x3C => 0x3E, 0x3E => 0x3C];

    /** @var array<string,array> */
    private static array $fontCache = [];
    /** @var array<string,array> فونت‌ها: R (معمولی) و B (ضخیم) */
    private array $fonts = [];
    /** @var array<string,array<int,int>> گلیف‌های استفاده‌شده ⇐ نویسه (برای ToUnicode و /W) */
    private array $used = ['R' => [], 'B' => []];
    /** @var string[] محتوای هر صفحه */
    private array $pages = [];
    private string $cur = '';
    private float $y = 0;
    private array $c;
    private string $footer;

    /** $colors: primary, ink, muted, soft, line, on (متن روی primary) — رنگ‌ها hex؛ $footer متن پایین هر صفحه */
    public function __construct(array $colors, string $footer = '')
    {
        $this->c = $colors + ['primary' => '#6d4ce8', 'ink' => '#1f2333', 'muted' => '#6b7085', 'soft' => '#f1eefc', 'line' => '#e2e4ec', 'on' => '#ffffff', 'danger' => '#c2333f'];
        $this->footer = $footer;
        $this->fonts['R'] = self::font('NotoSansArabic-Regular');
        $this->fonts['B'] = self::font('NotoSansArabic-Bold');
        $this->newPage();
    }

    private static function font(string $name): array
    {
        if (!isset(self::$fontCache[$name])) {
            $dir = dirname(__DIR__, 3) . '/resources/fonts/';
            $meta = json_decode((string) file_get_contents($dir . $name . '.json'), true);
            if (!is_array($meta)) {
                throw new \RuntimeException('PDF font metadata missing: ' . $name);
            }
            $meta['cmap'] = array_map('intval', $meta['cmap']);
            $meta['path'] = $dir . $meta['file'];
            self::$fontCache[$name] = $meta;
        }
        return self::$fontCache[$name];
    }

    // ───────────── شکل‌دهی و ترتیب نمایش ─────────────

    /** @return int[] */
    private static function cps(string $s): array
    {
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $s) ?? '';
        $s = str_replace(['ي', 'ك'], ['ی', 'ک'], $s);
        $out = [];
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $out[] = mb_ord($ch, 'UTF-8');
        }
        return $out;
    }

    private static function dual(int $cp): bool
    {
        return isset(self::FORMS[$cp]) && self::FORMS[$cp][2] !== null;
    }

    /** اتصال حروف (ترتیب منطقی) ⇐ نویسه‌های Presentation Forms؛ ZWNJ اتصال را می‌شکند و خودش حذف می‌شود */
    private static function shape(array $cps): array
    {
        $out = [];
        $n = count($cps);
        for ($i = 0; $i < $n; $i++) {
            $cp = $cps[$i];
            if ($cp === 0x200C) {
                continue;
            }
            if (!isset(self::FORMS[$cp])) {
                $out[] = $cp;
                continue;
            }
            $prev = $i > 0 ? $cps[$i - 1] : null;
            $prevJoins = $prev !== null && self::dual($prev);
            if ($cp === 0x0644 && $i + 1 < $n && isset(self::LAM_ALEF[$cps[$i + 1]])) {
                $out[] = self::LAM_ALEF[$cps[$i + 1]][$prevJoins ? 1 : 0];
                $i++;
                continue;
            }
            $next = $i + 1 < $n ? $cps[$i + 1] : null;
            $nextJoins = self::dual($cp) && $next !== null && isset(self::FORMS[$next]) && self::FORMS[$next][1] !== null;
            $f = self::FORMS[$cp];
            if ($prevJoins && $f[1] !== null) {
                $out[] = $nextJoins ? $f[3] : $f[1];
            } else {
                $out[] = $nextJoins ? $f[2] : $f[0];
            }
        }
        return $out;
    }

    private static function dir(int $cp): string
    {
        if (($cp >= 0x30 && $cp <= 0x39) || ($cp >= 0x6F0 && $cp <= 0x6F9) || ($cp >= 0x660 && $cp <= 0x669)) {
            return 'E';
        }
        if ($cp >= 0x66A && $cp <= 0x66C) {
            return 'N';
        }
        if (($cp >= 0x590 && $cp <= 0x8FF) || ($cp >= 0xFB1D && $cp <= 0xFDFF) || ($cp >= 0xFE70 && $cp <= 0xFEFF)) {
            return 'R';
        }
        if (($cp >= 0x41 && $cp <= 0x5A) || ($cp >= 0x61 && $cp <= 0x7A) || ($cp >= 0xC0 && $cp <= 0x24F)) {
            return 'L';
        }
        return 'N';
    }

    /** نویسه‌های شکل‌داده‌شده (منطقی) ⇐ ترتیب نمایش چپ‌به‌راست */
    private static function visual(array $cps): array
    {
        $n = count($cps);
        $t = array_map([self::class, 'dir'], $cps);
        // جداکننده‌های عدد (٪ / : . , -) کنار عدد = عدد
        for ($i = 0; $i < $n; $i++) {
            if ($t[$i] === 'N' && in_array($cps[$i], [0x25, 0x66A, 0x2F, 0x3A, 0x2E, 0x2C, 0x66B, 0x66C, 0x2D, 0x2B], true)
                && (($i > 0 && $t[$i - 1] === 'E') && ($i + 1 < $n && $t[$i + 1] === 'E') || (in_array($cps[$i], [0x25, 0x66A], true) && $i > 0 && $t[$i - 1] === 'E'))) {
                $t[$i] = 'E';
            }
        }
        $strong = array_map(static fn($x) => $x === 'E' ? 'L' : $x, $t);
        for ($i = 0; $i < $n; $i++) {
            if ($strong[$i] !== 'N') {
                continue;
            }
            $j = $i;
            while ($j < $n && $strong[$j] === 'N') {
                $j++;
            }
            $before = 'R';
            for ($k = $i - 1; $k >= 0; $k--) {
                if ($strong[$k] !== 'N') {
                    $before = $strong[$k];
                    break;
                }
            }
            $after = $j < $n ? $strong[$j] : 'R';
            $d = $before === $after ? $before : 'R';
            for ($k = $i; $k < $j; $k++) {
                $strong[$k] = $d;
            }
            $i = $j - 1;
        }
        $runs = [];
        foreach ($cps as $i => $cp) {
            $d = $strong[$i];
            if ($runs && $runs[count($runs) - 1][0] === $d) {
                $runs[count($runs) - 1][1][] = $cp;
            } else {
                $runs[] = [$d, [$cp]];
            }
        }
        $out = [];
        foreach (array_reverse($runs) as [$d, $chars]) {
            if ($d === 'R') {
                foreach (array_reverse($chars) as $cp) {
                    $out[] = self::MIRROR[$cp] ?? $cp;
                }
            } else {
                array_push($out, ...$chars);
            }
        }
        return $out;
    }

    /** متن ⇐ [[gid, cp], …] به ترتیب نمایش */
    private function glyphs(string $text, string $f): array
    {
        $font = $this->fonts[$f];
        $out = [];
        foreach (self::visual(self::shape(self::cps($text))) as $cp) {
            if ($cp === 0x0A || $cp === 0x0D || $cp === 0x09) {
                $cp = 0x20;
            }
            $g = $font['cmap'][$cp] ?? ($font['cmap'][0x3F] ?? 0);
            $out[] = [$g, $cp];
        }
        return $out;
    }

    public function width(string $text, float $size, bool $bold = false): float
    {
        $f = $bold ? 'B' : 'R';
        $w = 0;
        foreach ($this->glyphs($text, $f) as [$g]) {
            $w += $this->fonts[$f]['widths'][$g] ?? 500;
        }
        return $w * $size / $this->fonts[$f]['upm'];
    }

    /** شکستن متن به خط‌هایی که در $max جا شوند (حداکثر $lines خط؛ ادامه با «…») */
    public function wrap(string $text, float $max, float $size, bool $bold = false, int $lines = 3): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return [''];
        }
        $words = explode(' ', $text);
        $out = [];
        $line = '';
        foreach ($words as $w) {
            $try = $line === '' ? $w : $line . ' ' . $w;
            if ($this->width($try, $size, $bold) <= $max || $line === '') {
                $line = $try;
            } else {
                $out[] = $line;
                $line = $w;
            }
        }
        $out[] = $line;
        foreach ($out as $i => $l) {
            $out[$i] = $this->fit($l, $max, $size, $bold);
        }
        if (count($out) > $lines) {
            $out = array_slice($out, 0, $lines);
            $out[$lines - 1] = $this->fit($out[$lines - 1] . '…', $max, $size, $bold, true);
        }
        return $out;
    }

    /** کوتاه کردن یک خط تا در $max جا شود */
    private function fit(string $s, float $max, float $size, bool $bold, bool $forceDots = false): string
    {
        if (!$forceDots && $this->width($s, $size, $bold) <= $max) {
            return $s;
        }
        $base = rtrim($s, '…');
        while (mb_strlen($base) > 1 && $this->width($base . '…', $size, $bold) > $max) {
            $base = mb_substr($base, 0, -1);
        }
        return $base . '…';
    }

    // ───────────── رسم ─────────────

    private static function rgb(string $hex): string
    {
        $h = ltrim($hex, '#');
        if (strlen($h) === 3) {
            $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
        }
        if (!preg_match('/^[0-9a-f]{6}$/i', $h)) {
            $h = '000000';
        }
        return sprintf('%.3F %.3F %.3F', hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255);
    }

    /** متن با لبه‌ی راستِ $right (پیش‌فرض) یا لبه‌ی چپ $left؛ $y = خط پایه از بالای صفحه */
    public function textAt(string $text, float $x, float $y, float $size, string $color = 'ink', bool $bold = false, string $align = 'right'): void
    {
        $f = $bold ? 'B' : 'R';
        $gl = $this->glyphs($text, $f);
        if (!$gl) {
            return;
        }
        $w = 0;
        $hex = '';
        foreach ($gl as [$g, $cp]) {
            $w += $this->fonts[$f]['widths'][$g] ?? 500;
            $hex .= sprintf('%04X', $g);
            $this->used[$f][$g] = $cp;
        }
        $w = $w * $size / $this->fonts[$f]['upm'];
        $px = $align === 'right' ? $x - $w : ($align === 'center' ? $x - $w / 2 : $x);
        $this->cur .= sprintf("BT %s rg /F%s %.2F Tf 1 0 0 1 %.2F %.2F Tm <%s> Tj ET\n", self::rgb($this->c[$color] ?? $color), $f, $size, $px, self::H - $y, $hex);
    }

    private function rect(float $x, float $y, float $w, float $h, string $fill, ?string $stroke = null): void
    {
        $this->cur .= sprintf("%s rg %s%.2F %.2F %.2F %.2F re %s\n", self::rgb($this->c[$fill] ?? $fill),
            $stroke !== null ? self::rgb($this->c[$stroke] ?? $stroke) . ' RG 0.6 w ' : '', $x, self::H - $y - $h, $w, $h, $stroke !== null ? 'B' : 'f');
    }

    private function line(float $x1, float $y1, float $x2, float $y2, string $color = 'line', float $w = 0.6): void
    {
        $this->cur .= sprintf("%s RG %.2F w %.2F %.2F m %.2F %.2F l S\n", self::rgb($this->c[$color] ?? $color), $w, $x1, self::H - $y1, $x2, self::H - $y2);
    }

    private function newPage(): void
    {
        if ($this->cur !== '') {
            $this->pages[] = $this->cur;
        }
        $this->cur = '';
        $this->y = self::M;
    }

    /** اگر $h فضا نمانده، صفحه‌ی بعد */
    private function need(float $h): bool
    {
        if ($this->y + $h > self::H - self::M - 18) {
            $this->newPage();
            return true;
        }
        return false;
    }

    /** نوار عنوان رنگی بالای گزارش */
    public function header(string $title, string $sub = ''): void
    {
        $this->rect(self::M, $this->y, self::W - 2 * self::M, $sub !== '' ? 62 : 44, 'primary');
        $this->textAt($title, self::W - self::M - 16, $this->y + 27, 17, 'on', true);
        if ($sub !== '') {
            $this->textAt($sub, self::W - self::M - 16, $this->y + 48, 9.5, 'on');
        }
        $this->y += ($sub !== '' ? 62 : 44) + 16;
    }

    public function heading(string $text): void
    {
        $this->need(34);
        $this->y += 6;
        $this->rect(self::W - self::M - 3, $this->y, 3, 16, 'primary');
        $this->textAt($text, self::W - self::M - 10, $this->y + 12.5, 12, 'ink', true);
        $this->y += 24;
    }

    public function para(string $text, float $size = 9.5, string $color = 'muted'): void
    {
        foreach ($this->wrap($text, self::W - 2 * self::M, $size, false, 40) as $l) {
            $this->need($size + 6);
            $this->textAt($l, self::W - self::M, $this->y + $size, $size, $color);
            $this->y += $size + 5;
        }
        $this->y += 4;
    }

    /** کارت‌های عدد: [[label, value, tone?], …] در یک ردیف (حداکثر ۴ در هر ردیف) */
    public function kpis(array $items): void
    {
        $per = 4;
        $gap = 8;
        $w = (self::W - 2 * self::M - $gap * ($per - 1)) / $per;
        foreach (array_chunk($items, $per) as $row) {
            $this->need(56);
            $x = self::W - self::M;
            foreach ($row as $it) {
                $this->rect($x - $w, $this->y, $w, 48, 'soft');
                $this->textAt((string) $it[1], $x - 10, $this->y + 22, 15, ($it[2] ?? '') === 'danger' ? 'danger' : 'primary', true);
                $this->textAt($this->fit((string) $it[0], $w - 20, 8.5, false), $x - 10, $this->y + 39, 8.5, 'muted');
                $x -= $w + $gap;
            }
            $this->y += 56;
        }
        $this->y += 4;
    }

    /**
     * جدول راست‌به‌چپ. $cols = [[عنوان, سهم عرض], …] (ستون اول سمت راست)، $rows = [[متن، …], …]
     * متن هر خانه تا $maxLines خط شکسته می‌شود؛ سرستون در هر صفحه‌ی تازه تکرار می‌شود.
     */
    public function table(array $cols, array $rows, int $maxLines = 2): void
    {
        $total = array_sum(array_column($cols, 1)) ?: 1;
        $full = self::W - 2 * self::M;
        $ws = array_map(static fn($c) => $full * $c[1] / $total, $cols);
        $pad = 5;
        $size = 8.5;
        $lh = 11.5;
        $head = function () use ($cols, $ws, $pad, $full): void {
            $this->rect(self::M, $this->y, $full, 20, 'primary');
            $x = self::W - self::M;
            foreach ($cols as $i => $c) {
                $this->textAt($this->fit((string) $c[0], $ws[$i] - 2 * $pad, 8.5, true), $x - $pad, $this->y + 13.5, 8.5, 'on', true);
                $x -= $ws[$i];
            }
            $this->y += 20;
        };
        $this->need(44);
        $head();
        if (!$rows) {
            $this->textAt('موردی نیست.', self::W - self::M - $pad, $this->y + 15, 9, 'muted');
            $this->y += 24;
            return;
        }
        foreach ($rows as $ri => $row) {
            $cells = [];
            $n = 1;
            foreach ($cols as $i => $c) {
                $cells[$i] = $this->wrap((string) ($row[$i] ?? ''), $ws[$i] - 2 * $pad, $size, false, $maxLines);
                $n = max($n, count($cells[$i]));
            }
            $h = $n * $lh + 8;
            if ($this->need($h)) {
                $head();
            }
            if ($ri % 2 === 1) {
                $this->rect(self::M, $this->y, $full, $h, 'soft');
            }
            $x = self::W - self::M;
            foreach ($cols as $i => $c) {
                foreach ($cells[$i] as $li => $l) {
                    $this->textAt($l, $x - $pad, $this->y + 4 + $lh * ($li + 1) - 2.5, $size, 'ink');
                }
                $x -= $ws[$i];
            }
            $this->y += $h;
            $this->line(self::M, $this->y, self::W - self::M, $this->y);
        }
        $this->y += 10;
    }

    // ───────────── خروجی ─────────────

    public function output(): string
    {
        $this->pages[] = $this->cur;
        $this->cur = '';
        $count = count($this->pages);
        foreach ($this->pages as $i => $p) {
            $this->cur = '';
            $foot = $this->footer !== '' ? $this->footer . ' — ' : '';
            $this->textAt($foot . 'صفحه‌ی ' . fa_num($i + 1) . ' از ' . fa_num($count), self::W / 2, self::H - 18, 7.5, 'muted', false, 'center');
            $this->pages[$i] = $p . $this->cur;
        }
        $objs = [];
        $add = static function (string $body) use (&$objs): int {
            $objs[] = $body;
            return count($objs);
        };
        $catalog = $add('');           // ۱
        $pagesObj = $add('');          // ۲
        $fontRefs = [];
        foreach (['R', 'B'] as $f) {
            $fontRefs[$f] = $this->fontObjects($f, $add, $objs);
        }
        $kids = [];
        foreach ($this->pages as $content) {
            $z = gzcompress($content, 6);
            $cid = $add("<< /Length " . strlen($z) . " /Filter /FlateDecode >>\nstream\n" . $z . "\nendstream");
            $kids[] = $add(sprintf('<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /FR %d 0 R /FB %d 0 R >> >> /Contents %d 0 R >>',
                $pagesObj, self::W, self::H, $fontRefs['R'], $fontRefs['B'], $cid));
        }
        $objs[$catalog - 1] = '<< /Type /Catalog /Pages ' . $pagesObj . ' 0 R /Lang (fa-IR) /ViewerPreferences << /Direction /R2L >> >>';
        $objs[$pagesObj - 1] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn($k) => $k . ' 0 R', $kids)) . '] /Count ' . count($kids) . ' >>';
        $info = $add('<< /Producer (Karlix) /CreationDate (D:' . gmdate('YmdHis') . 'Z) >>');
        $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $offs = [];
        foreach ($objs as $i => $body) {
            $offs[$i + 1] = strlen($out);
            $out .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offs as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }
        $id = bin2hex(random_bytes(16));
        return $out . "trailer\n<< /Size " . (count($objs) + 1) . " /Root $catalog 0 R /Info $info 0 R /ID [<$id> <$id>] >>\nstartxref\n$xref\n%%EOF\n";
    }

    private function fontObjects(string $f, callable $add, array &$objs): int
    {
        $font = $this->fonts[$f];
        $scale = 1000 / $font['upm'];
        $ttf = (string) file_get_contents($font['path']);
        $z = gzcompress($ttf, 6);
        $file = $add('<< /Length ' . strlen($z) . ' /Length1 ' . strlen($ttf) . " /Filter /FlateDecode >>\nstream\n" . $z . "\nendstream");
        $name = 'KX' . $f . '+NotoSansArabic' . ($f === 'B' ? '-Bold' : '');
        $bb = array_map(static fn($v) => (int) round($v * $scale), $font['bbox']);
        $desc = $add(sprintf('<< /Type /FontDescriptor /FontName /%s /Flags 32 /FontBBox [%d %d %d %d] /ItalicAngle 0 /Ascent %d /Descent %d /CapHeight %d /StemV %d /FontFile2 %d 0 R >>',
            $name, $bb[0], $bb[1], $bb[2], $bb[3], (int) round($font['ascent'] * $scale), (int) round($font['descent'] * $scale), (int) round($font['cap'] * $scale), $f === 'B' ? 140 : 80, $file));
        $used = $this->used[$f];
        ksort($used);
        $w = '';
        foreach ($used as $g => $cp) {
            $w .= $g . ' [' . (int) round(($font['widths'][$g] ?? 500) * $scale) . '] ';
        }
        $cid = $add(sprintf('<< /Type /Font /Subtype /CIDFontType2 /BaseFont /%s /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor %d 0 R /DW 500 /W [%s] /CIDToGIDMap /Identity >>',
            $name, $desc, trim($w)));
        // ToUnicode: هر گلیف ⇐ نویسه‌ی اصلی (برای فرم‌های اتصال، همان حرف پایه)
        $map = '';
        $chunks = array_chunk($used, 100, true);
        foreach ($chunks as $chunk) {
            $map .= count($chunk) . " beginbfchar\n";
            foreach ($chunk as $g => $cp) {
                $map .= sprintf("<%04X> <%s>\n", $g, self::utf16hex(self::baseChar($cp)));
            }
            $map .= "endbfchar\n";
        }
        $cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Adobe-Identity-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n" . $map . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";
        $tu = $add('<< /Length ' . strlen($cmap) . " >>\nstream\n" . $cmap . "\nendstream");
        return $add(sprintf('<< /Type /Font /Subtype /Type0 /BaseFont /%s /Encoding /Identity-H /DescendantFonts [%d 0 R] /ToUnicode %d 0 R >>', $name, $cid, $tu));
    }

    /** نویسه‌ی Presentation Form ⇐ حرف (یا حروف) پایه، برای کپی متن از PDF */
    private static function baseChar(int $cp): array
    {
        static $rev = null;
        if ($rev === null) {
            $rev = [];
            foreach (self::FORMS as $base => $forms) {
                foreach ($forms as $x) {
                    if ($x !== null) {
                        $rev[$x] = [$base];
                    }
                }
            }
            foreach (self::LAM_ALEF as $alef => [$a, $b]) {
                $rev[$a] = $rev[$b] = [0x0644, $alef];
            }
        }
        return $rev[$cp] ?? [$cp];
    }

    private static function utf16hex(array $cps): string
    {
        $out = '';
        foreach ($cps as $cp) {
            $out .= strtoupper(bin2hex(mb_convert_encoding(mb_chr($cp, 'UTF-8'), 'UTF-16BE', 'UTF-8')));
        }
        return $out;
    }
}
