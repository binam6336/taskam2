<?php
declare(strict_types=1);
namespace KarlixLaravel\Models;
final class UserSession extends Record
{
    protected $primaryKey = 'id_hash';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $table = 'user_sessions';
    protected $hidden = ['data', 'id_hash'];
}
