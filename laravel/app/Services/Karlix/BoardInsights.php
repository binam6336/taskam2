<?php
declare(strict_types=1);

namespace KarlixLaravel\Services\Karlix;

use KarlixLaravel\Services\ReviewService;
use KarlixLaravel\Services\TaskAnalytics;
use KarlixLaravel\Services\WorkloadService;

/**
 * تحلیل‌های ویژه‌ی دستیار بورد (v7.22): رتبه‌بندی اعضا (بار کاری، عقب‌افتادگی، سرعت، کیفیت)، گزارش یک نفر، تفکیک دلخواه،
 * ریسک تسک‌ها و صف بررسی. مثل BoardTools همه از «دنیای تسک‌های» همین کاربر (BoardContext::universe) حساب می‌شوند.
 *
 * علاوه بر data/blocks/summary، هر ابزار «answer» دارد: چند خط فارسی آماده که سرور از عددهای واقعی می‌سازد
 * (با توکن [[user:U2]] و [[task:T3]]). وقتی مدل نتیجه را نمی‌بیند (done:true) همین خط‌ها پاسخ مستقیم سؤال‌اند،
 * مثلاً «بیشترین بار کاری روی دوش کیست؟» ⇐ «بیشترین بار کاری: [[user:U2]] — …».
 */
final class BoardInsights
{
    /** وزن اولویت در امتیاز بار کاری */
    private const WEIGHT = ['high' => 3, 'medium' => 2, 'low' => 1];
    private const LATE_FACTOR = 1.5;
    /** حداقل نمونه برای نرخ و میانه */
    private const MIN_SAMPLE = 3;

    public const METRICS = [
        'load' => 'بار کاری', 'open' => 'تسک باز', 'overdue' => 'عقب‌افتاده', 'due_soon' => 'سررسید ۷ روز آینده',
        'review' => 'منتظر بررسی', 'rework' => 'برگشتی برای اصلاح', 'done' => 'انجام‌شده', 'late_rate' => 'نرخ تأخیر',
        'on_time' => 'نرخ انجام به‌موقع', 'speed' => 'سرعت انجام (میانه‌ی روز)', 'rework_rate' => 'نرخ برگشت',
    ];

    private static function out(array $data, array $blocks, string $summary, array $answer = []): array
    {
        return ['data' => $data + ($answer ? ['answer' => $answer] : []), 'blocks' => $blocks, 'summary' => $summary];
    }

    private static function user(BoardContext $c, ?int $uid): array
    {
        return $uid ? ['t' => 'user', 'id' => pid('users', $uid), 'v' => (string) $c->name($uid)] : ['t' => 's', 'v' => 'بدون مسئول'];
    }

    private static function tok(BoardContext $c, ?int $uid): string
    {
        return $uid ? '[[user:' . $c->ref('u', $uid) . ']]' : 'تسک‌های بدون مسئول';
    }

    private static function taskSeg(array $t): array
    {
        return ['t' => 'task', 'id' => pid('tasks', (int) $t['id']), 'tok' => $t['share_token'], 'v' => (string) $t['title']];
    }

    private static function pct(?float $v): string
    {
        return $v === null ? '—' : fa_num((int) round($v * 100)) . '٪';
    }

    private static function days($a): int
    {
        return is_numeric($a) ? max(7, min(365, (int) $a)) : 30;
    }

    /** آمار هر عضو (+ ردیف ۰ = بدون مسئول) */
    public static function memberStats(BoardContext $c, int $days = 30): array
    {
        $today = Common::today()->format('Y-m-d');
        $in7 = Common::today()->modify('+7 day')->format('Y-m-d');
        $since = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $blank = ['open' => 0, 'overdue' => 0, 'due_soon' => 0, 'review' => 0, 'rework' => 0, 'load' => 0.0, 'done' => 0, 'late' => 0, 'reworked' => 0, 'dur' => [], 'high' => 0];
        $rows = [];
        foreach (array_keys($c->members()) as $m) {
            $rows[$m] = $blank;
        }
        $rows[0] = $blank;
        foreach ($c->universe() as $t) {
            $k = $t['assignee_id'] !== null ? (int) $t['assignee_id'] : 0;
            $rows[$k] ??= $blank;
            $r = &$rows[$k];
            if (!$t['done']) {
                $r['open']++;
                $r['overdue'] += $t['overdue'] ? 1 : 0;
                $r['due_soon'] += $t['due_date'] !== null && $t['due_date'] >= $today && $t['due_date'] <= $in7 ? 1 : 0;
                $r['review'] += $t['status'] === 'review' ? 1 : 0;
                $r['rework'] += $t['status'] === 'rework' ? 1 : 0;
                $r['high'] += $t['priority'] === 'high' ? 1 : 0;
                if ($t['status'] !== 'review') {        // تسک منتظر بررسی دیگر کار مسئول نیست
                    $r['load'] += (self::WEIGHT[$t['priority']] ?? 2) * ($t['overdue'] ? self::LATE_FACTOR : 1);
                }
            } elseif (($fin = TaskAnalytics::finishedAt($t)) !== null && $fin >= $since) {
                $r['done']++;
                $r['late'] += TaskAnalytics::isLate($t, $fin) ? 1 : 0;
                $r['reworked'] += (int) $t['rework_count'] > 0 ? 1 : 0;
                $r['dur'][] = max(0, (strtotime($fin) - strtotime((string) $t['created_at'])) / 86400);
            }
            unset($r);
        }
        if ($rows[0]['open'] === 0 && $rows[0]['done'] === 0) {
            unset($rows[0]);
        }
        foreach ($rows as &$r) {
            $n = $r['done'];
            $r['late_rate'] = $n >= self::MIN_SAMPLE ? $r['late'] / $n : null;
            $r['on_time'] = $n >= self::MIN_SAMPLE ? 1 - $r['late'] / $n : null;
            $r['rework_rate'] = $n >= self::MIN_SAMPLE ? $r['reworked'] / $n : null;
            $med = count($r['dur']) >= self::MIN_SAMPLE ? TaskAnalytics::median($r['dur']) : null;
            $r['speed'] = $med !== null ? round($med, 1) : null;
            $r['load'] = round($r['load'], 1);
            unset($r['dur']);
        }
        unset($r);
        // درصد ظرفیت (همان صفحه‌ی «بار کاری») فقط برای کسی که دسترسی تحلیل پروژه دارد
        if (WorkloadService::canView($c->projectId, $c->uid)) {
            try {
                foreach (WorkloadService::computeLoads(WorkloadService::build($c->projectId, $c->uid)) as $l) {
                    $id = \App\Core\Pid::id('users', (string) $l['member']['id']);
                    if ($id !== null && isset($rows[$id])) {
                        $rows[$id]['capacity_pct'] = (int) $l['pct'];
                    }
                }
            } catch (\Throwable $e) {
                // صفحه‌ی بار کاری در دسترس نیست؛ امتیاز وزنی کافی است
            }
        }
        return $rows;
    }

    // ───────────────────────── rank_members ─────────────────────────

    /** {metric, days?, order?: desc|asc} — جدول همه‌ی اعضا مرتب بر اساس معیار + جواب مستقیم (بیشترین / کمترین) */
    public static function rank(BoardContext $c, array $a): array
    {
        $metric = is_string($a['metric'] ?? null) && isset(self::METRICS[$a['metric']]) ? $a['metric'] : 'load';
        $days = self::days($a['days'] ?? null);
        $rows = self::memberStats($c, $days);
        $asc = ($a['order'] ?? '') === 'asc';
        // معیارهای «کمتر بهتر» (سرعت = روز کمتر) در «بهترین» صعودی‌اند؛ پیش‌فرض همیشه «بیشترین مقدار» اول است
        $val = static fn($r) => $metric === 'load' ? ($r['capacity_pct'] ?? null) ?? $r['load'] : $r[$metric];
        $keys = array_keys($rows);
        $withVal = array_values(array_filter($keys, static fn($k) => $val($rows[$k]) !== null));
        // سرعت = روز کمتر بهتر: پیش‌فرض «سریع‌ترین اول»؛ بقیه پیش‌فرض «بیشترین اول»
        $lowFirst = ($metric === 'speed') !== $asc;
        $withVal = array_values(array_filter($withVal, static fn($k) => $k !== 0));
        usort($withVal, static fn($x, $y) => $lowFirst ? [$val($rows[$x]), $rows[$x]['open']] <=> [$val($rows[$y]), $rows[$y]['open']] : [$val($rows[$y]), $rows[$y]['open']] <=> [$val($rows[$x]), $rows[$x]['open']]);
        $order = array_merge($withVal, array_values(array_diff($keys, $withVal, [0])), isset($rows[0]) ? [0] : []);
        $hasCap = (bool) array_filter($rows, static fn($r) => isset($r['capacity_pct']));
        $fmt = static function ($r, $m) use ($days) {
            $v = $r[$m] ?? null;
            if (in_array($m, ['late_rate', 'on_time', 'rework_rate'], true)) {
                return self::pct($v);
            }
            if ($m === 'speed') {
                return $v === null ? '—' : fa_num($v) . ' روز';
            }
            return $v === null ? '—' : fa_num($v);
        };
        $cols = ['مسئول', 'امتیاز بار', 'باز', 'عقب‌افتاده', 'سررسید ۷ روز', 'منتظر بررسی', 'انجام (' . fa_num($days) . ' روز)', 'به‌موقع', 'میانه‌ی زمان'];
        if ($hasCap) {
            array_splice($cols, 2, 0, ['ظرفیت']);
        }
        $table = [];
        $data = [];
        foreach ($order as $k) {
            $r = $rows[$k];
            $line = [self::user($c, $k ?: null), $fmt($r, 'load')];
            if ($hasCap) {
                $line[] = isset($r['capacity_pct']) ? fa_num($r['capacity_pct']) . '٪' : '—';
            }
            array_push($line, $fmt($r, 'open'), $fmt($r, 'overdue'), $fmt($r, 'due_soon'), $fmt($r, 'review'), $fmt($r, 'done'), $fmt($r, 'on_time'), $fmt($r, 'speed'));
            $table[] = $line;
            $data[] = ['member' => $k ? $c->ref('u', $k) . ' ' . $c->name($k) : 'unassigned'] + array_intersect_key($r, array_flip(['open', 'overdue', 'due_soon', 'review', 'rework', 'load', 'capacity_pct', 'done', 'late', 'late_rate', 'on_time', 'speed', 'rework_rate', 'high']));
        }
        // جواب مستقیم
        $answer = [];
        $people = $withVal;
        $label = self::METRICS[$metric];
        if (!$people) {
            $answer[] = in_array($metric, ['late_rate', 'on_time', 'speed', 'rework_rate'], true)
                ? 'برای «' . $label . '» نمونه‌ی کافی نیست (هر نفر حداقل ' . fa_num(self::MIN_SAMPLE) . ' تسک انجام‌شده در ' . fa_num($days) . ' روز اخیر).'
                : 'عضوی با داده‌ی کافی برای «' . $label . '» پیدا نشد.';
        } else {
            $top = $people[0];
            $r = $rows[$top];
            $detail = static function ($r) use ($fmt) {
                $p = fa_num($r['open']) . ' تسک باز';
                $p .= $r['overdue'] ? '، ' . fa_num($r['overdue']) . ' عقب‌افتاده' : '';
                $p .= $r['due_soon'] ? '، ' . fa_num($r['due_soon']) . ' سررسید تا ۷ روز' : '';
                $p .= $r['high'] ? '، ' . fa_num($r['high']) . ' با اولویت زیاد' : '';
                return $p . (isset($r['capacity_pct']) ? ' — ' . fa_num($r['capacity_pct']) . '٪ ظرفیت' : '');
            };
            if ($metric === 'load') {
                if ($rows[$top]['load'] <= 0 && !isset($r['capacity_pct'])) {
                    $answer[] = 'هیچ‌کدام از اعضا کار باز فعالی ندارد.';
                } else {
                    $answer[] = ($asc ? 'کمترین' : 'بیشترین') . ' بار کاری: **' . self::tok($c, $top) . '** — ' . $detail($r) . '.';
                    if (count($people) > 1) {
                        $o = $people[count($people) - 1];
                        $answer[] = ($asc ? 'بیشترین' : 'کمترین') . ' بار: ' . self::tok($c, $o) . ' — ' . $detail($rows[$o]) . '.';
                    }
                    if (isset($people[1]) && !$asc) {
                        $answer[] = 'نفر دوم: ' . self::tok($c, $people[1]) . ' — ' . $detail($rows[$people[1]]) . '.';
                    }
                }
            } else {
                $best = $metric === 'speed' ? ($asc ? 'کندترین' : 'سریع‌ترین') : ($asc ? 'کمترین' : 'بیشترین');
                $allZero = $metric !== 'speed' && !array_filter($people, static fn($k) => (float) $val($rows[$k]) > 0);
            }
            if ($metric !== 'load' && $allZero) {
                $answer[] = 'مقدار «' . $label . '» برای همه‌ی اعضای دارای داده صفر است' . (in_array($metric, ['done', 'late_rate', 'on_time', 'rework_rate'], true) ? ' (' . fa_num($days) . ' روز اخیر)' : '') . '.';
            } elseif ($metric !== 'load') {
                $answer[] = $best . ' «' . $label . '»: **' . self::tok($c, $top) . '** با ' . $fmt($rows[$top], $metric) . ($metric === 'done' || in_array($metric, ['late_rate', 'on_time', 'speed', 'rework_rate'], true) ? ' (' . fa_num($days) . ' روز اخیر)' : '') . '.';
                $tie = array_values(array_filter($people, static fn($k) => $k !== $top && $val($rows[$k]) === $val($rows[$top])));
                if ($tie) {
                    $answer[] = 'هم‌رتبه: ' . implode('، ', array_map(static fn($k) => self::tok($c, $k), array_slice($tie, 0, 3))) . '.';
                }
            }
            if (isset($rows[0]) && $rows[0]['open'] > 0 && in_array($metric, ['load', 'open', 'overdue'], true)) {
                $answer[] = fa_num($rows[0]['open']) . ' تسک باز هم مسئول ندارد.';
            }
        }
        $blocks = [['type' => 'table', 'title' => 'رتبه‌بندی اعضا بر اساس «' . $label . '»', 'cols' => $cols, 'rows' => $table,
            'note' => 'امتیاز بار = جمع وزن اولویت تسک‌های باز (زیاد ۳، متوسط ۲، کم ۱؛ عقب‌افتاده ×۱٫۵؛ «منتظر بررسی» حساب نمی‌شود)'
                . ($hasCap ? '؛ ظرفیت = همان درصد صفحه‌ی «بار کاری»' : '') . '. نرخ‌ها و میانه با حداقل ' . fa_num(self::MIN_SAMPLE) . ' تسک انجام‌شده.']];
        return self::out(['metric' => $metric, 'days' => $days, 'ranking' => $data], $blocks, 'rank_members ' . $metric . ' (' . count($data) . ' نفر)', $answer);
    }

    /** workload_by_assignee قدیمی = رتبه‌بندی بار کاری */
    public static function workload(BoardContext $c, array $a): array
    {
        return self::rank($c, ['metric' => 'load'] + $a);
    }

    // ───────────────────────── member_report ─────────────────────────

    /** {assignee: "U2"|"me", days?} — کارنامه‌ی یک نفر + لیست تسک‌های باز او */
    public static function member(BoardContext $c, array $a): array
    {
        $ref = $a['assignee'] ?? 'me';
        $uid = $ref === 'me' ? $c->uid : $c->idOf('u', $ref);
        if ($uid === null || !isset($c->members()[$uid])) {
            return self::out(['error' => 'assignee must be a U ref from MEMBERS or "me"'], [], 'member_report: عضو نامعتبر');
        }
        $days = self::days($a['days'] ?? null);
        $all = self::memberStats($c, $days);
        $r = $all[$uid];
        $open = array_values(array_filter($c->universe(), static fn($t) => !$t['done'] && (int) $t['assignee_id'] === $uid));
        usort($open, static fn($x, $y) => [$y['overdue'] ? 1 : 0, $x['due_date'] === null ? 1 : 0, $x['due_date'] ?? ''] <=> [$x['overdue'] ? 1 : 0, $y['due_date'] === null ? 1 : 0, $y['due_date'] ?? '']);
        $name = (string) $c->name($uid);
        [$set, $block] = BoardTools::makeSet($c, 'تسک‌های باز ' . $name, array_map(static fn($t) => (int) $t['id'], $open));
        // رتبه‌ی بار کاری بین اعضا
        $people = array_filter($all, static fn($x, $k) => $k !== 0, ARRAY_FILTER_USE_BOTH);
        uasort($people, static fn($x, $y) => ($y['capacity_pct'] ?? $y['load']) <=> ($x['capacity_pct'] ?? $x['load']));
        $rank = array_search($uid, array_keys($people), true);
        $kpis = [
            ['label' => 'باز', 'value' => fa_num($r['open'])],
            ['label' => 'عقب‌افتاده', 'value' => fa_num($r['overdue']), 'tone' => $r['overdue'] ? 'danger' : ''],
            ['label' => 'سررسید ۷ روز', 'value' => fa_num($r['due_soon'])],
            ['label' => 'منتظر بررسی', 'value' => fa_num($r['review'])],
            ['label' => 'انجام در ' . fa_num($days) . ' روز', 'value' => fa_num($r['done'])],
            ['label' => 'انجام به‌موقع', 'value' => self::pct($r['on_time']), 'tone' => $r['on_time'] !== null && $r['on_time'] < 0.7 ? 'warn' : ''],
            ['label' => 'میانه‌ی زمان انجام', 'value' => $r['speed'] !== null ? fa_num($r['speed']) . ' روز' : '—'],
            ['label' => 'برگشت برای اصلاح', 'value' => self::pct($r['rework_rate'])],
        ];
        if (isset($r['capacity_pct'])) {
            array_unshift($kpis, ['label' => 'بار نسبت به ظرفیت', 'value' => fa_num($r['capacity_pct']) . '٪', 'tone' => $r['capacity_pct'] > 100 ? 'danger' : ($r['capacity_pct'] >= 70 ? 'warn' : '')]);
        }
        $tok = '[[user:' . $c->ref('u', $uid) . ']]';
        $answer = [$tok . ': ' . fa_num($r['open']) . ' تسک باز' . ($r['overdue'] ? '، ' . fa_num($r['overdue']) . ' عقب‌افتاده' : '، بدون عقب‌افتادگی')
            . ($r['review'] ? '، ' . fa_num($r['review']) . ' منتظر بررسی' : '') . '؛ ' . fa_num($r['done']) . ' تسک در ' . fa_num($days) . ' روز اخیر انجام داده'
            . ($r['on_time'] !== null ? ' (' . self::pct($r['on_time']) . ' به‌موقع)' : '') . '.'];
        if ($rank !== false && count($people) > 1) {
            $answer[] = 'رتبه‌ی بار کاری بین ' . fa_num(count($people)) . ' عضو: ' . fa_num($rank + 1) . '.';
        }
        foreach (array_slice(array_filter($open, static fn($t) => $t['overdue']), 0, 3) as $t) {
            $answer[] = '- عقب‌افتاده: [[task:' . $c->ref('t', (int) $t['id']) . ']]';
        }
        $data = ['member' => $c->ref('u', $uid) . ' ' . $name, 'days' => $days, 'open_set' => 'R' . $set['seq']]
            + array_intersect_key($r, array_flip(['open', 'overdue', 'due_soon', 'review', 'rework', 'load', 'capacity_pct', 'done', 'late', 'on_time', 'speed', 'rework_rate']))
            + ['load_rank' => $rank !== false ? $rank + 1 : null, 'members' => count($people), 'open_first' => array_map([$c, 'brief'], array_slice($open, 0, 10))];
        return self::out($data, [['type' => 'kpis', 'items' => $kpis], $block], 'member_report ' . $c->ref('u', $uid) . ' → R' . $set['seq'], $answer);
    }

    // ───────────────────────── breakdown ─────────────────────────

    /** {by: label|priority|column|assignee|due, scope?: open|all} — توزیع تسک‌ها */
    public static function breakdown(BoardContext $c, array $a): array
    {
        $by = in_array($a['by'] ?? '', ['label', 'priority', 'column', 'assignee', 'due'], true) ? $a['by'] : 'label';
        $all = ($a['scope'] ?? 'open') === 'all';
        $today = Common::today()->format('Y-m-d');
        $in7 = Common::today()->modify('+7 day')->format('Y-m-d');
        $cols = $c->columns();
        $groups = [];
        $total = 0;
        foreach ($c->universe() as $t) {
            if ($t['status'] === 'archived' || (!$all && $t['done'])) {
                continue;
            }
            switch ($by) {
                case 'priority':
                    $k = (string) $t['priority'];
                    break;
                case 'column':
                    $k = (string) $t['status'];
                    break;
                case 'assignee':
                    $k = (string) (int) $t['assignee_id'];
                    break;
                case 'due':
                    $k = $t['done'] ? 'done' : ($t['due_date'] === null ? 'none' : ($t['overdue'] ? 'overdue' : ($t['due_date'] === $today ? 'today' : ($t['due_date'] <= $in7 ? 'week' : 'later'))));
                    break;
                default:
                    $k = (string) (int) $t['subject_id'];
            }
            $groups[$k] ??= ['n' => 0, 'overdue' => 0, 'done' => 0, 'high' => 0];
            $groups[$k]['n']++;
            $groups[$k]['overdue'] += $t['overdue'] ? 1 : 0;
            $groups[$k]['done'] += $t['done'] ? 1 : 0;
            $groups[$k]['high'] += !$t['done'] && $t['priority'] === 'high' ? 1 : 0;
            $total++;
        }
        $prio = ['high' => 'زیاد', 'medium' => 'متوسط', 'low' => 'کم'];
        $dueN = ['overdue' => 'عقب‌افتاده', 'today' => 'امروز', 'week' => '۷ روز آینده', 'later' => 'بعدتر', 'none' => 'بدون سررسید', 'done' => 'انجام‌شده'];
        $name = static function (string $k) use ($by, $c, $cols, $prio, $dueN) {
            switch ($by) {
                case 'priority':
                    return ['t' => 's', 'v' => $prio[$k] ?? $k];
                case 'column':
                    return ['t' => 'column', 'id' => $k, 'v' => $cols[$k] ?? $k];
                case 'assignee':
                    return self::user($c, (int) $k ?: null);
                case 'due':
                    return ['t' => 's', 'v' => $dueN[$k] ?? $k];
                default:
                    return (int) $k ? ['t' => 'label', 'id' => pid('subjects', (int) $k), 'v' => $c->labels()[(int) $k][0] ?? ''] : ['t' => 's', 'v' => 'بدون لیبل'];
            }
        };
        $tokOf = static function (string $k) use ($by, $c, $cols, $prio, $dueN) {
            switch ($by) {
                case 'column':
                    return '[[column:' . $k . ']]';
                case 'assignee':
                    return self::tok($c, (int) $k ?: null);
                case 'label':
                    return (int) $k ? '[[label:' . $c->ref('l', (int) $k) . ']]' : 'بدون لیبل';
                case 'priority':
                    return 'اولویت ' . ($prio[$k] ?? $k);
                default:
                    return $dueN[$k] ?? $k;
            }
        };
        if ($by === 'column') {
            $ordered = [];
            foreach (array_keys($cols) as $k) {
                if (isset($groups[$k])) {
                    $ordered[$k] = $groups[$k];
                }
            }
            $groups = $ordered + $groups;
        } elseif ($by === 'priority') {
            $groups = array_replace(array_intersect_key(['high' => 0, 'medium' => 0, 'low' => 0], $groups), $groups);
        } else {
            uasort($groups, static fn($x, $y) => $y['n'] <=> $x['n']);
        }
        $shares = array_combine(array_map('strval', array_keys($groups)), TaskAnalytics::percents(array_values(array_map(static fn($g) => $g['n'], $groups))));
        $rows = [];
        $data = [];
        foreach ($groups as $k => $g) {
            $k = (string) $k;
            $rows[] = [$name($k), fa_num($g['n']), fa_num($shares[$k] ?? 0) . '٪', fa_num($g['overdue']), fa_num($g['high'])];
            $data[] = ['group' => $tokOf($k), 'count' => $g['n'], 'share_percent' => $shares[$k] ?? 0, 'overdue' => $g['overdue'], 'high_priority_open' => $g['high']];
        }
        $byN = ['label' => 'لیبل', 'priority' => 'اولویت', 'column' => 'ستون', 'assignee' => 'مسئول', 'due' => 'سررسید'];
        $answer = [];
        if ($groups) {
            $big = $groups;
            uasort($big, static fn($x, $y) => $y['n'] <=> $x['n']);
            $k = (string) array_key_first($big);
            $answer[] = 'بیشترین سهم (' . $byN[$by] . '): ' . $tokOf($k) . ' با ' . fa_num($big[$k]['n']) . ' تسک از ' . fa_num($total) . '.';
            uasort($big, static fn($x, $y) => $y['overdue'] <=> $x['overdue']);
            $k = (string) array_key_first($big);
            if ($big[$k]['overdue'] > 0 && $by !== 'due') {
                $answer[] = 'بیشترین عقب‌افتادگی: ' . $tokOf($k) . ' (' . fa_num($big[$k]['overdue']) . ' تسک).';
            }
        } else {
            $answer[] = 'تسکی برای تفکیک نیست.';
        }
        $blocks = [['type' => 'table', 'title' => 'تفکیک ' . ($all ? 'همه‌ی تسک‌ها' : 'تسک‌های باز') . ' بر اساس ' . $byN[$by],
            'cols' => [$byN[$by], 'تعداد', 'سهم', 'عقب‌افتاده', 'اولویت زیاد'], 'rows' => $rows]];
        return self::out(['by' => $by, 'scope' => $all ? 'all' : 'open', 'total' => $total, 'groups' => $data], $blocks, 'breakdown ' . $by . ' (' . count($data) . ')', $answer);
    }

    // ───────────────────────── risk_report ─────────────────────────

    /** امتیاز ریسک یک تسک باز با دلیل‌ها (قاعده‌ی ثابت سرور) */
    public static function riskOf(array $t): array
    {
        $today = Common::today();
        $s = 0;
        $why = [];
        if ($t['overdue']) {
            $late = (int) $today->diff(new \DateTimeImmutable($t['due_date'], new \DateTimeZone('Asia/Tehran')))->format('%a');
            $s += 40 + min(20, $late * 2);
            $why[] = fa_num($late) . ' روز عقب‌افتاده';
        } elseif ($t['due_date'] !== null && $t['status'] !== 'review') {
            $left = (int) $today->diff(new \DateTimeImmutable($t['due_date'], new \DateTimeZone('Asia/Tehran')))->format('%r%a');
            if ($left <= 2 && $t['progress'] < 50) {
                $s += 25;
                $why[] = ($left <= 0 ? 'سررسید امروز' : 'سررسید ' . fa_num($left) . ' روز دیگر') . ' و پیشرفت ' . fa_num($t['progress']) . '٪';
            }
        }
        if ($t['priority'] === 'high') {
            $s += 10;
            $why[] = 'اولویت زیاد';
        }
        if (substr((string) $t['last_activity'], 0, 10) < $today->modify('-7 day')->format('Y-m-d')) {
            $s += 15;
            $why[] = 'بیش از یک هفته بدون فعالیت';
        }
        if ($t['assignee_id'] === null) {
            $s += 15;
            $why[] = 'بدون مسئول';
        }
        if ((int) $t['rework_count'] >= 2) {
            $s += 10;
            $why[] = fa_num((int) $t['rework_count']) . ' بار برگشت برای اصلاح';
        } elseif ($t['status'] === 'rework') {
            $s += 8;
            $why[] = 'برگشتی برای اصلاح';
        }
        if ($t['due_date'] === null && $t['priority'] === 'high') {
            $s += 5;
            $why[] = 'بدون سررسید';
        }
        return [$s, $why];
    }

    /** {limit?} — تسک‌های در معرض ریسک (لیست R# + دلیل‌ها) */
    public static function risk(BoardContext $c, array $a): array
    {
        $scored = [];
        foreach ($c->universe() as $t) {
            if ($t['done']) {
                continue;
            }
            [$s, $why] = self::riskOf($t);
            if ($s >= 25) {
                $scored[] = [$s, $why, $t];
            }
        }
        usort($scored, static fn($x, $y) => $y[0] <=> $x[0]);
        $limit = isset($a['limit']) && is_numeric($a['limit']) ? max(5, min(100, (int) $a['limit'])) : 40;
        $pick = array_slice($scored, 0, $limit);
        $level = static fn(int $s) => $s >= 60 ? 'بالا' : ($s >= 40 ? 'متوسط' : 'کم');
        [$set, $block] = BoardTools::makeSet($c, 'تسک‌های در معرض ریسک', array_map(static fn($x) => (int) $x[2]['id'], $pick), ['risk' => true]);
        $rows = array_map(static fn($x) => [self::taskSeg($x[2]), $level($x[0]), implode('، ', $x[1])], array_slice($pick, 0, 10));
        $answer = [];
        if (!$pick) {
            $answer[] = 'تسکی با ریسک قابل توجه پیدا نشد.';
        } else {
            $high = count(array_filter($pick, static fn($x) => $x[0] >= 60));
            $answer[] = fa_num(count($scored)) . ' تسک در معرض ریسک است' . ($high ? '؛ ' . fa_num($high) . ' مورد ریسک بالا' : '') . '.';
            foreach (array_slice($pick, 0, 3) as $x) {
                $answer[] = '- [[task:' . $c->ref('t', (int) $x[2]['id']) . ']] — ' . implode('، ', $x[1]);
            }
        }
        $blocks = [['type' => 'table', 'title' => 'دلیل ریسک (۱۰ مورد اول)', 'cols' => ['تسک', 'ریسک', 'دلیل'], 'rows' => $rows,
            'note' => 'قاعده‌ی خودکار: عقب‌افتادگی، سررسید نزدیک با پیشرفت کم، اولویت زیاد، رکود یک‌هفته‌ای، بدون مسئول و برگشت‌های مکرر.'], $block];
        $data = ['result_set' => 'R' . $set['seq'], 'total' => count($scored),
            'top' => array_map(static fn($x) => $c->brief($x[2]) + ['risk_score' => $x[0], 'reasons' => $x[1]], array_slice($pick, 0, 12))];
        return self::out($data, $blocks, 'risk_report → R' . $set['seq'] . ' (' . count($scored) . ')', $answer);
    }

    // ───────────────────────── review_report ─────────────────────────

    /** صف «بررسی سرپرست»: انتظار، تأییدکننده‌ها و نرخ برگشت ۳۰ روز اخیر */
    public static function review(BoardContext $c, array $a): array
    {
        $sla = ReviewService::slaHours();
        $now = time();
        $waiting = [];
        $since = date('Y-m-d H:i:s', strtotime('-30 days'));
        $approvers = [];
        $finished = $reworked = 0;
        foreach ($c->universe() as $t) {
            if ($t['status'] === 'review') {
                $h = !empty($t['delivered_at']) ? max(0, ($now - strtotime((string) $t['delivered_at'])) / 3600) : null;
                $waiting[] = [$h, $t];
            }
            if ($t['done'] && ($fin = TaskAnalytics::finishedAt($t)) !== null && $fin >= $since) {
                $finished++;
                $reworked += (int) $t['rework_count'] > 0 ? 1 : 0;
                if (!empty($t['approved_by'])) {
                    $approvers[(int) $t['approved_by']] = ($approvers[(int) $t['approved_by']] ?? 0) + 1;
                }
            }
        }
        usort($waiting, static fn($x, $y) => ($y[0] ?? 0) <=> ($x[0] ?? 0));
        $hours = array_values(array_filter(array_column($waiting, 0), static fn($h) => $h !== null));
        $over = count(array_filter($hours, static fn($h) => $h > $sla));
        $wait = static fn(?float $h) => $h === null ? '—' : ($h >= 48 ? fa_num((int) round($h / 24)) . ' روز' : fa_num((int) round($h)) . ' ساعت');
        $med = TaskAnalytics::median($hours);
        arsort($approvers);
        $blocks = [
            ['type' => 'kpis', 'items' => [
                ['label' => 'منتظر بررسی', 'value' => fa_num(count($waiting))],
                ['label' => 'بیش از ' . fa_num($sla) . ' ساعت', 'value' => fa_num($over), 'tone' => $over ? 'danger' : ''],
                ['label' => 'میانه‌ی انتظار', 'value' => $wait($med)],
                ['label' => 'نرخ برگشت (۳۰ روز)', 'value' => $finished >= self::MIN_SAMPLE ? self::pct($reworked / $finished) : '—'],
            ]],
            ['type' => 'table', 'title' => 'قدیمی‌ترین تسک‌های منتظر بررسی', 'cols' => ['تسک', 'مسئول', 'انتظار'],
                'rows' => array_map(static fn($x) => [self::taskSeg($x[1]), self::user($c, $x[1]['assignee_id'] !== null ? (int) $x[1]['assignee_id'] : null), $wait($x[0])], array_slice($waiting, 0, 10))],
        ];
        if ($approvers) {
            $blocks[] = ['type' => 'table', 'title' => 'تأییدها در ۳۰ روز اخیر', 'cols' => ['تأییدکننده', 'تعداد'],
                'rows' => array_map(static fn($k, $n) => [self::user($c, $k), fa_num($n)], array_keys($approvers), $approvers)];
        }
        $answer = [!$waiting ? 'هیچ تسکی منتظر بررسی نیست.' : fa_num(count($waiting)) . ' تسک منتظر بررسی است' . ($over ? '؛ ' . fa_num($over) . ' مورد بیش از ' . fa_num($sla) . ' ساعت معطل مانده' : '') . '.'];
        if ($waiting && $waiting[0][0] !== null) {
            $answer[] = 'قدیمی‌ترین: [[task:' . $c->ref('t', (int) $waiting[0][1]['id']) . ']] (' . $wait($waiting[0][0]) . ').';
        }
        $data = ['waiting' => count($waiting), 'over_sla' => $over, 'sla_hours' => $sla, 'median_wait_hours' => $med !== null ? round($med, 1) : null,
            'finished_30d' => $finished, 'reworked_30d' => $reworked,
            'oldest' => array_map(static fn($x) => $c->brief($x[1]) + ['wait_hours' => $x[0] !== null ? (int) round($x[0]) : null], array_slice($waiting, 0, 8)),
            'approvers_30d' => array_map(static fn($k, $n) => ['member' => $c->ref('u', $k) . ' ' . $c->name($k), 'approved' => $n], array_keys($approvers), $approvers)];
        return self::out($data, $blocks, 'review_report (' . count($waiting) . ')', $answer);
    }
}
