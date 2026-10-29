<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\DB;
use App\Models\ProjectModel;
use App\Services\Access;
use App\Services\BoardService;
use App\Services\ProjectService;

final class ProjectApi extends ApiController
{
    private function pid(): int
    {
        $id = (int) ($this->request->query['id'] ?? $this->request->input('project_id', $this->request->input('id', 0)));
        if ($id <= 0) {
            $this->err('پارامتر id پروژه الزامی است.', 400, 'INVALID_ID');
        }
        return $id;
    }

    private function member(int $id): array
    {
        $p = ProjectModel::find($id);
        if ($p === null || !Access::isProjectMember($id, $this->uid)) {
            $this->err('پروژه یافت نشد یا دسترسی ندارید.', 404, 'PROJECT_NOT_FOUND');
        }
        return $p;
    }

    private function format(array $p, array $perms): array
    {
        return [
            'id' => (int) $p['id'], 'title' => $p['title'], 'description' => $p['description'], 'max_members' => (int) $p['max_members'], 'priority_type' => $p['priority_type'] ?? 'category',
            'profile_image' => project_image_url($p['profile_image']), 'status' => (int) $p['status'],
            'is_creator' => (int) $p['user_id'] === $this->uid, 'my_permissions' => $perms, 'my_task_permissions' => Access::projectTaskPerms((int) $p['id'], $this->uid), 'my_board_permissions' => Access::projectBoardPerms((int) $p['id'], $this->uid), 'my_role' => $this->myRole($p), 'my_board_view_user_ids' => Access::boardViewScope((int) $p['id'], $this->uid), 'created_at' => $p['created_at'],
        ];
    }

    private function myRole(array $p): string
    {
        if ((int) $p['user_id'] === $this->uid) {
            return 'creator';
        }
        return Access::memberRole(DB::one('SELECT permissions, task_permissions, board_permissions, board_view_users FROM project_members WHERE project_id = ? AND user_id = ?', [(int) $p['id'], $this->uid]) ?? []);
    }

    public function list()
    {
        $q = $this->request->query;
        $role = in_array($q['role'] ?? 'all', ['all', 'creator', 'member'], true) ? $q['role'] ?? 'all' : 'all';
        [$limit, $offset] = $this->paging();
        $rows = ProjectModel::forUser($this->uid, trim((string) ($q['q'] ?? '')), $role, $limit, $offset);
        $out = array_map(fn($p) => $this->format($p, $p['perms']) + ['is_blocked_by_me' => (int) $p['is_blocked_by_me'] > 0, 'members_count' => (int) $p['members_count']], $rows);
        $this->ok(['count' => count($out), 'limit' => $limit, 'offset' => $offset, 'query' => trim((string) ($q['q'] ?? '')), 'role' => $role, 'projects' => $out]);
    }

    private function members(int $id): array
    {
        $creator = (int) DB::value('SELECT user_id FROM projects WHERE id = ?', [$id]);
        $out = [];
        foreach (ProjectModel::members($id) as $m) {
            $isCreator = (int) $m['user_id'] === $creator;
            $out[] = ['member_row_id' => (int) $m['member_row_id'], 'user_id' => (int) $m['user_id'], 'full_name' => full_name($m), 'first_name' => $m['first_name'], 'last_name' => $m['last_name'], 'mobile' => $m['mobile'], 'email' => $m['email'], 'avatar_url' => avatar_url($m['avatar']),
                'permissions' => $isCreator ? array_keys(Access::PROJECT_PERMS) : Access::decode($m['permissions'], array_keys(Access::PROJECT_PERMS)),
                'task_permissions' => $isCreator ? array_keys(Access::TASK_PERMS) : Access::decode($m['task_permissions'], array_keys(Access::TASK_PERMS)),
                'board_permissions' => $isCreator ? array_keys(Access::BOARD_PERMS) : Access::decode($m['board_permissions'] ?? null, array_keys(Access::BOARD_PERMS)),
                'board_view_user_ids' => $isCreator || Access::viewAll($m['board_view_users'] ?? null) ? null : Access::ids($m['board_view_users'] ?? null),
                'board_limits' => (object) ($isCreator ? [] : BoardService::decodeLimits($m['board_limits'] ?? null)),
                'role' => $isCreator ? 'creator' : Access::memberRole($m), 'is_creator' => $isCreator, 'added_at' => $m['added_at'], 'user_status' => $m['user_status']];
        }
        return $out;
    }

    public function show()
    {
        $id = $this->pid();
        $p = $this->member($id);
        $members = $this->members($id);
        $this->ok($this->format($p, Access::projectPerms($id, $this->uid)) + ['members_count' => count($members), 'members' => $members]);
    }

    public function membersList()
    {
        $id = $this->pid();
        $this->member($id);
        $m = $this->members($id);
        $this->ok(['project_id' => $id, 'count' => count($m), 'members' => $m]);
    }

    public function stats()
    {
        $u = $this->uid;
        $r = DB::one('SELECT (SELECT COUNT(*) FROM projects WHERE user_id = ?) created_count,
              (SELECT COUNT(*) FROM projects p WHERE p.user_id = ? OR EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id = ?)) total_projects,
              (SELECT COUNT(*) FROM project_members WHERE user_id = ?) total_memberships,
              (SELECT COUNT(*) FROM project_blocks WHERE user_id = ?) blocked_count', [$u, $u, $u, $u, $u]) ?? [];
        $this->ok(['created' => (int) ($r['created_count'] ?? 0), 'total_projects' => (int) ($r['total_projects'] ?? 0), 'total_members' => (int) ($r['total_memberships'] ?? 0), 'blocked' => (int) ($r['blocked_count'] ?? 0)]);
    }

    public function create()
    {
        $in = $this->body();
        [$ok, $msg, $id] = ProjectService::create($this->uid, trim((string) ($in['title'] ?? '')), trim((string) ($in['description'] ?? '')), (int) ($in['max_members'] ?? 10), is_array($in['members'] ?? null) ? array_map('intval', $in['members']) : [], null, isset($in['priority_type']) ? (string) $in['priority_type'] : null);
        if (!$ok) {
            $this->err($msg, 400, 'PROJECT_CREATE_FAILED');
        }
        $p = ProjectModel::find((int) $id);
        $this->ok($this->format($p, array_keys(Access::PROJECT_PERMS)) + ['members_count' => count(ProjectModel::members((int) $id)), 'pending_requests' => ProjectModel::pendingUserIds((int) $id)], $msg);
    }

    public function update()
    {
        $id = $this->pid();
        $p = $this->member($id);
        $in = $this->body();
        [$ok, $msg] = ProjectService::update($id, $this->uid, trim((string) ($in['title'] ?? $p['title'])), trim((string) ($in['description'] ?? $p['description'] ?? '')), (int) ($in['max_members'] ?? $p['max_members']), null, null, isset($in['priority_type']) ? (string) $in['priority_type'] : null);
        if (!$ok) {
            $this->err($msg, str_contains($msg, 'اجازه') ? 403 : 400, str_contains($msg, 'اجازه') ? 'FORBIDDEN' : 'PROJECT_UPDATE_FAILED');
        }
        $this->ok($this->format(ProjectModel::find($id) ?? $p, Access::projectPerms($id, $this->uid)) + ['updated_at' => date('Y-m-d H:i:s')], $msg);
    }

    public function delete()
    {
        $id = $this->pid();
        [$ok, $msg] = ProjectService::delete($id, $this->uid);
        $ok ? $this->ok(['id' => $id], $msg) : $this->err($msg, str_contains($msg, 'فقط') ? 403 : 404, str_contains($msg, 'فقط') ? 'FORBIDDEN' : 'PROJECT_NOT_FOUND');
    }

    public function leave()
    {
        $id = $this->pid();
        [$ok, $msg] = ProjectService::leave($id, $this->uid);
        $ok ? $this->ok(['project_id' => $id, 'left_at' => date('Y-m-d H:i:s')], $msg) : $this->err($msg, 400, 'LEAVE_FAILED');
    }

    public function block()
    {
        $id = $this->pid();
        $p = $this->member($id);
        if ((int) $p['user_id'] === $this->uid) {
            $this->err('سازنده نمی‌تواند پروژه‌ی خودش را مسدود کند.', 400, 'CREATOR_CANNOT_BLOCK');
        }
        DB::run('INSERT IGNORE INTO project_blocks (project_id, user_id) VALUES (?, ?)', [$id, $this->uid]);
        $this->ok(['project_id' => $id, 'blocked_at' => date('Y-m-d H:i:s')], 'پروژه مسدود شد.');
    }

    public function memberAdd()
    {
        $id = $this->pid();
        $uid = (int) $this->request->input('user_id', 0);
        if ($uid <= 0) {
            $this->err('پارامتر user_id الزامی است.', 400, 'INVALID_USER_ID');
        }
        if (!ProjectModel::find($id)) {
            $this->err('پروژه یافت نشد.', 404, 'PROJECT_NOT_FOUND');
        }
        if (!Access::projectCan($id, $this->uid, 'add_member')) {
            $this->err('شما دسترسی افزودن کاربر ندارید.', 403, 'FORBIDDEN');
        }
        [$ok, $msg, $state] = ProjectService::invite($id, $this->uid, $uid);
        if (!$ok) {
            $http = $state === 'USER_NOT_FOUND' ? 404 : ($state === 'NOT_A_COLLEAGUE' ? 403 : 400);
            $this->err($msg, $http, $state);
        }
        $this->ok(['project_id' => $id, 'user_id' => $uid, 'state' => $state, 'requires_approval' => $state === 'requested', 'added_at' => date('Y-m-d H:i:s')], $msg);
    }

    public function memberRemove()
    {
        $id = $this->pid();
        $uid = (int) $this->request->input('user_id', 0);
        if ($uid <= 0) {
            $this->err('پارامتر user_id الزامی است.', 400, 'INVALID_USER_ID');
        }
        [$ok, $msg] = ProjectService::removeMember($id, $this->uid, $uid);
        $ok ? $this->ok(['project_id' => $id, 'user_id' => $uid], $msg) : $this->err($msg, str_contains($msg, 'دسترسی') ? 403 : 400, str_contains($msg, 'دسترسی') ? 'FORBIDDEN' : 'REMOVE_FAILED');
    }

    public function memberPermissions()
    {
        $id = $this->pid();
        $in = $this->body();
        $uid = (int) ($in['user_id'] ?? 0);
        if ($uid <= 0) {
            $this->err('پارامتر user_id الزامی است.', 400, 'INVALID_USER_ID');
        }
        $perms = is_array($in['permissions'] ?? null) ? array_map('strval', $in['permissions']) : [];
        [$ok, $msg] = ProjectService::setPermissions($id, $this->uid, $uid, $perms);
        if (!$ok) {
            $this->err($msg, str_contains($msg, 'دسترسی') ? 403 : 400, str_contains($msg, 'دسترسی') ? 'FORBIDDEN' : 'PERMISSIONS_FAILED');
        }
        $this->ok(['project_id' => $id, 'user_id' => $uid, 'permissions' => Access::projectPerms($id, $uid)], $msg);
    }

    /** دسترسی یک عضو روی تسک‌های این پروژه: edit_info, edit_due, reassign, notes, complete, delete */
    public function memberTaskPermissions()
    {
        $id = $this->pid();
        $in = $this->body();
        $uid = (int) ($in['user_id'] ?? 0);
        if ($uid <= 0) {
            $this->err('پارامتر user_id الزامی است.', 400, 'INVALID_USER_ID');
        }
        $perms = is_array($in['permissions'] ?? null) ? array_map('strval', $in['permissions']) : [];
        [$ok, $msg] = ProjectService::setTaskPermissions($id, $this->uid, $uid, $perms);
        if (!$ok) {
            $this->err($msg, str_contains($msg, 'دسترسی') ? 403 : 400, str_contains($msg, 'دسترسی') ? 'FORBIDDEN' : 'PERMISSIONS_FAILED');
        }
        $this->ok(['project_id' => $id, 'user_id' => $uid, 'task_permissions' => Access::projectTaskPerms($id, $uid)], $msg);
    }

    /** دسترسی یک عضو در برد تیمی این پروژه: to_todo, to_progress, to_review, to_done, block, unblock, move_back, move_any */
    public function memberBoardPermissions()
    {
        $id = $this->pid();
        $in = $this->body();
        $uid = (int) ($in['user_id'] ?? 0);
        if ($uid <= 0) {
            $this->err('پارامتر user_id الزامی است.', 400, 'INVALID_USER_ID');
        }
        $perms = is_array($in['permissions'] ?? null) ? array_map('strval', $in['permissions']) : [];
        $view = array_key_exists('view_user_ids', $in) ? (is_array($in['view_user_ids']) ? array_map('intval', $in['view_user_ids']) : ($in['view_user_ids'] === 'all' ? 'all' : [])) : null;
        if (array_key_exists('board_limits', $in) && ($limErr = ProjectService::cleanBoardLimits(is_array($in['board_limits']) ? $in['board_limits'] : [])[1])) {
            $this->err($limErr, 400, 'INVALID_LIMITS');                 // سقف نامعتبر → هیچ چیز ذخیره نمی‌شود
        }
        [$ok, $msg] = ProjectService::setBoardPermissions($id, $this->uid, $uid, $perms, $view);
        if ($ok && array_key_exists('board_limits', $in)) {
            [$ok, $limMsg] = ProjectService::setBoardLimits($id, $this->uid, $uid, is_array($in['board_limits']) ? $in['board_limits'] : []);
            $msg = $ok ? $msg : $limMsg;
        }
        if (!$ok) {
            $this->err($msg, str_contains($msg, 'دسترسی') ? 403 : 400, str_contains($msg, 'دسترسی') ? 'FORBIDDEN' : 'PERMISSIONS_FAILED');
        }
        $this->ok(['project_id' => $id, 'user_id' => $uid, 'board_permissions' => Access::projectBoardPerms($id, $uid),
            'board_view_user_ids' => self::viewIds($id, $uid), 'board_limits' => self::limits($id, $uid)], $msg);
    }

    /** سقف تسک‌های عضو در ستون‌های برد؛ شیء خالی = بدون محدودیت */
    private static function limits(int $projectId, int $uid): object
    {
        return (object) BoardService::decodeLimits(DB::value('SELECT board_limits FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $uid]));
    }

    private static function viewIds(int $projectId, int $uid): ?array
    {
        $raw = (string) DB::value('SELECT board_view_users FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $uid]);
        return Access::viewAll($raw) ? null : Access::ids($raw);
    }

    /** اعمال نقش آماده روی عضو: admin, unit_manager, supervisor, member, none */
    public function memberRole()
    {
        $id = $this->pid();
        $in = $this->body();
        $uid = (int) ($in['user_id'] ?? 0);
        if ($uid <= 0) {
            $this->err('پارامتر user_id الزامی است.', 400, 'INVALID_USER_ID');
        }
        $role = (string) ($in['role'] ?? '');
        if (!isset(Access::ROLES[$role])) {
            $this->err('نقش نامعتبر است؛ یکی از: ' . implode(', ', array_keys(Access::ROLES)), 400, 'ROLE_INVALID');
        }
        [$ok, $msg] = ProjectService::applyRole($id, $this->uid, $uid, $role);
        if (!$ok) {
            $this->err($msg, str_contains($msg, 'دسترسی') ? 403 : 400, str_contains($msg, 'دسترسی') ? 'FORBIDDEN' : 'PERMISSIONS_FAILED');
        }
        $m = DB::one('SELECT permissions, task_permissions, board_permissions, board_view_users FROM project_members WHERE project_id = ? AND user_id = ?', [$id, $uid]) ?? [];
        $this->ok(['project_id' => $id, 'user_id' => $uid, 'role' => Access::memberRole($m), 'permissions' => Access::projectPerms($id, $uid),
            'task_permissions' => Access::projectTaskPerms($id, $uid), 'board_permissions' => Access::projectBoardPerms($id, $uid), 'board_view_user_ids' => self::viewIds($id, $uid)], $msg);
    }
}
