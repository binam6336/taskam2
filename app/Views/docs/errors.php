<?php /** @var array $spec */ use App\Services\DocsHelper as D; ?>
<article class="dx-article">
<h1>قالب پاسخ و کد خطاها</h1>
<p class="muted">در صورت خطا، وضعیت HTTP متناسب برمی‌گردد و بدنه شامل <code>message</code> (فارسی) و <code>error_code</code> (برای برنامه‌ی شما) است.</p>
<?= D::codeBox(D::pretty(['success' => false, 'status' => 'error', 'message' => 'تسک یافت نشد.', 'error_code' => 'TASK_NOT_FOUND'])) ?>
<h2 class="dx-h">جدول خطاها</h2>
<table class="dx-table"><thead><tr><th>#</th><th>error_code</th><th>HTTP</th><th>توضیحات</th></tr></thead><tbody>
<?php foreach ($spec['errors'] as $i => $er): ?>
    <tr><td><?= e(fa_num($i + 1)) ?></td><td><code><?= e($er['code']) ?></code></td><td><span class="badge <?= $er['http'] >= 500 ? 'red' : 'yellow' ?>"><?= e($er['http']) ?></span></td><td><?= e($er['desc']) ?></td></tr>
<?php endforeach; ?></tbody></table>
<div class="callout"><b>اعتبارسنجی:</b> خطای ورودی‌ها با HTTP ۴۰۰ و شناسه‌ی ناموجود با ۴۰۴ برمی‌گردد. کدهایی مثل <code>TITLE_REQUIRED</code>، <code>SUBJECT_REQUIRED</code>، <code>PROJECT_REQUIRED</code>، <code>DUE_DATE_INVALID</code> و <code>ASSIGNEE_INVALID</code> در پیام و کد، دلیل دقیق را مشخص می‌کنند.</div>
</article>
