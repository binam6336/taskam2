<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\DB;

/**
 * چیدمان سایدبار پنل کاربر که ادمین در «محتوا ← سایدبار» تنظیم می‌کند.
 * به‌صورت JSON در content_texts (کلید sidebar.user) ذخیره می‌شود؛ تا ذخیره نشده، پیش‌فرض config/sidebar.php نمایش داده می‌شود.
 * هر بار خواندن هم از normalize رد می‌شود تا صفحه‌ی حذف‌شده یا داده‌ی خراب سایدبار را نشکند.
 */
final class SidebarModel
{
    private const KEY = 'sidebar.user';
    public const MAX_LABEL = 40;
    public const MAX_URL = 300;
    public const MAX_ITEMS = 60;
    public const LINK_ICON = 'fa-arrow-up-right-from-square';

    private static ?array $cfg = null;

    private static function cfg(): array
    {
        return self::$cfg ??= require BASE_PATH . '/config/sidebar.php';
    }

    /** صفحه‌های قابل انتخاب: کلید => [مسیر، آیکون، نام پیش‌فرض] */
    public static function pages(): array
    {
        return self::cfg()['pages'];
    }

    public static function defaults(): array
    {
        return self::cfg()['default'];
    }

    /** چیدمان فعلی (ذخیره‌شده یا پیش‌فرض) در قالب config */
    public static function current(): array
    {
        try {
            $raw = DB::value('SELECT body FROM content_texts WHERE `key` = ?', [self::KEY]);
        } catch (\Throwable $e) {
            $raw = null;
        }
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($data)) {
            [$menu] = self::normalize($data);
            if ($menu) {
                return $menu;
            }
        }
        return self::defaults();
    }

    public static function isCustom(): bool
    {
        try {
            return (int) DB::value('SELECT COUNT(*) FROM content_texts WHERE `key` = ?', [self::KEY]) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * پاک‌سازی و اعتبارسنجی چیدمان. خروجی: [چیدمان، خطا یا null]
     * گروه فقط در سطح اول؛ داخل گروه فقط صفحه و لینک.
     */
    public static function normalize(array $data): array
    {
        $pages = self::pages();
        $groupIcons = array_column(array_filter(self::defaults(), static fn($i) => ($i['t'] ?? '') === 'group'), 'icon', 'id');
        $count = 0;
        $err = null;
        $label = static function ($v): string {
            $v = trim(preg_replace('/\s+/u', ' ', (string) $v));
            return mb_substr($v, 0, self::MAX_LABEL);
        };
        $leaf = static function ($i) use ($pages, $label, &$err): ?array {
            if (!is_array($i)) {
                return null;
            }
            $t = $i['t'] ?? '';
            if ($t === 'page') {
                $p = (string) ($i['page'] ?? '');
                if (!isset($pages[$p])) {
                    return null;
                }
                $out = ['t' => 'page', 'page' => $p, 'label' => $label($i['label'] ?? '') ?: $pages[$p][2]];
                if (!empty($i['soon'])) {
                    $out['soon'] = true;
                }
                return $out;
            }
            if ($t === 'link') {
                $url = trim((string) ($i['url'] ?? ''));
                $name = $label($i['label'] ?? '');
                if ($name === '') {
                    $err ??= 'نام همه‌ی لینک‌ها را وارد کنید.';
                    return null;
                }
                if (mb_strlen($url) > self::MAX_URL || !preg_match('~^https?://~i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                    $err ??= 'لینک «' . $name . '» معتبر نیست (باید با http:// یا https:// شروع شود).';
                    return null;
                }
                return ['t' => 'link', 'url' => $url, 'label' => $name];
            }
            return null;
        };
        $menu = [];
        foreach ($data as $i) {
            if (++$count > self::MAX_ITEMS) {
                break;
            }
            if (is_array($i) && ($i['t'] ?? '') === 'group') {
                $id = (string) ($i['id'] ?? '');
                if (!preg_match('/^[a-z0-9-]{1,30}$/', $id)) {
                    continue;
                }
                $items = [];
                foreach ((array) ($i['items'] ?? []) as $c) {
                    if (++$count > self::MAX_ITEMS) {
                        break;
                    }
                    if ($x = $leaf($c)) {
                        $items[] = $x;
                    }
                }
                $menu[] = ['t' => 'group', 'id' => $id, 'icon' => $groupIcons[$id] ?? 'fa-folder', 'label' => $label($i['label'] ?? '') ?: 'گروه', 'items' => $items];
            } elseif ($x = $leaf($i)) {
                $menu[] = $x;
            }
        }
        return [$menu, $err];
    }

    public static function save(array $menu, int $uid): void
    {
        DB::run('INSERT INTO content_texts (`key`, body, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body), updated_by = VALUES(updated_by)',
            [self::KEY, json_encode($menu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $uid]);
    }

    public static function reset(): void
    {
        DB::run('DELETE FROM content_texts WHERE `key` = ?', [self::KEY]);
    }

    /** قالب partials/sidebar_nav.php (گروه‌های خالی نمایش داده نمی‌شوند) */
    public static function nav(): array
    {
        $pages = self::pages();
        $leaf = static function (array $i) use ($pages): array {
            if ($i['t'] === 'link') {
                return ['link' => $i['url'], 'icon' => self::LINK_ICON, 'label' => $i['label']];
            }
            [$path, $icon] = $pages[$i['page']];
            return !empty($i['soon']) ? ['soon' => true, 'icon' => $icon, 'label' => $i['label']] : [$i['page'], $path, $icon, $i['label']];
        };
        $out = [];
        foreach (self::current() as $i) {
            if ($i['t'] !== 'group') {
                $out[] = $leaf($i);
            } elseif ($i['items']) {
                $out[] = ['group' => $i['id'], 'icon' => $i['icon'], 'label' => $i['label'], 'items' => array_map($leaf, $i['items'])];
            }
        }
        return $out;
    }
}
