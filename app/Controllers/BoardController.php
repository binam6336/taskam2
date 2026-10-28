<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\TaskModel;
use App\Services\BoardService;

/** برد تیمی (کانبان) — نسخه‌ی دمو؛ هنوز در منو نیست و از آدرس /board در دسترس است. */
final class BoardController extends Controller
{
    public function index()
    {
        $uid = $this->uid();
        $q = $this->request->query;
        $projects = BoardService::projects($uid);
        $ids = array_map(static fn($p) => (int) $p['id'], $projects);
        $pid = (int) ($q['project'] ?? 0);
        // ?task=<id>: آمدن از جزئیات تسک؛ برد پروژه‌ی همان تسک باز می‌شود و کارتش برجسته است
        $focus = (int) ($q['task'] ?? 0);
        $ft = $focus > 0 ? TaskModel::raw($focus) : null;
        if ($ft === null || !BoardService::canSee($ft, $uid)) {
            if ($focus > 0) {
                $this->flash('این تسک در برد تیمی شما نمایش داده نمی‌شود.', 'error');
            }
            $focus = 0;
        } elseif (!isset($q['project']) && $ft['project_id'] !== null) {
            $pid = (int) $ft['project_id'];
        }
        $pid = in_array($pid, $ids, true) ? $pid : 0;
        $f = ['project' => $pid, 'mine' => !empty($q['mine']) && $focus === 0, 'q' => $focus > 0 ? '' : trim(mb_substr((string) ($q['q'] ?? ''), 0, 100)), 'focus' => $focus];
        $this->view('board/index', [
            'columns' => BoardService::columns($uid, $f), 'statuses' => BoardService::STATUSES,
            'projects' => $projects, 'filters' => $f,
        ], ['title' => 'برد تیمی', 'page' => 'board', 'bodyClass' => 'page-board']);
    }

    public function move(string $id)
    {
        $res = BoardService::move($this->uid(), (int) $id, $this->request->str('status', 20), $this->request->str('reason', 255));
        $this->respond($res['ok'], $res['message'], '/board', $res['ok'] ? (array) $res['data'] : []);
    }
}
