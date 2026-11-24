<?php /** @var string $tab */ $isReg = $tab === 'register'; $cap = \KarlixLaravel\Auth\Captcha::enabled(); ?>
<?php /* v7.16 (دموی UI): تب‌های سگمنتی ورود/ثبت‌نام، عنوان و توضیح هر فرم، کد امنیتی داخل خود فرم */ ?>
<div class="tabs auth-tabs" role="tablist" aria-label="ورود یا ثبت‌نام">
    <button type="button" role="tab" id="t-login" aria-controls="form-login" aria-selected="<?= $isReg ? 'false' : 'true' ?>" class="tab <?= $isReg ? '' : 'active' ?>" data-auth-tab="login">ورود</button>
    <button type="button" role="tab" id="t-reg" aria-controls="form-register" aria-selected="<?= $isReg ? 'true' : 'false' ?>" class="tab <?= $isReg ? 'active' : '' ?>" data-auth-tab="register">ثبت‌نام</button>
</div>

<?php $regMobile = $isReg ? null : \App\Core\Session::pull('registered_mobile'); // بعد از ثبت‌نام: شماره پر و فوکوس روی رمز ?>
<form method="post" action="<?= e(url('/login')) ?>" id="form-login" role="tabpanel" aria-labelledby="t-login" class="auth-pane<?= $isReg ? ' hidden' : '' ?>" autocomplete="on">
    <?= csrf_field() ?>
    <h1 class="auth-h">خوش برگشتید</h1>
    <p class="auth-p">برای ورود، شماره موبایل و رمز عبور را وارد کنید.</p>
    <div class="field"><label for="l-mobile">شماره موبایل</label>
        <input class="input ltr" id="l-mobile" name="mobile" data-mobile inputmode="numeric" autocomplete="username" placeholder="<?= e(ph('login_mobile')) ?>" value="<?= e($regMobile ?? (old('_form') !== 'register' ? old('mobile') : '')) ?>" required <?= $regMobile ? '' : 'autofocus' ?>></div>
    <div class="field"><label for="l-pass">رمز عبور</label>
        <input class="input ltr" id="l-pass" type="password" name="password" autocomplete="current-password" required <?= $regMobile ? 'autofocus' : '' ?>></div>
    <?php if ($cap): ?>
    <div class="field"><label for="l-captcha">کد امنیتی</label>
        <div class="captcha-row">
            <input class="input ltr captcha-input" id="l-captcha" name="captcha" data-captcha maxlength="<?= \KarlixLaravel\Auth\Captcha::LENGTH ?>" lang="en" inputmode="latin" autocapitalize="characters" autocomplete="off" autocorrect="off" spellcheck="false" placeholder="<?= e(ph('login_captcha')) ?>" required>
            <img decoding="async" class="captcha-img" alt="تصویر کد امنیتی" width="120" height="40" data-captcha-img>
            <button type="button" class="tb-ib" data-captcha-refresh title="کد جدید" aria-label="نمایش کد امنیتی جدید"><i class="fa-solid fa-rotate" aria-hidden="true"></i></button>
        </div>
        <p class="hint">حروف تصویر را بنویسید (بزرگ و کوچک مهم نیست).</p></div>
    <?php endif; ?>
    <label class="check" for="l-remember"><input type="checkbox" id="l-remember" name="remember" value="1"><span>مرا به خاطر بسپار (۳۰ روز)</span></label>
    <button class="btn btn-primary btn-lg btn-block auth-submit" type="submit">ورود</button>
    <p class="hint center">بدون «مرا به خاطر بسپار»، پس از ۱ ساعت بی‌فعالیتی از حساب خارج می‌شوید.</p>
</form>

<form method="post" action="<?= e(url('/register')) ?>" id="form-register" role="tabpanel" aria-labelledby="t-reg" class="auth-pane<?= $isReg ? '' : ' hidden' ?>" autocomplete="off">
    <?= csrf_field() ?>
    <h1 class="auth-h">ساخت حساب</h1>
    <p class="auth-p">چند ثانیه بیشتر طول نمی‌کشد؛ حساب پس از تأیید مدیر فعال می‌شود.</p>
    <div class="row">
        <div class="field"><label for="r-first">نام <span class="req" aria-hidden="true">*</span></label><input class="input" id="r-first" name="first_name" value="<?= e(old('first_name')) ?>" maxlength="100" autocomplete="given-name" required></div>
        <div class="field"><label for="r-last">نام خانوادگی <span class="opt">اختیاری</span></label><input class="input" id="r-last" name="last_name" value="<?= e(old('last_name')) ?>" maxlength="100" autocomplete="family-name"></div>
    </div>
    <div class="field"><label for="r-mobile">شماره موبایل <span class="req" aria-hidden="true">*</span></label><input class="input ltr" id="r-mobile" name="mobile" data-mobile inputmode="numeric" placeholder="<?= e(ph('register_mobile')) ?>" value="<?= e(old('_form') === 'register' ? old('mobile') : '') ?>" required></div>
    <div class="field"><label for="r-email">ایمیل <span class="opt">اختیاری</span></label><input class="input ltr" id="r-email" type="email" name="email" value="<?= e(old('email')) ?>" maxlength="150"></div>
    <div class="field"><label for="r-pass">رمز عبور <span class="req" aria-hidden="true">*</span></label><input class="input ltr" type="password" name="password" id="r-pass" autocomplete="new-password" minlength="8" required>
        <div class="pw-meter"><i id="pw-bar"></i></div><p class="hint">حداقل ۸ نویسه، شامل حرف و عدد.</p></div>
    <div class="field"><label for="r-pass2">تکرار رمز عبور <span class="req" aria-hidden="true">*</span></label><input class="input ltr" type="password" id="r-pass2" name="password_confirm" autocomplete="new-password" data-pw-match="password" required></div>
    <button class="btn btn-primary btn-lg btn-block auth-submit" type="submit">ساخت حساب</button>
</form>
<?php $this->scripts[] = 'auth'; ?>
