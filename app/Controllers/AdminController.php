<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\Impersonation;
use App\Core\Response;
use App\Core\ErrorText;
use App\Core\PlaceholderText;
use App\Core\Session;
use App\Core\Validate;
use App\Models\ContentModel;
use App\Models\SidebarModel;
use App\Models\SupportModel;
use App\Models\UserModel;
use App\Services\ProjectService;
use App\Services\SupportDesk;

/** پنل مدیریت: کاربران، پشتیبانی، تحلیل سیستم. */
final class AdminController extends Controller
{
    private const PERMS = ['add_user' => 'افزودن کاربر', 'edit_user' => 'ویرایش کاربر', 'toggle_status' => 'تغییر وضعیت', 'delete_user' => 'حذف کاربر',
        'impersonate' => 'ورود به پنل کاربر', 'edit_content' => 'ویرایش محتوا (سایدبار، تور، Placeholder، خطاها)'];

    /** مدیریت مدیران دیگر (ساخت/ویرایش/حذف مدیر و تعیین نقش) — دسترسی manage_admins (v7.4؛ قبلاً هر مدیری) */
    private function isSuper(): bool
    {
        return Auth::adminCan('manage_admins');
    }

    /** نقش انتخاب‌شده در فرم کاربر: [role, admin_role] */
    private function roleInput(): array
    {
        $v = $this->request->str('role', 20);
        return isset(Auth::ADMIN_ROLES[$v]) ? ['admin', $v] : ['user', null];
    }

    /**
     * دسترسی پنل مدیر (v7.4): بدون اجازه، درخواست AJAX/POST خطای ۴۰۳ می‌گیرد و صفحه (GET) به اولین بخش مجاز
     * همین مدیر می‌رود (یا خطا اگر هیچ بخشی ندارد).
     */
    private function need(string $perm): void
    {
        if (Auth::adminCan($perm)) {
            return;
        }
        $land = \App\Core\AdminAccess::landing($this->user() ?? []);
        if ($this->request->method() === 'GET' && !$this->request->wantsJson() && $land !== null && $land !== '/' . ltrim($this->request->path(), '/')) {
            $this->flash('شما این دسترسی را ندارید.', 'error');
            $this->redirect($land);
        }
        $this->fail('شما این دسترسی را ندارید.', 403);
    }

    /** تعداد کاربر در هر صفحه‌ی «مدیریت کاربران» (پیش‌فرض اولی) */
    public const USERS_PER_PAGE = [10, 20, 50, 100];

    public function users()
    {
        $this->need('view_users');
        $me = $this->user();
        $r = $this->request;
        // تعداد در صفحه: از آدرس، وگرنه آخرین انتخاب همین مدیر در نشست
        $per = (int) ($r->query['per'] ?? Session::get('admin_users_per', self::USERS_PER_PAGE[0]));
        $per = in_array($per, self::USERS_PER_PAGE, true) ? $per : self::USERS_PER_PAGE[0];
        if (isset($r->query['per'])) {
            Session::put('admin_users_per', $per);
        }
        $q = trim(mb_substr((string) ($r->query['q'] ?? ''), 0, 100));
        $total = UserModel::pageForAdmin($q, 1, 0)['total'];
        $pages = max(1, (int) ceil($total / $per));
        $page = min($pages, max(1, (int) ($r->query['page'] ?? 1)));
        $res = UserModel::pageForAdmin($q, $per, ($page - 1) * $per);
        $this->view('admin/users', [
            'users' => $res['rows'], 'total' => $res['total'], 'pg' => $page, 'pages' => $pages, 'per' => $per, 'q' => $q, 'perOptions' => self::USERS_PER_PAGE, 'counts' => UserModel::counts(), 'myPerms' => Auth::adminPermissions($me),
            'isSuper' => $this->isSuper(), 'permDefs' => self::PERMS, 'superId' => Auth::superAdminId(), 'adminRoles' => Auth::ADMIN_ROLES,
            'supportUnread' => SupportModel::unreadForAdmin(),
        ], ['title' => 'مدیریت کاربران', 'page' => 'admin-users'], 'admin');
    }

    /** ورودی مشترک ایجاد/ویرایش؛ [data|null, error|null] */
    private function userInput(?int $editId): array
    {
        $r = $this->request;
        $first = $r->str('first_name', 100);
        $last = $r->str('last_name', 100);
        $mobile = Validate::mobile($r->str('mobile', 30));
        $email = $r->str('email', 150);
        if (!Validate::name($first) || ($last !== '' && !Validate::name($last))) {
            return [null, 'نام یا نام‌خانوادگی نامعتبر است.'];
        }
        if ($mobile === null) {
            return [null, 'شماره موبایل نامعتبر است.'];
        }
        if (UserModel::mobileExists($mobile, (int) $editId)) {
            return [null, 'این شماره موبایل قبلاً ثبت شده است.'];
        }
        if ($email !== '' && (!Validate::email($email) || UserModel::emailExists($email, (int) $editId))) {
            return [null, 'ایمیل نامعتبر یا تکراری است.'];
        }
        return [['first_name' => $first, 'last_name' => $last, 'mobile' => $mobile, 'email' => $email !== '' ? $email : null], null];
    }


    public function createUser()
    {
        $this->need('add_user');
        [$d, $err] = $this->userInput(null);
        if ($d === null) {
            $this->respond(false, (string) $err, '/admin');
        }
        $pass = (string) $this->request->input('password', '');
        if ($e = Validate::passwordError($pass)) {
            $this->respond(false, $e, '/admin');
        }
        [$role, $adminRole] = $this->isSuper() ? $this->roleInput() : ['user', null];
        $status = Validate::enum($this->request->input('status'), Auth::STATUSES, 'active');
        $id = DB::insert('users', $d + ['password_hash' => Auth::hash($pass), 'role' => $role, 'status' => $status, 'admin_role' => $adminRole]);
        \App\Models\SubjectModel::seedDefaults($id);
        Audit::log($this->uid(), 'admin_user_created', ['id' => $id, 'role' => $role]);
        if ($role === 'user') {
            ProjectService::createSample($id);
        }
        $this->respond(true, 'کاربر ایجاد شد.', '/admin');
    }

    public function updateUser(string $id)
    {
        $this->need('edit_user');
        $target = UserModel::find((int) $id);
        if ($target === null || (int) $target['is_bot'] === 1) {
            $this->respond(false, 'کاربر یافت نشد.', '/admin');
        }
        if ($target['role'] === 'admin' && !$this->isSuper() && (int) $target['id'] !== $this->uid()) {
            $this->respond(false, 'فقط مدیر کل می‌تواند مدیران دیگر را ویرایش کند.', '/admin');
        }
        [$d, $err] = $this->userInput((int) $id);
        if ($d === null) {
            $this->respond(false, (string) $err, '/admin');
        }
        $pass = (string) $this->request->input('password', '');
        if ($pass !== '') {
            if ($e = Validate::passwordError($pass)) {
                $this->respond(false, $e, '/admin');
            }
            $d['password_hash'] = Auth::hash($pass);
        }
        // تغییر رمز یا موبایل (شناسه‌ی ورود) = تصاحب حساب؛ مدیر باید رمز فعلی خودش را دوباره وارد کند (step-up)
        $mobileChanged = $d['mobile'] !== (string) $target['mobile'];
        if ($pass !== '' || $mobileChanged) {
            $this->denyImpersonation('/admin');
            $me = UserModel::find($this->uid());
            $cur = (string) $this->request->input('admin_password', '');
            if ($cur === '' || $me === null || !password_verify($cur, (string) $me['password_hash'])) {
                Audit::log($this->uid(), 'admin_reauth_failed', ['id' => (int) $id]);
                $this->respond(false, 'برای تغییر رمز یا موبایل کاربر، رمز عبور فعلی خودتان را درست وارد کنید.', '/admin');
            }
        }
        if ($this->isSuper() && (int) $target['id'] !== Auth::superAdminId()) {
            [$role, $adminRole] = $this->roleInput();
            $d['role'] = $role;
            $d['admin_role'] = $adminRole;
        }
        DB::update('users', $d, 'id = ?', [(int) $id]);
        if ($pass !== '') {
            \App\Models\TokenModel::revokeAll((int) $id);   // با تغییر رمز، توکن‌های API قبلی باطل می‌شوند
        }
        Audit::log($this->uid(), 'admin_user_updated', ['id' => (int) $id, 'password' => $pass !== '', 'mobile' => $mobileChanged]);
        if (($pass !== '' || $mobileChanged) && (int) $id !== $this->uid()) {
            $what = $pass !== '' && $mobileChanged ? 'رمز عبور و شماره موبایل' : ($pass !== '' ? 'رمز عبور' : 'شماره موبایل');
            \App\Services\Notifier::send((int) $id, "🔐 {$what} حساب شما توسط مدیر سیستم تغییر کرد. اگر از این تغییر خبر ندارید، با پشتیبانی تماس بگیرید.");
        }
        $this->respond(true, 'اطلاعات کاربر ذخیره شد.', '/admin');
    }

    public function setStatus(string $id)
    {
        $this->need('toggle_status');
        $status = Validate::enum($this->request->input('status'), Auth::STATUSES, '');
        $t = UserModel::find((int) $id);
        if ($status === '' || $t === null || (int) $t['is_bot'] === 1) {
            $this->respond(false, 'درخواست نامعتبر است.', '/admin');
        }
        if ((int) $t['id'] === $this->uid() || (int) $t['id'] === Auth::superAdminId() || ($t['role'] === 'admin' && !$this->isSuper())) {
            $this->respond(false, 'تغییر وضعیت این حساب مجاز نیست.', '/admin');
        }
        DB::update('users', ['status' => $status], 'id = ?', [(int) $id]);
        Audit::log($this->uid(), 'admin_user_status', ['id' => (int) $id, 'status' => $status]);
        $this->respond(true, 'وضعیت کاربر تغییر کرد.', '/admin');
    }

    public function deleteUser(string $id)
    {
        $this->need('delete_user');
        $t = UserModel::find((int) $id);
        if ($t === null || (int) $t['is_bot'] === 1) {
            $this->respond(false, 'کاربر یافت نشد.', '/admin');
        }
        if ((int) $t['id'] === $this->uid() || (int) $t['id'] === Auth::superAdminId() || ($t['role'] === 'admin' && !$this->isSuper())) {
            $this->respond(false, 'حذف این حساب مجاز نیست.', '/admin');
        }
        UserModel::deleteWithFiles((int) $id);
        Audit::log($this->uid(), 'admin_user_deleted', ['id' => (int) $id, 'mobile' => $t['mobile']]);
        $this->respond(true, 'کاربر و تمام داده‌هایش حذف شد.', '/admin');
    }

    public function impersonate(string $id)
    {
        $this->need('impersonate');
        $t = UserModel::find((int) $id);
        if ($t === null || $t['role'] === 'admin' || $t['status'] !== 'active' || (int) $t['is_bot'] === 1) {
            $this->respond(false, 'فقط کاربران عادیِ فعال قابل ورود هستند.', '/admin');
        }
        $this->rateLimit('impersonate', 30, 3600);
        $admin = (array) Auth::realUser();     // تأیید رمز مدیر فعلاً لازم نیست (دسترسی نقش‌های مدیر بعداً مدیریت می‌شود)
        $token = Impersonation::start($admin, $t, $this->request);
        // مقصد بعد از ورود (از «جزئیات کاربر»: فهرست تسک‌های یک پروژه یا لینک یک تسک)؛ فقط مسیرهای مجاز پنل کاربر
        $to = (string) $this->request->input('to', '');
        $to = preg_match('#^/(tasks|board|projects)(\?[A-Za-z0-9=&_%.\-]*)?$#', $to) ? $to : '/dashboard';
        // پنل کاربر در تب جدید (window.open در admin.js)؛ نشست و تب پنل مدیر دست نمی‌خورد
        $this->ok('پنل «' . full_name($t) . '» در تب جدید باز شد.', ['open' => Impersonation::urlFor($token, $to), 'name' => full_name($t)]);
    }

    /** پنجره‌های ورود به پنل کاربرِ باز همین نشست مدیر (کارت بالای فهرست کاربران) */
    public function impersonations()
    {
        $this->need('manage_impersonations');
        $rows = Impersonation::openFor($this->uid());
        $this->ok('', ['items' => array_map(static fn($r) => ['id' => pid('impersonations', $r), 'name' => full_name($r), 'mobile' => $r['mobile'], 'avatar' => avatar_url($r['avatar']),
            'started_fa' => jdate((string) $r['created_at']), 'seen_fa' => time_ago((string) $r['last_seen_at']), 'expires_fa' => jdate((string) $r['expires_at']), 'actions' => (int) $r['actions']], $rows)]);
    }

    /** بستن یک پنجره از پنل مدیر (فقط پنجره‌های خود همین مدیر) */
    public function impersonationEnd(string $id)
    {
        $this->need('manage_impersonations');
        $row = DB::one('SELECT id FROM impersonations WHERE id = ? AND admin_id = ? AND ended_at IS NULL', [(int) $id, $this->uid()]);
        if ($row === null) {
            $this->fail('پنجره یافت نشد یا قبلاً بسته شده است.', 404);
        }
        Impersonation::end((int) $row['id'], 'admin_end');
        $this->ok('پنجره‌ی پنل کاربر بسته شد؛ آن تب دیگر کار نمی‌کند.');
    }

    /** بستن همه‌ی پنجره‌های باز این مدیر (روی همه‌ی دستگاه‌ها) */
    public function impersonationEndAll()
    {
        $this->need('manage_impersonations');
        $n = Impersonation::endAll($this->uid(), 'end_all');
        $this->ok($n ? fa_num($n) . ' پنجره‌ی پنل کاربر بسته شد.' : 'پنجره‌ی بازی نبود.');
    }

    /** «جزئیات» کاربر در فهرست کاربران: GET /admin/users/{id}/details */
    public function userDetails(string $id)
    {
        $this->need('view_users');
        $this->rateLimit('admin_list', 120);
        $u = UserModel::find((int) $id);
        if ($u === null || (int) $u['is_bot'] === 1) {
            $this->fail('کاربر یافت نشد.', 404);
        }
        $uid = (int) $u['id'];
        $t = DB::one('SELECT COUNT(*) total, COALESCE(SUM(is_completed = 1),0) done, COALESCE(SUM(is_completed = 0 AND board_status = \'review\'),0) review,
                COALESCE(SUM(is_completed = 0 AND due_date < CURDATE()),0) late FROM tasks WHERE assignee_id = ? AND blocked_at IS NULL', [$uid]) ?? [];
        $created = (int) DB::value('SELECT COUNT(*) FROM tasks WHERE user_id = ? AND blocked_at IS NULL', [$uid]);
        $canEnter = Auth::adminCan('impersonate') && $u['role'] !== 'admin' && $u['status'] === 'active';
        $projects = [];
        foreach (DB::all('SELECT p.id, p.title, p.user_id, p.blocked_at, (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id <> p.user_id) + 1 AS members,
                (SELECT COUNT(*) FROM tasks x WHERE x.project_id = p.id AND x.assignee_id = ? AND x.blocked_at IS NULL) AS my_tasks
                FROM projects p WHERE p.user_id = ? OR p.id IN (SELECT project_id FROM project_members WHERE user_id = ?) ORDER BY p.blocked_at IS NOT NULL, p.title', [$uid, $uid, $uid]) as $p) {
            $creator = (int) $p['user_id'] === $uid;
            $pm = $creator ? null : DB::one('SELECT permissions, task_permissions, board_permissions, board_view_users FROM project_members WHERE project_id = ? AND user_id = ?', [(int) $p['id'], $uid]);
            $roles = \App\Services\Access::ROLES + ['custom' => \App\Services\Access::ROLE_CUSTOM];
            // تسک‌های کل پروژه (با دسترسی تحلیل) یا فقط تسک‌های خودش در همان پروژه
            $all = $creator || \App\Services\Access::projectCan((int) $p['id'], $uid, 'view_analytics');
            $projects[] = ['id' => pid('projects', $p['id']), 'title' => $p['title'], 'members' => (int) $p['members'], 'my_tasks' => (int) $p['my_tasks'], 'blocked' => $p['blocked_at'] !== null,
                'role' => $creator ? 'سازنده' : ($roles[\App\Services\Access::memberRole($pm ?? [])]['label'] ?? 'سفارشی'),
                'to' => $all ? '/tasks?scope=project&project=' . pid('projects', $p['id']) . '&status=all' : '/tasks?scope=mine&status=all&project=' . pid('projects', $p['id'])];
        }
        $last = $u['last_active_at'] ?? $u['last_login_at'] ?? null;
        $this->ok('', ['user' => [
            'id' => pid('users', $uid), 'name' => full_name($u), 'mobile' => $u['mobile'], 'email' => $u['email'], 'avatar' => avatar_url($u['avatar']),
            'role' => Auth::adminRole($u) ? Auth::ADMIN_ROLES[Auth::adminRole($u)]['label'] : 'کاربر', 'status' => $u['status'],
            'created_fa' => jdate((string) $u['created_at']), 'last_active_fa' => $last ? jdate((string) $last) : null, 'last_active_ago' => $last ? time_ago((string) $last) : null,
            'last_login_fa' => $u['last_login_at'] ? jdate((string) $u['last_login_at']) : null,
            'tasks' => (int) ($t['total'] ?? 0), 'tasks_done' => (int) ($t['done'] ?? 0), 'tasks_review' => (int) ($t['review'] ?? 0), 'tasks_late' => (int) ($t['late'] ?? 0), 'tasks_created' => $created,
            'can_enter' => $canEnter, 'enter_why' => $canEnter ? null : ($u['role'] === 'admin' ? 'ورود به پنل مدیران ممکن نیست.' : 'فقط به پنل کاربران فعال می‌توان وارد شد.'),
        ], 'projects' => $projects]);
    }

    /** تسک‌های یک کاربر (مسئولش) با صفحه‌بندی: GET /admin/users/{id}/tasks */
    public function userTasks(string $id)
    {
        $this->need('view_users');
        $this->rateLimit('admin_list', 120);
        $uid = (int) $id;
        if (!DB::value('SELECT 1 FROM users WHERE id = ? AND is_bot = 0', [$uid])) {
            $this->fail('کاربر یافت نشد.', 404);
        }
        $per = (int) ($this->request->query['per'] ?? self::USERS_PER_PAGE[0]);
        $per = in_array($per, self::USERS_PER_PAGE, true) ? $per : self::USERS_PER_PAGE[0];
        $total = (int) DB::value('SELECT COUNT(*) FROM tasks WHERE assignee_id = ?', [$uid]);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($pages, max(1, (int) ($this->request->query['page'] ?? 1)));
        $rows = DB::all('SELECT t.id, t.title, t.priority, t.priority_percent, t.is_completed, t.board_status, t.project_id, t.archived_at, t.blocked_at, t.due_date, t.created_at, t.share_token, p.title AS project_title
            FROM tasks t LEFT JOIN projects p ON p.id = t.project_id WHERE t.assignee_id = ? ORDER BY t.is_completed, t.created_at DESC, t.id DESC LIMIT ? OFFSET ?', [$uid, $per, ($page - 1) * $per]);
        $stl = static fn(array $x): array => \App\Services\BoardConfig::info($x['project_id'] !== null ? (int) $x['project_id'] : null, (string) $x['board_status']);
        $items = array_map(static fn($x) => ['title' => $x['title'], 'project' => $x['project_title'] ?? '—',
            'status' => $x['blocked_at'] ? 'مسدود' : ($x['archived_at'] ? 'آرشیو' : ($x['project_id'] !== null ? $stl($x)['label'] : ($x['is_completed'] ? 'انجام‌شده' : 'باز'))),
            'tone' => $x['blocked_at'] ? 'danger' : ($x['archived_at'] ? 'muted' : $stl($x)['tone']),
            'priority' => $x['priority_percent'] !== null ? fa_num((int) $x['priority_percent']) . '٪' : ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'][$x['priority']],
            'due_fa' => $x['due_date'] ? jdate($x['due_date'] . ' 00:00:00', false) : '—', 'late' => !$x['is_completed'] && $x['due_date'] && $x['due_date'] < date('Y-m-d'),
            'created_fa' => jdate((string) $x['created_at']),
            'to' => '/tasks?task=' . ($x['share_token'] ?: \App\Models\TaskModel::ensureShareToken((int) $x['id']))], $rows);
        $this->ok('', ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per]);
    }

    // ───── پشتیبانی ─────

    private function desk(?array $ticket): void
    {
        $this->view('support/desk', SupportDesk::data(true, $this->uid(), $this->request, $ticket) + ['supportUnread' => SupportModel::unreadForAdmin()],
            ['title' => $ticket ? 'تیکت #' . pid('support_tickets', $ticket) : 'پشتیبانی', 'page' => 'admin-support', 'bodyClass' => 'page-desk'], 'admin');
    }

    private function ticket(string $id): array
    {
        $t = SupportModel::find((int) $id);
        if ($t === null) {
            $this->abort(404, 'تیکت یافت نشد.', 'NOT_FOUND');
        }
        return $t;
    }

    public function support()
    {
        $this->need('support_view');
        $this->desk(null);
    }

    public function supportShow(string $id)
    {
        $this->need('support_view');
        $this->desk($this->ticket($id));
    }

    public function supportReply(string $id)
    {
        $this->need('support_reply');
        $t = $this->ticket($id);
        [$ok, $msg] = SupportModel::addMessage($t, $this->uid(), 'admin', $this->request->str('message', SupportModel::MSG_MAX), $this->request->file('attachment'));
        $this->respond($ok, $msg, '/admin/support/' . pid('support_tickets', $id));
    }

    public function supportStatus(string $id)
    {
        $this->need('support_manage');
        $t = $this->ticket($id);
        $s = Validate::enum($this->request->input('status'), ['open', 'in_progress', 'answered', 'closed'], '');
        if ($s === '') {
            $this->respond(false, 'وضعیت نامعتبر است.', '/admin/support/' . pid('support_tickets', $id));
        }
        DB::run('UPDATE support_tickets SET status = ?, closed_at = ' . ($s === 'closed' ? 'NOW()' : 'NULL') . ' WHERE id = ?', [$s, (int) $t['id']]);
        $labels = ['open' => 'تیکت دوباره باز شد.', 'in_progress' => 'تیکت «در حال انجام» شد.', 'answered' => 'وضعیت تیکت تغییر کرد.', 'closed' => 'تیکت بسته شد.'];
        $this->respond(true, $labels[$s], '/admin/support/' . pid('support_tickets', $id));
    }

    public function supportDelete(string $id)
    {
        $this->need('support_manage');
        SupportModel::delete((int) $this->ticket($id)['id']);
        $this->respond(true, 'تیکت حذف شد.', '/admin/support');
    }

    // ───── تحلیل ─────

    /** محتوا ← سایدبار: چیدمان، نام و مقصد (صفحه‌ی پنل یا لینک خارجی) گزینه‌های سایدبار پنل کاربر */
    public function contentSidebar()
    {
        $this->need('edit_sidebar');
        $pages = [];
        foreach (SidebarModel::pages() as $k => [$path, $icon, $label]) {
            $pages[] = ['key' => $k, 'path' => $path, 'icon' => $icon, 'label' => $label];
        }
        $this->view('admin/content_sidebar', [
            'menu' => SidebarModel::currentWithDesc(), 'pages' => $pages, 'custom' => SidebarModel::isCustom(),
            'options' => SidebarModel::options(), 'maxDesc' => SidebarModel::MAX_DESC, 'defaultDesc' => SidebarModel::descriptions(),
            'linkIcon' => SidebarModel::LINK_ICON, 'supportUnread' => SupportModel::unreadForAdmin(),
        ], ['title' => 'سایدبار', 'page' => 'admin-content-sidebar'], 'admin');
    }

    public function contentSidebarSave()
    {
        $this->need('edit_sidebar');
        $data = json_decode((string) $this->request->input('menu', ''), true);
        if (!is_array($data)) {
            $this->respond(false, 'داده‌ی سایدبار نامعتبر است.', '/admin/content/sidebar');
        }
        [$menu, $err] = SidebarModel::normalize($data);
        if ($err !== null) {
            $this->respond(false, $err, '/admin/content/sidebar');
        }
        if (!$menu) {
            $this->respond(false, 'سایدبار نمی‌تواند خالی باشد.', '/admin/content/sidebar');
        }
        SidebarModel::save($menu, $this->uid());
        SidebarModel::saveOptions(['show_desc' => $this->request->input('show_desc', '1') === '1'], $this->uid());
        Audit::log($this->uid(), 'content_updated', ['key' => 'sidebar.user']);
        $this->respond(true, 'سایدبار پنل کاربران ذخیره شد.', '/admin/content/sidebar');
    }

    public function contentSidebarReset()
    {
        $this->need('edit_sidebar');
        SidebarModel::reset();
        Audit::log($this->uid(), 'content_updated', ['key' => 'sidebar.user', 'reset' => true]);
        $this->respond(true, 'سایدبار به چیدمان پیش‌فرض برگشت.', '/admin/content/sidebar');
    }

    /** محتوا ← Placeholder: متن راهنمای فیلدهای پنل کاربر با مسیر هر کدام (config/placeholder_texts.php) */
    public function contentPlaceholders()
    {
        $this->need('edit_placeholders');
        $this->view('admin/content_placeholders', ['groups' => PlaceholderText::forAdmin(), 'supportUnread' => SupportModel::unreadForAdmin()],
            ['title' => 'Placeholder', 'page' => 'admin-content-ph'], 'admin');
    }

    public function contentPlaceholderSave(string $id)
    {
        $this->need('edit_placeholders');
        $err = PlaceholderText::save($id, (string) $this->request->input('body', ''), $this->uid());
        if ($err !== null) {
            $this->respond(false, $err, '/admin/content/placeholders');
        }
        Audit::log($this->uid(), 'content_updated', ['key' => PlaceholderText::PREFIX . $id]);
        $this->respond(true, 'متن «' . $id . '» ذخیره شد.', '/admin/content/placeholders');
    }

    /** محتوا ← خطاها: همه‌ی خطاهای پنل کاربر با توضیح و متن قابل ویرایش (config/error_texts.php) */
    public function contentErrors()
    {
        $this->need('edit_errors');
        $this->view('admin/content_errors', ['groups' => ErrorText::forAdmin(), 'supportUnread' => SupportModel::unreadForAdmin()],
            ['title' => 'خطاها', 'page' => 'admin-content-errors'], 'admin');
    }

    public function contentErrorSave(string $id)
    {
        $this->need('edit_errors');
        $err = ErrorText::save($id, (string) $this->request->input('body', ''), $this->uid());
        if ($err !== null) {
            $this->respond(false, $err, '/admin/content/errors');
        }
        Audit::log($this->uid(), 'content_updated', ['key' => ErrorText::PREFIX . $id]);
        $this->respond(true, 'متن خطای «' . $id . '» ذخیره شد.', '/admin/content/errors');
    }

    /** محتوا ← تور آموزشی: ویرایش متن‌هایی که در تور آموزشی پنل کاربر نمایش داده می‌شوند */
    public function contentTour()
    {
        $this->need('edit_tour');
        $this->view('admin/content_tour', ['groups' => ContentModel::tourForAdmin(), 'supportUnread' => SupportModel::unreadForAdmin()],
            ['title' => 'تور آموزشی', 'page' => 'admin-content-tour'], 'admin');
    }

    /** محتوا ← بورد (v6.1): بورد پیش‌فرض پروژه‌هایی که بورد اختصاصی ندارند */
    public function contentBoard()
    {
        $this->need('edit_board');
        $this->view('admin/content_board', ['custom' => \App\Services\BoardConfig::templateIsCustom(), 'supportUnread' => SupportModel::unreadForAdmin()],
            ['title' => 'بورد پیش‌فرض', 'page' => 'admin-content-board'], 'admin');
    }

    public function contentBoardData()
    {
        $this->need('edit_board');
        $this->ok('', \App\Services\BoardConfig::editorData(\App\Services\BoardConfig::template()));
    }

    public function contentBoardSave()
    {
        $this->need('edit_board');
        $cfg = json_decode((string) $this->request->input('config', ''), true);
        $reset = (string) $this->request->input('reset', '') === '1';
        if (!$reset && !is_array($cfg)) {
            $this->respond(false, 'داده‌ی بورد نامعتبر است.', '/admin/content/board');
        }
        [$ok, $msg] = \App\Services\BoardConfig::saveTemplate($this->uid(), is_array($cfg) ? $cfg : [], $reset);
        $this->respond($ok, $msg, '/admin/content/board');
    }

    public function contentTourSave(string $id)
    {
        $this->need('edit_tour');
        if (!array_key_exists($id, ContentModel::tourDefs())) {
            $this->respond(false, 'متن یافت نشد.', '/admin/content/tour');
        }
        $body = trim(str_replace("\r\n", "\n", (string) $this->request->input('body', '')));
        if ($body === '') {
            $this->respond(false, 'متن نمی‌تواند خالی باشد.', '/admin/content/tour');
        }
        if (mb_strlen($body) > ContentModel::MAX_LEN) {
            $this->respond(false, 'متن حداکثر ' . fa_num(ContentModel::MAX_LEN) . ' نویسه است.', '/admin/content/tour');
        }
        ContentModel::setTour($id, $body, $this->uid());
        Audit::log($this->uid(), 'content_updated', ['key' => 'tour.' . $id]);
        $this->respond(true, 'متن «' . $id . '» ذخیره شد.', '/admin/content/tour');
    }

    /** تنظیمات ← امنیت: لاگ ورود مدیران به پنل کاربر (ورود، خروج، هر تغییر با قبل و بعد) — App\Core\AdminLog */
    /* ───────── تنظیمات ← دسترسی (v7.4، SEC-001): فقط مدیر کل ───────── */

    private function needSuper(): void
    {
        if (!Auth::isSuperAdmin()) {
            $land = \App\Core\AdminAccess::landing($this->user() ?? []);
            if ($this->request->method() === 'GET' && !$this->request->wantsJson() && $land !== null) {
                $this->flash('فقط مدیر کل به بخش «دسترسی» دسترسی دارد.', 'error');
                $this->redirect($land);
            }
            $this->fail('فقط مدیر کل به بخش «دسترسی» دسترسی دارد.', 403);
        }
        $this->denyImpersonation('/admin');
    }

    public function access()
    {
        $this->needSuper();
        $admins = DB::all("SELECT id, pid, first_name, last_name, mobile, avatar, status, role, admin_role, admin_permissions, last_login_at FROM users WHERE role = 'admin' AND is_bot = 0 ORDER BY id ASC");
        foreach ($admins as &$a) {
            $a['ar'] = Auth::adminRole($a);
            $a['custom'] = \App\Core\AdminAccess::custom($a);
            $a['perms'] = \App\Core\AdminAccess::of($a);
        }
        unset($a);
        $this->view('admin/access', [
            'admins' => $admins, 'roles' => \App\Core\AdminAccess::roles(), 'defs' => \App\Core\AdminAccess::PERMS, 'groups' => \App\Core\AdminAccess::GROUPS,
            'defaults' => \App\Core\AdminAccess::DEFAULTS, 'adminRoles' => Auth::ADMIN_ROLES, 'superId' => Auth::superAdminId(), 'supportUnread' => SupportModel::unreadForAdmin(),
        ], ['title' => 'دسترسی', 'page' => 'admin-access'], 'admin');
    }

    /** ذخیره‌ی دسترسی نقش‌ها (مدیر داخلی، پشتیبان) */
    public function accessRolesSave()
    {
        $this->needSuper();
        $map = [];
        foreach (array_keys(\App\Core\AdminAccess::DEFAULTS) as $role) {
            $map[$role] = $this->request->arr('perm_' . $role);
        }
        \App\Core\AdminAccess::saveRoles($map, $this->uid());
        Audit::log($this->uid(), 'admin_access_roles', ['roles' => array_map('count', $map)]);
        $this->respond(true, 'دسترسی نقش‌ها ذخیره شد.', '/admin/access');
    }

    public function accessRolesReset()
    {
        $this->needSuper();
        \App\Core\AdminAccess::resetRoles();
        Audit::log($this->uid(), 'admin_access_reset', []);
        $this->respond(true, 'دسترسی نقش‌ها به پیش‌فرض برگشت.', '/admin/access');
    }

    /** نقش و دسترسی یک مدیر: role (internal|support|user = برداشتن مدیریت)، mode = role|custom، perms[] */
    public function accessUserSave(string $id)
    {
        $this->needSuper();
        $t = UserModel::find((int) $id);
        if ($t === null || $t['role'] !== 'admin' || (int) $t['is_bot'] === 1) {
            $this->respond(false, 'مدیر یافت نشد.', '/admin/access');
        }
        if ((int) $t['id'] === Auth::superAdminId()) {
            $this->respond(false, 'نقش و دسترسی حساب مدیر کل اصلی تغییر نمی‌کند.', '/admin/access');
        }
        $role = $this->request->str('admin_role', 20);
        if (!isset(Auth::ADMIN_ROLES[$role]) && $role !== 'user') {
            $this->respond(false, 'نقش انتخاب‌شده معتبر نیست.', '/admin/access');
        }
        $custom = $this->request->str('mode', 10) === 'custom' && $role !== 'super' && $role !== 'user'
            ? json_encode(\App\Core\AdminAccess::clean($this->request->arr('perms'))) : null;
        $d = $role === 'user' ? ['role' => 'user', 'admin_role' => null, 'admin_permissions' => null] : ['admin_role' => $role, 'admin_permissions' => $custom];
        DB::update('users', $d, 'id = ?', [(int) $t['id']]);
        Audit::log($this->uid(), 'admin_access_user', ['id' => (int) $t['id'], 'role' => $role, 'custom' => $custom !== null]);
        $this->respond(true, $role === 'user' ? 'دسترسی مدیریت این حساب برداشته شد؛ حالا کاربر عادی است.' : 'نقش و دسترسی «' . full_name($t) . '» ذخیره شد.', '/admin/access');
    }

    public function security()
    {
        $this->need('view_security');
        $this->rateLimit('admin_list', 60);
        $r = $this->request->query;
        $per = (int) ($r['per'] ?? self::USERS_PER_PAGE[0]);
        $per = in_array($per, self::USERS_PER_PAGE, true) ? $per : self::USERS_PER_PAGE[0];
        $adminId = (int) \App\Core\Pid::id('users', (string) ($r['admin'] ?? ''));
        $userId = (int) \App\Core\Pid::id('users', (string) ($r['u'] ?? ''));
        $kinds = ['all' => 'همه', 'enter' => 'ورود', 'exit' => 'خروج', 'change' => 'تغییرها'];
        $kind = isset($kinds[$r['kind'] ?? '']) ? (string) $r['kind'] : 'all';
        $w = ['1'];
        $p = [];
        if ($adminId > 0) {
            $w[] = 'l.admin_id = ?';
            $p[] = $adminId;
        }
        if ($userId > 0) {
            $w[] = 'l.target_id = ?';
            $p[] = $userId;
        }
        if ($kind !== 'all') {
            $w[] = $kind === 'change' ? "l.action IN ('create','update','delete')" : 'l.action = ?';
            if ($kind !== 'change') {
                $p[] = $kind;
            }
        }
        $where = ' WHERE ' . implode(' AND ', $w);
        $total = (int) DB::value('SELECT COUNT(*) FROM admin_logs l' . $where, $p);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($pages, max(1, (int) ($r['page'] ?? 1)));
        $rows = DB::all('SELECT l.* FROM admin_logs l' . $where . ' ORDER BY l.created_at DESC, l.id DESC LIMIT ? OFFSET ?', array_merge($p, [$per, ($page - 1) * $per]));
        $opt = static fn(string $col) => array_map(static fn($u) => ['id' => pid('users', $u), 'name' => full_name($u), 'mobile' => $u['mobile']],
            DB::all("SELECT u.id, u.pid, u.first_name, u.last_name, u.mobile FROM users u WHERE u.id IN (SELECT DISTINCT $col FROM admin_logs) ORDER BY u.first_name, u.last_name"));
        $this->view('admin/security', [
            'items' => \App\Core\AdminLog::present($rows), 'total' => $total, 'pg' => $page, 'pages' => $pages, 'per' => $per, 'perOptions' => self::USERS_PER_PAGE,
            'kinds' => $kinds, 'kind' => $kind, 'admins' => $opt('admin_id'), 'targets' => $opt('target_id'),
            'adminSel' => $adminId ? pid('users', $adminId) : '', 'userSel' => $userId ? pid('users', $userId) : '', 'supportUnread' => SupportModel::unreadForAdmin(),
        ], ['title' => 'امنیت', 'page' => 'admin-security'], 'admin');
    }

    /** تنظیمات ← مستندات پنل: محدودیت‌ها و قواعد امنیتی مهم (App\Services\PanelDocs) */
    public function panelDocs()
    {
        $this->need('view_panel_docs');
        $this->view('admin/panel_docs', ['cards' => \App\Services\PanelDocs::cards(), 'supportUnread' => SupportModel::unreadForAdmin()],
            ['title' => 'مستندات پنل', 'page' => 'admin-panel-docs'], 'admin');
    }

    /** فهرست‌های فقط‌خواندنی کارت‌های «تحلیل سیستم»: [عنوان، آیکون، صفحه‌ی منو] */
    private const LISTS = [
        'tasks' => ['همه‌ی تسک‌ها', 'fa-list-check'],
        'projects' => ['همه‌ی پروژه‌ها', 'fa-diagram-project'],
    ];

    /** GET /admin/list/{kind}: فهرست صفحه‌بندی‌شده با جستجو و فیلتر وضعیت (کلیک روی کارت‌های پیشخوان مدیر) */
    public function listing(string $kind)
    {
        $this->need('view_analysis');
        if (!isset(self::LISTS[$kind])) {
            $this->abort(404);
        }
        $this->rateLimit('admin_list', 60);
        $r = $this->request->query;
        $per = (int) ($r['per'] ?? Session::get('admin_users_per', self::USERS_PER_PAGE[0]));
        $per = in_array($per, self::USERS_PER_PAGE, true) ? $per : self::USERS_PER_PAGE[0];
        if (isset($r['per'])) {
            Session::put('admin_users_per', $per);
        }
        $q = trim(mb_substr((string) ($r['q'] ?? ''), 0, 100));
        $filters = [
            'tasks' => ['all' => 'همه', 'open' => 'باز', 'review' => 'منتظر تأیید', 'done' => 'انجام‌شده', 'blocked' => 'مسدود'],
            'projects' => ['all' => 'همه', 'active' => 'فعال', 'blocked' => 'مسدود'],
        ][$kind];
        $st = isset($filters[$r['status'] ?? '']) ? (string) $r['status'] : 'all';
        $w = [];
        $p = [];
        $like = DB::like($q);
        if ($kind === 'tasks') {
            $base = 'FROM tasks t LEFT JOIN projects p ON p.id = t.project_id LEFT JOIN users ua ON ua.id = t.assignee_id LEFT JOIN users uc ON uc.id = t.user_id';
            $w[] = ['all' => '1', 'open' => 't.is_completed = 0 AND t.blocked_at IS NULL', 'review' => "t.board_status = 'review' AND t.is_completed = 0 AND t.blocked_at IS NULL", 'done' => 't.is_completed = 1 AND t.blocked_at IS NULL', 'blocked' => 't.blocked_at IS NOT NULL'][$st];
            if ($q !== '') {
                [$qs, $qp] = \App\Core\Search::sql(['t.title', 'p.title', "CONCAT(ua.first_name, ' ', ua.last_name)"], $q);
                $w[] = $qs;
                $p = array_merge($p, $qp);
            }
            $sel = "SELECT t.id, t.title, t.priority, t.priority_percent, t.is_completed, t.board_status, t.project_id, t.archived_at, t.blocked_at, t.due_date, t.due_time, t.created_at, t.completed_at,
                    p.title AS project_title, ua.first_name AS a_first, ua.last_name AS a_last, uc.first_name AS c_first, uc.last_name AS c_last $base";
            $order = 't.created_at DESC, t.id DESC';
        } elseif ($kind === 'projects') {
            $base = 'FROM projects p LEFT JOIN users u ON u.id = p.user_id';
            $w[] = 'p.is_sample = 0';                        // پروژه‌های نمونه‌ی خودکار ثبت‌نام شمرده نمی‌شوند
            $w[] = ['all' => '1', 'active' => 'p.blocked_at IS NULL', 'blocked' => 'p.blocked_at IS NOT NULL'][$st];
            if ($q !== '') {
                [$qs, $qp] = \App\Core\Search::sql(['p.title', "CONCAT(u.first_name, ' ', u.last_name)"], $q);
                $w[] = $qs;
                $p = array_merge($p, $qp);
            }
            $sel = "SELECT p.id, p.title, p.priority_type, p.blocked_at, p.created_at, p.max_members, u.first_name, u.last_name,
                    (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id <> p.user_id) + 1 AS members,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.blocked_at IS NULL) AS tasks,
                    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.blocked_at IS NULL AND t.is_completed = 1) AS done $base";
            $order = 'p.created_at DESC, p.id DESC';
        }
        $where = ' WHERE ' . implode(' AND ', $w);
        $total = (int) DB::value('SELECT COUNT(*) ' . $base . $where, $p);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($pages, max(1, (int) ($r['page'] ?? 1)));
        $rows = DB::all($sel . $where . ' ORDER BY ' . $order . ' LIMIT ? OFFSET ?', array_merge($p, [$per, ($page - 1) * $per]));
        [$title, $icon] = self::LISTS[$kind];
        $this->view('admin/list', ['kind' => $kind, 'rows' => $rows, 'total' => $total, 'pg' => $page, 'pages' => $pages, 'per' => $per, 'perOptions' => self::USERS_PER_PAGE,
            'q' => $q, 'status' => $st, 'filters' => $filters, 'heading' => $title, 'icon' => $icon, 'supportUnread' => SupportModel::unreadForAdmin()],
            ['title' => $title, 'page' => 'admin-analysis'], 'admin');
    }

    /** پنجره‌ی جزئیات پروژه در فهرست پروژه‌های مدیر: GET /admin/list/projects/{id} */
    public function projectInfo(string $id)
    {
        $this->need('view_analysis');
        $this->rateLimit('admin_list', 120);
        $p = DB::one('SELECT p.id, p.title, p.description, p.priority_type, p.max_members, p.blocked_at, p.created_at, p.user_id, u.first_name, u.last_name, u.mobile, u.avatar, u.role, u.status
            FROM projects p LEFT JOIN users u ON u.id = p.user_id WHERE p.id = ?', [(int) $id]);
        if ($p === null) {
            $this->fail('پروژه یافت نشد.', 404);
        }
        $cnt = DB::one('SELECT COUNT(*) total, COALESCE(SUM(is_completed = 1 AND blocked_at IS NULL),0) done, COALESCE(SUM(blocked_at IS NOT NULL),0) blocked FROM tasks WHERE project_id = ?', [(int) $id]) ?? [];
        $this->ok('', ['project' => [
            'id' => pid('projects', $p['id']), 'title' => $p['title'], 'description' => $p['description'], 'created_fa' => jdate((string) $p['created_at']),
            'creator' => full_name($p), 'creator_mobile' => $p['mobile'], 'creator_avatar' => avatar_url($p['avatar']), 'creator_id' => (int) $p['user_id'],
            // «ورود به پروژه»: با نقش مدیر وارد پنل سازنده و صفحه‌ی پروژه‌ها (همین پروژه برجسته)
            'can_enter' => Auth::adminCan('impersonate') && $p['role'] !== 'admin' && $p['status'] === 'active',
            'enter_why' => $p['role'] === 'admin' ? 'سازنده‌ی این پروژه مدیر است؛ ورود به پنل مدیران ممکن نیست.' : ($p['status'] !== 'active' ? 'حساب سازنده‌ی این پروژه فعال نیست.' : null),
            'priority_type' => $p['priority_type'] === 'percent' ? 'درصدی' : 'دسته‌ای (کم/متوسط/زیاد)', 'is_blocked' => $p['blocked_at'] !== null,
            'members' => (int) DB::value('SELECT COUNT(*) FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.project_id = ? AND pm.user_id <> p.user_id', [(int) $id]) + 1, 'max_members' => (int) $p['max_members'],
            'tasks' => (int) ($cnt['total'] ?? 0), 'tasks_done' => (int) ($cnt['done'] ?? 0), 'tasks_blocked' => (int) ($cnt['blocked'] ?? 0),
        ]]);
    }

    /** اعضا یا تسک‌های یک پروژه (صفحه‌بندی‌شده) برای همان پنجره: GET /admin/list/projects/{id}/{part} */
    public function projectPart(string $id, string $part)
    {
        $this->need('view_analysis');
        $this->rateLimit('admin_list', 120);
        $pid = (int) $id;
        if (!in_array($part, ['members', 'tasks'], true) || !DB::value('SELECT 1 FROM projects WHERE id = ?', [$pid])) {
            $this->fail('یافت نشد.', 404);
        }
        $per = (int) ($this->request->query['per'] ?? self::USERS_PER_PAGE[0]);
        $per = in_array($per, self::USERS_PER_PAGE, true) ? $per : self::USERS_PER_PAGE[0];
        if ($part === 'members') {
            $total = (int) DB::value('SELECT COUNT(*) FROM project_members pm JOIN projects p ON p.id = pm.project_id WHERE pm.project_id = ? AND pm.user_id <> p.user_id', [$pid]) + 1;
            $pages = max(1, (int) ceil($total / $per));
            $page = min($pages, max(1, (int) ($this->request->query['page'] ?? 1)));
            // سازنده اول، بعد اعضا به ترتیب عضویت
            $rows = DB::all("SELECT * FROM (
                    SELECT u.id, u.first_name, u.last_name, u.mobile, u.avatar, p.created_at AS added_at, 1 AS is_creator, NULL AS permissions, NULL AS task_permissions, NULL AS board_permissions, NULL AS board_view_users,
                        (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.assignee_id = u.id AND t.blocked_at IS NULL) AS tasks
                    FROM projects p JOIN users u ON u.id = p.user_id WHERE p.id = ?
                    UNION ALL
                    SELECT u.id, u.first_name, u.last_name, u.mobile, u.avatar, pm.added_at, 0, pm.permissions, pm.task_permissions, pm.board_permissions, pm.board_view_users,
                        (SELECT COUNT(*) FROM tasks t WHERE t.project_id = pm.project_id AND t.assignee_id = u.id AND t.blocked_at IS NULL)
                    FROM project_members pm JOIN users u ON u.id = pm.user_id JOIN projects pp ON pp.id = pm.project_id WHERE pm.project_id = ? AND pm.user_id <> pp.user_id) x
                ORDER BY is_creator DESC, added_at, id LIMIT ? OFFSET ?", [$pid, $pid, $per, ($page - 1) * $per]);
            $roles = \App\Services\Access::ROLES + ['custom' => \App\Services\Access::ROLE_CUSTOM];
            $items = array_map(static fn($m) => ['name' => full_name($m), 'mobile' => $m['mobile'], 'avatar' => avatar_url($m['avatar']), 'added_fa' => jdate((string) $m['added_at']),
                'role' => $m['is_creator'] ? 'سازنده' : ($roles[\App\Services\Access::memberRole($m)]['label'] ?? 'سفارشی'), 'tasks' => (int) $m['tasks'], 'is_creator' => (bool) $m['is_creator']], $rows);
        } else {
            $total = (int) DB::value('SELECT COUNT(*) FROM tasks WHERE project_id = ?', [$pid]);
            $pages = max(1, (int) ceil($total / $per));
            $page = min($pages, max(1, (int) ($this->request->query['page'] ?? 1)));
            $rows = DB::all('SELECT t.id, t.title, t.priority, t.priority_percent, t.is_completed, t.board_status, t.archived_at, t.blocked_at, t.due_date, t.created_at, ua.first_name, ua.last_name
                FROM tasks t LEFT JOIN users ua ON ua.id = t.assignee_id WHERE t.project_id = ? ORDER BY t.created_at DESC, t.id DESC LIMIT ? OFFSET ?', [$pid, $per, ($page - 1) * $per]);
            $stl = static fn(array $t): array => \App\Services\BoardConfig::info($pid, (string) $t['board_status']);
            $items = array_map(static fn($t) => ['title' => $t['title'], 'assignee' => $t['first_name'] !== null ? full_name($t) : '—',
                'status' => $t['blocked_at'] ? 'مسدود' : ($t['archived_at'] ? 'آرشیو' : $stl($t)['label']),
                'tone' => $t['blocked_at'] ? 'danger' : ($t['archived_at'] ? 'muted' : $stl($t)['tone']),
                'priority' => $t['priority_percent'] !== null ? fa_num((int) $t['priority_percent']) . '٪' : ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'][$t['priority']],
                'due_fa' => $t['due_date'] ? jdate($t['due_date'] . ' 00:00:00', false) : '—', 'late' => !$t['is_completed'] && $t['due_date'] && $t['due_date'] < date('Y-m-d'),
                'created_fa' => jdate((string) $t['created_at'])], $rows);
        }
        $this->ok('', ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per]);
    }

    public function analysis()
    {
        $this->need('view_analysis');
        $c = static fn(string $sql): int => (int) DB::value($sql);
        $stats = [
            'users' => UserModel::counts(),
            'tasks' => ['total' => $c('SELECT COUNT(*) FROM tasks'), 'done' => $c('SELECT COUNT(*) FROM tasks WHERE is_completed = 1'), 'open' => $c('SELECT COUNT(*) FROM tasks WHERE is_completed = 0'), 'high' => $c("SELECT COUNT(*) FROM tasks WHERE priority = 'high'"), 'medium' => $c("SELECT COUNT(*) FROM tasks WHERE priority = 'medium'"), 'low' => $c("SELECT COUNT(*) FROM tasks WHERE priority = 'low'")],
            'misc' => ['notes' => $c('SELECT COUNT(*) FROM task_notes'), 'texts' => $c('SELECT COUNT(*) FROM texts'), 'projects' => $c('SELECT COUNT(*) FROM projects WHERE is_sample = 0'), 'members' => $c('SELECT COUNT(*) FROM project_members'), 'messages' => $c('SELECT COUNT(*) FROM chat_messages'), 'tokens' => $c('SELECT COUNT(*) FROM api_tokens WHERE is_active = 1'), 'support_open' => $c("SELECT COUNT(*) FROM support_tickets WHERE status <> 'closed'")],
        ];
        $days = [];
        $tk = array_column(DB::all('SELECT DATE(created_at) d, COUNT(*) c FROM tasks WHERE created_at >= (CURDATE() - INTERVAL 13 DAY) GROUP BY DATE(created_at)'), 'c', 'd');
        $us = array_column(DB::all('SELECT DATE(created_at) d, COUNT(*) c FROM users WHERE is_bot = 0 AND created_at >= (CURDATE() - INTERVAL 13 DAY) GROUP BY DATE(created_at)'), 'c', 'd');
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $days['labels'][] = \App\Core\Jalali::md($d);
            $days['tasks'][] = (int) ($tk[$d] ?? 0);
            $days['users'][] = (int) ($us[$d] ?? 0);
        }
        $topUsers = DB::all('SELECT u.id, u.first_name, u.last_name, u.mobile, u.avatar, COUNT(t.id) cnt, COALESCE(SUM(t.is_completed = 1),0) done FROM users u INNER JOIN tasks t ON t.assignee_id = u.id WHERE u.is_bot = 0 GROUP BY u.id, u.first_name, u.last_name, u.mobile, u.avatar ORDER BY cnt DESC LIMIT 8');
        $topProjects = DB::all('SELECT p.id, p.title, p.profile_image, (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id AND pm.user_id <> p.user_id) + 1 members, COUNT(t.id) tasks FROM projects p LEFT JOIN tasks t ON t.project_id = p.id WHERE p.is_sample = 0 GROUP BY p.id, p.title, p.profile_image ORDER BY tasks DESC, members DESC LIMIT 8');
        $topSubjects = DB::all('SELECT s.title, COUNT(t.id) cnt FROM subjects s INNER JOIN tasks t ON t.subject_id = s.id GROUP BY s.title ORDER BY cnt DESC LIMIT 8');
        $chatSec = [
            'configured' => Crypto::configured(),
            'total' => (int) DB::value('SELECT COUNT(*) FROM chat_messages'),
            'enc' => (int) DB::value("SELECT COUNT(*) FROM chat_messages WHERE message LIKE 'enc:v1:%'"),
            'plain' => (int) DB::value("SELECT COUNT(*) FROM chat_messages WHERE (message <> '' AND message NOT LIKE 'enc:v1:%') OR (attachment_name IS NOT NULL AND attachment_name <> '' AND attachment_name NOT LIKE 'enc:v1:%')"),
            'isSuper' => Auth::adminCan('chat_encrypt'),
        ];
        $audit = DB::all('SELECT a.action, a.ip, a.created_at, a.details, u.first_name, u.last_name, u.mobile FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 15');
        $this->view('admin/analysis', compact('stats', 'days', 'topUsers', 'topProjects', 'topSubjects', 'audit', 'chatSec') + ['supportUnread' => SupportModel::unreadForAdmin()], ['title' => 'تحلیل سیستم', 'page' => 'admin-analysis'], 'admin');
    }

    /** رمزگذاری پیام‌های متن‌ساده‌ی قبلی (فقط مدیر کل). تا ~۲۰ ثانیه در هر کلیک؛ در صورت باقی‌ماندن، دوباره بزنید. */
    public function chatEncrypt()
    {
        $this->need('chat_encrypt');
        if (!Crypto::configured()) {
            $this->fail('ENCODE_PASSWORD در .env تنظیم نشده یا کمتر از ۱۲ نویسه است (یا OpenSSL فعال نیست).');
        }
        $pending = "(message <> '' AND message NOT LIKE 'enc:v1:%') OR (attachment_name IS NOT NULL AND attachment_name <> '' AND attachment_name NOT LIKE 'enc:v1:%')";
        $start = time();
        $done = 0;
        while (time() - $start < 20) {
            $rows = DB::all("SELECT id, sender_id, receiver_id, message, attachment_name FROM chat_messages WHERE $pending ORDER BY id ASC LIMIT 300");
            if (!$rows) {
                break;
            }
            DB::transaction(static function () use ($rows, &$done): void {
                foreach ($rows as $r) {
                    $set = [];
                    if ($r['message'] !== '' && !Crypto::isEncoded($r['message'])) {
                        $set['message'] = \App\Models\ChatModel::seal($r['message'], (int) $r['sender_id'], (int) $r['receiver_id']);
                    }
                    if ($r['attachment_name'] !== null && $r['attachment_name'] !== '' && !Crypto::isEncoded($r['attachment_name'])) {
                        $set['attachment_name'] = \App\Models\ChatModel::seal($r['attachment_name'], (int) $r['sender_id'], (int) $r['receiver_id']);
                    }
                    if ($set) {
                        DB::update('chat_messages', $set, 'id = ?', [(int) $r['id']]);
                        $done++;
                    }
                }
            });
        }
        $left = (int) DB::value("SELECT COUNT(*) FROM chat_messages WHERE $pending");
        Audit::log($this->uid(), 'chat_encrypt_existing', ['done' => $done, 'left' => $left]);
        $this->respond(true, $left > 0 ? "$done پیام رمزگذاری شد؛ $left پیام باقی مانده است — دوباره بزنید." : "$done پیام رمزگذاری شد؛ همه‌ی پیام‌ها اکنون رمزشده‌اند.", '/admin/analysis');
    }

    /** «پایان و بستن» در نوار بالای پنجره‌ی پنل کاربر: فقط همین پنجره بسته می‌شود؛ نشست مدیر و تب‌های دیگر می‌مانند */
    public function stopImpersonate()
    {
        $c = Impersonation::current();
        if ($c !== null) {
            Impersonation::end((int) $c['id'], 'exit');
        }
        if ($this->request->wantsJson()) {
            $this->ok('پنجره‌ی پنل کاربر بسته شد.', ['close' => true, 'redirect' => app_base() . '/admin']);
        }
        Response::redirect(app_base() . '/admin');
    }
}
