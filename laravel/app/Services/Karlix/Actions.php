<?php
declare(strict_types=1);

namespace KarlixLaravel\Services\Karlix;

use KarlixLaravel\Database\DB;
use App\Core\Validate;
use KarlixLaravel\Repositories\TaskModel;
use KarlixLaravel\Services\BoardConfig;
use KarlixLaravel\Services\BoardService;
use KarlixLaravel\Services\Priority;
use KarlixLaravel\Services\TaskService;

/**
 * فهرست سفید عملیات کارلیکس (فاز اول: فقط اطلاعات همین تسک).
 * مدل فقط «پیشنهاد» می‌دهد: هر عملیات این‌جا جدا اعتبارسنجی می‌شود (نوع، پارامترها، ارجاع به لیبل/عضو از فهرست‌های داده‌شده)
 * و فقط از راه سرویس‌های موجود (TaskService::update، BoardService، …) با دسترسی همان کاربر درخواست‌کننده اجرا می‌شود.
 * هیچ متنی از خروجی مدل به‌عنوان کد، SQL یا شناسه‌ی خام استفاده نمی‌شود؛ عملیات ناشناخته رد می‌شود.
 */
final class Actions
{
    /** نوع ⇐ عنوان فارسی (ترتیب = ترتیب اجرا: وضعیت آخر از همه) */
    public const TYPES = [
        'update_title'       => 'عنوان',
        'update_description' => 'توضیحات',
        'update_priority'    => 'اولویت',
        'add_label'          => 'لیبل',
        'remove_label'       => 'حذف لیبل',
        'update_due_date'    => 'سررسید',
        'clear_due_date'     => 'حذف سررسید',
        'assign_user'        => 'مسئول',
        'change_status'      => 'وضعیت',
    ];

    public const MAX = 9;

    private const PRIO = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'];

    /**
     * اعتبارسنجی خروجی مدل. $ctx: ['labels' => [ref => [id, title]], 'members' => [ref => [id, name]], 'statuses' => [key => label], 'project_id' => ?int]
     * @return array{0: array[], 1: array[]} [عملیات معتبر، ردشده‌ها (['type','label','message'])]
     */
    public static function validate(array $raw, array $ctx): array
    {
        $ok = $bad = [];
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Tehran'));
        foreach (array_slice(array_values($raw), 0, self::MAX + 3) as $a) {
            if (!is_array($a)) {
                continue;
            }
            $type = is_string($a['type'] ?? null) ? $a['type'] : (is_string($a['action'] ?? null) ? $a['action'] : '');
            if (!isset(self::TYPES[$type])) {
                $bad[] = ['type' => 'unknown', 'label' => 'عملیات ناشناخته', 'message' => 'این کار در محدوده‌ی کارلیکس نیست و انجام نشد.'];
                continue;
            }
            $label = self::TYPES[$type];
            $err = static function (string $m) use (&$bad, $type, $label): void {
                $bad[] = ['type' => $type, 'label' => $label, 'message' => $m];
            };
            switch ($type) {
                case 'update_title':
                    $v = self::text($a['value'] ?? $a['title'] ?? null, 255);
                    $v === '' ? $err('عنوان جدید مشخص نبود.') : $ok[$type] = ['type' => $type, 'value' => $v];
                    break;
                case 'update_description':
                    $v = self::text($a['value'] ?? $a['description'] ?? null, 5000, true);
                    $mode = ($a['mode'] ?? 'replace') === 'append' ? 'append' : 'replace';
                    $v === '' ? $err('متن توضیحات مشخص نبود.') : $ok[$type] = ['type' => $type, 'value' => $v, 'mode' => $mode];
                    break;
                case 'update_priority':
                    $pct = isset($a['percent']) && is_numeric($a['percent']) ? Priority::percent($a['percent']) : null;
                    $lvl = is_string($a['value'] ?? null) && isset(self::PRIO[$a['value']]) ? $a['value'] : null;
                    if ($pct === null && $lvl === null) {
                        $err('اولویت باید «کم»، «متوسط»، «زیاد» یا درصدی بین ۰ تا ۱۰۰ باشد.');
                    } else {
                        $ok[$type] = ['type' => $type, 'value' => $lvl, 'percent' => $pct];
                    }
                    break;
                case 'add_label':
                    $ref = is_string($a['label'] ?? null) ? trim($a['label']) : '';
                    $hit = $ctx['labels'][$ref] ?? self::byName($ctx['labels'], $ref);
                    if ($hit === null) {
                        $err($ref !== '' && !isset($ctx['labels'][$ref]) ? 'لیبل «' . self::text($ref, 60) . '» در لیبل‌های این پروژه نیست (کارلیکس لیبل جدید نمی‌سازد).' : 'لیبل مشخص نبود.');
                    } else {
                        unset($ok['remove_label']);
                        $ok[$type] = ['type' => $type, 'id' => $hit[0], 'title' => $hit[1]];
                    }
                    break;
                case 'remove_label':
                    if (!isset($ok['add_label'])) {
                        $ok[$type] = ['type' => $type];
                    }
                    break;
                case 'update_due_date':
                    $date = is_string($a['date'] ?? null) ? trim($a['date']) : '';
                    $time = is_string($a['time'] ?? null) && trim($a['time']) !== '' ? trim($a['time']) : null;
                    $d = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? Validate::date($date) : null;
                    $t = $time !== null ? Validate::time(preg_match('/^\d{1,2}:\d{2}$/', $time) ? str_pad($time, 5, '0', STR_PAD_LEFT) : '') : null;
                    if ($d === null) {
                        $err('تاریخ سررسید قابل تشخیص نبود.');
                    } elseif ($time !== null && $t === null) {
                        $err('ساعت سررسید قابل تشخیص نبود.');
                    } else {
                        $diff = (int) $today->diff(new \DateTimeImmutable($d, new \DateTimeZone('Asia/Tehran')))->format('%r%a');
                        if ($diff < -366 || $diff > 3660) {
                            $err('تاریخ سررسید خارج از بازه‌ی معقول است.');
                        } else {
                            unset($ok['clear_due_date']);
                            $ok[$type] = ['type' => $type, 'date' => $d, 'time' => $t];
                        }
                    }
                    break;
                case 'clear_due_date':
                    if (!isset($ok['update_due_date'])) {
                        $ok[$type] = ['type' => $type];
                    }
                    break;
                case 'assign_user':
                    $ref = is_string($a['user'] ?? null) ? trim($a['user']) : '';
                    $hit = $ctx['members'][$ref] ?? self::byName($ctx['members'], $ref);
                    $hit === null ? $err('کاربر «' . self::text($ref, 60) . '» در اعضای قابل واگذاری این تسک پیدا نشد.') : $ok[$type] = ['type' => $type, 'id' => $hit[0], 'name' => $hit[1]];
                    break;
                case 'change_status':
                    $st = is_string($a['status'] ?? null) ? trim($a['status']) : '';
                    if ($st === 'complete') {
                        $st = 'done';
                    }
                    isset($ctx['statuses'][$st]) || ($st === 'done' && $ctx['project_id'] === null)
                        ? $ok[$type] = ['type' => $type, 'status' => $st]
                        : $err('وضعیت درخواستی در بورد این تسک وجود ندارد.');
                    break;
            }
        }
        // ترتیب ثابت اجرا
        $sorted = [];
        foreach (array_keys(self::TYPES) as $k) {
            if (isset($ok[$k])) {
                $sorted[] = $ok[$k];
            }
        }
        return [$sorted, $bad];
    }

    /**
     * اجرای یک عملیات معتبر با دسترسی $actor. خروجی: ['ok' => bool, 'partial' => bool, 'text' => string, 'expect' => ?array]
     * expect = مقدارهایی که بعد از اجرا باید در ردیف تسک دیده شوند (برای بررسی نهایی).
     */
    public static function run(array $a, int $actor, int $taskId): array
    {
        $t = TaskModel::raw($taskId);
        if ($t === null) {
            return self::fail('تسک دیگر در دسترس نیست.');
        }
        $upd = static fn(array $in) => TaskService::update($actor, $taskId, $in, 'karlix');
        switch ($a['type']) {
            case 'update_title':
                $r = $upd(['title' => $a['value']]);
                return $r['ok'] ? self::done('عنوان به «' . $a['value'] . '» تغییر کرد.', ['title' => $a['value']]) : self::fail($r['message']);
            case 'update_description':
                $v = $a['mode'] === 'append' && trim((string) $t['description']) !== '' ? rtrim((string) $t['description']) . "\n\n" . $a['value'] : $a['value'];
                if (mb_strlen($v) > 10000) {
                    return self::fail('توضیحات بیش از حد طولانی می‌شد.');
                }
                $r = $upd(['description' => $v]);
                return $r['ok'] ? self::done($a['mode'] === 'append' ? 'متن به توضیحات اضافه شد.' : 'توضیحات به‌روزرسانی شد.', ['description' => trim($v)]) : self::fail($r['message']);
            case 'update_priority':
                $percent = Priority::projectType($t['project_id'] !== null ? (int) $t['project_id'] : null) === 'percent';
                if ($percent && $a['percent'] !== null) {
                    $r = $upd(['priority_percent' => $a['percent']]);
                    return $r['ok'] ? self::done('اولویت روی ' . fa_num($a['percent']) . '٪ تنظیم شد.', ['priority_percent' => $a['percent']]) : self::fail($r['message']);
                }
                $lvl = $a['value'] ?? Priority::levelOf((int) $a['percent']);
                $r = $upd(['priority' => $lvl]);
                return $r['ok'] ? self::done('اولویت «' . self::PRIO[$lvl] . '» شد.', ['priority' => $lvl]) : self::fail($r['message']);
            case 'add_label':
                $prev = $t['subject_id'] !== null && (int) $t['subject_id'] !== $a['id'] ? (string) DB::value('SELECT title FROM subjects WHERE id = ?', [(int) $t['subject_id']]) : '';
                $r = $upd(['subject_id' => $a['id']]);
                return $r['ok'] ? self::done('لیبل «' . $a['title'] . '» گذاشته شد' . ($prev !== '' ? ' (هر تسک یک لیبل دارد؛ جای «' . $prev . '» را گرفت).' : '.'), ['subject_id' => $a['id']]) : self::fail($r['message']);
            case 'remove_label':
                if ($t['subject_id'] === null) {
                    return self::done('این تسک لیبلی نداشت.', null);
                }
                $r = $upd(['subject_id' => '']);
                return $r['ok'] ? self::done('لیبل تسک برداشته شد.', ['subject_id' => null]) : self::fail($r['message']);
            case 'update_due_date':
                $in = ['due_date' => $a['date']] + ($a['time'] !== null ? ['due_time' => $a['time']] : []);
                $r = $upd($in);
                $fa = jdate($a['date'] . ' 00:00:00', false, true) . ($a['time'] !== null ? ' ساعت ' . fa_num(substr($a['time'], 0, 5)) : '');
                return $r['ok'] ? self::done('سررسید روی ' . $fa . ' تنظیم شد.', ['due_date' => $a['date']] + ($a['time'] !== null ? ['due_time' => $a['time']] : [])) : self::fail($r['message']);
            case 'clear_due_date':
                $r = $upd(['due_date' => '']);
                return $r['ok'] ? self::done('سررسید تسک برداشته شد.', ['due_date' => null]) : self::fail($r['message']);
            case 'assign_user':
                $r = $upd(['assignee_id' => $a['id']]);
                return $r['ok'] ? self::done('تسک به ' . $a['name'] . ' واگذار شد.', ['assignee_id' => $a['id']]) : self::fail($r['message']);
            case 'change_status':
                return self::status($t, $a['status'], $actor);
        }
        return self::fail('عملیات پشتیبانی نمی‌شود.');
    }

    /**
     * تغییر وضعیت با همان قواعد بورد. «انجام‌شده»: اگر تسک در «بررسی سرپرست» است = تأیید (مجوز تکمیل)؛
     * وگرنه همان تیک «انجام شد» (BoardService::deliver): دارنده‌ی مجوز تأیید مستقیم تکمیل می‌کند و بقیه به «بررسی سرپرست» می‌فرستند.
     */
    private static function status(array $t, string $to, int $actor): array
    {
        $id = (int) $t['id'];
        $pid = $t['project_id'] !== null ? (int) $t['project_id'] : null;
        if ($pid === null) {
            if ($to !== 'done') {
                return self::fail('تسک بدون پروژه بورد ندارد؛ فقط «انجام‌شده» قابل تنظیم است.');
            }
            if ((int) $t['is_completed'] === 1) {
                return self::done('تسک از قبل انجام‌شده بود.', null);
            }
            $r = TaskService::toggle($actor, $id);
            return $r['ok'] ? self::done('تسک انجام‌شده ثبت شد.', ['is_completed' => 1]) : self::fail($r['message']);
        }
        $from = BoardService::statusOf($t);
        $label = static fn(string $k): string => BoardConfig::label($pid, $k);
        if ($from === $to) {
            return self::done('تسک همین حالا در «' . $label($to) . '» است.', null);
        }
        if ($to === 'done' && $from !== 'review') {
            $r = BoardService::deliver($actor, $id);
            if (!$r['ok']) {
                return self::fail($r['message']);
            }
            $now = (string) ($r['data']['status'] ?? '');
            return $now === 'done'
                ? self::done('تسک انجام‌شده ثبت شد.', ['is_completed' => 1])
                : ['ok' => true, 'partial' => true, 'text' => 'شما دسترسی تکمیل این تسک را ندارید؛ تسک برای «' . $label('review') . '» فرستاده شد تا سرپرست تأیید کند.', 'expect' => ['board_status' => 'review']];
        }
        $r = BoardService::setStatus($actor, $id, $to);
        if (!$r['ok']) {
            return self::fail($to === 'done' && ($r['code'] ?? '') === 'FORBIDDEN'
                ? 'این تسک منتظر تأیید سرپرست است و شما دسترسی تأیید و تکمیل آن را ندارید.' : $r['message']);
        }
        return self::done('وضعیت به «' . $label($to) . '» تغییر کرد.', $to === 'done' ? ['is_completed' => 1] : ['board_status' => $to]);
    }

    /** بعد از اجرا: آیا ردیف تسک واقعاً همان مقدار مورد انتظار را دارد؟ */
    public static function verify(array $expect, array $row): bool
    {
        foreach ($expect as $k => $v) {
            $cur = $row[$k] ?? null;
            if ($k === 'due_time') {
                $cur = $cur !== null ? substr((string) $cur, 0, 5) : null;
                $v = $v !== null ? substr((string) $v, 0, 5) : null;
            }
            if ($v === null ? $cur !== null : (string) $cur !== (string) $v) {
                return false;
            }
        }
        return true;
    }

    private static function done(string $text, ?array $expect): array
    {
        return ['ok' => true, 'partial' => false, 'text' => $text, 'expect' => $expect];
    }

    private static function fail(string $text): array
    {
        return ['ok' => false, 'partial' => false, 'text' => $text, 'expect' => null];
    }

    /** متن ساده از خروجی مدل: فقط رشته، بدون نویسه‌های کنترلی، با سقف طول */
    public static function text($v, int $max, bool $multiline = false): string
    {
        if (!is_string($v)) {
            return '';
        }
        $v = preg_replace($multiline ? '/[\x00-\x09\x0B-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', ' ', $v) ?? '';
        $v = trim($multiline ? preg_replace("/\n{3,}/", "\n\n", $v) ?? '' : preg_replace('/\s+/u', ' ', $v) ?? '');
        return mb_substr($v, 0, $max);
    }

    /** پیدا کردن در فهرست [ref => [id, name]] با نام (اگر مدل به‌جای ref نام نوشته باشد) — فقط تطابق دقیق یکتا */
    private static function byName(array $list, string $name): ?array
    {
        $n = Assistant::norm($name);
        if ($n === '') {
            return null;
        }
        $hits = array_values(array_filter($list, static fn($x) => Assistant::norm($x[1]) === $n));
        return count($hits) === 1 ? $hits[0] : null;
    }
}
