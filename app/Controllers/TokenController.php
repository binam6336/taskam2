<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Session;
use App\Models\TokenModel;

final class TokenController extends Controller
{
    public function index()
    {
        $uid = $this->uid();
        $this->view('tokens/index', [
            'tokens' => TokenModel::forUser($uid),
            'newToken' => Session::pull('new_token'),
            'max' => TokenModel::MAX_PER_USER,
        ], ['title' => 'توکن‌های API', 'page' => 'tokens']);
    }

    public function store()
    {
        $this->denyImpersonation('/tokens');
        $uid = $this->uid();
        $name = $this->request->str('name', 100) ?: 'بدون نام';
        if ((int) DB::value('SELECT COUNT(*) FROM api_tokens WHERE user_id = ?', [$uid]) >= TokenModel::MAX_PER_USER) {
            $this->respond(false, 'حداکثر ' . fa_num(TokenModel::MAX_PER_USER) . ' توکن مجاز است؛ توکن‌های بلااستفاده را حذف کنید.', '/tokens');
        }
        $days = $this->request->int('expires_days', 0);
        $exp = $days > 0 && $days <= 3650 ? date('Y-m-d H:i:s', time() + $days * 86400) : null;
        $t = TokenModel::create($uid, $name, $exp, $this->request->str('scope') === 'read' ? 'read' : 'write');
        Audit::log($uid, 'token_created', ['id' => $t['id']]);
        Session::put('new_token', $t['token']);
        $this->redirect('/tokens');
    }

    public function toggle(string $id)
    {
        $this->denyImpersonation('/tokens');
        $uid = $this->uid();
        $n = DB::run('UPDATE api_tokens SET is_active = 1 - is_active WHERE id = ? AND user_id = ?', [(int) $id, $uid]);
        $this->respond($n > 0, $n > 0 ? 'وضعیت توکن تغییر کرد.' : 'توکن یافت نشد.', '/tokens');
    }

    public function destroy(string $id)
    {
        $this->denyImpersonation('/tokens');
        $uid = $this->uid();
        $n = DB::run('DELETE FROM api_tokens WHERE id = ? AND user_id = ?', [(int) $id, $uid]);
        if ($n > 0) {
            Audit::log($uid, 'token_deleted', ['id' => (int) $id]);
        }
        $this->respond($n > 0, $n > 0 ? 'توکن حذف شد.' : 'توکن یافت نشد.', '/tokens');
    }
}
