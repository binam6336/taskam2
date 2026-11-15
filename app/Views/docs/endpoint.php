<?php
/**
 * صفحه‌ی هر endpoint (v7.8، ساده‌شده): ۱) نمونه‌ی JSON ورودی ۲) نمونه‌ی JSON خروجی ۳) جدول توضیح پارامترهای ورودی ۴) جدول فیلدهای پاسخ.
 * امتحان زنده و نمونه‌کدها به «تست API» (Swagger، /docs/swagger) منتقل شد.
 * @var array $group @var array $ep @var string $baseUrl @var array $fields
 */
use App\Services\DocsHelper as D;
$gid = $group['id'];
$isPost = $ep['method'] === 'POST';
$query = D::sampleQuery($ep);
$body = $isPost ? D::sampleBody($ep) : [];
$fullUrl = $baseUrl . ($ep['path'] === '/' ? '' : $ep['path']);
$shownUrl = $fullUrl . ($query ? '?' . http_build_query($query) : '');
$success = ['success' => true, 'status' => 'success', 'data' => $ep['example']];
$rows = D::flatten($ep['example']);
$errs = D::errors($ep, $gid);
$typeFa = ['integer' => 'int', 'string' => 'str', 'boolean' => 'bool', 'array' => 'array', 'object' => 'object', 'number' => 'float', 'null' => 'null'];
$input = $isPost ? $body : $query;
?>
<article class="dx-article dx-simple">
    <nav class="dx-crumb"><a href="<?= e(url('/docs')) ?>">خانه</a> / <a href="<?= e(url('/docs/' . $gid)) ?>"><?= e($group['title']) ?></a> / <span><?= e($ep['path']) ?></span></nav>
    <h1><?= e($ep['summary']) ?></h1>
    <div class="dx-url"><span class="dx-method m-<?= strtolower($ep['method']) ?>"><?= e($ep['method']) ?></span><code title="<?= e($shownUrl) ?>"><?= e($shownUrl) ?></code><button class="btn btn-xs" aria-label="کپی نشانی" title="کپی نشانی" data-copy="<?= e($shownUrl) ?>"><i class="fa-regular fa-copy"></i></button>
        <a class="btn btn-xs dx-try" href="<?= e(url('/docs/swagger') . '#/' . rawurlencode($group['title'])) ?>" title="امتحان همین endpoint در تست API (Swagger)"><i class="fa-solid fa-flask"></i> تست API</a></div>
    <p class="muted small">نیازمند توکن (<code>Authorization: Bearer …</code>). پاسخ همیشه با پوشش <code>success / status / data</code> برمی‌گردد.</p>

    <div class="dx-json2">
        <section>
            <h2 class="dx-h"><i class="fa-solid fa-upload" aria-hidden="true"></i> نمونه‌ی ورودی <small class="faint"><?= $isPost ? 'بدنه‌ی JSON' : 'پارامترهای query (به‌صورت JSON)' ?></small></h2>
            <?= $input ? D::codeBox(D::pretty($input)) : '<p class="faint small dx-noin">این endpoint ورودی ندارد.</p>' ?>
        </section>
        <section>
            <h2 class="dx-h"><i class="fa-solid fa-download" aria-hidden="true"></i> نمونه‌ی خروجی <span class="badge green">200</span></h2>
            <?= D::codeBox(D::pretty($success)) ?>
        </section>
    </div>

    <h2 class="dx-h">پارامترهای ورودی</h2>
    <?php if (!$ep['params']): ?><p class="faint">این endpoint پارامتر ورودی ندارد.</p><?php else: ?>
    <div class="table-wrap"><table class="dx-table"><thead><tr><th>#</th><th>فیلد</th><th>محل</th><th>نوع</th><th>توضیحات</th></tr></thead><tbody>
    <?php foreach ($ep['params'] as $i => $pr): ?>
        <tr><td><?= e(fa_num($i + 1)) ?></td><td><code><?= e($pr['name']) ?></code><?= $pr['required'] ? ' <span class="req">*</span>' : '' ?></td>
            <td class="muted small"><?= $pr['in'] === 'query' ? 'query' : 'body' ?></td><td><code class="dx-type"><?= e($typeFa[$pr['type']] ?? $pr['type']) ?></code></td>
            <td><?= e($pr['desc']) ?><?= $pr['default'] !== null ? ' <span class="faint">(پیش‌فرض: ' . e((string) $pr['default']) . ')</span>' : '' ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <p class="hint">فیلدهای دارای <span class="req">*</span> الزامی هستند.</p>
    <?php endif; ?>

    <h2 class="dx-h">فیلدهای پاسخ (<code>data</code>)</h2>
    <div class="table-wrap"><table class="dx-table"><thead><tr><th>#</th><th>فیلد</th><th>نوع</th><th>توضیحات</th></tr></thead><tbody>
    <?php foreach ($rows as $i => [$path, $type]): ?>
        <tr><td><?= e(fa_num($i + 1)) ?></td><td><code class="ltr"><?= e($path) ?></code></td><td><code class="dx-type"><?= e($typeFa[$type] ?? $type) ?></code></td><td><?= e(D::describe($fields, $gid, $path)) ?></td></tr>
    <?php endforeach; ?></tbody></table></div>

    <details class="dx-errs">
        <summary><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> نمونه پاسخ‌های خطا (<?= e(fa_num(count($errs))) ?>)</summary>
        <?php foreach ($errs as [$code, $json]): ?><h3 class="dx-h3"><span class="badge red"><?= e($code) ?></span></h3><?= D::codeBox(D::pretty($json)) ?><?php endforeach; ?>
        <p class="hint">فهرست کامل کدها در <a href="<?= e(url('/docs/errors')) ?>">کدهای خطا</a>.</p>
    </details>
</article>
