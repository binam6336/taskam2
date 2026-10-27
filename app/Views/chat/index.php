<?php /** @var int $me */ $this->boot['chat'] = ['me' => $me, 'open' => (int) ($_GET['user'] ?? 0)]; $this->scripts[] = 'chat'; ?>
<div class="chat" id="chat">
    <aside class="chat-side">
        <div class="chat-side-head">
            <div class="search"><i class="fa-solid fa-magnifying-glass"></i><input class="input" id="chat-search" placeholder="جستجو در مخاطبان و پیام‌ها…" autocomplete="off"></div>
            <?php if (\App\Core\Crypto::configured()): ?><div class="xs muted mt-1" title="متن پیام‌ها و نام فایل‌ها در دیتابیس رمزگذاری‌شده ذخیره می‌شود"><i class="fa-solid fa-lock" style="color:var(--g-400)"></i> پیام‌ها در دیتابیس رمزگذاری می‌شوند</div><?php endif; ?>
        </div>
        <div class="chat-contacts" id="contacts"></div>
    </aside>
    <section class="chat-main">
        <div class="chat-empty" id="chat-empty"><i class="fa-regular fa-comments" style="font-size:3rem;color:var(--border-2)"></i><p class="mt-1">یک گفتگو را انتخاب کنید.</p></div>
        <div id="chat-conv" style="display:none;flex-direction:column;min-height:0;flex:1">
            <div class="chat-head">
                <button class="btn btn-ghost btn-icon btn-sm" id="chat-back" aria-label="بازگشت"><i class="fa-solid fa-arrow-right"></i></button>
                <span class="avatar" id="ch-avatar"></span>
                <div class="grow"><div class="bold" id="ch-name"></div><div class="xs muted" id="ch-status"></div></div>
                <div class="search" style="width:200px"><i class="fa-solid fa-magnifying-glass"></i><input class="input" id="conv-search" placeholder="جستجو در این گفتگو"></div>
            </div>
            <div class="chat-msgs" id="msgs"></div>
            <form class="chat-input" id="chat-form" enctype="multipart/form-data">
                <label class="btn btn-icon" title="ارسال فایل" style="cursor:pointer"><i class="fa-solid fa-paperclip"></i><input type="file" id="chat-file" class="hidden" accept=".png,.jpg,.jpeg,.pdf,.zip,.txt"></label>
                <textarea class="input" id="chat-text" rows="1" placeholder="پیام خود را بنویسید…" maxlength="2000"></textarea>
                <button class="btn btn-primary btn-icon" aria-label="ارسال"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
        </div>
    </section>
</div>
<div class="modal" id="m-results"><div class="modal-box"><div class="modal-head"><h3><i class="fa-solid fa-magnifying-glass"></i> نتایج جستجو</h3><button class="btn btn-ghost btn-icon btn-sm" data-close><i class="fa-solid fa-xmark"></i></button></div><div class="modal-body" id="results-body"></div></div></div>
