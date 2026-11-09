<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * ستون‌های بورد هر پروژه (v6.1).
 * - هر پروژه بورد خودش را دارد: projects.board_config (JSON)؛ NULL = «بورد پیش‌فرض» که مدیر سیستم در «محتوا ← بورد» می‌چیند
 *   (content_texts، کلید board.default) و اگر آن هم نباشد BUILTIN (همان چیدمان قدیمی).
 * - سه ستون ماهیت ثابت دارند و حذف نمی‌شوند؛ فقط نام، آیکون و رنگشان عوض می‌شود: rework (بازبینی مسئول)،
 *   review (بررسی سرپرست) و done (انجام‌شده). بقیه‌ی ستون‌ها (کلید c_… یا کلیدهای قدیمی backlog/todo/in_progress) آزادند.
 * - «وضعیت اول بعد از ایجاد تسک» (start): ستونی که تسک تازه‌ی پروژه در آن قرار می‌گیرد (فقط ستون‌های آزاد).
 * - tasks.board_status = کلید ستون؛ تسکی که کلیدش در بورد پروژه نیست، در ستون شروع نمایش داده می‌شود.
 */
final class BoardConfig
{
    public const FIXED = ['rework', 'review', 'done'];
    public const CONTENT_KEY = 'board.default';
    public const MAX_COLUMNS = 12;
    public const MAX_LABEL = 30;
    public const MAX_DESC = 120;

    /** آیکون‌های مجاز ستون (Font Awesome solid) */
    public const ICONS = [
        'fa-inbox', 'fa-list-ul', 'fa-person-running', 'fa-magnifying-glass', 'fa-circle-check', 'fa-rotate-left',
        'fa-hourglass-half', 'fa-flag', 'fa-bug', 'fa-code', 'fa-palette', 'fa-truck', 'fa-rocket', 'fa-lightbulb',
        'fa-pause', 'fa-star', 'fa-comments', 'fa-clipboard-check', 'fa-box', 'fa-bolt', 'fa-calendar-check', 'fa-flask',
    ];

    /** رنگ‌های مجاز (کلید = کلاس tone-* در app.css) */
    public const TONES = [
        'muted' => 'خاکستری', 'info' => 'آبی', 'teal' => 'فیروزه‌ای', 'ok' => 'سبز', 'warn' => 'زرد',
        'orange' => 'نارنجی', 'red' => 'قرمز', 'pink' => 'صورتی', 'purple' => 'بنفش',
    ];

    /** چیدمان کارخانه (همان بورد پیش از v6.1) */
    public const BUILTIN = [
        'columns' => [
            ['key' => 'rework', 'label' => 'بازبینی مسئول', 'icon' => 'fa-rotate-left', 'tone' => 'orange', 'desc' => 'سرپرست ایرادی دیده و تسک برای اصلاح به مسئول برگشته'],
            ['key' => 'backlog', 'label' => 'برنامه‌ریزی‌نشده', 'icon' => 'fa-inbox', 'tone' => 'muted', 'desc' => 'ثبت شده ولی هنوز برای انجام انتخاب نشده'],
            ['key' => 'todo', 'label' => 'آماده انجام', 'icon' => 'fa-list-ul', 'tone' => 'info', 'desc' => 'انتخاب شده، کار هنوز شروع نشده'],
            ['key' => 'in_progress', 'label' => 'در حال انجام', 'icon' => 'fa-person-running', 'tone' => 'warn', 'desc' => 'مسئول کار را شروع کرده'],
            ['key' => 'review', 'label' => 'بررسی سرپرست', 'icon' => 'fa-magnifying-glass', 'tone' => 'purple', 'desc' => 'انجام شده و منتظر بررسی سرپرست'],
            ['key' => 'done', 'label' => 'انجام‌شده', 'icon' => 'fa-circle-check', 'tone' => 'ok', 'desc' => 'تکمیل شده و معیارهای اتمام برآورده شده'],
        ],
        'start' => 'backlog',
    ];

    /** توضیح ماهیت ستون‌های ثابت (در تنظیمات بورد) */
    public const FIXED_ROLE = [
        'rework' => 'تسکی که سرپرست برای اصلاح برگردانده اینجا می‌آید.',
        'review' => 'تیک «انجام شد» تسک را اینجا می‌فرستد تا سرپرست تأیید کند.',
        'done' => 'فقط با تأیید در «بررسی سرپرست»؛ تسک تکمیل‌شده.',
    ];

    private static array $cache = [];
    private static ?array $template = null;

    /** بورد پیش‌فرض (مدیر سیستم) یا BUILTIN */
    public static function template(): array
    {
        if (self::$template !== null) {
            return self::$template;
        }
        try {
            $raw = DB::value('SELECT body FROM content_texts WHERE `key` = ?', [self::CONTENT_KEY]);
        } catch (\Throwable $e) {
            $raw = null;
        }
        $d = is_string($raw) ? json_decode($raw, true) : null;
        return self::$template = is_array($d) ? self::normalize($d) : self::normalize(self::BUILTIN);
    }

    public static function templateIsCustom(): bool
    {
        try {
            return (bool) DB::value('SELECT 1 FROM content_texts WHERE `key` = ?', [self::CONTENT_KEY]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** بورد یک پروژه (بدون پروژه = پیش‌فرض) */
    public static function project(?int $projectId): array
    {
        $pid = (int) $projectId;
        if ($pid <= 0) {
            return self::template();
        }
        if (!isset(self::$cache[$pid])) {
            try {
                $raw = DB::value('SELECT board_config FROM projects WHERE id = ?', [$pid]);
            } catch (\Throwable $e) {
                $raw = null;                                    // پیش از migration ۰۴۰
            }
            $d = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            self::$cache[$pid] = is_array($d) ? self::normalize($d) + ['custom' => true] : self::template() + ['custom' => false];
        }
        return self::$cache[$pid];
    }

    public static function flush(?int $projectId = null): void
    {
        if ($projectId === null) {
            self::$cache = [];
            self::$template = null;
        } else {
            unset(self::$cache[$projectId]);
        }
    }

    /**
     * وضعیت‌های بورد پروژه به ترتیب: key => [label, icon, tone, desc, en, fixed]
     * (en: زیرعنوان انگلیسی فقط برای ستون‌های ثابت و کلیدهای قدیمی)
     */
    public static function statuses(?int $projectId): array
    {
        $out = [];
        foreach (self::project($projectId)['columns'] as $c) {
            $out[$c['key']] = $c + ['en' => self::EN[$c['key']] ?? '', 'fixed' => in_array($c['key'], self::FIXED, true)];
        }
        return $out;
    }

    private const EN = ['rework' => 'Rework', 'backlog' => 'Backlog', 'todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'In Review', 'done' => 'Done'];

    public static function keys(?int $projectId): array
    {
        return array_column(self::project($projectId)['columns'], 'key');
    }

    public static function has(?int $projectId, string $key): bool
    {
        return in_array($key, self::keys($projectId), true);
    }

    public static function start(?int $projectId): string
    {
        return (string) self::project($projectId)['start'];
    }

    /** نام ستون (برای تاریخچه و پیام‌ها): بورد پروژه، بعد پیش‌فرض، بعد BUILTIN، وگرنه خود کلید */
    public static function label(?int $projectId, string $key): string
    {
        if ($key === 'archived') {
            return 'آرشیو';
        }
        foreach ([self::project($projectId), self::template(), self::BUILTIN] as $cfg) {
            foreach ($cfg['columns'] as $c) {
                if ($c['key'] === $key) {
                    return (string) $c['label'];
                }
            }
        }
        return $key;
    }

    /** اطلاعات نمایشی یک ستون (برچسب، آیکون، رنگ) با همان ترتیب جستجوی label() */
    public static function info(?int $projectId, string $key): array
    {
        if ($key === 'archived') {
            return ['key' => 'archived', 'label' => 'آرشیو', 'icon' => 'fa-box-archive', 'tone' => 'muted', 'desc' => '', 'en' => 'Archived', 'fixed' => true];
        }
        foreach ([self::project($projectId), self::template(), self::BUILTIN] as $cfg) {
            foreach ($cfg['columns'] as $c) {
                if ($c['key'] === $key) {
                    return $c + ['en' => self::EN[$key] ?? '', 'fixed' => in_array($key, self::FIXED, true)];
                }
            }
        }
        return ['key' => $key, 'label' => $key, 'icon' => 'fa-list-ul', 'tone' => 'muted', 'desc' => '', 'en' => '', 'fixed' => false];
    }

    /**
     * مرحله‌ی بعد (دکمه‌ی «انتقال به …»): بازبینی مسئول ← ستون شروع؛ بررسی سرپرست ← انجام‌شده؛
     * بقیه ← ستون بعدی در ترتیب بورد (به‌جز بازبینی مسئول). انجام‌شده مرحله‌ی بعد ندارد.
     */
    public static function next(?int $projectId, string $st): ?string
    {
        if ($st === 'rework') {
            return self::start($projectId);
        }
        if ($st === 'review') {
            return 'done';
        }
        if ($st === 'done' || $st === 'archived') {
            return null;
        }
        $keys = array_values(array_filter(self::keys($projectId), static fn($k) => $k !== 'rework'));
        $i = array_search($st, $keys, true);
        if ($i === false || !isset($keys[$i + 1])) {
            return null;
        }
        return $keys[$i + 1] === 'done' ? 'review' : $keys[$i + 1];
    }

    /** ستون‌هایی که برای هر نفر سقف تعداد تسک دارند: همه جز انجام‌شده و ستون شروع */
    public static function limited(?int $projectId): array
    {
        $start = self::start($projectId);
        return array_values(array_filter(self::keys($projectId), static fn($k) => $k !== 'done' && $k !== $start));
    }

    /**
     * پاک‌سازی چیدمان. ستون‌های ثابت حتماً هستند (اگر نباشند در جای BUILTIN اضافه می‌شوند)، کلیدها یکتا،
     * آیکون/رنگ از فهرست مجاز، نام خالی = نام پیش‌فرض، و ستون شروع یکی از ستون‌های آزاد.
     */
    public static function normalize(array $in): array
    {
        $cols = [];
        $seen = [];
        foreach (array_slice(is_array($in['columns'] ?? null) ? $in['columns'] : [], 0, self::MAX_COLUMNS) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $key = (string) ($c['key'] ?? '');
            if (!preg_match('/^[a-z][a-z0-9_]{1,23}$/', $key) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $def = self::builtinCol($key);
            $label = trim((string) preg_replace('/\s+/u', ' ', (string) ($c['label'] ?? '')));
            $label = mb_substr($label !== '' ? $label : ($def['label'] ?? 'ستون'), 0, self::MAX_LABEL);
            $cols[] = [
                'key' => $key, 'label' => $label,
                'icon' => in_array($c['icon'] ?? '', self::ICONS, true) ? (string) $c['icon'] : ($def['icon'] ?? 'fa-list-ul'),
                'tone' => isset(self::TONES[$c['tone'] ?? '']) ? (string) $c['tone'] : ($def['tone'] ?? 'muted'),
                'desc' => mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) ($c['desc'] ?? ($def['desc'] ?? '')))), 0, self::MAX_DESC),
            ];
        }
        // ستون‌های ثابت جاافتاده
        foreach (self::FIXED as $fk) {
            if (isset($seen[$fk])) {
                continue;
            }
            $col = self::builtinCol($fk);
            if ($fk === 'rework') {
                array_unshift($cols, $col);
            } elseif ($fk === 'review') {
                $di = array_search('done', array_column($cols, 'key'), true);
                array_splice($cols, $di === false ? count($cols) : (int) $di, 0, [$col]);
            } else {
                $cols[] = $col;
            }
            $seen[$fk] = true;
        }
        $free = array_values(array_filter(array_column($cols, 'key'), static fn($k) => !in_array($k, self::FIXED, true)));
        if (!$free) {
            $b = self::builtinCol('backlog');
            array_splice($cols, 1, 0, [$b]);
            $free = ['backlog'];
        }
        $start = (string) ($in['start'] ?? '');
        return ['columns' => $cols, 'start' => in_array($start, $free, true) ? $start : $free[0]];
    }

    private static function builtinCol(string $key): array
    {
        foreach (self::BUILTIN['columns'] as $c) {
            if ($c['key'] === $key) {
                return $c;
            }
        }
        return [];
    }

    /** کلید تازه برای ستون جدید */
    public static function newKey(array $taken): string
    {
        do {
            $k = 'c_' . substr(bin2hex(random_bytes(4)), 0, 6);
        } while (in_array($k, $taken, true));
        return $k;
    }

    /** تعداد تسک‌های هر ستون پروژه (برای هشدار حذف ستون) */
    public static function counts(int $projectId): array
    {
        return array_map('intval', array_column(DB::all('SELECT board_status k, COUNT(*) n FROM tasks WHERE project_id = ? AND archived_at IS NULL AND blocked_at IS NULL GROUP BY board_status', [$projectId]), 'n', 'k'));
    }

    /**
     * ذخیره‌ی بورد پروژه. $moves: [کلید ستون حذف‌شده => کلید مقصد] برای تسک‌های ستون‌های حذف‌شده.
     * $reset = بازگشت به بورد پیش‌فرض. خروجی: [ok, پیام، کد]
     */
    public static function saveProject(int $projectId, int $actor, array $in, array $moves = [], bool $reset = false): array
    {
        $old = self::project($projectId);
        $new = $reset ? self::template() : self::normalize(self::withNewKeys($in));
        $removed = array_values(array_diff(array_column($old['columns'], 'key'), array_column($new['columns'], 'key')));
        $counts = self::counts($projectId);
        $plan = [];
        foreach ($removed as $k) {
            if (($counts[$k] ?? 0) === 0) {
                continue;
            }
            $to = (string) ($moves[$k] ?? ($reset ? $new['start'] : ''));
            if (!in_array($to, array_column($new['columns'], 'key'), true) || $to === 'done') {
                return [false, 'برای ' . fa_num($counts[$k]) . ' تسکِ ستون «' . self::label($projectId, $k) . '» ستون مقصد را انتخاب کنید.', 'BOARD_TARGET_REQUIRED'];
            }
            $plan[$k] = $to;
        }
        $moved = [];
        foreach ($plan as $from => $to) {
            foreach (DB::column('SELECT id FROM tasks WHERE project_id = ? AND board_status = ? AND archived_at IS NULL', [$projectId, $from]) as $tid) {
                $moved[] = [(int) $tid, $from, $to];
            }
        }
        $fromLabels = [];
        foreach ($plan as $from => $to) {
            $fromLabels[$from] = self::label($projectId, $from);
        }
        DB::transaction(static function () use ($projectId, $new, $plan, $reset): void {
            foreach ($plan as $from => $to) {
                DB::run('UPDATE tasks SET board_status = ?, board_moved_at = NOW() WHERE project_id = ? AND board_status = ?', [$to, $projectId, $from]);
            }
            DB::run('UPDATE projects SET board_config = ? WHERE id = ?', [$reset ? null : json_encode($new, JSON_UNESCAPED_UNICODE), $projectId]);
        });
        self::flush($projectId);
        // تاریخچه‌ی هر تسکِ جابه‌جاشده (ستونش حذف شد)
        foreach ($moved as [$tid, $from, $to]) {
            TaskHistory::log($tid, $actor, 'board', ['from' => $from, 'to' => $to, 'reason' => 'ستون «' . $fromLabels[$from] . '» از بورد حذف شد']);
        }
        self::flush($projectId);
        return [true, $reset ? 'بورد پروژه به چیدمان پیش‌فرض برگشت.' : 'بورد پروژه ذخیره شد.', ''];
    }

    /** ذخیره‌ی بورد پیش‌فرض (مدیر سیستم). تسک‌های پروژه‌های پیش‌فرض در ستون‌های حذف‌شده به ستون شروع جدید می‌روند. */
    public static function saveTemplate(int $adminId, array $in, bool $reset = false): array
    {
        $old = self::template();
        $new = $reset ? self::normalize(self::BUILTIN) : self::normalize(self::withNewKeys($in));
        $removed = array_values(array_diff(array_column($old['columns'], 'key'), array_column($new['columns'], 'key')));
        DB::transaction(static function () use ($adminId, $new, $removed, $reset): void {
            if ($removed) {
                DB::run('UPDATE tasks t JOIN projects p ON p.id = t.project_id SET t.board_status = ?, t.board_moved_at = NOW()
                         WHERE p.board_config IS NULL AND t.board_status IN (' . DB::in($removed) . ')', array_merge([$new['start']], $removed));
            }
            if ($reset) {
                DB::run('DELETE FROM content_texts WHERE `key` = ?', [self::CONTENT_KEY]);
            } else {
                DB::run('INSERT INTO content_texts (`key`, body, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body), updated_by = VALUES(updated_by)',
                    [self::CONTENT_KEY, json_encode($new, JSON_UNESCAPED_UNICODE), $adminId]);
            }
        });
        self::flush();
        return [true, $reset ? 'بورد پیش‌فرض به چیدمان اولیه برگشت.' : 'بورد پیش‌فرض ذخیره شد؛ پروژه‌هایی که بوردشان را تغییر نداده‌اند همین را می‌بینند.', ''];
    }

    /** ستون‌های تازه‌ی فرم (کلید new… یا خالی) کلید c_… می‌گیرند؛ start هم اگر به ستون تازه اشاره کند جابه‌جا می‌شود */
    private static function withNewKeys(array $in): array
    {
        $cols = is_array($in['columns'] ?? null) ? $in['columns'] : [];
        $taken = [];
        foreach ($cols as $c) {
            if (is_array($c) && preg_match('/^[a-z][a-z0-9_]{1,23}$/', (string) ($c['key'] ?? '')) && !str_starts_with((string) $c['key'], 'new')) {
                $taken[] = (string) $c['key'];
            }
        }
        $map = [];
        foreach ($cols as $i => $c) {
            if (!is_array($c)) {
                continue;
            }
            $k = (string) ($c['key'] ?? '');
            if ($k === '' || str_starts_with($k, 'new') || !preg_match('/^[a-z][a-z0-9_]{1,23}$/', $k)) {
                $nk = self::newKey($taken);
                $taken[] = $nk;
                $map[$k] = $nk;
                $cols[$i]['key'] = $nk;
            }
        }
        $start = (string) ($in['start'] ?? '');
        return ['columns' => $cols, 'start' => $map[$start] ?? $start];
    }

    /** داده‌ی ویرایشگر (تنظیمات بورد پروژه / محتوا ← بورد) */
    public static function editorData(array $cfg, array $counts = []): array
    {
        return [
            'columns' => array_map(static fn($c) => $c + ['fixed' => in_array($c['key'], self::FIXED, true), 'role' => self::FIXED_ROLE[$c['key']] ?? '', 'count' => $counts[$c['key']] ?? 0], $cfg['columns']),
            'start' => $cfg['start'], 'icons' => self::ICONS, 'tones' => self::TONES, 'max' => self::MAX_COLUMNS, 'max_label' => self::MAX_LABEL,
        ];
    }
}
