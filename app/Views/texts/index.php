<?php /** @var array $texts */ $this->boot['texts'] = $texts; $this->scripts[] = 'texts'; ?>
<div class="page-head">
    <div class="search grow" style="max-width:420px"><i class="fa-solid fa-magnifying-glass"></i><input class="input" id="text-search" placeholder="جستجو در عنوان و متن…"></div>
    <button class="btn btn-primary" id="btn-new-text"><i class="fa-solid fa-plus"></i> متن جدید</button>
</div>
<p class="muted small mb-2"><i class="fa-solid fa-circle-info"></i> با کشیدن و رها کردن کارت‌ها ترتیب را تغییر دهید؛ با کلیک روی «کپی» متن در کلیپ‌بورد قرار می‌گیرد.</p>
<div class="grid grid-auto" id="text-grid"></div>
<div class="card empty hidden" id="text-empty"><i class="fa-solid fa-message"></i><p>متنی پیدا نشد.</p></div>

<div class="modal" id="m-text"><div class="modal-box">
    <form id="f-text"><div class="modal-head"><h3><i class="fa-solid fa-message"></i> <span id="text-modal-title">متن جدید</span></h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div>
        <div class="modal-body"><input type="hidden" name="id">
            <div class="field"><label>عنوان *</label><input class="input" name="title" maxlength="255" required></div>
            <div class="field"><label>متن *</label><textarea class="input" name="content" rows="8" required></textarea></div></div>
        <div class="modal-foot"><button type="button" class="btn" data-close>انصراف</button><button class="btn btn-primary">ذخیره</button></div></form></div></div>
