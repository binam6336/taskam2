<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Jalali;

/**
 * شاخص‌های عملکرد (KPI) تسک‌های پروژه.
 *
 * امتیاز سرعت هر تسک تحویل‌شده (۰ تا ۱۰۰):
 *   ۶۰٪ زمان انجام  → ۲۴ ساعت یا کمتر = ۱۰۰، ۱۴ روز یا بیشتر = ۰ (بین این دو لگاریتمی)
 *   ۴۰٪ به‌موقع بودن → تا پایان روز سررسید (یا بدون سررسید) = ۱۰۰، دیرتر = ۰
 *   منهای REWORK_PENALTY برای هر بار برگشت برای اصلاح (v7.7، DATA-002)
 * امتیاز یک کاربر میانگین امتیاز تسک‌های تحویل‌شده‌ی اوست؛ برچسب کیفی فقط با دست‌کم MIN_SAMPLE تسک (DATA-010).
 *
 * واژه‌ها (v7.7، DATA-003): «تحویل‌شده» = کار مسئول تمام است (منتظر تأیید + تأییدشده)؛ «تأییدشده» = سرپرست بسته است؛
 * «در جریان / باز» = هنوز تحویل نشده؛ «عقب‌افتاده» = سررسید روزهای قبل و هنوز تحویل نشده (TaskModel::OVERDUE).
 * زمان مسئول تا اولین تحویل برای بررسی حساب می‌شود (first_delivered_at؛ با برگشت پاک نمی‌شود)، نه تأیید سرپرست.
 * «میانگین زمان انجام» در همه‌جا = ایجاد تا تحویل (durations) با تعداد نمونه و میانه.
 */
final class TaskAnalytics
{
    public const FAST_HOURS = 24;
    public const SLOW_HOURS = 336;
    /** کمترین تعداد تسک تحویل‌شده برای برچسب کیفی امتیاز (کمتر = «داده‌ی ناکافی») */
    public const MIN_SAMPLE = 5;
    /** کسر امتیاز برای هر بار برگشت برای اصلاح */
    public const REWORK_PENALTY = 15;

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

    /** برچسب امتیاز؛ $n = تعداد نمونه (null = بدون بررسی حداقل نمونه) */
    public static function rating(?float $score, ?int $n = null): array
    {
        if ($score === null) {
            return ['label' => 'بدون داده', 'tone' => '', 'score' => null, 'n' => $n ?? 0, 'enough' => false];
        }
        if ($n !== null && $n < self::MIN_SAMPLE) {
            return ['label' => 'داده‌ی ناکافی', 'tone' => '', 'score' => (int) round($score), 'n' => $n, 'enough' => false];
        }
        foreach (self::RATINGS as [$min, $label, $tone]) {
            if ($score >= $min) {
                return ['label' => $label, 'tone' => $tone, 'score' => (int) round($score), 'n' => $n, 'enough' => true];
            }
        }
        return ['label' => 'خیلی بد', 'tone' => 'red', 'score' => (int) round($score), 'n' => $n, 'enough' => true];
    }

    public static function dueTs(array $t): ?int
    {
        if (empty($t['due_date'])) {
            return null;
        }
        $ts = strtotime($t['due_date'] . ' ' . ($t['due_time'] ?: '23:59:59'));
        return $ts === false ? null : $ts;
    }

    /** لحظه‌ی تحویل فعلی: تحویل برای بررسی، وگرنه تکمیل (تسک‌های قدیمی/بی‌پروژه)؛ null = الان تحویل‌شده نیست */
    public static function finishedAt(array $t): ?string
    {
        if (!empty($t['delivered_at']) && ((int) $t['is_completed'] === 1 || ($t['board_status'] ?? '') === 'review')) {
            return (string) $t['delivered_at'];
        }
        return (int) $t['is_completed'] === 1 && !empty($t['completed_at']) ? (string) $t['completed_at'] : null;
    }

    /** لحظه‌ی مبنای KPI: اولین تحویل (حتی اگر بعداً برای اصلاح برگشته باشد)، وگرنه finishedAt */
    public static function kpiAt(array $t): ?string
    {
        return !empty($t['first_delivered_at']) ? (string) $t['first_delivered_at'] : self::finishedAt($t);
    }

    /** با تأخیر؟ فقط وقتی روزِ تحویل بعد از روزِ سررسید باشد (برای تسک باز: سررسید روزهای قبل) */
    public static function isLate(array $t, ?string $finished): bool
    {
        if (empty($t['due_date'])) {
            return false;
        }
        return $finished !== null ? substr($finished, 0, 10) > $t['due_date'] : $t['due_date'] < date('Y-m-d');
    }

    /** امتیاز سرعت یک تسک (بر اساس اولین تحویل، با کسر برگشت‌ها) یا null برای تسکی که هرگز تحویل نشده. */
    public static function taskScore(array $t): ?float
    {
        $fin = self::kpiAt($t);
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
        $score = 0.6 * $time + 0.4 * (self::isLate($t, $fin) ? 0 : 100);
        return max(0.0, $score - self::REWORK_PENALTY * (int) ($t['rework_count'] ?? 0));
    }

    /** میانه */
    public static function median(array $v): ?float
    {
        if (!$v) {
            return null;
        }
        sort($v);
        $n = count($v);
        return $n % 2 ? (float) $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
    }

    /**
     * «میانگین زمان انجام» با یک تعریف در همه‌جا (DATA-004): ایجاد تا تحویل (finishedAt) برای ردیف‌های تحویل‌شده.
     * @return array{a:?float, med:?float, c:int} ساعت
     */
    public static function durations(array $rows): array
    {
        $h = [];
        foreach ($rows as $t) {
            $fin = self::finishedAt($t);
            if ($fin !== null) {
                $h[] = max(0, (strtotime($fin) - strtotime((string) $t['created_at'])) / 3600);
            }
        }
        return ['a' => $h ? array_sum($h) / count($h) : null, 'med' => self::median($h), 'c' => count($h)];
    }

    /** درصد با جمع دقیق ۱۰۰ (بزرگ‌ترین باقی‌مانده، تساوی به ترتیب ورودی) — برای همه‌ی نمودارهای بخشی (DATA-014) */
    public static function percents(array $values): array
    {
        $total = array_sum($values);
        $out = array_fill_keys(array_keys($values), 0);
        if ($total <= 0) {
            return $out;
        }
        $rem = [];
        $order = 0;
        foreach ($values as $k => $n) {
            $x = $n / $total * 100;
            $out[$k] = (int) floor($x);
            $rem[] = [$x - floor($x), $order++, $k];
        }
        usort($rem, static fn($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);
        foreach (array_slice($rem, 0, 100 - array_sum($out)) as [, , $k]) {
            $out[$k]++;
        }
        return $out;
    }

    /** همه‌ی تسک‌های پروژه با فیلدهای لازم برای محاسبه. */
    private static function tasks(int $projectId): array
    {
        return DB::all('SELECT t.id, t.assignee_id, t.subject_id, t.priority, t.is_completed, t.board_status, t.archived_at, t.project_id, t.created_at, t.completed_at, t.delivered_at, t.delivered_by, t.approved_by,
                t.first_delivered_at, t.rework_count, t.due_date, t.due_time, s.title AS subject_title, s.color AS subject_color
            FROM tasks t LEFT JOIN subjects s ON s.id = t.subject_id WHERE t.project_id = ? AND t.blocked_at IS NULL', [$projectId]);
    }

    /** سازنده + اعضای پروژه. */
    public static function people(int $projectId): array
    {
        return DB::all('SELECT u.id, u.first_name, u.last_name, u.mobile, u.avatar FROM users u
            WHERE u.id = (SELECT user_id FROM projects WHERE id = ?) OR u.id IN (SELECT user_id FROM project_members WHERE project_id = ?)
            ORDER BY u.first_name, u.last_name', [$projectId, $projectId]);
    }

    /** اعضا به ترتیب پرکاری (تعداد تسک تحویل‌شده، بعد کل تسک‌ها) — انتخاب پیش‌فرض نمودار مقایسه (DATA-013) */
    public static function busiest(int $projectId, int $limit = 5): array
    {
        $ids = array_map('intval', array_column(self::people($projectId), 'id'));
        if (!$ids) {
            return [];
        }
        $rows = DB::all('SELECT t.assignee_id id, SUM(t.is_completed = 1 OR t.first_delivered_at IS NOT NULL OR t.board_status = \'review\') d, COUNT(*) c
            FROM tasks t WHERE t.project_id = ? AND t.blocked_at IS NULL AND t.assignee_id IN (' . DB::in($ids) . ') GROUP BY t.assignee_id HAVING c > 0 ORDER BY d DESC, c DESC LIMIT ' . max(1, $limit), array_merge([$projectId], $ids));
        return array_map(static fn($r) => (int) $r['id'], $rows);
    }

    /**
     * KPI هر عضو، خلاصه‌ی پروژه و توزیع لیبل‌ها.
     * @return array{members:array,summary:array,subjects:array}
     */
    public static function project(int $projectId): array
    {
        $tasks = self::tasks($projectId);
        $blank = ['total' => 0, 'done' => 0, 'waiting' => 0, 'open' => 0, 'overdue' => 0, 'late' => 0, 'rework' => 0, 'delivered' => 0, 'hours' => [], 'scores' => []];
        $members = [];
        foreach (self::people($projectId) as $u) {
            $members[(int) $u['id']] = $u + $blank;
        }
        // DATA-007: تسک‌های بی‌مسئول یا با مسئولی که دیگر عضو پروژه نیست در یک ردیف جدا (جمع ردیف‌ها = کل تسک‌ها)
        $orphan = ['id' => null, 'first_name' => 'اعضای سابق / بدون مسئول', 'last_name' => '', 'mobile' => '', 'avatar' => null, 'is_orphan' => true] + $blank;
        $sum = ['total' => 0, 'done' => 0, 'waiting' => 0, 'open' => 0, 'delayed' => 0, 'done_late' => 0, 'open_late' => 0];
        $subjects = [];
        $reviewers = [];
        $rework = 0;
        $delivered = 0;
        foreach ($tasks as $t) {
            $sum['total']++;
            $fin = self::finishedAt($t);               // الان تحویل‌شده (منتظر تأیید یا تأییدشده)
            $done = $fin !== null;
            $waiting = $done && (int) $t['is_completed'] !== 1;
            $kpi = self::kpiAt($t);                    // اولین تحویل (مبنای امتیاز و تأخیر)
            $late = $done ? self::isLate($t, $kpi) : self::isLate($t, null);
            $sum[$done ? ($waiting ? 'waiting' : 'done') : 'open']++;
            if ($late) {
                $sum['delayed']++;
                $sum[$done ? 'done_late' : 'open_late']++;
            }
            if ($kpi !== null) {
                $delivered++;
                $rework += (int) $t['rework_count'] > 0 ? 1 : 0;
            }
            // توزیع لیبل‌ها: «بدون لیبل» شمرده نمی‌شود
            if ($t['subject_id'] !== null) {
                $sk = (int) $t['subject_id'];
                $subjects[$sk] = $subjects[$sk] ?? ['label' => $t['subject_title'] ?? 'لیبل', 'color' => $t['subject_color'] ?: '#5f776a', 'value' => 0];
                $subjects[$sk]['value']++;
            }
            // KPI سرپرست: زمان تأیید (تحویل ← تأیید) به نام تأییدکننده؛ DATA-011: تأیید کار خودِ تأییدکننده (تحویل و تأیید هم‌زمان) شمرده نمی‌شود
            if ((int) $t['is_completed'] === 1 && !empty($t['approved_by']) && !empty($t['delivered_at']) && !empty($t['completed_at'])
                && (int) $t['approved_by'] !== (int) ($t['delivered_by'] ?? 0)) {
                $reviewers[(int) $t['approved_by']][] = max(0, (strtotime($t['completed_at']) - strtotime($t['delivered_at'])) / 3600);
            }

            $aid = (int) $t['assignee_id'];
            if (isset($members[$aid])) {
                $m = &$members[$aid];
            } else {
                $m = &$orphan;                         // مسئول دیگر عضو پروژه نیست یا تسک مسئول ندارد
            }
            $m['total']++;
            if ($done) {
                $m[$waiting ? 'waiting' : 'done']++;
                if ($late) {
                    $m['late']++;
                }
                $m['hours'][] = max(0, (strtotime($fin) - strtotime($t['created_at'])) / 3600);
            } else {
                $m['open']++;
                if (self::isLate($t, null) && !(($t['board_status'] ?? '') === 'review')) {
                    $m['overdue']++;
                }
            }
            if ($kpi !== null) {
                $m['delivered']++;
                $m['rework'] += (int) $t['rework_count'] > 0 ? 1 : 0;
                $sc = self::taskScore($t);
                if ($sc !== null) {
                    $m['scores'][] = $sc;
                }
            }
            unset($m);
        }

        $out = [];
        $wSum = 0.0;
        $wCnt = 0;
        $rows = array_values($members);
        if ($orphan['total'] > 0) {
            $rows[] = $orphan;
        }
        foreach ($rows as $m) {
            $n = count($m['scores']);
            $score = $n ? array_sum($m['scores']) / $n : null;
            if ($score !== null && empty($m['is_orphan'])) {
                $wSum += $score * $n;
                $wCnt += $n;
            }
            $hours = $m['hours'];
            unset($m['hours'], $m['scores']);
            $fin = $m['done'] + $m['waiting'];
            $out[] = $m + [
                'avg_hours' => $hours ? array_sum($hours) / count($hours) : null,
                'median_hours' => self::median($hours),
                'hours_n' => count($hours),
                'on_time' => $fin > 0 ? (int) round(($fin - $m['late']) / $fin * 100) : null,
                'rework_pct' => $m['delivered'] > 0 ? (int) round($m['rework'] / $m['delivered'] * 100) : null,
                'rating' => self::rating($score, $n),
                'is_orphan' => !empty($m['is_orphan']),
            ];
        }
        // ترتیب: اعضا با امتیاز معتبر (بیشترین اول)، بعد داده‌ی ناکافی، بعد بدون داده؛ ردیف «اعضای سابق» آخر
        $key = static fn($r) => [(int) $r['is_orphan'], $r['rating']['score'] === null ? 2 : ($r['rating']['enough'] ? 0 : 1), -($r['rating']['score'] ?? 0), -$r['done']];
        usort($out, static fn($a, $b) => $key($a) <=> $key($b));

        // تفکیک بدون هم‌پوشانی با جمع دقیق ۱۰۰٪
        $parts = ['done_ontime' => $sum['done'] + $sum['waiting'] - $sum['done_late'], 'done_late' => $sum['done_late'], 'open_ontime' => $sum['open'] - $sum['open_late'], 'open_late' => $sum['open_late']];
        $split = self::percents($parts);
        $overall = self::rating($wCnt > 0 ? $wSum / $wCnt : null, $wCnt);
        $deliveredNow = $sum['done'] + $sum['waiting'];
        $summary = [
            'split' => $split,
            'rating' => $overall,
            'rework_pct' => $delivered > 0 ? (int) round($rework / $delivered * 100) : null,
            'text' => $sum['total'] === 0
                ? 'هنوز تسکی در این پروژه ثبت نشده است.'
                : sprintf('%s٪ وظایف به‌موقع و %s٪ با تأخیر تحویل شده (%s تأییدشده، %s منتظر تأیید)؛ %s٪ هنوز تحویل نشده (%s٪ عقب‌افتاده)؛ ',
                    fa_num($split['done_ontime']), fa_num($split['done_late']), fa_num($sum['done']), fa_num($sum['waiting']), fa_num($split['open_ontime'] + $split['open_late']), fa_num($split['open_late']))
                  . ($overall['score'] === null ? 'برای ارزیابی عملکرد اعضا هنوز تسک تحویل‌شده‌ای وجود ندارد.'
                    : (!$overall['enough'] ? 'برای ارزیابی کیفی عملکرد دست‌کم ' . fa_num(self::MIN_SAMPLE) . ' تسک تحویل‌شده لازم است (فعلاً ' . fa_num($wCnt) . ').'
                    : 'عملکرد اعضا به‌طور کلی «' . $overall['label'] . '» ارزیابی می‌شود (بر اساس ' . fa_num($wCnt) . ' تسک).')),
        ];

        $subjects = array_values($subjects);
        usort($subjects, static fn($a, $b) => $b['value'] <=> $a['value']);
        $labelCount = count($subjects);
        if (count($subjects) > 9) {
            $rest = array_slice($subjects, 8);
            $subjects = array_slice($subjects, 0, 8);
            $subjects[] = ['label' => 'سایر لیبل‌ها (' . fa_num(count($rest)) . ')', 'color' => '#5f776a', 'value' => array_sum(array_column($rest, 'value'))];
        }
        // DATA-014: همان روش گرد کردن نوار خلاصه (جمع دقیق ۱۰۰٪)
        foreach (self::percents(array_column($subjects, 'value')) as $i => $pc) {
            $subjects[$i]['pct'] = $pc;
        }

        // KPI «میانگین زمان تأیید» هر سرپرست (تأییدکننده) با میانه و تعداد
        $names = array_column(array_map(static fn($u) => ['id' => (int) $u['id'], 'u' => $u], self::people($projectId)), 'u', 'id');
        $rev = [];
        foreach ($reviewers as $rid => $hrs) {
            $u = $names[$rid] ?? DB::one('SELECT id, first_name, last_name, mobile, avatar FROM users WHERE id = ?', [$rid]);
            if ($u === null) {
                continue;
            }
            $rev[] = $u + ['count' => count($hrs), 'avg_hours' => array_sum($hrs) / count($hrs), 'median_hours' => self::median($hrs)];
        }
        usort($rev, static fn($a, $b) => $a['median_hours'] <=> $b['median_hours']);

        return ['members' => $out, 'summary' => $summary + ['counts' => $parts, 'total' => $sum['total'], 'sum' => $sum, 'delivered' => $deliveredNow],
            'subjects' => $subjects, 'label_count' => $labelCount, 'reviewers' => $rev, 'durations' => self::durations($tasks)];
    }

    /**
     * خانه‌های زمانی تقویمی نمودار مقایسه (DATA-005): مرز روز/هفته (شنبه)/ماه شمسی به وقت سرور (تهران)، برچسب = شروع خانه.
     * @return array{unit:string, keys:array, labels:array, from:int}
     */
    public static function buckets(string $range, ?int $firstTs = null): array
    {
        $now = time();
        $span = self::RANGES[$range][1] ?? null;
        if ($span === null) {
            $span = max(86400, $now - ($firstTs ?? $now));
        }
        if ($range === '2d') {
            $unit = '6h';
        } elseif ($span <= 31 * 86400) {
            $unit = 'day';
        } elseif ($span <= 200 * 86400) {
            $unit = 'week';
        } else {
            $unit = 'month';
        }
        $keep = ['2d' => 8, '1w' => 7, '1m' => 30, '6m' => 26, '1y' => 12][$range] ?? null;
        $keys = [];
        $labels = [];
        $stepSec = $unit === '6h' ? 6 * 3600 : 86400;
        $start = $unit === '6h' ? strtotime(date('Y-m-d 00:00:00', $now - 86400)) : strtotime(date('Y-m-d', $now - $span));
        for ($ts = $start; $ts <= $now; $ts += $stepSec) {
            $k = self::bucketKey($unit, $ts);
            if (!isset($labels[$k])) {
                $keys[] = $k;
                $labels[$k] = self::bucketLabel($unit, $k);
            }
        }
        $todayKey = self::bucketKey($unit, $now);
        if (!isset($labels[$todayKey])) {
            $keys[] = $todayKey;
            $labels[$todayKey] = self::bucketLabel($unit, $todayKey);
        }
        if ($keep !== null && count($keys) > $keep) {
            $keys = array_slice($keys, -$keep);
        }
        return ['unit' => $unit, 'keys' => $keys, 'labels' => array_map(static fn($k) => $labels[$k], $keys), 'from' => self::bucketStart($unit, $keys[0])];
    }

    private static function bucketKey(string $unit, int $ts): string
    {
        if ($unit === '6h') {
            return date('Y-m-d', $ts) . ' ' . sprintf('%02d', intdiv((int) date('G', $ts), 6) * 6);
        }
        if ($unit === 'day') {
            return date('Y-m-d', $ts);
        }
        if ($unit === 'week') {
            $back = ((int) date('w', $ts) + 1) % 7;          // شنبه = شروع هفته
            return date('Y-m-d', strtotime(date('Y-m-d', $ts) . " -$back days"));
        }
        [$jy, $jm] = Jalali::fromGregorian((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
        return sprintf('%04d-%02d', $jy, $jm);
    }

    private static function bucketLabel(string $unit, string $k): string
    {
        if ($unit === '6h') {
            return Jalali::md(substr($k, 0, 10)) . ' ' . fa_num(substr($k, 11, 2)) . ':۰۰';
        }
        if ($unit === 'day') {
            return Jalali::md($k);
        }
        if ($unit === 'week') {
            return 'هفته‌ی ' . Jalali::md($k);
        }
        [$jy, $jm] = array_map('intval', explode('-', $k));
        return Jalali::MONTHS[$jm - 1] . ' ' . fa_num($jy);
    }

    /** شروع یک خانه (برای حد پایین کوئری) */
    private static function bucketStart(string $unit, string $k): int
    {
        if ($unit === '6h') {
            return (int) strtotime($k . ':00:00');
        }
        if ($unit !== 'month') {
            return (int) strtotime($k);
        }
        // ماه شمسی: اولین روز میلادی که کلیدش همین ماه است (حداکثر ۳۲ روز عقب‌تر از امروزِ آن ماه)
        $ts = strtotime(date('Y-m-d', time() - 400 * 86400));
        while (self::bucketKey('month', $ts) < $k) {
            $ts += 86400;
        }
        return (int) $ts;
    }

    /**
     * نمودار مقایسه‌ی سرعت (حداکثر ۵ کاربر) در بازه‌ی زمانی.
     * metric: score = میانگین امتیاز سرعت تسک‌های تحویل‌شده در هر خانه | count = تعداد تحویل
     * مبنای زمانی = اولین تحویل (kpiAt)، همان مبنای جدول «عملکرد اعضا» (DATA-005).
     */
    public static function speedSeries(int $projectId, array $userIds, string $range, string $metric): array
    {
        $range = isset(self::RANGES[$range]) ? $range : '1m';
        $metric = $metric === 'count' ? 'count' : 'score';
        $allowed = array_map('intval', array_column(self::people($projectId), 'id'));
        $userIds = array_slice(array_values(array_intersect(array_unique(array_map('intval', $userIds)), $allowed)), 0, 5);
        $at = "COALESCE(first_delivered_at, CASE WHEN is_completed = 1 OR board_status = 'review' THEN COALESCE(delivered_at, completed_at) END)";
        $first = self::RANGES[$range][1] === null ? DB::value("SELECT MIN($at) FROM tasks WHERE project_id = ? AND blocked_at IS NULL", [$projectId]) : null;
        $b = self::buckets($range, $first ? (int) strtotime((string) $first) : null);
        $index = array_flip($b['keys']);

        $series = [];
        if ($userIds) {
            $rows = DB::all("SELECT assignee_id, is_completed, board_status, created_at, completed_at, delivered_at, first_delivered_at, rework_count, due_date, due_time FROM tasks
                WHERE project_id = ? AND blocked_at IS NULL AND $at >= ? AND assignee_id IN (" . DB::in($userIds) . ')', array_merge([$projectId, date('Y-m-d H:i:s', $b['from'])], $userIds));
            $cells = [];
            foreach ($rows as $t) {
                $kpi = self::kpiAt($t);
                if ($kpi === null) {
                    continue;
                }
                $k = self::bucketKey($b['unit'], (int) strtotime($kpi));
                if (isset($index[$k])) {
                    $cells[(int) $t['assignee_id']][$index[$k]][] = (float) self::taskScore($t);
                }
            }
            $names = array_column(array_map(static fn($u) => ['id' => (int) $u['id'], 'name' => full_name($u)], self::people($projectId)), 'name', 'id');
            foreach ($userIds as $uid) {
                $data = [];
                foreach (array_keys($b['keys']) as $i) {
                    $c = $cells[$uid][$i] ?? [];
                    $data[] = $metric === 'count' ? count($c) : ($c ? (int) round(array_sum($c) / count($c)) : null);
                }
                $series[] = ['id' => $uid, 'name' => $names[$uid] ?? 'کاربر', 'data' => $data];
            }
        }
        return ['labels' => $b['labels'], 'series' => $series, 'range' => $range, 'metric' => $metric, 'range_label' => self::RANGES[$range][0], 'unit' => $b['unit']];
    }
}
