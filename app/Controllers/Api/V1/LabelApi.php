<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Models\SubjectModel;
use App\Services\LabelService;

/** لیبل‌های تسک (در دیتابیس و فیلدهای تسک: subject) */
final class LabelApi extends ApiController
{
    public static function format(array $s): array
    {
        return ['id' => (int) $s['id'], 'title' => $s['title'], 'color' => $s['color'], 'tasks_count' => isset($s['tasks_count']) ? (int) $s['tasks_count'] : null, 'created_at' => $s['created_at'] ?? null];
    }

    public function list()
    {
        $rows = array_map([self::class, 'format'], SubjectModel::forUser($this->uid));
        $this->ok(['count' => count($rows), 'colors' => SubjectModel::COLORS, 'labels' => $rows]);
    }

    public function create()
    {
        $this->fromService(LabelService::create($this->uid, $this->body()), [self::class, 'format']);
    }

    public function update()
    {
        $this->fromService(LabelService::update($this->uid, $this->id(), $this->body()), [self::class, 'format']);
    }

    public function delete()
    {
        $this->fromService(LabelService::delete($this->uid, $this->id()));
    }
}
