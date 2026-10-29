<?php
/** @var string $baseUrl @var bool $loggedIn */
use App\Services\DocsHelper as D;
$tokenEx = 'tk_9f2c1b7e4a8d0c35b6e1f7a29d4c8b03e5a1f6c2d7b9e480';
$okMe = ['success' => true, 'status' => 'success', 'data' => ['id' => 7, 'full_name' => 'علی رضایی', 'mobile' => '09121234567', 'auth_method' => 'token']];
$bad = static fn(string $m, string $c) => ['success' => false, 'status' => 'error', 'message' => $m, 'error_code' => $c];
?>
<article class="dx-article">
<h1>نحوه دریافت و ارسال توکن</h1>
<p class="muted">همه‌ی درخواست‌های API باید احراز هویت شوند. روش پیشنهادی، <b>Bearer Token</b> در هدر <code>Authorization</code> است.</p>

<h2 class="dx-h">۱) دریافت توکن</h2>
<ol class="dx-steps">
    <li>وارد پنل شوید و صفحه‌ی <a href="<?= e(url('/tokens')) ?>"><b>توکن‌های API</b></a> را باز کنید.</li>
    <li>یک نام (مثلاً «ربات تلگرام») و در صورت نیاز مدت انقضا انتخاب و روی <b>ساخت توکن</b> بزنید.</li>
    <li>توکن را همان لحظه کپی کنید؛ <b>فقط یک‌بار</b> نمایش داده می‌شود (در دیتابیس فقط هش آن ذخیره می‌شود).</li>
</ol>
<div class="callout tip"><i class="fa-solid fa-circle-info"></i> قالب توکن: <code>tk_</code> + ۴۸ نویسه‌ی هگزادسیمال، مثل <code class="ltr"><?= e($tokenEx) ?></code></div>

<h2 class="dx-h">۲) هدرهای درخواست</h2>
<table class="dx-table"><thead><tr><th>#</th><th>هدر</th><th>الزامی</th><th>توضیحات</th></tr></thead><tbody>
    <tr><td>۱</td><td><code>Authorization</code></td><td>بله *</td><td><code>Bearer YOUR_TOKEN</code> — روش پیشنهادی</td></tr>
    <tr><td>۲</td><td><code>X-API-Key</code></td><td>جایگزین</td><td>اگر هدر Authorization در سرور شما فیلتر می‌شود، توکن را اینجا بفرستید.</td></tr>
    <tr><td>۳</td><td><code>Accept</code></td><td>پیشنهادی</td><td><code>application/json</code></td></tr>
    <tr><td>۴</td><td><code>Content-Type</code></td><td>برای POST</td><td><code>application/json</code> (یا <code>multipart/form-data</code>)</td></tr>
</tbody></table>
<div class="callout warn"><i class="fa-solid fa-triangle-exclamation"></i> ارسال توکن در آدرس (<code>?token=</code>) به‌دلیل ثبت در لاگ‌ها و تاریخچه‌ی مرورگر <b>به‌صورت پیش‌فرض غیرفعال</b> است.</div>

<h2 class="dx-h">۳) نمونه‌ی ارسال</h2>
<div class="dx-tabbed" data-tabbed>
    <div class="code-tabs"><button class="active" data-pane="curl">cURL</button><button data-pane="js">JavaScript</button><button data-pane="node">Node.js</button><button data-pane="php">PHP</button><button data-pane="py">Python</button><button data-pane="pm">Postman</button></div>
    <div class="dx-pane" data-pane="curl"><?= D::codeBox("# GET\ncurl -X GET \"" . $baseUrl . "/tasks/list?status=pending\" \\\n  -H \"Authorization: Bearer YOUR_TOKEN\" \\\n  -H \"Accept: application/json\"\n\n# POST (JSON)\ncurl -X POST \"" . $baseUrl . "/tasks/create\" \\\n  -H \"Authorization: Bearer YOUR_TOKEN\" \\\n  -H \"Content-Type: application/json\" \\\n  -d '{\"title\":\"گزارش هفتگی\",\"subject_id\":1,\"project_id\":2}'", 'bash') ?></div>
    <div class="dx-pane" data-pane="js" hidden><?= D::codeBox("const TOKEN = 'YOUR_TOKEN';\nconst BASE = '" . $baseUrl . "';\n\nconst res = await fetch(BASE + '/tasks/list?status=pending', {\n  headers: { 'Authorization': 'Bearer ' + TOKEN, 'Accept': 'application/json' }\n});\nconst json = await res.json();\nif (!json.success) throw new Error(json.message);\nconsole.log(json.data.tasks);", 'javascript') ?></div>
    <div class="dx-pane" data-pane="node" hidden><?= D::codeBox("import axios from 'axios';\n\nconst api = axios.create({\n  baseURL: '" . $baseUrl . "',\n  headers: { Authorization: 'Bearer ' + process.env.TASKAM_TOKEN }\n});\n\nconst { data } = await api.post('/tasks/create', { title: 'گزارش هفتگی', subject_id: 1, project_id: 2 });\nconsole.log(data);", 'javascript') ?></div>
    <div class="dx-pane" data-pane="php" hidden><?= D::codeBox("<?php\n\$ch = curl_init('" . $baseUrl . "/tasks/list?status=pending');\ncurl_setopt_array(\$ch, [\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_HTTPHEADER => ['Authorization: Bearer YOUR_TOKEN', 'Accept: application/json'],\n]);\n\$json = json_decode(curl_exec(\$ch), true);\nvar_dump(\$json['success']);", 'php') ?></div>
    <div class="dx-pane" data-pane="py" hidden><?= D::codeBox("import requests\n\nr = requests.get(\n    \"" . $baseUrl . "/tasks/list\",\n    params={\"status\": \"pending\"},\n    headers={\"Authorization\": \"Bearer YOUR_TOKEN\"},\n)\nprint(r.json())", 'python') ?></div>
    <div class="dx-pane" data-pane="pm" hidden>
        <ol class="dx-steps"><li>یک درخواست جدید بسازید و آدرس endpoint را وارد کنید.</li><li>تب <b>Authorization</b> ← نوع <b>Bearer Token</b> ← توکن را در فیلد Token بچسبانید.</li><li>برای POST: تب <b>Body</b> ← <b>raw</b> ← <b>JSON</b>.</li><li>روی <b>Send</b> بزنید.</li></ol>
    </div>
</div>

<h2 class="dx-h">۴) پاسخ‌ها</h2>
<div class="grid grid-2">
    <div><div class="muted small mb-1"><span class="badge green">200</span> توکن معتبر — <code>GET /me</code></div><?= D::codeBox(D::pretty($okMe)) ?></div>
    <div><div class="muted small mb-1"><span class="badge red">401</span> توکن نامعتبر / منقضی / غیرفعال</div><?= D::codeBox(D::pretty($bad('توکن نامعتبر یا غیرفعال است.', 'TOKEN_INVALID'))) ?></div>
    <div><div class="muted small mb-1"><span class="badge red">401</span> توکن ارسال نشده</div><?= D::codeBox(D::pretty($bad('دسترسی غیرمجاز. توکن ارسال کنید یا وارد شوید.', 'UNAUTHORIZED'))) ?></div>
    <div><div class="muted small mb-1"><span class="badge yellow">403</span> حساب صاحب توکن فعال نیست</div><?= D::codeBox(D::pretty($bad('حساب کاربری فعال نیست.', 'ACCOUNT_INACTIVE'))) ?></div>
</div>

<h2 class="dx-h">۵) آزمایش توکن</h2>
<div class="card flat">
    <p class="small muted">توکن را وارد کنید تا با <code>GET /me</code> بررسی شود (توکن فقط در حافظه‌ی همین تب می‌ماند).</p>
    <div class="flex gap-1"><input class="input ltr" id="tok-input" type="password" placeholder="<?= e(ph('docs_auth_token')) ?>" autocomplete="off"><button class="btn btn-primary" id="tok-test"><i class="fa-solid fa-plug-circle-check"></i> آزمایش</button></div>
    <div class="mt-1" id="tok-out" hidden><div class="small muted mb-1">وضعیت: <span class="badge" id="tok-status"></span></div><?= '<div class="codebox"><pre class="code" id="tok-pre"></pre></div>' ?></div>
</div>

<h2 class="dx-h">نکات امنیتی</h2>
<ul class="dx-list">
    <li>توکن را مثل رمز عبور نگه دارید؛ در کد سمت کاربر (مرورگر/اپ موبایل) و مخزن‌های عمومی قرار ندهید.</li>
    <li>برای هر برنامه یک توکن جدا بسازید تا در صورت نشت، فقط همان را حذف یا غیرفعال کنید.</li>
    <li>توکن‌ها می‌توانند تاریخ انقضا داشته باشند؛ توکن منقضی خطای <code>TOKEN_INVALID</code> می‌دهد.</li>
    <li>هر توکن فقط به داده‌های صاحب خود دسترسی دارد و رعایت مجوزهای همکاری/پروژه الزامی است.</li>
    <li>اگر از نشست مرورگر (کوکی) به‌جای توکن استفاده می‌کنید، برای درخواست‌های POST هدر <code>X-CSRF-Token</code> لازم است.</li>
</ul>
</article>
