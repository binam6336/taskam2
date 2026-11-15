<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ProjectModel;
use App\Models\SubjectModel;
use App\Models\TaskModel;
use App\Services\Access;
use App\Services\BoardConfig;
use App\Services\BoardService;

/**
 * بورد پروژه (کانبان) — آدرس /board?project=<شناسه>. از v6.1 هر پروژه بورد خودش را دارد؛
 * بدون پروژه، فهرست پروژه‌ها برای انتخاب نمایش داده می‌شود (منوی «وظایف ← بورد» هم اول پروژه را می‌پرسد).
 */
final class BoardController extends Controller
{
    public function index()
    {
        $this->rateLimit('heavy_board', 60);
        $uid = $this->uid();
        $q = $this->request->query;
        $projects = BoardService::projects($uid);
        $ids = array_map(static fn($p) => (int) $p['id'], $projects);
        $pid = (int) ($q['project'] ?? 0);
        // ?task=<id>: آمدن از جزئیات تسک؛ بورد پروژه‌ی همان تسک و کارتش برجسته
        $focus = (int) \App\Core\Pid::id('tasks', (string) ($q['task'] ?? ''));
        $ft = $focus > 0 ? TaskModel::raw($focus) : null;
        if ($ft === null || !BoardService::canSee($ft, $uid) || empty($ft['project_id'])) {
            if ($focus > 0) {
                $this->flash('این تسک در بورد پروژه شما نمایش داده نمی‌شود.', 'error');
            }
            $focus = 0;
        } elseif ($pid <= 0) {
            $pid = (int) $ft['project_id'];
        }
        $pid = in_array($pid, $ids, true) ? $pid : 0;
        if ($pid === 0) {
            // انتخاب پروژه
            $this->view('board/pick', ['projects' => $this->pickList($uid, $projects)], ['title' => 'بورد پروژه', 'page' => 'board']);
            return;
        }
        $f = ['project' => $pid, 'mine' => !empty($q['mine']), 'q' => trim(mb_substr((string) ($q['q'] ?? ''), 0, 100)), 'focus' => $focus];
        [$columns, $archivable] = BoardService::columns($uid, $f);
        $project = null;
        foreach ($projects as $p) {
            if ((int) $p['id'] === $pid) {
                $project = $p;
            }
        }
        $statuses = BoardConfig::statuses($pid);
        // ستون شروع اسکرول: تأییدکننده‌ها «بررسی سرپرست»، بقیه ستون شروع بورد
        $canApprove = \in_array('complete', Access::projectTaskPerms($pid, $uid), true);
        $this->view('board/index', [
            'columns' => $columns, 'archivable' => $archivable, 'archive' => BoardService::archive($uid, $f),
            'statuses' => $statuses, 'order' => array_merge(array_keys($statuses), ['archived']), 'projects' => $projects, 'project' => $project,
            'filters' => $f, 'over' => BoardService::overLimits($uid, $pid), 'startCol' => $canApprove ? 'review' : BoardConfig::start($pid),
            'canManage' => Access::projectCan($pid, $uid, 'manage_board'), 'customBoard' => !empty(BoardConfig::project($pid)['custom']),
            // پنجره‌های مشاهده و ویرایش تسک داخل خود برد
            'subjects' => SubjectModel::forUser($uid), 'taskProjects' => ProjectModel::forUser($uid), 'assignees' => TaskController::assigneeOptions($uid),
        ], ['title' => 'بورد ' . ($project['title'] ?? 'پروژه'), 'page' => 'board', 'bodyClass' => 'page-board']);
    }

    /** فهرست پروژه‌ها برای انتخاب بورد: تعداد تسک‌های باز و اینکه بورد اختصاصی دارد یا نه */
    private function pickList(int $uid, array $projects): array
    {
        $ids = array_map(static fn($p) => (int) $p['id'], $projects);
        $open = $ids ? array_column(\App\Core\DB::all('SELECT project_id, COUNT(*) n FROM tasks WHERE project_id IN (' . \App\Core\DB::in($ids) . ') AND is_completed = 0 AND blocked_at IS NULL AND archived_at IS NULL GROUP BY project_id', $ids), 'n', 'project_id') : [];
        return array_map(static fn($p) => ['id' => pid('projects', $p), 'title' => (string) $p['title'], 'image' => project_image_url($p['profile_image'] ?? null),
            'open' => (int) ($open[(int) $p['id']] ?? 0), 'url' => url('/board?project=' . pid('projects', $p))], $projects);
    }

    /** پنجره‌ی انتخاب پروژه (منوی بورد در سایدبار) */
    public function projects()
    {
        $uid = $this->uid();
        $this->ok('', ['projects' => $this->pickList($uid, BoardService::projects($uid))]);
    }

    /** فیلترهای برد از query یا بدنه‌ی درخواست (پروژه فقط اگر کاربر عضوش باشد). */
    private function filters(int $uid): array
    {
        $r = $this->request;
        $pid = (int) ($r->query['project'] ?? $r->input('project', 0));
        $ids = array_map(static fn($p) => (int) $p['id'], BoardService::projects($uid));
        if (!in_array($pid, $ids, true)) {
            $this->fail('پروژه یافت نشد.', 404);
        }
        return ['project' => $pid, 'mine' => !empty($r->query['mine'] ?? $r->input('mine')),
            'q' => trim(mb_substr((string) ($r->query['q'] ?? $r->input('q', '')), 0, 100))];
    }

    /** همه‌ی آرشیو (پاپ‌آپ «مشاهده بیشتر») با همان فیلترهای برد */
    public function archive()
    {
        $this->rateLimit('heavy_board_archive', 60);
        $uid = $this->uid();
        $res = BoardService::archive($uid, $this->filters($uid), 0);
        $items = array_map(static fn($t) => [
            'id' => pid('tasks', $t), 'title' => $t['title'], 'url' => url('/tasks?task=' . $t['token']),
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

    /** تنظیمات بورد پروژه (چرخ‌دنده؛ مجوز «مدیریت بورد») */
    public function settings()
    {
        $uid = $this->uid();
        $pid = (int) ($this->request->query['project'] ?? 0);
        if ($pid <= 0 || !Access::projectCan($pid, $uid, 'manage_board')) {
            $this->fail('تنظیمات بورد این پروژه با دارندگان دسترسی «مدیریت بورد» است.', 403);
        }
        $cfg = BoardConfig::project($pid);
        $this->ok('', BoardConfig::editorData($cfg, BoardConfig::counts($pid)) + ['custom' => !empty($cfg['custom'])]);
    }

    public function saveSettings()
    {
        $uid = $this->uid();
        $pid = (int) $this->request->input('project', 0);
        if ($pid <= 0 || !Access::projectCan($pid, $uid, 'manage_board')) {
            $this->fail('تنظیمات بورد این پروژه با دارندگان دسترسی «مدیریت بورد» است.', 403);
        }
        $this->rateLimit('board_settings', 30);
        $cfg = json_decode((string) $this->request->input('config', ''), true);
        $moves = json_decode((string) $this->request->input('moves', ''), true);
        $reset = (string) $this->request->input('reset', '') === '1';
        if (!$reset && !is_array($cfg)) {
            $this->fail('داده‌ی بورد نامعتبر است.', 422);
        }
        [$ok, $msg] = BoardConfig::saveProject($pid, $uid, is_array($cfg) ? $cfg : [], is_array($moves) ? $moves : [], $reset);
        $this->respond($ok, $msg, '/board?project=' . pid('projects', $pid));
    }

    public function move(string $id)
    {
        $res = BoardService::move($this->uid(), (int) $id, $this->request->str('status', 24), $this->request->str('reason', 255));
        $this->respond($res['ok'], $res['message'], '/board', $res['ok'] ? \App\Core\Pid::tag((array) $res['data'], 'tasks') : []);
    }
}
