<?php
declare(strict_types=1);
namespace KarlixLaravel\Models;
final class Task extends Record
{
    protected $table = 'tasks';
    public function project() { return $this->belongsTo(Project::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assignee_id'); }
    public function notes() { return $this->hasMany(TaskNote::class); }
}
