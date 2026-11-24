<?php
namespace KarlixLaravel\Policies;
use KarlixLaravel\Models\User;
use KarlixLaravel\Models\Task;
use KarlixLaravel\Services\Access;
final class TaskPolicy
{
    public function view(User $u, Task $t): bool { return Access::canTask($t->attributesToArray(), (int)$u->id, 'view'); }
    public function update(User $u, Task $t): bool { return Access::canTask($t->attributesToArray(), (int)$u->id, 'edit_info'); }
    public function complete(User $u, Task $t): bool { return Access::canTask($t->attributesToArray(), (int)$u->id, 'complete'); }
}
