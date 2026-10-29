<?php
/** @var array $group @var array $ep @var string $baseUrl @var array $fields */
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
$this->boot['ep'] = ['method' => $ep['method'], 'url' => $fullUrl, 'query' => (object) $query, 'body' => (object) $body, 'params' => $ep['params']];
$typeFa = ['integer' => 'int', 'string' => 'str', 'boolean' => 'bool', 'array' => 'array', 'object' => 'object', 'number' => 'float', 'null' => 'null'];
?>
<article class="dx-article">
    <nav class="dx-crumb"><a href="<?= e(url('/docs')) ?>">خانه</a> / <a href="<?= e(url('/docs/' . $gid)) ?>"><?= e($group['title']) ?></a> / <span><?= e($ep['path']) ?></span></nav>
    <h1><?= e($ep['summary']) ?></h1>
    <div class="dx-cols">
        <div class="dx-info">
            <p class="muted">این endpoint <b>نیازمند توکن</b> است. نتیجه همیشه با پوشش <code>success / status / data</code> برمی‌گردد.</p>

            <h2 class="dx-h">پارامترهای ورودی</h2>
            <?php if (!$ep['params']): ?><p class="faint">این endpoint پارامتر ورودی ندارد.</p><?php else: ?>
            <table class="dx-table"><thead><tr><th>#</th><th>فیلد</th><th>محل</th><th>نوع</th><th>توضیحات</th></tr></thead><tbody>
            <?php foreach ($ep['params'] as $i => $pr): ?>
                <tr><td><?= e(fa_num($i + 1)) ?></td><td><code><?= e($pr['name']) ?></code><?= $pr['required'] ? ' <span class="req">*</span>' : '' ?></td>
                    <td class="muted small"><?= $pr['in'] === 'query' ? 'query' : 'body' ?></td><td><code class="dx-type"><?= e($typeFa[$pr['type']] ?? $pr['type']) ?></code></td>
                    <td><?= e($pr['desc']) ?><?= $pr['default'] !== null ? ' <span class="faint">(پیش‌فرض: ' . e((string) $pr['default']) . ')</span>' : '' ?></td></tr>
            <?php endforeach; ?></tbody></table>
            <p class="hint">فیلدهای دارای <span class="req">*</span> الزامی هستند.</p>
            <?php endif; ?>

            <h2 class="dx-h">فیلدهای پاسخ (<code>data</code>)</h2>
            <table class="dx-table"><thead><tr><th>#</th><th>فیلد</th><th>نوع</th><th>توضیحات</th></tr></thead><tbody>
            <?php foreach ($rows as $i => [$path, $type]): ?>
                <tr><td><?= e(fa_num($i + 1)) ?></td><td><code class="ltr"><?= e($path) ?></code></td><td><code class="dx-type"><?= e($typeFa[$type] ?? $type) ?></code></td><td><?= e(D::describe($fields, $gid, $path)) ?></td></tr>
            <?php endforeach; ?></tbody></table>
            <p class="hint">پوشش بیرونی پاسخ (<code>success</code>، <code>status</code>، <code>message</code>، <code>data</code>، <code>error_code</code>) در <a href="<?= e(url('/docs')) ?>">صفحه‌ی خانه</a> توضیح داده شده است.</p>

            <h2 class="dx-h">امتحان زنده</h2>
            <form class="card flat" data-try>
                <div class="field"><label>توکن</label><input class="input ltr" type="password" data-token placeholder="<?= e(ph('docs_try_token')) ?>" autocomplete="off"></div>
                <?php foreach ($ep['params'] as $pr): ?>
                    <div class="try-row"><label class="small ltr"><?= e($pr['name']) ?><?= $pr['required'] ? ' *' : '' ?></label><input class="input ltr" data-in="<?= e($pr['in']) ?>" data-name="<?= e($pr['name']) ?>" data-type="<?= e($pr['type']) ?>" placeholder="<?= e($pr['type']) ?>"></div>
                <?php endforeach; ?>
                <button class="btn btn-primary btn-sm mt-1"><i class="fa-solid fa-paper-plane"></i> ارسال درخواست</button>
                <div class="try-out hidden mt-1"><div class="small muted mb-1">پاسخ: <span data-status class="badge"></span></div><div class="codebox"><pre class="code" data-out></pre></div></div>
            </form>
        </div>

        <aside class="dx-code">
            <div class="dx-url"><span class="dx-method m-<?= strtolower($ep['method']) ?>"><?= e($ep['method']) ?></span><code title="<?= e($shownUrl) ?>"><?= e($shownUrl) ?></code><button class="btn btn-xs" data-copy="<?= e($shownUrl) ?>"><i class="fa-regular fa-copy"></i></button></div>

            <?php if ($isPost): ?>
                <h3 class="dx-h3">نمونه جیسون جهت ارسال</h3>
                <?= $body ? D::codeBox(D::pretty($body)) : '<p class="faint small">این درخواست بدنه‌ی JSON ندارد (پارامترها در query هستند).</p>' ?>
            <?php else: ?>
                <h3 class="dx-h3">ارسال درخواست</h3>
                <p class="faint small">درخواست GET بدنه ندارد؛ پارامترها در query-string ارسال می‌شوند.</p>
            <?php endif; ?>

            <h3 class="dx-h3">نمونه‌ی درخواست</h3>
            <div class="dx-tabbed" data-tabbed data-samples>
                <div class="code-tabs"><button class="active" data-pane="curl">cURL</button><button data-pane="js">JavaScript</button><button data-pane="php">PHP</button><button data-pane="py">Python</button></div>
                <?php foreach (['curl' => 'bash', 'js' => 'javascript', 'php' => 'php', 'py' => 'python'] as $k => $lang): ?>
                    <div class="dx-pane" data-pane="<?= $k ?>" <?= $k === 'curl' ? '' : 'hidden' ?>><div class="codebox"><div class="code-bar"><span class="code-lang"><?= $lang ?></span><button type="button" class="btn btn-xs" data-copy-raw><i class="fa-regular fa-copy"></i> کپی</button></div><pre class="code" data-sample="<?= $k ?>"></pre></div></div>
                <?php endforeach; ?>
            </div>

            <h3 class="dx-h3">نمونه پاسخ سرور <span class="badge green">200</span></h3>
            <?= D::codeBox(D::pretty($success)) ?>

            <h3 class="dx-h3">نمونه پاسخ خطا</h3>
            <div class="dx-tabbed" data-tabbed>
                <div class="code-tabs"><?php foreach ($errs as $i => [$code]): ?><button class="<?= $i === 0 ? 'active' : '' ?>" data-pane="e<?= e($code) ?>"><?= e($code) ?></button><?php endforeach; ?></div>
                <?php foreach ($errs as $i => [$code, $json]): ?>
                    <div class="dx-pane" data-pane="e<?= e($code) ?>" <?= $i === 0 ? '' : 'hidden' ?>><?= D::codeBox(D::pretty($json)) ?></div>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
</article>
