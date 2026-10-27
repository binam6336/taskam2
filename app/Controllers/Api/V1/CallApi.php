<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\DB;
use App\Models\CallModel;
use App\Services\Access;
use App\Services\CallService;

final class CallApi extends ApiController
{
    public static function format(array $c): array
    {
        return [
            'id' => (int) $c['id'], 'user_id' => (int) $c['user_id'], 'assignee_id' => $c['assignee_id'] !== null ? (int) $c['assignee_id'] : null,
            'first_name' => $c['first_name'], 'last_name' => $c['last_name'], 'mobile' => $c['mobile'], 'email' => $c['email'],
            'store' => $c['store'], 'website' => $c['website'], 'description' => $c['description'] ?? null,
            'status' => (int) $c['status'], 'is_done' => (int) $c['status'] === 1, 'created_at' => $c['created_at'], 'completed_at' => $c['completed_at'],
            'assignee_first_name' => $c['assignee_first_name'] ?? null, 'assignee_last_name' => $c['assignee_last_name'] ?? null, 'assignee_mobile' => $c['assignee_mobile'] ?? null,
            'creator_first_name' => $c['creator_first_name'] ?? null, 'creator_last_name' => $c['creator_last_name'] ?? null, 'creator_mobile' => $c['creator_mobile'] ?? null,
        ];
    }

    public function list()
    {
        $q = $this->request->query;
        $status = in_array($q['status'] ?? 'all', ['all', 'new', 'done'], true) ? $q['status'] ?? 'all' : 'all';
        $assignee = ($q['assignee'] ?? 'all') === 'me' ? 'me' : 'all';
        [$limit, $offset] = $this->paging();
        $rows = CallModel::apiList($this->uid, $status, (string) ($q['q'] ?? ''), $limit, $offset);
        if ($assignee === 'me') {
            $rows = array_values(array_filter($rows, fn($r) => (int) $r['assignee_id'] === $this->uid));
        }
        $this->ok(['count' => count($rows), 'limit' => $limit, 'offset' => $offset, 'status' => $status, 'assignee' => $assignee, 'calls' => array_map([self::class, 'format'], $rows)]);
    }

    public function show()
    {
        $c = CallModel::find($this->id());
        if ($c === null || !Access::can($c, $this->uid, 'view')) {
            $this->err('درخواست یافت نشد.', 404, 'CALL_NOT_FOUND');
        }
        $this->ok(self::format($c));
    }

    public function stats()
    {
        $r = DB::one('SELECT COUNT(*) total, COALESCE(SUM(status = 0),0) new_count, COALESCE(SUM(status = 1),0) done_count, COALESCE(SUM(DATE(created_at) = CURDATE()),0) today, COALESCE(SUM(assignee_id IS NULL),0) unassigned FROM call_requests WHERE user_id = ? OR assignee_id = ?', [$this->uid, $this->uid]) ?? [];
        $this->ok(['total' => (int) ($r['total'] ?? 0), 'new' => (int) ($r['new_count'] ?? 0), 'done' => (int) ($r['done_count'] ?? 0), 'today' => (int) ($r['today'] ?? 0), 'unassigned' => (int) ($r['unassigned'] ?? 0)]);
    }

    public function create()
    {
        $this->fromService(CallService::create($this->uid, $this->body()), fn($c) => self::format(CallModel::find((int) $c['id']) ?? $c));
    }

    public function update()
    {
        $in = $this->body();
        unset($in['id']);
        $id = $this->id();
        $this->fromService(CallService::update($this->uid, $id, $in), fn($c) => self::format(CallModel::find((int) $c['id']) ?? $c));
    }

    public function status()
    {
        $id = $this->id();
        $c = CallModel::find($id);
        if ($c === null || !Access::can($c, $this->uid, 'view') && !Access::can($c, $this->uid, 'complete')) {
            $this->err('درخواست یافت نشد.', 404, 'CALL_NOT_FOUND');
        }
        if (!Access::can($c, $this->uid, 'complete')) {
            $this->err('دسترسی ندارید.', 403, 'FORBIDDEN');
        }
        $in = $this->body();
        $new = array_key_exists('status', $in) ? ((int) $in['status'] === 1 ? 1 : 0) : ((int) $c['status'] === 1 ? 0 : 1);
        DB::run('UPDATE call_requests SET status = ?, completed_at = ' . ($new === 1 ? 'NOW()' : 'NULL') . ' WHERE id = ?', [$new, $id]);
        $this->ok(['id' => $id, 'status' => $new, 'is_done' => $new === 1, 'completed_at' => $new === 1 ? date('Y-m-d H:i:s') : null], 'وضعیت درخواست تغییر کرد.');
    }

    public function delete()
    {
        $this->fromService(CallService::delete($this->uid, $this->id()), static fn($d) => ['id' => $d['id']]);
    }
}
