<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Jalali;
use App\Models\UserModel;

/** ارسال اعلان‌های سیستمی از طریق «ربات تسکام» در چت. */
final class Notifier
{
    public static function send(int $toUserId, string $text): void
    {
        $bot = UserModel::botId();
        if ($toUserId <= 0 || $toUserId === $bot) {
            return;
        }
        try {
            \App\Models\ChatModel::create($bot, $toUserId, $text);
        } catch (\Throwable $e) {
            \App\Core\ErrorHandler::log($e);
        }
    }

    /** اعلان ناشی از کار یک کاربر: اگر بین دو طرف مسدودی باشد ارسال نمی‌شود (مسیر مزاحمت بعد از مسدودسازی بسته بماند). */
    private static function sendFrom(int $actorId, int $toUserId, string $text): void
    {
        if ($actorId > 0 && $actorId !== $toUserId && Access::isBlockedBetween($actorId, $toUserId)) {
            return;
        }
        self::send($toUserId, $text);
    }

    public static function taskAssigned(array $task, int $actorId, ?string $projectTitle): void
    {
        // تسک گروهی (v5.10): برای همه‌ی اعضای گروه (جز خود انجام‌دهنده)
        $gid = (int) ($task['assignee_group_id'] ?? 0);
        if ($gid > 0) {
            $g = \App\Models\GroupModel::find($gid);
            if ($g === null || empty($task['share_token'])) {
                return;
            }
            foreach (\App\Models\GroupModel::memberIds($gid) as $m) {
                if ($m !== $actorId) {
                    self::taskAssignedTo($task, $actorId, $projectTitle, $m, $g['title'], (int) $g['head_id'] === $m, !empty($task['group_head_approval']));
                }
            }
            return;
        }
        $assignee = (int) ($task['assignee_id'] ?? 0);
        if ($assignee <= 0 || $assignee === $actorId || empty($task['share_token'])) {
            return;
        }
        self::taskAssignedTo($task, $actorId, $projectTitle, $assignee, null, false, false);
    }

    private static function taskAssignedTo(array $task, int $actorId, ?string $projectTitle, int $assignee, ?string $group, bool $isHead, bool $headApproval): void
    {
        $actor = UserModel::find($actorId);
        $pr = ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'][$task['priority']] ?? $task['priority'];
        if (isset($task['priority_percent']) && Priority::projectType(isset($task['project_id']) ? (int) $task['project_id'] : null) === 'percent') {
            $pr = fa_num((int) $task['priority_percent']) . '٪';
        }
        $lines = [$group !== null ? '📌 وظیفه جدیدی برای گروه «' . $group . '» ایجاد شد' : '📌 وظیفه جدیدی برای شما ایجاد شد', '', 'عنوان: ' . $task['title'], 'ایجاد کننده: ' . ($actor ? full_name($actor) : 'کاربر')];
        if ($group !== null) {
            $lines[] = $headApproval ? ($isHead ? 'این تسک با تیک شما (سرگروه) تحویل می‌شود.' : 'هر عضو تیک خودش را می‌زند؛ تحویل نهایی با تیک سرگروه است.')
                : 'هر عضو تیک خودش را می‌زند؛ تسک وقتی برای بررسی می‌رود که همه‌ی اعضا تیک زده باشند.';
        }
        if ($projectTitle) {
            $lines[] = 'پروژه: ' . $projectTitle;
        }
        $lines[] = 'اولویت: ' . $pr;
        if (!empty($task['due_date'])) {
            $d = Jalali::format($task['due_date'] . ' 00:00:00', false);
            if (!empty($task['due_time'])) {
                $d .= ' - ' . fa_num(substr((string) $task['due_time'], 0, 5));
            }
            $lines[] = 'مهلت: ' . $d;
        }
        if (!empty($task['description'])) {
            $lines[] = '';
            $lines[] = 'توضیحات: ' . mb_substr(trim((string) $task['description']), 0, 200, 'UTF-8');
        }
        self::sendFrom($actorId, $assignee, implode("\n", $lines) . "\n\n[[task:" . $task['share_token'] . ']]');
    }

    public static function noteMention(array $task, int $actorId, string $noteText, int $mentionedId, bool $mentioned = false): void
    {
        $actor = UserModel::find($actorId);
        $msg = ($mentioned ? '📣 شما در یادداشت وظیفه «' . $task['title'] . '» منشن شدید' : '📝 یادداشت جدید در وظیفه «' . $task['title'] . '»') . "\n\nاز طرف: " . ($actor ? full_name($actor) : 'کاربر')
            . "\n\n" . mb_substr(trim($noteText), 0, 220, 'UTF-8') . "\n\n[[task:" . $task['share_token'] . ']]';
        self::sendFrom($actorId, $mentionedId, $msg);
    }

    public static function callAssigned(array $call, int $actorId): void
    {
        $assignee = (int) ($call['assignee_id'] ?? 0);
        if ($assignee <= 0 || $assignee === $actorId || empty($call['share_token'])) {
            return;
        }
        $actor = UserModel::find($actorId);
        $lines = ['📞 درخواست تماس جدیدی برای شما ثبت شد', '', 'نام: ' . trim($call['first_name'] . ' ' . $call['last_name']), 'موبایل: ' . $call['mobile']];
        if (!empty($call['store'])) {
            $lines[] = 'فروشگاه: ' . $call['store'];
        }
        $lines[] = 'ثبت‌کننده: ' . ($actor ? full_name($actor) : 'کاربر');
        self::sendFrom($actorId, $assignee, implode("\n", $lines) . "\n\n[[call:" . $call['share_token'] . ']]');
    }
    /** پیوند اشتراکی تسک برای پیام ربات */
    private static function token(array $task): string
    {
        return (string) ($task['share_token'] ?: \App\Models\TaskModel::ensureShareToken((int) $task['id']));
    }

    /** تسک به «بررسی سرپرست» رسید → برای همه‌ی کسانی که می‌توانند تکمیلش کنند */
    public static function taskInReview(array $task, int $actorId, array $toIds): void
    {
        if (!$toIds) {
            return;
        }
        $actor = UserModel::find($actorId);
        $pt = !empty($task['project_id']) ? (string) \App\Core\DB::value('SELECT title FROM projects WHERE id = ?', [(int) $task['project_id']]) : '';
        $msg = '🔎 تسکی برای بررسی سرپرست آماده است' . "\n\nعنوان: " . $task['title'] . ($pt !== '' ? "\nپروژه: " . $pt : '')
            . "\nارسال‌کننده: " . ($actor ? full_name($actor) : 'کاربر') . "\n\nلطفاً بررسی کنید و تأیید (انجام‌شده) یا برای اصلاح به «بازبینی مسئول» برگردانید."
            . "\n\n[[task:" . self::token($task) . ']]';
        foreach (array_unique($toIds) as $uid) {
            self::sendFrom($actorId, (int) $uid, $msg);
        }
    }

    /** سرپرست تسک را به «بازبینی مسئول» برگرداند → برای مسئول تسک (یا سازنده اگر مسئول ندارد) */
    public static function taskReturned(array $task, int $actorId, ?string $reason, ?array $only = null): void
    {
        // تسک گروهی (v5.10): همه‌ی اعضای گروه؛ با انتخاب سرپرست (v5.12) فقط اعضای انتخاب‌شده + سرگروه
        $gid = (int) ($task['assignee_group_id'] ?? 0);
        $to = $gid > 0 ? \App\Models\GroupModel::memberIds($gid) : [(int) ($task['assignee_id'] ?: $task['user_id'])];
        if ($gid > 0 && $only !== null) {
            $head = (int) (\App\Models\GroupModel::find($gid)['head_id'] ?? 0);
            $to = array_values(array_intersect($to, array_merge(array_map('intval', $only), [$head])));
        }
        $actor = UserModel::find($actorId);
        $msg = '↩️ تسک «' . $task['title'] . '» برای اصلاح به «بازبینی مسئول» برگشت' . "\n\nاز طرف: " . ($actor ? full_name($actor) : 'سرپرست')
            . ($reason ? "\nعلت: " . $reason : '') . "\n\nپس از اصلاح، تسک را دوباره به «بررسی سرپرست» بفرستید.\n\n[[task:" . self::token($task) . ']]';
        foreach (array_unique($to) as $u) {
            if ($u > 0 && $u !== $actorId) {
                self::sendFrom($actorId, $u, $msg);
            }
        }
    }

    /** وظیفه‌ی شخصی عضو روی تسک گروهی (v5.12) → پیام به همان عضو */
    public static function groupDuty(array $task, int $actorId, int $memberId, string $duty): void
    {
        if ($memberId === $actorId) {
            return;
        }
        $actor = UserModel::find($actorId);
        self::sendFrom($actorId, $memberId, '📝 وظیفه‌ی شما در تسک گروهی «' . $task['title'] . '»' . "\n\n" . $duty . "\n\nاز طرف: " . ($actor ? full_name($actor) : 'سرگروه') . "\n\n[[task:" . self::token($task) . ']]');
    }

    /** تسک گروهی با تأیید سرگروه: همه‌ی اعضا تیک زدند → پیام به سرگروه */
    public static function groupAllTicked(array $task, int $actorId, int $headId): void
    {
        if ($headId <= 0 || $headId === $actorId) {
            return;
        }
        self::sendFrom($actorId, $headId, '✅ همه‌ی اعضای گروه تسک «' . $task['title'] . '» را انجام‌شده علامت زدند؛ با تیک شما (سرگروه) تسک تحویل می‌شود.' . "\n\n[[task:" . self::token($task) . ']]');
    }
}
