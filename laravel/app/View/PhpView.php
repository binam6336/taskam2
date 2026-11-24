<?php
declare(strict_types=1);

namespace KarlixLaravel\View;

/** رندر قالب‌های PHP با layout. در قالب‌ها، $this همین شیء است. */
final class PhpView
{
    public string $title = '';
    public string $page = '';
    public string $bodyClass = '';
    public array $scripts = [];
    public array $styles = [];
    public array $boot = [];
    public array $crumbs = [];   // v7.16: مسیر بالای عنوان در نوار بالا [[نام، مسیر یا null]، …]

    public static function render(string $view, array $data = [], ?string $layout = 'app', array $meta = []): string
    {
        $v = new self();
        foreach ($meta as $k => $val) {
            if (property_exists($v, $k)) {
                $v->$k = $val;
            }
        }
        $content = $v->capture(BASE_PATH . '/laravel/resources/views/' . $view . '.php', $data);
        if ($layout === null) {
            return $content;
        }
        return $v->capture(BASE_PATH . '/laravel/resources/views/layouts/' . $layout . '.php', array_merge($data, ['content' => $content]));
    }

    public function partial(string $name, array $data = []): void
    {
        echo $this->capture(BASE_PATH . '/laravel/resources/views/partials/' . $name . '.php', $data);
    }

    private function capture(string $file, array $data): string
    {
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . basename($file));
        }
        $fn = function () use ($file, $data): string {
            extract($data, EXTR_SKIP);
            ob_start();
            try {
                require $file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
            return (string) ob_get_clean();
        };
        return $fn->call($this);
    }
}
