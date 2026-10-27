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
