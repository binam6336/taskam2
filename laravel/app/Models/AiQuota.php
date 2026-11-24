<?php
declare(strict_types=1);
namespace KarlixLaravel\Models;
final class AiQuota extends Record
{
    protected $primaryKey = 'user_id';
    protected $keyType = 'int';
    public $incrementing = false;
    protected $table = 'ai_quotas';
}
