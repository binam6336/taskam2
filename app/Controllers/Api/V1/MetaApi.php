<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\Jalali;
use App\Models\UserModel;

final class MetaApi extends ApiController
{
    /** GET /api/v1/ — معرفی API و فهرست endpointها (از روی همان spec که مستندات را می‌سازد). */
    public function index()
    {
        $spec = require BASE_PATH . '/config/api_spec.php';
        $endpoints = [];
        foreach ($spec['groups'] as $g) {
            foreach ($g['endpoints'] as $e) {
                $endpoints[$g['id']][] = ['method' => $e['method'], 'url' => '/api/v1' . $e['path'], 'desc' => $e['summary']];
            }
        }
        $this->ok(['name' => 'Taskam API', 'version' => 'v1', 'base_url' => rtrim(full_url('api/v1'), '/'), 'auth' => ['type' => 'Bearer Token', 'header' => 'Authorization: Bearer <token>'], 'docs' => full_url('docs'), 'endpoints' => $endpoints]);
    }

    public function me()
    {
        $u = UserModel::find($this->uid);
        $this->ok(['id' => (int) $u['id'], 'first_name' => $u['first_name'], 'last_name' => $u['last_name'], 'full_name' => full_name($u), 'mobile' => $u['mobile'], 'email' => $u['email'], 'avatar_url' => avatar_url($u['avatar']), 'auth_method' => $this->request->params['_api_method'] ?? null]);
    }

    public function time()
    {
        $this->ok(Jalali::nowInfo());
    }
}
