<?php
declare(strict_types=1);

namespace App\Core;

/** ثبت رویدادهای امنیتی/مدیریتی در جدول audit_log. */
final class Audit
{
    public static function log(?int $userId, string $action, array $details = []): void
    {
        // رویداد در لاگ عمومی روز هم (storage/logs/YYYY-MM-DD.log)؛ داده‌ی حساس در Log::clean پوشانده می‌شود
        Log::general(preg_match('/fail|lock|mismatch|throttl|misuse|duplicate/', $action) ? 'WARNING' : 'INFO',
            'audit.' . $action, $userId !== null ? ['user_id' => $userId] + $details : $details);
        try {
            $req = Request::capture();
            DB::insert('audit_log', [
                'user_id'    => $userId,
                'action'     => mb_substr($action, 0, 64),
                'details'    => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip'         => $req->ip(),
                'user_agent' => $req->userAgent(),
            ]);
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
        }
    }
}
