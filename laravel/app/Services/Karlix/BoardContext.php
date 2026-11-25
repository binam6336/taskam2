<?php
declare(strict_types=1);

namespace KarlixLaravel\Services\Karlix;

use KarlixLaravel\Database\DB;
use App\Core\Search;
use KarlixLaravel\Repositories\SubjectModel;
use KarlixLaravel\Repositories\TaskModel;
use KarlixLaravel\Services\Access;
use KarlixLaravel\Services\BoardConfig;
use KarlixLaravel\Services\BoardService;

/**
 * زمینه‌ی یک گفتگوی دستیار بورد (v7.20): کاربر (همان کاربری که پنل را می‌بیند؛ در ورود مدیر = کاربر مشاهده‌شده)،
 * پروژه، فیلترهای فعال بورد، ارجاع‌های کوتاه (T/U/L) و «دنیای تسک‌ها» = فقط تسک‌هایی که کاربر در همین بورد می‌بیند.
 * هیچ داده‌ای بیرون از این دنیا به مدل یا رابط نمی‌رسد.
 */
final class BoardContext
{
    public int $uid;
    public ?int $realUid;
    public int $projectId;
    public array $project;
    public array $session;
    /** فیلترهای فعال بورد: mine (bool)، q (متن جستجو) */
    public array $filters = ['mine' => false, 'q' => ''];
    /** انتخاب کاربر در جدول‌ها: ['set' => ?int (شناسه‌ی ai_result_sets), 'tasks' => int[]] */
    public array $selection = ['set' => null, 'tasks' => []];
    /** @var array{t:int[],u:int[],l:int[]} */
    private array $refs;
    private ?array $universe = null;
    private ?array $members = null;
    private ?array $labels = null;
    private bool $dirty = false;

    public function __construct(int $uid, ?int $realUid, array $project, array $session)
    {
        $this->uid = $uid;
        $this->realUid = $realUid;
        $this->project = $project;
        $this->projectId = (int) $project['id'];
        $this->session = $session;
        $r = json_decode((string) ($session['refs'] ?? ''), true);
        $this->refs = ['t' => array_map('intval', $r['t'] ?? []), 'u' => array_map('intval', $r['u'] ?? []), 'l' => array_map('intval', $r['l'] ?? [])];
    }

    // ───────── ارجاع‌های کوتاه (فقط سرور نگاشت را می‌داند) ─────────

    public function ref(string $kind, int $id): string
    {
        $i = array_search($id, $this->refs[$kind], true);
        if ($i === false) {
            $this->refs[$kind][] = $id;
            $i = count($this->refs[$kind]) - 1;
            $this->dirty = true;
        }
        return strtoupper($kind) . ($i + 1);
    }

    /** ارجاع مدل ⇐ شناسه‌ی واقعی (یا null)؛ فقط ارجاع‌هایی که خود سرور ساخته */
    public function idOf(string $kind, $ref): ?int
    {
        // v7.22: مدل گاهی ارجاع را به شکل توکن می‌فرستد («[[task:T3]]»)
        if (is_string($ref) && preg_match('/^\[\[\s*[a-z]+\s*:\s*([A-Za-z]\d{1,5})\b/u', trim($ref), $t)) {
            $ref = $t[1];
        }
        if (!is_string($ref) || !preg_match('/^' . strtoupper($kind) . '(\d{1,5})$/i', trim($ref), $m)) {
            return null;
        }
        return $this->refs[$kind][(int) $m[1] - 1] ?? null;
    }

    public function saveRefs(): void
    {
        if ($this->dirty) {
            DB::run('UPDATE ai_board_sessions SET refs = ? WHERE id = ?', [json_encode($this->refs), (int) $this->session['id']]);
            $this->dirty = false;
        }
    }

    // ───────── اعضا، لیبل‌ها، ستون‌ها ─────────

    /** اعضای فعال پروژه (سازنده + اعضا، بدون ربات): id ⇒ name */
    public function members(): array
    {
        if ($this->members === null) {
            $rows = DB::all("SELECT u.id, u.first_name, u.last_name FROM users u WHERE u.status = 'active' AND u.is_bot = 0
                AND (u.id = (SELECT user_id FROM projects WHERE id = ?) OR u.id IN (SELECT user_id FROM project_members WHERE project_id = ?)) ORDER BY u.id", [$this->projectId, $this->projectId]);
            $this->members = [];
            foreach ($rows as $u) {
                $n = trim($u['first_name'] . ' ' . $u['last_name']);
                $this->members[(int) $u['id']] = $n !== '' ? $n : 'کاربر بدون نام';
            }
        }
        return $this->members;
    }

    /** لیبل‌های پروژه: id ⇒ [title, color] */
    public function labels(): array
    {
        if ($this->labels === null) {
            $this->labels = [];
            foreach (SubjectModel::forProject($this->projectId) as $s) {
                $this->labels[(int) $s['id']] = [(string) $s['title'], (string) ($s['color'] ?? '')];
            }
        }
        return $this->labels;
    }

    /** ستون‌ها: key ⇒ label */
    public function columns(): array
    {
        $out = [];
        foreach (BoardConfig::statuses($this->projectId) as $k => $c) {
            $out[$k] = (string) $c['label'];
        }
        return $out;
    }

    public function name(?int $uid): ?string
    {
        if ($uid === null) {
            return null;
        }
        return $this->members()[$uid] ?? (string) (($u = DB::one('SELECT first_name, last_name FROM users WHERE id = ?', [$uid])) ? trim($u['first_name'] . ' ' . $u['last_name']) : '');
    }

    // ───────── دنیای تسک‌ها ─────────

    /**
     * همه‌ی تسک‌های پروژه که کاربر در بورد می‌بیند (بدون مسدودشده‌ها؛ آرشیوشده‌ها هم هستند با status = archived).
     * @return array<int,array> id ⇒ ردیف با status (کلید ستون)، overdue، progress، last_activity
     */
    public function universe(): array
    {
        if ($this->universe !== null) {
            return $this->universe;
        }
        $rows = DB::all('SELECT t.*, s.title AS label_title, s.color AS label_color,
                (SELECT MAX(n.created_at) FROM task_notes n WHERE n.task_id = t.id) AS last_note_at,
                (SELECT MAX(e.created_at) FROM task_events e WHERE e.task_id = t.id) AS last_event_at
            FROM tasks t LEFT JOIN subjects s ON s.id = t.subject_id
            WHERE t.project_id = ? AND t.blocked_at IS NULL AND ' . TaskModel::BOARD_NON_RECURRING . ' AND ' . TaskModel::confidentialSql($this->uid) . '
            ORDER BY t.id DESC LIMIT 5000', [$this->projectId]);
        $today = Common::today()->format('Y-m-d');
        $out = [];
        foreach ($rows as $t) {
            if (!Access::canBoardView($t, $this->uid)) {
                continue;
            }
            $st = BoardService::statusOf($t);
            $t['status'] = $st;
            $t['done'] = $st === 'done' || $st === 'archived';
            $t['overdue'] = !$t['done'] && $st !== 'review' && $t['due_date'] !== null && $t['due_date'] < $today;
            $step = BoardService::stepOf($this->projectId, $st);
            $t['progress'] = $t['done'] ? 100 : ($step['steps'] > 0 ? (int) round(100 * $step['step'] / $step['steps']) : 0);
            $t['last_activity'] = max((string) $t['updated_at'], (string) $t['last_note_at'], (string) $t['last_event_at']);
            $out[(int) $t['id']] = $t;
        }
        return $this->universe = $out;
    }

    /** بعد از تغییر تسک‌ها دوباره خوانده شود */
    public function refresh(): void
    {
        $this->universe = null;
    }

    public function task(int $id): ?array
    {
        return $this->universe()[$id] ?? null;
    }

    /** شناسه‌هایی که فیلترهای فعال بورد (فقط مسئولیت من، جستجو) را دارند */
    public function boardFilterIds(): ?array
    {
        $mine = !empty($this->filters['mine']);
        $q = (string) ($this->filters['q'] ?? '');
        if (!$mine && !Search::tokens($q)) {
            return null;
        }
        $ids = array_keys($this->universe());
        if ($mine) {
            $ids = array_values(array_filter($ids, fn($id) => (int) $this->universe[$id]['assignee_id'] === $this->uid));
        }
        if (Search::tokens($q) && $ids) {
            [$qs, $qp] = Search::sql(['t.title', 't.description'], $q);
            $ids = array_map('intval', DB::column('SELECT t.id FROM tasks t WHERE t.id IN (' . DB::in($ids) . ') AND ' . $qs, array_merge($ids, $qp)));
        }
        return $ids;
    }

    // ───────── نمایش ─────────

    public function dueText(array $t): ?string
    {
        if (empty($t['due_date'])) {
            return null;
        }
        return jdate($t['due_date'] . ' 00:00:00', false, true) . (!empty($t['due_time']) ? ' ساعت ' . fa_num(substr((string) $t['due_time'], 0, 5)) : '');
    }

    /** ردیف جدول برای رابط (فقط شناسه‌های عمومی) */
    public function row(array $t): array
    {
        $cols = $this->columns();
        $info = BoardConfig::info($this->projectId, $t['status'] === 'archived' ? 'done' : $t['status']);
        return [
            'id' => pid('tasks', (int) $t['id']), 'tok' => $t['share_token'] ?: TaskModel::ensureShareToken((int) $t['id']),
            'title' => (string) $t['title'], 'col' => $t['status'] === 'archived' ? 'آرشیو' : ($cols[$t['status']] ?? $t['status']),
            'col_key' => $t['status'], 'tone' => (string) ($info['tone'] ?? ''),
            'assignee' => $t['assignee_id'] !== null ? $this->name((int) $t['assignee_id']) : null,
            'assignee_id' => $t['assignee_id'] !== null ? pid('users', (int) $t['assignee_id']) : null,
            'due' => $this->dueText($t), 'overdue' => (bool) $t['overdue'], 'progress' => (int) $t['progress'],
            'labels' => $t['subject_id'] !== null ? [['id' => pid('subjects', (int) $t['subject_id']), 'title' => (string) $t['label_title'], 'color' => (string) $t['label_color']]] : [],
            'priority' => (string) $t['priority'],
        ];
    }

    /** خلاصه‌ی تسک برای مدل (بدون شناسه‌ی واقعی) */
    public function brief(array $t): array
    {
        $cols = $this->columns();
        return array_filter([
            'ref' => $this->ref('t', (int) $t['id']), 'title' => mb_substr((string) $t['title'], 0, 120),
            'column' => $t['status'] === 'archived' ? 'archived' : ($cols[$t['status']] ?? $t['status']),
            'assignee' => $t['assignee_id'] !== null ? $this->ref('u', (int) $t['assignee_id']) . ' ' . $this->name((int) $t['assignee_id']) : null,
            'due' => $t['due_date'] ? $t['due_date'] . (!empty($t['due_time']) ? ' ' . substr((string) $t['due_time'], 0, 5) : '') : null,
            'due_jalali' => $t['due_date'] ? $this->dueText($t) : null,
            'overdue' => $t['overdue'] ? true : null, 'priority' => (string) $t['priority'],
            'label' => $t['subject_id'] !== null ? (string) $t['label_title'] : null,
        ], static fn($v) => $v !== null);
    }
}
