<?php
declare(strict_types=1);
namespace KarlixLaravel\Models;
final class Project extends Record
{
    protected $table = 'projects';
    public function owner() { return $this->belongsTo(User::class, 'user_id'); }
    public function tasks() { return $this->hasMany(Task::class); }
}
