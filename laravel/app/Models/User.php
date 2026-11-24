<?php
declare(strict_types=1);
namespace KarlixLaravel\Models;
final class User extends Record implements \Illuminate\Contracts\Auth\Authenticatable
{
    protected $table = 'users';
    use \Illuminate\Auth\Authenticatable;
    public function getAuthPasswordName() { return 'password_hash'; }
    public function projects() { return $this->hasMany(Project::class, 'user_id'); }
}
