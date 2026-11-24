<?php
declare(strict_types=1);

/** بررسی وابستگی‌های همراه نسخه، بدون نیاز به نصب Composer یا اتصال به اینترنت. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

$vendor = BASE_PATH . '/laravel/vendor';
try {
    $lock = json_decode((string) file_get_contents(BASE_PATH . '/laravel/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
    $installed = json_decode((string) file_get_contents($vendor . '/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
    $versions = static function (array $packages): array {
        $result = [];
        foreach ($packages as $package) { $result[$package['name']] = $package['version']; }
        ksort($result);
        return $result;
    };
    if (($installed['dev'] ?? true) || $versions($lock['packages']) !== $versions($installed['packages'])) {
        throw new RuntimeException('وابستگی‌های همراه نسخه با composer.lock تولید مطابقت ندارند.');
    }
    require $vendor . '/autoload.php';
    // علاوه بر فایل ورودی، تمام فایل‌های classmap و helperهای اجباری نیز باید همراه نسخه باشند.
    $classmap = require $vendor . '/composer/autoload_classmap.php';
    $files = require $vendor . '/composer/autoload_files.php';
    foreach (array_merge(array_values($classmap), array_values($files)) as $path) {
        if (!is_file($path) || !is_readable($path)) { throw new RuntimeException('فایل وابستگی ناقص است: ' . $path); }
    }
    foreach ([Illuminate\Foundation\Application::class, Illuminate\Database\Eloquent\Model::class,
        Symfony\Component\HttpFoundation\Response::class, Carbon\Carbon::class,
        Ramsey\Uuid\Uuid::class, KarlixLaravel\Http\Controllers\TaskController::class] as $class) {
        if (!class_exists($class)) { throw new RuntimeException('کلاس وابستگی بارگذاری نشد: ' . $class); }
        $path = (new ReflectionClass($class))->getFileName();
        if (!str_starts_with((string) realpath($path), BASE_PATH . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('وابستگی از خارج این نسخه بارگذاری شده است: ' . $class);
        }
    }
    echo 'PASS ' . count($installed['packages']) . ' production packages match composer.lock; ' . count($classmap) . " mapped classes and required helpers exist\n";
    echo "PASS standalone Laravel, Eloquent, Symfony, Carbon, UUID and native controllers\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n");
    exit(1);
}
