<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Models\TaskModel;
use App\Services\BoardService;

/** برد تیمی (کانبان): ستون‌ها، جابه‌جایی و آرشیو — با همان دسترسی‌ها و قواعد پنل */
final class BoardApi extends ApiController
{
    /** تسک + فیلدهای برد؛ allowed_moves = ستون‌هایی که کاربر اجازه‌ی انتقال کارت به آن‌ها را دارد */
    public static function card(array $t): array
    {
        return TaskApi::format($t) + [
            'blocked_reason' => $t['blocked_reason'] ?? null, 'board_moved_at' => $t['board_moved_at'] ?? null,
            'archived_at' => $t['archived_at'] ?? null, 'allowed_moves' => array_values($t['moves'] ?? []),
        ];
    }

    /** فیلترها از query یا بدنه (پروژه فقط اگر کاربر عضوش باشد) */
    private function filters(): array
    {
        $r = $this->request;
        $pid = (int) ($r->query['project_id'] ?? $r->input('project_id', 0));
        if ($pid > 0 && !in_array($pid, array_map(static fn($p) => (int) $p['id'], BoardService::projects($this->uid)), true)) {
            $this->err('پروژه یافت نشد یا عضو آن نیستید.', 404, 'PROJECT_NOT_FOUND');
        }
        $mine = $r->query['mine'] ?? $r->input('mine', 0);
        return ['project' => $pid, 'mine' => in_array((string) $mine, ['1', 'true'], true), 'q' => trim(mb_substr((string) ($r->query['q'] ?? $r->input('q', '')), 0, 100))];
    }

    private static function statuses(): array
    {
        $out = [];
        foreach (BoardService::STATUSES as $k => $s) {
            $out[] = ['key' => $k, 'label' => $s['label'], 'en' => $s['en']];
        }
        $out[] = ['key' => 'archived', 'label' => 'آرشیو', 'en' => 'Archived'];
        return $out;
    }

    public function list()
    {
        $f = $this->filters();
        [$cols, $archivable] = BoardService::columns($this->uid, $f);
        $archive = BoardService::archive($this->uid, $f);
        $columns = [];
        foreach ($cols as $k => $cards) {
            $columns[$k] = array_map([self::class, 'card'], $cards);
        }
        $this->ok(['filters' => ['project_id' => $f['project'] ?: null, 'mine' => $f['mine'], 'q' => $f['q']], 'statuses' => self::statuses(),
            'columns' => $columns, 'archivable' => $archivable, 'archive_total' => $archive['total']]);
    }

    public function archive()
    {
        [$limit, $offset] = $this->paging();
        $res = BoardService::archive($this->uid, $this->filters(), 0);
        $rows = array_map([self::class, 'card'], array_slice($res['cards'], $offset, $limit));
        $this->ok(['total' => $res['total'], 'count' => count($rows), 'limit' => $limit, 'offset' => $offset, 'tasks' => $rows]);
    }

    public function move()
    {
        $id = $this->id();
        $res = BoardService::move($this->uid, $id, trim((string) $this->request->input('status', '')), (string) $this->request->input('reason', ''));
        $this->fromService($res, function () use ($id) {
            $t = TaskModel::find($id, $this->uid);
            return $t ? self::card($t) : ['id' => $id];
        });
    }

    public function archiveDone()
    {
        $this->fromService(BoardService::archiveDone($this->uid, $this->filters()));
    }
}
