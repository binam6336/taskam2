<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\DocsHelper;

/** مستندات API (عمومی): خانه، ارسال توکن، خطاها، صفحه‌ی هر گروه و هر endpoint. */
final class DocsController extends Controller
{
    private function spec(): array
    {
        return require BASE_PATH . '/config/api_spec.php';
    }

    private function render(string $view, array $data, string $title, string $current): void
    {
        $spec = $this->spec();
        $index = [];
        foreach ($spec['groups'] as $g) {
            foreach ($g['endpoints'] as $ep) {
                $index[] = ['t' => $ep['summary'], 'p' => $ep['path'], 'm' => $ep['method'], 'g' => $g['title'], 'u' => DocsHelper::url($g['id'], $ep)];
            }
        }
        $this->view('docs/' . $view, $data + [
            'spec' => $spec, 'baseUrl' => rtrim(full_url('api/v1'), '/'), 'loggedIn' => Auth::check(), 'current' => $current,
            'fields' => require BASE_PATH . '/config/api_fields.php',
        ], ['title' => $title . ' • مستندات API', 'bodyClass' => 'docs', 'boot' => ['docs' => ['index' => $index, 'base' => rtrim(full_url('api/v1'), '/'), 'loggedIn' => Auth::check()]]], 'docs');
    }

    public function index(): void
    {
        $this->render('home', [], 'خانه', 'home');
    }

    public function auth(): void
    {
        $this->render('auth', [], 'نحوه دریافت و ارسال توکن', 'auth');
    }

    public function errors(): void
    {
        $this->render('errors', [], 'کدهای خطا', 'errors');
    }

    /** سند OpenAPI برای Swagger (v7.8) — همان منبع صفحه‌های مستندات */
    public function openapi(): void
    {
        $doc = DocsHelper::openapi($this->spec(), require BASE_PATH . '/config/api_fields.php', rtrim(full_url('api/v1'), '/'));
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache');
        echo json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** «تست API» (Swagger UI، v7.8): صفحه‌ی مستقل (بدون app.css تا با استایل Swagger تداخل نداشته باشد) */
    public function swagger(): void
    {
        $u = Auth::user();
        $this->view('docs/swagger', [
            'me' => $u !== null && !Auth::isImpersonating() ? $u : null,
            'captcha' => $u === null && \App\Core\Captcha::enabled(),
            'csrf' => csrf_token(),
        ], ['title' => 'تست API'], null);
    }

    /**
     * ساخت توکن موقت برای «تست API» (v7.8): با نشست پنل (کاربر واردشده) یا موبایل + رمز (+ کپچا)، با همان سقف تلاش و قفل حساب صفحه‌ی ورود.
     * توکن «Swagger (تست API)» یک روزه است؛ توکن قبلی همین نام حذف می‌شود. حساب غیرفعال فقط توکن خواندنی می‌گیرد.
     */
    public function swaggerToken(): void
    {
        $u = Auth::user();
        if ($u !== null && Auth::isImpersonating()) {
            $this->fail('در پنجره‌ی ورود مدیر به پنل کاربر نمی‌توان توکن ساخت.', 403);
        }
        if ($u === null) {
            if (\App\Core\Captcha::enabled() && !\App\Core\Captcha::verify($this->request->str('captcha', 20))) {
                $this->fail('کد امنیتی اشتباه است؛ کد جدید را وارد کنید.');
            }
            $mobile = \App\Core\Validate::mobile($this->request->str('mobile', 30));
            $pass = (string) $this->request->input('password', '');
            if ($mobile === null || $pass === '' || strlen($pass) > 200) {
                $this->fail('شماره موبایل یا رمز عبور اشتباه است.');
            }
            $r = Auth::attempt($mobile, $pass, $this->request, false, false);
            if (!$r['ok']) {
                $this->fail($r['message']);
            }
            $u = $r['user'];
        }
        if ((int) ($u['is_bot'] ?? 0) === 1 || !in_array($u['status'], ['active', 'deactivated'], true)) {
            $this->fail('حساب کاربری شما فعال نیست.', 403);
        }
        $uid = (int) $u['id'];
        if (!\App\Core\Throttle::attempt('swagger_token', (string) $uid, 10, 3600)) {
            $this->fail('تعداد ساخت توکن تست زیاد است؛ کمی بعد دوباره تلاش کنید.', 429);
        }
        $name = 'Swagger (تست API)';
        \App\Core\DB::run('DELETE FROM api_tokens WHERE user_id = ? AND name = ?', [$uid, $name]);
        if ((int) \App\Core\DB::value('SELECT COUNT(*) FROM api_tokens WHERE user_id = ?', [$uid]) >= \App\Models\TokenModel::MAX_PER_USER) {
            $this->fail('حداکثر ' . fa_num(\App\Models\TokenModel::MAX_PER_USER) . ' توکن مجاز است؛ از پنل ← توکن‌های API یکی را حذف کنید.');
        }
        $t = \App\Models\TokenModel::create($uid, $name, date('Y-m-d H:i:s', time() + 86400), $u['status'] === 'active' ? 'write' : 'read');
        \App\Core\Audit::log($uid, 'token_created', ['id' => $t['id'], 'via' => 'swagger']);
        $this->ok('توکن تست یک‌روزه ساخته شد و در Swagger اعمال شد.', ['token' => $t['token'], 'name' => full_name($u), 'expires_in_hours' => 24]);
    }

    public function group(string $group): void
    {
        foreach ($this->spec()['groups'] as $g) {
            if ($g['id'] === $group) {
                $this->render('group', ['group' => $g], $g['title'], 'g:' . $group);
                return;
            }
        }
        $this->abort(404, 'بخش مورد نظر در مستندات پیدا نشد.', 'NOT_FOUND');
    }

    public function endpoint(string $group, string $slug): void
    {
        foreach ($this->spec()['groups'] as $g) {
            if ($g['id'] !== $group) {
                continue;
            }
            foreach ($g['endpoints'] as $ep) {
                if (DocsHelper::slug($ep) === $slug) {
                    $this->render('endpoint', ['group' => $g, 'ep' => $ep], $ep['summary'], 'e:' . $group . '/' . $slug);
                    return;
                }
            }
        }
        $this->abort(404, 'این endpoint در مستندات پیدا نشد.', 'NOT_FOUND');
    }
}
