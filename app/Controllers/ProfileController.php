<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Throttle;
use App\Core\Uploader;
use App\Core\Validate;
use App\Models\UserModel;
use App\Services\TaskService;

final class ProfileController extends Controller
{
    public function index()
    {
        $u = $this->user();
        $links = require BASE_PATH . '/config/quick_links.php';
        $sel = json_decode((string) $u['quick_links'], true);
        $this->view('profile/index', [
            'u' => $u, 'links' => $links, 'selected' => is_array($sel) ? $sel : [],
            'stats' => [
                'tasks' => (int) DB::value('SELECT COUNT(*) FROM tasks WHERE assignee_id = ?', [(int) $u['id']]),
                'colleagues' => (int) DB::value('SELECT COUNT(*) FROM colleagues WHERE user_id = ?', [(int) $u['id']]),
                'projects' => (int) DB::value('SELECT COUNT(*) FROM project_members WHERE user_id = ?', [(int) $u['id']]),
            ],
        ], ['title' => 'پروفایل', 'page' => 'profile']);
    }

    public function updateInfo()
    {
        $u = $this->user();
        $r = $this->request;
        $first = $r->str('first_name', 100);
        $last = $r->str('last_name', 100);
        $email = $r->str('email', 150);
        if (!Validate::name($first) || ($last !== '' && !Validate::name($last))) {
            $this->respond(false, 'نام یا نام‌خانوادگی نامعتبر است.', '/profile');
        }
        if ($email !== '' && !Validate::email($email)) {
            $this->respond(false, 'ایمیل نامعتبر است.', '/profile');
        }
        if ($email !== '' && UserModel::emailExists($email, (int) $u['id'])) {
            $this->respond(false, 'این ایمیل قبلاً استفاده شده است.', '/profile');
        }
        DB::update('users', ['first_name' => $first, 'last_name' => $last, 'email' => $email !== '' ? $email : null], 'id = ?', [(int) $u['id']]);
        $this->respond(true, 'اطلاعات پروفایل ذخیره شد.', '/profile');
    }

    public function updatePassword()
    {
        $u = $this->user();
        $r = $this->request;
        $cur = (string) $r->input('current_password', '');
        $new = (string) $r->input('new_password', '');
        $conf = (string) $r->input('confirm_password', '');
        if (!Throttle::attempt('pwd_change', (string) $u['id'], 5, 900)) {
            $this->respond(false, 'تلاش‌های زیاد؛ چند دقیقه بعد دوباره امتحان کنید.', '/profile');
        }
        if (!password_verify($cur, (string) $u['password_hash'])) {
            $this->respond(false, 'رمز عبور فعلی صحیح نیست.', '/profile');
        }
        if ($err = Validate::passwordError($new)) {
            $this->respond(false, $err, '/profile');
        }
        if (!hash_equals($new, $conf)) {
            $this->respond(false, 'تکرار رمز عبور جدید مطابقت ندارد.', '/profile');
        }
        if (hash_equals($cur, $new)) {
            $this->respond(false, 'رمز جدید باید با رمز فعلی متفاوت باشد.', '/profile');
        }
        DB::update('users', ['password_hash' => Auth::hash($new)], 'id = ?', [(int) $u['id']]);
        Auth::refreshPasswordVersion((int) $u['id']);
        Throttle::clear('pwd_change', (string) $u['id']);
        Audit::log((int) $u['id'], 'password_changed');
        $this->respond(true, 'رمز عبور تغییر کرد. سایر دستگاه‌ها از حساب خارج شدند.', '/profile');
    }

    public function avatar()
    {
        $u = $this->user();
        $f = $this->request->file('avatar');
        if ($f === null) {
            $this->respond(false, 'تصویری انتخاب نشده است.', '/profile');
        }
        $r = Uploader::saveImage($f, 'avatars', 'av' . $u['id']);
        if (!$r['ok']) {
            $this->respond(false, $r['error'], '/profile');
        }
        Uploader::delete('avatars', $u['avatar']);
        DB::update('users', ['avatar' => $r['name']], 'id = ?', [(int) $u['id']]);
        $this->respond(true, 'تصویر پروفایل به‌روزرسانی شد.', '/profile', ['avatar_url' => avatar_url($r['name'])]);
    }

    public function removeAvatar()
    {
        $u = $this->user();
        Uploader::delete('avatars', $u['avatar']);
        DB::update('users', ['avatar' => null], 'id = ?', [(int) $u['id']]);
        $this->respond(true, 'تصویر پروفایل حذف شد.', '/profile');
    }

    public function privacy()
    {
        $u = $this->user();
        $r = $this->request;
        DB::update('users', [
            'allow_colleague_requests' => $r->input('allow_colleague_requests') ? 1 : 0,
            'project_join_requires_approval' => $r->input('project_join_requires_approval') ? 1 : 0,
        ], 'id = ?', [(int) $u['id']]);
        $this->respond(true, 'تنظیمات حریم خصوصی ذخیره شد.', '/profile');
    }

    /** «امنیت تسک‌ها»: چه کسانی تسک‌های من را با لینک اشتراکی ببینند. */
    public function taskSecurity()
    {
        $u = $this->user();
        $v = $this->request->str('task_link_access', 20);
        if (!array_key_exists($v, TaskService::LINK_ACCESS)) {
            $this->respond(false, 'گزینه‌ی انتخاب‌شده معتبر نیست.', '/profile');
        }
        DB::update('users', ['task_link_access' => $v], 'id = ?', [(int) $u['id']]);
        $this->respond(true, 'تنظیمات امنیت تسک‌ها ذخیره شد.', '/profile');
    }

    public function quickLinks()
    {
        $u = $this->user();
        $all = array_column(require BASE_PATH . '/config/quick_links.php', 'id');
        $ids = array_values(array_intersect($all, array_map('strval', $this->request->arr('links'))));
        DB::update('users', ['quick_links' => json_encode($ids)], 'id = ?', [(int) $u['id']]);
        $this->respond(true, 'دسترسی سریع ذخیره شد.', '/profile');
    }
}
