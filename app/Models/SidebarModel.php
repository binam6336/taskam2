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
        return $opts = ['show_desc' => !is_array($o) || !array_key_exists('show_desc', $o) || (bool) $o['show_desc'],
            'known_pages' => is_array($o) && isset($o['known_pages']) && is_array($o['known_pages']) ? array_values(array_map('strval', $o['known_pages'])) : null];
    }

    public static function saveOptions(array $o, int $uid): void
    {
        DB::run('INSERT INTO content_texts (`key`, body, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body), updated_by = VALUES(updated_by)',
            [self::OPTS_KEY, json_encode(['show_desc' => !empty($o['show_desc']), 'known_pages' => array_merge(array_keys(self::pages()), array_map(static fn($p) => 'moved:' . $p, array_keys(self::MOVED_PAGES)))]), $uid]);
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
                return self::withNewPages($menu);
            }
        }
        return self::defaults();
    }

    /**
     * صفحه‌های تازه‌ی پنل که در چیدمانِ ذخیره‌شده‌ی ادمین (قبل از اضافه شدنشان) نیستند، کنار صفحه‌ی مشخص‌شده اضافه می‌شوند —
     * تا وقتی ادمین چیدمان را دوباره ذخیره کند (known_pages در تنظیمات = صفحه‌هایی که ادمین موقع ذخیره دیده؛ حذف عمدی‌شان محترم است).
     */
    private const NEW_PAGES = ['groups' => 'projects'];

    /**
     * صفحه‌هایی که در نسخه‌ی جدید جایشان عوض شده (v6.0: «متن‌های آماده» ← «یادداشت‌ها» زیر «بورد» در گروه وظایف):
     * در چیدمان ذخیره‌شده‌ی قدیمی جابه‌جا می‌شوند تا ادمین دوباره ذخیره کند (نشانه‌ی moved:<صفحه> در known_pages).
     * [گروه مقصد، بعد از صفحه، نام‌های قدیمی که با نام جدید عوض می‌شوند]
     */
    private const MOVED_PAGES = ['texts' => ['tasks', 'board', ['متن‌های آماده', 'متن آماده']]];

    private static function withMoves(array $menu, ?array $known): array
    {
        foreach (self::MOVED_PAGES as $page => [$gid, $after, $oldLabels]) {
            if ((is_array($known) && in_array('moved:' . $page, $known, true)) || !isset(self::pages()[$page])) {
                continue;
            }
            $item = null;
            foreach ($menu as $gi => $g) {
                if (($g['t'] ?? '') === 'page' && $g['page'] === $page) {
                    $item = $g;
                    unset($menu[$gi]);
                } elseif (($g['t'] ?? '') === 'group') {
                    foreach ($g['items'] as $ii => $c) {
                        if (($c['t'] ?? '') === 'page' && $c['page'] === $page) {
                            $item = $c;
                            unset($menu[$gi]['items'][$ii]);
                        }
                    }
                    $menu[$gi]['items'] = array_values($menu[$gi]['items']);
                }
            }
            $item = $item ?? ['t' => 'page', 'page' => $page, 'label' => self::pages()[$page][2]];
            if (in_array($item['label'] ?? '', $oldLabels, true)) {
                $item['label'] = self::pages()[$page][2];
                unset($item['desc']);
            }
            $put = false;
            foreach ($menu as $gi => $g) {
                if (($g['t'] ?? '') !== 'group' || ($g['id'] ?? '') !== $gid) {
                    continue;
                }
                $at = count($g['items']);
                foreach ($g['items'] as $ii => $c) {
                    if (($c['t'] ?? '') === 'page' && $c['page'] === $after) {
                        $at = $ii + 1;
                    }
                }
                array_splice($menu[$gi]['items'], $at, 0, [$item]);
                $put = true;
                break;
            }
            if (!$put) {
                $menu[] = $item;
            }
        }
        // گروهی که بعد از حذف صفحه‌ها (مثل «درخواست تماس» در v6.0) خالی مانده نمایش داده نمی‌شود
        return array_values(array_filter($menu, static fn($g) => ($g['t'] ?? '') !== 'group' || !empty($g['items'])));
    }

    private static function withNewPages(array $menu): array
    {
        $known = self::options()['known_pages'] ?? null;
        $menu = self::withMoves($menu, $known);
        $has = static function (array $items, string $page) use (&$has): bool {
            foreach ($items as $i) {
                if (($i['t'] ?? '') === 'page' && $i['page'] === $page) {
                    return true;
                }
                if (($i['t'] ?? '') === 'group' && $has($i['items'], $page)) {
                    return true;
                }
            }
            return false;
        };
        foreach (self::NEW_PAGES as $page => $after) {
            if ((is_array($known) && in_array($page, $known, true)) || $has($menu, $page) || !isset(self::pages()[$page])) {
                continue;
            }
            $item = ['t' => 'page', 'page' => $page, 'label' => self::pages()[$page][2]];
            $put = false;
            foreach ($menu as $gi => $g) {
                if (($g['t'] ?? '') !== 'group') {
                    continue;
                }
                foreach ($g['items'] as $ii => $c) {
                    if (($c['t'] ?? '') === 'page' && $c['page'] === $after) {
                        array_splice($menu[$gi]['items'], $ii + 1, 0, [$item]);
                        $put = true;
                        break 2;
                    }
                }
            }
            if (!$put) {
                $menu[] = $item;
            }
        }
        return $menu;
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
            // بورد از v6.1 آماده است: «به‌زودی» چیدمان‌های ذخیره‌شده‌ی قدیمی برایش نادیده گرفته می‌شود
            return !empty($i['soon']) && $i['page'] !== 'board' ? ['soon' => true, 'icon' => $icon, 'label' => $i['label'], 'desc' => $d($i)] : [$i['page'], $path, $icon, $i['label'], 'desc' => $d($i)];
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
