package com.taskam.app;

import android.Manifest;
import android.annotation.SuppressLint;
import android.app.Activity;
import android.app.AlertDialog;
import android.content.ActivityNotFoundException;
import android.content.ClipData;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.graphics.Color;
import android.net.Uri;
import android.net.http.SslCertificate;
import android.net.http.SslError;
import android.os.Build;
import android.os.Bundle;
import android.os.Message;
import android.view.View;
import android.view.Window;
import android.webkit.CookieManager;
import android.webkit.JsResult;
import android.webkit.SslErrorHandler;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.FrameLayout;
import android.widget.ProgressBar;
import android.widget.TextView;
import android.widget.Toast;

import java.io.ByteArrayInputStream;
import java.security.cert.CertificateFactory;
import java.security.cert.X509Certificate;
import java.util.ArrayList;

/**
 * پوسته‌ی اندروید تسکام: همان پنل وب (بدون تغییر فرانت) داخل WebView.
 * - لینک‌های همان سرور داخل برنامه، بقیه (tel:، mailto:، سایت‌های دیگر) در برنامه‌ی مربوط باز می‌شوند.
 * - window.open / target=_blank (مثل «ورود به پنل کاربر» مدیر) یک لایه‌ی جدید روی صفحه باز می‌کند؛ دکمه‌ی بازگشت آن را می‌بندد.
 * - آدرس سرور ثابت است (SERVER_URL)؛ زنجیره‌ی گواهی ناقص سرور مثل کروم تکمیل می‌شود (AiaTrust).
 * - انتخاب فایل برای پیوست‌ها، دانلود فایل در Downloads (همراه کوکی نشست)، صفحه‌ی خطای اتصال با کد خطا، رنگ نوار وضعیت از theme-color.
 */
public class MainActivity extends Activity {
    private static final int REQ_FILE = 10;
    private static final int REQ_STORAGE = 11;
    /** آدرس ثابت سرور (app/build.gradle → SERVER_URL)؛ کاربر آن را نمی‌بیند و عوض نمی‌کند. */
    private static final String SERVER = BuildConfig.SERVER_URL.replaceAll("/+$", "");
    private static final String UA_SUFFIX = " TaskamAndroid/" + BuildConfig.VERSION_NAME;

    private FrameLayout stack;
    private ProgressBar progress;
    private View splash, errorView;
    private final ArrayList<WebView> webs = new ArrayList<>();
    private ValueCallback<Uri[]> fileCb;
    private String server, serverHost;
    private String[] pendingDownload;

    @Override
    protected void onCreate(Bundle state) {
        super.onCreate(state);
        server = SERVER;
        serverHost = Uri.parse(server).getHost();
        setContentView(R.layout.activity_main);
        stack = findViewById(R.id.web_stack);
        progress = findViewById(R.id.progress);
        splash = findViewById(R.id.splash);
        errorView = findViewById(R.id.error_view);
        findViewById(R.id.retry).setOnClickListener(v -> retry());

        WebView.setWebContentsDebuggingEnabled(BuildConfig.DEBUG);
        CookieManager.getInstance().setAcceptCookie(true);
        WebView main = newWebView();
        stack.addView(main);
        webs.add(main);
        if (state == null || main.restoreState(state) == null) {
            main.loadUrl(server + "/");
        }
    }

    // ───────────────────────── WebView ─────────────────────────

    @SuppressLint("SetJavaScriptEnabled")
    private WebView newWebView() {
        WebView w = new WebView(this);
        w.setLayoutParams(new FrameLayout.LayoutParams(FrameLayout.LayoutParams.MATCH_PARENT, FrameLayout.LayoutParams.MATCH_PARENT));
        w.setBackgroundColor(getColor(R.color.bg));
        WebSettings s = w.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setDatabaseEnabled(true);
        s.setJavaScriptCanOpenWindowsAutomatically(true);
        s.setSupportMultipleWindows(true);
        s.setMediaPlaybackRequiresUserGesture(false);
        s.setAllowFileAccess(false);
        s.setAllowContentAccess(true);
        s.setLoadWithOverviewMode(true);
        s.setUseWideViewPort(true);
        s.setSupportZoom(false);
        s.setBuiltInZoomControls(false);
        s.setTextZoom(100);   // اندازه‌ی فونت سیستم چیدمان پنل را به هم نریزد
        s.setMixedContentMode(WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE);
        s.setUserAgentString(s.getUserAgentString() + UA_SUFFIX);
        CookieManager.getInstance().setAcceptThirdPartyCookies(w, true);
        w.setWebViewClient(new Client());
        w.setWebChromeClient(new Chrome());
        w.setDownloadListener((url, ua, cd, mime, len) -> download(url, ua, cd, mime));
        w.setOverScrollMode(View.OVER_SCROLL_NEVER);
        return w;
    }

    private WebView top() {
        return webs.get(webs.size() - 1);
    }

    private boolean isInternal(Uri u) {
        String sc = u.getScheme();
        return ("http".equals(sc) || "https".equals(sc)) && serverHost != null && serverHost.equalsIgnoreCase(u.getHost());
    }

    private void openExternal(Uri u) {
        try {
            Intent i;
            if ("intent".equals(u.getScheme())) {
                i = Intent.parseUri(u.toString(), Intent.URI_INTENT_SCHEME);
                i.addCategory(Intent.CATEGORY_BROWSABLE);
                i.setComponent(null);
                i.setSelector(null);
            } else {
                i = new Intent(Intent.ACTION_VIEW, u);
            }
            startActivity(i);
        } catch (ActivityNotFoundException | java.net.URISyntaxException e) {
            Toast.makeText(this, "برنامه‌ای برای باز کردن این لینک پیدا نشد.", Toast.LENGTH_SHORT).show();
        }
    }

    private void closeChild(WebView w) {
        if (webs.size() <= 1 || webs.get(0) == w) return;
        webs.remove(w);
        stack.removeView(w);
        w.destroy();
        updateBars(top());
    }

    private class Client extends WebViewClient {
        @Override
        public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest req) {
            Uri u = req.getUrl();
            if (isInternal(u)) return false;
            if ("about".equals(u.getScheme()) || "blob".equals(u.getScheme()) || "data".equals(u.getScheme())) return false;
            openExternal(u);
            // پنجره‌ی تازه‌ای که فقط برای یک لینک بیرونی باز شده بود، خالی نماند
            if (view != webs.get(0) && view.copyBackForwardList().getSize() == 0) closeChild(view);
            return true;
        }

        @Override
        public void onPageStarted(WebView view, String url, android.graphics.Bitmap favicon) {
            if (view == top()) progress.setVisibility(View.VISIBLE);
        }

        @Override
        public void onPageFinished(WebView view, String url) {
            if (view == top()) progress.setVisibility(View.GONE);
            if (errorView.getVisibility() != View.VISIBLE) splash.setVisibility(View.GONE);
            updateBars(view);
            CookieManager.getInstance().flush();
        }

        @Override
        public void onReceivedError(WebView view, WebResourceRequest req, WebResourceError err) {
            if (req.isForMainFrame()) showError(null, "ERR " + err.getErrorCode() + " · " + err.getDescription());
        }

        /**
         * فقط یک حالت با بررسی کامل پذیرفته می‌شود: گواهی همین سرور که زنجیره‌اش ناقص فرستاده شده (SSL_UNTRUSTED) و بعد از
         * دریافت گواهی واسط از نشانی رسمی داخل گواهی (AIA، مثل کروم) به یک ریشه‌ی معتبر اندروید می‌رسد. بقیه همیشه رد می‌شوند.
         */
        @Override
        public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
            final String host = Uri.parse(error.getUrl()).getHost();
            final String code = "SSL " + sslName(error);
            boolean onlyChain = error.hasError(SslError.SSL_UNTRUSTED) && !error.hasError(SslError.SSL_IDMISMATCH)
                    && !error.hasError(SslError.SSL_EXPIRED) && !error.hasError(SslError.SSL_NOTYETVALID)
                    && !error.hasError(SslError.SSL_DATE_INVALID) && serverHost.equalsIgnoreCase(host);
            final X509Certificate leaf = onlyChain ? x509(error.getCertificate()) : null;
            if (leaf == null) {
                handler.cancel();
                showError("گواهی امنیتی (SSL) سرور معتبر نیست؛ اتصال برای امنیت حساب شما برقرار نشد.", code);
                return;
            }
            new Thread(() -> {
                boolean ok;
                try { ok = AiaTrust.get().verifyLeaf(leaf, host); } catch (Exception e) { ok = false; }
                final boolean trusted = ok;
                runOnUiThread(() -> {
                    if (trusted) handler.proceed();
                    else {
                        handler.cancel();
                        showError("گواهی امنیتی (SSL) سرور کامل یا معتبر نیست؛ اتصال برای امنیت حساب شما برقرار نشد.", code + " (chain)");
                    }
                });
            }).start();
        }
    }

    private class Chrome extends WebChromeClient {
        @Override
        public void onProgressChanged(WebView view, int p) {
            if (view == top()) {
                progress.setProgress(p);
                progress.setVisibility(p >= 100 ? View.GONE : View.VISIBLE);
            }
        }

        @Override
        public boolean onCreateWindow(WebView view, boolean dialog, boolean gesture, Message msg) {
            WebView child = newWebView();
            stack.addView(child);
            webs.add(child);
            ((WebView.WebViewTransport) msg.obj).setWebView(child);
            msg.sendToTarget();
            return true;
        }

        @Override
        public void onCloseWindow(WebView w) {
            closeChild(w);
        }

        @Override
        public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> cb, FileChooserParams params) {
            if (fileCb != null) fileCb.onReceiveValue(null);
            fileCb = cb;
            Intent i = params.createIntent();
            i.addCategory(Intent.CATEGORY_OPENABLE);
            String[] types = params.getAcceptTypes();
            ArrayList<String> mimes = new ArrayList<>();
            for (String t : types) {
                for (String x : t.split(",")) {
                    x = x.trim();
                    if (x.contains("/")) mimes.add(x);
                }
            }
            i.setType(mimes.size() == 1 ? mimes.get(0) : "*/*");
            if (mimes.size() > 1) i.putExtra(Intent.EXTRA_MIME_TYPES, mimes.toArray(new String[0]));
            if (params.getMode() == FileChooserParams.MODE_OPEN_MULTIPLE) i.putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true);
            try {
                startActivityForResult(Intent.createChooser(i, "انتخاب فایل"), REQ_FILE);
            } catch (ActivityNotFoundException e) {
                fileCb = null;
                return false;
            }
            return true;
        }

        @Override
        public boolean onJsAlert(WebView view, String url, String message, JsResult result) {
            new AlertDialog.Builder(MainActivity.this).setMessage(message)
                    .setPositiveButton("باشه", (d, w) -> result.confirm())
                    .setOnCancelListener(d -> result.cancel()).show();
            return true;
        }

        @Override
        public boolean onJsConfirm(WebView view, String url, String message, JsResult result) {
            new AlertDialog.Builder(MainActivity.this).setMessage(message)
                    .setPositiveButton("بله", (d, w) -> result.confirm())
                    .setNegativeButton("خیر", (d, w) -> result.cancel())
                    .setOnCancelListener(d -> result.cancel()).show();
            return true;
        }
    }

    @Override
    protected void onActivityResult(int req, int res, Intent data) {
        if (req != REQ_FILE) {
            super.onActivityResult(req, res, data);
            return;
        }
        if (fileCb == null) return;
        Uri[] out = null;
        if (res == RESULT_OK && data != null) {
            ClipData clip = data.getClipData();
            if (clip != null && clip.getItemCount() > 0) {
                out = new Uri[clip.getItemCount()];
                for (int i = 0; i < out.length; i++) out[i] = clip.getItemAt(i).getUri();
            } else if (data.getData() != null) {
                out = new Uri[]{data.getData()};
            }
        }
        fileCb.onReceiveValue(out);
        fileCb = null;
    }

    private static X509Certificate x509(SslCertificate c) {
        if (c == null) return null;
        try {
            if (Build.VERSION.SDK_INT >= 29) return c.getX509Certificate();
            byte[] der = SslCertificate.saveState(c).getByteArray("x509-certificate");
            return der == null ? null : (X509Certificate) CertificateFactory.getInstance("X.509").generateCertificate(new ByteArrayInputStream(der));
        } catch (Exception e) {
            return null;
        }
    }

    private static String sslName(SslError e) {
        String[] n = {"NOTYETVALID", "EXPIRED", "IDMISMATCH", "UNTRUSTED", "DATE_INVALID", "INVALID"};
        StringBuilder b = new StringBuilder();
        for (int i = 0; i < n.length; i++) if (e.hasError(i)) b.append(b.length() > 0 ? "," : "").append(n[i]);
        return b.toString();
    }

    // ───────────────────────── دانلود ─────────────────────────

    private void download(String url, String ua, String cd, String mime) {
        if (url.startsWith("blob:") || url.startsWith("data:")) {
            Toast.makeText(this, "این نوع دانلود در برنامه پشتیبانی نمی‌شود.", Toast.LENGTH_SHORT).show();
            return;
        }
        if (Build.VERSION.SDK_INT < 29 && checkSelfPermission(Manifest.permission.WRITE_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED) {
            pendingDownload = new String[]{url, ua, cd, mime};
            requestPermissions(new String[]{Manifest.permission.WRITE_EXTERNAL_STORAGE}, REQ_STORAGE);
            return;
        }
        Downloader.start(this, url, ua, CookieManager.getInstance().getCookie(url), cd, mime);
    }

    @Override
    public void onRequestPermissionsResult(int req, String[] perms, int[] res) {
        if (req == REQ_STORAGE && pendingDownload != null) {
            String[] d = pendingDownload;
            pendingDownload = null;
            if (res.length > 0 && res[0] == PackageManager.PERMISSION_GRANTED) download(d[0], d[1], d[2], d[3]);
            else Toast.makeText(this, "برای ذخیره‌ی فایل، اجازه‌ی دسترسی به حافظه لازم است.", Toast.LENGTH_LONG).show();
        }
    }

    // ───────────────────────── خطا، نوار وضعیت، بازگشت ─────────────────────────

    private void showError(String desc, String code) {
        ((TextView) findViewById(R.id.error_desc)).setText(desc != null ? desc : getString(R.string.err_desc));
        ((TextView) findViewById(R.id.error_code)).setText(code == null ? "" : code + "\n" + server);
        errorView.setVisibility(View.VISIBLE);
        splash.setVisibility(View.GONE);
        progress.setVisibility(View.GONE);
    }

    private void retry() {
        errorView.setVisibility(View.GONE);
        WebView w = top();
        String u = w.getUrl();
        if (u == null || u.isEmpty() || u.startsWith("about:") || u.startsWith("data:")) w.loadUrl(server + "/");
        else w.reload();
    }

    /** رنگ نوار وضعیت و ناوبری = meta theme-color صفحه (تم تیره/روشن پنل) */
    private void updateBars(WebView w) {
        if (w != top()) return;
        w.evaluateJavascript("(function(){var m=document.querySelector('meta[name=theme-color]');return m?m.content:''})()", v -> {
            if (v == null) return;
            String c = v.replace("\"", "").trim();
            if (!c.startsWith("#")) return;
            int color;
            try { color = Color.parseColor(c); } catch (IllegalArgumentException e) { return; }
            Window win = getWindow();
            win.setStatusBarColor(color);
            win.setNavigationBarColor(color);
            findViewById(R.id.root).setBackgroundColor(color);
            double lum = (0.299 * Color.red(color) + 0.587 * Color.green(color) + 0.114 * Color.blue(color)) / 255;
            int flags = win.getDecorView().getSystemUiVisibility();
            int light = View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR | (Build.VERSION.SDK_INT >= 26 ? View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR : 0);
            win.getDecorView().setSystemUiVisibility(lum > 0.6 ? flags | light : flags & ~light);
        });
    }

    /** بازگشت: اول پنجره‌ی باز (modal) یا منوی کناری بسته می‌شود، بعد صفحه‌ی قبل، بعد لایه‌ی جدید، بعد خروج. */
    @Override
    public void onBackPressed() {
        if (webs.isEmpty()) { super.onBackPressed(); return; }
        final WebView w = top();
        if (errorView.getVisibility() == View.VISIBLE) {
            errorView.setVisibility(View.GONE);
            if (w.canGoBack()) { w.goBack(); return; }
            if (webs.size() > 1) { closeChild(w); return; }
            finish();
            return;
        }
        String js = "(function(){try{"
                + "var m=[].slice.call(document.querySelectorAll('.modal.open')).pop();"
                + "if(m&&window.TK&&TK.modal){TK.modal.tryClose(m);return 1}"
                + "var s=document.querySelector('.sidebar.open');"
                + "if(s){s.classList.remove('open');var o=document.querySelector('.overlay');if(o)o.classList.remove('show');return 1}"
                + "var d=document.querySelectorAll('.dropdown.open,.qa.open');"
                + "if(d.length){[].forEach.call(d,function(x){x.classList.remove('open')});return 1}"
                + "}catch(e){}return 0})()";
        w.evaluateJavascript(js, v -> {
            if ("1".equals(v)) return;
            if (w.canGoBack()) w.goBack();
            else if (webs.size() > 1) closeChild(w);
            else finish();
        });
    }

    @Override
    protected void onSaveInstanceState(Bundle out) {
        super.onSaveInstanceState(out);
        if (!webs.isEmpty()) webs.get(0).saveState(out);
    }

    @Override
    protected void onPause() {
        super.onPause();
        CookieManager.getInstance().flush();
        for (WebView w : webs) w.onPause();
    }

    @Override
    protected void onResume() {
        super.onResume();
        for (WebView w : webs) w.onResume();
    }

    @Override
    protected void onDestroy() {
        for (WebView w : webs) w.destroy();
        webs.clear();
        super.onDestroy();
    }
}
