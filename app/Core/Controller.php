<?php
declare(strict_types=1);

namespace App\Core;

/** پایه‌ی کنترلرهای وب. */
abstract class Controller
{
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    protected function user(): array
    {
        $u = Auth::user();
        if ($u === null) {
            throw new HttpException(401, 'ابتدا وارد شوید.', 'UNAUTHORIZED');
        }
        return $u;
    }

    protected function uid(): int
    {
        return (int) $this->user()['id'];
    }

    protected function view(string $view, array $data = [], array $meta = [], ?string $layout = 'app')
    {
        $data['currentUser'] = Auth::user();
        if ($this->request->header('X-Live') === '1') {
            $layout = null;   // تازه‌سازی زنده: فقط محتوای صفحه، بدون layout
        }
        if ($data['currentUser'] !== null) {
            // صفحه‌های حاوی اطلاعات کاربر در کش مرورگر/پراکسی نمی‌مانند (مثلاً دکمه‌ی Back بعد از خروج)
            header('Cache-Control: no-store, private');
        }
        Response::html(View::render($view, $data, $layout, $meta));
    }

    protected function redirect(string $to)
    {
        Response::redirect($to);
    }

    /** بازگشت به صفحه‌ی قبلی (فقط داخل همین سایت) یا مسیر پیش‌فرض. */
    protected function back(string $fallback = '/')
    {
        $ref = $this->request->header('Referer');
        if ($ref) {
            $p = parse_url($ref);
            $host = $_SERVER['HTTP_HOST'] ?? '';
            if (($p['host'] ?? '') !== '' && (($p['host'] . (isset($p['port']) ? ':' . $p['port'] : '')) === $host || $p['host'] === explode(':', $host)[0])) {
                Response::redirect(($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : ''));
            }
        }
        Response::redirect($fallback);
    }

    protected function flash(string $msg, string $type = 'success'): void
    {
        Session::flash($msg, $type);
    }

    protected function json(array $data, int $status = 200)
    {
        Response::json($data, $status);
    }

    /** پاسخ استاندارد AJAX داخلی پنل: {status, message, ...extra} */
    protected function ok(string $message = '', array $extra = [])
    {
        Response::json(['status' => 'success', 'message' => $message] + $extra);
    }

    protected function fail(string $message, int $http = 422, array $extra = [])
    {
        Response::json(['status' => 'error', 'message' => $message] + $extra, $http);
    }

    /** پاسخ برای فرم‌ها: AJAX → JSON، در غیر این صورت flash + redirect. */
    protected function respond(bool $success, string $message, string $redirectTo, array $extra = [])
    {
        if ($this->request->wantsJson()) {
            Response::json(['status' => $success ? 'success' : 'error', 'message' => $message] + $extra, $success ? 200 : 422);
        }
        Session::flash($message, $success ? 'success' : 'error');
        Response::redirect($redirectTo);
    }

    protected function abort(int $status, string $message = '', string $code = 'ERROR')
    {
        throw new HttpException($status, $message !== '' ? $message : 'درخواست نامعتبر است.', $code);
    }
}
