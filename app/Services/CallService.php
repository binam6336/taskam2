<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Validate;
use App\Models\CallModel;

/** منطق درخواست‌های تماس (مشترک وب/API). */
final class CallService
{
    private static function res(bool $ok, string $msg = '', string $code = '', int $http = 200, $data = null): array
    {
        return ['ok' => $ok, 'message' => $msg, 'code' => $code, 'http' => $http, 'data' => $data];
    }

    /** اعتبارسنجی فیلدها؛ [clean|null, error|null] */
    private static function clean(array $in): array
    {
        $first = trim((string) ($in['first_name'] ?? ''));
        $last = trim((string) ($in['last_name'] ?? ''));
        $mobRaw = trim((string) ($in['mobile'] ?? ''));
        if ($first === '' || $last === '' || $mobRaw === '') {
            return [null, ['نام، نام‌خانوادگی و موبایل الزامی است.', 'FIELDS_REQUIRED']];
        }
        $mobile = Validate::mobile($mobRaw);
        if ($mobile === null) {
            return [null, ['شماره موبایل نامعتبر است (۰۹xxxxxxxxx).', 'MOBILE_INVALID']];
        }
        $email = trim((string) ($in['email'] ?? ''));
        if ($email !== '' && !Validate::email($email)) {
            return [null, ['ایمیل نامعتبر است.', 'EMAIL_INVALID']];
        }
        $site = trim((string) ($in['website'] ?? ''));
        if ($site !== '' && mb_strlen($site) > 255) {
            return [null, ['آدرس وب‌سایت بیش از حد طولانی است.', 'WEBSITE_INVALID']];
        }
        return [[
            'first_name' => mb_substr($first, 0, 150), 'last_name' => mb_substr($last, 0, 150), 'mobile' => $mobile,
            'email' => $email !== '' ? $email : null, 'store' => mb_substr(trim((string) ($in['store'] ?? '')), 0, 255) ?: null,
            'website' => $site !== '' ? $site : null, 'description' => mb_substr(trim((string) ($in['description'] ?? '')), 0, 5000) ?: null,
        ], null];
    }

    public static function create(int $actor, array $in): array
    {
        [$d, $err] = self::clean($in);
        if ($d === null) {
            return self::res(false, $err[0], $err[1], 422);
        }
        $assignee = (int) ($in['assignee_id'] ?? 0) ?: $actor;
        if (!Access::canAssignTo($actor, $assignee, null)) {
            return self::res(false, 'امکان واگذاری به این کاربر وجود ندارد (باید همکار شما باشد).', 'ASSIGNEE_INVALID', 403);
        }
        $id = DB::insert('call_requests', $d + ['user_id' => $actor, 'assignee_id' => $assignee]);
        CallModel::ensureToken($id);
        $row = CallModel::find($id);
        Notifier::callAssigned($row, $actor);
        return self::res(true, 'درخواست تماس ثبت شد.', '', 201, $row);
    }

    public static function update(int $actor, int $id, array $in): array
    {
        $row = CallModel::find($id);
        if ($row === null || !Access::can($row, $actor, 'view') && !Access::can($row, $actor, 'edit')) {
            return self::res(false, 'درخواست یافت نشد.', 'CALL_NOT_FOUND', 404);
        }
        // برای به‌روزرسانی جزئی (API)، فیلدهای ارسال‌نشده از مقدار فعلی پر می‌شوند
        $merged = $in + array_intersect_key($row, array_flip(['first_name', 'last_name', 'mobile', 'email', 'store', 'website', 'description']));
        [$d, $err] = self::clean($merged);
        if ($d === null) {
            return self::res(false, $err[0], $err[1], 422);
        }
        if (!Access::can($row, $actor, 'edit')) {
            return self::res(false, 'اجازه‌ی ویرایش این درخواست را ندارید.', 'FORBIDDEN', 403);
        }
        $set = $d;
        $reassigned = false;
        if (array_key_exists('assignee_id', $in) && (int) $in['assignee_id'] > 0 && (int) $in['assignee_id'] !== (int) $row['assignee_id']) {
            if (!Access::can($row, $actor, 'reassign')) {
                return self::res(false, 'اجازه‌ی تغییر مسئول را ندارید.', 'FORBIDDEN', 403);
            }
            $aid = (int) $in['assignee_id'];
            if (!Access::canAssignTo((int) $row['user_id'], $aid, null) && !Access::canAssignTo($actor, $aid, null)) {
                return self::res(false, 'امکان واگذاری به این کاربر وجود ندارد.', 'ASSIGNEE_INVALID', 403);
            }
            $set['assignee_id'] = $aid;
            $reassigned = true;
        }
        DB::update('call_requests', $set, 'id = ?', [$id]);
        $fresh = CallModel::find($id);
        if ($reassigned) {
            CallModel::ensureToken($id);
            Notifier::callAssigned(CallModel::find($id) ?? $fresh, $actor);
        }
        return self::res(true, 'درخواست به‌روزرسانی شد.', '', 200, $fresh);
    }

    public static function complete(int $actor, int $id): array
    {
        $row = CallModel::find($id);
        if ($row === null || !Access::can($row, $actor, 'view') && !Access::can($row, $actor, 'complete')) {
            return self::res(false, 'درخواست یافت نشد.', 'CALL_NOT_FOUND', 404);
        }
        if (!Access::can($row, $actor, 'complete')) {
            return self::res(false, 'اجازه‌ی تکمیل این درخواست را ندارید.', 'FORBIDDEN', 403);
        }
        if ((int) $row['status'] === 1) {
            return self::res(false, 'این درخواست قبلاً انجام شده است.', 'ALREADY_COMPLETED', 409);
        }
        DB::run('UPDATE call_requests SET status = 1, completed_at = NOW() WHERE id = ? AND status = 0', [$id]);
        return self::res(true, 'درخواست به‌عنوان انجام‌شده ثبت شد.', '', 200, CallModel::find($id));
    }

    public static function delete(int $actor, int $id): array
    {
        $row = CallModel::find($id);
        if ($row === null || !Access::can($row, $actor, 'view') && !Access::can($row, $actor, 'delete')) {
            return self::res(false, 'درخواست یافت نشد.', 'CALL_NOT_FOUND', 404);
        }
        if (!Access::can($row, $actor, 'delete')) {
            return self::res(false, 'اجازه‌ی حذف این درخواست را ندارید.', 'FORBIDDEN', 403);
        }
        DB::run('DELETE FROM call_requests WHERE id = ?', [$id]);
        return self::res(true, 'درخواست حذف شد.', '', 200, ['id' => $id]);
    }
}
