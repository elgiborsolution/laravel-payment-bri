<?php

namespace ESolution\BriPayments\Support;

use Illuminate\Support\Str;
use RuntimeException;

class SnapSignature
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config ?: (config('bri') ?? []);
    }

    public function iso8601Now(): string { return now()->format('Y-m-d\TH:i:sP'); }

    public function rsaHeaders(string $timestamp): array
    {
        $clientId = $this->config['common']['client_id'];
        if(!empty($this->config['common']['private_key'])){

            $pkey = openssl_pkey_get_private($this->config['common']['private_key']);
        }else{

            $privatePath = base_path($this->config['common']['private_key_path']??'/private.key.pem');
            if (!file_exists($privatePath)) throw new RuntimeException('SNAP private key not found: '.$privatePath);
            $pkey = openssl_pkey_get_private(file_get_contents($privatePath));
            if (!$pkey) throw new RuntimeException('Invalid SNAP private key');   
        }
        openssl_sign($clientId.'|'.$timestamp, $sig, $pkey, OPENSSL_ALGO_SHA256);
        return [
            'X-SIGNATURE' => base64_encode($sig),
            'X-CLIENT-KEY' => $clientId,
            'X-TIMESTAMP' => $timestamp,
            'Content-Type' => 'application/json',
        ];
    }

    public function businessHeaders(string $accessToken, string $timestamp, string $path, array $body): array
    {
        $secret = $this->config['common']['client_secret'];
        $partnerId = $this->config['qris']['partner_id'];
        $channelId = $this->config['qris']['channel_id'];
        $externalId = (string) Str::uuid();
        $minified = json_encode($body, JSON_UNESCAPED_SLASHES);
        $bodyHash = hash('sha256', $minified);
        $stringToSign = implode(':', ['POST', $path, $accessToken, strtolower($bodyHash), $timestamp]);
        $hmac = hash_hmac('sha512', $stringToSign, $secret);
        return [
            'Authorization' => 'Bearer '.$accessToken,
            'X-TIMESTAMP'   => $timestamp,
            'X-SIGNATURE'   => $hmac,
            'Content-Type'  => 'application/json',
            'X-PARTNER-ID'  => $partnerId,
            'CHANNEL-ID'    => (string)$channelId,
            'X-EXTERNAL-ID' => $externalId,
        ];
    }

    public function qrisBusinessHeaders(string $accessToken, string $path, array $body, $method='POST'): array
    {
        $timestamp = $this->iso8601Now();
        $secret = $this->config['common']['client_secret'];
        $partnerId = $this->config['qris']['partner_id'];
        $channelId = $this->config['qris']['channel_id'];
        $externalId = str_pad((string)random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        $minified = json_encode($body, JSON_UNESCAPED_SLASHES);
        $bodyHash = hash('sha256', $minified);
        $stringToSign = implode(':', [$method, $path, $accessToken, strtolower($bodyHash), $timestamp]);
        $hmac = hash_hmac('sha512', $stringToSign, $secret);
        return [
            'Authorization' => 'Bearer '.$accessToken,
            'X-TIMESTAMP'   => $timestamp,
            'X-SIGNATURE'   => $hmac,
            'Content-Type'  => 'application/json',
            'X-PARTNER-ID'  => $partnerId,
            'CHANNEL-ID'    => (string)$channelId,
            'X-EXTERNAL-ID' => $externalId,
        ];
    }

    public function brivaBusinessHeaders(string $accessToken, string $path, array $body, $method='POST'): array
    {
        $timestamp = $this->iso8601Now();
        $secret = $this->config['common']['client_secret'];
        $partnerId = $this->config['briva']['partner_id'];
        $channelId = $this->config['briva']['channel_id'];
        $externalId = str_pad((string)random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        $minified = json_encode($body, JSON_UNESCAPED_SLASHES);
        $bodyHash = hash('sha256', $minified);
        $stringToSign = implode(':', [$method, $path, $accessToken, strtolower($bodyHash), $timestamp]);
        $hmac = hash_hmac('sha512', $stringToSign, $secret);
        return [
            'Authorization' => 'Bearer '.$accessToken,
            'X-TIMESTAMP'   => $timestamp,
            'X-SIGNATURE'   => $hmac,
            'Content-Type'  => 'application/json',
            'X-PARTNER-ID'  => $partnerId,
            'CHANNEL-ID'    => (string)$channelId,
            'X-EXTERNAL-ID' => $externalId,
        ];
    }

    public function verifyRsa(string $stringToSign, string $signatureBase64): bool
    {

        if(!empty($this->config['qris']['public_key'])){

            $pub = openssl_pkey_get_public($this->config['qris']['public_key']);
        }else{
            
            $publicPath = $this->config['qris']['public_key_path'] ? base_path($this->config['qris']['public_key_path']) : null;
            if (!$publicPath || !file_exists($publicPath)) return false;
            $pub = openssl_pkey_get_public(file_get_contents($publicPath));
        }
        if (!$pub) return false;
        $ok = openssl_verify($stringToSign, base64_decode($signatureBase64), $pub, OPENSSL_ALGO_SHA256);
        return $ok === 1;

    }


    /**
     * Generate Symmetric API Signature
     *
     * @param string $httpMethod  HTTP method, e.g. GET, POST
     * @param string $endpoint    Endpoint path, e.g. /snap/v1.0/dummy
     * @param string $accessToken Access token from Authorization header
     * @param string $clientSecret Client secret
     * @param array|string $body  Request body
     * @param string|null $timestamp Optional, ISO8601 timestamp. If null, will use current UTC time.
     *
     * @return string
     */
        public function generateSignature(
            string $endpoint,
            string $httpMethod,
            string $accessToken,
            string $clientSecret,
            $bodyRaw = '',
            ?string $timestamp = null
        ): string {
            // 1. Timestamp
            if (!$timestamp) {
                $timestamp = now()->format('Y-m-d\TH:i:sP');
            }

            $bodyString = '';

            if (!empty($bodyRaw)) {

                // Decode JSON
                $decoded = json_decode($bodyRaw, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {

                    // Tambahkan 3 spasi jika ada field
                    foreach (['partnerServiceId', 'virtualAccountNo'] as $key) {
                        if (isset($decoded[$key]) && is_string($decoded[$key])) {
                            if (!str_starts_with($decoded[$key], '   ')) {
                                $decoded[$key] = '   ' . ltrim($decoded[$key]);
                            }
                        }
                    }

                    // MINIFY RESMI → json_encode ulang (tanpa indent)
                    $minBody = json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                } else {
                    // Fallback — body bukan JSON
                    $minBody = trim((string) $bodyRaw);
                }

                // SHA-256 lowercase hex
                $bodyString = hash('sha256', $minBody);

            }

            // 3. Build stringToSign
            $stringToSign = sprintf(
                '%s:%s:%s:%s:%s',
                strtoupper($httpMethod),
                $endpoint,
                $accessToken,
                $bodyString,
                $timestamp
            );


            // 4. HMAC-SHA512 → BASE64
            return base64_encode(
                hash_hmac('sha512', $stringToSign, $clientSecret, true)
            );
        }

}
