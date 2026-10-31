<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/** توکن‌های API؛ مقدار خام فقط یک‌بار هنگام ساخت نمایش داده می‌شود. */
final class TokenModel
{
    public const MAX_PER_USER = 10;

    public static function forUser(int $uid): array
    {
        return DB::all('SELECT id, name, token_prefix, scope, is_active, last_used_at, last_ip, expires_at, created_at FROM api_tokens WHERE user_id = ? ORDER BY id DESC', [$uid]);
    }

    /** @return array{id:int,token:string} */
    public static function create(int $uid, string $name, ?string $expiresAt, string $scope = 'write'): array
    {
        $raw = 'tk_' . bin2hex(random_bytes(24));
        $id = DB::insert('api_tokens', [
            'user_id' => $uid, 'name' => $name, 'token_hash' => hash('sha256', $raw),
            'token_prefix' => substr($raw, 0, 10), 'expires_at' => $expiresAt, 'scope' => $scope === 'read' ? 'read' : 'write',
        ]);
        return ['id' => $id, 'token' => $raw];
    }

    /** غیرفعال کردن همه‌ی توکن‌های کاربر (بعد از تغییر رمز) */
    public static function revokeAll(int $uid): void
    {
        DB::run('UPDATE api_tokens SET is_active = 0 WHERE user_id = ?', [$uid]);
    }
}
