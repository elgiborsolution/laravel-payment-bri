
<?php

namespace ESolution\BriPayments\Support;

class NonSnapSignature
{
    public function __construct(protected array $config) {}

    public function iso8601UtcNow(): string
    {
        return now()->setTimezone('UTC')->format('Y-m-d\TH:i:s.\0\0\0\Z');
    }

    public function buildSignature(string $path, string $verb, string $token, string $timestamp, string $body = ''): string
    {
        $secret = $this->config['common']['client_secret'];
        $payload = 'path=' . $path . '&verb=' . strtoupper($verb) . '&token=' . $token . '&timestamp=' . $timestamp . '&body=' . $body;
        return base64_encode(hash_hmac('sha256', $payload, $secret, true));
    }

    public function minifyJson(array $arr): string
    {
        return json_encode($arr, JSON_UNESCAPED_SLASHES);
    }
}
