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
        $pids = array_map(static fn($p) => (int) $p['id'], $projects);
        // فیلتر پروژه (خالی = همه‌ی پروژه‌های من) و جستجو در نام گروه
        $want = (int) ($this->request->query['project'] ?? 0);
        $cur = in_array($want, $pids, true) ? $want : 0;
        $q = mb_substr(trim((string) ($this->request->query['q'] ?? '')), 0, 80);
        $groups = GroupModel::forProjects($cur ? [$cur] : $pids, $q);
        $manage = $analytics = $assign = [];
        foreach ($pids as $pid) {
            $manage[$pid] = GroupService::canManage($pid, $uid);
            $analytics[$pid] = \App\Services\Access::projectCan($pid, $uid, 'view_analytics');
            $assign[$pid] = \App\Services\Access::canAssignOnCreate($pid, $uid);
        }
        // پنجره‌ی «گروه جدید» ← مرحله‌ی اول: فقط پروژه‌هایی که کاربر در آن‌ها گروه می‌سازد
        $manageable = array_values(array_filter($projects, static fn($p) => $manage[(int) $p['id']]));
        $this->view('groups/index', [
            'projects' => $projects, 'current' => $cur, 'q' => $q, 'groups' => $groups, 'manage' => $manage, 'analytics' => $analytics, 'canAssign' => $assign,
            'manageable' => $manageable, 'total' => $pids && ($cur || $q !== '') ? (int) DB::value('SELECT COUNT(*) FROM project_groups WHERE project_id IN (' . DB::in($pids) . ')', $pids) : count($groups),
        ], ['title' => 'دپارتمان / گروه', 'page' => 'groups']);
    }

    /** مرحله‌ی دوم «گروه جدید» / ویرایش: اعضای پروژه با تصویر پروفایل (فقط برای کسی که در آن پروژه گروه می‌سازد) */
    public function members()
    {
        $uid = $this->uid();
        $pid = (int) ($this->request->query['project'] ?? 0);
        if ($pid <= 0 || !GroupService::canManage($pid, $uid)) {
            $this->fail('ساخت گروه در این پروژه با سرپرست و نقش‌های بالاتر (یا مجوز «افزودن کاربر») است.', 403);
        }
        $p = DB::one('SELECT p.title, p.user_id, u.first_name, u.last_name, u.avatar FROM projects p JOIN users u ON u.id = p.user_id WHERE p.id = ?', [$pid]);
        $out = [];
        if ($p) {
            $out[] = ['id' => Pid::of('users', (int) $p['user_id']), 'name' => full_name($p), 'avatar' => avatar_url($p['avatar']), 'role' => 'سازنده‌ی پروژه'];
        }
        foreach (ProjectModel::members($pid) as $m) {
            if ((int) $m['user_id'] !== (int) ($p['user_id'] ?? 0) && ($m['user_status'] ?? 'active') === 'active') {
                $out[] = ['id' => Pid::of('users', (int) $m['user_id']), 'name' => full_name($m), 'avatar' => avatar_url($m['avatar']), 'role' => ''];
            }
        }
        $this->ok('', ['project' => ['id' => Pid::of('projects', $pid), 'title' => (string) ($p['title'] ?? '')], 'members' => $out]);
    }

    public function store()
    {
        $r = $this->request;
        $pid = (int) $r->input('project_id', 0);
        $res = GroupService::create($this->uid(), $pid, $this->input());
        $this->respond($res['ok'], $res['message'], '/groups');
    }

    public function update(string $id)
    {
        $res = GroupService::update($this->uid(), (int) $id, $this->input());
        $this->respond($res['ok'], $res['message'], '/groups');
    }

    /** مسدود کردن گروه به‌جای حذف (v5.12) */
    public function block(string $id)
    {
        $res = GroupService::setBlocked($this->uid(), (int) $id, true);
        $this->respond($res['ok'], $res['message'], '/groups');
    }

    public function unblock(string $id)
    {
        $res = GroupService::setBlocked($this->uid(), (int) $id, false);
        $this->respond($res['ok'], $res['message'], '/groups');
    }

    /** هشدار پیش از ذخیره‌ی ویرایش: اعضایی که بیرون می‌روند و در تسک‌های باز گروه سهم دارند */
    public function impact(string $id)
    {
        $this->ok('', ['removed' => GroupService::removalImpact($this->uid(), (int) $id, array_map('intval', $this->request->arr('members')))]);
    }

    /** وظیفه‌ی شخصی یک عضو روی تسک گروهی (سرگروه / سازنده‌ی تسک / مدیر گروه‌ها) */
    public function duty(string $task)
    {
        $res = GroupService::setDuty($this->uid(), (int) $task, (int) $this->request->input('member_id', 0), $this->request->str('duty', 600));
        $this->respond($res['ok'], $res['message'], '/tasks');
    }

    /** سرگروه تیک یک عضو را می‌زند یا برمی‌دارد */
    public function memberTick(string $task)
    {
        $res = GroupService::setMemberTick($this->uid(), (int) $task, (int) $this->request->input('member_id', 0), $this->request->input('on', '1') === '1');
        $this->respond($res['ok'], $res['message'], '/tasks');
    }

    private function input(): array
    {
        $r = $this->request;
        return ['title' => $r->str('title', 200), 'description' => $r->str('description', 600), 'members' => array_map('intval', $r->arr('members')), 'head_id' => (int) $r->input('head_id', 0)];
    }
}
