<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * اولویت تسک: دسته‌بندی (کم/متوسط/زیاد) یا درصدی (۰ تا ۱۰۰، مضرب ۵) بسته به تنظیم پروژه.
 * هر دو مقدار همیشه همگام ذخیره می‌شوند تا تغییر نوع اولویت پروژه چیزی را از دست ندهد.
 */
final class Priority
{
    public const LEVELS = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'];
    public const PERCENT = ['low' => 25, 'medium' => 50, 'high' => 75];
    public const TYPES = ['category' => 'دسته‌بندی (کم / متوسط / زیاد)', 'percent' => 'درصدی (۰ تا ۱۰۰٪)'];

    /** درصد معتبر (مضرب ۵ بین ۰ و ۱۰۰) یا null */
    public static function percent($v): ?int
    {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return null;
        }
        $n = (int) round((float) $v);
        return $n < 0 || $n > 100 ? null : (int) (round($n / 5) * 5);
    }

    /** دسته‌ی معادل یک درصد: نزدیک‌ترین به ۲۵ / ۵۰ / ۷۵ */
    public static function levelOf(int $percent): string
    {
        return $percent < 38 ? 'low' : ($percent < 63 ? 'medium' : 'high');
    }

    public static function projectType(?int $projectId): string
    {
        if (!$projectId) {
            return 'category';
        }
        return DB::value('SELECT priority_type FROM projects WHERE id = ?', [$projectId]) === 'percent' ? 'percent' : 'category';
    }

    /** با تغییر نوع به «درصدی»، درصد تسک‌ها از روی دسته تنظیم می‌شود (کم ۲۵، متوسط ۵۰، زیاد ۷۵). */
    public static function switchProject(int $projectId, string $from, string $to): void
    {
        if ($from !== 'percent' && $to === 'percent') {
            DB::run("UPDATE tasks SET priority_percent = CASE priority WHEN 'low' THEN 25 WHEN 'high' THEN 75 ELSE 50 END WHERE project_id = ?", [$projectId]);
        }
    }
}
