<?php /** @var array $u */ $this->scripts[] = 'auth'; ?>
<div class="card">
    <div class="center">
        <div class="stat" style="justify-content:center;margin-bottom:1rem"><div class="ico warn" style="width:64px;height:64px;font-size:1.6rem"><i class="fa-solid fa-wifi"></i></div></div>
        <h2>شبکه‌ی اینترنت شما عوض شده است</h2>
        <p class="muted">برای امنیت حساب، <b><?= e(full_name($u)) ?></b> عزیز، رمز عبور خود را دوباره وارد کنید تا از همان جایی که بودید ادامه دهید.</p>
    </div>
    <form method="post" action="<?= e(url('/reauth')) ?>" class="mt-2"><?= csrf_field() ?>
        <div class="field"><label for="ra-pass">رمز عبور</label>
            <input class="input ltr" id="ra-pass" type="password" name="password" autocomplete="current-password" required autofocus></div>
        <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-unlock"></i> ادامه</button>
    </form>
    <form method="post" action="<?= e(url('/reauth/cancel')) ?>" class="mt-1 center"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><i class="fa-solid fa-right-from-bracket"></i> خروج و ورود با حساب دیگر</button></form>
</div>
