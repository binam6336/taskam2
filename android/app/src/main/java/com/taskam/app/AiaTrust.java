package com.taskam.app;

import java.io.ByteArrayInputStream;
import java.io.ByteArrayOutputStream;
import java.io.InputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.security.KeyStore;
import java.security.cert.CertificateException;
import java.security.cert.CertificateFactory;
import java.security.cert.X509Certificate;
import java.util.ArrayList;
import java.util.Arrays;
import java.util.Collection;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;

import javax.net.ssl.TrustManager;
import javax.net.ssl.TrustManagerFactory;
import javax.net.ssl.X509TrustManager;

/**
 * بررسی گواهی سرور با مخزن مرجع‌های خود اندروید، به‌علاوه‌ی تکمیل زنجیره‌ی ناقص (AIA).
 * <p>
 * اگر سرور گواهی واسط (intermediate) را نفرستد، کروم آن را از نشانی «CA Issuers» داخل گواهی دانلود می‌کند و سایت باز می‌شود،
 * ولی WebView و DownloadManager این کار را نمی‌کنند و اتصال رد می‌شود. این کلاس همان کار کروم را انجام می‌دهد:
 * گواهی واسط را از نشانی رسمی داخل گواهی می‌گیرد و زنجیره‌ی کامل را دوباره با مخزن مرجع‌های سیستم بررسی می‌کند.
 * چیزی بدون بررسی پذیرفته نمی‌شود: گواهی خودامضا، منقضی، با نام نادرست یا با ریشه‌ی نامعتبر همچنان رد می‌شود.
 */
final class AiaTrust implements X509TrustManager {
    private static final byte[] CA_ISSUERS_OID = {0x06, 0x08, 0x2B, 0x06, 0x01, 0x05, 0x05, 0x07, 0x30, 0x02};
    private static AiaTrust instance;

    private final X509TrustManager sys;
    private final Map<String, Collection<? extends java.security.cert.Certificate>> cache = new ConcurrentHashMap<>();

    private AiaTrust(X509TrustManager sys) {
        this.sys = sys;
    }

    static synchronized AiaTrust get() throws Exception {
        if (instance == null) {
            TrustManagerFactory f = TrustManagerFactory.getInstance(TrustManagerFactory.getDefaultAlgorithm());
            f.init((KeyStore) null);
            for (TrustManager t : f.getTrustManagers()) {
                if (t instanceof X509TrustManager) {
                    instance = new AiaTrust((X509TrustManager) t);
                    break;
                }
            }
            if (instance == null) throw new IllegalStateException("no system X509TrustManager");
        }
        return instance;
    }

    @Override
    public void checkClientTrusted(X509Certificate[] chain, String authType) throws CertificateException {
        sys.checkClientTrusted(chain, authType);
    }

    @Override
    public void checkServerTrusted(X509Certificate[] chain, String authType) throws CertificateException {
        try {
            sys.checkServerTrusted(chain, authType);
        } catch (CertificateException e) {
            X509Certificate[] full = complete(chain);
            if (full == null) throw e;
            sys.checkServerTrusted(full, authType);
        }
    }

    @Override
    public X509Certificate[] getAcceptedIssuers() {
        return sys.getAcceptedIssuers();
    }

    /** برای WebView که فقط گواهی اصلی (leaf) را می‌دهد: اعتبار زمانی + نام دامنه + زنجیره‌ی تکمیل‌شده تا یک ریشه‌ی معتبر سیستم. */
    boolean verifyLeaf(X509Certificate leaf, String host) {
        try {
            leaf.checkValidity();
            if (!matchesHost(leaf, host)) return false;
            checkServerTrusted(new X509Certificate[]{leaf}, "EC".equals(leaf.getPublicKey().getAlgorithm()) ? "ECDHE_ECDSA" : "ECDHE_RSA");
            return true;
        } catch (Exception e) {
            return false;
        }
    }

    /** زنجیره + گواهی‌های واسط دانلودشده از AIA (حداکثر ۳ سطح)؛ null اگر چیزی اضافه نشد. */
    private X509Certificate[] complete(X509Certificate[] chain) {
        if (chain == null || chain.length == 0) return null;
        List<X509Certificate> out = new ArrayList<>(Arrays.asList(chain));
        X509Certificate last = out.get(out.size() - 1);
        for (int depth = 0; depth < 3; depth++) {
            if (last.getSubjectX500Principal().equals(last.getIssuerX500Principal())) break;   // خودامضا
            String url = caIssuersUrl(last);
            if (url == null) break;
            Collection<? extends java.security.cert.Certificate> got = fetch(url);
            if (got == null) break;
            X509Certificate next = null;
            for (java.security.cert.Certificate c : got) {
                if (c instanceof X509Certificate && !out.contains(c)) {
                    out.add((X509Certificate) c);
                    if (next == null) next = (X509Certificate) c;
                }
            }
            if (next == null) break;
            last = next;
        }
        return out.size() > chain.length ? out.toArray(new X509Certificate[0]) : null;
    }

    private Collection<? extends java.security.cert.Certificate> fetch(String url) {
        Collection<? extends java.security.cert.Certificate> c = cache.get(url);
        if (c != null) return c;
        HttpURLConnection con = null;
        try {
            con = (HttpURLConnection) new URL(url).openConnection();
            con.setConnectTimeout(8000);
            con.setReadTimeout(8000);
            if (con.getResponseCode() != 200) return null;
            InputStream in = con.getInputStream();
            ByteArrayOutputStream buf = new ByteArrayOutputStream();
            byte[] b = new byte[4096];
            int r;
            while ((r = in.read(b)) > 0) {
                buf.write(b, 0, r);
                if (buf.size() > 65536) return null;
            }
            c = CertificateFactory.getInstance("X.509").generateCertificates(new ByteArrayInputStream(buf.toByteArray()));
            if (c != null && !c.isEmpty()) cache.put(url, c);
            return c;
        } catch (Exception e) {
            return null;
        } finally {
            if (con != null) con.disconnect();
        }
    }

    /** نشانی «CA Issuers» از افزونه‌ی Authority Information Access (فقط http/https). */
    static String caIssuersUrl(X509Certificate cert) {
        byte[] ext = cert.getExtensionValue("1.3.6.1.5.5.7.1.1");
        if (ext == null) return null;
        outer:
        for (int i = 0; i + CA_ISSUERS_OID.length + 2 < ext.length; i++) {
            for (int j = 0; j < CA_ISSUERS_OID.length; j++) {
                if (ext[i + j] != CA_ISSUERS_OID[j]) continue outer;
            }
            int p = i + CA_ISSUERS_OID.length;
            if ((ext[p] & 0xff) != 0x86) continue;   // [6] uniformResourceIdentifier
            int len = ext[p + 1] & 0xff, start = p + 2;
            if (len == 0x81 && start < ext.length) { len = ext[start] & 0xff; start++; }
            else if (len > 0x7f) continue;
            if (start + len > ext.length) continue;
            String url = new String(ext, start, len, java.nio.charset.StandardCharsets.US_ASCII);
            if (url.startsWith("http://") || url.startsWith("https://")) return url;
        }
        return null;
    }

    /** نام دامنه در SAN گواهی (با پشتیبانی wildcard یک‌سطحی). */
    static boolean matchesHost(X509Certificate cert, String host) {
        if (host == null) return false;
        host = host.toLowerCase(Locale.ROOT);
        try {
            Collection<List<?>> sans = cert.getSubjectAlternativeNames();
            if (sans == null) return false;
            for (List<?> san : sans) {
                if (san.size() < 2 || !Integer.valueOf(2).equals(san.get(0))) continue;
                String name = String.valueOf(san.get(1)).toLowerCase(Locale.ROOT);
                if (name.equals(host)) return true;
                if (name.startsWith("*.")) {
                    int dot = host.indexOf('.');
                    if (dot > 0 && host.substring(dot + 1).equals(name.substring(2))) return true;
                }
            }
        } catch (Exception ignored) { }
        return false;
    }
}
