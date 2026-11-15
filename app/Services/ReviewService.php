<?php
declare(strict_types=1);

namespace App\Services;

/**
 * «صندوق بررسی سرپرست» (v7.9): تحویل‌های منتظر تأیید کاربر جاری + تأیید گروهی.
 * دسترسی و منطق همان جریان فعلی است: صف از BoardService::reviewQueue (مجوز تکمیل + review → done)،
 * تأیید از BoardService::setStatus(done) و برگشت از همان مسیر /tasks/{id}/status (rework + reason + reason_category).
 * زمان انتظار = اکنون − لحظه‌ی ورود به review (tasks.delivered_at؛ با هر تحویل NOW ثبت می‌شود، board_moved_at پشتیبان).
 */
final class ReviewService
{
    /** سقف یک «تأیید گروهی» */
    public const BULK_MAX = 100;

    /** مهلت بررسی (ساعت)؛ قابل تنظیم با REVIEW_SLA_HOURS در .env (پیش‌فرض ۲۴) */
    public static function slaHours(): int
    {
        return max(1, min(24 * 30, (int) env('REVIEW_SLA_HOURS', 24)));
    }

    /** مدت انسانی: «۴۰ دقیقه»، «۳ ساعت»، «۲ روز»، «۱ هفته»، «۲ ماه» */
    public static function human(int $seconds): string
    {
        $m = intdiv(max(0, $seconds), 60);
        if ($m < 60) {
            return fa_num(max(1, $m)) . ' دقیقه';
        }
        $h = intdiv($m, 60);
        if ($h < 24) {
            return fa_num($h) . ' ساعت';
        }
        $d = intdiv($h, 24);
        if ($d < 7) {
            return fa_num($d) . ' روز';
        }
        if ($d < 30) {
            return fa_num(intdiv($d, 7)) . ' هفته';
        }
        return fa_num(intdiv($d, 30)) . ' ماه';
    }

    /** یک ردیف صف بررسی برای خروجی (وب و API؛ شناسه‌ها عمومی) */
    public static function item(array $t, int $now): array
    {
        $at = (string) ($t['delivered_at'] ?: $t['board_moved_at']);
        $ts = $at !== '' ? (int) strtotime($at) : $now;
        $wait = max(0, $now - $ts);
        $dl = substr($at !== '' ? $at : date('Y-m-d'), 0, 10);
        $late = $t['due_date'] && $dl > $t['due_date'] ? (int) round((strtotime($dl) - strtotime((string) $t['due_date'])) / 86400) : 0;
        $name = full_name($t);
        return [
            'id' => pid('tasks', $t),
            'title' => (string) $t['title'],
            'assignee' => ['name' => $name, 'initial' => initial($name), 'avatar_url' => avatar_url($t['avatar'] ?? null)],
            'project' => $t['project_id'] ? ['id' => pid('projects', (int) $t['project_id']), 'title' => (string) $t['project_title']] : null,
            'delivered_at' => $at !== '' ? date('c', $ts) : null,
            'delivered_ts' => $ts,
            'wait_seconds' => $wait,
            'wait_label' => self::human($wait),
            'over_sla' => $wait > self::slaHours() * 3600,
            'note' => trim((string) ($t['last_note'] ?? '')) !== '' ? (string) $t['last_note'] : null,
            'attachments' => (int) ($t['files_count'] ?? 0),
            'due' => !$t['due_date'] ? ['state' => 'none', 'late_days' => 0] : ['state' => $late > 0 ? 'late' : 'on_time', 'late_days' => $late],
        ];
    }

    /**
     * تحویل‌های منتظر بررسی کاربر (بیشترین انتظار اول) + خلاصه.
     * @return array{items:array, count:int, over_sla_count:int, late:int, on_time:int, oldest_seconds:int, oldest_label:string, sla_hours:int, projects:array}
     */
    public static function pending(int $uid): array
    {
        return self::fromRows(BoardService::reviewQueue($uid, 500));
    }

    /** خروجی صف از ردیف‌های BoardService::reviewQueue (پیشخوان همان ردیف‌ها را برای فهرست و خلاصه به کار می‌برد) */
    public static function fromRows(array $rows): array
    {
        $now = time();
        $items = array_map(static fn($t) => self::item($t, $now), $rows);
        $projects = [];
        foreach ($items as $i) {
            if ($i['project']) {
                $projects[$i['project']['id']] = $i['project']['title'];
            }
        }
        $oldest = $items ? $items[0]['wait_seconds'] : 0;
        return [
            'items' => $items,
            'count' => count($items),
            'over_sla_count' => count(array_filter($items, static fn($i) => $i['over_sla'])),
            'late' => count(array_filter($items, static fn($i) => $i['due']['state'] === 'late')),
            'on_time' => count(array_filter($items, static fn($i) => $i['due']['state'] !== 'late')),
            'oldest_seconds' => $oldest,
            'oldest_label' => $items ? self::human($oldest) : '',
            'sla_hours' => self::slaHours(),
            'projects' => array_map(static fn($k, $v) => ['id' => $k, 'title' => $v], array_keys($projects), array_values($projects)),
        ];
    }

    /**
     * تأیید گروهی: برای هر تسک همان تأیید تکی (BoardService::setStatus → done؛ همان مجوز، تاریخچه و اعلان).
     * @param int[] $ids شناسه‌های عددی
     * @return array{ok:bool, message:string, code:string, http:int, data:array{approved:array, failed:array}}
     */
    public static function bulkApprove(int $uid, array $ids): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids)))), 0, self::BULK_MAX);
        if (!$ids) {
            return ['ok' => false, 'message' => 'هیچ تسکی برای تأیید انتخاب نشده است.', 'code' => 'BULK_EMPTY', 'http' => 422, 'data' => ['approved' => [], 'failed' => []]];
        }
        $ok = [];
        $fail = [];
        foreach ($ids as $id) {
            $t = \App\Models\TaskModel::raw($id);
            // ترتیب: دیدن تسک ← مجوز تأیید (همان شرط صف بررسی) ← وضعیت؛ تا وضعیت تسک به کسی که مجوز ندارد گفته نشود
            $r = $t === null || !Access::canTask($t, $uid, 'view') ? ['ok' => false, 'message' => 'تسک یافت نشد.', 'code' => 'TASK_NOT_FOUND']
                : (!Access::canTask($t, $uid, 'complete') || !in_array('done', Access::boardTargets($t, $uid, 'review', ['done']), true)
                    ? ['ok' => false, 'message' => 'شما دسترسی لازم برای تأیید این تسک را ندارید.', 'code' => 'FORBIDDEN']
                : (BoardService::statusOf($t) !== 'review' ? ['ok' => false, 'message' => 'این تسک دیگر منتظر بررسی نیست.', 'code' => 'NOT_IN_REVIEW']
                : BoardService::setStatus($uid, $id, 'done')));
            if ($r['ok']) {
                $ok[] = ['id' => pid('tasks', $id), 'title' => (string) $t['title']];
            } else {
                $fail[] = ['id' => pid('tasks', $id), 'title' => $t !== null && \App\Services\Access::canTask($t, $uid, 'view') ? (string) $t['title'] : null,
                    'message' => (string) $r['message'], 'code' => (string) ($r['code'] ?? 'FAILED')];
            }
        }
        $n = count($ok);
        $msg = $n ? fa_num($n) . ' تسک تأیید شد.' . ($fail ? ' ' . fa_num(count($fail)) . ' تسک تأیید نشد.' : '') : 'هیچ تسکی تأیید نشد: ' . ($fail[0]['message'] ?? '');
        return ['ok' => $n > 0, 'message' => $msg, 'code' => $n ? '' : ($fail[0]['code'] ?? 'FORBIDDEN'), 'http' => $n ? 200 : (($fail[0]['code'] ?? '') === 'FORBIDDEN' ? 403 : 422),
            'data' => ['approved' => $ok, 'failed' => $fail]];
    }
}
