<?php
declare(strict_types=1);

namespace App\Services;

/**
 * «بار کاری» (Workload) هر پروژه (v7.10).
 *
 * computeLoads() و suggestRebalance() پورت یک‌به‌یک همان توابع public/assets/js/modules/workload.js (مشخصات مرجع) هستند؛
 * خروجی هر دو روی fixtureهای tests/workload/*.json باید دقیقاً یکی باشد (bin/test-workload.php).
 * ریزه‌کاری‌های JS هم عیناً حفظ شده‌اند: Math.round = floor(x + 0.5)، ترتیب کلیدهای شیء (کلیدهای عددی اول)،
 * localeCompare('fa') = Collator('fa')، و اینکه loads() داخل شبیه‌سازی از سررسید اصلی تسک استفاده می‌کند (نه سررسید پیشنهادی).
 */
final class WorkloadService
{
    public const DEFAULTS = [
        'capacity' => 12, 'window_days' => 7, 'weights' => ['high' => 3, 'medium' => 2, 'low' => 1],
        'free_below' => 70, 'over_above' => 100, 'target_max' => 85, 'daily_share' => 5, 'max_suggestions' => 5,
    ];
    /** وضعیت‌هایی که بار مسئول حساب می‌شوند */
    public const OPEN = ['backlog' => 1, 'todo' => 1, 'in_progress' => 1, 'rework' => 1];
    /** فقط کار شروع‌نشده برای جابه‌جایی پیشنهاد می‌شود */
    public const MOVABLE = ['backlog' => 1, 'todo' => 1];
    public const CAP_MIN = 4;
    public const CAP_MAX = 30;

    // ───────────────────────── کمکی‌ها (همان رفتار JS) ─────────────────────────

    private static function settings(array $data): array
    {
        return array_replace(self::DEFAULTS, (array) ($data['settings'] ?? []));
    }

    /** Math.round جاوااسکریپت (نیمه به سمت +∞) */
    private static function jsRound(float $x): int
    {
        return (int) floor($x + 0.5);
    }

    /** روزهای میان دو تاریخ Y-m-d (a − b) */
    private static function diffDays(string $a, string $b): int
    {
        return (int) round((strtotime($a . ' 00:00:00 UTC') - strtotime($b . ' 00:00:00 UTC')) / 86400);
    }

    private static function addDays(string $ymd, int $n): string
    {
        return gmdate('Y-m-d', strtotime($ymd . ' 00:00:00 UTC') + $n * 86400);
    }

    /** localeCompare(…, 'fa') */
    private static function nameCmp(string $a, string $b): int
    {
        static $coll = false;
        if ($coll === false) {
            $coll = class_exists(\Collator::class) ? new \Collator('fa') : null;
        }
        if ($coll !== null) {
            return (int) $coll->compare($a, $b);
        }
        // بدون intl: ترتیب الفبای فارسی، بقیه‌ی نویسه‌ها با کد یونیکد (بدون وابستگی به mbstring)
        static $rank = null;
        if ($rank === null) {
            $rank = array_flip(preg_split('//u', 'آاأإءئؤبپتثجچحخدذرزژسشصضطظعغفقکكگلمنوهةیي', -1, PREG_SPLIT_NO_EMPTY));
        }
        $key = static function (string $s) use ($rank): array {
            $out = [];
            foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                if (isset($rank[$ch])) {
                    $out[] = $rank[$ch];
                    continue;
                }
                $b = array_values(unpack('C*', $ch));        // کد یونیکد از بایت‌های UTF-8
                $cp = count($b) === 1 ? $b[0] : (count($b) === 2 ? (($b[0] & 0x1F) << 6) | ($b[1] & 0x3F)
                    : (count($b) === 3 ? (($b[0] & 0x0F) << 12) | (($b[1] & 0x3F) << 6) | ($b[2] & 0x3F) : (($b[0] & 0x07) << 18) | (($b[1] & 0x3F) << 12) | (($b[2] & 0x3F) << 6) | ($b[3] & 0x3F)));
                $out[] = 1000 + $cp;
            }
            return $out;
        };
        return $key($a) <=> $key($b);
    }

    /** ترتیب Object.keys در JS: کلیدهای شبیه عدد صحیح به ترتیب صعودی، بعد بقیه به ترتیب درج */
    private static function jsKeys(array $map): array
    {
        $ints = [];
        $rest = [];
        foreach (array_keys($map) as $k) {
            $s = (string) $k;
            if (preg_match('/^(0|[1-9]\d{0,8})$/', $s)) {
                $ints[] = $s;
            } else {
                $rest[] = $s;
            }
        }
        usort($ints, static fn($a, $b) => (int) $a <=> (int) $b);
        return array_merge($ints, $rest);
    }

    /** 'od' عقب‌افتاده | 'wk' سررسید در پنجره | 'nd' بدون سررسید | 'later' دورتر */
    public static function bucketOf(?string $due, string $today, int $win): string
    {
        if ($due === null || $due === '') {
            return 'nd';
        }
        $dd = self::diffDays($due, $today);
        return $dd < 0 ? 'od' : ($dd < $win ? 'wk' : 'later');
    }

    private static function weightOf(array $t, array $st): int
    {
        $w = $st['weights'][$t['priority'] ?? ''] ?? 0;
        return (int) ($w ?: $st['weights']['medium']);
    }

    // ───────────────────────── مشخصات (SPEC) ─────────────────────────

    /**
     * بار هر عضو: load = od + wk + nd (جمع وزن)، pct = round(load ÷ capacity × 100)، مرتب: بیشترین درصد، بیشترین تسک باز، نام.
     * @param array|null $assign  task_id(string) => member_id (شبیه‌سازی)
     * @return array<int,array{member:array,capacity:int,od:int,wk:int,nd:int,later:int,load:int,pct:int,level:string,open:int,tasks:array}>
     */
    public static function computeLoads(array $data, ?int $capOverride = null, ?array $assign = null): array
    {
        $st = self::settings($data);
        $today = (string) $data['today'];
        $win = (int) $st['window_days'];
        $defCap = $capOverride ?: (int) $st['capacity'];
        $rows = [];
        foreach ($data['members'] as $m) {
            $rows[(string) $m['id']] = ['member' => $m, 'capacity' => (int) (($m['capacity'] ?? null) ?: $defCap),
                'od' => 0, 'wk' => 0, 'nd' => 0, 'later' => 0, 'load' => 0, 'pct' => 0, 'open' => 0, 'tasks' => []];
        }
        foreach ($data['tasks'] as $t) {
            if (!isset(self::OPEN[$t['status'] ?? ''])) {
                continue;
            }
            $tid = (string) $t['id'];
            $aid = $assign !== null && array_key_exists($tid, $assign) && $assign[$tid] !== null ? $assign[$tid] : ($t['assignee_id'] ?? null);
            if ($aid === null || !isset($rows[(string) $aid])) {
                continue;
            }
            $r = &$rows[(string) $aid];
            $b = self::bucketOf($t['due_date'] ?? null, $today, $win);
            $r[$b] += self::weightOf($t, $st);
            $r['open']++;
            $r['tasks'][] = $t;
            unset($r);
        }
        $out = [];
        foreach (self::jsKeys($rows) as $k) {
            $r = $rows[$k];
            $r['load'] = $r['od'] + $r['wk'] + $r['nd'];
            $r['pct'] = self::jsRound($r['load'] / $r['capacity'] * 100);
            $r['level'] = $r['pct'] > $st['over_above'] ? 'bd' : ($r['pct'] >= $st['free_below'] ? 'wr' : 'ok');
            $out[] = $r;
        }
        usort($out, static fn($a, $b) => ($b['pct'] <=> $a['pct']) ?: (($b['open'] <=> $a['open']) ?: self::nameCmp((string) $a['member']['name'], (string) $b['member']['name'])));
        return $out;
    }

    /**
     * پیشنهاد توزیع دوباره (حریصانه، قطعی، زنجیره‌ای) — توضیح کامل در کامنت suggestRebalance در workload.js.
     * @param array $ignore task_id(string) => true  (تسک‌هایی که کاربر «رد» کرده)
     * @return array{suggestions:array, unresolved:array}
     */
    public static function suggestRebalance(array $data, ?int $capOverride = null, array $ignore = []): array
    {
        $st = self::settings($data);
        $today = (string) $data['today'];
        $win = (int) $st['window_days'];
        $assign = [];
        foreach ($data['tasks'] as $t) {
            $assign[(string) $t['id']] = $t['assignee_id'] ?? null;
        }
        $dueOverride = [];
        $out = [];
        $blocked = [];
        $unresolved = [];
        $loads = static function () use ($data, $capOverride, &$assign) {
            return self::computeLoads($data, $capOverride, $assign);   // شبیه‌سازی: واگذاری‌های تا این لحظه
        };
        $dueOf = static function (array $t) use (&$dueOverride) {
            return $dueOverride[(string) $t['id']] ?? ($t['due_date'] ?? null);
        };

        for ($guard = 0; $guard < 50 && count($out) < $st['max_suggestions']; $guard++) {
            $rows = $loads();
            $src = null;
            foreach ($rows as $r) {
                if ($r['pct'] > $st['over_above'] && empty($blocked[(string) $r['member']['id']])) {
                    $src = $r;
                    break;
                }
            }
            if ($src === null) {
                break;
            }
            $excess = $src['load'] - $src['capacity'];
            $cands = array_values(array_filter($src['tasks'], static fn($t) => isset(self::MOVABLE[$t['status']]) && empty($ignore[(string) $t['id']])
                && self::bucketOf($dueOf($t), $today, $win) !== 'later'));
            usort($cands, static function ($a, $b) use ($st, $excess, $dueOf, $today, $win) {
                $wa = self::weightOf($a, $st);
                $wb = self::weightOf($b, $st);
                $ka = $wa <= $excess ? $excess - $wa : 100 + ($wa - $excess);
                $kb = $wb <= $excess ? $excess - $wb : 100 + ($wb - $excess);
                if ($ka !== $kb) {
                    return $ka - $kb;
                }
                $oa = self::bucketOf($dueOf($a), $today, $win) === 'od' ? 0 : 1;
                $ob = self::bucketOf($dueOf($b), $today, $win) === 'od' ? 0 : 1;
                if ($oa !== $ob) {
                    return $oa - $ob;
                }
                $da = $dueOf($a) ?: '9999';
                $db = $dueOf($b) ?: '9999';
                if ($da !== $db) {
                    return $da < $db ? -1 : 1;
                }
                return strcmp((string) $a['id'], (string) $b['id']) < 0 ? -1 : 1;
            });
            $moved = false;
            foreach ($cands as $t) {
                $w = self::weightOf($t, $st);
                $targets = [];
                foreach ($rows as $r) {
                    $m = $r['member'];
                    if ((string) $m['id'] === (string) $src['member']['id'] || (array_key_exists('can_receive', $m) && $m['can_receive'] === false)) {
                        continue;
                    }
                    if (isset($m['wip_limit']) && $m['wip_limit'] !== null && $r['open'] >= $m['wip_limit']) {
                        continue;
                    }
                    if (($r['load'] + $w) / $r['capacity'] * 100 > $st['target_max']) {
                        continue;
                    }
                    $aff = !empty($t['label']) && (bool) array_filter($r['tasks'], static fn($x) => ($x['label'] ?? null) === $t['label']);
                    $targets[] = ['r' => $r, 'aff' => $aff, 'score' => $r['pct'] - ($aff ? 10 : 0)];
                }
                if (!$targets) {
                    continue;
                }
                usort($targets, static fn($a, $b) => ($a['score'] <=> $b['score']) ?: self::nameCmp((string) $a['r']['member']['name'], (string) $b['r']['member']['name']));
                $tg = $targets[0]['r'];
                $wasOverdue = self::bucketOf($dueOf($t), $today, $win) === 'od';
                $newDue = null;
                if ($wasOverdue) {
                    $dailyCap = (int) ceil($tg['capacity'] / $st['daily_share']);
                    for ($d = 0; $d < $win && $newDue === null; $d++) {
                        $day = self::addDays($today, $d);
                        $dayLoad = 0;
                        foreach ($tg['tasks'] as $x) {
                            if ($dueOf($x) === $day) {
                                $dayLoad += self::weightOf($x, $st);
                            }
                        }
                        if ($dayLoad + $w <= max($dailyCap, $w)) {
                            $newDue = $day;
                        }
                    }
                    $newDue = $newDue ?? self::addDays($today, $win - 1);
                }
                $before = ['from' => $src['pct'], 'to' => $tg['pct']];
                $assign[(string) $t['id']] = $tg['member']['id'];
                if ($newDue !== null) {
                    $dueOverride[(string) $t['id']] = $newDue;
                }
                $a = [];
                foreach ($loads() as $r) {
                    $a[(string) $r['member']['id']] = $r['pct'];
                }
                $out[] = [
                    'task_id' => $t['id'], 'task_title' => $t['title'], 'weight' => $w, 'label' => !empty($t['label']) ? $t['label'] : null,
                    'from_id' => $src['member']['id'], 'from_name' => $src['member']['name'], 'to_id' => $tg['member']['id'], 'to_name' => $tg['member']['name'],
                    'from_before' => $before['from'], 'from_after' => $a[(string) $src['member']['id']], 'to_before' => $before['to'], 'to_after' => $a[(string) $tg['member']['id']],
                    'was_overdue' => $wasOverdue, 'new_due_date' => $newDue, 'affinity' => $targets[0]['aff'],
                ];
                $moved = true;
                break;
            }
            if (!$moved) {
                $sid = (string) $src['member']['id'];
                $blocked[$sid] = true;
                $hasMovable = (bool) array_filter($src['tasks'], static fn($t) => isset(self::MOVABLE[$t['status']]) && empty($ignore[(string) $t['id']]));
                $unresolved[$sid] = ['member_id' => $src['member']['id'], 'name' => $src['member']['name'], 'pct' => $src['pct'], 'reason' => $hasMovable ? 'no_target' : 'nothing_movable'];
            }
        }
        return ['suggestions' => $out, 'unresolved' => array_map(static fn($k) => $unresolved[$k], self::jsKeys($unresolved))];
    }

    /** خروجی loads برای API و تست‌ها (همان شکل fixtureها) */
    public static function loadsOut(array $rows): array
    {
        return array_map(static fn($r) => ['member_id' => $r['member']['id'], 'name' => $r['member']['name'], 'capacity' => $r['capacity'],
            'od' => $r['od'], 'wk' => $r['wk'], 'nd' => $r['nd'], 'load' => $r['load'], 'pct' => $r['pct'], 'level' => $r['level'], 'open' => $r['open']], $rows);
    }

    // ───────────────────────── داده‌ی پنل (v7.10) ─────────────────────────

    /** «امروز» به وقت تهران (مشخصات: Asia/Tehran، مستقل از منطقه‌ی زمانی سرور) */
    public static function today(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tehran')))->format('Y-m-d');
    }

    /** مشاهده: سازنده‌ی پروژه یا عضو با دسترسی پروژه‌ی «مشاهده‌ی تحلیل‌ها» */
    public static function canView(int $projectId, int $uid): bool
    {
        return Access::isProjectMember($projectId, $uid) && Access::projectCan($projectId, $uid, 'view_analytics');
    }

    /** مدیریت: سازنده یا عضو با دسترسی تسک «تغییر مسئول» در همین پروژه */
    public static function canManage(int $projectId, int $uid): bool
    {
        return self::canView($projectId, $uid) && in_array('reassign', Access::projectTaskPerms($projectId, $uid), true);
    }

    /**
     * نگاشت ستون بورد پروژه به وضعیت‌های بار کاری: backlog/todo/in_progress/rework/review/done/archived.
     * ستون‌های سفارشی (c_…) نوع پایه ندارند → todo.
     */
    public static function statusMap(string $key): string
    {
        return in_array($key, ['backlog', 'todo', 'in_progress', 'rework', 'review', 'done', 'archived'], true) ? $key : 'todo';
    }

    /** اولویت تسک برای بار کاری: در پروژه‌ی درصدی ≥۶۷ زیاد، ≥۳۴ متوسط، بقیه کم (آستانه‌های مشخصات، نه Priority::levelOf) */
    public static function priorityOf(array $t, string $type): string
    {
        if ($type === 'percent' && $t['priority_percent'] !== null) {
            $p = (int) $t['priority_percent'];
            return $p >= 67 ? 'high' : ($p >= 34 ? 'medium' : 'low');
        }
        return in_array($t['priority'], ['low', 'medium', 'high'], true) ? (string) $t['priority'] : 'medium';
    }

    /**
     * سقف هم‌زمان عضو (wip_limit) = کمترین «سقف تعداد تسک» او در ستون‌های دارای سقفِ بورد همین پروژه
     * (همان BoardService::limitFor: سقف اختصاصی عضو، وگرنه پیش‌فرض نقش)؛ null = بی‌سقف.
     */
    public static function wipLimit(int $projectId, int $uid): ?int
    {
        $min = null;
        foreach (BoardConfig::limited($projectId) as $k) {
            if (in_array($k, ['done', 'review', 'archived'], true)) {
                continue;
            }
            $l = BoardService::limitFor($projectId, $uid, $k);
            if ($l !== null && ($min === null || $l < $min)) {
                $min = $l;
            }
        }
        return $min;
    }

    /** اعضای پروژه با شناسه‌ی داخلی (کلید = user_id) */
    private static function memberRows(int $projectId): array
    {
        $creator = (int) \App\Core\DB::value('SELECT user_id FROM projects WHERE id = ?', [$projectId]);
        $out = [];
        foreach (\App\Models\ProjectModel::members($projectId) as $m) {
            $uid = (int) $m['user_id'];
            $isCreator = $uid === $creator;
            $role = $isCreator ? 'creator' : Access::memberRole($m);
            $out[$uid] = [
                'uid' => $uid, 'name' => full_name($m), 'avatar' => avatar_url($m['avatar'] ?? null),
                'capacity' => $m['weekly_capacity'] !== null ? (int) $m['weekly_capacity'] : null,
                'wip_limit' => self::wipLimit($projectId, $uid),
                // تأییدشده (ردیف project_members فقط بعد از پذیرش دعوت ساخته می‌شود) + فعال + نقشی غیر از «بدون دسترسی»
                'can_receive' => ($m['user_status'] ?? '') === 'active' && $role !== 'none',
            ];
        }
        return $out;
    }

    /** ردیف‌های تسک باز پروژه که بیننده می‌بیند (بدون مسدود، آرشیو، تأییدشده و محرمانه‌ی نادیدنی) */
    private static function taskRows(int $projectId, int $viewer): array
    {
        return \App\Core\DB::all('SELECT t.id, t.pid, t.title, t.priority, t.priority_percent, t.board_status, t.is_completed, t.archived_at, t.due_date, t.assignee_id,
                t.user_id, t.share_token, t.project_id, s.title AS subject_title
            FROM tasks t LEFT JOIN subjects s ON s.id = t.subject_id
            WHERE t.project_id = ? AND t.blocked_at IS NULL AND t.archived_at IS NULL AND t.is_completed = 0 AND ' . \App\Models\TaskModel::confidentialSql($viewer) . '
            ORDER BY t.id', [$projectId]);
    }

    /**
     * داده‌ی صفحه‌ی «بار کاری» (همان شکل sampleData() در workload.js؛ همه‌ی شناسه‌ها عمومی/pid).
     */
    public static function build(int $projectId, int $viewer): array
    {
        $p = \App\Models\ProjectModel::find($projectId);
        $ppid = pid('projects', $projectId);
        $type = ($p['priority_type'] ?? '') === 'percent' ? 'percent' : 'category';
        $members = [];
        foreach (self::memberRows($projectId) as $m) {
            $members[] = ['id' => pid('users', $m['uid']), 'name' => $m['name'], 'avatar' => $m['avatar'], 'capacity' => $m['capacity'],
                'wip_limit' => $m['wip_limit'], 'can_receive' => $m['can_receive']];
        }
        $tasks = [];
        foreach (self::taskRows($projectId, $viewer) as $t) {
            $st = self::statusMap(BoardService::statusOf($t));
            if (!isset(self::OPEN[$st])) {
                continue;
            }
            $tasks[] = [
                'id' => pid('tasks', $t), 'title' => (string) $t['title'],
                'url' => url('/tasks?task=' . ($t['share_token'] ?: '')),
                'priority' => self::priorityOf($t, $type), 'status' => $st,
                'status_label' => BoardConfig::label($projectId, (string) BoardService::statusOf($t)),
                'due_date' => $t['due_date'] ?: null,
                'assignee_id' => $t['assignee_id'] !== null ? pid('users', (int) $t['assignee_id']) : null,
                'label' => $t['subject_title'] !== null && $t['subject_title'] !== '' ? (string) $t['subject_title'] : null,
            ];
        }
        return [
            'today' => self::today(),
            'project' => ['id' => $ppid, 'title' => (string) ($p['title'] ?? ''), 'board_url' => url('/board?project=' . $ppid),
                'analytics_url' => url('/projects/' . $ppid . '/analytics'), 'workload_url' => url('/projects/' . $ppid . '/workload')],
            'can_manage' => self::canManage($projectId, $viewer),
            'settings' => ['capacity' => self::capacity($projectId)],
            'members' => $members,
            'tasks' => $tasks,
        ];
    }

    /** ظرفیت پیش‌فرض پروژه (۴ تا ۳۰) */
    public static function capacity(int $projectId): int
    {
        $c = (int) \App\Core\DB::value('SELECT workload_capacity FROM projects WHERE id = ?', [$projectId]);
        return $c >= self::CAP_MIN && $c <= self::CAP_MAX ? $c : self::DEFAULTS['capacity'];
    }

    /** خروجی API: داده + loads/suggestions/unresolved محاسبه‌شده در PHP */
    public static function withAnalysis(array $data): array
    {
        $res = self::suggestRebalance($data);
        return $data + ['loads' => self::loadsOut(self::computeLoads($data)), 'suggestions' => $res['suggestions'], 'unresolved' => $res['unresolved']];
    }

    private static function res(bool $ok, string $msg, string $code = '', int $http = 200, $data = null): array
    {
        return ['ok' => $ok, 'message' => $msg, 'code' => $code, 'http' => $http, 'data' => $data];
    }

    /**
     * واگذاری از صفحه‌ی بار کاری: اعتبارسنجی مخصوص همین صفحه، بعد همان TaskService::update
     * (مجوز «تغییر مسئول»/«تغییر زمان»، تاریخچه با source، اعلان به مسئول جدید و قبلی).
     * @param string $source suggestion|drag|undo
     */
    public static function reassign(int $projectId, int $actor, int $taskId, int $assigneeId, ?string $due, bool $hasDue, string $source): array
    {
        if (!in_array($source, ['suggestion', 'drag', 'undo'], true)) {
            return self::res(false, 'منبع جابه‌جایی نامعتبر است.', 'SOURCE_INVALID', 422);
        }
        if (!self::canManage($projectId, $actor)) {
            return self::res(false, 'اجازه‌ی تغییر مسئول تسک‌های این پروژه را ندارید.', 'FORBIDDEN', 403);
        }
        $t = $taskId > 0 ? \App\Models\TaskModel::raw($taskId) : null;
        if ($t === null || (int) $t['project_id'] !== $projectId || !Access::canTask($t, $actor, 'view')) {
            return self::res(false, 'تسک در این پروژه یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        $st = self::statusMap(BoardService::statusOf($t));
        if (in_array($st, ['review', 'done', 'archived'], true) || (int) $t['is_completed'] === 1) {
            return self::res(false, 'تسک‌های در بررسی، انجام‌شده یا آرشیوشده جابه‌جا نمی‌شوند.', 'TASK_NOT_OPEN', 409);
        }
        $members = self::memberRows($projectId);
        if (!isset($members[$assigneeId]) || !$members[$assigneeId]['can_receive']) {
            return self::res(false, 'این کاربر نمی‌تواند در این پروژه تسک بگیرد.', 'ASSIGNEE_INVALID', 422);
        }
        $in = ['assignee_id' => $assigneeId];
        if ($hasDue) {
            $d = $due === null || $due === '' ? null : \App\Core\Validate::date($due);
            if ($due !== null && $due !== '' && $d === null) {
                return self::res(false, 'فرمت تاریخ سررسید نامعتبر است.', 'DUE_DATE_INVALID', 422);
            }
            // فقط «بازگردانی» می‌تواند سررسید گذشته (مقدار قبلی) را برگرداند
            if ($d !== null && $d < self::today() && $source !== 'undo') {
                return self::res(false, 'سررسید جدید نباید قبل از امروز باشد.', 'DUE_DATE_PAST', 422);
            }
            $in['due_date'] = $d ?? '';
        }
        $r = TaskService::update($actor, $taskId, $in, $source);
        if (!$r['ok']) {
            return $r;
        }
        $f = \App\Models\TaskModel::raw($taskId);
        return self::res(true, 'مسئول تسک تغییر کرد.', '', 200, ['task_id' => pid('tasks', $taskId),
            'assignee_id' => $f['assignee_id'] !== null ? pid('users', (int) $f['assignee_id']) : null, 'due_date' => $f['due_date'] ?: null]);
    }

    /** ذخیره‌ی ظرفیت پیش‌فرض پروژه (فقط can_manage) */
    public static function setCapacity(int $projectId, int $actor, $capacity): array
    {
        if (!self::canManage($projectId, $actor)) {
            return self::res(false, 'اجازه‌ی تغییر ظرفیت این پروژه را ندارید.', 'FORBIDDEN', 403);
        }
        if (!is_numeric($capacity) || (int) $capacity != $capacity || (int) $capacity < self::CAP_MIN || (int) $capacity > self::CAP_MAX) {
            return self::res(false, 'ظرفیت باید عددی بین ' . fa_num(self::CAP_MIN) . ' تا ' . fa_num(self::CAP_MAX) . ' باشد.', 'CAPACITY_INVALID', 422);
        }
        \App\Core\DB::run('UPDATE projects SET workload_capacity = ? WHERE id = ?', [(int) $capacity, $projectId]);
        return self::res(true, 'ظرفیت ذخیره شد.', '', 200, ['capacity' => (int) $capacity]);
    }
}
