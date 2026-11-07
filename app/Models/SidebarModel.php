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
    private const OPTS_KEY = 'sidebar.user.opts';      // تنظیمات نمایش (v5.9): {"show_desc": bool}
    public const MAX_LABEL = 40;
    public const MAX_DESC = 80;
    public const MAX_URL = 300;
    public const MAX_ITEMS = 60;
    public const LINK_ICON = 'fa-arrow-up-right-from-square';

    private static ?array $cfg = null;

    private static function cfg(): array
    {
        return self::$cfg ??= require BASE_PATH . '/config/sidebar.php';
    }

    /** توضیح پیش‌فرض صفحه‌ها و گروه‌ها (group:<id>) از config؛ توضیح ذخیره‌شده‌ی ادمین (کلید desc هر گزینه) بر این مقدم است */
    public static function descriptions(): array
    {
        return self::cfg()['desc'] ?? [];
    }

    /** توضیح مؤثر یک گزینه: ذخیره‌شده (حتی خالی = بدون توضیح) یا پیش‌فرض config */
    private static function descOf(array $i): string
    {
        if (array_key_exists('desc', $i)) {
            return (string) $i['desc'];
        }
        $d = self::descriptions();
        $t = $i['t'] ?? '';
        if ($t === 'page') {
            return (string) ($d[$i['page'] ?? ''] ?? '');
        }
        return $t === 'group' ? (string) ($d['group:' . ($i['id'] ?? '')] ?? '') : '';
    }

    /** چیدمان فعلی با توضیح مؤثر هر گزینه (برای ویرایشگر ادمین) */
    public static function currentWithDesc(): array
    {
        $fill = static function (array $i): array { $i['desc'] = self::descOf($i); return $i; };
        return array_map(static function (array $i) use ($fill): array {
            $i = $fill($i);
            if ($i['t'] === 'group') {
                $i['items'] = array_map($fill, $i['items']);
            }
            return $i;
        }, self::current());
    }

    /** تنظیمات نمایش سایدبار: show_desc = نمایش توضیح زیر گزینه‌ها و گروه‌ها در پاپ‌آپ (پیش‌فرض روشن) */
    public static function options(): array
    {
        static $opts = null;
        if ($opts !== null) {
            return $opts;
        }
        try {
            $raw = DB::value('SELECT body FROM content_texts WHERE `key` = ?', [self::OPTS_KEY]);
        } catch (\Throwable $e) {
            $raw = null;
        }
        $o = is_string($raw) ? json_decode($raw, true) : null;
        return $opts = ['show_desc' => !is_array($o) || !array_key_exists('show_desc', $o) || (bool) $o['show_desc']];
    }

    public static function saveOptions(array $o, int $uid): void
    {
        DB::run('INSERT INTO content_texts (`key`, body, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body), updated_by = VALUES(updated_by)',
            [self::OPTS_KEY, json_encode(['show_desc' => !empty($o['show_desc'])]), $uid]);
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
            return (int) DB::value('SELECT COUNT(*) FROM content_texts WHERE `key` IN (?, ?)', [self::KEY, self::OPTS_KEY]) > 0;
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
        // توضیح زیر نام (v5.9): فقط اگر فرستاده شده باشد ذخیره می‌شود؛ نبودنش = توضیح پیش‌فرض config
        $desc = static function (array $i, array $out): array {
            if (array_key_exists('desc', $i)) {
                $out['desc'] = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $i['desc'])), 0, self::MAX_DESC);
            }
            return $out;
        };
        $leaf = static function ($i) use ($pages, $label, $desc, &$err): ?array {
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
                return $desc($i, $out);
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
                return $desc($i, ['t' => 'link', 'url' => $url, 'label' => $name]);
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
                $menu[] = $desc($i, ['t' => 'group', 'id' => $id, 'icon' => $groupIcons[$id] ?? 'fa-folder', 'label' => $label($i['label'] ?? '') ?: 'گروه', 'items' => $items]);
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
        DB::run('DELETE FROM content_texts WHERE `key` IN (?, ?)', [self::KEY, self::OPTS_KEY]);   // چیدمان + تنظیمات نمایش
    }

    /** قالب partials/sidebar.php (گروه‌های خالی نمایش داده نمی‌شوند)؛ desc = توضیح زیر نام (خالی اگر ادمین نمایش توضیح را خاموش کرده) */
    public static function nav(): array
    {
        $pages = self::pages();
        $show = self::options()['show_desc'];
        $d = static fn(array $i): string => $show ? self::descOf($i) : '';
        $leaf = static function (array $i) use ($pages, $d): array {
            if ($i['t'] === 'link') {
                return ['link' => $i['url'], 'icon' => self::LINK_ICON, 'label' => $i['label'], 'desc' => $d($i)];
            }
            [$path, $icon] = $pages[$i['page']];
            return !empty($i['soon']) ? ['soon' => true, 'icon' => $icon, 'label' => $i['label'], 'desc' => $d($i)] : [$i['page'], $path, $icon, $i['label'], 'desc' => $d($i)];
        };
        $out = [];
        foreach (self::current() as $i) {
            if ($i['t'] !== 'group') {
                $out[] = $leaf($i);
            } elseif ($i['items']) {
                $out[] = ['group' => $i['id'], 'icon' => $i['icon'], 'label' => $i['label'], 'desc' => $d($i), 'items' => array_map($leaf, $i['items'])];
            }
        }
        return $out;
    }
}
