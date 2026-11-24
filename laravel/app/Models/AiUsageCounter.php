<?php
declare(strict_types=1);
namespace KarlixLaravel\Models;
final class AiUsageCounter extends Record
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    public function save(array $options = []) { throw new \LogicException('Composite keys require the scoped repository/query builder'); }
    public function delete() { throw new \LogicException('Composite keys require the scoped repository/query builder'); }
    protected $table = 'ai_usage_counters';
}
