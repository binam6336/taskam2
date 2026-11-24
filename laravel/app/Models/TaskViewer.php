<?php
declare(strict_types=1);
namespace KarlixLaravel\Models;
final class TaskViewer extends Record
{
    protected $primaryKey = 'task_id';
    public $incrementing = false;
    public function save(array $options = []) { throw new \LogicException('Composite keys require the scoped repository/query builder'); }
    public function delete() { throw new \LogicException('Composite keys require the scoped repository/query builder'); }
    protected $table = 'task_viewers';
}
