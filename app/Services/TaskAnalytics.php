<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Jalali;

/**
 * شاخص‌های عملکرد (KPI) تسک‌های پروژه.
 *
 * امتیاز سرعت هر تسک انجام‌شده (۰ تا ۱۰۰):
 *   ۶۰٪ زمان انجام  → ۲۴ ساعت یا کمتر = ۱۰۰، ۱۴ روز یا بیشتر = ۰ (بین این دو لگاریتمی)
 *   ۴۰٪ به‌موقع بودن → تا پایان سررسید (یا بدون سررسید) = ۱۰۰، دیرتر = ۰
 * امتیاز یک کاربر میانگین امتیاز تسک‌های انجام‌شده‌ی اوست.
 *
 * v4.1: زمان مسئول تا لحظه‌ی «تحویل برای بررسی» (delivered_at) حساب می‌شود، نه تأیید سرپرست؛ تسکِ منتظر تأیید برای مسئول
 * «تحویل‌شده» است (نه باز). زمان بررسی جدا به نام تأییدکننده (approved_by) در KPI «میانگین زمان تأیید» ثبت می‌شود.
 * تأخیر (KPI) روزانه است: فقط وقتی روز تحویل از روز سررسید گذشته باشد (ساعت سررسید اثر ندارد)؛ «عقب‌افتاده» = سررسید روزهای قبل.
 */
final class TaskAnalytics
{
    public const FAST_HOURS = 24;
    public const SLOW_HOURS = 336;

    /** بازه‌های نمودار مقایسه: [عنوان، ثانیه‌ی بازه یا null = از ابتدا] */
    public const RANGES = [
        '2d' => ['۲ روز اخیر', 2 * 86400],
        '1w' => ['یک هفته‌ی اخیر', 7 * 86400],
        '1m' => ['یک ماه اخیر', 30 * 86400],
        '6m' => ['شش ماه اخیر', 182 * 86400],
        '1y' => ['یک سال اخیر', 365 * 86400],
        'all' => ['از ابتدا', null],
    ];

    /** [حداقل امتیاز، عنوان، رنگ نشان] — از بهترین به بدترین */
    public const RATINGS = [
        [90, 'بسیار عالی', 'green'],
        [75, 'عالی', 'green'],
        [60, 'خوب', 'blue'],
        [45, 'متوسط', 'yellow'],
        [30, 'بد', 'red'],
        [0, 'خیلی بد', 'red'],
    ];

    public static function rating(?float $score): array
    {
        if ($score === null) {
            return ['label' => 'بدون داده', 'tone' => '', 'score' => null];
        }
        foreach (self::RATINGS as [$min, $label, $tone]) {
            if ($score >= $min) {
                return ['label' => $label, 'tone' => $tone, 'score' => (int) round($score)];
            }
        }
        return ['label' => 'خیلی بد', 'tone' => 'red', 'score' => (int) round($score)];
    }

    public static function dueTs(array $t): ?int
    {
        if (empty($t['due_date'])) {
            return null;
        }
        $ts = strtotime($t['due_date'] . ' ' . ($t['due_time'] ?: '23:59:59'));
        return $ts === false ? null : $ts;
    }

    /** لحظه‌ی پایان کار مسئول: تحویل برای بررسی، وگرنه تکمیل (تسک‌های قدیمی/بی‌پروژه)؛ null = هنوز تحویل نشده */
    public static function finishedAt(array $t): ?string
    {
        if (!empty($t['delivered_at']) && ((int) $t['is_completed'] === 1 || ($t['board_status'] ?? '') === 'review')) {
            return (string) $t['delivered_at'];
        }
        return (int) $t['is_completed'] === 1 && !empty($t['completed_at']) ? (string) $t['completed_at'] : null;
    }

    /** با تأخیر؟ فقط وقتی روزِ تحویل بعد از روزِ سررسید باشد (برای تسک باز: سررسید روزهای قبل) */
    public static function isLate(array $t, ?string $finished): bool
    {
        if (empty($t['due_date'])) {
            return false;
        }
        return $finished !== null ? substr($finished, 0, 10) > $t['due_date'] : $t['due_date'] < date('Y-m-d');
    }

    /** امتیاز سرعت یک تسک تحویل‌شده/انجام‌شده (یا null برای تسک باز). */
    public static function taskScore(array $t): ?float
    {
        $fin = self::finishedAt($t);
        if ($fin === null) {
            return null;
        }
        $hours = max(0.0, (strtotime($fin) - strtotime($t['created_at'])) / 3600);
        if ($hours <= self::FAST_HOURS) {
            $time = 100.0;
        } elseif ($hours >= self::SLOW_HOURS) {
            $time = 0.0;
        } else {
            $time = 100 * (1 - log($hours / self::FAST_HOURS) / log(self::SLOW_HOURS / self::FAST_HOURS));
        }
        return 0.6 * $time + 0.4 * (self::isLate($t, $fin) ? 0 : 100);
    }

    /** همه‌ی تسک‌های پروژه با فیلدهای لازم برای محاسبه. */
    private static function tasks(int $projectId): array
    {
        return DB::all('SELECT t.id, t.assignee_id, t.assignee_group_id, t.subject_id, t.priority, t.is_completed, t.board_status, t.created_at, t.completed_at, t.delivered_at, t.approved_by, t.due_date, t.due_time,
                s.title AS subject_title, s.color AS subject_color
            FROM tasks t LEFT JOIN subjects s ON s.id = t.subject_id WHERE t.project_id = ? AND t.blocked_at IS NULL', [$projectId]);
    }

    /** سازنده + اعضای پروژه. */
    public static function people(int $projectId): array
    {
        return DB::all('SELECT u.id, u.first_name, u.last_name, u.mobile, u.avatar FROM users u
            WHERE u.id = (SELECT user_id FROM projects WHERE id = ?) OR u.id IN (SELECT user_id FROM project_members WHERE project_id = ?)
            ORDER BY u.first_name, u.last_name', [$projectId, $projectId]);
    }

    /**
     * KPI هر عضو، خلاصه‌ی پروژه و توزیع لیبل‌ها.
     * @return array{members:array,summary:array,subjects:array}
     */
    public static function project(int $projectId): array
    {
        $now = time();
        $tasks = self::tasks($projectId);
        $members = [];
        foreach (self::people($projectId) as $u) {
            $members[(int) $u['id']] = $u + ['total' => 0, 'done' => 0, 'waiting' => 0, 'open' => 0, 'overdue' => 0, 'late' => 0, 'hours' => [], 'scores' => []];
        }
        // دپارتمان / گروه (v5.10): هر گروه یک ردیف KPI مثل یک عضو؛ تسک گروهی فقط به نام گروه حساب می‌شود (نه اعضا)
        try {
            foreach (DB::all('SELECT id, title FROM project_groups WHERE project_id = ? ORDER BY title', [$projectId]) as $g) {
                $members['g' . $g['id']] = ['id' => (int) $g['id'], 'is_group' => true, 'first_name' => 'گروه ' . $g['title'], 'last_name' => '', 'mobile' => null, 'avatar' => null,
                    'total' => 0, 'done' => 0, 'waiting' => 0, 'open' => 0, 'overdue' => 0, 'late' => 0, 'hours' => [], 'scores' => []];
            }
        } catch (\Throwable $e) {
            // پیش از migration ۰۳۵
        }
        $sum = ['total' => 0, 'done' => 0, 'open' => 0, 'delayed' => 0, 'done_late' => 0, 'open_late' => 0];
        $subjects = [];
        $reviewers = [];
        foreach ($tasks as $t) {
            $sum['total']++;
            $fin = self::finishedAt($t);               // تحویل‌شده (منتظر تأیید یا تأییدشده) = کار مسئول تمام است
            $done = $fin !== null;
            $waiting = $done && (int) $t['is_completed'] !== 1;
            $late = self::isLate($t, $fin);
            $sum[$done ? 'done' : 'open']++;
            if ($late) {
                $sum['delayed']++;
                $sum[$done ? 'done_late' : 'open_late']++;
            }
            // توزیع لیبل‌ها: «بدون لیبل» شمرده نمی‌شود
            if ($t['subject_id'] !== null) {
                $sk = (int) $t['subject_id'];
                $subjects[$sk] = $subjects[$sk] ?? ['label' => $t['subject_title'] ?? 'لیبل', 'color' => $t['subject_color'] ?: '#5f776a', 'value' => 0];
                $subjects[$sk]['value']++;
            }
            // KPI سرپرست: زمان تأیید (تحویل ← تأیید) به نام تأییدکننده
            if ((int) $t['is_completed'] === 1 && !empty($t['approved_by']) && !empty($t['delivered_at']) && !empty($t['completed_at'])) {
                $rid = (int) $t['approved_by'];
                $reviewers[$rid][] = max(0, (strtotime($t['completed_at']) - strtotime($t['delivered_at'])) / 3600);
            }

            $aid = !empty($t['assignee_group_id']) ? 'g' . (int) $t['assignee_group_id'] : (int) $t['assignee_id'];
            if (!isset($members[$aid])) {
                continue;     // مسئول دیگر عضو پروژه نیست
            }
            $m = &$members[$aid];
            $m['total']++;
            if ($done) {
                $m[$waiting ? 'waiting' : 'done']++;
                if ($late) {
                    $m['late']++;
                }
                $m['hours'][] = max(0, (strtotime($fin) - strtotime($t['created_at'])) / 3600);
                $sc = self::taskScore($t);
                if ($sc !== null) {
                    $m['scores'][] = $sc;
                }
            } else {
                $m['open']++;
                if ($late) {
                    $m['overdue']++;
                }
            }
            unset($m);
        }

        $out = [];
        $wSum = 0.0;
        $wCnt = 0;
        foreach ($members as $m) {
            if (!empty($m['is_group']) && $m['total'] === 0) {
                continue;                                    // گروه بدون تسک در جدول KPI نمی‌آید
            }
            $score = $m['scores'] ? array_sum($m['scores']) / count($m['scores']) : null;
            $avgH = $m['hours'] ? array_sum($m['hours']) / count($m['hours']) : null;
            if ($score !== null) {
                $wSum += $score * count($m['scores']);
                $wCnt += count($m['scores']);
            }
            unset($m['hours'], $m['scores']);
            $out[] = $m + [
                'avg_hours' => $avgH,
                'on_time' => $m['done'] + $m['waiting'] > 0 ? (int) round(($m['done'] + $m['waiting'] - $m['late']) / ($m['done'] + $m['waiting']) * 100) : null,
                'rating' => self::rating($score),
            ];
        }
        usort($out, static fn($a, $b) => [$b['rating']['score'] ?? -1, $b['done']] <=> [$a['rating']['score'] ?? -1, $a['done']]);

        $pct = static fn(int $n) => $sum['total'] > 0 ? (int) round($n / $sum['total'] * 100) : 0;
        $labeled = array_sum(array_column($subjects, 'value'));
        // تفکیک بدون هم‌پوشانی با جمع دقیق ۱۰۰٪ (بزرگ‌ترین باقی‌مانده): به‌موقع / با تأخیر انجام‌شده، باز در مهلت / عقب‌افتاده
        $parts = ['done_ontime' => $sum['done'] - $sum['done_late'], 'done_late' => $sum['done_late'], 'open_ontime' => $sum['open'] - $sum['open_late'], 'open_late' => $sum['open_late']];
        $split = array_fill_keys(array_keys($parts), 0);
        if ($sum['total'] > 0) {
            $rem = [];
            foreach ($parts as $k => $n) {
                $x = $n / $sum['total'] * 100;
                $split[$k] = (int) floor($x);
                $rem[$k] = $x - floor($x);
            }
            arsort($rem);
            foreach (array_slice(array_keys($rem), 0, 100 - array_sum($split)) as $k) {
                $split[$k]++;
            }
        }
        $overall = self::rating($wCnt > 0 ? $wSum / $wCnt : null);
        $summary = [
            'done_pct' => $pct($sum['done']), 'delayed_pct' => $pct($sum['delayed']), 'open_pct' => $pct($sum['open']), 'split' => $split,
            'rating' => $overall,
            'text' => $sum['total'] === 0
                ? 'هنوز تسکی در این پروژه ثبت نشده است.'
                : sprintf('%s٪ وظایف به‌موقع و %s٪ با تأخیر انجام شده؛ %s٪ هنوز باز است (%s٪ عقب‌افتاده)؛ ', fa_num($split['done_ontime']), fa_num($split['done_late']), fa_num($split['open_ontime'] + $split['open_late']), fa_num($split['open_late']))
                  . ($overall['score'] === null ? 'برای ارزیابی عملکرد اعضا هنوز تسک انجام‌شده‌ای وجود ندارد.' : 'عملکرد اعضا به‌طور کلی «' . $overall['label'] . '» ارزیابی می‌شود.'),
        ];

        $subjects = array_values($subjects);
        usort($subjects, static fn($a, $b) => $b['value'] <=> $a['value']);
        // نمودار خوانا بماند: ۸ لیبل پرتکرار + «سایر لیبل‌ها»
        $labelCount = count($subjects);
        if (count($subjects) > 9) {
            $rest = array_slice($subjects, 8);
            $subjects = array_slice($subjects, 0, 8);
            $subjects[] = ['label' => 'سایر لیبل‌ها (' . fa_num(count($rest)) . ')', 'color' => '#5f776a', 'value' => array_sum(array_column($rest, 'value'))];
        }
        foreach ($subjects as &$s) {
            $s['pct'] = $labeled > 0 ? (int) round($s['value'] / $labeled * 100) : 0;   // سهم از تسک‌های لیبل‌دار
        }
        unset($s);

        // KPI «میانگین زمان تأیید» هر سرپرست (تأییدکننده)
        $names = array_column(array_map(static fn($u) => ['id' => (int) $u['id'], 'u' => $u], self::people($projectId)), 'u', 'id');
        $rev = [];
        foreach ($reviewers as $rid => $hrs) {
            $u = $names[$rid] ?? DB::one('SELECT id, first_name, last_name, mobile, avatar FROM users WHERE id = ?', [$rid]);
            if ($u === null) {
                continue;
            }
            $rev[] = $u + ['count' => count($hrs), 'avg_hours' => array_sum($hrs) / count($hrs)];
        }
        usort($rev, static fn($a, $b) => $a['avg_hours'] <=> $b['avg_hours']);

        return ['members' => $out, 'summary' => $summary + ['counts' => $parts, 'total' => $sum['total']], 'subjects' => $subjects, 'label_count' => $labelCount, 'reviewers' => $rev];
    }

    /**
     * نمودار مقایسه‌ی سرعت (حداکثر ۵ کاربر) در بازه‌ی زمانی.
     * metric: score = میانگین امتیاز سرعت تسک‌های انجام‌شده در هر بازه | count = تعداد انجام‌شده
     */
    public static function speedSeries(int $projectId, array $userIds, string $range, string $metric): array
    {
        $range = isset(self::RANGES[$range]) ? $range : '1m';
        $metric = $metric === 'count' ? 'count' : 'score';
        $allowed = array_map('intval', array_column(self::people($projectId), 'id'));
        $userIds = array_slice(array_values(array_intersect(array_unique(array_map('intval', $userIds)), $allowed)), 0, 5);

        $now = time();
        $span = self::RANGES[$range][1];
        if ($span === null) {
            $first = DB::value('SELECT MIN(completed_at) FROM tasks WHERE project_id = ? AND is_completed = 1 AND blocked_at IS NULL', [$projectId]);
            $span = max(86400, $now - ($first ? (int) strtotime((string) $first) : $now));
        }
        $from = $now - $span;
        // اندازه‌ی هر خانه‌ی زمانی
        if ($span <= 2 * 86400) {
            $step = 6 * 3600;
        } elseif ($span <= 31 * 86400) {
            $step = 86400;
        } elseif ($span <= 200 * 86400) {
            $step = 7 * 86400;
        } else {
            $step = 30 * 86400;
        }
        $n = (int) ceil($span / $step);
        $from = $now - $n * $step;
        $labels = [];
        for ($i = 0; $i < $n; $i++) {
            $ts = $from + ($i + 1) * $step;
            $labels[] = $step < 86400 ? Jalali::md(date('Y-m-d', $ts)) . ' ' . fa_num(date('H', $ts)) . ':۰۰' : Jalali::md(date('Y-m-d', $ts));
        }

        $series = [];
        if ($userIds) {
            $in = implode(',', array_fill(0, count($userIds), '?'));
            $rows = DB::all("SELECT assignee_id, is_completed, board_status, created_at, completed_at, delivered_at, due_date, due_time FROM tasks
                WHERE project_id = ? AND is_completed = 1 AND blocked_at IS NULL AND completed_at >= ? AND assignee_id IN ($in)", array_merge([$projectId, date('Y-m-d H:i:s', $from)], $userIds));
            $buckets = [];
            foreach ($rows as $t) {
                $i = min($n - 1, max(0, (int) floor((strtotime($t['completed_at']) - $from) / $step)));
                $buckets[(int) $t['assignee_id']][$i][] = (float) self::taskScore($t);
            }
            $names = array_column(array_map(static fn($u) => ['id' => (int) $u['id'], 'name' => full_name($u)], self::people($projectId)), 'name', 'id');
            foreach ($userIds as $uid) {
                $data = [];
                for ($i = 0; $i < $n; $i++) {
                    $b = $buckets[$uid][$i] ?? [];
                    $data[] = $metric === 'count' ? count($b) : ($b ? (int) round(array_sum($b) / count($b)) : null);
                }
                $series[] = ['id' => $uid, 'name' => $names[$uid] ?? 'کاربر', 'data' => $data];
            }
        }
        return ['labels' => $labels, 'series' => $series, 'range' => $range, 'metric' => $metric, 'range_label' => self::RANGES[$range][0]];
    }
}
