<?php
declare(strict_types=1);

namespace App\Core;

/** نمای یکپارچه از درخواست جاری (query، body، JSON، فایل‌ها، هدرها). */
final class Request
{
    private static ?Request $instance = null;
    private ?array $json = null;

    public array $query;
    public array $post;
    public array $files;
    public array $server;
    public array $params = [];

    private function __construct()
    {
        $this->query = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->server = $_SERVER;
    }

    public static function capture(): self
    {
        return self::$instance ??= new self();
    }

    public function method(): string
    {
        return strtoupper((string) ($this->server['REQUEST_METHOD'] ?? 'GET'));
    }

    public function isSafe(): bool
    {
        return in_array($this->method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    /** مسیر تمیز (بدون base و بدون اسلش انتهایی)، همیشه با / شروع می‌شود. */
    public function path(): string
    {
        $uri = (string) (parse_url((string) ($this->server['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        $uri = rawurldecode($uri);
        $base = base_url();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/public/')) {
            $uri = substr($uri, 7);
        }
        if (str_ends_with($uri, '/index.php')) {
            $uri = substr($uri, 0, -10);
        }
        $uri = '/' . trim($uri, '/');
        return $uri;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $v = $this->server[$key] ?? null;
        if ($v === null && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $val) {
                if (strcasecmp((string) $k, $name) === 0) {
                    $v = $val;
                    break;
                }
            }
        }
        return $v !== null ? trim((string) $v) : null;
    }

    public function isJsonBody(): bool
    {
        return stripos((string) ($this->server['CONTENT_TYPE'] ?? ''), 'application/json') !== false;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path(), '/api/');
    }

    public function wantsJson(): bool
    {
        return $this->isApi() || $this->isAjax() || $this->isJsonBody()
            || stripos((string) $this->header('Accept'), 'application/json') !== false;
    }

    /** بدنه‌ی JSON (در صورت وجود). */
    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }
        $this->json = [];
        if ($this->isJsonBody() || (empty($this->post) && !$this->isSafe())) {
            $raw = (string) file_get_contents('php://input');
            if ($raw !== '') {
                $d = json_decode($raw, true);
                if (is_array($d)) {
                    $this->json = $d;
                } elseif (!$this->isJsonBody()) {
                    parse_str($raw, $parsed);
                    if (is_array($parsed)) {
                        $this->json = $parsed;
                    }
                }
            }
        }
        return $this->json;
    }

    /** ورودی از body (POST یا JSON) و سپس query. */
    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        $j = $this->json();
        if (array_key_exists($key, $j)) {
            return $j[$key];
        }
        return $this->query[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return $this->input($key, null) !== null;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->json(), $this->post);
    }

    public function str(string $key, int $max = 0, string $default = ''): string
    {
        $v = $this->input($key, $default);
        if (is_array($v)) {
            return $default;
        }
        $v = trim((string) $v);
        return $max > 0 ? mb_substr($v, 0, $max, 'UTF-8') : $v;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? (int) $v : $default;
    }

    /** آرایه‌ای از مقادیر (مثلاً چک‌باکس‌ها). */
    public function arr(string $key): array
    {
        $v = $this->input($key, []);
        return is_array($v) ? $v : [];
    }

    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        if (self::fromTrustedProxy($remote)) {
            $h = $this->header('CF-Connecting-IP') ?: $this->header('X-Forwarded-For');
            if ($h) {
                $ip = trim(explode(',', $h)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $remote;
    }

    /** محدوده‌های IP کلودفلر (https://www.cloudflare.com/ips/) برای TRUSTED_PROXY=cloudflare */
    private const CLOUDFLARE = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
        '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /**
     * هدرهای IP واقعی/HTTPS فقط وقتی پذیرفته می‌شوند که درخواست واقعاً از پراکسی مورد اعتماد آمده باشد (وگرنه هر کس IP خودش را جعل می‌کند):
     * TRUSTED_PROXY=cloudflare → فقط IPهای کلودفلر؛ true → فقط فهرست TRUSTED_PROXY_IPS (IP یا CIDR با کاما)،
     * و اگر فهرست خالی باشد فقط پراکسی محلی (loopback/شبکه‌ی خصوصی، مثل Nginx روی همان سرور).
     */
    public static function fromTrustedProxy(?string $remote = null): bool
    {
        $mode = strtolower(trim((string) env('TRUSTED_PROXY', 'false')));
        if (in_array($mode, ['', 'false', '0', 'off', 'no'], true)) {
            return false;
        }
        $remote = $remote ?? (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($mode === 'cloudflare') {
            $list = self::CLOUDFLARE;
        } else {
            $list = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXY_IPS', '')))));
            if (!$list) {
                return filter_var($remote, FILTER_VALIDATE_IP) !== false
                    && filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
            }
        }
        foreach ($list as $cidr) {
            if (self::inCidr($remote, $cidr)) {
                return true;
            }
        }
        return false;
    }

    public static function inCidr(string $ip, string $cidr): bool
    {
        [$net, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        $a = @inet_pton($ip);
        $b = @inet_pton((string) $net);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) {
            return false;
        }
        $bits = $bits === null ? strlen($a) * 8 : (int) $bits;
        $bytes = intdiv($bits, 8);
        if (substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        $rem = $bits % 8;
        if ($rem === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
    }

    /**
     * دو بخش اول IP (شبکه‌ی دستگاه): 5.120.33.7 → 5.120؛ IPv6: دو گروه اول (2a01:5ec0).
     */
    public function ipPrefix(?string $ip = null): string
    {
        $ip = $ip ?? $this->ip();
        if (preg_match('/^(?:::ffff:)?(\d{1,3})\.(\d{1,3})\.\d{1,3}\.\d{1,3}$/i', $ip, $m)) {
            return $m[1] . '.' . $m[2];
        }
        $bin = @inet_pton($ip);
        if ($bin !== false && strlen($bin) === 16) {
            $g = unpack('n8', $bin);
            return dechex($g[1]) . ':' . dechex($g[2]);
        }
        return $ip;
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255, 'UTF-8');
    }

    /** توکن API از Authorization: Bearer یا X-API-Key (و در صورت فعال‌بودن، ?token). */
    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization') ?? ($this->server['REDIRECT_HTTP_AUTHORIZATION'] ?? null);
        if ($auth) {
            if (preg_match('/^Bearer\s+(\S+)/i', (string) $auth, $m)) {
                return $m[1];
            }
        }
        $key = $this->header('X-API-Key');
        if ($key) {
            return $key;
        }
        if (env('API_ALLOW_QUERY_TOKEN', false)) {
            $t = $this->query['token'] ?? ($this->json()['token'] ?? null);
            if (is_string($t) && $t !== '') {
                return $t;
            }
        }
        return null;
    }

    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        return is_array($f) && isset($f['error']) && $f['error'] !== UPLOAD_ERR_NO_FILE ? $f : null;
    }
}
