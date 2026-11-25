<?php
declare(strict_types=1);
namespace KarlixLaravel\Services\Meetings;

use App\Core\HttpException;
use App\Core\LiveKitConfig as Config;

/** رابط JSON رسمی RoomService (Twirp)؛ بدون Node یا SDK غیررسمی PHP روی هاست. */
class LiveKitClient
{
    public function requireConfigured(): void
    {
        if (!Config::configured()) { throw new HttpException(503, 'جلسات آنلاین هنوز تنظیم نشده‌اند؛ با مدیر سامانه تماس بگیرید.', 'MEETING_NOT_CONFIGURED'); }
    }
    public function url(): string { $this->requireConfigured(); return (string) Config::url(); }
    private static function b64(string $v): string { return rtrim(strtr(base64_encode($v), '+/', '-_'), '='); }
    public function token(array $claims): string
    {
        $this->requireConfigured();
        $claims = ['iss' => (string) env('LIVEKIT_API_KEY'), 'iat' => time(), 'nbf' => time() - 5, 'exp' => time() + Config::TOKEN_SECONDS] + $claims;
        $body = self::b64('{"alg":"HS256","typ":"JWT"}') . '.' . self::b64(json_encode($claims, JSON_THROW_ON_ERROR));
        return $body . '.' . self::b64(hash_hmac('sha256', $body, (string) env('LIVEKIT_API_SECRET'), true));
    }
    public function call(string $method, array $data, array $grant): array
    {
        $this->requireConfigured();
        if (!in_array($method, ['CreateRoom','ListRooms','DeleteRoom','ListParticipants','RemoveParticipant','UpdateParticipant','MutePublishedTrack'], true)) { throw new \LogicException('Unsupported LiveKit API'); }
        $endpoint = str_replace('wss://', 'https://', $this->url()) . '/twirp/livekit.RoomService/' . $method;
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $this->token(['video' => $grant])];
        $body = json_encode((object) $data, JSON_THROW_ON_ERROR);
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
            $raw = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        } else {
            // روی هاست بدون cURL، همان API با جریان HTTPS و اعتبارسنجی گواهی اجرا می‌شود.
            $context = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body,
                'timeout' => 10, 'ignore_errors' => true, 'follow_location' => 0], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $http_response_header = [];
            $raw = @file_get_contents($endpoint, false, $context, 0, 1048576);
            $status = preg_match('~^HTTP/\S+ (\d{3})~', $http_response_header[0] ?? '', $match) ? (int) $match[1] : 0;
        }
        $out = is_string($raw) ? json_decode($raw, true) : null;
        // RemoveParticipant حتی برای فرد خارج‌شده، توکن قبلی را در Cloud باطل می‌کند.
        if ($status === 404 && in_array($method, ['RemoveParticipant','DeleteRoom'], true) && ($out['code'] ?? '') === 'not_found') { return []; }
        if ($status < 200 || $status >= 300 || !is_array($out)) {
            \App\Core\Log::warning('meeting.provider', ['method' => $method, 'http' => $status]);
            throw new HttpException(503, 'ارتباط با سرویس جلسه برقرار نشد؛ دوباره تلاش کنید.', 'MEETING_PROVIDER_ERROR');
        }
        return $out;
    }
    public function verifyWebhook(string $raw, string $authorization): array
    {
        $this->requireConfigured();
        $parts = explode('.', preg_replace('/^Bearer\s+/i', '', trim($authorization)));
        $fail = static function (): never { throw new HttpException(401, 'امضای اعلان جلسه معتبر نیست.', 'WEBHOOK_INVALID'); };
        if (count($parts) !== 3) { $fail(); }
        $decode = static fn($v) => base64_decode(strtr($v, '-_', '+/'), true);
        $head = json_decode((string) $decode($parts[0]), true); $claims = json_decode((string) $decode($parts[1]), true);
        $sig = $decode($parts[2]);
        if (($head['alg'] ?? '') !== 'HS256' || !is_array($claims) || !is_string($sig)
            || !hash_equals(hash_hmac('sha256', $parts[0] . '.' . $parts[1], (string) env('LIVEKIT_API_SECRET'), true), $sig)
            || ($claims['iss'] ?? '') !== (string) env('LIVEKIT_API_KEY') || (int) ($claims['exp'] ?? 0) < time()
            || (int) ($claims['nbf'] ?? 0) > time() + 5 || !hash_equals(base64_encode(hash('sha256', $raw, true)), (string) ($claims['sha256'] ?? ''))) { $fail(); }
        $event = json_decode($raw, true);
        if (!is_array($event) || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', (string) ($event['id'] ?? ''))) { $fail(); }
        return $event;
    }
}
