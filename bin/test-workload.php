<?php
declare(strict_types=1);

/**
 * تست طلایی الگوریتم «بار کاری» (v7.10): WorkloadService (PHP) روی tests/workload/*.input.json
 * باید دقیقاً همان loads، suggestions و unresolved فایل *.expected.json را بدهد (today از خود fixture).
 *   php bin/test-workload.php            → PASS/FAIL هر fixture، کد خروج ۱ در صورت شکست
 * همتای JS: node bin/test-workload.js
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('BASE_PATH', dirname(__DIR__));
spl_autoload_register(static function (string $c): void {
    $f = BASE_PATH . '/app/' . str_replace(['App\\', '\\'], ['', '/'], $c) . '.php';
    if (is_file($f)) {
        require $f;
    }
});

use App\Services\WorkloadService as W;

$dir = BASE_PATH . '/tests/workload';
$fail = 0;
foreach (glob($dir . '/*.input.json') as $in) {
    $data = json_decode((string) file_get_contents($in), true);
    $exp = json_decode((string) file_get_contents(str_replace('.input.', '.expected.', $in)), true);
    $res = W::suggestRebalance($data);
    $got = ['loads' => W::loadsOut(W::computeLoads($data)), 'suggestions' => $res['suggestions'], 'unresolved' => $res['unresolved']];
    $a = json_encode($got, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    $b = json_encode($exp, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    $ok = $a === $b;
    $fail += $ok ? 0 : 1;
    echo ($ok ? 'PASS ' : 'FAIL ') . basename($in) . PHP_EOL;
    if (!$ok) {
        echo '  expected: ' . $b . PHP_EOL . '  got:      ' . $a . PHP_EOL;
    }
    // «اعمال همه»: همه‌ی پیشنهادها روی داده اعمال می‌شود (مسئول + سررسید تازه)؛ بعد از آن کسی جز اعضای unresolved
    // (در fixture ۱ فقط nothing_movable؛ در fixture ۳ عمداً no_target) بالای ۱۰۰٪ نمی‌ماند و هیچ مقصدی بالای ۸۵٪ نمی‌رود (معیار پذیرش ۴)
    $after = $data;
    $targets = [];
    foreach ($res['suggestions'] as $g) {
        foreach ($after['tasks'] as &$t) {
            if ((string) $t['id'] === (string) $g['task_id']) {
                $t['assignee_id'] = $g['to_id'];
                if ($g['new_due_date'] !== null) {
                    $t['due_date'] = $g['new_due_date'];
                }
            }
        }
        unset($t);
        $targets[(string) $g['to_id']] = true;
    }
    $nm = [];
    foreach ($res['unresolved'] as $u) {
        $nm[(string) $u['member_id']] = $u['name'] . ' ' . $u['pct'] . '% ' . $u['reason'];
    }
    $bad = [];
    foreach (W::computeLoads($after) as $r) {
        $id = (string) $r['member']['id'];
        if ($r['pct'] > 100 && !isset($nm[$id])) {
            $bad[] = $r['member']['name'] . ' ' . $r['pct'] . '% (پربار مانده)';
        }
        if (isset($targets[$id]) && $r['pct'] > 85) {
            $bad[] = $r['member']['name'] . ' ' . $r['pct'] . '% (مقصد بالای ۸۵)';
        }
    }
    $fail += $bad ? 1 : 0;
    echo ($bad ? '  FAIL' : '  PASS') . ' apply-all: ' . count($res['suggestions']) . ' پیشنهاد' . ($nm ? '، بالای ۱۰۰ مجاز: ' . implode('، ', $nm) : '') . ($bad ? ' — ' . implode('، ', $bad) : '') . PHP_EOL;
}
exit($fail ? 1 : 0);
