<?php
declare(strict_types=1);
namespace KarlixLaravel\Session;

/**
 * داده‌های کمکی Laravel در یک کلید از نشست موجود، مستقل از کلیدهای هویت و امنیت.
 * get/flash/old input و کلیدهای تو در تو همان رفتار Store رسمی را دارند.
 * Session مشترک، رمزگذاری و جداسازی پنجرهٔ مدیر را بدون جدول یا کوکی جدید حفظ می‌کند.
 */
final class SharedStore extends \Illuminate\Session\Store
{
    private array $initial;
    private bool $saved = false;

    public function __construct()
    {
        parent::__construct('taskam_sid', new \Illuminate\Session\ArraySessionHandler(120));
        $this->attributes = (array) \App\Core\Session::get('_laravel_store', []);
        $this->initial = $this->attributes;
    }

    public function start() { return $this->started = true; }
    public function save()
    {
        if ($this->attributes !== $this->initial || (!$this->saved && $this->attributes !== [])) {
            $this->ageFlashData();
            \App\Core\Session::put('_laravel_store', $this->attributes);
            $this->initial = $this->attributes;
        }
        $this->started = false;
        $this->saved = true;
    }
    public function token() { return \App\Core\Csrf::token(); }
    public function regenerateToken()
    {
        if (\App\Core\Impersonation::active()) {
            throw new \LogicException('Cannot rotate the admin CSRF token inside impersonation');
        }
        \App\Core\Session::forget('_csrf');
        \App\Core\Csrf::token();
    }
    public function getId() { return session_id() ?: parent::getId(); }
    public function getName() { return session_status() === PHP_SESSION_ACTIVE ? session_name() : parent::getName(); }
    public function migrate($destroy = false)
    {
        if (\App\Core\Impersonation::active()) {
            throw new \LogicException('Cannot rotate the admin session inside impersonation');
        }
        \App\Core\Session::regenerate();
        return true;
    }
    public function invalidate()
    {
        if (\App\Core\Impersonation::active()) {
            throw new \LogicException('End the impersonation window instead of invalidating the admin session');
        }
        $this->flush();
        $this->initial = [];
        \App\Core\Session::destroy();
        return true;
    }
}
