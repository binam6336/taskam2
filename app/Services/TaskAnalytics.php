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

    /** امتیاز سرعت یک تسک انجام‌شده (یا null برای تسک باز). */
    public static function taskScore(array $t): ?float
    {
        if ((int) $t['is_completed'] !== 1 || empty($t['completed_at'])) {
            return null;
        }
        $hours = max(0.0, (strtotime($t['completed_at']) - strtotime($t['created_at'])) / 3600);
        if ($hours <= self::FAST_HOURS) {
            $time = 100.0;
        } elseif ($hours >= self::SLOW_HOURS) {
            $time = 0.0;
        } else {
            $time = 100 * (1 - log($hours / self::FAST_HOURS) / log(self::SLOW_HOURS / self::FAST_HOURS));
        }
        $due = self::dueTs($t);
        $onTime = $due === null || strtotime($t['completed_at']) <= $due;
        return 0.6 * $time + 0.4 * ($onTime ? 100 : 0);
    }

    /** همه‌ی تسک‌های پروژه با فیلدهای لازم برای محاسبه. */
    private static function tasks(int $projectId): array
    {
        return DB::all('SELECT t.id, t.assignee_id, t.subject_id, t.priority, t.is_completed, t.created_at, t.completed_at, t.due_date, t.due_time,
                s.title AS subject_title, s.color AS subject_color
            FROM tasks t LEFT JOIN subjects s ON s.id = t.subject_id WHERE t.project_id = ?', [$projectId]);
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
            $members[(int) $u['id']] = $u + ['total' => 0, 'done' => 0, 'open' => 0, 'overdue' => 0, 'late' => 0, 'hours' => [], 'scores' => []];
        }
        $sum = ['total' => 0, 'done' => 0, 'open' => 0, 'delayed' => 0];
        $subjects = [];
        foreach ($tasks as $t) {
            $sum['total']++;
            $done = (int) $t['is_completed'] === 1;
            $due = self::dueTs($t);
            $late = $done ? ($due !== null && $t['completed_at'] && strtotime($t['completed_at']) > $due) : ($due !== null && $due < $now);
            $sum[$done ? 'done' : 'open']++;
            if ($late) {
                $sum['delayed']++;
            }
            $sk = $t['subject_id'] !== null ? (int) $t['subject_id'] : 0;
            $subjects[$sk] = $subjects[$sk] ?? ['label' => $t['subject_title'] ?? 'بدون لیبل', 'color' => $t['subject_color'] ?: '#5f776a', 'value' => 0];
            $subjects[$sk]['value']++;

            $aid = (int) $t['assignee_id'];
            if (!isset($members[$aid])) {
                continue;     // مسئول دیگر عضو پروژه نیست
            }
            $m = &$members[$aid];
            $m['total']++;
            if ($done) {
                $m['done']++;
                if ($late) {
                    $m['late']++;
                }
                if ($t['completed_at']) {
                    $m['hours'][] = max(0, (strtotime($t['completed_at']) - strtotime($t['created_at'])) / 3600);
                }
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
            $score = $m['scores'] ? array_sum($m['scores']) / count($m['scores']) : null;
            $avgH = $m['hours'] ? array_sum($m['hours']) / count($m['hours']) : null;
            if ($score !== null) {
                $wSum += $score * count($m['scores']);
                $wCnt += count($m['scores']);
            }
            unset($m['hours'], $m['scores']);
            $out[] = $m + [
                'avg_hours' => $avgH,
                'on_time' => $m['done'] > 0 ? (int) round(($m['done'] - $m['late']) / $m['done'] * 100) : null,
                'rating' => self::rating($score),
            ];
        }
        usort($out, static fn($a, $b) => [$b['rating']['score'] ?? -1, $b['done']] <=> [$a['rating']['score'] ?? -1, $a['done']]);

        $pct = static fn(int $n) => $sum['total'] > 0 ? (int) round($n / $sum['total'] * 100) : 0;
        $overall = self::rating($wCnt > 0 ? $wSum / $wCnt : null);
        $summary = [
            'done_pct' => $pct($sum['done']), 'delayed_pct' => $pct($sum['delayed']), 'open_pct' => $pct($sum['open']),
            'rating' => $overall,
            'text' => $sum['total'] === 0
                ? 'هنوز تسکی در این پروژه ثبت نشده است.'
                : sprintf('%s٪ وظایف انجام شده، %s٪ وظایف با تأخیر بوده و %s٪ وظایف انجام نشده است؛ ', fa_num($pct($sum['done'])), fa_num($pct($sum['delayed'])), fa_num($pct($sum['open'])))
                  . ($overall['score'] === null ? 'برای ارزیابی عملکرد اعضا هنوز تسک انجام‌شده‌ای وجود ندارد.' : 'عملکرد اعضا به‌طور کلی «' . $overall['label'] . '» ارزیابی می‌شود.'),
        ];

        $subjects = array_values($subjects);
        usort($subjects, static fn($a, $b) => $b['value'] <=> $a['value']);
        foreach ($subjects as &$s) {
            $s['pct'] = $pct($s['value']);
        }
        unset($s);

        return ['members' => $out, 'summary' => $summary, 'subjects' => $subjects];
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
            $first = DB::value('SELECT MIN(completed_at) FROM tasks WHERE project_id = ? AND is_completed = 1', [$projectId]);
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
            $rows = DB::all("SELECT assignee_id, is_completed, created_at, completed_at, due_date, due_time FROM tasks
                WHERE project_id = ? AND is_completed = 1 AND completed_at >= ? AND assignee_id IN ($in)", array_merge([$projectId, date('Y-m-d H:i:s', $from)], $userIds));
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
                $series[] = ['id' => $uid, 'name' => $names[$uid] ?? ('#' . $uid), 'data' => $data];
            }
        }
        return ['labels' => $labels, 'series' => $series, 'range' => $range, 'metric' => $metric, 'range_label' => self::RANGES[$range][0]];
    }
}
