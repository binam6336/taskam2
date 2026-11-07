<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Pid;
use App\Models\GroupModel;
use App\Models\ProjectModel;
use App\Services\GroupService;

/** مدیریت تیم ← دپارتمان / گروه (v5.10): گروه‌های هر پروژه، ساخت/ویرایش/حذف با مجوز «افزودن کاربر» */
final class GroupController extends Controller
{
    public function index()
    {
        $uid = $this->uid();
        $projects = ProjectModel::forUser($uid);
        $want = (int) ($this->request->query['project'] ?? 0);
        $pids = array_map(static fn($p) => (int) $p['id'], $projects);
        $cur = in_array($want, $pids, true) ? $want : ($pids[0] ?? 0);
        $groups = $cur ? GroupModel::forProject($cur) : [];
        $members = [];
        if ($cur) {
            $p = DB::one('SELECT p.user_id, u.first_name, u.last_name, u.avatar FROM projects p JOIN users u ON u.id = p.user_id WHERE p.id = ?', [$cur]);
            if ($p) {
                $members[] = ['id' => (int) $p['user_id'], 'name' => full_name($p), 'avatar' => avatar_url($p['avatar']), 'creator' => true];
            }
            foreach (ProjectModel::members($cur) as $m) {
                if ((int) $m['user_id'] !== (int) ($p['user_id'] ?? 0)) {
                    $members[] = ['id' => (int) $m['user_id'], 'name' => full_name($m), 'avatar' => avatar_url($m['avatar']), 'creator' => false];
                }
            }
        }
        $canManage = $cur && GroupService::canManage($cur, $uid);
        // داده‌ی فرم ساخت/ویرایش گروه (groups.js)؛ شناسه‌ها عمومی
        $boot = [
            'project' => $cur ? Pid::of('projects', $cur) : null,
            'members' => array_map(static fn($m) => ['id' => Pid::of('users', $m['id']), 'name' => $m['name'], 'avatar' => $m['avatar']], $members),
            'can_manage' => $canManage,
        ];
        $this->view('groups/index', [
            'boot' => $boot, 'projects' => $projects, 'current' => $cur, 'groups' => $groups, 'canManage' => $canManage, 'memberCount' => count($members),
        ], ['title' => 'دپارتمان / گروه', 'page' => 'groups']);
    }

    public function store()
    {
        $r = $this->request;
        $pid = (int) $r->input('project_id', 0);
        $res = GroupService::create($this->uid(), $pid, $this->input());
        $this->respond($res['ok'], $res['message'], '/groups?project=' . Pid::of('projects', $pid));
    }

    public function update(string $id)
    {
        $res = GroupService::update($this->uid(), (int) $id, $this->input());
        $this->respond($res['ok'], $res['message'], '/groups');
    }

    public function delete(string $id)
    {
        $res = GroupService::delete($this->uid(), (int) $id);
        $this->respond($res['ok'], $res['message'], '/groups');
    }

    private function input(): array
    {
        $r = $this->request;
        return ['title' => $r->str('title', 200), 'description' => $r->str('description', 600), 'members' => array_map('intval', $r->arr('members')), 'head_id' => (int) $r->input('head_id', 0)];
    }
}
