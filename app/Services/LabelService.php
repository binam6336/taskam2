<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\SubjectModel;

/**
 * لیبل‌های تسک (جدول subjects) — مشترک بین پنل و API.
 * رنگ فقط از ۱۵ رنگ آماده (SubjectModel::COLORS)؛ لیبل قدیمی با رنگ دیگر، اگر رنگ ارسال نشود همان رنگ را نگه می‌دارد.
 */
final class LabelService
{
    private static function res(bool $ok, string $msg = '', string $code = '', int $http = 200, $data = null): array
    {
        return ['ok' => $ok, 'message' => $msg, 'code' => $code, 'http' => $http, 'data' => $data];
    }

    /** null = رنگ ارسال نشده؛ false = خارج از رنگ‌های آماده */
    private static function color($c)
    {
        $c = strtolower(trim((string) ($c ?? '')));
        if ($c === '') {
            return null;
        }
        return in_array($c, SubjectModel::COLORS, true) ? $c : false;
    }

    private static function badColor(): array
    {
        return self::res(false, 'رنگ لیبل باید یکی از ۱۵ رنگ آماده باشد: ' . implode('، ', SubjectModel::COLORS), 'COLOR_INVALID', 422);
    }

    public static function create(int $uid, array $in): array
    {
        $title = trim(mb_substr((string) ($in['title'] ?? ''), 0, 150));
        if ($title === '') {
            return self::res(false, 'عنوان لیبل را وارد کنید.', 'TITLE_REQUIRED', 422);
        }
        $color = self::color($in['color'] ?? null);
        if ($color === false) {
            return self::badColor();
        }
        $id = DB::insert('subjects', ['user_id' => $uid, 'title' => $title, 'color' => $color ?? '#22c55e']);
        return self::res(true, 'لیبل ایجاد شد.', '', 201, SubjectModel::owned($id, $uid));
    }

    /** ویرایش جزئی: فقط فیلدهای ارسالی */
    public static function update(int $uid, int $id, array $in): array
    {
        $old = SubjectModel::owned($id, $uid);
        if ($old === null) {
            return self::res(false, 'لیبل یافت نشد.', 'LABEL_NOT_FOUND', 404);
        }
        $set = [];
        if (array_key_exists('title', $in)) {
            $set['title'] = trim(mb_substr((string) $in['title'], 0, 150));
            if ($set['title'] === '') {
                return self::res(false, 'عنوان لیبل را وارد کنید.', 'TITLE_REQUIRED', 422);
            }
        }
        $color = self::color($in['color'] ?? null);
        if ($color === false) {
            return self::badColor();
        }
        if ($color !== null) {
            $set['color'] = $color;
        }
        if ($set) {
            DB::update('subjects', $set, 'id = ? AND user_id = ?', [$id, $uid]);
        }
        return self::res(true, 'لیبل ویرایش شد.', '', 200, SubjectModel::owned($id, $uid));
    }

    public static function delete(int $uid, int $id): array
    {
        if (SubjectModel::owned($id, $uid) === null) {
            return self::res(false, 'لیبل یافت نشد.', 'LABEL_NOT_FOUND', 404);
        }
        DB::run('DELETE FROM subjects WHERE id = ? AND user_id = ?', [$id, $uid]);
        return self::res(true, 'لیبل حذف شد (تسک‌های آن بدون لیبل باقی ماندند).', '', 200, ['id' => $id]);
    }
}
