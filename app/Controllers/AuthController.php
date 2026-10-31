<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\Throttle;
use App\Core\Validate;
use App\Models\UserModel;
use App\Services\ProjectService;

final class AuthController extends Controller
{
    public function showLogin()
    {
        // هر بازدید بدون کوکی یک نشست مهمان (ردیف user_sessions) می‌سازد؛ از هر IP حداکثر ۶۰ نشست جدید در دقیقه
        if (!Session::hasCookie() && !\App\Core\Throttle::attempt('guest_session', $this->request->ip(), 60, 60)) {
            header('Retry-After: 60');
            $this->abort(429, 'درخواست‌ها بیش از حد سریع است؛ چند لحظه بعد دوباره تلاش کنید.', 'RATE_LIMITED');
        }
        $this->view('auth/login', ['tab' => $this->request->query['tab'] ?? 'login'], ['title' => 'ورود به تسکام'], 'auth');
    }

    public function login()
    {
        $mobile = Validate::mobile($this->request->str('mobile', 30));
        $pass = (string) $this->request->input('password', '');
        Session::put('_old', ['mobile' => $this->request->str('mobile', 30)]);
        if ($mobile === null || $pass === '' || strlen($pass) > 200) {
            Session::flash('شماره موبایل یا رمز عبور اشتباه است.', 'error');
            $this->redirect('/login');
        }
        $r = Auth::attempt($mobile, $pass, $this->request, (bool) $this->request->input('remember'));
        if (!$r['ok']) {
            Session::flash($r['message'], 'error');
            $this->redirect('/login');
        }
        Session::forget('_old');
        $this->redirect('/');
    }

    public function register()
    {
        $req = $this->request;
        $first = $req->str('first_name', 100);
        $last = $req->str('last_name', 100);
        $mobileRaw = $req->str('mobile', 30);
        $email = $req->str('email', 150);
        $pass = (string) $req->input('password', '');
        $confirm = (string) $req->input('password_confirm', '');
        Session::put('_old', ['first_name' => $first, 'last_name' => $last, 'mobile' => $mobileRaw, 'email' => $email]);

        $fail = function (string $m) {
            Session::flash($m, 'error');
            $this->redirect('/login?tab=register');
        };

        if (!Throttle::attempt('register_ip', $req->ip(), 8, 3600)) {
            $fail('تعداد درخواست‌های ثبت‌نام از این شبکه زیاد است. بعداً تلاش کنید.');
        }
        if (!Validate::name($first) || ($last !== '' && !Validate::name($last))) {
            $fail('نام و نام‌خانوادگی نامعتبر است.');
        }
        $mobile = Validate::mobile($mobileRaw);
        if ($mobile === null) {
            $fail('شماره موبایل باید با فرمت ۰۹xxxxxxxxx باشد.');
        }
        if ($email !== '' && !Validate::email($email)) {
            $fail('ایمیل نامعتبر است.');
        }
        if ($err = Validate::passwordError($pass)) {
            $fail($err);
        }
        if (!hash_equals($pass, $confirm)) {
            $fail('تکرار رمز عبور مطابقت ندارد.');
        }
        // پاسخ ثبت‌نام برای شماره/ایمیل تکراری و جدید یکسان است تا نشود وجود حساب‌ها را با فرم ثبت‌نام شمارش کرد
        if (UserModel::mobileExists($mobile) || ($email !== '' && UserModel::emailExists($email))) {
            Auth::hash($pass);                                 // هش ساختگی: زمان پاسخ هم مثل ثبت‌نام واقعی باشد
            Audit::log(null, 'register_duplicate', ['mobile' => $mobile]);
        } else {
            $id = Auth::register(['mobile' => $mobile, 'first_name' => $first, 'last_name' => $last, 'email' => $email, 'password' => $pass]);
            Audit::log($id, 'register');
            ProjectService::createSample($id);
        }
        Session::forget('_old');
        Session::flash('درخواست ثبت‌نام ثبت شد. با شماره موبایل و رمز خود وارد شوید؛ حساب پس از تأیید مدیر فعال می‌شود.', 'success');
        $this->redirect('/login');
    }

    public function logout()
    {
        Auth::logout();
        Session::start();
        Session::flash('با موفقیت از سامانه خارج شدید.', 'success');
        $this->redirect('/login');
    }

    public function pending()
    {
        $u = $this->user();
        if ($u['status'] === 'active') {
            $this->redirect('/');
        }
        $this->view('auth/pending', ['status' => $u['status']], ['title' => 'وضعیت حساب'], 'auth');
    }

    public function pendingStatus()
    {
        $u = $this->user();
        $this->json(['status' => $u['status'], 'active' => $u['status'] === 'active']);
    }

    public function home()
    {
        $u = Auth::user();
        if ($u === null) {
            $this->redirect('/login');
        }
        if ($u['role'] === 'admin' && $u['status'] === 'active' && !Auth::isImpersonating()) {
            $this->redirect('/admin');
        }
        $this->redirect($u['status'] === 'active' ? '/dashboard' : '/pending');
    }
}
