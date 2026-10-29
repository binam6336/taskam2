<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Crypto;
use App\Core\DB;
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
    private const PERMS = ['add_user' => 'افزودن کاربر', 'edit_user' => 'ویرایش کاربر', 'toggle_status' => 'تغییر وضعیت', 'delete_user' => 'حذف کاربر'];

    private function isSuper(): bool
    {
        return $this->uid() === Auth::superAdminId();
    }

    private function need(string $perm): void
    {
        if (!Auth::adminCan($perm)) {
            $this->fail('شما این دسترسی را ندارید.', 403);
        }
    }

    public function users()
    {
        $me = $this->user();
        $this->view('admin/users', [
            'users' => UserModel::allForAdmin(), 'counts' => UserModel::counts(), 'myPerms' => Auth::adminPermissions($me),
            'isSuper' => $this->isSuper(), 'permDefs' => self::PERMS, 'superId' => Auth::superAdminId(),
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

    private function adminPerms(): ?string
    {
        $p = array_values(array_intersect(array_keys(self::PERMS), array_map('strval', $this->request->arr('admin_permissions'))));
        return json_encode($p);
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
        $role = $this->request->str('role') === 'admin' && $this->isSuper() ? 'admin' : 'user';
        $status = Validate::enum($this->request->input('status'), Auth::STATUSES, 'active');
        $id = DB::insert('users', $d + ['password_hash' => Auth::hash($pass), 'role' => $role, 'status' => $status, 'admin_permissions' => $role === 'admin' ? $this->adminPerms() : null]);
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
        if ($this->isSuper() && (int) $target['id'] !== Auth::superAdminId()) {
            $role = $this->request->str('role') === 'admin' ? 'admin' : 'user';
            $d['role'] = $role;
            $d['admin_permissions'] = $role === 'admin' ? $this->adminPerms() : null;
        }
        DB::update('users', $d, 'id = ?', [(int) $id]);
        Audit::log($this->uid(), 'admin_user_updated', ['id' => (int) $id, 'password' => $pass !== '']);
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
        $t = UserModel::find((int) $id);
        if ($t === null || $t['role'] === 'admin' || $t['status'] !== 'active' || (int) $t['is_bot'] === 1) {
            $this->respond(false, 'فقط کاربران عادیِ فعال قابل ورود هستند.', '/admin');
        }
        Auth::startImpersonation($this->uid(), (int) $t['id']);
        // درخواست AJAX باید JSON بگیرد (نه redirect به HTML → «پاسخ نامعتبر از سرور (200)»)
        if ($this->request->wantsJson()) {
            $this->ok('در حال ورود به پنل «' . full_name($t) . '» …', ['redirect' => url('/dashboard')]);
        }
        $this->redirect('/dashboard');
    }

    // ───── پشتیبانی ─────

    private function desk(?array $ticket): void
    {
        $this->view('support/desk', SupportDesk::data(true, $this->uid(), $this->request, $ticket) + ['supportUnread' => SupportModel::unreadForAdmin()],
            ['title' => $ticket ? 'تیکت #' . fa_num($ticket['id']) : 'پشتیبانی', 'page' => 'admin-support', 'bodyClass' => 'page-desk'], 'admin');
    }

    private function ticket(string $id): array
    {
        SupportModel::ensureSchema();
        $t = SupportModel::find((int) $id);
        if ($t === null) {
            $this->abort(404, 'تیکت یافت نشد.', 'NOT_FOUND');
        }
        return $t;
    }

    public function support()
    {
        SupportModel::ensureSchema();
        $this->desk(null);
    }

    public function supportShow(string $id)
    {
        $this->desk($this->ticket($id));
    }

    public function supportReply(string $id)
    {
        $t = $this->ticket($id);
        [$ok, $msg] = SupportModel::addMessage($t, $this->uid(), 'admin', $this->request->str('message', SupportModel::MSG_MAX), $this->request->file('attachment'));
        $this->respond($ok, $msg, '/admin/support/' . $id);
    }

    public function supportStatus(string $id)
    {
        $t = $this->ticket($id);
        $s = Validate::enum($this->request->input('status'), ['open', 'in_progress', 'answered', 'closed'], '');
        if ($s === '') {
            $this->respond(false, 'وضعیت نامعتبر است.', '/admin/support/' . $id);
        }
        DB::run('UPDATE support_tickets SET status = ?, closed_at = ' . ($s === 'closed' ? 'NOW()' : 'NULL') . ' WHERE id = ?', [$s, (int) $t['id']]);
        $labels = ['open' => 'تیکت دوباره باز شد.', 'in_progress' => 'تیکت «در حال انجام» شد.', 'answered' => 'وضعیت تیکت تغییر کرد.', 'closed' => 'تیکت بسته شد.'];
        $this->respond(true, $labels[$s], '/admin/support/' . $id);
    }

    public function supportDelete(string $id)
    {
        SupportModel::delete((int) $this->ticket($id)['id']);
        $this->respond(true, 'تیکت حذف شد.', '/admin/support');
    }

    // ───── تحلیل ─────

    /** محتوا ← سایدبار: چیدمان، نام و مقصد (صفحه‌ی پنل یا لینک خارجی) گزینه‌های سایدبار پنل کاربر */
    public function contentSidebar()
    {
        $pages = [];
        foreach (SidebarModel::pages() as $k => [$path, $icon, $label]) {
            $pages[] = ['key' => $k, 'path' => $path, 'icon' => $icon, 'label' => $label];
        }
        $this->view('admin/content_sidebar', [
            'menu' => SidebarModel::current(), 'pages' => $pages, 'custom' => SidebarModel::isCustom(),
            'linkIcon' => SidebarModel::LINK_ICON, 'supportUnread' => SupportModel::unreadForAdmin(),
        ], ['title' => 'سایدبار', 'page' => 'admin-content-sidebar'], 'admin');
    }

    public function contentSidebarSave()
    {
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
        Audit::log($this->uid(), 'content_updated', ['key' => 'sidebar.user']);
        $this->respond(true, 'سایدبار پنل کاربران ذخیره شد.', '/admin/content/sidebar');
    }

    public function contentSidebarReset()
    {
        SidebarModel::reset();
        Audit::log($this->uid(), 'content_updated', ['key' => 'sidebar.user', 'reset' => true]);
        $this->respond(true, 'سایدبار به چیدمان پیش‌فرض برگشت.', '/admin/content/sidebar');
    }

    /** محتوا ← تور آموزشی: ویرایش متن‌هایی که در تور آموزشی پنل کاربر نمایش داده می‌شوند */
    public function contentTour()
    {
        $this->view('admin/content_tour', ['groups' => ContentModel::tourForAdmin(), 'supportUnread' => SupportModel::unreadForAdmin()],
            ['title' => 'تور آموزشی', 'page' => 'admin-content-tour'], 'admin');
    }

    public function contentTourSave(string $id)
    {
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

    public function analysis()
    {
        $c = static fn(string $sql): int => (int) DB::value($sql);
        $stats = [
            'users' => UserModel::counts(),
            'tasks' => ['total' => $c('SELECT COUNT(*) FROM tasks'), 'done' => $c('SELECT COUNT(*) FROM tasks WHERE is_completed = 1'), 'open' => $c('SELECT COUNT(*) FROM tasks WHERE is_completed = 0'), 'high' => $c("SELECT COUNT(*) FROM tasks WHERE priority = 'high'"), 'medium' => $c("SELECT COUNT(*) FROM tasks WHERE priority = 'medium'"), 'low' => $c("SELECT COUNT(*) FROM tasks WHERE priority = 'low'")],
            'calls' => ['total' => $c('SELECT COUNT(*) FROM call_requests'), 'new' => $c('SELECT COUNT(*) FROM call_requests WHERE status = 0'), 'done' => $c('SELECT COUNT(*) FROM call_requests WHERE status = 1')],
            'misc' => ['notes' => $c('SELECT COUNT(*) FROM task_notes'), 'texts' => $c('SELECT COUNT(*) FROM texts'), 'projects' => $c('SELECT COUNT(*) FROM projects'), 'members' => $c('SELECT COUNT(*) FROM project_members'), 'messages' => $c('SELECT COUNT(*) FROM chat_messages'), 'tokens' => $c('SELECT COUNT(*) FROM api_tokens WHERE is_active = 1'), 'support_open' => $c("SELECT COUNT(*) FROM support_tickets WHERE status <> 'closed'")],
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
        $topProjects = DB::all('SELECT p.id, p.title, p.profile_image, (SELECT COUNT(*) FROM project_members pm WHERE pm.project_id = p.id) members, COUNT(t.id) tasks FROM projects p LEFT JOIN tasks t ON t.project_id = p.id GROUP BY p.id, p.title, p.profile_image ORDER BY tasks DESC, members DESC LIMIT 8');
        $topSubjects = DB::all('SELECT s.title, COUNT(t.id) cnt FROM subjects s INNER JOIN tasks t ON t.subject_id = s.id GROUP BY s.title ORDER BY cnt DESC LIMIT 8');
        $chatSec = [
            'configured' => Crypto::configured(),
            'total' => (int) DB::value('SELECT COUNT(*) FROM chat_messages'),
            'enc' => (int) DB::value("SELECT COUNT(*) FROM chat_messages WHERE message LIKE 'enc:v1:%'"),
            'plain' => (int) DB::value("SELECT COUNT(*) FROM chat_messages WHERE (message <> '' AND message NOT LIKE 'enc:v1:%') OR (attachment_name IS NOT NULL AND attachment_name <> '' AND attachment_name NOT LIKE 'enc:v1:%')"),
            'isSuper' => $this->isSuper(),
        ];
        $audit = DB::all('SELECT a.action, a.ip, a.created_at, a.details, u.first_name, u.last_name, u.mobile FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 15');
        $this->view('admin/analysis', compact('stats', 'days', 'topUsers', 'topProjects', 'topSubjects', 'audit', 'chatSec') + ['supportUnread' => SupportModel::unreadForAdmin()], ['title' => 'تحلیل سیستم', 'page' => 'admin-analysis'], 'admin');
    }

    /** رمزگذاری پیام‌های متن‌ساده‌ی قبلی (فقط مدیر کل). تا ~۲۰ ثانیه در هر کلیک؛ در صورت باقی‌ماندن، دوباره بزنید. */
    public function chatEncrypt()
    {
        if (!$this->isSuper()) {
            $this->fail('فقط مدیر کل می‌تواند این عملیات را انجام دهد.', 403);
        }
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

    public function stopImpersonate()
    {
        Auth::stopImpersonation();
        if ($this->request->wantsJson()) {
            $this->ok('بازگشت به پنل مدیریت.', ['redirect' => url('/admin')]);
        }
        $this->redirect('/admin');
    }
}
