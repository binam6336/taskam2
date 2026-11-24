<?php
namespace KarlixLaravel\Policies;
use KarlixLaravel\Models\User;
use KarlixLaravel\Models\Project;
use KarlixLaravel\Services\Access;
final class ProjectPolicy
{
    public function view(User $u, Project $p): bool { return Access::isProjectMember((int)$p->id, (int)$u->id); }
    public function update(User $u, Project $p): bool { return Access::projectCan((int)$p->id, (int)$u->id, 'edit_project'); }
    public function viewStorage(User $u, Project $p): bool { return Access::canViewStorage((int)$p->id, (int)$u->id); }
}
