<form method="post" action="<?= e(url('/admin/side-panels' . ($panel ? '/' . $panel['id'] . '/save' : ''))) ?>" data-ajax class="side-panel-form mt-2" autocomplete="off">
    <?= csrf_field() ?>
    <label>عنوان<input class="input" name="title" maxlength="150" value="<?= e($panel['title'] ?? '') ?>" required></label>
    <label>نشانی سایت<input class="input ltr" type="url" name="url" maxlength="2048" value="<?= e($panel['url'] ?? '') ?>" placeholder="https://example.com" required></label>
    <label>توضیحات<textarea class="input" name="description" maxlength="2000" rows="3"><?= e($panel['description'] ?? '') ?></textarea></label>
    <label>نام کاربری (اختیاری)<input class="input ltr" name="username" maxlength="500" autocomplete="off"></label>
    <label>رمز عبور (اختیاری)<input class="input ltr" type="password" name="password" maxlength="500" autocomplete="new-password"></label>
    <?php if ($panel): ?>
    <p class="small muted">خالی گذاشتن اطلاعات ورود، مقدار قبلی را نگه می‌دارد. برای حذف از گزینه‌های زیر استفاده کنید.</p>
    <label><input type="checkbox" name="clear_username" value="1"> حذف نام کاربری</label>
    <label><input type="checkbox" name="clear_password" value="1"> حذف رمز عبور</label>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary"<?= !$cryptoReady ? ' disabled' : '' ?>>ذخیره پنل</button>
</form>
