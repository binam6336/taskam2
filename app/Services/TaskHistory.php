<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * تاریخچه‌ی تغییرات تسک.
 * ثبت: تغییر فیلدها (TaskService::update)، انجام/بازگشت، بورد پروژه، ویرایش/حذف یادداشت و حذف پیوست در task_events.
 * نمایش: رویدادهای ثبت‌شده + «ایجاد تسک»، یادداشت‌ها و پیوست‌ها از جدول‌های خودشان (برای تسک‌های قبل از این قابلیت هم کامل است).
 * ثبت تاریخچه هیچ‌وقت نباید عملیات اصلی را خراب کند؛ خطاهایش فقط لاگ می‌شوند.
 */
final class TaskHistory
{
    private const PRIO = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'];

    public static function log(int $taskId, ?int $actor, string $type, array $data = []): void
    {
        try {
            DB::insert('task_events', ['task_id' => $taskId, 'user_id' => $actor, 'type' => $type, 'data' => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null]);
        } catch (\Throwable $e) {
            error_log('task history: ' . $e->getMessage());
        }
    }

    /** مقایسه‌ی ردیف قبلی تسک با تغییرات ذخیره‌شده ($set در TaskService::update) */
    public static function changes(array $old, array $set, int $actor): void
    {
        $id = (int) $old['id'];
        if (array_key_exists('title', $set)) {
            self::log($id, $actor, 'title', ['from' => $old['title'], 'to' => $set['title']]);
        }
        if (array_key_exists('description', $set)) {
            self::log($id, $actor, 'description', ['to' => mb_substr((string) $set['description'], 0, 2000)]);
        }
        if (array_key_exists('priority', $set) || array_key_exists('priority_percent', $set)) {
            $pct = $old['project_id'] && DB::value('SELECT priority_type FROM projects WHERE id = ?', [(int) $old['project_id']]) === 'percent';
            $fmt = static fn($lvl, $p) => $pct && $p !== null ? fa_num((int) $p) . '٪' : (self::PRIO[$lvl] ?? (string) $lvl);
            $from = $fmt($old['priority'], $old['priority_percent']);
            $to = $fmt($set['priority'] ?? $old['priority'], $set['priority_percent'] ?? $old['priority_percent']);
            if ($from !== $to) {
                self::log($id, $actor, 'priority', ['from' => $from, 'to' => $to]);
            }
        }
        if (array_key_exists('due_date', $set) || array_key_exists('due_time', $set)) {
            $fmt = static function ($d, $t): string {
                if (!$d) {
                    return '';
                }
                return jdate($d . ' 00:00:00', false) . ($t ? ' ساعت ' . fa_num(substr((string) $t, 0, 5)) : '');
            };
            $from = $fmt($old['due_date'], $old['due_time']);
            $to = $fmt(array_key_exists('due_date', $set) ? $set['due_date'] : $old['due_date'], array_key_exists('due_time', $set) ? $set['due_time'] : $old['due_time']);
            if ($from !== $to) {
                self::log($id, $actor, 'due', ['from' => $from, 'to' => $to]);
            }
        }
        if (array_key_exists('subject_id', $set)) {
            $t = static fn($sid) => $sid ? (string) DB::value('SELECT title FROM subjects WHERE id = ?', [(int) $sid]) : '';
            self::log($id, $actor, 'label', ['from' => $t($old['subject_id']), 'to' => $t($set['subject_id'])]);
        }
        if (array_key_exists('project_id', $set)) {
            $t = static fn($pid) => $pid ? (string) DB::value('SELECT title FROM projects WHERE id = ?', [(int) $pid]) : '';
            self::log($id, $actor, 'project', ['from' => $t($old['project_id']), 'to' => $t($set['project_id'])]);
        }
        if (array_key_exists('assignee_id', $set)) {
            self::log($id, $actor, 'assignee', ['from' => $old['assignee_id'] ? (int) $old['assignee_id'] : null, 'to' => (int) $set['assignee_id']]);
        }
    }

    /** همه‌ی تاریخچه‌ی یک تسک، از قدیم به جدید */
    public static function forTask(array $t): array
    {
        $id = (int) $t['id'];
        $items = [];
        $add = static function (string $type, ?int $uid, string $at, array $data = []) use (&$items): void {
            $items[] = ['type' => $type, 'uid' => $uid, 'at' => $at, 'data' => $data];
        };
        $add('created', (int) $t['user_id'], (string) $t['created_at'], ['assignee' => $t['assignee_id'] ? (int) $t['assignee_id'] : null]);

        $events = [];
        try {
            $events = DB::all('SELECT user_id, type, data, created_at FROM task_events WHERE task_id = ? ORDER BY created_at, id', [$id]);
        } catch (\Throwable $e) {
            // جدول هنوز ساخته نشده
        }
        $hasDone = false;
        foreach ($events as $e) {
            $data = json_decode((string) $e['data'], true) ?: [];
            $hasDone = $hasDone || $e['type'] === 'completed' || ($e['type'] === 'board' && ($data['to'] ?? '') === 'done');
            $add((string) $e['type'], $e['user_id'] !== null ? (int) $e['user_id'] : null, (string) $e['created_at'], $data);
        }
        // تسک‌هایی که قبل از این قابلیت انجام شده‌اند
        if (!$hasDone && $t['completed_at']) {
            $add('completed', null, (string) $t['completed_at'], ['legacy' => true]);
        }
        foreach (DB::all('SELECT id, user_id, note, created_at, updated_at FROM task_notes WHERE task_id = ?', [$id]) as $n) {
            $files = array_column(DB::all('SELECT file_name FROM note_attachments WHERE note_id = ?', [(int) $n['id']]), 'file_name');
            $add('note', (int) $n['user_id'], (string) $n['created_at'], ['text' => (string) $n['note'], 'files' => $files, 'edited' => (bool) $n['updated_at']]);
        }
        foreach (DB::all('SELECT user_id, file_name, created_at FROM task_attachments WHERE task_id = ?', [$id]) as $f) {
            $add('file', (int) $f['user_id'], (string) $f['created_at'], ['name' => (string) $f['file_name']]);
        }
        // مسئول در لحظه‌ی ایجاد = «از»ِ اولین تغییر مسئول (اگر بعداً عوض شده باشد)
        foreach ($items as $i) {
            if ($i['type'] === 'assignee') {
                $items[0]['data']['assignee'] = $i['data']['from'] ?? null;
                break;
            }
        }
        usort($items, static fn($a, $b) => [$a['at'], $a['type'] === 'created' ? 0 : 1] <=> [$b['at'], $b['type'] === 'created' ? 0 : 1]);

        // کاربران (انجام‌دهنده و مسئول قبلی/جدید) با نام و آواتار
        $uids = [];
        foreach ($items as $i) {
            $uids[] = $i['uid'];
            if ($i['type'] === 'assignee') {
                $uids[] = $i['data']['from'] ?? null;
                $uids[] = $i['data']['to'] ?? null;
            }
            if ($i['type'] === 'created') {
                $uids[] = $i['data']['assignee'] ?? null;
            }
        }
        $uids = array_values(array_unique(array_filter($uids)));
        $users = [];
        if ($uids) {
            foreach (DB::all('SELECT id, first_name, last_name, avatar FROM users WHERE id IN (' . implode(',', array_fill(0, count($uids), '?')) . ')', $uids) as $u) {
                $users[(int) $u['id']] = ['name' => full_name($u), 'avatar' => avatar_url($u['avatar'])];
            }
        }
        $who = static fn($uid) => $uid && isset($users[$uid]) ? $users[$uid] : null;
        $labels = array_map(static fn($s) => $s['label'], BoardService::STATUSES) + ['archived' => 'آرشیو'];
        return array_map(static function (array $i) use ($who, $labels) {
            $d = $i['data'];
            if ($i['type'] === 'assignee') {
                $d['from_user'] = $who($d['from'] ?? null);
                $d['to_user'] = $who($d['to'] ?? null);
            }
            if ($i['type'] === 'created') {
                $d['assignee_user'] = $who($d['assignee'] ?? null);
            }
            if ($i['type'] === 'board') {
                $d['from'] = $labels[$d['from'] ?? ''] ?? '';
                $d['to'] = $labels[$d['to'] ?? ''] ?? '';
            }
            return ['type' => $i['type'], 'actor' => $who($i['uid']), 'at' => jdate($i['at']), 'data' => $d];
        }, $items);
    }
}
