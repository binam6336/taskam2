<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Validate;
use App\Models\CallModel;
use App\Services\Access;
use App\Services\CallService;

final class CallController extends Controller
{
    private const PER_PAGE = 12;

    public function index()
    {
        $uid = $this->uid();
        $r = $this->request;
        $tab = ($r->query['tab'] ?? 'new') === 'done' ? 'done' : 'new';
        $page = max(1, (int) ($r->query['page'] ?? 1));
        $q = trim((string) ($r->query['q'] ?? ''));
        $res = CallModel::paginate($uid, $tab === 'done' ? 1 : 0, self::PER_PAGE, ($page - 1) * self::PER_PAGE, $q);
        foreach ($res['rows'] as &$row) {
            $row['can'] = ['edit' => Access::can($row, $uid, 'edit'), 'reassign' => Access::can($row, $uid, 'reassign'), 'complete' => Access::can($row, $uid, 'complete'), 'delete' => Access::can($row, $uid, 'delete')];
        }
        unset($row);

        $shared = null;
        $tok = Validate::shareToken((string) ($r->query['call'] ?? ''));
        if ($tok !== null) {
            $c = CallModel::findByToken($tok);
            if ($c !== null && (Access::can($c, $uid, 'view') || Access::isColleague((int) $c['user_id'], $uid))) {
                $c['can'] = ['edit' => Access::can($c, $uid, 'edit'), 'reassign' => Access::can($c, $uid, 'reassign'), 'complete' => Access::can($c, $uid, 'complete')];
                $shared = $c;
            } else {
                $this->flash('لینک درخواست نامعتبر است یا دسترسی ندارید.', 'error');
            }
        }
        $counts = [
            'new' => (int) DB::value('SELECT COUNT(*) FROM call_requests WHERE (user_id = ? OR assignee_id = ?) AND status = 0', [$uid, $uid]),
            'done' => (int) DB::value('SELECT COUNT(*) FROM call_requests WHERE (user_id = ? OR assignee_id = ?) AND status = 1', [$uid, $uid]),
        ];
        $assignees = DB::all("SELECT u.id, u.first_name, u.last_name, u.mobile FROM colleagues c INNER JOIN users u ON u.id = c.colleague_user_id WHERE c.user_id = ? AND u.status = 'active' ORDER BY u.first_name", [$uid]);
        $this->view('calls/index', [
            'calls' => $res['rows'], 'total' => $res['total'], 'tab' => $tab, 'pg' => $page, 'pages' => max(1, (int) ceil($res['total'] / self::PER_PAGE)),
            'q' => $q, 'counts' => $counts, 'shared' => $shared,
            'assignees' => array_map(static fn($u) => ['id' => (int) $u['id'], 'name' => full_name($u)], $assignees),
        ], ['title' => 'درخواست‌های تماس', 'page' => 'calls']);
    }

    public function store()
    {
        $res = CallService::create($this->uid(), $this->request->all());
        $this->respond($res['ok'], $res['message'], '/calls');
    }

    public function update(string $id)
    {
        $res = CallService::update($this->uid(), (int) $id, $this->request->all());
        $this->respond($res['ok'], $res['message'], '/calls');
    }

    public function complete(string $id)
    {
        $res = CallService::complete($this->uid(), (int) $id);
        $this->respond($res['ok'], $res['message'], '/calls');
    }

    public function destroy(string $id)
    {
        $res = CallService::delete($this->uid(), (int) $id);
        $this->respond($res['ok'], $res['message'], '/calls');
    }

    public function share(string $id)
    {
        $uid = $this->uid();
        $c = CallModel::find((int) $id);
        if ($c === null || !Access::can($c, $uid, 'view')) {
            $this->fail('درخواست یافت نشد.', 404);
        }
        $tok = CallModel::ensureToken((int) $id);
        $this->ok('', ['token' => $tok, 'url' => full_url('calls?call=' . $tok)]);
    }
}
