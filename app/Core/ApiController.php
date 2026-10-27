<?php
declare(strict_types=1);

namespace App\Core;

/** پایه‌ی کنترلرهای API: قالب یکسان پاسخ { success, status, message?, data? , error_code? } */
abstract class ApiController
{
    protected int $uid;

    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->uid = (int) ($request->params['_api_user'] ?? 0);
    }

    protected function ok($data = null, string $message = '', int $http = 200)
    {
        $out = ['success' => true, 'status' => 'success'];
        if ($message !== '') {
            $out['message'] = $message;
        }
        if ($data !== null) {
            $out['data'] = $data;
        }
        Response::json($out, $http);
    }

    protected function err(string $message, int $http = 400, string $code = '')
    {
        $out = ['success' => false, 'status' => 'error', 'message' => $message];
        if ($code !== '') {
            $out['error_code'] = $code;
        }
        Response::json($out, $http);
    }

    /** تبدیل نتیجه‌ی Service به پاسخ API (اعتبارسنجی = 400 مطابق قرارداد نسخه‌ی ۱). */
    protected function fromService(array $res, ?callable $map = null)
    {
        if (!$res['ok']) {
            $http = $res['http'] === 422 ? 400 : $res['http'];
            $this->err($res['message'], $http, $res['code']);
        }
        $data = $res['data'];
        if ($map !== null && $data !== null) {
            $data = $map($data);
        }
        $this->ok($data, $res['message'], $res['http'] === 201 ? 200 : $res['http']);
    }

    protected function id(): int
    {
        $id = (int) ($this->request->query['id'] ?? $this->request->input('id', 0));
        if ($id <= 0) {
            $this->err('پارامتر id الزامی است.', 400, 'INVALID_ID');
        }
        return $id;
    }

    protected function paging(int $defaultLimit = 100): array
    {
        $q = $this->request->query;
        return [min(200, max(1, (int) ($q['limit'] ?? $defaultLimit))), max(0, (int) ($q['offset'] ?? 0))];
    }

    protected function body(): array
    {
        return $this->request->all();
    }
}
