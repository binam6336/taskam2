<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
$checks = 0;
function brandCheck(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } $GLOBALS['checks']++; echo "PASS $label\n"; }
$cookie = $_COOKIE;
try {
    unset($_COOKIE[THEME_COOKIE]);
    brandCheck(user_theme() === 'violet-dark' && theme_mode(user_theme()) === 'dark', 'پیش‌فرض بدون انتخاب قبلی بنفش تیره است');
    $_COOKIE[THEME_COOKIE] = 'green-light';
    brandCheck(user_theme() === 'green-light', 'انتخاب قبلی دستگاه حفظ می‌شود');
    brandCheck(theme_key('invalid') === 'violet-dark', 'تم نامعتبر به پیش‌فرض جدید برمی‌گردد');
    $logo = logo_svg(); $favicon = favicon_uri(); $png = brand_icon_url('icon-192.png');
    foreach (array_keys(themes()) as $theme) {
        $_COOKIE[THEME_COOKIE] = $theme;
        brandCheck(logo_svg('fixture', $theme) === $logo && favicon_uri($theme) === $favicon && icon_suffix($theme) === '' && brand_icon_url('icon-192.png') === $png, 'برند و آیکن در ' . $theme . ' ثابت‌اند');
    }
    foreach (['karlix-favicon.svg', 'karlix-fa.svg', 'karlix-en.svg'] as $name) {
        $xml = new DOMDocument(); $xml->load(BASE_PATH . '/public/assets/img/brand/' . $name, LIBXML_NONET);
        $xpath = new DOMXPath($xml);
        brandCheck($xml->documentElement->localName === 'svg' && $xpath->query('//*[local-name()="script" or local-name()="foreignObject" or local-name()="text"]')->length === 0 && $xpath->query('//@style')->length === 0, 'SVG مستقل از فونت و بدون محتوای اجرایی: ' . $name);
    }
    brandCheck(str_contains(brand_lockup_svg('fa'), 'karlix-fa.svg') && str_contains(brand_lockup_svg('en'), 'karlix-en.svg'), 'نسخه فارسی و انگلیسی از فایل رسمی خوانده می‌شوند');
    brandCheck(hash_file('sha256', BASE_PATH . '/public/assets/img/brand/karlix-icon.svg') === hash_file('sha256', BASE_PATH . '/public/assets/img/brand/karlix-favicon.svg'), 'نشانی قدیمی SVG نشان هم همان برند جدید را دارد');
    foreach (['icon-192' => 192, 'icon-512' => 512, 'icon-maskable-192' => 192, 'icon-maskable-512' => 512, 'apple-touch-icon' => 180] as $name => $size) {
        $file = BASE_PATH . '/public/assets/img/' . $name . '.png';
        $dimensions = getimagesize($file);
        brandCheck($dimensions[0] === $size && $dimensions[1] === $size, 'اندازه واقعی ' . $name);
        foreach (array_keys(themes()) as $theme) {
            brandCheck(hash_file('sha256', $file) === hash_file('sha256', BASE_PATH . '/public/assets/img/' . $name . '-' . $theme . '.png'), 'نام قدیمی ' . $name . '-' . $theme . ' همان تصویر ثابت است');
        }
    }
    $image = imagecreatefrompng(BASE_PATH . '/public/assets/img/icon-maskable-512.png');
    brandCheck((imagecolorat($image, 0, 0) & 0xFFFFFF) === 0x120E1E, 'حاشیه امن maskable زمینه ثابت برند را دارد');
    imagedestroy($image);
    $image = imagecreatefrompng(BASE_PATH . '/public/assets/img/badge-96.png');
    $opaque = 0; $transparent = 0;
    for ($x = 0; $x < 96; $x++) for ($y = 0; $y < 96; $y++) {
        $color = imagecolorat($image, $x, $y); $alpha = ($color >> 24) & 0x7F;
        if ($alpha === 127) $transparent++; else { $opaque++; if (($color & 0xFFFFFF) !== 0xFFFFFF) throw new RuntimeException('نشان اندروید باید تک‌رنگ باشد'); }
    }
    imagedestroy($image);
    brandCheck($opaque > 0 && $transparent > 0, 'نشان اندروید واقعاً سفید روی زمینه شفاف است');
    echo "OK $checks checks\n";
} finally { $_COOKIE = $cookie; }
