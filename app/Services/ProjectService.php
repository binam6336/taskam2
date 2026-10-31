<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Uploader;
use App\Models\ProjectModel;

/** منطق کسب‌وکار پروژه‌ها (مشترک بین وب و API). همه‌ی متدها [ok, message, extra] برمی‌گردانند. */
final class ProjectService
{
    /** افزودن مستقیم یا ارسال درخواست تأیید، بسته به تنظیم کاربر هدف. */
    public static function invite(int $projectId, int $actorId, int $targetId): array
    {
        $p = DB::one('SELECT id, user_id, max_members FROM projects WHERE id = ?', [$projectId]);
        if ($p === null) {
            return [false, 'پروژه یافت نشد.', 'PROJECT_NOT_FOUND'];
        }
        $u = DB::one('SELECT id, status, project_join_requires_approval FROM users WHERE id = ? AND is_bot = 0', [$targetId]);
        if ($u === null) {
            return [false, 'کاربر یافت نشد.', 'USER_NOT_FOUND'];
        }
        if ($u['status'] !== 'active') {
            return [false, 'کاربر فعال نیست.', 'USER_NOT_ACTIVE'];
        }
        if (!DB::value('SELECT 1 FROM colleagues WHERE user_id = ? AND colleague_user_id = ?', [$actorId, $targetId])) {
            return [false, 'این کاربر در لیست همکاران شما نیست.', 'NOT_A_COLLEAGUE'];
        }
        if (DB::value('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $targetId])) {
            return [false, 'این کاربر قبلاً عضو پروژه است.', 'ALREADY_MEMBER'];
        }
        if (DB::value('SELECT 1 FROM project_blocks WHERE project_id = ? AND user_id = ?', [$projectId, $targetId])) {
            return [false, 'این کاربر پروژه را مسدود کرده است.', 'PROJECT_BLOCKED_BY_USER'];
        }
        $count = (int) DB::value('SELECT COUNT(*) FROM project_members WHERE project_id = ?', [$projectId]);
        if ($count >= (int) $p['max_members']) {
            return [false, 'ظرفیت پروژه تکمیل است.', 'PROJECT_FULL'];
        }
        if ((int) $u['project_join_requires_approval'] === 1) {
            DB::run("DELETE FROM project_join_requests WHERE project_id = ? AND user_id = ? AND status <> 'pending'", [$projectId, $targetId]);
            DB::run("INSERT IGNORE INTO project_join_requests (project_id, user_id, invited_by, status) VALUES (?, ?, ?, 'pending')", [$projectId, $targetId, $actorId]);
            return [true, 'درخواست عضویت ارسال شد و در انتظار تأیید کاربر است.', 'requested'];
        }
        DB::run('INSERT IGNORE INTO project_members (project_id, user_id, permissions) VALUES (?, ?, ?)', [$projectId, $targetId, '[]']);
        return [true, 'کاربر به پروژه اضافه شد.', 'added'];
    }

    public static function create(int $uid, string $title, string $desc, int $max, array $memberIds, ?array $image, ?string $priorityType = null): array
    {
        if ($title === '' || mb_strlen($title) > 255) {
            return [false, 'عنوان پروژه را (حداکثر ۲۵۵ نویسه) وارد کنید.', null];
        }
        if ($max < 1 || $max > 100) {
            return [false, 'حداکثر اعضا باید بین ۱ تا ۱۰۰ باشد.', null];
        }
        $img = null;
        if ($image !== null) {
            $r = Uploader::saveImage($image, 'projects', 'proj');
            if (!$r['ok']) {
                return [false, $r['error'], null];
            }
            $img = $r['name'];
        }
        $ptype = $priorityType === 'percent' ? 'percent' : 'category';
        $id = DB::transaction(static function () use ($uid, $title, $desc, $max, $img, $ptype): int {
            $id = DB::insert('projects', ['user_id' => $uid, 'title' => $title, 'description' => $desc !== '' ? $desc : null, 'max_members' => $max, 'profile_image' => $img, 'priority_type' => $ptype]);
            DB::insert('project_members', ['project_id' => $id, 'user_id' => $uid, 'permissions' => json_encode(array_keys(Access::PROJECT_PERMS))]);
            return $id;
        });
        $requested = 0;
        $added = 0;
        foreach (array_unique(array_map('intval', $memberIds)) as $mid) {
            if ($mid === $uid) {
                continue;
            }
            [$ok, , $state] = self::invite($id, $uid, $mid);
            if ($ok) {
                $state === 'requested' ? $requested++ : $added++;
            }
        }
        $msg = 'پروژه ایجاد شد.' . ($added ? ' ' . fa_num($added) . ' عضو اضافه شد.' : '') . ($requested ? ' ' . fa_num($requested) . ' درخواست تأیید ارسال شد.' : '');
        return [true, $msg, $id];
    }

    /** پروژه‌ی نمونه برای کاربر تازه‌ثبت‌نام‌شده تا تسک‌های اولیه را با آن ثبت کند (کاربر می‌تواند ویرایش یا حذفش کند). */
    public static function createSample(int $uid): void
    {
        try {
            self::create($uid, 'پروژه نمونه', 'این یک پروژه‌ی نمونه است تا تسک‌های اولیه‌ات را با آن ثبت کنی؛ هر وقت خواستی می‌توانی ویرایش یا حذفش کنی.', 10, [], null);
        } catch (\Throwable $e) {
            error_log('sample project: ' . $e->getMessage()); // نبودِ پروژه‌ی نمونه نباید ثبت‌نام را خراب کند
        }
    }

    public static function update(int $projectId, int $uid, string $title, string $desc, int $max, ?array $memberIds, ?array $image, ?string $priorityType = null): array
    {
        $p = DB::one('SELECT * FROM projects WHERE id = ?', [$projectId]);
        if ($p === null) {
            return [false, 'پروژه یافت نشد.'];
        }
        $perms = Access::projectPerms($projectId, $uid);
        $data = [];
        if (in_array('edit_project', $perms, true)) {
            if ($title === '' || mb_strlen($title) > 255) {
                return [false, 'عنوان پروژه نامعتبر است.'];
            }
            if ($max < 1 || $max > 100) {
                return [false, 'حداکثر اعضا باید بین ۱ تا ۱۰۰ باشد.'];
            }
            $cur = (int) DB::value('SELECT COUNT(*) FROM project_members WHERE project_id = ?', [$projectId]);
            if ($max < $cur) {
                return [false, 'حداکثر اعضا نمی‌تواند کمتر از تعداد فعلی اعضا (' . fa_num($cur) . ') باشد.'];
            }
            $data = ['title' => $title, 'description' => $desc !== '' ? $desc : null, 'max_members' => $max];
            if ($priorityType !== null && array_key_exists($priorityType, Priority::TYPES) && $priorityType !== $p['priority_type']) {
                $data['priority_type'] = $priorityType;
                Priority::switchProject($projectId, (string) $p['priority_type'], $priorityType);
            }
            if ($image !== null) {
                $r = Uploader::saveImage($image, 'projects', 'proj');
                if (!$r['ok']) {
                    return [false, $r['error']];
                }
                Uploader::delete('projects', $p['profile_image']);
                $data['profile_image'] = $r['name'];
            }
            DB::update('projects', $data, 'id = ?', [$projectId]);
        } elseif ($title !== '' && $title !== $p['title']) {
            return [false, 'اجازه‌ی ویرایش پروژه را ندارید.'];
        }

        if ($memberIds !== null) {
            $want = array_unique(array_map('intval', $memberIds));
            $have = array_map('intval', DB::column('SELECT user_id FROM project_members WHERE project_id = ?', [$projectId]));
            $pending = ProjectModel::pendingUserIds($projectId);
            $creator = (int) $p['user_id'];
            if (in_array('add_member', $perms, true)) {
                foreach (array_diff($want, $have, $pending) as $mid) {
                    if ($mid !== $uid) {
                        self::invite($projectId, $uid, $mid);
                    }
                }
            }
            if (in_array('remove_member', $perms, true)) {
                foreach (array_diff($have, $want) as $mid) {
                    if ($mid !== $creator && $mid !== $uid) {
                        DB::run('DELETE FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $mid]);
                    }
                }
                foreach (array_diff($pending, $want) as $mid) {
                    DB::run("DELETE FROM project_join_requests WHERE project_id = ? AND user_id = ? AND status = 'pending'", [$projectId, $mid]);
                }
            }
        }
        return [true, 'پروژه به‌روزرسانی شد.'];
    }

    /**
     * غیرسازنده فقط روی اعضایی کار می‌کند که دسترسی‌شان (پروژه، تسک، برد) زیرمجموعه‌ی سخت‌گیرانه‌ی دسترسی خودش باشد؛
     * یعنی عضو هم‌رده یا بالاتر را نمی‌تواند حذف کند یا مجوزهایش را تغییر دهد. روی خودش هم کار نمی‌کند.
     */
    public static function canManageMember(int $projectId, int $actorId, int $memberId): bool
    {
        // نتیجه در طول همین درخواست ثابت می‌ماند (ذخیره‌ی چندمرحله‌ای مثل نقش یا برد + سقف، وسط کار رد نشود)
        static $memo = [];
        $k = "$projectId:$actorId:$memberId";
        return $memo[$k] ??= self::outranks($projectId, $actorId, $memberId);
    }

    private static function outranks(int $projectId, int $actorId, int $memberId): bool
    {
        $creator = (int) DB::value('SELECT user_id FROM projects WHERE id = ?', [$projectId]);
        if ($actorId === $creator) {
            return true;
        }
        if ($memberId === $actorId || $memberId === $creator) {
            return false;
        }
        $sets = [[Access::projectPerms($projectId, $actorId), Access::projectPerms($projectId, $memberId)],
                 [Access::projectTaskPerms($projectId, $actorId), Access::projectTaskPerms($projectId, $memberId)],
                 [Access::projectBoardPerms($projectId, $actorId), Access::projectBoardPerms($projectId, $memberId)]];
        $strict = false;
        foreach ($sets as [$mine, $theirs]) {
            if (array_diff($theirs, $mine)) {
                return false;                                  // عضو دسترسی‌ای دارد که بازیگر ندارد
            }
            $strict = $strict || (bool) array_diff($mine, $theirs);
        }
        return $strict;
    }

    public static function removeMember(int $projectId, int $actorId, int $memberId): array
    {
        $creator = (int) DB::value('SELECT user_id FROM projects WHERE id = ?', [$projectId]);
        if ($creator === 0) {
            return [false, 'پروژه یافت نشد.'];
        }
        if (!Access::projectCan($projectId, $actorId, 'remove_member')) {
            return [false, 'دسترسی حذف کاربر را ندارید.'];
        }
        if ($memberId === $creator) {
            return [false, 'سازنده‌ی پروژه قابل حذف نیست.'];
        }
        if (DB::value('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $memberId]) && !self::canManageMember($projectId, $actorId, $memberId)) {
            return [false, 'فقط اعضایی را می‌توانید مدیریت کنید که دسترسی کمتری از شما دارند.'];
        }
        $n = DB::run('DELETE FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $memberId]);
        return $n > 0 ? [true, 'کاربر از پروژه حذف شد.'] : [false, 'کاربر عضو پروژه نیست.'];
    }

    public static function setPermissions(int $projectId, int $actorId, int $memberId, array $perms): array
    {
        $creator = (int) DB::value('SELECT user_id FROM projects WHERE id = ?', [$projectId]);
        if ($creator === 0) {
            return [false, 'پروژه یافت نشد.'];
        }
        $mine = Access::projectPerms($projectId, $actorId);
        if (!in_array('manage_permissions', $mine, true)) {
            return [false, 'دسترسی مدیریت مجوزها را ندارید.'];
        }
        if ($memberId === $creator) {
            return [false, 'مجوزهای سازنده قابل تغییر نیست.'];
        }
        if (!self::canManageMember($projectId, $actorId, $memberId)) {
            return [false, 'فقط اعضایی را می‌توانید مدیریت کنید که دسترسی کمتری از شما دارند.'];
        }
        $old = Access::decode((string) DB::value('SELECT permissions FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $memberId]), array_keys(Access::PROJECT_PERMS));
        if (!DB::value('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $memberId])) {
            return [false, 'کاربر عضو پروژه نیست.'];
        }
        $clean = array_values(array_unique(array_intersect($perms, array_keys(Access::PROJECT_PERMS))));
        if ($actorId !== $creator) {
            // فقط مجوزهایی که خودش دارد قابل اعطا/لغو است؛ بقیه بدون تغییر می‌مانند
            $grantable = $mine;
            $clean = array_values(array_unique(array_merge(array_diff($old, $grantable), array_intersect($clean, $grantable))));
        }
        DB::run('UPDATE project_members SET permissions = ? WHERE project_id = ? AND user_id = ?', [json_encode($clean), $projectId, $memberId]);
        return [true, 'مجوزها ذخیره شد.'];
    }

    /**
     * دسترسی یک عضو روی تسک‌های این پروژه (پیش‌فرض هیچ).
     * سازنده یا عضو دارای «اعطای دسترسی»؛ غیرسازنده فقط دسترسی‌هایی را که خودش دارد اعطا/لغو می‌کند.
     */
    public static function setTaskPermissions(int $projectId, int $actorId, int $memberId, array $perms): array
    {
        return self::setMemberPerms('task_permissions', Access::TASK_PERMS, [Access::class, 'projectTaskPerms'], $projectId, $actorId, $memberId, $perms, 'دسترسی تسک‌ها ذخیره شد.');
    }

    /**
     * دسترسی یک عضو در برد تیمی این پروژه (پیش‌فرض هیچ)؛ همان قواعد اعطای دسترسی تسک.
     * $viewUsers (اختیاری): اعضایی که تسک‌هایشان در برد او دیده شود، یا 'all' = همه‌ی اعضا (حتی اعضای بعدی)؛
     * غیرسازنده فقط اعضایی را که خودش می‌بیند انتخاب/حذف می‌کند و «همه» را فقط اگر خودش همه را ببیند.
     * @param int[]|string|null $viewUsers
     */
    public static function setBoardPermissions(int $projectId, int $actorId, int $memberId, array $perms, $viewUsers = null): array
    {
        $res = self::setMemberPerms('board_permissions', Access::BOARD_PERMS, [Access::class, 'projectBoardPerms'], $projectId, $actorId, $memberId, $perms, 'دسترسی برد تیمی ذخیره شد.');
        if (!$res[0] || $viewUsers === null) {
            return $res;
        }
        $scope = Access::boardViewScope($projectId, $actorId);
        $oldRaw = (string) DB::value('SELECT board_view_users FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $memberId]);
        if ($viewUsers === 'all') {
            if ($scope === null) {
                DB::run('UPDATE project_members SET board_view_users = ? WHERE project_id = ? AND user_id = ?', ['"all"', $projectId, $memberId]);
                return $res;
            }
            if (Access::viewAll($oldRaw)) {
                return $res;
            }
            $viewUsers = $scope;
        } elseif (Access::viewAll($oldRaw) && $scope !== null) {
            return $res;                                       // «همه» را فقط کسی که خودش همه را می‌بیند برمی‌دارد
        }
        $viewUsers = is_array($viewUsers) ? $viewUsers : [];
        $members = array_map('intval', array_column(DB::all('SELECT user_id FROM project_members WHERE project_id = ?', [$projectId]), 'user_id'));
        $members[] = (int) DB::value('SELECT user_id FROM projects WHERE id = ?', [$projectId]);
        $old = Access::ids($oldRaw);
        $clean = array_values(array_intersect(array_unique(array_map('intval', $viewUsers)), $members));
        if ($scope !== null) {
            $clean = array_merge(array_diff($old, $scope), array_intersect($clean, $scope));
        }
        $clean = array_values(array_diff(array_unique($clean), [$memberId]));      // خودش همیشه هست
        sort($clean);
        DB::run('UPDATE project_members SET board_view_users = ? WHERE project_id = ? AND user_id = ?', [json_encode($clean), $projectId, $memberId]);
        return $res;
    }

    /** اعتبارسنجی سقف‌ها (قبل از ذخیره‌ی هر چیز دیگری صدا زده شود): [سقف‌های تمیز، خطا یا null] */
    public static function cleanBoardLimits(array $limits): array
    {
        $clean = [];
        foreach (BoardService::LIMITED as $st) {
            $v = $limits[$st] ?? null;
            if ($v === null || $v === '') {
                continue;
            }
            if (!is_numeric($v) || (int) $v != $v || (int) $v < 0 || (int) $v > BoardService::MAX_LIMIT) {
                return [[], 'سقف «' . BoardService::STATUSES[$st]['label'] . '» باید عددی بین ۰ تا ' . fa_num(BoardService::MAX_LIMIT) . ' باشد.'];
            }
            $clean[$st] = (int) $v;
        }
        return [$clean, null];
    }

    /**
     * سقف تعداد تسک‌های یک عضو در ستون‌های برد (BoardService::LIMITED)؛ مقدار خالی/null = بدون محدودیت.
     * همان شرط ویرایش دسترسی برد: سازنده یا عضو دارای «اعطای دسترسی»؛ برای سازنده‌ی پروژه سقف تعیین نمی‌شود.
     */
    public static function setBoardLimits(int $projectId, int $actorId, int $memberId, array $limits): array
    {
        $creator = (int) DB::value('SELECT user_id FROM projects WHERE id = ?', [$projectId]);
        if ($creator === 0) {
            return [false, 'پروژه یافت نشد.'];
        }
        if (!Access::projectCan($projectId, $actorId, 'manage_permissions')) {
            return [false, 'دسترسی مدیریت مجوزها را ندارید.'];
        }
        if ($memberId === $creator) {
            return [false, 'برای سازنده‌ی پروژه سقف تعداد تسک تعیین نمی‌شود.'];
        }
        if (!self::canManageMember($projectId, $actorId, $memberId)) {
            return [false, 'فقط اعضایی را می‌توانید مدیریت کنید که دسترسی کمتری از شما دارند.'];
        }
        if (!DB::value('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $memberId])) {
            return [false, 'کاربر عضو پروژه نیست.'];
        }
        [$clean, $err] = self::cleanBoardLimits($limits);
        if ($err !== null) {
            return [false, $err];
        }
        DB::run('UPDATE project_members SET board_limits = ? WHERE project_id = ? AND user_id = ?', [$clean ? json_encode($clean) : null, $projectId, $memberId]);
        return [true, 'سقف تعداد تسک‌ها ذخیره شد.'];
    }

    /**
     * اعمال یک نقش آماده (Access::ROLES) روی عضو: دسترسی تسک، پروژه، برد و «مشاهده‌ی تسک‌ها» یکجا.
     * همان قواعد اعطا: غیرسازنده فقط آنچه خودش دارد را تغییر می‌دهد (در آن صورت نتیجه ممکن است «سفارشی» شود).
     */
    public static function applyRole(int $projectId, int $actorId, int $memberId, string $role): array
    {
        $pkg = Access::rolePackage($role);
        if ($pkg === null) {
            return [false, 'نقش نامعتبر است.'];
        }
        [$task, $project, $board, $viewAll] = $pkg;
        foreach ([self::setTaskPermissions($projectId, $actorId, $memberId, $task), self::setPermissions($projectId, $actorId, $memberId, $project),
                  self::setBoardPermissions($projectId, $actorId, $memberId, $board, $viewAll ? 'all' : [])] as $r) {
            if (!$r[0]) {
                return $r;
            }
        }
        $now = Access::memberRole(DB::one('SELECT permissions, task_permissions, board_permissions, board_view_users FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $memberId]) ?? []);
        if ($now !== $role) {
            return [true, 'نقش «' . Access::ROLES[$role]['label'] . '» فقط تا حدی اعمال شد؛ فقط دسترسی‌هایی را که خودتان دارید می‌توانید تغییر دهید. نقش فعلی: سفارشی.'];
        }
        return [true, 'نقش «' . Access::ROLES[$role]['label'] . '» اعمال شد.'];
    }

    private static function setMemberPerms(string $column, array $defs, callable $holderPerms, int $projectId, int $actorId, int $memberId, array $perms, string $okMsg): array
    {
        $creator = (int) DB::value('SELECT user_id FROM projects WHERE id = ?', [$projectId]);
        if ($creator === 0) {
            return [false, 'پروژه یافت نشد.'];
        }
        if (!Access::projectCan($projectId, $actorId, 'manage_permissions')) {
            return [false, 'دسترسی مدیریت مجوزها را ندارید.'];
        }
        if ($memberId === $creator) {
            return [false, 'سازنده‌ی پروژه همیشه همه‌ی دسترسی‌ها را دارد.'];
        }
        if (!self::canManageMember($projectId, $actorId, $memberId)) {
            return [false, 'فقط اعضایی را می‌توانید مدیریت کنید که دسترسی کمتری از شما دارند.'];
        }
        $row = DB::one('SELECT `' . $column . '` AS v FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $memberId]);
        if ($row === null) {
            return [false, 'کاربر عضو پروژه نیست.'];
        }
        $keys = array_keys($defs);
        $old = Access::decode($row['v'], $keys);
        $clean = array_values(array_unique(array_intersect($perms, $keys)));
        if ($actorId !== $creator) {
            $grantable = $holderPerms($projectId, $actorId);
            $clean = array_values(array_unique(array_merge(array_diff($old, $grantable), array_intersect($clean, $grantable))));
        }
        // ترتیب ثابت مثل تعریف‌ها
        $clean = array_values(array_intersect($keys, $clean));
        DB::run('UPDATE project_members SET `' . $column . '` = ? WHERE project_id = ? AND user_id = ?', [json_encode($clean), $projectId, $memberId]);
        return [true, $okMsg];
    }

    public static function delete(int $projectId, int $uid): array
    {
        $p = DB::one('SELECT id, user_id, profile_image FROM projects WHERE id = ?', [$projectId]);
        if ($p === null) {
            return [false, 'پروژه یافت نشد.'];
        }
        if ((int) $p['user_id'] !== $uid) {
            return [false, 'فقط سازنده می‌تواند پروژه را حذف کند.'];
        }
        DB::run('DELETE FROM projects WHERE id = ?', [$projectId]);
        Uploader::delete('projects', $p['profile_image']);
        return [true, 'پروژه حذف شد.'];
    }

    public static function leave(int $projectId, int $uid): array
    {
        $p = DB::one('SELECT id, user_id, title, profile_image FROM projects WHERE id = ?', [$projectId]);
        if ($p === null) {
            return [false, 'پروژه یافت نشد.'];
        }
        if ((int) $p['user_id'] === $uid) {
            return [false, 'سازنده نمی‌تواند از پروژه خارج شود؛ پروژه را حذف کنید.'];
        }
        if (!DB::value('SELECT 1 FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $uid])) {
            return [false, 'شما عضو این پروژه نیستید.'];
        }
        DB::transaction(static function () use ($p, $projectId, $uid): void {
            DB::insert('project_leave_history', ['project_id' => $projectId, 'user_id' => $uid, 'project_title' => $p['title'], 'project_image' => $p['profile_image']]);
            DB::run('DELETE FROM project_members WHERE project_id = ? AND user_id = ?', [$projectId, $uid]);
            DB::run('DELETE FROM project_blocks WHERE project_id = ? AND user_id = ?', [$projectId, $uid]);
        });
        return [true, 'از پروژه خارج شدید.'];
    }

    public static function respond(int $requestId, int $uid, bool $accept): array
    {
        $r = DB::one("SELECT r.*, p.title AS project_title, p.profile_image AS project_image, p.max_members FROM project_join_requests r INNER JOIN projects p ON p.id = r.project_id WHERE r.id = ? AND r.user_id = ? AND r.status = 'pending'", [$requestId, $uid]);
        if ($r === null) {
            return [false, 'درخواست یافت نشد یا قبلاً پاسخ داده شده است.'];
        }
        $pid = (int) $r['project_id'];
        if ($accept) {
            if ((int) DB::value('SELECT COUNT(*) FROM project_members WHERE project_id = ?', [$pid]) >= (int) $r['max_members']) {
                return [false, 'ظرفیت پروژه تکمیل است. با سازنده‌ی پروژه تماس بگیرید.'];
            }
            DB::transaction(static function () use ($pid, $uid, $requestId): void {
                DB::run('INSERT IGNORE INTO project_members (project_id, user_id, permissions) VALUES (?, ?, ?)', [$pid, $uid, '[]']);
                DB::run("UPDATE project_join_requests SET status = 'accepted', resolved_at = NOW() WHERE id = ?", [$requestId]);
            });
            return [true, 'عضویت شما در پروژه تأیید شد.'];
        }
        DB::transaction(static function () use ($r, $pid, $uid, $requestId): void {
            DB::run("UPDATE project_join_requests SET status = 'rejected', resolved_at = NOW() WHERE id = ?", [$requestId]);
            DB::insert('project_leave_history', ['project_id' => $pid, 'user_id' => $uid, 'project_title' => $r['project_title'], 'project_image' => $r['project_image']]);
        });
        return [true, 'درخواست عضویت رد شد.'];
    }
}
