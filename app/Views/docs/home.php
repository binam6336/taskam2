<?php
/** @var array $spec @var string $baseUrl */
$total = 0;
foreach ($spec['groups'] as $g) {
    $total += count($g['endpoints']);
}
$sample = ['success' => true, 'status' => 'success', 'message' => 'پیام اختیاری', 'data' => ['…' => '…']];
?>
<section class="dx-hero">
    <h1>مستندات <span>Taskam API</span></h1>
    <p>REST API ساده و امن برای مدیریت تسک‌ها، درخواست‌های تماس، متن‌های آماده، همکاران و پروژه‌ها. با یک توکن Bearer در چند دقیقه شروع کنید.</p>
    <div class="flex gap-2 wrap">
        <a class="btn btn-primary" href="<?= e(url('/docs/authentication')) ?>"><i class="fa-solid fa-key"></i> نحوه دریافت و ارسال توکن</a>
        <a class="btn" href="<?= e(url('/docs/tasks/list')) ?>"><i class="fa-solid fa-play"></i> اولین endpoint</a>
    </div>
    <div class="flex gap-1 wrap mt-2"><span class="chip"><i class="fa-solid fa-code-branch"></i> نسخه <?= e($spec['meta']['version']) ?></span><span class="chip"><i class="fa-solid fa-plug"></i> <?= e(fa_num($total)) ?> endpoint</span><span class="chip"><i class="fa-solid fa-shield-halved"></i> Bearer Token</span><span class="chip">JSON</span></div>
</section>

<h2 class="dx-h">نشانی پایه</h2>
<div class="dx-url"><span class="dx-method m-get">BASE</span><code><?= e($baseUrl) ?></code><button class="btn btn-xs" data-copy="<?= e($baseUrl) ?>"><i class="fa-regular fa-copy"></i></button></div>
<p class="muted mt-1">همه‌ی endpointها نسبت به این آدرس هستند؛ مثلاً <code><?= e($baseUrl) ?>/tasks/list</code>.</p>

<h2 class="dx-h">شروع سریع</h2>
<ol class="dx-steps">
    <li><b>توکن بسازید.</b> وارد پنل شوید و از بخش <a href="<?= e(url('/tokens')) ?>">توکن‌های API</a> یک توکن بسازید (فقط یک‌بار نمایش داده می‌شود).</li>
    <li><b>توکن را بفرستید.</b> در هدر <code>Authorization: Bearer YOUR_TOKEN</code> — راهنمای کامل در <a href="<?= e(url('/docs/authentication')) ?>">صفحه‌ی ارسال توکن</a>.</li>
    <li><b>درخواست بزنید.</b> مثلاً دریافت اطلاعات خودتان:</li>
</ol>
<?= \App\Services\DocsHelper::codeBox("curl -X GET \"" . $baseUrl . "/me\" \\\n  -H \"Authorization: Bearer YOUR_TOKEN\" \\\n  -H \"Accept: application/json\"", 'bash') ?>

<h2 class="dx-h">بخش‌های API</h2>
<div class="grid grid-auto">
    <?php foreach ($spec['groups'] as $g): ?>
    <a class="card hover dx-gcard" href="<?= e(url('/docs/' . $g['id'])) ?>">
        <div class="flex items-center gap-2 mb-1"><span class="stat"><span class="ico" style="width:42px;height:42px"><i class="fa-solid <?= e($g['icon']) ?>"></i></span></span><b><?= e($g['title']) ?></b><span class="badge" style="margin-inline-start:auto"><?= e(fa_num(count($g['endpoints']))) ?> endpoint</span></div>
        <p class="muted small" style="margin:0"><?= e($g['desc']) ?></p>
    </a>
    <?php endforeach; ?>
</div>

<h2 class="dx-h">قالب پاسخ</h2>
<p class="muted">همه‌ی پاسخ‌ها JSON (UTF-8) هستند و پوشش یکسان دارند:</p>
<table class="dx-table"><thead><tr><th>#</th><th>فیلد</th><th>نوع</th><th>توضیحات</th></tr></thead><tbody>
<?php $i = 0; foreach ([['success', 'boolean'], ['status', 'string'], ['message', 'string'], ['data', 'object'], ['error_code', 'string']] as [$k, $t]): $i++; ?>
    <tr><td><?= e(fa_num($i)) ?></td><td><code><?= e($k) ?></code></td><td><code class="dx-type"><?= e($t) ?></code></td><td><?= e($fields['common'][$k]) ?></td></tr>
<?php endforeach; ?></tbody></table>
<div class="grid grid-2 mt-2">
    <div><div class="muted small mb-1">پاسخ موفق</div><?= \App\Services\DocsHelper::codeBox(\App\Services\DocsHelper::pretty($sample)) ?></div>
    <div><div class="muted small mb-1">پاسخ خطا</div><?= \App\Services\DocsHelper::codeBox(\App\Services\DocsHelper::pretty(['success' => false, 'status' => 'error', 'message' => 'توضیح خطا', 'error_code' => 'TASK_NOT_FOUND'])) ?></div>
</div>

<h2 class="dx-h">قراردادها</h2>
<ul class="dx-list">
    <li>بدنه‌ی POST می‌تواند <code>application/json</code> یا <code>form-data</code> باشد.</li>
    <li>پارامتر <code>id</code> در endpointهای مبتنی بر شناسه، در query یا body پذیرفته می‌شود.</li>
    <li>تاریخ <code>YYYY-MM-DD</code>، ساعت <code>HH:MM</code> و همه‌ی زمان‌ها به وقت تهران است.</li>
    <li>فهرست‌ها با <code>limit</code> (حداکثر ۲۰۰، پیش‌فرض ۱۰۰) و <code>offset</code> صفحه‌بندی می‌شوند.</li>
    <li>ایجاد تسک بدون «موضوع» و «پروژه» ممکن نیست (<code>subject_id</code> و <code>project_id</code> اجباری‌اند).</li>
</ul>
