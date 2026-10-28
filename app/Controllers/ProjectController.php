<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Uploader;
use App\Models\ProjectModel;
use App\Services\Access;
use App\Services\ColleagueService;
use App\Services\ProjectService;
use App\Services\TaskAnalytics;

final class ProjectController extends Controller
{
    public function index()
    {
        $uid = $this->uid();
        $projects = ProjectModel::forUser($uid);
        foreach ($projects as &$p) {
            $p['members'] = ProjectModel::members((int) $p['id']);
            $p['pending_ids'] = $p['is_creator'] || in_array('add_member', $p['perms'], true) ? ProjectModel::pendingUserIds((int) $p['id']) : [];
        }
        unset($p);
        $colleagues = array_map(static fn($c) => ['id' => (int) $c['user_id'], 'name' => full_name($c), 'mobile' => $c['mobile'], 'avatar' => avatar_url($c['avatar'])], ColleagueService::listFor($uid));
        $this->view('projects/index', [
            'projects' => $projects, 'colleagues' => $colleagues, 'requests' => ProjectModel::pendingRequestsFor($uid),
            'history' => ProjectModel::leaveHistory($uid), 'permDefs' => Access::PROJECT_PERMS, 'taskPermDefs' => Access::TASK_PERMS, 'boardPermDefs' => Access::BOARD_PERMS,
        ], ['title' => 'پروژه‌ها', 'page' => 'projects']);
    }

    private function image(): ?array
    {
        return $this->request->file('profile_image');
    }

    public function store()
    {
        $r = $this->request;
        [$ok, $msg] = ProjectService::create($this->uid(), $r->str('project_title', 255), $r->str('project_description', 3000), $r->int('max_members', 10), array_map('intval', $r->arr('members')), $this->image(), $r->str('priority_type', 20) ?: null);
        $this->respond($ok, $msg, '/projects');
    }

    public function update(string $id)
    {
        $r = $this->request;
        $members = $r->has('members_present') ? array_map('intval', $r->arr('members')) : null;
        [$ok, $msg] = ProjectService::update((int) $id, $this->uid(), $r->str('project_title', 255), $r->str('project_description', 3000), $r->int('max_members', 10), $members, $this->image(), $r->str('priority_type', 20) ?: null);
        $this->respond($ok, $msg, '/projects');
    }

    public function destroy(string $id)
    {
        [$ok, $msg] = ProjectService::delete((int) $id, $this->uid());
        $this->respond($ok, $msg, '/projects');
    }

    public function leave(string $id)
    {
        [$ok, $msg] = ProjectService::leave((int) $id, $this->uid());
        $this->respond($ok, $msg, '/projects');
    }

    public function block(string $id)
    {
        $uid = $this->uid();
        $p = ProjectModel::find((int) $id);
        if ($p === null || (int) $p['user_id'] === $uid || !Access::isProjectMember((int) $id, $uid)) {
            $this->respond(false, 'امکان مسدودسازی این پروژه وجود ندارد.', '/projects');
        }
        DB::run('INSERT IGNORE INTO project_blocks (project_id, user_id) VALUES (?, ?)', [(int) $id, $uid]);
        $this->respond(true, 'پروژه مسدود شد؛ دیگر دعوت جدیدی از آن دریافت نمی‌کنید.', '/projects');
    }

    public function unblock(string $id)
    {
        DB::run('DELETE FROM project_blocks WHERE project_id = ? AND user_id = ?', [(int) $id, $this->uid()]);
        $this->respond(true, 'مسدودیت پروژه برداشته شد.', '/projects');
    }

    public function memberPermissions(string $id, string $userId)
    {
        [$ok, $msg] = ProjectService::setPermissions((int) $id, $this->uid(), (int) $userId, array_map('strval', $this->request->arr('permissions')));
        $this->respond($ok, $msg, '/projects');
    }

    public function memberTaskPermissions(string $id, string $userId)
    {
        [$ok, $msg] = ProjectService::setTaskPermissions((int) $id, $this->uid(), (int) $userId, array_map('strval', $this->request->arr('permissions')));
        $this->respond($ok, $msg, '/projects');
    }

    public function memberBoardPermissions(string $id, string $userId)
    {
        [$ok, $msg] = ProjectService::setBoardPermissions((int) $id, $this->uid(), (int) $userId, array_map('strval', $this->request->arr('permissions')),
            $this->request->input('view_present') ? array_map('intval', $this->request->arr('view_users')) : null);
        $this->respond($ok, $msg, '/projects');
    }

    public function removeMember(string $id, string $userId)
    {
        [$ok, $msg] = ProjectService::removeMember((int) $id, $this->uid(), (int) $userId);
        $this->respond($ok, $msg, '/projects');
    }

    public function respondRequest(string $id)
    {
        $action = $this->request->str('action');
        if (!in_array($action, ['accept', 'reject'], true)) {
            $this->respond(false, 'نوع پاسخ مشخص نیست.', '/projects');
        }
        [$ok, $msg] = ProjectService::respond((int) $id, $this->uid(), $action === 'accept');
        $this->respond($ok, $msg, '/projects');
    }

    public function clearHistory()
    {
        DB::run('DELETE FROM project_leave_history WHERE user_id = ?', [$this->uid()]);
        $this->respond(true, 'تاریخچه‌ی پروژه‌های سابق پاک شد.', '/projects');
    }

    public function analytics(string $id)
    {
        $uid = $this->uid();
        $p = ProjectModel::find((int) $id);
        if ($p === null || !Access::isProjectMember((int) $id, $uid)) {
            $this->abort(404, 'پروژه یافت نشد.', 'NOT_FOUND');
        }
        if (!Access::projectCan((int) $id, $uid, 'view_analytics')) {
            $this->abort(403, 'دسترسی مشاهده‌ی تحلیل‌های این پروژه را ندارید.', 'FORBIDDEN');
        }
        $people = array_map(static fn($u) => ['id' => (int) $u['id'], 'name' => full_name($u)], TaskAnalytics::people((int) $id));
        $this->view('projects/analytics', [
            'project' => $p, 'a' => ProjectModel::analytics((int) $id), 'k' => TaskAnalytics::project((int) $id), 'people' => $people,
            'speed' => TaskAnalytics::speedSeries((int) $id, array_slice(array_column($people, 'id'), 0, 5), '1m', 'score'),
        ], ['title' => 'تحلیل پروژه', 'page' => 'projects']);
    }

    /** داده‌ی نمودار مقایسه‌ی سرعت انجام (AJAX). */
    public function speed(string $id)
    {
        $uid = $this->uid();
        if (ProjectModel::find((int) $id) === null || !Access::isProjectMember((int) $id, $uid) || !Access::projectCan((int) $id, $uid, 'view_analytics')) {
            $this->fail('دسترسی مشاهده‌ی تحلیل‌های این پروژه را ندارید.', 403);
        }
        $q = $this->request->query;
        $users = array_filter(array_map('intval', explode(',', (string) ($q['users'] ?? ''))));
        $this->ok('', TaskAnalytics::speedSeries((int) $id, $users, (string) ($q['range'] ?? '1m'), (string) ($q['metric'] ?? 'score')));
    }
}
