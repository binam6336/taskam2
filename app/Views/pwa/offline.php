<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#060908"><title>آفلاین • تسکام</title>
<link rel="icon" type="image/svg+xml" href="<?= e(favicon_uri()) ?>">
<style>
  html, body { margin: 0; height: 100%; background: #060908; color: #e9f3ee; font-family: Vazirmatn, Tahoma, system-ui, sans-serif; }
  .w { min-height: 100%; display: grid; place-items: center; text-align: center; padding: 2rem; box-sizing: border-box; }
  .logo { width: 96px; height: 96px; margin: 0 auto 1.2rem; }
  h1 { font-size: 1.3rem; margin: 0 0 .5rem; } p { color: #8fa89b; line-height: 2; margin: 0 0 1.4rem; }
  a { display: inline-block; padding: .7rem 1.6rem; border-radius: 12px; background: linear-gradient(180deg, #5eea8f, #22c55e); color: #04210f; font-weight: 800; text-decoration: none; }
</style>
</head>
<body><div class="w"><div>
    <div class="logo"><?= logo_svg() ?></div>
    <h1>اتصال اینترنت برقرار نیست</h1>
    <p>تسکام برای نمایش اطلاعات به اینترنت نیاز دارد.<br>بعد از وصل شدن، دوباره تلاش کنید.</p>
    <a href="<?= e(url('/')) ?>">تلاش دوباره</a>
</div></div></body>
</html>
