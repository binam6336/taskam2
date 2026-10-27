<?php
declare(strict_types=1);

namespace App\Services;

/** کمک‌کننده‌ی صفحات مستندات: هایلایت JSON، استخراج جدول فیلدها، ساخت نمونه‌ی درخواست/خطا. */
final class DocsHelper
{
    private const NOT_FOUND = [
        'tasks'      => ['TASK_NOT_FOUND', 'تسک یافت نشد.'],
        'calls'      => ['CALL_NOT_FOUND', 'درخواست یافت نشد.'],
        'texts'      => ['TEXT_NOT_FOUND', 'متن یافت نشد.'],
        'colleagues' => ['COLLEAGUE_NOT_FOUND', 'همکار یافت نشد.'],
        'projects'   => ['PROJECT_NOT_FOUND', 'پروژه یافت نشد یا دسترسی ندارید.'],
    ];

    public static function pretty($data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function slug(array $ep): string
    {
        $parts = array_values(array_filter(explode('/', (string) $ep['path']), static fn($p) => $p !== ''));
        return $parts ? (string) end($parts) : 'index';
    }

    public static function url(string $group, array $ep): string
    {
        return url('/docs/' . $group . '/' . self::slug($ep));
    }

    /** JSON → HTML هایلایت‌شده؛ هر خط یک <span class="l"> (شماره‌ی خط با CSS counter). */
    public static function highlight(string $json): string
    {
        $out = '';
        foreach (explode("\n", $json) as $line) {
            $h = '';
            $pos = 0;
            if (preg_match_all('/("(?:\\\\.|[^"\\\\])*")(\s*:)?|\b(true|false|null)\b|(-?\b\d+(?:\.\d+)?\b)/u', $line, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($m as $t) {
                    $full = $t[0][0];
                    $off = $t[0][1];
                    $h .= e(substr($line, $pos, $off - $pos));
                    if (isset($t[1]) && $t[1][1] !== -1 && $t[1][0] !== '') {
                        $isKey = isset($t[2]) && $t[2][1] !== -1 && $t[2][0] !== '';
                        $h .= '<span class="' . ($isKey ? 'tok-k' : 'tok-s') . '">' . e($t[1][0]) . '</span>' . ($isKey ? e($t[2][0]) : '');
                    } elseif (isset($t[3]) && $t[3][1] !== -1 && $t[3][0] !== '') {
                        $h .= '<span class="tok-b">' . e($t[3][0]) . '</span>';
                    } else {
                        $h .= '<span class="tok-n">' . e($t[4][0]) . '</span>';
                    }
                    $pos = $off + strlen($full);
                }
            }
            $h .= e(substr($line, $pos));
            $out .= '<span class="l">' . ($h === '' ? ' ' : $h) . '</span>';
        }
        return $out;
    }

    /** بلوک کد با شماره‌ی خط، برچسب زبان و دکمه‌ی کپی. */
    public static function codeBox(string $json, string $lang = 'json'): string
    {
        return '<div class="codebox"><div class="code-bar"><span class="code-lang">' . e($lang) . '</span><button type="button" class="btn btn-xs" data-copy-raw><i class="fa-regular fa-copy"></i> کپی</button></div>'
            . '<pre class="code" data-raw="' . e($json) . '">' . self::highlight($json) . '</pre></div>';
    }

    /** @return array<int,array{0:string,1:string}> ردیف‌های [مسیر فیلد، نوع] */
    public static function flatten($v, string $path = '', array &$rows = []): array
    {
        if (!is_array($v)) {
            return $rows;
        }
        $isList = $v === [] || array_keys($v) === range(0, count($v) - 1);
        if ($isList) {
            if ($path !== '') {
                $rows[] = [$path, 'array'];
            }
            if ($v !== [] && is_array($v[0])) {
                self::flatten($v[0], $path . '[]', $rows);
            }
            return $rows;
        }
        if ($path !== '') {
            $rows[] = [$path, 'object'];
        }
        foreach ($v as $k => $c) {
            $p = $path === '' ? (string) $k : $path . '.' . $k;
            if (is_array($c)) {
                self::flatten($c, $p, $rows);
            } else {
                $rows[] = [$p, self::type($c)];
            }
        }
        return $rows;
    }

    private static function type($v): string
    {
        if (is_bool($v)) {
            return 'boolean';
        }
        if (is_int($v)) {
            return 'integer';
        }
        if (is_float($v)) {
            return 'number';
        }
        if ($v === null) {
            return 'null';
        }
        return 'string';
    }

    public static function describe(array $fields, string $group, string $path): string
    {
        $last = (string) preg_replace('/\[\]$/', '', (string) substr(strrchr('.' . $path, '.'), 1));
        return (string) ($fields['groups'][$group][$last] ?? $fields['common'][$last] ?? '—');
    }

    private static function sampleValue(array $p)
    {
        $n = $p['name'];
        $map = [
            'mobile' => '09123456789', 'email' => 'user@example.com', 'due_date' => '2026-11-05', 'due_time' => '14:30', 'priority' => 'medium',
            'title' => 'عنوان نمونه', 'description' => 'توضیحات نمونه', 'first_name' => 'سارا', 'last_name' => 'محمدی', 'store' => 'فروشگاه نمونه',
            'website' => 'https://example.com', 'content' => 'متن نمونه', 'note' => 'یادداشت نمونه', 'max_members' => 10, 'sort_order' => 0,
            'permissions' => ['complete', 'notes'], 'members' => [2, 3], 'id' => 42, 'user_id' => 9,
        ];
        if (array_key_exists($n, $map)) {
            return $map[$n];
        }
        if ($p['type'] === 'integer') {
            return 1;
        }
        if ($p['type'] === 'boolean') {
            return true;
        }
        if ($p['type'] === 'array') {
            return [];
        }
        return 'مقدار نمونه';
    }

    /** بدنه‌ی JSON نمونه‌ی درخواست (فقط پارامترهای body). */
    public static function sampleBody(array $ep): array
    {
        $out = [];
        $extra = 0;
        foreach ($ep['params'] as $p) {
            if ($p['in'] !== 'body') {
                continue;
            }
            if ($p['required'] || $extra < 2) {
                $out[$p['name']] = self::sampleValue($p);
                if (!$p['required']) {
                    $extra++;
                }
            }
        }
        return $out;
    }

    /** query-string نمونه: پارامترهای الزامی + limit. */
    public static function sampleQuery(array $ep): array
    {
        $q = [];
        foreach ($ep['params'] as $p) {
            if ($p['in'] === 'query' && ($p['required'] || $p['name'] === 'limit')) {
                $q[$p['name']] = $p['name'] === 'limit' ? 20 : self::sampleValue($p);
            }
        }
        return $q;
    }

    /** نمونه‌های پاسخ خطا مرتبط با endpoint. @return array<int,array{0:string,1:array}> */
    public static function errors(array $ep, string $group): array
    {
        $env = static fn(string $msg, string $code): array => ['success' => false, 'status' => 'error', 'message' => $msg, 'error_code' => $code];
        $out = [['401', $env('توکن نامعتبر یا غیرفعال است.', 'TOKEN_INVALID')]];
        $needsId = false;
        $firstReq = null;
        foreach ($ep['params'] as $p) {
            if ($p['required'] && in_array($p['name'], ['id', 'user_id', 'row_id'], true)) {
                $needsId = true;
            }
            if ($p['required'] && $firstReq === null && !in_array($p['name'], ['id', 'user_id', 'row_id'], true)) {
                $firstReq = $p['name'];
            }
        }
        if ($needsId && isset(self::NOT_FOUND[$group])) {
            $out[] = ['404', $env(self::NOT_FOUND[$group][1], self::NOT_FOUND[$group][0])];
        }
        if ($firstReq !== null) {
            $out[] = ['400', $env('پارامتر ' . $firstReq . ' الزامی است.', strtoupper($firstReq) . '_REQUIRED')];
        } elseif ($needsId) {
            $out[] = ['400', $env('پارامتر id الزامی است.', 'INVALID_ID')];
        }
        if ($ep['method'] === 'POST' && in_array($group, ['tasks', 'calls', 'projects', 'colleagues'], true)) {
            $out[] = ['403', $env('اجازه‌ی انجام این عملیات را ندارید.', 'FORBIDDEN')];
        }
        return $out;
    }
}
