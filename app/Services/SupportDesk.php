<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Request;
use App\Models\SupportModel;
use App\Models\TextModel;

/** داده‌ی مشترک صفحه‌ی «میز تیکت» برای پنل کاربر و ادمین. */
final class SupportDesk
{
    /**
     * @param bool $admin  نمای ادمین (همه‌ی تیکت‌ها) یا کاربر (فقط تیکت‌های خودش)
     * @param array|null $ticket تیکت بازشده (null = فقط فهرست)
     */
    public static function data(bool $admin, int $uid, Request $req, ?array $ticket): array
    {
        $q = $req->query;
        // ادمین با ورود به صفحه تیکت‌های «جدید» را می‌بیند؛ کاربر همه‌ی تیکت‌هایش را
        $default = $admin ? 'new' : 'all';
        $params = [
            'default' => $default,
            'status' => isset(SupportModel::filters($admin)[$q['status'] ?? '']) ? (string) $q['status'] : $default,
            'q' => mb_substr(trim((string) ($q['q'] ?? '')), 0, 100),
            'page' => max(1, (int) ($q['page'] ?? 1)),
            'per' => (int) ($q['per'] ?? 20),
        ];
        $list = SupportModel::tickets($admin ? null : $uid, $params['status'], $params['q'], $params['page'], $params['per']);
        $params['page'] = $list['page'];
        $params['per'] = $list['per'];

        $messages = [];
        if ($ticket !== null) {
            if ($admin) {
                DB::run("UPDATE support_messages SET is_read_by_admin = 1 WHERE ticket_id = ? AND sender_role = 'user' AND is_read_by_admin = 0", [(int) $ticket['id']]);
            } else {
                DB::run("UPDATE support_messages SET is_read_by_user = 1 WHERE ticket_id = ? AND sender_role = 'admin' AND is_read_by_user = 0", [(int) $ticket['id']]);
            }
            $messages = SupportModel::messages((int) $ticket['id']);
        }
        $texts = array_map(static fn($t) => ['title' => (string) $t['title'], 'content' => (string) $t['content']], array_slice(TextModel::forUser($uid), 0, 60));

        return [
            'admin' => $admin,
            'base' => $admin ? '/admin/support' : '/support',
            'list' => $list,
            'params' => $params,
            'ticket' => $ticket,
            'messages' => $messages,
            'me' => $uid,
            'texts' => $texts,
            'priorities' => SupportModel::PRIORITIES,
            'counts' => $admin ? SupportModel::adminCounts() : null,
        ];
    }

    /** پارامترهای فهرست برای ساخت لینک‌ها (فقط مقادیر غیرپیش‌فرض). */
    public static function query(array $params, array $override = []): string
    {
        $p = array_merge($params, $override);
        $out = [];
        if (($p['status'] ?? 'all') !== ($p['default'] ?? 'all')) {
            $out['status'] = $p['status'];
        }
        if (($p['q'] ?? '') !== '') {
            $out['q'] = $p['q'];
        }
        if ((int) ($p['page'] ?? 1) > 1) {
            $out['page'] = (int) $p['page'];
        }
        if ((int) ($p['per'] ?? 20) !== 20) {
            $out['per'] = (int) $p['per'];
        }
        return $out ? '?' . http_build_query($out) : '';
    }
}
