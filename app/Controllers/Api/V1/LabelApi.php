<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Models\SubjectModel;
use App\Services\LabelService;

/** لیبل‌های تسک (در دیتابیس و فیلدهای تسک: subject) */
final class LabelApi extends ApiController
{
    protected const TABLE = 'subjects';

    public static function format(array $s): array
    {
        return ['id' => pid('subjects', $s), 'title' => $s['title'], 'color' => $s['color'],
            'project_id' => $s['project_id'] !== null ? pid('projects', $s['project_id']) : null, 'project_title' => $s['project_title'] ?? null, 'can_manage' => !empty($s['can_manage']), 'tasks_count' => isset($s['tasks_count']) ? (int) $s['tasks_count'] : null, 'created_at' => $s['created_at'] ?? null];
    }

    public function list()
    {
        $only = $this->request->input('project_id');
        $only = $only !== null && $only !== '' ? (int) $only : null;          // Pid::INPUTS: pid → id، نامعتبر = -1
        $rows = array_map([self::class, 'format'], array_values(array_filter(SubjectModel::forUser($this->uid, true),
            static fn($s) => $only === null || (int) $s['project_id'] === $only)));
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
}
