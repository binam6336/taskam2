<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\BoardService;

/** برد تیمی (کانبان) — نسخه‌ی دمو؛ هنوز در منو نیست و از آدرس /board در دسترس است. */
final class BoardController extends Controller
{
    public function index()
    {
        $uid = $this->uid();
        $q = $this->request->query;
        $projects = BoardService::projects($uid);
        $pid = (int) ($q['project'] ?? 0);
        $pid = in_array($pid, array_map(static fn($p) => (int) $p['id'], $projects), true) ? $pid : 0;
        $f = ['project' => $pid, 'mine' => !empty($q['mine']), 'q' => trim(mb_substr((string) ($q['q'] ?? ''), 0, 100))];
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
