<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Services\BlockService;

/**
 * مسدود کردن به‌جای حذف (v4.0): تسک، پروژه، لیبل و درخواست تماس حذف نمی‌شوند، مسدود می‌شوند.
 * هر منبع سه آدرس دارد: …/block و …/unblock (با id) و فهرست مسدودشده‌ها (GET).
 * پروژه: /projects/block-project، /projects/unblock-project، /projects/blocked-projects
 * (آدرس /projects/block از قبل برای «مسدود کردن دعوت‌های یک پروژه» است).
 */
final class BlockApi extends ApiController
{
    private const TABLES = ['task' => 'tasks', 'project' => 'projects', 'label' => 'subjects', 'call' => 'call_requests'];

    private function doSet(string $kind, bool $on)
    {
        $id = $this->id(self::TABLES[$kind]);
        $this->fromService($on ? BlockService::block($kind, $this->uid, $id) : BlockService::unblock($kind, $this->uid, $id), static fn($d) => \App\Core\Pid::tag((array) $d, self::TABLES[$kind]));
    }

    private function doList(string $kind)
    {
        [$limit, $offset] = $this->paging();
        $r = BlockService::list($kind, $this->uid, $limit, $offset);
        $items = array_map(static fn($i) => ['id' => $i['id'], 'title' => $i['title'], 'subtitle' => $i['sub'] !== '' ? $i['sub'] : null, 'blocked_at' => $i['blocked_at'],
            'blocked_by_name' => $i['blocked_by_name'] !== '' ? $i['blocked_by_name'] : null, 'can_unblock' => $i['can_unblock']], $r['items']);
        $this->ok(['count' => $r['total'], 'limit' => $limit, 'offset' => $offset, 'blocked' => $items]);
    }

    public function taskBlock() { $this->doSet('task', true); }
    public function taskUnblock() { $this->doSet('task', false); }
    public function taskList() { $this->doList('task'); }
    public function projectBlock() { $this->doSet('project', true); }
    public function projectUnblock() { $this->doSet('project', false); }
    public function projectList() { $this->doList('project'); }
    public function labelBlock() { $this->doSet('label', true); }
    public function labelUnblock() { $this->doSet('label', false); }
    public function labelList() { $this->doList('label'); }
    public function callBlock() { $this->doSet('call', true); }
    public function callUnblock() { $this->doSet('call', false); }
    public function callList() { $this->doList('call'); }
}
