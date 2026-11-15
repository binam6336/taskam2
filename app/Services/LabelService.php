<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\SubjectModel;

/**
 * لیبل‌های تسک (جدول subjects) — مشترک بین پنل و API.
 * v7.12: هر لیبل مال یک پروژه است (subjects.project_id)؛ ساخت، ویرایش و مسدود کردن = Access::canManageLabels
 * (سازنده‌ی پروژه یا «دسترسی ایجاد لیبل»؛ نقش مدیر کل و مدیر واحد دارند). بقیه‌ی اعضا فقط لیبل موجود پروژه را روی تسک می‌گذارند.
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

    /** لیبل با نام پروژه و can_manage برای خروجی */
    public static function present(int $id, int $uid): ?array
    {
        $r = DB::one('SELECT s.*, p.title AS project_title, (SELECT COUNT(*) FROM tasks t WHERE t.subject_id = s.id AND t.blocked_at IS NULL) AS tasks_count
                FROM subjects s LEFT JOIN projects p ON p.id = s.project_id WHERE s.id = ?', [$id]);
        if ($r !== null) {
            $r['can_manage'] = self::canManage($r, $uid);
        }
        return $r;
    }

    /** لیبل پروژه: «دسترسی ایجاد لیبل» در همان پروژه؛ لیبل شخصی قدیمی (بدون پروژه): فقط صاحبش (برای انتقال به پروژه) */
    public static function canManage(array $label, int $uid): bool
    {
        return $label['project_id'] === null ? (int) $label['user_id'] === $uid : Access::canManageLabels((int) $label['project_id'], $uid);
    }

    /** کاربر لیبل را می‌بیند؟ عضو پروژه‌ی لیبل، یا صاحب لیبل شخصی قدیمی */
    public static function canSee(array $label, int $uid): bool
    {
        return $label['project_id'] === null ? (int) $label['user_id'] === $uid : Access::isProjectMember((int) $label['project_id'], $uid);
    }

    /** پروژه‌هایی که کاربر در آن‌ها لیبل می‌سازد: [[id, title], …] */
    public static function manageableProjects(int $uid): array
    {
        $out = [];
        foreach (\App\Models\ProjectModel::forUser($uid) as $p) {
            if (empty($p['blocked_at']) && Access::canManageLabels((int) $p['id'], $uid)) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** ساخت لیبل برای پروژه (project_id الزامی) — فقط سازنده‌ی پروژه یا عضو دارای «دسترسی ایجاد لیبل» */
    public static function create(int $uid, array $in): array
    {
        $projectId = (int) ($in['project_id'] ?? 0);
        if ($projectId === 0) {
            return self::res(false, 'پروژه‌ی لیبل را انتخاب کنید؛ لیبل‌ها مال پروژه‌اند.', 'PROJECT_REQUIRED', 422);
        }
        if ($projectId < 0 || !Access::isProjectMember($projectId, $uid)) {
            return self::res(false, 'پروژه‌ی انتخاب‌شده معتبر نیست یا عضو آن نیستید.', 'PROJECT_INVALID', 422);
        }
        if (!Access::canManageLabels($projectId, $uid)) {
            return self::res(false, 'دسترسی ایجاد لیبل در این پروژه را ندارید؛ فقط از لیبل‌های موجود پروژه استفاده کنید.', 'LABEL_FORBIDDEN', 403);
        }
        $title = trim(mb_substr((string) ($in['title'] ?? ''), 0, 150));
        if ($title === '') {
            return self::res(false, 'عنوان لیبل را وارد کنید.', 'TITLE_REQUIRED', 422);
        }
        $color = self::color($in['color'] ?? null);
        if ($color === false) {
            return self::badColor();
        }
        if (SubjectModel::titleTaken($projectId, $title)) {
            return self::res(false, 'لیبلی با همین عنوان در این پروژه هست.', 'LABEL_DUPLICATE', 409);
        }
        if ($lim = Limits::create('label', $uid)) {
            return self::res(false, $lim, 'LIMIT_REACHED', 429);
        }
        $id = DB::insert('subjects', ['user_id' => $uid, 'project_id' => $projectId, 'title' => $title, 'color' => $color ?? '#22c55e']);
        return self::res(true, 'لیبل ایجاد شد.', '', 201, self::present($id, $uid));
    }

    /** ویرایش جزئی: فقط فیلدهای ارسالی؛ project_id فقط برای انتقال لیبل شخصی قدیمی به یک پروژه */
    public static function update(int $uid, int $id, array $in): array
    {
        $old = SubjectModel::find($id);
        if ($old === null || !self::canSee($old, $uid)) {
            return self::res(false, 'لیبل یافت نشد.', 'LABEL_NOT_FOUND', 404);
        }
        if (!self::canManage($old, $uid)) {
            return self::res(false, 'دسترسی ویرایش لیبل‌های این پروژه را ندارید.', 'LABEL_FORBIDDEN', 403);
        }
        $set = [];
        $project = $old['project_id'] === null ? null : (int) $old['project_id'];
        if (array_key_exists('project_id', $in) && (int) $in['project_id'] !== 0 && (int) $in['project_id'] !== (int) $project) {
            $to = (int) $in['project_id'];
            if ($project !== null) {
                return self::res(false, 'لیبل پروژه به پروژه‌ی دیگر منتقل نمی‌شود.', 'LABEL_PROJECT_FIXED', 422);
            }
            if ($to < 0 || !Access::isProjectMember($to, $uid)) {
                return self::res(false, 'پروژه‌ی انتخاب‌شده معتبر نیست یا عضو آن نیستید.', 'PROJECT_INVALID', 422);
            }
            if (!Access::canManageLabels($to, $uid)) {
                return self::res(false, 'دسترسی ایجاد لیبل در این پروژه را ندارید؛ فقط از لیبل‌های موجود پروژه استفاده کنید.', 'LABEL_FORBIDDEN', 403);
            }
            $set['project_id'] = $project = $to;
        }
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
        if ($project !== null && SubjectModel::titleTaken($project, (string) ($set['title'] ?? $old['title']), $id)) {
            return self::res(false, 'لیبلی با همین عنوان در این پروژه هست.', 'LABEL_DUPLICATE', 409);
        }
        if ($set) {
            DB::update('subjects', $set, 'id = ?', [$id]);
        }
        return self::res(true, isset($set['project_id']) ? 'لیبل به پروژه منتقل شد.' : 'لیبل ویرایش شد.', '', 200, self::present($id, $uid));
    }
}
