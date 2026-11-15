<?php
declare(strict_types=1);

namespace App\Services\Karlix;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Jalali;
use App\Core\Pid;

/**
 * خروجی CSV و PDF دستیار بورد (v7.20). محتوا فقط از دیتابیس و ابزارهای بک‌اند (BoardTools/BoardContext) ساخته می‌شود، نه از متن مدل.
 * فایل‌ها در storage/app/ai-exports (بیرون از webroot) با نام تصادفی؛ دانلود فقط با لینک امضاشده‌ی کوتاه‌مدت (HMAC با APP_KEY)
 * و فقط برای همان کاربر؛ فایل‌های منقضی خودکار پاک می‌شوند.
 */
final class Export
{
    public const KINDS = ['board' => 'وضعیت بورد', 'tasks' => 'لیست تسک‌ها', 'workload' => 'بار کاری', 'report' => 'گزارش بازه‌ای'];

    public static function minutes(): int
    {
        return max(5, min(240, (int) env('AI_EXPORT_MINUTES', 30)));
    }

    private static function dir(): string
    {
        $d = STORAGE_PATH . '/app/ai-exports';
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
        }
        return $d;
    }

    private static function sign(string $pid, int $exp, int $uid): string
    {
        return hash_hmac('sha256', $pid . '|' . $exp . '|' . $uid, 'ai-export|' . (string) env('APP_KEY', 'taskam'));
    }

    /** پاک‌سازی: فایل‌های منقضی و ردیف‌های قدیمی‌تر از ۷ روز (حداکثر ساعتی یک بار) */
    public static function cleanup(bool $force = false): void
    {
        $flag = STORAGE_PATH . '/cache/ai-exports-cleaned';
        if (!$force && is_file($flag) && filemtime($flag) > time() - 3600) {
            return;
        }
        @touch($flag);
        try {
            foreach (DB::all('SELECT id, file FROM ai_exports WHERE expires_at < NOW() AND file <> \'\'') as $r) {
                $f = self::dir() . '/' . basename((string) $r['file']);
                if (is_file($f)) {
                    @unlink($f);
                }
                DB::run("UPDATE ai_exports SET file = '' WHERE id = ?", [(int) $r['id']]);
            }
            DB::run('DELETE FROM ai_exports WHERE created_at < (NOW() - INTERVAL 7 DAY)');
            // فایل‌های بی‌صاحب (مثلاً ردیف حذف‌شده)
            foreach (glob(self::dir() . '/*') ?: [] as $f) {
                if (is_file($f) && filemtime($f) < time() - 86400) {
                    @unlink($f);
                }
            }
        } catch (\Throwable $e) {
            // جدول هنوز ساخته نشده
        }
    }

    /** خانه‌ی CSV امن (جلوگیری از CSV Injection) */
    public static function cell($v): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", (string) $v);
        if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r", '|', '%'], true)) {
            $s = "'" . $s;
        }
        return '"' . str_replace('"', '""', $s) . '"';
    }

    private static function jd(?string $d, ?string $t = null): string
    {
        if (empty($d)) {
            return '';
        }
        [$y, $m, $dd] = array_map('intval', explode('-', substr($d, 0, 10)));
        [$jy, $jm, $jd] = Jalali::fromGregorian($y, $m, $dd);
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd) . ($t ? ' ' . substr($t, 0, 5) : '');
    }

    /**
     * ساخت خروجی. $kind: board | tasks (با $set) | workload | report (با from/to)؛ $format: csv | pdf.
     * خروجی: بلوک export برای رابط (یا خطا).
     */
    public static function make(BoardContext $c, string $kind, string $format, array $opt = []): array
    {
        if (!isset(self::KINDS[$kind]) || !in_array($format, ['csv', 'pdf'], true)) {
            return ['ok' => false, 'message' => 'نوع خروجی نامعتبر است.'];
        }
        self::cleanup();
        [$title, $kpis, $tables] = self::content($c, $kind, $opt);
        if ($title === null) {
            return ['ok' => false, 'message' => 'لیست مورد نظر پیدا نشد.'];
        }
        $now = Jalali::format(date('Y-m-d H:i:s'));
        $who = Common::who($c->uid);
        $ptitle = (string) $c->project['title'];
        if ($format === 'csv') {
            $lines = [];
            foreach ($tables as $i => $tb) {
                if ($i > 0) {
                    $lines[] = '';
                }
                if (count($tables) > 1) {
                    $lines[] = self::cell($tb['title']);
                }
                $lines[] = implode(',', array_map([self::class, 'cell'], $tb['cols']));
                foreach ($tb['rows'] as $r) {
                    $lines[] = implode(',', array_map([self::class, 'cell'], $r));
                }
            }
            $body = "\xEF\xBB\xBF" . implode("\r\n", $lines) . "\r\n";
        } else {
            $theme = themes()[user_theme()] ?? (themes()['violet-light'] ?? []);
            $pal = $theme['palette'] ?? ['#241B3D', '#6D4AFF', '#A78BFA', '#F0ECFF', '#FAF9FF'];
            $pdf = new Pdf(['primary' => $pal[1], 'ink' => $pal[0], 'soft' => $pal[3]], app_name() . ' — ' . self::KINDS[$kind] . ' — ' . $ptitle);
            $pdf->header($title, 'پروژه‌ی «' . $ptitle . '» — تهیه‌شده ' . $now . ' برای ' . $who);
            if ($kpis) {
                $pdf->kpis($kpis);
            }
            foreach ($tables as $tb) {
                $pdf->heading($tb['title']);
                if (!empty($tb['note'])) {
                    $pdf->para($tb['note'], 8.5);
                }
                $pdf->table($tb['pdf_cols'] ?? array_map(static fn($h) => [$h, 1], $tb['cols']), $tb['pdf_rows'] ?? $tb['rows'], 2);
            }
            $pdf->para('این گزارش مستقیم از داده‌های بورد ساخته شده و فقط تسک‌هایی را دارد که ' . $who . ' در این بورد می‌بیند.', 8);
            $body = $pdf->output();
        }
        $pid = Pid::new();
        $file = bin2hex(random_bytes(16)) . '.' . $format;
        if (@file_put_contents(self::dir() . '/' . $file, $body, LOCK_EX) === false) {
            return ['ok' => false, 'message' => 'ذخیره‌ی فایل خروجی ممکن نشد.'];
        }
        $exp = time() + self::minutes() * 60;
        $name = 'karlix-' . $kind . '-' . date('Ymd-Hi') . '.' . $format;
        DB::insert('ai_exports', ['pid' => $pid, 'user_id' => $c->uid, 'project_id' => $c->projectId, 'session_id' => (int) $c->session['id'],
            'kind' => $kind, 'format' => $format, 'file' => $file, 'name' => $name, 'size' => strlen($body), 'expires_at' => date('Y-m-d H:i:s', $exp)]);
        Audit::log($c->uid, 'ai.board.export', ['project' => $c->projectId, 'kind' => $kind, 'format' => $format, 'export' => $pid] + ($c->realUid ? ['real_user_id' => $c->realUid] : []));
        return ['ok' => true, 'block' => ['type' => 'export', 'format' => $format, 'name' => $name, 'title' => $title, 'size' => strlen($body),
            'url' => url('/board/ai/download?f=' . $pid . '&e=' . $exp . '&s=' . self::sign($pid, $exp, $c->uid)), 'expires' => date('Y-m-d H:i:s', $exp),
            'expires_fa' => fa_num(self::minutes()) . ' دقیقه']];
    }

    /** دانلود: امضا، انقضا و صاحب فایل بررسی می‌شود. خروجی [path, name, format] یا null */
    public static function resolve(string $pid, int $exp, string $sig, int $uid): ?array
    {
        if (!Pid::valid($pid) || $exp < time() || !hash_equals(self::sign($pid, $exp, $uid), $sig)) {
            return null;
        }
        $r = DB::one('SELECT * FROM ai_exports WHERE pid = ? AND user_id = ? AND expires_at > NOW()', [$pid, $uid]);
        if ($r === null || (string) $r['file'] === '') {
            return null;
        }
        $path = self::dir() . '/' . basename((string) $r['file']);
        if (!is_file($path)) {
            return null;
        }
        DB::run('UPDATE ai_exports SET downloaded_at = NOW() WHERE id = ?', [(int) $r['id']]);
        return [$path, (string) $r['name'], (string) $r['format']];
    }

    // ───────── محتوا (فقط از داده‌ی واقعی) ─────────

    private const TASK_COLS = ['عنوان', 'ستون', 'مسئول', 'سررسید (شمسی)', 'پیشرفت (٪)', 'لیبل', 'اولویت', 'عقب‌افتاده'];

    private static function taskTable(BoardContext $c, array $ids, string $title): array
    {
        $u = $c->universe();
        $prio = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'];
        $rows = $pdf = [];
        foreach ($ids as $id) {
            if (!isset($u[$id])) {
                continue;
            }
            $t = $u[$id];
            $r = $c->row($t);
            $rows[] = [$t['title'], $r['col'], (string) $r['assignee'], self::jd($t['due_date'], $t['due_time']), (string) $r['progress'],
                $r['labels'] ? $r['labels'][0]['title'] : '', $prio[$t['priority']] ?? $t['priority'], $t['overdue'] ? 'بله' : ''];
            $pdf[] = [$t['title'], $r['col'], (string) $r['assignee'], $t['due_date'] ? fa_num(self::jd($t['due_date'], $t['due_time'])) : '—',
                fa_num($r['progress']) . '٪', $r['labels'] ? $r['labels'][0]['title'] : '', ($prio[$t['priority']] ?? '') . ($t['overdue'] ? ' — عقب‌افتاده' : '')];
        }
        return ['title' => $title . ' (' . fa_num(count($rows)) . ' تسک)', 'cols' => self::TASK_COLS, 'rows' => $rows,
            'pdf_cols' => [['عنوان', 3.9], ['ستون', 1.7], ['مسئول', 1.9], ['سررسید', 2.4], ['پیشرفت', 1.3], ['لیبل', 1.4], ['اولویت', 1.7]], 'pdf_rows' => $pdf];
    }

    /** خروجی [عنوان، کارت‌های عدد، جدول‌ها] */
    private static function content(BoardContext $c, string $kind, array $opt): array
    {
        if ($kind === 'tasks') {
            $set = BoardTools::set($c, $opt['set'] ?? 'last');
            if ($set === null) {
                return [null, [], []];
            }
            return [(string) $set['title'], [], [self::taskTable($c, BoardTools::setIds($c, $set), 'لیست R' . $set['seq'])]];
        }
        if ($kind === 'workload') {
            $w = BoardTools::workload($c, []);
            $tb = $w['blocks'][0];
            $rows = array_map(static fn($r) => array_map(static fn($x) => is_array($x) ? (string) $x['v'] : (string) $x, $r), $tb['rows']);
            return ['بار کاری اعضای پروژه', [], [['title' => $tb['title'], 'cols' => $tb['cols'], 'rows' => $rows, 'note' => $tb['note'] ?? '',
                'pdf_cols' => [['مسئول', 2.4], ['باز', 1], ['عقب‌افتاده', 1.2], ['سررسید ۷ روز', 1.3], ['منتظر بررسی', 1.3], ['انجام ۷ روز', 1.2], ['انجام ۳۰ روز', 1.2], ['میانه‌ی زمان', 1.4]]]]];
        }
        if ($kind === 'report') {
            $r = BoardTools::periodReport($c, $opt);
            if (isset($r['data']['error'])) {
                return [null, [], []];
            }
            $k = $r['data']['kpis'];
            $range = Jalali::format($r['data']['from'] . ' 00:00:00', false) . ' تا ' . Jalali::format($r['data']['to'] . ' 00:00:00', false);
            $per = $r['blocks'][1];
            $tables = [['title' => $per['title'], 'cols' => $per['cols'], 'rows' => array_map(static fn($row) => array_map(static fn($x) => is_array($x) ? (string) $x['v'] : (string) $x, $row), $per['rows'])]];
            if (!empty($r['data']['finished_set']) && ($set = BoardTools::set($c, $r['data']['finished_set']))) {
                $tables[] = self::taskTable($c, BoardTools::setIds($c, $set), 'تسک‌های انجام‌شده در بازه');
            }
            $open = array_keys(array_filter($c->universe(), static fn($t) => $t['overdue']));
            $tables[] = self::taskTable($c, $open, 'تسک‌های عقب‌افتاده‌ی فعلی');
            return ['گزارش ' . $range, [['ایجادشده', fa_num($k['created'])], ['انجام‌شده', fa_num($k['finished'])], ['انجام با تأخیر', fa_num($k['finished_late'])], ['عقب‌افتاده‌ی فعلی', fa_num($k['overdue_now']), 'danger']], $tables];
        }
        // board
        $s = BoardTools::boardStats($c, []);
        $k = $s['data']['kpis'];
        $cols = $s['data']['columns'];
        $ids = array_keys(array_filter($c->universe(), static fn($t) => $t['status'] !== 'archived'));
        $u = $c->universe();
        usort($ids, static fn($a, $b) => [array_search($u[$a]['status'], array_column($cols, 'key'), true), $u[$a]['due_date'] ?? '9999'] <=> [array_search($u[$b]['status'], array_column($cols, 'key'), true), $u[$b]['due_date'] ?? '9999']);
        $tables = [['title' => 'تعداد تسک در هر ستون', 'cols' => ['ستون', 'تعداد'], 'rows' => array_map(static fn($x) => [$x['label'], (string) $x['count']], $cols),
            'pdf_rows' => array_map(static fn($x) => [$x['label'], fa_num($x['count'])], $cols)]];
        if ($s['data']['bottlenecks']) {
            $tables[] = ['title' => 'گلوگاه‌ها و ریسک‌ها', 'cols' => ['مورد'], 'rows' => array_map(static fn($b) => [$b], $s['data']['bottlenecks'])];
        }
        $tables[] = self::taskTable($c, $ids, 'همه‌ی تسک‌های بورد');
        return ['وضعیت بورد «' . $c->project['title'] . '»', [['تسک باز', fa_num($k['open'])], ['عقب‌افتاده', fa_num($k['overdue']), 'danger'], ['منتظر بررسی', fa_num($k['waiting_review'])], ['راکد ۷ روز', fa_num($k['stale_7_days'])]], $tables];
    }
}
