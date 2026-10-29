<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ProjectModel;
use App\Models\SubjectModel;
use App\Models\TaskModel;
use App\Services\BoardService;

/** برد تیمی (کانبان) — آدرس /board؛ نمایشش در سایدبار از «محتوا ← سایدبار» در پنل ادمین. */
final class BoardController extends Controller
{
    public function index()
    {
        $uid = $this->uid();
        $q = $this->request->query;
        $projects = BoardService::projects($uid);
        $ids = array_map(static fn($p) => (int) $p['id'], $projects);
        $pid = (int) ($q['project'] ?? 0);
        // ?task=<id>: آمدن از جزئیات تسک؛ برد کامل (بدون تغییر فیلترها) و فقط کارت همان تسک برجسته می‌شود
        $focus = (int) ($q['task'] ?? 0);
        $ft = $focus > 0 ? TaskModel::raw($focus) : null;
        if ($ft === null || !BoardService::canSee($ft, $uid)) {
            if ($focus > 0) {
                $this->flash('این تسک در برد تیمی شما نمایش داده نمی‌شود.', 'error');
            }
            $focus = 0;
        }
        $pid = in_array($pid, $ids, true) ? $pid : 0;
        $f = ['project' => $pid, 'mine' => !empty($q['mine']), 'q' => trim(mb_substr((string) ($q['q'] ?? ''), 0, 100)), 'focus' => $focus];
        [$columns, $archivable] = BoardService::columns($uid, $f);
        $this->view('board/index', [
            'columns' => $columns, 'archivable' => $archivable, 'archive' => BoardService::archive($uid, $f),
            'statuses' => BoardService::STATUSES, 'projects' => $projects, 'filters' => $f, 'over' => BoardService::overLimits($uid, $pid),
            // پنجره‌های مشاهده و ویرایش تسک داخل خود برد
            'subjects' => SubjectModel::forUser($uid), 'taskProjects' => ProjectModel::forUser($uid), 'assignees' => TaskController::assigneeOptions($uid),
        ], ['title' => 'برد تیمی', 'page' => 'board', 'bodyClass' => 'page-board']);
    }

    /** فیلترهای برد از query یا بدنه‌ی درخواست (پروژه فقط اگر کاربر عضوش باشد). */
    private function filters(int $uid): array
    {
        $r = $this->request;
        $pid = (int) ($r->query['project'] ?? $r->input('project', 0));
        $ids = array_map(static fn($p) => (int) $p['id'], BoardService::projects($uid));
        return ['project' => in_array($pid, $ids, true) ? $pid : 0, 'mine' => !empty($r->query['mine'] ?? $r->input('mine')),
            'q' => trim(mb_substr((string) ($r->query['q'] ?? $r->input('q', '')), 0, 100))];
    }

    /** همه‌ی آرشیو (پاپ‌آپ «مشاهده بیشتر») با همان فیلترهای برد */
    public function archive()
    {
        $uid = $this->uid();
        $res = BoardService::archive($uid, $this->filters($uid), 0);
        $items = array_map(static fn($t) => [
            'id' => (int) $t['id'], 'title' => $t['title'], 'url' => url('/tasks?task=' . $t['token']),
            'project' => $t['project_title'], 'assignee' => $t['assignee_id'] ? full_name($t, 'assignee_') : null,
            'assignee_avatar' => $t['assignee_id'] ? avatar_url($t['assignee_avatar']) : null,
            'archived_fa' => jdate((string) $t['archived_at']), 'completed_fa' => $t['completed_at'] ? jdate((string) $t['completed_at']) : null,
            'can_restore' => in_array('done', $t['moves'], true),
        ], $res['cards']);
        $this->ok('', ['total' => $res['total'], 'items' => $items]);
    }

    /** آیکون آرشیو ستون «انجام‌شده»: همه‌ی انجام‌شده‌های برد (با فیلترهای فعلی) به آرشیو */
    public function archiveDone()
    {
        $uid = $this->uid();
        $res = BoardService::archiveDone($uid, $this->filters($uid));
        $this->respond($res['ok'], $res['message'], '/board', $res['ok'] ? (array) $res['data'] : []);
    }

    public function move(string $id)
    {
        $res = BoardService::move($this->uid(), (int) $id, $this->request->str('status', 20), $this->request->str('reason', 255));
        $this->respond($res['ok'], $res['message'], '/board', $res['ok'] ? (array) $res['data'] : []);
    }
}
