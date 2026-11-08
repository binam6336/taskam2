<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Models\GroupModel;

/**
 * دپارتمان / گروه (v5.10) — ساخت، ویرایش و حذف گروه‌های یک پروژه.
 * مدیریت: سازنده‌ی پروژه و هر عضوی که در پروژه مجوز «افزودن کاربر» (add_member) دارد.
 * اعضا فقط از اعضای همان پروژه؛ سرگروه (اختیاری) باید عضو گروه باشد.
 * خروج/خارج شدن از پروژه = خروج از همه‌ی گروه‌های آن پروژه (onMemberLeft).
 */
final class GroupService
{
    private static function res(bool $ok, string $msg = '', string $code = '', int $http = 200, $data = null): array
    {
        return ['ok' => $ok, 'message' => $msg, 'code' => $code, 'http' => $http, 'data' => $data];
    }

    private static function err(string $msg, string $code, int $http = 422): array
    {
        return self::res(false, $msg, $code, $http);
    }

    /**
     * مدیریت گروه‌های پروژه (v5.12): سرپرست به بالا — سازنده‌ی پروژه، دارنده‌ی مجوز «افزودن کاربر»،
     * نقش‌های سرپرست / مدیر واحد / مدیر کل، یا هر عضوی که دسترسی تأیید تسک دارد (تکمیل تسک + بررسی سرپرست ← انجام‌شده).
     */
    public static function canManage(int $projectId, int $uid): bool
    {
        if ($projectId <= 0 || !Access::isProjectMember($projectId, $uid)) {
            return false;
        }
        if (Access::projectCan($projectId, $uid, 'add_member')) {
            return true;
        }
        $row = DB::one('SELECT permissions, task_permissions, board_permissions, board_view_users FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $uid]);
        if ($row === null) {
            return false;
        }
        if (in_array(Access::memberRole($row), ['admin', 'unit_manager', 'supervisor'], true)) {
            return true;
        }
        return in_array('complete', Access::projectTaskPerms($projectId, $uid), true) && in_array('to_done', Access::projectBoardPerms($projectId, $uid), true);
    }

    /** دیدن گروه‌های پروژه: هر عضو پروژه */
    public static function canView(int $projectId, int $uid): bool
    {
        return $projectId > 0 && Access::isProjectMember($projectId, $uid);
    }

    public static function create(int $actor, int $projectId, array $in): array
    {
        if (!Access::isProjectMember($projectId, $actor)) {
            return self::err('پروژه‌ی انتخاب‌شده معتبر نیست یا عضو آن نیستید.', 'PROJECT_INVALID', 404);
        }
        if (!self::canManage($projectId, $actor)) {
            return self::err('ساخت گروه در این پروژه با سرپرست و نقش‌های بالاتر (یا مجوز «افزودن کاربر») است.', 'FORBIDDEN', 403);
        }
        if (BlockService::isBlocked('project', $projectId)) {
            return self::err('این پروژه مسدود شده است.', 'PROJECT_BLOCKED', 403);
        }
        if ((int) DB::value('SELECT COUNT(*) FROM project_groups WHERE project_id = ?', [$projectId]) >= GroupModel::MAX_GROUPS) {
            return self::err('سقف تعداد گروه‌های این پروژه پر شده است.', 'GROUP_LIMIT', 422);
        }
        $v = self::validate($projectId, $in, null);
        if (isset($v['error'])) {
            return $v['error'];
        }
        $id = DB::insert('project_groups', ['project_id' => $projectId, 'title' => $v['title'], 'description' => $v['description'], 'head_id' => $v['head'], 'created_by' => $actor]);
        self::syncMembers($id, $v['members']);
        return self::res(true, 'گروه «' . $v['title'] . '» ساخته شد.', '', 201, ['id' => $id]);
    }

    public static function update(int $actor, int $groupId, array $in): array
    {
        $g = GroupModel::find($groupId);
        if ($g === null || !self::canView((int) $g['project_id'], $actor)) {
            return self::err('گروه یافت نشد.', 'GROUP_NOT_FOUND', 404);
        }
        if (!self::canManage((int) $g['project_id'], $actor)) {
            return self::err('ویرایش گروه‌های این پروژه با سرپرست و نقش‌های بالاتر (یا مجوز «افزودن کاربر») است.', 'FORBIDDEN', 403);
        }
        $v = self::validate((int) $g['project_id'], $in, $groupId);
        if (isset($v['error'])) {
            return $v['error'];
        }
        DB::update('project_groups', ['title' => $v['title'], 'description' => $v['description'], 'head_id' => $v['head']], 'id = ?', [$groupId]);
        $gone = array_values(array_diff(GroupModel::memberIds($groupId), $v['members']));
        self::syncMembers($groupId, $v['members']);
        // خروج عضو از گروه (v5.12): روی هر تسک باز گروه در تاریخچه ثبت می‌شود
        if ($gone) {
            $names = array_map('full_name', DB::all('SELECT first_name, last_name FROM users WHERE id IN (' . DB::in($gone) . ')', $gone));
            foreach (DB::column('SELECT id FROM tasks WHERE assignee_group_id = ? AND is_completed = 0 AND blocked_at IS NULL', [$groupId]) as $tid) {
                TaskHistory::log((int) $tid, $actor, 'group_member_removed', ['group' => $v['title'], 'names' => $names]);
                DB::run('DELETE FROM task_group_duties WHERE task_id = ? AND user_id IN (' . DB::in($gone) . ')', array_merge([(int) $tid], $gone));
            }
        }
        // روند تحویل تسک‌های باز (v5.13) تابع سرگروه فعلی است؛ ستون group_head_approval آینه‌ی «گروه سرگروه دارد»
        DB::run('UPDATE tasks SET group_head_approval = ? WHERE assignee_group_id = ? AND is_completed = 0', [$v['head'] === null ? 0 : 1, $groupId]);
        return self::res(true, 'گروه «' . $v['title'] . '» ذخیره شد.', '', 200, ['id' => $groupId]);
    }

    /**
     * مسدود کردن گروه به‌جای حذف (v5.12): هر گروهی، حتی با تسک فعال. تسک‌های فعلی گروه عادی ادامه پیدا می‌کنند و
     * به همان گروه می‌مانند؛ فقط تسک جدید یا واگذاری تازه به گروه مسدود ممکن نیست. با «رفع مسدودی» برمی‌گردد.
     */
    public static function setBlocked(int $actor, int $groupId, bool $block): array
    {
        $g = GroupModel::find($groupId);
        if ($g === null || !self::canView((int) $g['project_id'], $actor)) {
            return self::err('گروه یافت نشد.', 'GROUP_NOT_FOUND', 404);
        }
        if (!self::canManage((int) $g['project_id'], $actor)) {
            return self::err('ویرایش گروه‌های این پروژه با سرپرست و نقش‌های بالاتر (یا مجوز «افزودن کاربر») است.', 'FORBIDDEN', 403);
        }
        if ($block === ($g['blocked_at'] !== null)) {
            return self::res(true, $block ? 'گروه همین حالا مسدود است.' : 'گروه مسدود نیست.');
        }
        DB::update('project_groups', $block ? ['blocked_at' => date('Y-m-d H:i:s'), 'blocked_by' => $actor] : ['blocked_at' => null, 'blocked_by' => null], 'id = ?', [$groupId]);
        $open = (int) DB::value('SELECT COUNT(*) FROM tasks WHERE assignee_group_id = ? AND is_completed = 0 AND blocked_at IS NULL', [$groupId]);
        return self::res(true, $block
            ? 'گروه «' . $g['title'] . '» مسدود شد؛ دیگر تسک جدید نمی‌گیرد' . ($open ? ' و ' . fa_num($open) . ' تسک باز آن عادی ادامه پیدا می‌کند.' : '.')
            : 'مسدودی گروه «' . $g['title'] . '» برداشته شد.');
    }

    /** عضوهایی که با این ویرایش از گروه بیرون می‌روند و در تسک‌های باز گروه سهم دارند (هشدار پیش از ذخیره) */
    public static function removalImpact(int $actor, int $groupId, array $members): array
    {
        $g = GroupModel::find($groupId);
        if ($g === null || !self::canManage((int) $g['project_id'], $actor)) {
            return [];
        }
        $gone = array_values(array_diff(GroupModel::memberIds($groupId), array_map('intval', $members)));
        $open = (int) DB::value('SELECT COUNT(*) FROM tasks WHERE assignee_group_id = ? AND is_completed = 0 AND blocked_at IS NULL', [$groupId]);
        if (!$gone || $open === 0) {
            return [];
        }
        $out = [];
        foreach (DB::all('SELECT id, first_name, last_name FROM users WHERE id IN (' . DB::in($gone) . ')', $gone) as $u) {
            $ticked = (int) DB::value('SELECT COUNT(*) FROM task_group_ticks k JOIN tasks t ON t.id = k.task_id WHERE t.assignee_group_id = ? AND t.is_completed = 0 AND k.user_id = ?', [$groupId, (int) $u['id']]);
            $out[] = ['id' => \App\Core\Pid::of('users', (int) $u['id']), 'name' => full_name($u), 'open' => $open, 'ticked' => $ticked];
        }
        return $out;
    }

    /** خروج یا خارج شدن عضو از پروژه: از گروه‌های آن پروژه بیرون می‌رود (و اگر سرگروه بود، گروه بی‌سرگروه می‌شود) */
    public static function onMemberLeft(int $projectId, int $uid): void
    {
        try {
            DB::run('DELETE m FROM project_group_members m JOIN project_groups g ON g.id = m.group_id WHERE g.project_id = ? AND m.user_id = ?', [$projectId, $uid]);
            DB::run('UPDATE project_groups SET head_id = NULL WHERE project_id = ? AND head_id = ?', [$projectId, $uid]);
            DB::run('UPDATE tasks t JOIN project_groups g ON g.id = t.assignee_group_id SET t.group_head_approval = 0 WHERE g.project_id = ? AND g.head_id IS NULL AND t.is_completed = 0', [$projectId]);
            GroupModel::flush();
        } catch (\Throwable $e) {
            // پیش از migration ۰۳۵ جدول‌ها نیستند
        }
    }

    /**
     * اختیارات سرگروه روی تسک گروهی (v5.12): سرگروه، سازنده‌ی تسک و مدیران گروه (canManage) می‌توانند
     * برای هر عضو «وظیفه‌ی شخصی» بنویسند و تیک «انجام دادم» هر عضو را بزنند یا بردارند.
     */
    public static function canLead(array $task, int $uid): bool
    {
        $gid = (int) ($task['assignee_group_id'] ?? 0);
        if ($gid <= 0 || !empty($task['blocked_at'])) {
            return false;
        }
        return Access::isGroupHead($task, $uid) || (int) $task['user_id'] === $uid || self::canManage((int) $task['project_id'], $uid);
    }

    /**
     * چه کسی وظیفه‌ی شخصی عضو $memberId را می‌نویسد (v5.12.3):
     * گروهِ دارای سرگروه ← فقط سرگروه (برای هر عضو، از جمله خودش)؛ گروهِ بدون سرگروه ← هر عضو فقط برای خودش.
     */
    public static function canDuty(array $task, int $actor, int $memberId): bool
    {
        $gid = (int) ($task['assignee_group_id'] ?? 0);
        if ($gid <= 0 || !empty($task['blocked_at']) || !GroupModel::isMember($gid, $actor)) {
            return false;
        }
        $g = GroupModel::find($gid);
        if ($g === null) {
            return false;
        }
        $head = (int) ($g['head_id'] ?? 0);
        if ($head > 0 && GroupModel::isMember($gid, $head)) {
            return $actor === $head;
        }
        return $actor === $memberId;
    }

    /** وظیفه‌ی شخصی یک عضو روی تسک گروهی (متن خالی = برداشتن) */
    public static function setDuty(int $actor, int $taskId, int $memberId, string $duty): array
    {
        $t = \App\Models\TaskModel::raw($taskId);
        if ($t === null || !Access::canTask($t, $actor, 'view')) {
            return self::err('تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        if (!GroupModel::isMember((int) $t['assignee_group_id'], $memberId)) {
            return self::err('این کاربر عضو گروه این تسک نیست.', 'NOT_GROUP_MEMBER');
        }
        if (!self::canDuty($t, $actor, $memberId)) {
            return self::err('وظیفه‌ی اعضا را فقط سرگروه می‌نویسد؛ در گروه بدون سرگروه هر عضو فقط وظیفه‌ی خودش را.', 'FORBIDDEN', 403);
        }
        $duty = trim(mb_substr(preg_replace('/[ \t]+/u', ' ', $duty), 0, 500));
        if ($duty === '') {
            DB::run('DELETE FROM task_group_duties WHERE task_id = ? AND user_id = ?', [$taskId, $memberId]);
            return self::res(true, 'وظیفه‌ی شخصی برداشته شد.');
        }
        DB::run('INSERT INTO task_group_duties (task_id, user_id, duty, set_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE duty = VALUES(duty), set_by = VALUES(set_by)', [$taskId, $memberId, $duty, $actor]);
        TaskHistory::log($taskId, $actor, 'group_duty', ['user' => $memberId, 'duty' => $duty]);
        Notifier::groupDuty($t, $actor, $memberId, $duty);
        return self::res(true, 'وظیفه‌ی شخصی ثبت شد و برای عضو فرستاده شد.');
    }

    /** سرگروه تیک یک عضو را می‌زند یا برمی‌دارد (تیک خود سرگروه = تحویل، از مسیر عادی تیک) */
    public static function setMemberTick(int $actor, int $taskId, int $memberId, bool $on): array
    {
        $t = \App\Models\TaskModel::raw($taskId);
        if ($t === null || !Access::canTask($t, $actor, 'view')) {
            return self::err('تسک یافت نشد.', 'TASK_NOT_FOUND', 404);
        }
        if (!self::canLead($t, $actor)) {
            return self::err('تیک اعضا را فقط سرگروه، سازنده‌ی تسک یا مدیر گروه‌ها تغییر می‌دهد.', 'FORBIDDEN', 403);
        }
        $gid = (int) $t['assignee_group_id'];
        if (!GroupModel::isMember($gid, $memberId)) {
            return self::err('این کاربر عضو گروه این تسک نیست.', 'NOT_GROUP_MEMBER');
        }
        if (in_array(BoardService::statusOf($t), ['review', 'done', 'archived'], true)) {
            return self::err('تسک تحویل شده است؛ تیک اعضا دیگر تغییر نمی‌کند.', 'GROUP_DELIVERED', 409);
        }
        GroupModel::tick($taskId, $memberId, $on);
        TaskHistory::log($taskId, $actor, $on ? 'group_tick_by' : 'group_untick_by', ['user' => $memberId]);
        // گروه بدون سرگروه (v5.13): با تیکِ آخرین عضو تسک خودکار تحویل می‌شود
        if ($on && GroupModel::headOf($gid) === 0 && GroupModel::allTicked($taskId, $gid)) {
            return \App\Services\TaskService::groupAutoDeliver($actor, $taskId);
        }
        return self::res(true, $on ? 'تیک عضو زده شد.' : 'تیک عضو برداشته شد.');
    }

    /** @return array{title:string,description:?string,members:int[],head:?int}|array{error:array} */
    private static function validate(int $projectId, array $in, ?int $groupId): array
    {
        $title = trim(preg_replace('/\s+/u', ' ', (string) ($in['title'] ?? '')));
        if ($title === '') {
            return ['error' => self::err('نام گروه الزامی است.', 'TITLE_REQUIRED')];
        }
        if (mb_strlen($title) > GroupModel::MAX_TITLE) {
            return ['error' => self::err('نام گروه نباید بیشتر از ' . fa_num(GroupModel::MAX_TITLE) . ' نویسه باشد.', 'TITLE_TOO_LONG')];
        }
        $dup = DB::value('SELECT id FROM project_groups WHERE project_id = ? AND title = ?' . ($groupId ? ' AND id <> ?' : '') . ' LIMIT 1', $groupId ? [$projectId, $title, $groupId] : [$projectId, $title]);
        if ($dup) {
            return ['error' => self::err('گروهی با همین نام در این پروژه هست.', 'GROUP_DUPLICATE')];
        }
        $desc = trim((string) ($in['description'] ?? ''));
        if (mb_strlen($desc) > GroupModel::MAX_DESC) {
            return ['error' => self::err('توضیح گروه بیش از حد طولانی است.', 'DESCRIPTION_TOO_LONG')];
        }
        $raw = $in['members'] ?? [];
        if (is_string($raw)) {
            $raw = $raw === '' ? [] : explode(',', $raw);
        }
        $members = array_values(array_unique(array_filter(array_map('intval', (array) $raw), static fn($v) => $v > 0)));
        if (!$members) {
            return ['error' => self::err('دست‌کم یک عضو برای گروه انتخاب کنید.', 'MEMBERS_REQUIRED')];
        }
        if (count($members) > GroupModel::MAX_MEMBERS) {
            return ['error' => self::err('تعداد اعضای گروه بیش از حد مجاز است.', 'MEMBERS_LIMIT')];
        }
        foreach ($members as $m) {
            if (!Access::isProjectMember($projectId, $m)) {
                return ['error' => self::err('اعضای گروه باید عضو همین پروژه باشند.', 'MEMBER_NOT_IN_PROJECT')];
            }
        }
        $head = (int) ($in['head_id'] ?? 0) ?: null;
        if ($head !== null && !in_array($head, $members, true)) {
            return ['error' => self::err('سرگروه باید یکی از اعضای گروه باشد.', 'HEAD_NOT_MEMBER')];
        }
        return ['title' => $title, 'description' => $desc !== '' ? $desc : null, 'members' => $members, 'head' => $head];
    }

    private static function syncMembers(int $groupId, array $members): void
    {
        $now = GroupModel::memberIds($groupId);
        $del = array_values(array_diff($now, $members));
        if ($del) {
            DB::run('DELETE FROM project_group_members WHERE group_id = ? AND user_id IN (' . DB::in($del) . ')', array_merge([$groupId], $del));
            // تیک عضوی که از گروه بیرون رفته روی تسک‌های باز گروه معنی ندارد
            DB::run('DELETE k FROM task_group_ticks k JOIN tasks t ON t.id = k.task_id WHERE t.assignee_group_id = ? AND t.is_completed = 0 AND k.user_id IN (' . DB::in($del) . ')', array_merge([$groupId], $del));
        }
        foreach (array_diff($members, $now) as $m) {
            DB::run('INSERT IGNORE INTO project_group_members (group_id, user_id) VALUES (?, ?)', [$groupId, $m]);
        }
        GroupModel::flush();
    }
}
