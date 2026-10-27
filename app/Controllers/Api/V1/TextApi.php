<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\DB;
use App\Models\TextModel;

final class TextApi extends ApiController
{
    private function tid(): string
    {
        $id = (string) ($this->request->query['id'] ?? $this->request->input('id', ''));
        if (!preg_match('/^[A-Za-z0-9_]{1,50}$/', $id)) {
            $this->err('پارامتر id الزامی یا نامعتبر است.', 400, 'INVALID_ID');
        }
        return $id;
    }

    private static function format(array $t): array
    {
        return ['id' => $t['id'], 'title' => $t['title'], 'content' => $t['content'], 'sort_order' => (int) $t['sort_order'], 'created_at' => $t['created_at'], 'updated_at' => $t['updated_at']];
    }

    public function list()
    {
        $q = trim((string) ($this->request->query['q'] ?? ''));
        [$limit, $offset] = $this->paging();
        $rows = array_slice(TextModel::forUser($this->uid, $q), $offset, $limit);
        $this->ok(['count' => count($rows), 'limit' => $limit, 'offset' => $offset, 'query' => $q, 'texts' => array_map([self::class, 'format'], $rows)]);
    }

    public function show()
    {
        $t = TextModel::owned($this->tid(), $this->uid);
        $t === null ? $this->err('متن یافت نشد.', 404, 'TEXT_NOT_FOUND') : $this->ok(self::format($t));
    }

    public function stats()
    {
        $r = DB::one('SELECT COUNT(*) total, COALESCE(SUM(DATE(created_at) = CURDATE()),0) today, COALESCE(SUM(DATE(updated_at) = CURDATE()),0) updated_today FROM texts WHERE user_id = ?', [$this->uid]) ?? [];
        $this->ok(['total' => (int) ($r['total'] ?? 0), 'today' => (int) ($r['today'] ?? 0), 'updated_today' => (int) ($r['updated_today'] ?? 0)]);
    }

    public function create()
    {
        $in = $this->body();
        $title = trim((string) ($in['title'] ?? ''));
        $content = trim((string) ($in['content'] ?? ''));
        if ($title === '' || $content === '') {
            $this->err('عنوان و متن الزامی هستند.', 400, 'FIELDS_REQUIRED');
        }
        if (mb_strlen($title) > 255 || mb_strlen($content) > 20000) {
            $this->err('عنوان یا متن بیش از حد طولانی است.', 400, 'TOO_LONG');
        }
        $row = TextModel::create($this->uid, $title, $content);
        if (isset($in['sort_order']) && is_numeric($in['sort_order'])) {
            DB::run('UPDATE texts SET sort_order = ? WHERE id = ? AND user_id = ?', [(int) $in['sort_order'], $row['id'], $this->uid]);
            $row = TextModel::owned($row['id'], $this->uid) ?? $row;
        }
        $this->ok(self::format($row), 'متن با موفقیت ایجاد شد.');
    }

    public function update()
    {
        $id = $this->tid();
        $t = TextModel::owned($id, $this->uid);
        if ($t === null) {
            $this->err('متن یافت نشد.', 404, 'TEXT_NOT_FOUND');
        }
        $in = $this->body();
        $title = trim((string) ($in['title'] ?? $t['title']));
        $content = trim((string) ($in['content'] ?? $t['content']));
        if ($title === '' || $content === '' || mb_strlen($title) > 255 || mb_strlen($content) > 20000) {
            $this->err('عنوان یا متن نامعتبر است.', 400, 'FIELDS_INVALID');
        }
        $set = ['title' => $title, 'content' => $content];
        if (isset($in['sort_order']) && is_numeric($in['sort_order'])) {
            $set['sort_order'] = (int) $in['sort_order'];
        }
        DB::update('texts', $set, 'id = ? AND user_id = ?', [$id, $this->uid]);
        $this->ok(self::format(TextModel::owned($id, $this->uid) ?? []), 'متن با موفقیت ویرایش شد.');
    }

    public function delete()
    {
        $id = $this->tid();
        if (TextModel::owned($id, $this->uid) === null) {
            $this->err('متن یافت نشد.', 404, 'TEXT_NOT_FOUND');
        }
        DB::run('DELETE FROM texts WHERE id = ? AND user_id = ?', [$id, $this->uid]);
        $this->ok(['id' => $id], 'متن حذف شد.');
    }
}
