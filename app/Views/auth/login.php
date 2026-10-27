<?php /** @var string $tab */ $isReg = $tab === 'register'; ?>
<div class="brand"><span class="logo"><i class="fa-solid fa-check"></i></span><span>تسکام<small>مدیریت وظایف، تیم و مشتریان</small></span></div>
<div class="card">
    <div class="tabs mb-2" style="display:flex">
        <button type="button" class="tab grow <?= $isReg ? '' : 'active' ?>" data-auth-tab="login" style="justify-content:center">ورود</button>
        <button type="button" class="tab grow <?= $isReg ? 'active' : '' ?>" data-auth-tab="register" style="justify-content:center">ثبت‌نام</button>
    </div>

    <form method="post" action="<?= e(url('/login')) ?>" id="form-login" class="<?= $isReg ? 'hidden' : '' ?>" autocomplete="on">
        <?= csrf_field() ?>
        <div class="field"><label for="l-mobile">شماره موبایل</label>
            <input class="input ltr" id="l-mobile" name="mobile" data-mobile inputmode="numeric" autocomplete="username" placeholder="09123456789" value="<?= e(old('mobile')) ?>" required autofocus></div>
        <div class="field"><label for="l-pass">رمز عبور</label>
            <input class="input ltr" id="l-pass" type="password" name="password" autocomplete="current-password" required></div>
        <label class="check mb-2" for="l-remember"><input type="checkbox" id="l-remember" name="remember" value="1"><div><b>من را به خاطر بسپار</b><span>فعال: نشست تا ۳۰ روز می‌ماند و خارج نمی‌شوید. غیرفعال: پس از ۱ ساعت عدم فعالیت خارج می‌شوید.</span></div></label>
        <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-right-to-bracket"></i> ورود به پنل</button>
    </form>

    <form method="post" action="<?= e(url('/register')) ?>" id="form-register" class="<?= $isReg ? '' : 'hidden' ?>" autocomplete="off">
        <?= csrf_field() ?>
        <div class="row">
            <div class="field"><label>نام</label><input class="input" name="first_name" value="<?= e(old('first_name')) ?>" maxlength="100" required></div>
            <div class="field"><label>نام خانوادگی</label><input class="input" name="last_name" value="<?= e(old('last_name')) ?>" maxlength="100"></div>
        </div>
        <div class="field"><label>شماره موبایل</label><input class="input ltr" name="mobile" data-mobile inputmode="numeric" placeholder="09123456789" value="<?= e(old('mobile')) ?>" required></div>
        <div class="field"><label>ایمیل <span class="faint">(اختیاری)</span></label><input class="input ltr" type="email" name="email" value="<?= e(old('email')) ?>" maxlength="150"></div>
        <div class="field"><label>رمز عبور</label><input class="input ltr" type="password" name="password" id="r-pass" autocomplete="new-password" minlength="8" required>
            <div class="pw-meter"><i id="pw-bar"></i></div><div class="hint">حداقل ۸ نویسه، شامل حرف و عدد.</div></div>
        <div class="field"><label>تکرار رمز عبور</label><input class="input ltr" type="password" name="password_confirm" autocomplete="new-password" required></div>
        <button class="btn btn-primary btn-block" type="submit"><i class="fa-solid fa-user-plus"></i> ایجاد حساب</button>
        <p class="hint center mt-1">حساب جدید پس از تأیید مدیر فعال می‌شود.</p>
    </form>
</div>
<p class="center small muted mt-2"><a href="<?= e(url('/docs')) ?>"><i class="fa-solid fa-book"></i> مستندات API</a></p>
<?php $this->scripts[] = 'auth'; ?>
